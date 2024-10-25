<?php

namespace Newspack\MigrationTools\Util\Log;

use InvalidArgumentException;
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
	use LoggerManagerTrait;

	/**
	 * This is the array of loggers that this logger logs to.
	 *
	 * Be careful to not confuse this with the static $loggers array in LoggerManagerTrait.
	 *
	 * @var array LoggerInterface[]
	 */
	private array $multilog_loggers = [];

	/**
	 * Constructor.
	 *
	 * @param string   $name    The name of the logger.
	 * @param Logger[] $loggers Array of loggers - each wil log when this logger logs.
	 *
	 * @throws InvalidArgumentException If any of the loggers do not implement the LoggerInterface.
	 */
	private function __construct( string $name, array $loggers ) {
		self::validate_logger_instances( $loggers );
		// Add the logger instances to log to.
		// phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found -- This is the first assignment. Above was just validation.
		array_map( fn( $logger ) => $this->multilog_loggers[] = $logger, $loggers );
		// Add this instance (with the loggers inside it) to the log manager.
		self::add_new_logger( $name, $this );
	}

	/**
	 * Get a logger that logs to multiple loggers.
	 *
	 * @param string   $name    The name of the logger.
	 * @param Logger[] $loggers Array of loggers - each wil log when this logger logs.
	 *
	 * @throws InvalidArgumentException If any of the loggers do not implement the LoggerInterface.
	 */
	public static function get_logger( string $name, array $loggers ): LoggerInterface {
		$name   = self::get_name_from_name_and_loggers( $name, $loggers );
		$logger = self::get_existing_logger( $name );
		if ( $logger ) {
			return $logger;
		}

		return new self( $name, $loggers );
	}

	/**
	 * {@inheritDoc}
	 */
	public function log( $level, Stringable|string $message, array $context = [] ): void {
		array_map( fn( $logger ) => $logger->log( $level, $message, $context ), $this->multilog_loggers );
	}

	/**
	 * Validate that all array entries are instances of LoggerInterface.
	 *
	 * @param array $loggers Array of loggers to validate.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException If any of the array entries don't implement the LoggerInterface.
	 */
	private static function validate_logger_instances( array $loggers ): void {
		foreach ( $loggers as $logger ) {
			if ( ! $logger instanceof LoggerInterface ) {
				throw new InvalidArgumentException( 'All loggers must implement the LoggerInterface.' );
			}
		}
	}

	/**
	 * Helper to get a unique name from the name AND the list of loggers.
	 *
	 * @param string $name    The name of the logger.
	 * @param array  $loggers Array of loggers.
	 *
	 * @return string The unique name - it's an ugly string but it works.
	 */
	private static function get_name_from_name_and_loggers( string $name, array $loggers ): string {
		self::validate_logger_instances( $loggers );

		// The SPL object hash is fast, but produces gibberish. Luckily, the name is not used anywhere visibly.
		return array_reduce( $loggers, fn( $carry, $logger ) => $carry .= spl_object_hash( $logger ), $name );
	}
}
