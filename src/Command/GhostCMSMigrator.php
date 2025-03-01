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
use Newspack\MigrationTools\Util\Log\MultiLog;

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

		// Set log slug: Class name without namespace plus function: "GhostCMSMigrator_cmd_ghostcms_import" .
		$log_slug = str_replace( __NAMESPACE__ . '\\', '', __CLASS__ ) . '_' . __FUNCTION__;

		$logger = MultiLog::get_logger( 
			'multi-' . $log_slug,
			[
				CliLog::get_logger( $log_slug ),
				FileLog::get_logger( $log_slug ),
			]
		);
		
		$logger->info( 'Starting CLI - GhostCMS Import...' );

		// Do helper.
		$helper = new GhostCMSHelper();
		$helper->ghostcms_import( $pos_args, $assoc_args, $log_slug );
	}
}
