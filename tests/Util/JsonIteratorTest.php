<?php

namespace Newspack\MigrationTools\Tests\Util;

use Newspack\MigrationTools\Util\JsonIterator;
use WP_UnitTestCase;

class JsonIteratorTest extends WP_UnitTestCase {

	public static string $trees_json_file_path = 'tests/fixtures/trees.json';
	public static int $trees_json_length       = 5;

	/**
	 * Test that the batch args are correctly calculated.
	 *
	 * @return void
	 */
	public function test_batch_args(): void {

		$iterator_mock = $this->get_mocked_iterator( self::$trees_json_length );
		$batch_args    = $iterator_mock->validate_and_get_batch_args_for_json_file( self::$trees_json_file_path, [] );
		$this->assertEquals( 1, $batch_args['start'] );
		$this->assertEquals( PHP_INT_MAX, $batch_args['end'] );
		$this->assertEquals( self::$trees_json_length, $batch_args['total'] );

		$batch_args = $iterator_mock->validate_and_get_batch_args_for_json_file(
			self::$trees_json_file_path,
			[
				'start'     => 2,
				'num-items' => 3,
			]
		);
		$this->assertEquals( 2, $batch_args['start'] );
		$this->assertEquals( self::$trees_json_length, $batch_args['end'] );
		$this->assertEquals( 3, $batch_args['total'] );

		$remote_file   = 'https://jsonplaceholder.typicode.com/users'; // This has exactly 100 entries.
		$iterator_mock = $this->get_mocked_iterator( 100 );
		$batch_args    = $iterator_mock->validate_and_get_batch_args_for_json_file(
			$remote_file,
			[
				'start'     => 10,
				'num-items' => 42,
			]
		);
		$this->assertEquals( 10, $batch_args['start'] );
		$this->assertEquals( 52, $batch_args['end'] );
		$this->assertEquals( 42, $batch_args['total'] );
	}

	/**
	 * Test only passing how many items to process.
	 *
	 * @return void
	 */
	public function test_loop_from_start(): void {
		$iterator_mock = $this->get_mocked_iterator( self::$trees_json_length );
		$batch_args    = $iterator_mock->validate_and_get_batch_args_for_json_file( self::$trees_json_file_path, [ 'num-items' => 3 ] );

		$row_count = 0;
		foreach ( $iterator_mock->batched_items( self::$trees_json_file_path, $batch_args['start'], $batch_args['end'] ) as $row ) {
			$this->assertEquals( ++$row_count, $row->id );
			$this->assertTrue( in_array( $row->id, [ 1, 2, 3 ] ) );
		}
	}

	/**
	 * Test starting from the second item.
	 *
	 * @return void
	 */
	public function test_loop_from_2(): void {
		$iterator_mock = $this->get_mocked_iterator( self::$trees_json_length );
		$batch_args    = $iterator_mock->validate_and_get_batch_args_for_json_file( self::$trees_json_file_path, [ 'start' => 2 ] );

		$row_count = 1;
		foreach ( $iterator_mock->batched_items( self::$trees_json_file_path, $batch_args['start'], $batch_args['end'] ) as $row ) {
			$this->assertEquals( ++$row_count, $row->id );
			$this->assertTrue( in_array( $row->id, [ 2, 3, 4, 5 ] ) );
		}
	}

	/**
	 * Test starting from 2 and ending at 4.
	 *
	 * @return void
	 */
	public function test_loop_just_2(): void {
		$iterator_mock = $this->get_mocked_iterator( self::$trees_json_length );
		$batch_args    = $iterator_mock->validate_and_get_batch_args_for_json_file(
			self::$trees_json_file_path,
			[
				'start' => 2,
				'end'   => 4,
			]
		);

		$row_count = 1;
		foreach ( $iterator_mock->batched_items( self::$trees_json_file_path, $batch_args['start'], $batch_args['end'] ) as $row ) {
			$this->assertEquals( ++$row_count, $row->id );
			$this->assertTrue( in_array( $row->id, [ 2, 3 ] ) );
		}
	}

