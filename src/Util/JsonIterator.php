<?php
/**
 * Wrapper class for json file iteration using JsonMachine.
 *
 * @package NewspackCustomContentMigrator
 */

namespace Newspack\MigrationTools\Util;
 
use Exception;
use JsonMachine\Items;
use Newspack\MigrationTools\Log\FileLogger;

/**
 * Class JsonIterator.
 *
 * Helpers to iterate over JSON files using less memory.
 */
class JsonIterator {

	/**
	 * File logger instance.
	 *
	 * @var FileLogger.
	 */
	private FileLogger $file_logger;

	/**
	 * Log file name.
	 *
	 * @var string Log name.
	 */
	const LOG_NAME = 'json_iterator.log';

	/**
	 * Constructor.
	 *
	 * @param FileLogger|null $file_logger Optional File logger instance.
	 */
	public function __construct( FileLogger $file_logger = null ) {
		if ( ! $file_logger ) {
			$file_logger = new FileLogger();
		}

		$this->file_logger = $file_logger;
	}

	/**
	 * A low-tech way to do a "file_exists" on a url.
	 *
	 * @param string $url The url to check.
	 *
	 * @return bool True if the url responds with a 200 OK.
	 */
	private function url_responds( string $url ): bool {
		$req = wp_remote_head( $url );

		return ! empty( $req['response']['code'] ) && 200 === $req['response']['code'];
	}

	/**
	 * Iterate over json data in batches.
	 *
	 * The start and end args to get items between start number and end number in the array of data in the json file.
	 *
	 * @param string $json_file Path to the json file.
	 * @param int    $start Start number (inclusive) in the array of data in the json file.
	 * @param int    $end End number (exclusive) in the array of data in the json file.
	 * @param array  $options Optional. See items() in this class.
	 *
	 * @return iterable
	 */
	public function batched_items( string $json_file, int $start, int $end, array $options = [] ): iterable {
		$item_no = 0;
		foreach ( $this->items( $json_file, $options ) as $item ) {
			$item_no++;
			if ( 0 !== $start && $item_no < $start ) {
				// Keep looping until we get to where we want to be in the file.
				continue;
			}

			if ( $item_no < $end ) {
				yield $item;
			} else {
				break;
			}
		}
	}

	/**
	 * Will read a JSON file in chunks and return an iterable of objects from the JSON.
	 *
	 * If you want only parts of the json, or the structure is not straightforward,
	 * you can pass JSON pointers in the $options array. See this for more info:
	 * https://github.com/halaxa/json-machine#json-pointer
	 *
	 * @param string $json_file Path to the JSON file – can be a URL too.
	 * @param array  $options Options to pass to JsonMachine.
	 *
	 * @return iterable
	 */
	public function items( string $json_file, array $options = [] ): iterable {
		$file_exists = str_starts_with( $json_file, 'http' ) ? $this->url_responds( $json_file ) : file_exists( $json_file );

		if ( ! $file_exists ) {
			$this->file_logger::log( self::LOG_NAME, "Doesn't exist: {$json_file}", FileLogger::ERROR, true );

			return new \EmptyIterator();
		}

		try {
			return Items::fromFile( $json_file, $options );
		} catch ( Exception $o_0 ) {
			$this->file_logger::log( self::LOG_NAME, "Could not read the JSON from {$json_file}", FileLogger::ERROR, true );

			return new \EmptyIterator();
		}
	}

	/**
	 * Will count number of entries in a JSON file where the root is an array.
	 *
	 * Handy for getting a "total" number for progress bars and such.
	 *
	 * @param string $json_file_path Path to the JSON file.
	 *
	 * @return int Number of entries in the array in the JSON file.
	 *
	 * @throws Exception If the jq command fails or the JSON file does not exist.
	 */
	public function count_json_array_entries( string $json_file_path ): int {
		if ( file_exists( $json_file_path ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
			exec( 'cat ' . escapeshellarg( $json_file_path ) . " | jq 'length'", $count );
			if ( ! empty( $count[0] ) ) {
				return (int) $count[0];
			}
		}

		throw new Exception( sprintf( 'Could not count entries in JSON file: %s', $json_file_path ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * Will validate and get batch args for a JSON file.
	 *
	 * @param string $json_path Path to JSON file.
	 * @param array  $assoc_args Args from WP CLI command.
	 *
	 * @return array
	 * @throws Exception If the args were not acceptable or the json file not countable.
	 */
	public function validate_and_get_batch_args_for_json_file( string $json_path, array $assoc_args ): array {
		$batch_args = BatchLogic::validate_and_get_batch_args( $assoc_args );

		if ( PHP_INT_MAX === $batch_args['end'] ) {
			$batch_args['total'] = $this->count_json_array_entries( $json_path );
			if ( 1 !== $batch_args['start'] ) {
				$batch_args['total'] = $batch_args['total'] - $batch_args['start'];
			}
		}

		return $batch_args;
	}
}
