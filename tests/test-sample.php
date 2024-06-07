<?php
/**
 * Class SampleTest
 *
 * @package Newspack_Migration_Tools
 */

use Newspack\MigrationTools\Command\AttachmentsMigrator;

/**
 * Sample test case.
 */
class SampleTest extends WP_UnitTestCase {

	/**
	 * A single example test.
	 */
	public function test_attachment_years() {
		$post_id = self::factory()->post->create(
			[
				'post_title'    => 'My post',
				'post_content'  => 'This is my post.',
				'post_status'   => 'publish',
			]
		);
		// Create an attachment, so there is something to test.
		self::factory()->attachment->create_object(
			[
				'file' => 'tests/fixtures/koi.jpg',
				'post_parent' => $post_id,
			]
		);

		$attachments_migrator = AttachmentsMigrator::get_instance();
		$attachments_migrator->cmd_get_atts_by_years( [], [] );

		$this->assertFileExists( '0_failed_ids.txt' );
		unlink( '0_failed_ids.txt' );
	}
}
