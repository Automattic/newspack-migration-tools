<?php

namespace Newspack\MigrationTools\Util\Log;

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Newspack\MigrationTools\NMT;
use Psr\Log\LoggerInterface;

class CliLog {

	use LoggerManagerTrait;

	/**
	 * Get logger for CLI.
	 *
	 * Note that by default the logger logs to /dev/null unless you enable it by returning true in
	 * the newspack_migration_tools_enable_cli_log filter.
	 *
	 * It also logs to /dev/null if the script is not running in CLI mode.
	 *
	 * @param string             $name      The name of the logger (used in the output).
	 * @param FormatterInterface $formatter Optional formatter to use. Defaults to Bramus\Monolog\Formatter\ColoredLineFormatter.
	 *
	 * @return Logger Logger instance.
	 */
	public static function get_logger( string $name, FormatterInterface $formatter = null ): LoggerInterface {
		$logger = self::get_existing_logger( $name );
		if ( $logger ) {
			return $logger;
		}
		$logger = new Logger( $name );

		if ( ! apply_filters( 'newspack_migration_tools_enable_cli_log', false ) || PHP_SAPI !== 'cli' ) {
			$logger->pushHandler( new NullHandler() );
		} else {
			$handler = new StreamHandler( 'php://stdout', NMT::get_log_level() );
			if ( null === $formatter ) {
				$format      = apply_filters( 'nmt_cli_log_format', LineFormatter::SIMPLE_FORMAT );
				$date_format = apply_filters( 'nmt_cli_log_date_format', 'Y-m-d H:i:s' );
				if ( defined( 'NMT_CLI_LOG_FORMAT' ) ) {
					$format = NMT_CLI_LOG_FORMAT;
				}
				if ( defined( 'NMT_CLI_LOG_DATE_FORMAT' ) ) {
					$date_format = NMT_CLI_LOG_DATE_FORMAT;
				}
				$formatter = new ColoredLineFormatter(
					null,
					apply_filters( 'nmt_cli_log_format', $format ),
					apply_filters( 'nmt_cli_log_date_format', $date_format ),
					true,
					true
				);
			}
			$handler->setFormatter( $formatter );
			$logger->pushHandler( $handler );
		}

		self::add_new_logger( $name, $logger );

		return $logger;
	}
}
