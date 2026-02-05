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

			default:
				NMT::exit_with_message( sprintf( 'Invalid migration type "%s". Only Joomla and Drupal are supported as of now.', $type ) );
		}

		// If a constant is defined, use it as the prefix for the import tables. (Blank prefix is OK).
		if ( defined( 'NCCM_FG_MIGRATOR_PREFIX' ) ) {
			$this->db_import_tables_prefix = NCCM_FG_MIGRATOR_PREFIX;
		}

		if ( ! defined( 'NCCM_SOURCE_WEBSITE_URL' ) ) {
			NMT::exit_with_message( 'NCCM_SOURCE_WEBSITE_URL is not defined in wp-config.php' );
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active( "fg-{$this->type}-to-wp-premium/fg-{$this->type}-to-wp-premium.php" ) ) {
			NMT::exit_with_message( "FG {$this->type} to WP Premium plugin not found. Install and activate it before using this class." );
		}
	}

	/**
	 * Add filter for options.
	 */
	private function add_hooks(): void {
		// Filter if option values already exist in db.
		add_filter( "option_{$this->function_prefix}_options", [ $this, 'filter_options' ] );
		// Filter if option values do not exist in db. (Needed otherwise WordPress won't filter the options).
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
		if ( false === $options ) {
			$options = [];
		}

		$options['password'] = getenv( 'DB_PASSWORD' );
		if ( false === $options['password'] ) {
			NMT::exit_with_message( 'You must set the DB_PASSWORD as an environment variable to use the FgHelper. See docs/fg-helper.md' );
		}

		// If env vars are not set for these, just use WP's constants for the DB WordPress DB connection.
		$options['hostname'] = false === getenv( 'DB_HOST' ) ? DB_HOST : getenv( 'DB_HOST' );
		$options['database'] = false === getenv( 'DB_NAME' ) ? DB_NAME : getenv( 'DB_NAME' );
		$options['username'] = false === getenv( 'DB_USER' ) ? DB_USER : getenv( 'DB_USER' );

		$options['prefix'] = $this->get_import_tables_prefix();

		$options['url'] = NCCM_SOURCE_WEBSITE_URL;

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

	/**
	 * Get a specific FG option.
	 *
	 * @param string $option_name Name of the option to get - e.g. file_public_path_source, hostname, etc.
	 *
	 * @return mixed The option value or null if not found.
	 */
	public function get_fg_option( string $option_name ): mixed {
		$fg_options = get_option( "{$this->function_prefix}_options" );
		return $fg_options[ $option_name ] ?? null;
	}

	/**
	 * Get a specific FG premium option.
	 *
	 * Note that this is almost the same as get_fg_option, but with a "p" for premium after the function prefix.
	 *
	 * @param string $option_name Name of the option to get - e.g. skip_users, unicode_usernames, etc.
	 *
	 * @return mixed The option value or null if not found.
	 */
	public function get_fg_premium_option( string $option_name ): mixed {
		// Note the "p" for premium after the function prefix.
		$premium_options = get_option( "{$this->function_prefix}p_options" );
		return $premium_options[ $option_name ] ?? null;
	}
}
