<?php

namespace Newspack\MigrationTools\Util;

class Logger {

	const WARNING = 'warning';
	const LINE    = 'line';
	const SUCCESS = 'success';
	const ERROR   = 'error';
	const INFO    = 'info';

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
	public static function log( $file, $message, $level = 'line', bool $exit_on_error = false ) {
		/**
		 * Filter the file path for the log file.
		 *
		 * @since 0.0.1
		 *
		 * @param string $file The file path.
		 */
		$file_path = apply_filters( 'newspack_migration_tools_log_file_path_default', $file );

		/**
		 * Fires before default logging.
		 *
		 * @since 0.0.1
		 *
		 * @param string $file          File name or path.
		 * @param string $message       Log message.
		 * @param string $level         Log level. See constants in this class.
		 * @param bool   $exit_on_error Whether to exit on error.
		 */
		do_action( 'newspack_migration_tools_log', $file, $message, $level, $exit_on_error );

		/**
		 * Filter to disable default logging.
		 *
		 * @since 0.0.1
		 *
		 * @param bool $disable_default If not false then disable default logging.
		 */
		if ( apply_filters( 'newspack_migration_tools_log_disable_default', false ) ) {
			return;
		}

		// Allow disabling default logging.
		if ( defined( 'NEWSPACK_MIGRATION_TOOLS_DISABLE_DEFAULT_LOGGING' ) && ! empty( NEWSPACK_MIGRATION_TOOLS_DISABLE_DEFAULT_LOGGING ) ) {
			return;
		}

		if ( in_array( $level, [ self::SUCCESS, self::WARNING, self::ERROR ], true ) ) {
			// Prepend the level to the message for easier grepping in the log file.
			$message = sprintf( '%s: %s', strtoupper( $level ), $message );
		}

		file_put_contents( $file_path, "{$message}\n", FILE_APPEND );
		echo "{$message}\n";
	}

}
