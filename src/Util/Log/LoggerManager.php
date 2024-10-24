<?php

namespace Newspack\MigrationTools\Util\Log;

use ErrorException;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Class that keeps loggers and provides a way to get them by name.
 */
class LoggerManager {

	protected static array $loggers = [];

	/**
	 * Get a logger by name.
	 *
	 * If the logger is not found, an ErrorException is thrown.
	 *
	 * @param string $name The name of the logger.
	 *
	 * @return LoggerInterface The logger.
	 * @throws ErrorException If the logger is not found.
	 */
	public static function get_logger_by_name( string $name ): LoggerInterface {
		$logger = self::$loggers[ $name ];
		if ( empty( $logger ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new ErrorException( sprintf( 'Logger not found: %s', $name ) );
		}

		return $logger;
	}

	/**
	 * Add a logger to the manager
	 *
	 * @param MultiLog|Logger $logger The logger to add.
	 *
	 * @return void
	 */
	public static function addLogger( MultiLog|Logger $logger ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		self::$loggers[ $logger->getName() ] = $logger;
	}
}
