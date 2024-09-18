<?php

namespace Newspack\MigrationTools\Tests\Log;

use Newspack\MigrationTools\Log\CliLogger;
use Newspack\MigrationTools\Log\FileLogger;
use Newspack\MigrationTools\Log\Log;
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
	 * Test that with both loggers disabled, there is no output or log file.
	 *
	 * @return void
	 */
	public function test_no_file_logging(): void {
		add_filter( 'newspack_migration_tools_log_file_logger_disable', '__return_true' );

		ob_start();

		FileLogger::log( $this->log_file, 'Log error level to file', Log::ERROR );

		$this->assertEquals( '', ob_get_clean() );
		$this->assertFileDoesNotExist( $this->log_file );

		add_filter( 'newspack_migration_tools_log_file_logger_disable', '__return_false' );
	}

	/**
	 * Test logging to file.
	 */
	public function test_file_log(): void {
		// Disable logging to CLI, so we can test the file logging in isolation.
		add_filter( 'newspack_migration_tools_log_clilog_disable', '__return_true' );

		ob_start();

		FileLogger::log( $this->log_file, 'Log line level to file', Log::LINE );
		FileLogger::log( $this->log_file, 'Log info level to file', Log::INFO );
		FileLogger::log( $this->log_file, 'Log success level to file', Log::SUCCESS );
		FileLogger::log( $this->log_file, 'Log warning level to file', Log::WARNING );
		FileLogger::log( $this->log_file, 'Log error level to file', Log::ERROR );

		// Assert that there was no output to CLI because we disabled it.
		$this->assertEquals( '', ob_get_clean() );

		// And that our log file exists.
		$this->assertFileExists( $this->log_file );

		$log_lines = file( $this->log_file );
		$this->assertCount( 5, $log_lines );
		// The line level should not have a prefix.
		$this->assertStringNotContainsString( ':', $log_lines[0] );
		$this->assertStringStartsWith( 'ERROR:', $log_lines[4] );

		remove_filter( 'newspack_migration_tools_log_clilog_disable', '__return_true' );
	}

	/**
	 * Test logging to CLI.
	 */
	public function test_cli_log(): void {
		$logged_message = 'Will Robinson!';

		ob_start();
		CliLogger::warning( $logged_message );
		$output = trim( ob_get_clean(), PHP_EOL );

		$regex = '/^\033\[\d+(;\d+)*mWARNING\033\[0m/';

		// Check that the output is colorized.
		$this->assertMatchesRegularExpression( $regex, $output );
		// And it contains what we expect.
		$this->assertStringEndsWith( 'Will Robinson!', $output );
	}

	/**
	 * Test that $exit_on_error / wp_die() in CliLogger will die as expected.
	 * 
	 * The WP_CLI migrators use $exit_on_error as an execution control. This
	 * test will verify that $exit_on_error will run properly when called.
	 *
	 * @return void
	 */
	public function test_cli_logger_wp_die(): void {

		// Define a local wp_die() handler to verify wp_die() is called.
		$wp_die_arg1 = null;
		add_filter(
			'wp_die_handler',
			function () use ( &$wp_die_arg1 ) {
				return function ( $arg1 ) use ( &$wp_die_arg1 ) {
					$wp_die_arg1 = $arg1;
				};
			}
		);

		// Buffer the output so PHPunit doesn't echo the logging.
		// Do not use `newspack_migration_tools_log_clilog_disable` here, as that filter
		// will not allow wp_die() to be called/tested.
		ob_start();

		// Log an error with exit_on_error.
		CliLogger::error( 'Oops, exit.', true );

		// Verify wp_die returned an empty array.
		$this->assertIsArray( $wp_die_arg1 );
		$this->assertEquals( $wp_die_arg1, [] );

		// To make PHPUnit happy and not mark this test as risky, test the buffer too.
		$this->assertStringContainsString( 'Oops, exit.', ob_get_clean() ); 
	}

	/**
	 * Test that $exit_on_error in the loggers will exit as expected even if
	 * output logging is disabled.
	 * 
	 * The WP_CLI migrators use $exit_on_error as an execution control. This
	 * test will verify that $exit_on_error will run properly even if logging
	 * is disabled.
	 *
	 * In a CLI context, the logger mixes output with control. Meaning that when logging
	 * a message, the logger can also invoke the temination of the program using the 
	 * optional argument $exit_on_error.
	 * 
	 * When disabling output in PHPUnit tests, make sure to not disable $exit_on_error, which
	 * would allow the script being tested to continue execution when it should
	 * have terminated.
	 * 
	 * @return void
	 */
	public function test_no_logging_but_still_exit(): void {

		// Turn off logging.
		add_filter( 'newspack_migration_tools_log_clilog_disable', '__return_true' );
		add_filter( 'newspack_migration_tools_log_file_logger_disable', '__return_true' );

		// Log an error with $exit_on_error true.
		// And verify that $exit_on_error was still honored even though logging is off.
		// In a WP_CLI context wp_die would have been called, but here we're testing
		// that an exception is thrown when logging is disabled but $exit_on_error is still true.

		// CliLogger:
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Logging disabled with exit_on_error.' );
		CliLogger::error( 'Oops, exit.', true );
        
		// FileLogger:
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Logging disabled with exit_on_error.' );
		FileLogger::error( 'Oops, exit.', true );

	}
}
