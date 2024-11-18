<?php

namespace Newspack\MigrationTools\Tests\Scaffold;

use Newspack\MigrationTools\Scaffold\AbstractMigrationDataChest;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationDataChest;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationObject;
use Newspack\MigrationTools\Scaffold\MigrationObjectPropertyWrapper;
use WP_UnitTestCase;

/**
 * Class MigrationObjectTest.
 */
class MigrationObjectTest extends WP_UnitTestCase {

	/**
	 * Sets up the test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
	}

	/**
	 * Returns raw data for the Migration Object to use.
	 *
	 * @return array
	 */
	public function get_raw_data(): array {
		return [
			[
				'id'         => 2024,
				'title'      => 'Test Title',
				'content'    => 'Test Content',
				'author'     => 'Test Author',
				'categories' => [
					[
						'name'    => 'Test Category',
						'slug'    => 'test-category',
						'id'      => 1,
						'created' => [
							'created_at' => '2021-01-01 00:00:00',
							'created_by' => 'Test User',
						],
					],
				],
				'tags'       => [
					[
						[
							[
								[
									'name' => 'Test Tag',
								],
							],
						],
					],
					[
						[
							'name' => 'Test Tag 2',
						],
					],
				],
			],
		];
	}

	/**
	 * Returns a Migration Data Container.
	 *
	 * @return MigrationDataChest
	 */
	public function get_migration_data_container(): MigrationDataChest {
		$raw_data = $this->get_raw_data();

		return new class( $raw_data, 'id' ) extends AbstractMigrationDataChest {
			/**
			 * Data source type.
			 *
			 * @var string $source_type Describes the source for this data set. Default: query.
			 */
			protected string $source_type = 'array';

			/**
			 * Gets all migration objects.
			 *
			 * @return MigrationObject[]
			 */
			public function get_all(): array {
				return array_map(
					fn( $row ) => new \Newspack\MigrationTools\Scaffold\MigrationObject( $row, $this->get_pointer_to_identifier(), $this ),
					$this->get_raw_data()
				);
			}
		};
	}

	/**
	 * Test that dynamically accessing properties on the Migration Object returns a Property Wrapper.
	 * Also tests that the Property Wrapper can be used to access the value of the property.
	 * Also tests that the Property Wrapper can be used to access the path to the property.
	 *
	 * @return void
	 */
	public function test_can_get_property_wrapper(): void {
		$data_container = $this->get_migration_data_container();

		foreach ( $data_container->get_all() as $migration_object ) {
			$this->assertInstanceOf( MigrationObject::class, $migration_object );
			$this->assertInstanceOf( MigrationObjectPropertyWrapper::class, $migration_object->title );
			$this->assertInstanceOf( MigrationObjectPropertyWrapper::class, $migration_object->content );
			$this->assertInstanceOf( MigrationObjectPropertyWrapper::class, $migration_object->author );
			$this->assertInstanceOf( MigrationObjectPropertyWrapper::class, $migration_object->categories );
			$this->assertInstanceOf( MigrationObjectPropertyWrapper::class, $migration_object->categories[0] );
			$this->assertInstanceOf( MigrationObjectPropertyWrapper::class, $migration_object->categories[0]->name );
			$this->assertInstanceOf( MigrationObjectPropertyWrapper::class, $migration_object->categories[0]->created );
			$this->assertInstanceOf( MigrationObjectPropertyWrapper::class, $migration_object->tags[0][0][0][0]['name'] );
			$this->assertInstanceOf( MigrationObjectPropertyWrapper::class, $migration_object['author'] );
			$this->assertInstanceOf( MigrationObjectPropertyWrapper::class, $migration_object['categories'][0]['name'] );
			$this->assertNull( $migration_object->non_existent_property );
			$this->assertNull( $migration_object['non_existent_property'] );
			$this->assertNull( $migration_object->categories[0]->non_existent_property );
			$this->assertNull( $migration_object['categories'][0]['non_existent_property'] );

			$tag = $migration_object->tags[0][0];
			$this->assertEquals( 'Test Tag', $tag[0][0]['name']->get_value() );
			$this->assertEquals( 'tags.0.0.0.0.name', $tag[0][0]['name']->get_path() );
			$this->assertInstanceOf( MigrationObjectPropertyWrapper::class, $migration_object->tags[1][0] );
			$categories = $migration_object->categories[0];
			$this->assertEquals( 'Test Category', $categories->name->get_value() );
			$this->assertEquals( 'categories.0.created.created_at', $categories[0]->created->created_at->get_path() );
			$this->assertIsString( $categories->created->created_at->get_value() );
			$this->assertIsString( (string) $categories->created->created_at ); // This should call __toString() magic method.
			$category_created_by = $migration_object['categories'][0]['created']['created_by'];
			$this->assertInstanceOf( MigrationObjectPropertyWrapper::class, $category_created_by );
			$this->assertIsString( $category_created_by->get_value() );
			$this->assertEquals( 'categories.0.created.created_by', $category_created_by->get_path() );
			$this->assertIsInt( $migration_object->id->get_value() );
			$this->assertIsString( (string) $migration_object->id );
			$this->assertTrue( isset( $migration_object->categories ) );
			$this->assertTrue( isset( $migration_object['categories'] ) );
			$this->assertTrue( isset( $migration_object->categories[0] ) );
			$this->assertTrue( isset( $migration_object['categories'][0] ) );
			$this->assertEquals( '<mig_scaf>' . wp_json_encode( $this->get_raw_data()[0] ) . '</mig_scaf>', (string) $migration_object );
			$this->assertEquals( '<mig_scaf><property>' . wp_json_encode( $this->get_raw_data()[0]['categories'] ) . '</property></mig_scaf>', (string) $migration_object->categories );
		}
	}

