<?php

namespace Newspack\MigrationTools\Tests\Command;

use Newspack\MigrationTools\Command\AttachmentsMigrator;
use WP_UnitTestCase;

/**
 * Class AttachmentsMigratorTests
 *
 * @package newspack-migration-tools
 */
class TestAttachmentsMigrator extends WP_UnitTestCase {

	/**
	 * A single example test.
	 */
	public function test_attachment_years() {
		add_filter( 'newspack_migration_tools_log_file_logger_disable', '__return_true' );
		add_filter( 'newspack_migration_tools_log_clilog_disable', '__return_true' );

		$post_id = self::factory()->post->create();
		// Create an attachment, so there is something to test.
		self::factory()->attachment->create_object(
			[
				'file'        => 'tests/fixtures/koi.jpg',
				'post_parent' => $post_id,
			]
		);

		AttachmentsMigrator::cmd_get_atts_by_years( [], [] );

		$this->assertFileExists( '0_failed_ids.txt' );
		unlink( '0_failed_ids.txt' );
	}
}
