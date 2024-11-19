<?php

namespace Newspack\MigrationTools\Util\Log;

use Monolog\Level;
use Newspack\MigrationTools\NMT;
use Psr\Log\LogLevel;
use UnhandledMatchError;

/**
 * Class for handling commands' logging.
 *
 * @deprecated Use the  CliLog, FileLog, and Multilog classes instead.
 */
class Logger {

	const WARNING = 'warning';
	const LINE    = 'line';
	const SUCCESS = 'success';
	const ERROR   = 'error';

	/**
	 * File logging.
	 *
	 * @deprecated Use the  CliLog, FileLog, and Multilog classes instead.
	 *
	 * @param string $file          Log file name.
	 * @param string $message       Log message.
	 * @param string $level         Log level - default is INFO.
	 * @param bool   $exit_on_error Whether to exit on error.
	 *
	 * @return void
	 */
	public function log( string $file, string $message, string $level = LogLevel::INFO, bool $exit_on_error = false ): void {
		$filename    = pathinfo( $file, PATHINFO_FILENAME ); // File name without extension.
		$file_logger = FileLog::get_logger(
			$filename,
			basename( $file ) // File name.
		);
		$cli_logger  = CliLog::get_logger( $filename );

		try {
			$log_level = Level::fromName( $level );
		} catch ( UnhandledMatchError $e ) {
			$log_level = Level::fromName( Level::Info );
		}

		if ( $exit_on_error ) {
			NMT::exit_with_message( $message, [ $cli_logger, $file_logger ] );
		}
		$file_logger->log( $log_level->getName(), $message );
		$cli_logger->log( $log_level->getName(), $message );
	}
}
