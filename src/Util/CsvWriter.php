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
		// fputcsv escape='' for RFC 4180 compliance (@see https://www.php.net/manual/en/function.fputcsv.php).
		if ( false === fputcsv( $this->file_pointer, $row, ',', '"', '' ) ) { // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv.
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

	/**
	 * Closes the file pointer when the object is destroyed.
	 *
	 * Ensures the file handle is released even if close() was never called
	 * explicitly. Safe to call after close() — is_resource() returns false
	 * on an already-closed handle, so this is a no-op in that case.
	 *
	 * Any exception from close() is swallowed and re-emitted as a warning,
	 * because exceptions thrown from a destructor during shutdown become
	 * fatal errors that callers cannot catch.
	 */
	public function __destruct() {
		if ( ! is_resource( $this->file_pointer ) ) {
			return;
		}
		try {
			$this->close();
		} catch ( Exception $e ) {
			trigger_error( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
				esc_html( "CsvWriter::__destruct could not close {$this->filename}: " . $e->getMessage() ),
				E_USER_WARNING
			);
		}
	}
}
