<?php

namespace Newspack\MigrationTools\Util\Tests;

use PHPUnit\Framework\TestCase;
use Newspack\MigrationTools\Util\CsvIterator;

/**
 * Class CsvIteratorTest
 *
 * @package newspack-migration-tools
 */
class CsvIteratorTest extends TestCase {
	private string $test_file_with_headers;
	private string $test_file_without_headers;
	private string $test_file_empty;
	private string $test_file_nonexistent;
	private CsvIterator $csv_iterator;

	protected function setUp(): void {
		parent::setUp();

		$temp_dir        = sys_get_temp_dir();
		$random_dir_name = $temp_dir . '/' . uniqid( 'csv_test_' );

		if ( ! is_dir( $random_dir_name ) ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_mkdir -- Test setup
			mkdir( $random_dir_name, 0755, true );
		}

		$this->test_file_with_headers    = $random_dir_name . '/test-with-headers.csv';
		$this->test_file_without_headers = $random_dir_name . '/test-without-headers.csv';
		$this->test_file_empty           = $random_dir_name . '/test-empty.csv';
		$this->test_file_nonexistent     = $random_dir_name . '/nonexistent.csv';

		// Create test CSV file with headers
		$content_with_headers = "name,email,role\nJohn Doe,john@example.com,author\nJane Smith,jane@example.com,editor\n";
		file_put_contents( $this->test_file_with_headers, $content_with_headers );

		// Create test CSV file without headers
		$content_without_headers = "value1,value2,value3\ndata1,data2,data3\nrow1,row2,row3\n";
		file_put_contents( $this->test_file_without_headers, $content_without_headers );

		// Create empty test file
		file_put_contents( $this->test_file_empty, '' );

		$this->csv_iterator = new CsvIterator();
	}

	protected function tearDown(): void {
		// Clean up test files
		$files_to_clean = [
			$this->test_file_with_headers,
			$this->test_file_without_headers,
			$this->test_file_empty,
		];

		foreach ( $files_to_clean as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}

		// Clean up directory
		$dir = dirname( $this->test_file_with_headers );
		if ( is_dir( $dir ) ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir -- Test cleanup
			rmdir( $dir );
		}
	}

	/**
	 * Test items_without_headers method with valid CSV file
	 */
	public function test_items_without_headers_with_valid_file(): void {
		$items = iterator_to_array( $this->csv_iterator->items_without_headers( $this->test_file_without_headers, ',' ) );

		$this->assertCount( 3, $items );
		$this->assertEquals( [ 'value1', 'value2', 'value3' ], $items[0] );
		$this->assertEquals( [ 'data1', 'data2', 'data3' ], $items[1] );
		$this->assertEquals( [ 'row1', 'row2', 'row3' ], $items[2] );
	}

	/**
	 * Test items_without_headers method with different separators
	 */
	public function test_items_without_headers_with_different_separators(): void {
		// Create test file with semicolon separator
		$semicolon_content = "value1;value2;value3\ndata1;data2;data3\n";
		$semicolon_file    = dirname( $this->test_file_without_headers ) . '/test-semicolon.csv';
		file_put_contents( $semicolon_file, $semicolon_content );

		$items = iterator_to_array( $this->csv_iterator->items_without_headers( $semicolon_file, ';' ) );

		$this->assertCount( 2, $items );
		$this->assertEquals( [ 'value1', 'value2', 'value3' ], $items[0] );
		$this->assertEquals( [ 'data1', 'data2', 'data3' ], $items[1] );

		// Clean up
		unlink( $semicolon_file );
	}

	/**
	 * Test items_without_headers method with empty file
	 */
	public function test_items_without_headers_with_empty_file(): void {
		$items = iterator_to_array( $this->csv_iterator->items_without_headers( $this->test_file_empty, ',' ) );

		$this->assertCount( 0, $items );
	}

	/**
	 * Test items_without_headers method trims whitespace
	 */
	public function test_items_without_headers_trims_whitespace(): void {
		// Create test file with whitespace
		$whitespace_content = " value1 , value2 , value3 \n data1 , data2 , data3 \n";
		$whitespace_file    = dirname( $this->test_file_without_headers ) . '/test-whitespace.csv';
		file_put_contents( $whitespace_file, $whitespace_content );

		$items = iterator_to_array( $this->csv_iterator->items_without_headers( $whitespace_file, ',' ) );

		$this->assertCount( 2, $items );
		$this->assertEquals( [ 'value1', 'value2', 'value3' ], $items[0] );
		$this->assertEquals( [ 'data1', 'data2', 'data3' ], $items[1] );

		// Clean up
		unlink( $whitespace_file );
	}

