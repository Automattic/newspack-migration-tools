<?php

namespace Newspack\MigrationTools\Util\Log;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Newspack\MigrationTools\NMT;
use Psr\Log\LoggerInterface;

class FileLog {

	use LoggerManagerTrait;

	/**
	 * Get logger for logging to file.
	 *
	 * Note that by default the logger logs to /dev/null unless you enable it by returning true in
	 *  the newspack_migration_tools_enable_file_log filter.
	 *
	 * If you implement the newspack_migration_tools_log_dir filter to return a directory path,
	 * the log file will be created in that directory.
	 *
	 * @param string                  $name          The name of the logger (used in the output).
	 * @param string                  $log_file_name Optional name of the log file.
	 * @param FormatterInterface|null $formatter     Optional formatter to use. Defaults to Monolog\Formatter\LineFormatter.
	 *
	 * @return Logger
	 */
	public static function get_logger( string $name, string $log_file_name = '', ?FormatterInterface $formatter = null ): LoggerInterface {
		if ( empty( $log_file_name ) ) {
			// Just stick ".log" to the end of the name and sanitize it.
			$log_file_name = sanitize_file_name( $name . '.log' );
		}

		$log_id = $name . ':' . $log_file_name;
		$logger = self::get_existing_logger( $log_id );
		if ( $logger ) {
			return $logger;
		}

		$logger = new Logger( $name );
		if ( ! apply_filters( 'newspack_migration_tools_enable_file_log', false ) ) {
			$logger->pushHandler( new NullHandler() );
			self::add_new_logger( $log_id, $logger );

			return $logger;
		}


		$log_dir = apply_filters( 'newspack_migration_tools_log_dir', defined( 'NMT_LOG_DIR' ) ? NMT_LOG_DIR : dirname( $log_file_name ) );
		if ( ! $log_dir || ! is_dir( $log_dir ) || ! is_writable( $log_dir ) ) {
			$log_dir = '';
		} else {
			$log_dir = trailingslashit( $log_dir );
		}

		$basename  = basename( $log_file_name );
		$file_path = $log_dir . $basename;
		$handler   = new StreamHandler( $file_path, NMT::get_log_level() );
		if ( null === $formatter ) {
			$formatter = new LineFormatter( null, 'Y-m-d H:i:s', true, true );
		}
		$handler->setFormatter( $formatter );

		$logger->pushHandler( $handler );

		self::add_new_logger( $log_id, $logger );

		return $logger;
	}

	/**
	 * Truncate file(s) on disk.
	 *
	 * Loggers write to disk files using handlers. For FileLog, the handler is StreamHandler,
	 * which handles the underlying fopen, fwrite, etc.  Loggers can have multiple handlers,
	 * so each handler's file (if exists) will be truncated.
	 *
	 * @param Logger $logger Logger object.
	 * @return int Count of truncated files.
	 */
	public static function truncate_files( Logger $logger ): int {

		$truncated_count = 0;
		foreach ( $logger->getHandlers() as $handler ) {

			// Only StreamHandler.
			if ( ! $handler instanceof StreamHandler ) {
				continue;
			}

			// Get the file path. (Note: MonoLog stores the path as "url").
			$file_path = $handler->getUrl();

			// No need to truncate if file doesn't exist, isn't local, isn't writeable, or non-file stream (scheme ://), etc
			if ( empty( $file_path ) || ! is_file( $file_path ) || ! stream_is_local( $file_path ) || ! is_writable( $file_path ) ) {
				continue;
			}

			// Try to use the existing file resource, otherwise a new resource will need to be opened.
			$file_resource = $handler->getStream();

			// If file resource is not already open, attempt to open it, but do not create it.
			if ( ! is_resource( $file_resource ) ) {

				// Just open in reading and writing mode so we don't create the file if it doesn't already exist.
				// Use @ to avoid a PHP warning incase file doesn't already exist.
				$file_resource = @fopen( $file_path, 'r+' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen
				if ( false === $file_resource ) {
					// file doesn't exist, so no need to truncate.
					continue;
				}
			}

			// Truncate and must rewind the resource.
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_ftruncate
			if ( ftruncate( $file_resource, 0 ) && rewind( $file_resource ) ) {
				++$truncated_count;
			}

			// Close the stream if we opened it just for truncation (ie: the handler stream was not open).
			if ( is_resource( $file_resource ) && ! is_resource( $handler->getStream() ) ) {
				fclose( $file_resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}
		}

		return $truncated_count;
	}
}
