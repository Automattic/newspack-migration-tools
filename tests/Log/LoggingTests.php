<?php

namespace Newspack\MigrationTools\Tests\Log;

use Newspack\MigrationTools\Util\Log\FileLog;
use WP_UnitTestCase;

/**
 * Class LoggingTests
 *
 * @package newspack-migration-tools
 */
class LoggingTests extends WP_UnitTestCase {

	private string $log_file;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();
		$this->log_file = get_temp_dir() . '/logging-test.log';
	}

	/**
	 * {@inheritDoc}
	 */
	public function tearDown(): void {
		parent::tearDown();
		if ( file_exists( $this->log_file ) ) {
			unlink( $this->log_file );
		}
	}

	/**
	 * Test that by default we don't log.
	 */
	public function test_file_log_default(): void {
		$logger = FileLog::get_logger( 'Testing file log disabled', $this->log_file );

		$should_not_be_logged = 'Logging is off by default –I should not be in the log file';
		$logger->info( $should_not_be_logged );

		$this->assertFileDoesNotExist( $this->log_file );
	}

	/**
	 * Test that we can enable logging by implementing the filter.
	 */
	public function test_file_log_enabled() {
		add_filter( 'newspack_migration_tools_enable_file_log', '__return_true' );
		$logger = FileLog::get_logger( 'Testing file log enabled', $this->log_file );

		$should_be_logged = 'This was insightful';
		$logger->info( $should_be_logged );
		$this->assertFileExists( $this->log_file );

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$log_content = file_get_contents( $this->log_file );
		$this->assertStringContainsString( $should_be_logged, $log_content );
	}
}
