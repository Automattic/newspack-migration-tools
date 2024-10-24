<?php

namespace Newspack\MigrationTools\Util\Log;

use Monolog\Handler\NullHandler;
use Monolog\Logger;

class PlainFileLog {

	/**
	 * Get logger for writing plain unformatted lines to a file.
	 *
	 * Note that by default the logger logs to /dev/null unless you enable it by returning true in
	 * the newspack_migration_tools_enable_plain_log filter.
	 *
	 * If you implement the newspack_migration_tools_log_dir filter to return a directory path,
	 *  the log file will be created in that directory.
	 *
	 * @param string $name          The name of the logger (used in the output).
	 * @param string $log_file_name The name of the log file.
	 *
	 * @return Logger Logger instance.
	 */
	public static function create_logger( string $name, string $log_file_name ): Logger {
		$logger = new Logger( $name );
		if ( ! apply_filters( 'newspack_migration_tools_enable_plain_log', false ) ) {
			$logger->pushHandler( new NullHandler() );

			return $logger;
		}

		$yes = fn() => true;

		// Enable the file log for a sec if it isn't already enabled.
		add_filter( 'newspack_migration_tools_enable_file_log', $yes );
		$logger = FileLog::create_logger( $name, $log_file_name, new PlainLineFormatter() );
		// Remove our yes filter.
		remove_filter( 'newspack_migration_tools_enable_file_log', $yes );

		return $logger;
	}
}
