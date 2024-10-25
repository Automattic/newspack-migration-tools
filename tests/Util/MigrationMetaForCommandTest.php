<?php

namespace Newspack\MigrationTools\Tests\Util;

use Newspack\MigrationTools\Util\MigrationMeta;
use Newspack\MigrationTools\Util\MigrationMetaForCommand;
use WP_UnitTestCase;

class MigrationMetaForCommandTest extends WP_UnitTestCase {

	/**
	 * Test that we get a nice log file name.
	 *
	 * @return void
	 */
	public function test_get_suggested_logfile_name() {
		$command_meta       = new MigrationMetaForCommand( 'test_myself_with_a_log_file_name', 1 );
		$expected_file_name = 'test-myself-with-a-log-file-name-1.log';
		$this->assertEquals( $expected_file_name, $command_meta->get_suggested_log_name() );
	}

	/**
	 * Test if a post should be skipped for processing if it has already been processed.
	 *
	 * @return void
	 */
	public function test_should_skip_post() {
		$post_id      = 1;
		$cmd_version  = 'test_skips';
		$command_meta = new MigrationMetaForCommand( $cmd_version, 1 );
		// The post has not been processed, so it should not be skipped.
		$this->assertFalse( $command_meta->should_skip_post( $post_id ) );

		// Now set a higher version number and we should skip.
		MigrationMeta::update( $post_id, $cmd_version, 'post', 2 );
		$this->assertTrue( $command_meta->should_skip_post( $post_id ) );

		// If we insist on refreshing existing posts with refresh existing, it should not be skipped though.
		$command_meta->set_refresh_existing( true );
		$this->assertFalse( $command_meta->should_skip_post( $post_id ) );

		MigrationMeta::delete( $post_id, $cmd_version, 'post' );
	}

	/**
	 * Test the set next version sets the MigrationMeta version number correctly.
	 *
	 * @return void
	 */
	public function test_set_next_version() {
		$post_id        = 1;
		$cmd_version    = 'test_bump';
		$version_number = 1;
		$command_meta   = new MigrationMetaForCommand( $cmd_version, $version_number );
		$command_meta->set_next_version_on_post( $post_id );
		$this->assertEquals( $version_number + 1, MigrationMeta::get( $post_id, $cmd_version, 'post' ) );

		// No matter how many times we call this, it should be the MigrationMetaForCommand's version number + 1.
		$command_meta->set_next_version_on_post( $post_id );
		$command_meta->set_next_version_on_post( $post_id );
		$this->assertEquals( $version_number + 1, MigrationMeta::get( $post_id, $cmd_version, 'post' ) );
	}
}
