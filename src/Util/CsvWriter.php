<?php
/**
 * Wrapper class for writing CSV files.
 *
 * @package Newspack\MigrationTools\Util
 */

namespace Newspack\MigrationTools\Util;

use Exception;

class CsvWriter {
	/**
	 * CSV file pointer.
	 *
	 * @var resource
	 */
	private $file_pointer;

	/**
	 * Constructor.
	 *
	 * @param string $filename The name of the CSV file to write to.
	 * @throws Exception If the file cannot be opened.
	 */
	public function __construct(
		private string $filename
	) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$this->file_pointer = fopen( $this->filename, 'a+' );

		if ( false === $this->file_pointer ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( "Could not open file: {$this->filename}" );
		}
	}

	/**
	 * Sets the Header columns for the CSV file.
	 *
	 * @param  array $header The header columns to write.
	 * @return void
	 */
	public function set_header( array $header ): void {
		// Check if the file is empty before writing the header.
		if ( fstat( $this->file_pointer )['size'] > 0 ) {
			return;
		}

		$this->put( $header );
	}

	/**
	 * Writes a row to the CSV file.
	 *
	 * @param  array $row The row data to write.
	 * @return void
	 * @throws Exception If the row cannot be written to the file.
	 */
	public function put( array $row ): void {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
		if ( false === fputcsv( $this->file_pointer, $row ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( "Could not write to file: {$this->filename}" );
		}
	}

	/**
	 * Closes the file pointer.
	 *
	 * @return void
	 * @throws Exception If the file cannot be closed.
	 */
	public function close(): void {
		if ( false === fclose( $this->file_pointer ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( "Could not close file: {$this->filename}" );
		}
	}
}
