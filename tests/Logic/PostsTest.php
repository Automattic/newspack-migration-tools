<?php
/**
 * Tests for the Posts class.
 */

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\MigrationTools\Logic\Posts;
use WP_UnitTestCase;
use WP_Error;

/**
 * Test the Posts class.
 */
class PostsTest extends WP_UnitTestCase {
	/**
	 * The Posts instance.
	 *
	 * @var Posts
	 */
	private $posts;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();
		$this->posts = new Posts();
	}

	/**
	 * Test creating a new post with valid data.
	 */
	public function test_create_new_post() {
		$post_data         = [
			'post_title'   => 'Test Post',
			'post_content' => 'Test content',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		];
		$unique_identifier = 'test-post-123';

		$result = Posts::create_or_get_post( $post_data, $unique_identifier );

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		$post = get_post( $result );
		$this->assertEquals( 'Test Post', $post->post_title );
		$this->assertEquals( 'Test content', $post->post_content );
		$this->assertEquals( 'publish', $post->post_status );
		$this->assertEquals( 'post', $post->post_type );

		// Check that the unique identifier meta was set
		$meta_value = get_post_meta( $result, Posts::UNIQUE_POST_IDENTIFIER_META_KEY, true );
		$this->assertEquals( $unique_identifier, $meta_value );
	}

	/**
	 * Test getting an existing post by unique identifier.
	 */
	public function test_get_existing_post() {
		$post_data         = [
			'post_title'   => 'Existing Post',
			'post_content' => 'Existing content',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		];
		$unique_identifier = 'existing-post-456';

		// Create the post first
		$first_result = Posts::create_or_get_post( $post_data, $unique_identifier );
		$this->assertIsInt( $first_result );

		// Try to create/get the same post again
		$second_result = Posts::create_or_get_post( $post_data, $unique_identifier );

		$this->assertEquals( $first_result, $second_result );

		// Verify the post wasn't duplicated
		$post_count = get_posts(
			[
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			]
		);
		$this->assertCount( 1, $post_count );
	}

	/**
	 * Test error when unique identifier is empty.
	 */
	public function test_error_empty_unique_identifier() {
		$post_data = [
			'post_title'   => 'Test Post',
			'post_content' => 'Test content',
			'post_status'  => 'publish',
		];

		$result = Posts::create_or_get_post( $post_data, '' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'empty_unique_identifier', $result->get_error_code() );
		$this->assertEquals( 'The unique identifier cannot be empty.', $result->get_error_message() );
	}

	/**
	 * Test creating a post with minimal required data.
	 */
	public function test_create_post_with_minimal_data() {
		$post_data         = [
			'post_title' => 'Minimal Post',
		];
		$unique_identifier = 'minimal-post-789';

		$result = Posts::create_or_get_post( $post_data, $unique_identifier );

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		$post = get_post( $result );
		$this->assertEquals( 'Minimal Post', $post->post_title );
		$this->assertEquals( 'draft', $post->post_status ); // Default status
		$this->assertEquals( 'post', $post->post_type ); // Default type
	}

	/**
	 * Test creating a page instead of a post.
	 */
	public function test_create_page() {
		$post_data         = [
			'post_title'   => 'Test Page',
			'post_content' => 'Page content',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		];
		$unique_identifier = 'test-page-101';

		$result = Posts::create_or_get_post( $post_data, $unique_identifier );

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		$post = get_post( $result );
		$this->assertEquals( 'Test Page', $post->post_title );
		$this->assertEquals( 'page', $post->post_type );
	}

	/**
	 * Test creating a custom post type.
	 */
	public function test_create_custom_post_type() {
		// Register a custom post type for testing
		register_post_type(
			'test_cpt',
			[
				'public' => true,
				'label'  => 'Test CPT',
			]
		);

		$post_data         = [
			'post_title'   => 'Custom Post Type',
			'post_content' => 'CPT content',
			'post_status'  => 'publish',
			'post_type'    => 'test_cpt',
		];
		$unique_identifier = 'custom-cpt-202';

		$result = Posts::create_or_get_post( $post_data, $unique_identifier );

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		$post = get_post( $result );
		$this->assertEquals( 'Custom Post Type', $post->post_title );
		$this->assertEquals( 'test_cpt', $post->post_type );
	}

	/**
	 * Test creating a post with all available fields.
	 */
	public function test_create_post_with_all_fields() {
		$post_data         = [
			'post_title'     => 'Full Post',
			'post_content'   => 'Full content',
			'post_excerpt'   => 'Post excerpt',
			'post_status'    => 'publish',
			'post_type'      => 'post',
			'post_name'      => 'full-post-slug',
			'post_author'    => 1,
			'post_parent'    => 0,
			'menu_order'     => 0,
			'comment_status' => 'open',
			'ping_status'    => 'open',
		];
		$unique_identifier = 'full-post-303';

		$result = Posts::create_or_get_post( $post_data, $unique_identifier );

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		$post = get_post( $result );
		$this->assertEquals( 'Full Post', $post->post_title );
		$this->assertEquals( 'Full content', $post->post_content );
		$this->assertEquals( 'Post excerpt', $post->post_excerpt );
		$this->assertEquals( 'full-post-slug', $post->post_name );
		$this->assertEquals( 1, $post->post_author );
		$this->assertEquals( 'open', $post->comment_status );
		$this->assertEquals( 'open', $post->ping_status );
	}

	/**
	 * Test that posts with different unique identifiers are treated as separate posts.
	 */
	public function test_posts_with_different_unique_identifiers() {
		$post_data = [
			'post_title'   => 'Same Title Post',
			'post_content' => 'Same content',
			'post_status'  => 'publish',
		];

		$result1 = Posts::create_or_get_post( $post_data, 'unique-id-1' );
		$result2 = Posts::create_or_get_post( $post_data, 'unique-id-2' );

		$this->assertIsInt( $result1 );
		$this->assertIsInt( $result2 );
		$this->assertNotEquals( $result1, $result2 );

		// Both posts should exist
		$post1 = get_post( $result1 );
		$post2 = get_post( $result2 );

		$this->assertEquals( 'Same Title Post', $post1->post_title );
		$this->assertEquals( 'Same Title Post', $post2->post_title );

		// Check unique identifiers
		$meta1 = get_post_meta( $result1, Posts::UNIQUE_POST_IDENTIFIER_META_KEY, true );
		$meta2 = get_post_meta( $result2, Posts::UNIQUE_POST_IDENTIFIER_META_KEY, true );

		$this->assertEquals( 'unique-id-1', $meta1 );
		$this->assertEquals( 'unique-id-2', $meta2 );
	}

	/**
	 * Test that updating post data doesn't affect existing post retrieval.
	 */
	public function test_update_post_data_doesnt_affect_retrieval() {
		$original_data     = [
			'post_title'   => 'Original Title',
			'post_content' => 'Original content',
			'post_status'  => 'publish',
		];
		$unique_identifier = 'update-test-404';

		// Create the post
		$post_id = Posts::create_or_get_post( $original_data, $unique_identifier );
		$this->assertIsInt( $post_id );

		// Try to create with updated data
		$updated_data = [
			'post_title'   => 'Updated Title',
			'post_content' => 'Updated content',
			'post_status'  => 'draft',
		];

		$result = Posts::create_or_get_post( $updated_data, $unique_identifier );

		// Should return the same post ID
		$this->assertEquals( $post_id, $result );

		// The post should still have the original data (not updated)
		$post = get_post( $post_id );
		$this->assertEquals( 'Original Title', $post->post_title );
		$this->assertEquals( 'Original content', $post->post_content );
		$this->assertEquals( 'publish', $post->post_status );
	}

	/**
	 * Test creating a post with special characters in the unique identifier.
	 */
	public function test_create_post_with_special_characters_in_identifier() {
		$post_data         = [
			'post_title'   => 'Special Chars Post',
			'post_content' => 'Content with special chars',
		];
		$unique_identifier = 'special-chars-!@#$%^&*()_+-=[]{}|;:,.<>?';

		$result = Posts::create_or_get_post( $post_data, $unique_identifier );

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		$meta_value = get_post_meta( $result, Posts::UNIQUE_POST_IDENTIFIER_META_KEY, true );
		$this->assertEquals( $unique_identifier, $meta_value );
	}

	/**
	 * Test creating a post with a very long unique identifier.
	 */
	public function test_create_post_with_long_identifier() {
		$post_data         = [
			'post_title'   => 'Long ID Post',
			'post_content' => 'Content for long ID post',
		];
		$unique_identifier = str_repeat( 'a', 1000 ); // Very long identifier

		$result = Posts::create_or_get_post( $post_data, $unique_identifier );

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		$meta_value = get_post_meta( $result, Posts::UNIQUE_POST_IDENTIFIER_META_KEY, true );
		$this->assertEquals( $unique_identifier, $meta_value );
	}

	/**
	 * Test creating a post with numeric unique identifier.
	 */
	public function test_create_post_with_numeric_identifier() {
		$post_data         = [
			'post_title'   => 'Numeric ID Post',
			'post_content' => 'Content for numeric ID post',
		];
		$unique_identifier = '12345';

		$result = Posts::create_or_get_post( $post_data, $unique_identifier );

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		$meta_value = get_post_meta( $result, Posts::UNIQUE_POST_IDENTIFIER_META_KEY, true );
		$this->assertEquals( $unique_identifier, $meta_value );
	}

	/**
	 * Test that the get_post_by_unique_identifier method works correctly.
	 */
	public function test_get_post_by_unique_identifier() {
		$post_data         = [
			'post_title'   => 'Get By ID Post',
			'post_content' => 'Content for get by ID post',
		];
		$unique_identifier = 'get-by-id-505';

		// Create the post
		$post_id = Posts::create_or_get_post( $post_data, $unique_identifier );
		$this->assertIsInt( $post_id );

		// Get the post by unique identifier
		$found_post_id = Posts::get_post_by_unique_identifier( $unique_identifier );

		$this->assertEquals( $post_id, $found_post_id );
	}

	/**
	 * Test that get_post_by_unique_identifier returns false for non-existent identifier.
	 */
	public function test_get_post_by_unique_identifier_not_found() {
		$result = Posts::get_post_by_unique_identifier( 'non-existent-id' );

		$this->assertFalse( $result );
	}

	/**
	 * Test that get_post_by_unique_identifier returns false for empty identifier.
	 */
	public function test_get_post_by_unique_identifier_empty() {
		$result = Posts::get_post_by_unique_identifier( '' );

		$this->assertFalse( $result );
	}

	/**
	 * Test creating multiple posts and verifying they all have unique IDs.
	 */
	public function test_multiple_posts_have_unique_ids() {
		$post_ids           = [];
		$unique_identifiers = [];

		// Create 10 posts
		for ( $i = 1; $i <= 10; $i++ ) {
			$post_data         = [
				'post_title'   => "Post $i",
				'post_content' => "Content for post $i",
				'post_status'  => 'publish',
			];
			$unique_identifier = "post-$i-identifier";

			$post_id              = Posts::create_or_get_post( $post_data, $unique_identifier );
			$post_ids[]           = $post_id;
			$unique_identifiers[] = $unique_identifier;
		}

		// All post IDs should be unique
		$this->assertCount( 10, array_unique( $post_ids ) );

		// All unique identifiers should be unique
		$this->assertCount( 10, array_unique( $unique_identifiers ) );

		// Verify each post has the correct meta
		foreach ( $post_ids as $index => $post_id ) {
			$meta_value = get_post_meta( $post_id, Posts::UNIQUE_POST_IDENTIFIER_META_KEY, true );
			$this->assertEquals( $unique_identifiers[ $index ], $meta_value );
		}
	}

	/**
	 * Test creating a post with an empty title.
	 */
	public function test_wp_insert_post_error_handling() {
		$post_data         = [
			'post_title'   => '', // Empty title should not cause an error
			'post_content' => 'Content',
			'post_status'  => 'publish',
		];
		$unique_identifier = 'error-test-606';

		$result = Posts::create_or_get_post( $post_data, $unique_identifier );

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		$post = get_post( $result );
		$this->assertEquals( 'Content', $post->post_content );
		$this->assertEquals( 'publish', $post->post_status );
		$this->assertEquals( $unique_identifier, get_post_meta( $result, Posts::UNIQUE_POST_IDENTIFIER_META_KEY, true ) );
	}

	/**
	 * Test creating a post with a future date.
	 */
	public function test_create_post_with_future_date() {
		$future_date       = gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) );
		$post_data         = [
			'post_title'   => 'Future Post',
			'post_content' => 'Future content',
			'post_status'  => 'future',
			'post_date'    => $future_date,
		];
		$unique_identifier = 'future-post-707';

		$result = Posts::create_or_get_post( $post_data, $unique_identifier );

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		$post = get_post( $result );
		$this->assertEquals( 'Future Post', $post->post_title );
		$this->assertEquals( 'future', $post->post_status );
		$this->assertEquals( $future_date, $post->post_date );
	}
}