	/**
	 * Test items_without_headers method with single row
	 */
	public function test_items_without_headers_with_single_row(): void {
		// Create test file with single row
		$single_row_content = "value1,value2,value3\n";
		$single_row_file    = dirname( $this->test_file_without_headers ) . '/test-single-row.csv';
		file_put_contents( $single_row_file, $single_row_content );

		$items = iterator_to_array( $this->csv_iterator->items_without_headers( $single_row_file, ',' ) );

		$this->assertCount( 1, $items );
		$this->assertEquals( [ 'value1', 'value2', 'value3' ], $items[0] );

		// Clean up
		unlink( $single_row_file );
	}

	/**
	 * Test items_without_headers method with empty rows (should ignore empty rows)
	 */
	public function test_items_without_headers_with_empty_rows(): void {
		// Create test file with empty rows
		$empty_rows_content = "value1,value2,value3\n\n\n";
		$empty_rows_file    = dirname( $this->test_file_without_headers ) . '/test-empty-rows.csv';
		file_put_contents( $empty_rows_file, $empty_rows_content );

		$items = iterator_to_array( $this->csv_iterator->items_without_headers( $empty_rows_file, ',' ) );

		// Should only return the non-empty row, ignoring blank lines
		$this->assertCount( 1, $items );
		$this->assertEquals( [ 'value1', 'value2', 'value3' ], $items[0] );

		// Clean up
		unlink( $empty_rows_file );
	}

	/**
	 * Test items_without_headers method returns iterable
	 */
	public function test_items_without_headers_returns_iterable(): void {
		$items = $this->csv_iterator->items_without_headers( $this->test_file_without_headers, ',' );

		$this->assertIsIterable( $items );
	}

	/**
	 * Test items_without_headers method with file that has headers (should not skip first row)
	 */
	public function test_items_without_headers_with_file_that_has_headers(): void {
		$items = iterator_to_array( $this->csv_iterator->items_without_headers( $this->test_file_with_headers, ',' ) );

		$this->assertCount( 3, $items );
		// First row should be treated as data, not skipped
		$this->assertEquals( [ 'name', 'email', 'role' ], $items[0] );
		$this->assertEquals( [ 'John Doe', 'john@example.com', 'author' ], $items[1] );
		$this->assertEquals( [ 'Jane Smith', 'jane@example.com', 'editor' ], $items[2] );
	}

	/**
	 * Test items method (existing functionality) for comparison
	 */
	public function test_items_with_headers(): void {
		$items = iterator_to_array( $this->csv_iterator->items( $this->test_file_with_headers, ',' ) );

		$this->assertCount( 2, $items ); // Should skip header row
		$this->assertEquals(
			[
				'name'  => 'John Doe',
				'email' => 'john@example.com',
				'role'  => 'author',
			],
			$items[0]
		);
		$this->assertEquals(
			[
				'name'  => 'Jane Smith',
				'email' => 'jane@example.com',
				'role'  => 'editor',
			],
			$items[1]
		);
	}

	/**
	 * Test that items_without_headers and items methods handle the same file differently
	 */
	public function test_items_without_headers_vs_items_methods(): void {
		$items_without_headers = iterator_to_array( $this->csv_iterator->items_without_headers( $this->test_file_with_headers, ',' ) );
		$items_with_headers    = iterator_to_array( $this->csv_iterator->items( $this->test_file_with_headers, ',' ) );

		// items_without_headers should have 3 rows (including header as data)
		$this->assertCount( 3, $items_without_headers );

		// items should have 2 rows (excluding header)
		$this->assertCount( 2, $items_with_headers );

		// First row in items_without_headers should be the header row
		$this->assertEquals( [ 'name', 'email', 'role' ], $items_without_headers[0] );

		// First row in items should be the first data row
		$this->assertEquals(
			[
				'name'  => 'John Doe',
				'email' => 'john@example.com',
				'role'  => 'author',
			],
			$items_with_headers[0]
		);
	}
}