	/**
	 * Test that the Migration Object is read-only by ensuring that a dynamic property cannot be set.
	 *
	 * @return void
	 */
	public function test_migration_object_is_not_writable(): void {
		$data_container = $this->get_migration_data_container();

		foreach ( $data_container->get_all() as $migration_object ) {
			$change_value = 'testing changing content';
			$this->expectException( \Exception::class );
			$migration_object->some_value = $change_value;
			$this->assertObjectNotHasProperty( 'some_value', $migration_object );
		}
	}

	/**
	 * Test that the Migration Object Property is read-only by ensuring that a dynamic property cannot be unset.
	 *
	 * @return void
	 */
	public function test_migration_object_property_cannot_be_unset(): void {
		$data_container = $this->get_migration_data_container();

		foreach ( $data_container->get_all() as $migration_object ) {
			$this->expectException( \Exception::class );
			unset( $migration_object['categories'] );
			$this->assertTrue( isset( $migration_object['categories'] ) );
		}
	}

	/**
	 * Test that the Migration Object Property Wrapper is read-only by ensuring that a dynamic property cannot be set.
	 *
	 * @return void
	 */
	public function test_migration_object_property_wrapper_is_not_writable(): void {
		$data_container = $this->get_migration_data_container();

		foreach ( $data_container->get_all() as $migration_object ) {
			$this->assertEquals( 'Test Content', $migration_object->content->get_value() );
			$change_value = 'testing changing content';
			$this->expectException( \Exception::class );
			$migration_object->content = $change_value;
			$this->assertNotEquals( $change_value, $migration_object->content->get_value() );
			$this->assertInstanceOf( MigrationObjectPropertyWrapper::class, $migration_object->content );
		}
	}

	/**
	 * Test that the Migration Object Property Wrapper is read-only by ensuring that a dynamic property cannot be unset.
	 *
	 * @return void
	 */
	public function test_migration_object_property_wrapper_cannot_by_unset(): void {
		$data_container = $this->get_migration_data_container();

		foreach ( $data_container->get_all() as $migration_object ) {
			$this->expectException( \Exception::class );
			unset( $migration_object['categories'][0] );
			$this->assertTrue( isset( $migration_object['categories'][0] ) );
		}
	}

	/**
	 * Test that nested Migration Object Property Wrapper is read-only by ensuring that a dynamic property cannot be set.
	 *
	 * @return void
	 */
	public function test_deep_migration_object_property_wrapper_is_read_only(): void {
		$data_container = $this->get_migration_data_container();

		foreach ( $data_container->get_all() as $migration_object ) {
			$change_value = 'testing changing content';
			$this->expectException( \Exception::class );
			$migration_object->categories[0]->name = $change_value;
			$this->assertNotEquals( $change_value, $migration_object->categories[0]->name->get_value() );
			$this->assertInstanceOf( MigrationObjectPropertyWrapper::class, $migration_object->categories[0]->name );
		}
	}
}
