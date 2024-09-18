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
			// Use wp_die (instead of exit) so WP_CLI and PHPUnit can exit more gracefully.
			// Using wp_die will allow PHPUnit to show call stack and continue running other tests too.
			// Set argument to empty array/object so WP_CLI doesn't show a blank "Error:" line as last output.
			wp_die( [] );
		}
	}
}
