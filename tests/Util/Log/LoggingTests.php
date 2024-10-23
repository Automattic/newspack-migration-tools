<?php

namespace Newspack\MigrationTools\Tests\Log;

use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\Log\MultiLog;
use Newspack\MigrationTools\Util\Log\PlainFileLog;
use WP_UnitTestCase;

/**
 * Class LoggingTests
 *
 * @package newspack-migration-tools
 */
class LoggingTests extends WP_UnitTestCase {

	private string $file_log;
	private string $plain_file_log;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();
		$this->file_log       = get_temp_dir() . '/test-file-log.log';
		$this->plain_file_log = get_temp_dir() . '/test-plain-log.txt';
	}

	/**
	 * {@inheritDoc}
	 */
	public function tearDown(): void {
		parent::tearDown();
		if ( file_exists( $this->file_log ) ) {
			unlink( $this->file_log );
		}
		if ( file_exists( $this->plain_file_log ) ) {
			unlink( $this->plain_file_log );
		}
	}

	/**
	 * Test that by default we don't log.
	 */
	public function test_file_log_default(): void {
		$logger = new MultiLog(
			[
				FileLog::create_logger( 'Testing file log disabled', $this->file_log ),
				PlainFileLog::create_logger( 'Testing plain file log disabled', $this->plain_file_log ),
			]
		);

		$logger->info( 'Logging is off by default – I should not be in any log file' );

		$this->assertFileDoesNotExist( $this->file_log );
		$this->assertFileDoesNotExist( $this->plain_file_log );
	}

	/**
	 * Test that we can enable logging by implementing the filter.
	 */
	public function test_file_log_enabled() {
		add_filter( 'newspack_migration_tools_enable_file_log', '__return_true' );
		add_filter( 'newspack_migration_tools_enable_plain_log', '__return_true' );
		$logger = new MultiLog(
			[
				FileLog::create_logger( 'Testing file log enabled', $this->file_log ),
				PlainFileLog::create_logger( 'Testing plain file log enabled', $this->plain_file_log ),
			]
		);

		$should_be_logged = 'This was insightful';

		$logger->info( $should_be_logged );


		$this->assertFileExists( $this->file_log );
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$log_content = file_get_contents( $this->file_log );
		$this->assertStringContainsString( $should_be_logged, $log_content );

		$this->assertFileExists( $this->plain_file_log );
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$log_content = file_get_contents( $this->plain_file_log );
		$this->assertStringContainsString( $should_be_logged, $log_content );
	}
}
