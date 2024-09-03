<?php

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\MigrationTools\Logic\NewspaperThemeHelper;
use Newspack\MigrationTools\Util\MigrationMetaForCommand;
use WP_UnitTestCase;

class NewspaperThemeHelperTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		add_filter( 'newspack_migration_tools_log_file_logger_disable', '__return_true' );
		add_filter( 'newspack_migration_tools_log_clilog_disable', '__return_true' );
	}

	public function test_migrate_subtitle() {
		$command_meta = new MigrationMetaForCommand( __FUNCTION__, 1 );
		$helper       = new NewspaperThemeHelper();
		$subtitle     = 'Test subtitle';
		$post_meta    = [
			'td_subtitle' => $subtitle,
		];
		$post_id      = self::factory()->post->create();
		// Check that the field is migrated.
		$helper->migrate_subtitle_to_newspack_subtitle( $post_id, $post_meta, $command_meta );
		$this->assertEquals( $subtitle, get_post_meta( $post_id, 'newspack_post_subtitle', true ) );

		// Check that if we set a new value, and try migrating again – the MigrationMeta should prevent it because of the command version.
		update_post_meta( $post_id, 'newspack_post_subtitle', 'some new value' );
		$helper->migrate_subtitle_to_newspack_subtitle( $post_id, $post_meta, $command_meta );
		$this->assertNotEquals( $subtitle, get_post_meta( $post_id, 'newspack_post_subtitle', true ) );
	}
}
