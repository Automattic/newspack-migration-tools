<?php

namespace Newspack\MigrationTools\Util\Log;

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Newspack\MigrationTools\NMT;

class CliLog {

	/**
	 * Get logger for CLI.
	 *
	 * Note that by default the logger logs to /dev/null unless you enable it by returning true in
	 * the newspack_migration_tools_enable_cli_log filter.
	 *
	 * It also logs to /dev/null if the script is not running in CLI mode.
	 *
	 * @param string                  $name      The name of the logger (used in the output).
	 * @param FormatterInterface|null $formatter Optional formatter to use. Defaults to Bramus\Monolog\Formatter\ColoredLineFormatter.
	 *
	 * @return Logger Logger instance.
	 */
	public static function create_logger( string $name, FormatterInterface $formatter = null ): Logger {
		$logger = new Logger( $name );
		if ( ! apply_filters( 'newspack_migration_tools_enable_cli_log', false ) || PHP_SAPI !== 'cli' ) {
			$logger->pushHandler( new NullHandler() );

			return $logger;
		}
		$handler = new StreamHandler( 'php://stdout', NMT::get_log_level() );
		if ( null === $formatter ) {
			$formatter = new ColoredLineFormatter( null, null, 'Y-m-d H:i:s', true, true );
		}
		$handler->setFormatter( $formatter );
		$logger->pushHandler( $handler );

		return $logger;
	}
}
