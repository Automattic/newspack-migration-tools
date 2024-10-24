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

	private string $name;

	/**
	 * MultiLog constructor.
	 *
	 * @param string   $name    The name of the logger.
	 * @param Logger[] $loggers Array of loggers - empty by default. You can also add loggers later using addLogger method.
	 */
	public function __construct( string $name, array $loggers = [] ) {
		array_map( fn( $logger ) => $this->addLogger( $logger ), $loggers );
		$this->name = $name;
		LoggerManager::addLogger( $this );
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * Adds a logger to the list of loggers.
	 *
	 * @param Logger $logger Logger to add.
	 */
	public function addLogger( Logger $logger ): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- We're following the Monolog naming convention.
		$this->loggers[] = $logger;
	}

	/**
	 * {@inheritDoc}
	 */
	public function log( $level, Stringable|string $message, array $context = [] ): void {
		array_map( fn( $logger ) => $logger->log( $level, $message, $context ), $this->loggers );
	}
}
