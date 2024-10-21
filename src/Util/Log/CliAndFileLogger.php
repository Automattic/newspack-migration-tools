<?php

namespace Newspack\MigrationTools\Util\Log;


use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class CliAndFileLogger implements LoggerInterface {

	private Logger $file_logger;
	private Logger $cli_logger;

	public function __construct( string $name, string $log_file_name ) {
		$this->file_logger = FileLog::get_logger( $name, $log_file_name );
		$this->cli_logger = CliLog::get_logger( $name );
	}

	public function emergency( \Stringable|string $message, array $context = [] ) {
		$this->log( LogLevel::EMERGENCY, $message, $context );
	}

	public function alert( \Stringable|string $message, array $context = [] ) {
		$this->log( LogLevel::ALERT, $message, $context );
	}

	public function critical( \Stringable|string $message, array $context = [] ) {
		$this->log( LogLevel::CRITICAL, $message, $context );
	}

	public function error( \Stringable|string $message, array $context = [] ) {
		$this->log( LogLevel::ERROR, $message, $context );
	}

	public function warning( \Stringable|string $message, array $context = [] ) {
		$this->log( LogLevel::WARNING, $message, $context );
	}

	public function notice( \Stringable|string $message, array $context = [] ) {
		$this->log( LogLevel::NOTICE, $message, $context );
	}

	public function info( \Stringable|string $message, array $context = [] ) {
		$this->log( LogLevel::INFO, $message, $context );
	}

	public function debug( \Stringable|string $message, array $context = [] ) {
		$this->log( LogLevel::DEBUG, $message, $context );
	}

	public function log( $level, \Stringable|string $message, array $context = [] ) {
		$this->file_logger->log( $level, $message, $context );
		$this->cli_logger->log( $level, $message, $context );
	}
}