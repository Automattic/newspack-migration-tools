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
	 * Truncate files on disk.
	 * 
	 * Loggers write to disk files using handlers. For FileLog, the handler is StreamHandler,
	 * which handles the underlying fopen, fwrite, etc.  A file must first be fopen and a resource handler
	 * assigned befora truncate can happen. Creating a Logger does not create the file.
	 * MonoLog needs to "write" once to open the file (resource) so it can then be truncated.
	 * 
	 * Loggers can have multiple handlers, so each handler's file (if exists) will be truncated.
	 *
	 * @param Logger $logger Logger object.
	 * @return int Count of truncated files.
	 * 
	 * @throws If MonoLog is unable to fopen/fwrite, an exception will be thrown.
	 */
	public static function truncate_files( Logger $logger ): int {

		$truncated_count = 0;
		foreach ( $logger->getHandlers() as $handler ) {
			
			// Only StreamHandler at this time since that is the stream type used above in the get_logger function.
			if ( ! $handler instanceof StreamHandler ) {
				continue;
			}

			// Monolog file resource.
			$file_resource = $handler->getStream();

			// If file is not open, write a blank line so MonoLog will do it's magic and open the file.
			// Note: Creating a Logger does not create the file...MonoLog needs to "write" once to open the file resource.
			if ( ! is_resource( $file_resource ) ) {

				// try {
					// Write a blank message so MonoLog will open the recource. Catch any fopen/fwrite errors now.
					$handler->handle( new LogRecord(
						datetime: new \DateTimeImmutable(),
						channel: 'app',
						level: Level::Info,
						message: '',
				)	 );
				// } catch ( \Throwable $e ) {                   
				// 	throw $e;
				// }

				// Set the resource to the opened file.
				$file_resource = $handler->getStream();
			}

			ftruncate( $file_resource, 0 );
			rewind( $file_resource );
			++$truncated_count;
		}
		
		return $truncated_count;
	}
}
