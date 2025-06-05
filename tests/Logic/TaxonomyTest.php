<?php
/**
 * Tests for the Taxonomy class.
 */

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\MigrationTools\Logic\Taxonomy;
use WP_UnitTestCase;
use WP_Error;

/**
 * Test the Taxonomy class.
 */
class TaxonomyTest extends WP_UnitTestCase {
	/**
	 * The Taxonomy instance.
	 *
	 * @var Taxonomy
	 */
	private $taxonomy;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();
		$this->taxonomy = new Taxonomy();
	}

	/**
	 * Test creating a new category with just a name.
	 */
	public function test_create_category_with_name_only() {
		$result = $this->taxonomy->get_or_create_category(
			[
				'cat_name' => 'Test Category',
			]
		);

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		$term = get_term( $result, 'category' );
		$this->assertEquals( 'Test Category', $term->name );
		$this->assertEquals( 0, $term->parent );
		$this->assertEquals( '', $term->description );
	}

	/**
	 * Test creating a new category with all optional fields.
	 */
	public function test_create_category_with_all_fields() {
		$result = $this->taxonomy->get_or_create_category(
			[
				'cat_name'             => 'Test Category Full',
				'category_parent'      => 0,
				'category_description' => 'Test Description',
				'category_nicename'    => 'test-category-full',
			]
		);

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		$term = get_term( $result, 'category' );
		$this->assertEquals( 'Test Category Full', $term->name );
		$this->assertEquals( 0, $term->parent );
		$this->assertEquals( 'Test Description', $term->description );
		$this->assertEquals( 'test-category-full', $term->slug );
	}

	/**
	 * Test getting an existing category.
	 */
	public function test_get_existing_category() {
		// First create a category
		$first_result = $this->taxonomy->get_or_create_category(
			[
				'cat_name' => 'Existing Category',
			]
		);

		// Try to create the same category again
		$second_result = $this->taxonomy->get_or_create_category(
			[
				'cat_name' => 'Existing Category',
			]
		);

		$this->assertEquals( $first_result, $second_result );
	}

	/**
	 * Test creating a category with a parent.
	 */
	public function test_create_category_with_parent() {
		// First create a parent category
		$parent_id = $this->taxonomy->get_or_create_category(
			[
				'cat_name' => 'Parent Category',
			]
		);

		// Create a child category
		$child_result = $this->taxonomy->get_or_create_category(
			[
				'cat_name'        => 'Child Category',
				'category_parent' => $parent_id,
			]
		);

		$this->assertIsInt( $child_result );
		$this->assertGreaterThan( 0, $child_result );

		$term = get_term( $child_result, 'category' );
		$this->assertEquals( 'Child Category', $term->name );
		$this->assertEquals( $parent_id, $term->parent );
	}

	/**
	 * Test error when cat_name is missing.
	 */
	public function test_error_missing_cat_name() {
		$result = $this->taxonomy->get_or_create_category( [] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'missing_cat_name', $result->get_error_code() );
	}

	/**
	 * Test error when cat_name is empty.
	 */
	public function test_error_empty_cat_name() {
		$result = $this->taxonomy->get_or_create_category(
			[
				'cat_name' => '',
			]
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'missing_cat_name', $result->get_error_code() );
	}

	/**
	 * Test error when parent category doesn't exist.
	 */
	public function test_error_invalid_parent_category() {
		$result = $this->taxonomy->get_or_create_category(
			[
				'cat_name'        => 'Test Category',
				'category_parent' => 999999, // Non-existent category ID
			]
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'wrong_parent_category_term_id', $result->get_error_code() );
	}

	/**
	 * Test creating a new tag with just a name.
	 */
	public function test_create_tag_with_name_only() {
		$result = $this->taxonomy->get_or_create_tag(
			[
				'name' => 'Test Tag',
			]
		);

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		$term = get_term( $result, 'post_tag' );
		$this->assertEquals( 'Test Tag', $term->name );
		$this->assertEquals( '', $term->description );
	}

	/**
	 * Test creating a new tag with all optional fields.
	 */
	public function test_create_tag_with_all_fields() {
		$result = $this->taxonomy->get_or_create_tag(
			[
				'name'        => 'Test Tag Full',
				'description' => 'Test Description',
				'slug'        => 'test-tag-full',
			]
		);

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		$term = get_term( $result, 'post_tag' );
		$this->assertEquals( 'Test Tag Full', $term->name );
		$this->assertEquals( 'Test Description', $term->description );
		$this->assertEquals( 'test-tag-full', $term->slug );
	}

	/**
	 * Test getting an existing tag.
	 */
	public function test_get_existing_tag() {
		// First create a tag
		$first_result = $this->taxonomy->get_or_create_tag(
			[
				'name' => 'Existing Tag',
			]
		);

		// Try to create the same tag again
		$second_result = $this->taxonomy->get_or_create_tag(
			[
				'name' => 'Existing Tag',
			]
		);

		$this->assertEquals( $first_result, $second_result );
	}

	/**
	 * Test error when tag name is missing.
	 */
	public function test_error_missing_tag_name() {
		$result = $this->taxonomy->get_or_create_tag( [] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'missing_tag_name', $result->get_error_code() );
	}

	/**
	 * Test error when tag name is empty.
	 */
	public function test_error_empty_tag_name() {
		$result = $this->taxonomy->get_or_create_tag(
			[
				'name' => '',
			]
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'missing_tag_name', $result->get_error_code() );
	}

	/**
	 * Test creating a tag with a duplicate slug.
	 */
	public function test_create_tag_with_duplicate_slug() {
		// First create a tag with a specific slug
		$first_result = $this->taxonomy->get_or_create_tag(
			[
				'name' => 'First Tag',
				'slug' => 'test-slug',
			]
		);

		// Try to create another tag with the same slug
		$second_result = $this->taxonomy->get_or_create_tag(
			[
				'name' => 'Second Tag',
				'slug' => 'test-slug',
			]
		);

		// WordPress should automatically append a number to make the slug unique
		$this->assertIsInt( $second_result );
		$this->assertGreaterThan( 0, $second_result );
		$this->assertNotEquals( $first_result, $second_result );

		$term = get_term( $second_result, 'post_tag' );
		$this->assertEquals( 'Second Tag', $term->name );
		$this->assertStringStartsWith( 'test-slug', $term->slug );
	}
}
