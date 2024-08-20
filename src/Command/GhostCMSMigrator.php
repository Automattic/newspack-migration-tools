<?php
/**
 * GhostCMSMigrator class.
 * 
 * @link: https://ghost.org/
 *
 * @package newspack-migration-tools
 */

namespace Newspack\MigrationTools\Command;

use Newspack\MigrationTools\Log\FileLogger;
use Newspack\MigrationTools\Log\Log;
use Newspack\MigrationTools\Logic\GhostCMSHelper;

/**
 * GhostCMS general Migrator command class.
 */
class GhostCMSMigrator implements WpCliCommandInterface {

	/**
	 * Private constructor.
	 */
	private function __construct() {
		// I don't do anything right now.
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_instance(): self {
		static $instance = null;
		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_cli_commands(): array {
		
		return [
			[
				'newspack-migration-tools ghostcms-import',
				[ $this, 'cmd_ghostcms_import' ],
				[
					'shortdesc' => 'Import content from Ghost JSON export.',
					'synopsis'  => array(
						array(
							'type'        => 'assoc',
							'name'        => 'json-file',
							'description' => 'Path to Ghost JSON export file.',
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
							'name'        => 'default-user-id',
							'description' => 'User ID for default "post_author" for wp_insert_post(). Integer.',
							'optional'    => false,
							'repeating'   => false,
						),
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
	public function cmd_ghostcms_import( array $pos_args, array $assoc_args ): void {

		$log_file = str_replace( __NAMESPACE__ . '\\', '', __CLASS__ ) . '_' . __FUNCTION__ . '.log';

		FileLogger::log( $log_file, 'Starting CLI - GhostCMS Import...', Log::INFO );

		( new GhostCMSHelper() )->ghostcms_import( $pos_args, $assoc_args, $log_file );

	}
}
