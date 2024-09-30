<?php
/**
 * Logger class for handling commands' logging
 *
 * @package NewspackCustomContentMigrator
 */

namespace Newspack\MigrationTools\Util;

use Newspack\MigrationTools\Log\FileLogger;
use Newspack\MigrationTools\Log\Log;
use WP_CLI;

/**
 * Class for handling commands' logging
 */
class Logger {

	const LE_LOG_DIRECTORY = 'newspack_le_logs';

	const WARNING = Log::WARNING;
	const LINE    = Log::LINE;
	const SUCCESS = Log::SUCCESS;
	const ERROR   = Log::ERROR;
	const INFO    = Log::INFO;

	/**
	 * Determine the writeable directory used for storing logs created by migration commands.
	 *
	 * @return string Directory path.
	 */
	public function get_le_log_path( $filename ) {

		$log_dir = get_temp_dir() . self::LE_LOG_DIRECTORY;
		if ( ! file_exists( $log_dir ) ) {
			mkdir( $log_dir ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_mkdir
		}

		return $log_dir . DIRECTORY_SEPARATOR . $filename . '.log';
	}

	/**
	 * Simple file logging.
	 *
	 * @param string $file          File name or path.
	 * @param string $message       Log message.
	 * @param string $level         Whether to output the message to the CLI. Default to `line` CLI level.
	 * @param bool   $exit_on_error Whether to exit on error.
	 *
	 * @return void
	 */
	public function log( $file, $message, string $level = Log::LINE, bool $exit_on_error = false ): void {
		$level = strtoupper( $level ); // Upper case for backwards compatibility.
		FileLogger::log( $file, $message, $level, $exit_on_error );
		$this->wp_cli_log( $message, $level, $exit_on_error );
	}

	/**
	 * Output log message to CLI with WP_CLI.
	 *
	 * @param string $message       Log message.
	 * @param string $level         Log level - see constants in Log class.
	 * @param bool   $exit_on_error Whether to exit the script on error – default is false.
	 *
	 * @return void
	 */
	public function wp_cli_log( string $message, string $level = Log::LINE, bool $exit_on_error = false ): void {
		if ( $level ) {
			switch ( $level ) {
				case ( Log::SUCCESS ):
					WP_CLI::success( $message );
					break;
				case ( Log::WARNING ):
					WP_CLI::warning( $message );
					break;
				case ( Log::ERROR ):
					WP_CLI::error( $message, $exit_on_error );
					break;
				case ( Log::INFO ):
					$label = 'Info';
					$color = '%B';
					$label = \cli\Colors::colorize( "$color$label:%n", true );

					WP_CLI::line( "$label $message" );
					break;
				case ( Log::LINE ):
				default:
					WP_CLI::line( $message );
					break;
			}
		}
	}
}
