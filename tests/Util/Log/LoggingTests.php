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

	/**
	 * Test that truncate_files() empties an existing log file's contents.
	 */
	public function test_truncate_files_truncates_existing_content(): void {
		$logger = FileLog::get_logger( 'test-truncate-existing', $this->file_log );

		$logger->info( 'Some content that should be wiped out' );

		$this->assertFileExists( $this->file_log );
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$this->assertNotEmpty( file_get_contents( $this->file_log ) );

		$truncated_count = FileLog::truncate_files( $logger );

		$this->assertEquals( 1, $truncated_count );
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$this->assertEquals( '', file_get_contents( $this->file_log ) );
	}












	/**
	 * Test that truncate_files() will open the underlying file resource even if
	 * nothing has been logged yet (Monolog doesn't fopen until first write).
	 */
	public function test_truncate_files_opens_file_if_not_yet_opened(): void {
		$logger = FileLog::get_logger( 'test-truncate-not-yet-opened', $this->file_log );

		// Nothing has been logged, so the file shouldn't exist yet.
		$this->assertFileDoesNotExist( $this->file_log );

		$truncated_count = FileLog::truncate_files( $logger );

		$this->assertEquals( 1, $truncated_count );
		$this->assertFileExists( $this->file_log );
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$this->assertEquals( '', file_get_contents( $this->file_log ) );
	}

	/**
	 * Test that after truncating, subsequent writes start clean (no leftover
	 * bytes/null padding from before the truncate + rewind).
	 */
	public function test_truncate_files_allows_writes_after_truncate(): void {
		$logger = FileLog::get_logger( 'test-truncate-then-write', $this->file_log );

		$logger->info( 'First run content' );
		FileLog::truncate_files( $logger );

		$second_message = 'Second run content';
		$logger->info( $second_message );

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$log_content = file_get_contents( $this->file_log );

		$this->assertStringContainsString( $second_message, $log_content );
		$this->assertStringNotContainsString( 'First run content', $log_content );
	}

	/**
	 * Test that truncate_files() is a no-op (returns 0, no error) when the
	 * logger only has a NullHandler (i.e. file logging disabled).
	 */
	public function test_truncate_files_returns_zero_when_logger_disabled(): void {
		add_filter( 'newspack_migration_tools_enable_file_log', '__return_false' );

		$logger = FileLog::get_logger( 'test-truncate-disabled', $this->file_log );

		$truncated_count = FileLog::truncate_files( $logger );

		$this->assertEquals( 0, $truncated_count );
		$this->assertFileDoesNotExist( $this->file_log );
	}

	/**
	 * Test that truncate_files() truncates every StreamHandler attached to
	 * the logger when there are multiple handlers/files.
	 */
	public function test_truncate_files_with_multiple_handlers(): void {
		$second_file_log = $this->log_dir . '/second-test-file-log.log';

		$logger = MultiLog::get_logger(
			'test-truncate-multi-handler',
			[
				FileLog::get_logger( 'first handler', $this->file_log ),
				FileLog::get_logger( 'second handler', $second_file_log ),
			]
		);

		$logger->info( 'Content in both files' );

		$this->assertFileExists( $this->file_log );
		$this->assertFileExists( $second_file_log );

		$truncated_count = FileLog::truncate_files( $logger );

		$this->assertEquals( 2, $truncated_count );
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$this->assertEquals( '', file_get_contents( $this->file_log ) );
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$this->assertEquals( '', file_get_contents( $second_file_log ) );

		// Clean up the extra file since it isn't handled by tearDown()'s known filenames.
		if ( file_exists( $second_file_log ) ) {
			unlink( $second_file_log );
		}
	}

	/**
	 * Test that truncate_files() throws when the underlying handler is unable
	 * to open/write to its file path (e.g. an unwritable directory).
	 */
	public function test_truncate_files_throws_on_unwritable_path(): void {
		add_filter( 'newspack_migration_tools_log_dir', fn() => '/path/does/not/exist/and/is/unwritable' );

		$logger = FileLog::get_logger( 'test-truncate-unwritable', 'unwritable.log' );

		$this->expectException( \Throwable::class );
		FileLog::truncate_files( $logger );
	}




}
