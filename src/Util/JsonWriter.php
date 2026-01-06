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
	 * A flag to indicate if we're appending to an existing file and need to remove the closing bracket.
	 * 
	 * @var bool
	 */
	private $should_truncate_closing_bracket = false;

	/**
	 * Constructor.
	 * 
	 * @param string $filename The name of the JSON file to write to.
	 * @throws Exception If the file cannot be opened or contains invalid JSON.
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

		// Check if file has existing content and validate JSON
		fseek( $this->file_pointer, 0, SEEK_END );
		$file_size = ftell( $this->file_pointer );
		
		if ( $file_size > 0 ) {
			rewind( $this->file_pointer );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			$content = fread( $this->file_pointer, $file_size );
			
			if ( null === json_decode( $content ) && JSON_ERROR_NONE !== json_last_error() ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new Exception( "File contains invalid JSON: {$this->filename}" );
			}
			
			$this->is_first_item                   = false;
			$this->should_truncate_closing_bracket = true;
			fseek( $this->file_pointer, 0, SEEK_END );
		}
	}

	/**
	 * Writes an object to the JSON file.
	 * 
	 * Handles three scenarios:
	 * 1. First item in a new file: writes opening bracket and item
	 * 2. First item when appending to existing file: removes closing bracket, adds comma, writes item
	 * 3. Subsequent items: adds comma and writes item
	 * 
	 * @param  array $item The object data to write.
	 * @return void
	 * @throws Exception If the object cannot be written to the file.
	 */
	public function put( array $item ): void {
		if ( $this->is_first_item ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite
			fwrite( $this->file_pointer, "[\n" );
		} elseif ( $this->should_truncate_closing_bracket ) {
			// Remove the closing bracket before appending to existing file
			$current_position = ftell( $this->file_pointer );
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_ftruncate
			ftruncate( $this->file_pointer, max( 0, $current_position - 2 ) );
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite
			fwrite( $this->file_pointer, ",\n" );
			$this->should_truncate_closing_bracket = false;
		} else {
			// Subsequent items in same session
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite
			fwrite( $this->file_pointer, ",\n" );
		}

		$json = wp_json_encode( $item, $this->flags );

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite
		fwrite( $this->file_pointer, "$json" );

		$this->is_first_item = false;
	}

	/**
	 * Closes the file pointer and finalizes the JSON array.
	 * 
	 * Writes the closing bracket and newline to complete the JSON array structure,
	 * then closes the file pointer. This method is called automatically via __destruct().
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
