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
	private string $log_dir;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();

		$temp_dir = get_temp_dir();

		$random_dir_name = wp_unique_filename( $temp_dir, uniqid( time() ) );

		$this->log_dir = trailingslashit( $temp_dir ) . $random_dir_name;
		wp_mkdir_p( $this->log_dir );

		$this->file_log       = $this->log_dir . '/test-file-log.log';
		$this->plain_file_log = $this->log_dir . '/test-plain-log.log';

		// Enable logging (it's off by default).
		add_filter( 'newspack_migration_tools_enable_file_log', '__return_true' );
		add_filter( 'newspack_migration_tools_enable_plain_log', '__return_true' );

		add_filter( 'newspack_migration_tools_log_dir', fn() => $this->log_dir );
	}

	/**
	 * {@inheritDoc}
	 */
	public function tearDown(): void {

		if ( file_exists( $this->log_dir ) ) {
			// Delete all files in the dir so rmdir() will work.
			foreach ( scandir( $this->log_dir ) as $item ) {
				if ( in_array( $item, [ '.', '..' ] ) ) { // Skip the `.` and `..` directories
					continue;
				}

				$file = trailingslashit( $this->log_dir ) . $item;
				if ( file_exists( $file ) ) {
					unlink( $file );
				}
			}
		}
		// It's OK, phpcs. It's a temp dir.
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir
		rmdir( $this->log_dir );
		parent::tearDown();
	}

	/**
	 * Test with logging off.
	 */
	public function test_with_logging_off(): void {
		add_filter( 'newspack_migration_tools_enable_file_log', '__return_false' );
		add_filter( 'newspack_migration_tools_enable_plain_log', '__return_false' );

		$logger = MultiLog::get_logger(
			'test-file-log-default',
			[
				FileLog::get_logger( 'Testing file log disabled', $this->file_log ),
				PlainFileLog::get_logger( 'Testing plain file log disabled', $this->plain_file_log ),
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
		$logger = MultiLog::get_logger(
			'test-file-log-enabled',
			[
				FileLog::get_logger( 'Testing file log enabled', $this->file_log ),
				PlainFileLog::get_logger( 'Testing plain file log enabled', $this->plain_file_log ),
			]
		);

		$should_be_logged = 'This was insightful';

		$logger->alert( $should_be_logged );


		$this->assertFileExists( $this->file_log );
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$log_content = file_get_contents( $this->file_log );
		$this->assertStringContainsString( $should_be_logged, $log_content );

		$this->assertFileExists( $this->plain_file_log );
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$log_content = file_get_contents( $this->plain_file_log );
		$this->assertStringContainsString( $should_be_logged, $log_content );
	}

	public function test_get_existing_multilogger(): void {
		$loggers_arg = [
			FileLog::get_logger( 'Testing get existing logger', $this->file_log ),
			PlainFileLog::get_logger( 'Testing get existing logger', $this->plain_file_log ),
		];
		$logger      = MultiLog::get_logger(
			'test-get-existing-logger',
			$loggers_arg
		);

		$logger->info( 'Time passes' );
		$logger->debug( "It's water under the bridge" );

		// Test that we get the same instance when calling with the same args.
		$existing_logger = MultiLog::get_logger(
			'test-get-existing-logger',
			$loggers_arg
		);
		$this->assertEquals( $logger, $existing_logger );

		// Test that we don't when calling with different args.
		unset( $loggers_arg[0] );
		$should_be_new_logger = MultiLog::get_logger(
			'test-get-existing-logger',
			$loggers_arg
		);
		$this->assertNotEquals( $logger, $should_be_new_logger );

		// Also test that trying to pass garbage in the array will throw an exception.
		$this->expectException( \InvalidArgumentException::class );
		MultiLog::get_logger( 'should-not-work', [ 'garbage' ] );
	}

	public function test_get_existing_plain_logger(): void {
		$same    = 'same';
		$plain   = PlainFileLog::get_logger( $same, $this->plain_file_log );
		$plain_2 = PlainFileLog::get_logger( $same, $this->plain_file_log );
		$this->assertEquals( $plain, $plain_2 );
		$plain_3 = PlainFileLog::get_logger( $same );
		$this->assertNotEquals( $plain, $plain_3 );

		$plain_4 = PlainFileLog::get_logger( 'different', $this->plain_file_log );
		$this->assertNotEquals( $plain, $plain_4 );
	}
}
