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

		$die_handler_message = "I'm the test die handler!";
	
		// Define a local wp_die() handler to verify wp_die() is called.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		add_filter( 'wp_die_handler', fn() => fn() => print $die_handler_message );

		$exit_message = 'Oops, exit';

		// Cause CliLogger to exit_on_error which leads to wp_die.
		CliLogger::error( $exit_message, true );

		// PHPUnit will only consider one "expect" in a test, so test both strings in one regex.
		// output to match: 'ERROR: Oops, exit \n I'm the test die handler!'
		$this->expectOutputRegex( '/' . preg_quote( $exit_message, '/' ) . '.*' . preg_quote( $die_handler_message, '/' ) . '/s' );
	}

	/**
	 * Test that $exit_on_error in the loggers will exit as expected even if
	 * output logging is disabled via a filter.
	 * 
	 * The WP_CLI migrators use $exit_on_error as an execution control while doing output. 
	 * This test will verify that $exit_on_error will still run properly even if output logging
	 * is disabled.
	 * 
	 * Since the disable logging filter is primary used for PHPUnit, an exception is thrown
	 * instead of wp_die.
	 * 
	 * @return void
	 */
	public function test_cli_no_logging_but_still_exit(): void {

		// Turn off logging.
		add_filter( 'newspack_migration_tools_log_clilog_disable', '__return_true' );

		// Verify $exit_on_error still works even though logging is disabled via filter.
		$this->expectExceptionMessage( 'CLI logging disabled with exit_on_error.' );
		CliLogger::error( 'Oops, exit.', true );
	}

	/**
	 * Test that $exit_on_error in the loggers will exit as expected even if
	 * output logging is disabled via a filter.
	 * 
	 * The WP_CLI migrators use $exit_on_error as an execution control while doing output. 
	 * This test will verify that $exit_on_error will still run properly even if output logging
	 * is disabled.
	 * 
	 * Since the disable logging filter is primary used for PHPUnit, an exception is thrown
	 * instead of wp_die.
	 * 
	 * @return void
	 */
	public function test_file_no_logging_but_still_exit(): void {

		// Turn off logging.
		add_filter( 'newspack_migration_tools_log_file_logger_disable', '__return_true' );

		// Verify $exit_on_error still works even though logging is disabled via filter.
		$this->expectExceptionMessage( 'File logging disabled with exit_on_error.' );
		FileLogger::log( $this->log_file, 'Oops, exit.', Log::ERROR, true );
	}
}
