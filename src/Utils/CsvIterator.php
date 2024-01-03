<?php
/**
 * Wrapper class for csv file iteration using BatchLogic.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Utils;

use Exception;
use WP_CLI;

class CsvIterator {

	/**
	 * @param string $csv_path Path to CSV file.
	 * @param string $separator Separator for CSV file.
	 *
	 * @return iterable
	 * @throws Exception
	 */
	public function items( string $csv_path, string $separator ): iterable {
		if ( ! is_readable( $csv_path ) ) {
			WP_CLI::error( "Could not read CSV file: $csv_path" );

			return [];
		}

		$csv_file    = fopen( $csv_path, 'r' );
		$csv_headers = [];
		$line_number = 0;
		while ( false !== ( $line = fgetcsv( $csv_file, null, $separator ) ) ) {
			++ $line_number;
			if ( $line_number === 1 ) {
				$csv_headers = array_map( 'trim', $line );
				continue;
			}
			yield array_combine( $csv_headers, array_map( 'trim', $line ) );
		}
		fclose( $csv_file );
	}

	/**
	 * @param string $csv_file Path to CSV file.
	 * @param string $separator Separator for CSV file.
	 * @param int    $start Start number (inclusive) line in the file.
	 * @param int    $end End number (exclusive) line in the file.
	 *
	 * @return iterable
	 * @throws Exception
	 */
	public function batched_items( string $csv_file, string $separator, int $start, int $end ): iterable {
		$item_no = 0;
		foreach ( $this->items( $csv_file, $separator ) as $item ) {
			$item_no ++;
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
	 * Will count number of entries in a CSV file.
	 *
	 * Handy for getting a "total" number for progress bars and such.
	 *
	 * @param string $csv_file_path Path to the CSV file.
	 *
	 * @return int Number of entries in the array in the CSV file.
	 *
	 * @throws Exception
	 */
	public function count_csv_file_entries( string $csv_file_path, string $separator ): int {
		return count( [ ... $this->items( $csv_file_path, $separator ) ] );
	}

	/**
	 * Will validate and get batch args for a CSV file.
	 *
	 * @param string $csv_path Path to CSV file.
	 * @param array  $assoc_args Args from WP CLI command.
	 * @param string $separator Separator for CSV file.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function validate_and_get_batch_args_for_file( string $csv_path, array $assoc_args, string $separator ): array {
		$batch_args = BatchLogic::validate_and_get_batch_args( $assoc_args );

		if ( PHP_INT_MAX === $batch_args['end'] ) {
			$batch_args['total'] = $this->count_csv_file_entries( $csv_path, $separator );
			if ( 1 !== $batch_args['start'] ) {
				$batch_args['total'] = $batch_args['total'] - $batch_args['start'];
			}
		}

		return $batch_args;
	}
}
