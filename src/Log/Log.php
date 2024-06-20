<?php

namespace Newspack\MigrationTools\Log;

class Log {

	/**
	 * @var string
	 */
	public const LINE = 'LINE';
	/**
	 * @var string
	 */
	public const INFO = 'INFO';
	/**
	 * @var string
	 */
	public const SUCCESS = 'SUCCESS';
	/**
	 * @var string
	 */
	public const WARNING = 'WARNING';
	/**
	 * @var string
	 */
	public const ERROR = 'ERROR';

	/**
	 * Get a formatted message with color (if colorize is true) and label prefix.
	 *
	 * @param string $message  Log message.
	 * @param string $level    Log level (see constants in this class).
	 * @param bool   $colorize Whether to colorize the output.
	 *
	 * @return string
	 */
	protected static function get_formatted_message( string $message, string $level, bool $colorize ): string {
		if ( ! in_array( $level, [ self::INFO, self::SUCCESS, self::WARNING, self::ERROR ], true ) ) {
			return $message . PHP_EOL;
		}

		$label = $level;
		if ( $colorize ) {
			$color_code = false;
			switch ( $level ) {
				case self::INFO:
					$color_code = '1;34'; // Blue, bold.
					break;
				case self::SUCCESS:
					$color_code = '1;32'; // Green, bold.
					break;
				case self::WARNING:
					$color_code = '1;33'; // Yellow, bold.
					break;
				case self::ERROR:
					$color_code = '1;31'; // Red, bold.
					break;
			}
			$label = "\033[" . $color_code . 'm' . $level . "\033[0m";
		}

		// Prepend the level to the message for easier searching.
		return sprintf( '%s: %s', $label, $message ) . PHP_EOL;
	}
}
