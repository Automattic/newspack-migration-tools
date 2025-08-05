<?php
/**
 * Wrapper class for writing JSON files.
 *
 * @package Newspack\MigrationTools\Util
 */

namespace Newspack\MigrationTools\Util;

use Exception;

class JsonWriter {
	/**
	 * JSON file pointer.
	 * 
	 * @var resource
	 */
	private $file_pointer;

	/**
	 * A flag to indicate if the first item is being written.
	 * 
	 * @var bool
	 */
	private $is_first_item = true;

	/**
	 * Constructor.
	 * 
	 * @param string $filename The name of the JSON file to write to.
	 * @throws Exception If the file cannot be opened.
	 */
	public function __construct(
		private string $filename,
		private int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
	) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$this->file_pointer = fopen( getcwd() . '/' . $this->filename, 'a+' );

		if ( false === $this->file_pointer ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( "Could not open file: {$this->filename}" );
		}
	}

	/**
	 * Writes an object to the JSON file.
	 * 
	 * @param  array $item The object data to write.
	 * @return void
	 * @throws Exception If the object cannot be written to the file.
	 */
	public function put( array $item ): void {
		if ( $this->is_first_item ) {
            // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite
			fwrite( $this->file_pointer, "[\n" );
		} else {
            // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite
			fwrite( $this->file_pointer, ",\n" );
		}

		$json = wp_json_encode( $item, $this->flags );

        // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite
		fwrite( $this->file_pointer, "$json" );

		$this->is_first_item = false;
	}

	/**
	 * Closes the file pointer.
	 * 
	 * @return void
	 * @throws Exception If the file cannot be closed.
	 */
	public function close(): void {
        // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite
		fwrite( $this->file_pointer, "\n]\n" );

		if ( false === fclose( $this->file_pointer ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( "Could not close file: {$this->filename}" );
		}
	}

	/**
	 * Handles proper file ending when the object is destroyed.
	 */
	public function __destruct() {
		if ( is_resource( $this->file_pointer ) ) {
			$this->close();
		}
	}
}
