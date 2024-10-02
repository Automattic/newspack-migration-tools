<?php

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\MigrationTools\Logic\GhostCMSHelper;
use WP_UnitTestCase;

class TestGhostCMSHelper extends WP_UnitTestCase {

	/**
	 * Test that GhostCMS Helper will import from JSON file.
	 *
	 * @return void
	 */
	public function test_ghostcms_import(): void {

		// Turn off logging output.
		add_filter( 'newspack_migration_tools_log_file_logger_disable', '__return_true' );
		add_filter( 'newspack_migration_tools_log_clilog_disable', '__return_true' );

		// Run test.
		$test_ghostcms_helper = new GhostCMSHelper();
		$test_ghostcms_helper->ghostcms_import( 
			[], 
			[
				'json-file'       => 'tests/fixtures/ghostcms.json',
				'ghost-url'       => 'https://newspack.com/',
				'default-user-id' => 1,
			],
			''
		);

		// Posts.
		$posts = get_posts(
			[
				'title'       => 'The Title',
				'numberposts' => 1,
			]
		);
		$this->assertIsArray( $posts );
		$this->assertCount( 1, $posts );
		$this->assertEquals( 'the-title', $posts[0]->post_name );

		// @todo CoAuthorsPlus / GA

		// Categories.
		$category = get_term_by( 'name', 'News', 'category' );
		$this->assertIsObject( $category );
		$this->assertEquals( 'news', $category->slug );
	}
}
