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

		// Set path to log file.
		$log_file = get_temp_dir() . str_replace( __NAMESPACE__ . '\\', '', __CLASS__ ) . '_' . __FUNCTION__ . '.log';

		// If already exists, clear it.
		if ( file_exists( $log_file ) ) {
			file_put_contents( $log_file, '' );
		}

		// Capture CLI logging.
		ob_start();

		// Run test.
		$testGhostCMSHelper = new GhostCMSHelper();
		$testGhostCMSHelper->ghostcms_import( 
			[], 
			[
				'json-file'       => 'tests/fixtures/ghostcms.json',
				'ghost-url'       => 'https://newspack.com/',
				'default-user-id' => 1,
			],
			$log_file
		);

		// Get output CLI buffer without color codes.
		$output = preg_replace('/\033\[[0-9;]+m/', '', ob_get_clean() );

		// Test that log exists.
		$this->assertFileExists( $log_file );

		// Test that log file matches 
		$this->assertEquals( $output, file_get_contents( $log_file) );

		// Test that output.
		$this->assertStringContainsString( 'Doing migration.', $output );

// +--json-file: tests/fixtures/ghostcms.json\r\n
// +--ghost-url: https://newspack.com\r\n
// +--default-user-id: 1\r\n
// +---- json id: 65ea49ad7b6cf900014e661c\r\n
// +Title/Slug: The Title / the-title\r\n
// +Created/Published: 2024-04-07T23:11:41.000Z / 2024-04-07T23:12:17.000Z\r\n
// +Inserted new post: 4\r\n
// +Featured image fetch url: https://newspack.com/content/images/2024/04/image.jpeg\r\n
// +WARNING: Featured image import failed for: https://newspack.com/content/images/2024/04/image.jpeg\r\n
// +WARNING: Featured image import wp error: Not Found\r\n
// +Relationship found for author: 6387a43e354f5f003ddbe55f\r\n
// +Get or insert author: some-user\r\n
// +Created new GA.\r\n
// +Assigned authors (wp users and/or cap gas). Count: 1\r\n
// +Relationship found for tag: 6387a43f354f5f003ddbe565\r\n
// +Inserted category term: 4\r\n
// +Set post categories. Count: 1\r\n


		// Remove log file.
		if ( file_exists( $log_file ) ) {
			unlink( $log_file );
		}
	}
}
