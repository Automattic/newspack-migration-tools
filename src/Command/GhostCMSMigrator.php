<?php
/**
 * GhostCMSMigrator class.
 * 
 * @link: https://ghost.org/
 *
 * @package newspack-migration-tools
 */

namespace Newspack\MigrationTools\Command;

use Newspack\MigrationTools\Logic\GhostCMSHelper;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;

/**
 * GhostCMS general Migrator command class.
 */
class GhostCMSMigrator implements WpCliCommandInterface {

	/**
	 * {@inheritDoc}
	 */
	public static function get_cli_commands(): array {
		
		return [
			[
				'newspack-migration-tools ghostcms-import',
				[ __CLASS__, 'cmd_ghostcms_import' ],
				[
					'shortdesc' => 'Import content from Ghost JSON export.',
					'synopsis'  => array(

						// required:
						array(
							'type'        => 'assoc',
							'name'        => 'default-user-id',
							'description' => 'User ID for default "post_author" for wp_insert_post(). Integer.',
							'optional'    => false,
							'repeating'   => false,
						),
						array(
							'type'        => 'assoc',
							'name'        => 'ghost-url',
							'description' => 'Public URL of current/live Ghost Website. Scheme with domain: https://www.mywebsite.com',
							'optional'    => false,
							'repeating'   => false,
						),
						array(
							'type'        => 'assoc',
							'name'        => 'json-file',
							'description' => 'Path to Ghost JSON export file.',
							'optional'    => false,
							'repeating'   => false,
						),

						// optional:
						array(
							'type'        => 'assoc',
							'name'        => 'created-after',
							'description' => 'Datetime cut-off to only import posts AFTER this date. (Must be parseable by strtotime).',
							'optional'    => true,
							'repeating'   => false,
						),

					),
				],
			],
		];
	}
	
	/**
	 * GhostCMS Import command.
	 */
	public static function cmd_ghostcms_import( array $pos_args, array $assoc_args ): void {

		// Turn on logging to /wp-content/ folder.
		add_filter( 'newspack_migration_tools_enable_cli_log', '__return_true' );
		add_filter( 'newspack_migration_tools_enable_file_log', '__return_true' );
		add_filter( 'newspack_migration_tools_log_dir', fn() => WP_CONTENT_DIR );

		// Set log slug to class name (namespace removed) and function: "GhostCMSMigrator_cmd_ghostcms_import" .
		$log_slug = str_replace( __NAMESPACE__ . '\\', '', __CLASS__ ) . '_' . __FUNCTION__;

		$file_logger = FileLog::get_logger( $log_slug . '.log', $log_slug . '.log' );
		$cli_logger = CliLog::get_logger( $log_slug . '-cli' );

		$info = 'Starting CLI - GhostCMS Import...';
		$file_logger->info( $info );
		$cli_logger->info( $info );

		$helper = new GhostCMSHelper();
		$helper->ghostcms_import( $pos_args, $assoc_args, $log_slug );
	}
}
