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

			// we can't throw an exception because this will end up in debug.log when run from WP_CLI
			// throw new \Exception( 'nope...' );
			
			// Use wp_die (instead of exit) so WP_CLI and PHPUnit can exit more gracefully.
			// Using wp_die will allow PHPUnit to show call stack and continue running other tests too.
			// Set argument to empty array/object so WP_CLI doesn't show a blank "Error:" line as last output.
			
			// we can't use empty array because this will throw error when WP_CLI tries to trim() array
			// wp_die( [] );

			// we can't use empty () because WP_CLI will print a redundant blank "Error:" line in CLI
			// wp_die();

			// we'd have to use a string:
			// wp_die( ' -- wp_cli has exited --');

			// otherwise, if we don't want the redundent error message, we have to create our or own die hander...
			// actually this wont work in PHPUnit without an additional die_handler with a higher priority.
			// add_filter( 'wp_die_handler', fn() => fn() => exit );
			// wp_die();

			// Nevermind about everything above...Ron can't find a way to have one command that works in
			// both PHPUnit and WP_CLI.  So do differnet things based on context. 

			// If PHPUnit throw an exception.
			if ( isset( $GLOBALS['phpunit_version'] ) ) {
				throw new \Exception( 'CLILogger exit_on_error.' );
			}

			// Let WP_CLI do a normal exit.
			exit( 1 );
		}
	}
}
