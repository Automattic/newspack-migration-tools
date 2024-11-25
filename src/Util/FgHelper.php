<?php
/**
 * Helper for the great FG migration plugins.
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
		if ( defined( 'NCCM_FG_MIGRATOR_PREFIX' ) && ! empty( NCCM_FG_MIGRATOR_PREFIX ) ) {
			$this->db_import_tables_prefix = NCCM_FG_MIGRATOR_PREFIX;
		}

		if ( ! defined( 'NCCM_SOURCE_WEBSITE_URL' ) ) {
			NMT::exit_with_message( 'NCCM_SOURCE_WEBSITE_URL is not defined in wp-config.php' );
		}

		$this->type = $type;
	}

	/**
	 * Add filter for options.
	 */
	private function add_hooks(): void {
		add_filter( "option_{$this->function_prefix}_options", [ $this, 'filter_options' ] );
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
	 * Override the database connection details with environment variables.
	 *
	 * @param array $options Options for the fg plugin.
	 */
	public function filter_options( $options ): array {
		$options['hostname'] = getenv( 'DB_HOST' );
		$options['database'] = getenv( 'DB_NAME' );
		$options['username'] = getenv( 'DB_USER' );
		$options['password'] = getenv( 'DB_PASSWORD' );

		if ( empty( $options['hostname'] ) || empty( $options['database'] ) || empty( $options['username'] ) || empty( $options['password'] ) ) {
			NMT::exit_with_message( 'Could not get database connection details from environment variables.' );
		}

		$options['prefix'] = $this->db_import_tables_prefix;

		return $options;
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
