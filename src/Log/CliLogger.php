<?php

namespace Newspack\MigrationTools\Log;

class CliLogger extends Log {

	/**
	 * Log a line to CLI.
	 *
	 * @param string $message Log message.
	 *
	 * @return void
	 */
	public static function line( string $message ): void {
		self::log( $message, self::LINE, false );
	}

	/**
	 * Log a warning line to CLI (yellow).
	 *
	 * @param string $message Log message.
	 *
	 * @return void
	 */
	public static function warning( string $message ): void {
		self::log( $message, self::WARNING, false );
	}

	/**
	 * Log an error line to CLI (red).
	 *
	 * @param string $message       Log message.
	 * @param bool   $exit_on_error Whether to exit the script on error – default is false.
	 *
	 * @return void
	 */
	public static function error( string $message, bool $exit_on_error = false ): void {
		self::log( $message, self::ERROR, $exit_on_error );
	}

	/**
	 * Output a line to CLI.
	 *
	 * @param string $message       Log message.
	 * @param string $level         Log level. See constants in parent class.
	 * @param bool   $exit_on_error Whether to exit the script on error – default is false.
	 *
	 * @return void
	 * 
	 * @throws \Exception If logging is disabled, but $exit_on_error is true. Primarily used by PHPUnit.
	 */
	public static function log( string $message, string $level = self::INFO, bool $exit_on_error = false ): void {
		/**
		 * Fires before default logging to CLI.
		 *
		 * @since 0.0.1
		 *
		 * @param string $message       Log message.
		 * @param string $level         Log level. See constants in this class.
		 * @param bool   $exit_on_error Whether to exit on error.
		 */
		do_action( 'newspack_migration_tools_cli_log', $message, $level, $exit_on_error );

		/**
		 * Filter to disable cli logging.
		 *
		 * @since 0.0.1
		 *
		 * @param bool $disable_default If not false then cli default logging.
		 */
		if ( apply_filters( 'newspack_migration_tools_log_clilog_disable', false ) ) {
			if ( $exit_on_error ) {
				// We still need to exit even if logging was disabled.
				// Since this filter is generally used in testing, throw exception instead of wp_die.
				throw new \Exception( 'CLI logging disabled with exit_on_error.' );
			}
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::get_formatted_message( $message, $level, true );

		if ( $exit_on_error ) {
			
			// We can't do exit because this will cause PHPUnit to stop running all subsequent tests.
			// exit( 1 );

			// We can't just throw an exception because this will end up in wp-content/debug.log when run from WP_CLI.
			// It would confuse developers why the debug.log is filling up with the exception below and
			// that this exception isn't the reason their WP_CLI is actually "exiting" in the first place.
			// throw new \Exception( 'exiting the cli' );
						
			// We could do a check if PHPUnit else WP_CLI...this would look like the following:
			// if ( isset( $GLOBALS['phpunit_version'] ) ) throw new \Exception( 'CLILogger exit_on_error.' );
			// else exit( 1 );
			// But we've decided to try to avoid having divergent code between PHPUnit and normal execution.

			// In the end, we can use wp_die() so WP_CLI and PHPUnit can both exit gracefully.
			// Using wp_die will causes PHPUnit to throw an exception which can be caught, and it also 
			// allows other PHPUnit tests to keep running, unlike "exit()".

			// In our call to wp_die() there are some caveats:

			// We can't use empty array or object because this will throw an error as WP_CLI expects a string.
			// wp_die( [] );

			// We can't use an empty argument/string because WP_CLI will print a blank "Error:" line in the CLI
			// wp_die();

			// We have to use a string with length...so we'll just print something helpful.
			wp_die( ' -- cli_logger has exited --' );

		}
	}
}
