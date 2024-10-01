<?php
/**
 * Helper to consistently handle start and end for commands.
 *
 * @package NewspackCustomContentMigrator
 */

namespace Newspack\MigrationTools\Util;

use Newspack\MigrationTools\Log\CliLogger;

/**
 * BatchLogic helper to consistently handle start and end for commands.
 */
class BatchLogic {
	public static array $start = [
		'type'        => 'assoc',
		'name'        => 'start',
		'description' => 'Start row (default: 1)',
		'optional'    => true,
		'repeating'   => false,
	];

	public static array $end = [
		'type'        => 'assoc',
		'name'        => 'end',
		'description' => 'End row (default: PHP_INT_MAX)',
		'optional'    => true,
		'repeating'   => false,
	];

	public static array $num_items = [
		'type'        => 'assoc',
		'name'        => 'num-items',
		'description' => 'Number of items to process. Will be ignored if end is provided.',
		'optional'    => true,
		'repeating'   => false,
	];

	/**
	 * Args to use in a command.
	 *
	 * @var array
	 */
	private static array $batch_args;

	/**
	 * Get batch args.
	 *
	 * To use in a command, spread the returned value into the command's synopsis property.
	 *
	 * @return array[]
	 */
	public static function get_batch_args(): array {
		if ( empty( self::$batch_args ) ) {
			self::$batch_args = [ self::$start, self::$end, self::$num_items ];
		}
		return self::$batch_args;
	}

	/**
	 * Validate assoc args for batch and return start, end, and total.
	 *
	 * Note that end is exclusive, so --start=1 --end=2 will only process 1 item. Start is inclusive.
	 *
	 * @param array $assoc_args Assoc args from a command run.
	 *
	 * @return array Array keyed with: start, end, total.
	 */
	public static function validate_and_get_batch_args( array $assoc_args ): array {
		// Ensure that batch_args is initialized by calling get_batch_args().
		self::get_batch_args();

		$start     = $assoc_args[ self::$batch_args[0]['name'] ] ?? 1;
		$end       = $assoc_args[ self::$batch_args[1]['name'] ] ?? PHP_INT_MAX;
		$num_items = $assoc_args[ self::$batch_args[2]['name'] ] ?? false;

		if ( $start <= 0 ) {
			// We don't count from zero here, so if zero (or less) is passed, fix it by assuming 1.
			$start = 1;
		}

		if ( $num_items && PHP_INT_MAX === $end ) {
			$end = $start + $num_items;
		}

		if ( ! is_numeric( $start ) || ! is_numeric( $end ) ) {
			CliLogger::error( 'Start and end args must be numeric.', true );
		}
		if ( $end < $start ) {
			CliLogger::error( 'End arg must be greater than start arg.', true );
		}

		return [
			'start' => $start,
			'end'   => $end,
			'total' => $end - $start,
		];
	}
}
