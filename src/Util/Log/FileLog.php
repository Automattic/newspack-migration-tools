<?php

namespace Newspack\MigrationTools\Util\Log;

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Newspack\MigrationTools\NMT;
use Psr\Log\LoggerInterface;

class FileLog {

	/**
	 * Get logger for logging to file.
	 *
	 * Note that by default the logger logs to /dev/null unless you enable it by returning true in
	 *  the newspack_migration_tools_enable_file_log filter.
	 *
	 * If you implement the newspack_migration_tools_log_dir filter to return a directory path,
	 * the log file will be created in that directory.
	 *
	 * @param string $name          The name of the logger (used in the output).
	 * @param string $log_file_name The name of the log file.
	 *
	 * @return LoggerInterface
	 */
	public static function get_logger( string $name, string $log_file_name ): LoggerInterface {
		$logger = new Logger( $name );
		if ( ! apply_filters( 'newspack_migration_tools_enable_file_log', false ) ) {
			$logger->pushHandler( new NullHandler() );

			return $logger;
		}
		$log_dir = apply_filters( 'newspack_migration_tools_log_dir', false );
		if ( ! empty( $log_dir ) && is_dir( $log_dir ) ) {
			$log_dir       = untrailingslashit( $log_dir );
			$basename      = basename( $log_file_name );
			$log_file_name = "{$log_dir}/$basename";
		}

		$logger->pushHandler( new StreamHandler( $log_file_name, NMT::get_log_level() ) );

		return $logger;
	}
}
