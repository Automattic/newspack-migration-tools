<?php
/**
 * Helper for the great FG migration plugins.
 *
 * See ../../docs/fg-helper.md for more information.
 */

namespace Newspack\MigrationTools\Util;

use Newspack\MigrationTools\NMT;

class FgHelper {

	/** CMS we are migrating from.
	 *
	 * @var string Type of CMS we are migrating from.
	 */
	private string $type;

	/**
	 * The FG plugins use a prefix based on the CMS it's migrating from, so this holds that for calling functions.
	 *
	 * @var string Prefix for the function names.
	 */
	private string $function_prefix;

	/**
	 * The prefix for the import tables in the database. Will default to the CMS type, but can be overridden by a constant in your setup.
	 *
	 * @var string Prefix for the import tables.
	 */
	private string $db_import_tables_prefix;

	public function __construct( string $type ) {

		$type = strtolower( $type );
		if ( ! in_array( $type, [ 'drupal', 'joomla' ] ) ) {
			NMT::exit_with_message( sprintf( 'Invalid migration type "%s" is not supported', $type ) );
		}

		$supported_cms = [ 'drupal', 'joomla' ];
		if ( ! in_array( $type, $supported_cms ) ) {
			NMT::exit_with_message( sprintf( 'Invalid migration type "%s". Only %s are supported as of now.', $type, implode( $supported_cms ) ) );
		}

		switch ( $type ) {
			case 'drupal':
				$this->type                    = 'drupal';
				$this->function_prefix         = 'fgd2wp';
				$this->db_import_tables_prefix = 'drupal_';
				break;
			case 'joomla':
				$this->type                    = 'joomla';
				$this->function_prefix         = 'fgj2wp';
				$this->db_import_tables_prefix = 'joomla_';
				break;
		}

		// If a constant is defined, use it as the prefix for the import tables.
		// @todo: allow blank - verify with Camilla (CarsonNow & UgObserver)
		if ( defined( 'NCCM_FG_MIGRATOR_PREFIX' ) ) {
			$this->db_import_tables_prefix = NCCM_FG_MIGRATOR_PREFIX;
		}

		if ( ! defined( 'NCCM_SOURCE_WEBSITE_URL' ) ) {
			NMT::exit_with_message( 'NCCM_SOURCE_WEBSITE_URL is not defined in wp-config.php' );
		}

		if ( ! is_plugin_active( "fg-{$this->type}-to-wp-premium/fg-{$this->type}-to-wp-premium.php" ) ) {
			NMT::exit_with_message( 'FG {$this->type} to WP Premium plugin not found. Install and activate it before using this class.' );
		}

		$this->type = $type;
	}

	/**
	 * Add filter for options.
	 */
	private function add_hooks(): void {
		// Filter if option values already exist in db.
		add_filter( "option_{$this->function_prefix}_options", [ $this, 'filter_options' ] );
		// Filter if option values do not exist in db. (Needed otherwise wordpress won't filter the options).
		add_filter( "default_option_{$this->function_prefix}_options", [ $this, 'filter_options' ] );		
	}

	/**
	 * Run the import.
	 *
	 * We simply wrap the import command from FG Joomla and add our hooks before running the import.
	 * Note that we can't batch this at all, so timeouts might be a thing.
	 *
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function import( array $pos_args, array $assoc_args ): void {
		$this->add_hooks();

		do_action( 'fg_helper_pre_import', [ $pos_args, $assoc_args ] );

		// Note that the 'launch' arg is important – without it the hooks above will not be registered.
		\WP_CLI::runcommand( "import-$this->type import", [ 'launch' => false ] );
	}

	/**
	 * Filter the options for the FG plugin.
	 * 
	 * For database environment variables put these variables in your .env file locally.
	 * 
	 * @param  array|false $options The options array to filter or boolean false if database option doesn't exist.
	 * @return array                The filtered options.
	 */
	public function filter_options( array|false $options ): array {
		
		// For when options don't exist yet in the db.
		if( false === $options ) $options = [];

		$options['hostname'] = getenv( 'DB_HOST' );
		$options['database'] = getenv( 'DB_NAME' );
		$options['username'] = getenv( 'DB_USER' );
		$options['password'] = getenv( 'DB_PASSWORD' );
		if ( empty( $options['hostname'] ) || empty( $options['database'] ) || ! isset( $options['username'] ) || ! isset( $options['password'] ) ) {
			NMT::exit_with_message( 'Could not get database connection details from environment variables.' );
		}

		$options['prefix'] = $this->get_import_tables_prefix();

		$options['url'] = NCCM_SOURCE_WEBSITE_URL;

		// filter by reference
		if ( 'drupal' == $this->type ) $this->filter_options_drupal( $options );
		
		return $options;
	}

	/**
	 * Filter Drupal specific options by reference
	 */
	private function filter_options_drupal( &$options ) {

		// Default values from FG plugin version 3.85.2
		// $this->plugin_options = array(
		// 	'automatic_empty'			=> 0,
		// 	'url'						=> null,
		// 	'download_protocol'			=> 'http',
		// 	'base_dir'					=> '',
		// 	'driver'					=> 'mysql',
		// 	'hostname'					=> 'localhost',
		// 	'port'						=> 3306,
		// 	'database'					=> null,
		// 	'username'					=> 'root',
		// 	'password'					=> '',
		// 	'sqlite_file'				=> '',
		// 	'prefix'					=> '',
		// 	'summary'					=> 'in_content',
		// 	'skip_media'				=> 0,
		// 	'file_public_path_source'	=> 'default',
		// 	'file_public_path'			=> 'sites/default/files',
		// 	'file_private_path_source'	=> 'default',
		// 	'file_private_path'			=> 'sites/default/private/files',
		// 	'featured_image'			=> 'featured',
		// 	'only_featured_image'		=> 0,
		// 	'remove_first_image'		=> 0,
		// 	'skip_thumbnails'			=> 0,
		// 	'import_external'			=> 0,
		// 	'import_duplicates'			=> 0,
		// 	'force_media_import'		=> 0,
		// 	'timeout'					=> 20,
		// 	'logger_autorefresh'		=> 1,
		// );


		// Keep default: 'force_media_import' => 0 so that already downloaded images aren't fetched again from Live site.
		$options['force_media_import'] = 0;

		// @todo Should this go into Publisher specific migrator instead?
		$options['summary'] = 'in_excerpt'; // otherwise excerpt will go in top of content with <!--more--> link

		// @todo should we turn this on for images with the same filenames?
		// how are these store in drupal? in wordpress the same filename could be used if in different /year/mon/ folders...
		// but what about if the import was restarted...will images be fetched again and given unique -abc at the end?
		// import_duplicates = 1;
	}

	/**
	 * Get the DB prefix.
	 *
	 * @return string
	 */
	public function get_import_tables_prefix(): string {
		return $this->db_import_tables_prefix;
	}
}
