<?php

namespace Newspack\MigrationTools;

use Monolog\Level;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

defined( 'ABSPATH' ) || exit;

/**
 * Class NMT
 *
 * Main class for the Newspack Migration Tools plugin.
 */
class NMT {

	/**
	 * Log level.
	 *
	 * @see \Monolog\Level
	 * @var Level
	 */
	private Level $log_level;

	/**
	 * Private on purpose
	 */
	private function __construct() {
		require_once realpath( __DIR__ . '/../vendor/autoload.php' );
		$this->log_level = Level::fromName( LogLevel::DEBUG );

		if ( defined( 'NMT_LOG_LEVEL' ) && in_array( NMT_LOG_LEVEL, array_map( fn( $value ) => $value->name, Level::cases() ) ) ) {
			$this->log_level = Level::fromName( NMT_LOG_LEVEL );
		}
	}

	/**
	 * Get the singleton if that is your thing.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		static $instance = null;
		if ( null === $instance ) {
			$instance = new NMT();
		}

		return $instance;
	}

	/**
	 * Call this to get things started (or include ./newspack-migration-tools.php).
	 */
	public static function setup() {
		self::get_instance();
	}

	/**
	 * Get the log level for the NMT.
	 *
	 * @return Level
	 */
	public static function get_log_level(): Level {
		return self::get_instance()->log_level;
	}

	/**
	 * Logs the message using an array of LoggerInterface instances.
	 *
	 * @param string                 $message What to say as the last words – this is output no matter if loggers are passed too.
	 * @param LoggerInterface[]|null $loggers An optional array of LoggerInterface instances that will log the message.
	 *
	 * @return void
	 */
	public static function exit_with_message( string $message, array $loggers = [] ): void {
		array_map( fn( LoggerInterface $logger ) => $logger->critical( $message ), $loggers );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_die( $message );
	}
}
