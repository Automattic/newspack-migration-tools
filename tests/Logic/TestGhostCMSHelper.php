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

		// Activation of CAP is required for it's internal "init" actions.
		// phpunit can get by without activating CAP plugin, but real-world
		// tests will fail if not activated.  So just activate to be safe.
		activate_plugin( 'co-authors-plus/co-authors-plus.php' );
		
		// Set path to log file.
		$log_file = get_temp_dir() . str_replace( __NAMESPACE__ . '\\', '', __CLASS__ ) . '_' . __FUNCTION__ . '.log';

		// If already exists, clear it.
		if ( file_exists( $log_file ) ) {
			file_put_contents( $log_file, '' );
		}

		// Capture CLI logging.
		ob_start();

		// Run test.
		$test_ghostcms_helper = new GhostCMSHelper();
		$test_ghostcms_helper->ghostcms_import( 
			[], 
			[
				'json-file'       => 'tests/fixtures/ghostcms.json',
				'ghost-url'       => 'https://newspack.com/',
				'default-user-id' => 1,
			],
			$log_file
		);

		// Get output CLI buffer without color codes.
		$output = preg_replace( '/\033\[[0-9;]+m/', '', ob_get_clean() );

		// Test that log exists.
		$this->assertFileExists( $log_file );

		// Test that log file matches 
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$this->assertEquals( $output, file_get_contents( $log_file ) );

		// Test that output.
		$this->assertStringContainsString( 'Doing migration.', $output );
		$this->assertStringContainsString( 'Inserted new post:', $output );
		$this->assertStringContainsString( 'Created new GA.', $output );
		$this->assertStringContainsString( 'Assigned authors (wp users and/or cap gas).', $output );
		$this->assertStringContainsString( 'Inserted category term:', $output );
		$this->assertStringContainsString( 'Set post categories.', $output );
		$this->assertStringContainsString( 'SUCCESS: Done.', $output );

		// Remove log file.
		if ( file_exists( $log_file ) ) {
			unlink( $log_file );
		}
	}
}
