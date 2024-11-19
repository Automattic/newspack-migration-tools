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
	public static function get_logger( string $name, string $log_file_name = '', FormatterInterface $formatter = null ): LoggerInterface {
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
}
