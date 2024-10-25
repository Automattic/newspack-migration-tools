<?php

namespace Newspack\MigrationTools\Util\Log;

use Psr\Log\LoggerInterface;

/**
 * Trait that keeps track of loggers.
 *
 * Use on a logger that needs to keep a static stash of loggers to avoid creating multiple instances of the same logger.
 */
trait LoggerManagerTrait {

	protected static array $loggers = [];

	/**
	 * Add a logger.
	 *
	 * @param string          $name   The name of the logger.
	 * @param LoggerInterface $logger The logger to add.
	 *
	 * @return void
	 */
	protected static function add_new_logger( string $name, LoggerInterface $logger ): void {
		$name                   = str_replace( ' ', '', $name );
		self::$loggers[ $name ] = $logger;
	}

	/**
	 * Get a logger by name.
	 *
	 * @param string $name The name of the logger.
	 *
	 * @return LoggerInterface|bool The logger or false if not found.
	 */
	protected static function get_existing_logger( string $name ): bool|LoggerInterface {
		$name = str_replace( ' ', '', $name );

		return self::$loggers[ $name ] ?? false;
	}
}
