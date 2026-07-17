<?php

namespace Newspack\MigrationTools\Util\Log;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Newspack\MigrationTools\NMT;
use Psr\Log\LoggerInterface;
use Throwable;

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
	 * which handles the underlying fopen, fwrite, etc.  A file must first be fopen and a resource handler
	 * assigned before truncate can happen.
	 * 
	 * Note: Creating a Logger does not create the file...MonoLog needs to "write" once to open the
	 * file (resource) so it can then be truncated.
	 * 
	 * Loggers can have multiple handlers, so each handler's file (if exists) will be truncated.
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

			// Monolog file resource.
			$file_resource = $handler->getStream();

			// If file resource is not already open, attempt to open it, but do not create it.
			if ( ! is_resource( $file_resource ) ) {

				// Get the file path. Note: MonoLog names the path as "url" since it could support "scheme://..." paths.
				$file_path = $handler->getUrl();

				// No need to truncate if file doesn't already exist.
				// todo: put this back in!!!
				// if ( empty( $file_path ) || ! is_file( $file_path ) || ! is_writable( $file_path ) ) {
				// 	continue;
				// }

				$steam_is_local = stream_is_local( $file_path );
				$parse_url = parse_url( $file_path, PHP_URL_SCHEME );
				
				// Just open in reading and writing mode so we don't create the file if it doesn't already exist.
				// Use @ to avoide a PHP warning if file doesn't already exist.
				$file_resource = @fopen( $file_path, 'r+' );
				if ( $file_resource === false ) {
					// file doesn't exist, so no need to truncate.
					continue;
				}
			}

			// Truncate and must rewind the resource.
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_ftruncate
			if ( ftruncate( $file_resource, 0 ) && rewind( $file_resource ) ) {
				++$truncated_count;
			}
		}
		
		return $truncated_count;
	}
}
