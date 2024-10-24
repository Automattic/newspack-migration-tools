<?php

namespace Newspack\MigrationTools\Util\Log;

use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Stringable;

/**
 * Class MultiLog.
 *
 * A logger that logs to multiple loggers.
 */
class MultiLog implements LoggerInterface {

	use LoggerTrait;

	private array $loggers = [];

	/**
	 * MultiLog constructor.
	 *
	 * @param Logger[] $loggers Array of loggers - empty by default. You can also add loggers later using addLogger method.
	 */
	public function __construct( array $loggers = [] ) {
		array_map( fn( $logger ) => $this->addLogger( $logger ), $loggers );
	}

	/**
	 * Adds a logger to the list of loggers.
	 *
	 * @param Logger $logger Logger to add.
	 */
	public function addLogger( Logger $logger ): void {
		$this->loggers[] = $logger;
	}

	public function log( $level, Stringable|string $message, array $context = [] ): void {
		array_map( fn( $logger ) => $logger->log( $level, $message, $context ), $this->loggers );
	}
}
