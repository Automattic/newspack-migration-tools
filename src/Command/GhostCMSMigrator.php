<?php
/**
 * GhostCMSMigrator class.
 *
 * @package newspack-migration-tools
 */

namespace Newspack\MigrationTools\Command;

use Newspack\MigrationTools\Log\FileLogger;
use Newspack\MigrationTools\Log\Log;

/**
 * Ghost CMS general Migrator command class.
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
				'newspack-migration-tools ghost-cms-migrator',
				[ $this, 'cmd_ghost_cms_migrator' ],
			],
		];
	}

	/**
	 * Ghost CMS Migrator command.
	 */
	public function cmd_ghost_cms_migrator( array $pos_args, array $assoc_args ): void {

		$logfile = __FUNCTION__ . '.log';

		FileLogger::log( $logfile, sprintf( "Done." ) );

	}
	
}