	/**
	 * Test filtering items by key existence when value is null.
	 *
	 * @return void
	 */
	public function test_filtered_items_by_key_existence(): void {
		$iterator = new JsonIterator();

		// Test filtering by 'fruit' key existence (should return all items that have a fruit property)
		$items_with_fruit = iterator_to_array( $iterator->filtered_items( self::$trees_json_file_path, 'fruit', null ) );

		// Should return 3 items (Apple Tree, Orange Tree, Mango Tree) that have fruit values
		$this->assertCount( 3, $items_with_fruit );
		$this->assertEquals( 'Apple Tree', $items_with_fruit[0]->tree_name );
		$this->assertEquals( 'Orange Tree', $items_with_fruit[1]->tree_name );
		$this->assertEquals( 'Mango Tree', $items_with_fruit[2]->tree_name );

		// Test filtering by 'metadata' key existence (should return all items)
		$items_with_metadata = iterator_to_array( $iterator->filtered_items( self::$trees_json_file_path, 'metadata', null ) );
		$this->assertCount( 5, $items_with_metadata );

		// Test filtering by non-existent key (should return empty array)
		$items_with_nonexistent = iterator_to_array( $iterator->filtered_items( self::$trees_json_file_path, 'nonexistent_key', null ) );
		$this->assertCount( 0, $items_with_nonexistent );
	}

	/**
	 * Test filtering items by key and specific value.
	 *
	 * @return void
	 */
	public function test_filtered_items_by_key_and_value(): void {
		$iterator = new JsonIterator();

		// Test filtering by 'type' with value 'fruit'
		$fruit_trees = iterator_to_array( $iterator->filtered_items( self::$trees_json_file_path, 'type', 'fruit' ) );
		$this->assertCount( 3, $fruit_trees );
		$this->assertEquals( 'Apple Tree', $fruit_trees[0]->tree_name );
		$this->assertEquals( 'Orange Tree', $fruit_trees[1]->tree_name );
		$this->assertEquals( 'Mango Tree', $fruit_trees[2]->tree_name );

		// Test filtering by 'type' with value 'non-fruit'
		$non_fruit_trees = iterator_to_array( $iterator->filtered_items( self::$trees_json_file_path, 'type', 'non-fruit' ) );
		$this->assertCount( 2, $non_fruit_trees );
		$this->assertEquals( 'Maple Tree', $non_fruit_trees[0]->tree_name );
		$this->assertEquals( 'Pine Tree', $non_fruit_trees[1]->tree_name );

		// Test filtering by 'fruit' with value 'apple'
		$apple_trees = iterator_to_array( $iterator->filtered_items( self::$trees_json_file_path, 'fruit', 'apple' ) );
		$this->assertCount( 1, $apple_trees );
		$this->assertEquals( 'Apple Tree', $apple_trees[0]->tree_name );

		// Test filtering by non-existent value (should return empty array)
		$nonexistent_value = iterator_to_array( $iterator->filtered_items( self::$trees_json_file_path, 'type', 'nonexistent' ) );
		$this->assertCount( 0, $nonexistent_value );
	}

	/**
	 * Mock the JsonIterator class for methods we can't use in unittests.
	 *
	 * @param int $num_entries Number of entries to mock.
	 * @return JsonIterator|MockObject
	 */
	private function get_mocked_iterator( int $num_entries ) {
		$iterator_mock = $this->getMockBuilder( JsonIterator::class )
								->onlyMethods( [ 'count_json_array_entries' ] )
								->getMock();

		$iterator_mock->method( 'count_json_array_entries' ) // Mock count_json_array_entries because it uses exec and jq
						->willReturn( $num_entries ); // There are 5 entries in the trees.json file.

		return $iterator_mock;
	}
}
