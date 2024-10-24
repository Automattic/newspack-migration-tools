<?php

namespace Newspack\MigrationTools\Util\Log;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

/**
 * Formatter for plain line logs with no formatting whatsoever.
 *
 * Everything but $message is ignored.
 */
class PlainLineFormatter implements FormatterInterface {

	public function format( LogRecord $record ): string {
		return $record['message'] . PHP_EOL;
	}

	public function formatBatch( array $records ): string {
		return array_reduce( $records, fn( $carry, $record ) => $carry . $this->format( $record ), '' );
	}
}
