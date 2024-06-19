<?php

namespace Newspack\MigrationTools\Log;

class FileLogger extends Log {

	/**
	 * Simple log to file.
	 *
	 * If you want fancier logging, use the `newspack_migration_tools_log` action. You can disable the simple
	 * logging by using the `newspack_migration_tools_log_disable_default` filter.
	 *
	 * @param string $file          File name or path.
	 * @param string $message       Log message.
	 * @param string $level         Log level. See constants in this class.
	 * @param bool   $exit_on_error Whether to exit on error.
	 */
	public static function log( string $file, string $message, string $level = 'line', bool $exit_on_error = false ): void {
		/**
		 * Filter the file path for the log file.
		 *
		 * @since 0.0.1
		 *
		 * @param string $file The file path.
		 */
		$file_path = apply_filters( 'newspack_migration_tools_logger_file_path_default', $file );

		/**
		 * Fires before default logging.
		 *
		 * @since 0.0.1
		 *
		 * @param string $file          File name or path.
		 * @param string $message       Log message.
		 * @param string $level         Log level. See constants in parent class.
		 * @param bool   $exit_on_error Whether to exit on error.
		 */
		do_action( 'newspack_migration_tools_logger_log', $file, $message, $level, $exit_on_error );

		/**
		 * Filter to disable file logging.
		 *
		 * @since 0.0.1
		 *
		 * @param bool $disable_default If not false then disable file logging.
		 */
		if ( apply_filters( 'newspack_migration_tools_log_file_logger_disable', false ) ) {
			return;
		}

		// Write to log file.
		file_put_contents( $file_path, self::get_formatted_message( $message, $level, false ), FILE_APPEND );
		// Also log to CLI.
		CliLogger::log( $message, $level, $exit_on_error );
	}
}
