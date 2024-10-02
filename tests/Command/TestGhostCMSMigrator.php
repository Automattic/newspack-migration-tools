<?php

namespace Newspack\MigrationTools\Tests\Command;

use Newspack\MigrationTools\Command\GhostCMSMigrator;
use WP_UnitTestCase;

/**
 * Class TestGhostCMSMigrator
 *
 * @package newspack-migration-tools
 */
class TestGhostCMSMigrator extends WP_UnitTestCase {

	/**
	 * Test GhostCMS import CLI command
	 */
	public function test_cmd_ghostcms_import() {

		// Turn off logging.
		add_filter( 'newspack_migration_tools_log_file_logger_disable', '__return_true' );
		add_filter( 'newspack_migration_tools_log_clilog_disable', '__return_true' );

		$migrator = GhostCMSMigrator::get_instance();

		$migrator->cmd_ghostcms_import(
			[],
			[
				'default-user-id' => 1,
				'ghost-url'       => 'https://newspack.com',
				'json-file'       => 'tests/fixtures/ghostcms.json',
			]
		);

		// Assert true if PHPUnit didn't faile on any Exceptions in command above.
		$this->assertTrue( true );
	}
}
