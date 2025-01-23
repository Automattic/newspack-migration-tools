<?php

namespace Newspack\MigrationTools\Command;


use Newspack\MigrationTools\Logic\DrupalHelper;
use Newspack\MigrationTools\NMT;
use Newspack\MigrationTools\Util\Log\CliLog;
use WP_CLI;

/**
 * Class DrupalMigrator
 *
 * Tools to work with the great https://www.fredericgilles.net/fg-drupal-to-wordpress plugin.
 *
 * @package Newspack\MigrationTools\Command
 */
class DrupalMigrator implements WpCliCommandInterface {

	public static function get_cli_commands(): array {
		return [
			[
				'newspack-migration-tools drupal-import',
				[ __CLASS__, 'cmd_wrap_drupal_import' ],
				[
					'shortdesc' => 'Wrap the import command from the FG Drupal importer plugin.',
					'synopsis'  => [
						[
							'type'        => 'positional',
							'name'        => 'migration-name',
							'description' => 'The name of the migration to run. Your name will be used in calling hooks.',
							'optional'    => false,
							'repeating'   => false,
						],
					],
				]
			],
		];
	}

	/**
	 * Run the import.
	 *
	 * We simply wrap the import command from the FG Drupal plugin and add our hooks before running the import.
	 * Note that we can't batch this at all, so timeouts might be a thing. It does pick up nicely where it left off
	 * if re-run though.
	 */
	public static function cmd_wrap_drupal_import( array $pos_args, array $assoc_args ): void {
		self::check_requirements();

		$migration_name = str_replace( '-', '_', sanitize_title( $pos_args[0] ) );
		if ( empty( $migration_name ) ) {
			NMT::exit_with_message( "Please provide a migration name. If you don't have custom code to run, then just make one up, but we do need a name." );
		}
		CliLog::get_logger( 'drupal-migrator' )->info( sprintf( 'Using "%s" as the Drupal Migration machine name. ', $migration_name ) );
		CliLog::get_logger( 'drupal-migrator' )->info( 'Use ^^ in hooks. See DrupalMigration::add_fg_hooks().' );

		// Add our customizations.
		add_filter( 'option_fgd2wp_options',         [ __CLASS__, 'option_fgd2wp_options' ] );
		add_filter( 'default_option_fgd2wp_options', [ __CLASS__, 'option_fgd2wp_options' ] );

		// Make it possible for implementing code to customize the migration.
		do_action( 'nmt_drupal_migrator_add_fg_hooks_' . $migration_name );

		// Note that the 'launch' arg is important – without it the hooks above will not be registered.
		WP_CLI::runcommand( 'import-drupal import', [ 'launch' => false ] );
	}

	public static function check_requirements() {
		if ( ! is_plugin_active( "fg-drupal-to-wp-premium/fg-drupal-to-wp-premium.php" ) ) {
			NMT::exit_with_message( 'FG Drupal to WP Premium plugin not found. Install and activate it before using this class.' );
		}

		if ( ! defined( 'NMT_DRUPAL_MIGRATOR_SOURCE_URL' ) ) {
			NMT::exit_with_message( 'The NMT_DRUPAL_MIGRATOR_SOURCE_URL constant is not defined. Please define it.' );
		}
	}

	/**
	 * Filter the options for the FG Drupal to WP plugin to use environment variables for the database connection.
	 * Put these variables in your .env file locally (or comment out locally).
	 *
	 * @param  array|false $options The options array to filter or boolean false if database option doesn't exist.
	 * @return array                The filtered options.
	 */
	public static function option_fgd2wp_options( array|false $options ): array {
		
		// Options / Default values / FG plugin version 3.85.2
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

		if( false === $options ) $options = [];

		$options['hostname'] = getenv( 'DB_HOST' );
		$options['database'] = getenv( 'DB_NAME' );
		$options['username'] = getenv( 'DB_USER' );
		$options['password'] = getenv( 'DB_PASSWORD' );
		if ( empty( $options['hostname'] ) || empty( $options['database'] ) || ! isset( $options['username'] ) || ! isset( $options['password'] ) ) {
			NMT::exit_with_message( 'Could not get database connection details from environment variables.' );
		}

		$options['prefix'] = DrupalHelper::get_tables_prefix();

		// @todo Verify url define exists or fail gracefully.
		$options['url'] = NMT_DRUPAL_MIGRATOR_SOURCE_URL;

		// @todo Should this go into Publisher specific migrator instead?
		$options['summary'] = 'in_excerpt'; // otherwise excerpt will go in top of content with <!--more--> link

		// @todo should we turn this on for images with the same filenames?
		// how are these store in drupal? in wordpress the same filename could be used if in different /year/mon/ folders...
		// but what about if the import was restarted...will images be fetched again and given unique -abc at the end?
		// import_duplicates = 1;

		return $options;
	}

}