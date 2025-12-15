<?php

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\Guest_Contributor_Role;
use Newspack\MigrationTools\Logic\GhostCMSHelper;
use WP_UnitTestCase;

class TestGhostCMSHelper extends WP_UnitTestCase {

	/**
	 * Test empty path returns the root JSON object.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_empty_path_returns_root(): void {
		$json = json_decode( '{"db": [{"data": {"posts": []}}]}' );

		$helper = new GhostCMSHelper();

		$result = $helper->get_json_data_from_path( '', $json );

		$this->assertSame( $json, $result );
	}

	/**
	 * Test path with leading dot is stripped and works correctly.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_strips_leading_dot(): void {
		$json = json_decode( '{"db": [{"data": {"posts": []}}]}' );

		$helper = new GhostCMSHelper();

		$result = $helper->get_json_data_from_path( '.db[0].data', $json );

		$this->assertIsObject( $result );
		$this->assertTrue( property_exists( $result, 'posts' ) );
	}

	/**
	 * Test simple property access.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_simple_property(): void {
		$json = json_decode( '{"settings": {"theme": "dark"}}' );

		$helper = new GhostCMSHelper();

		$result = $helper->get_json_data_from_path( 'settings', $json );

		$this->assertIsObject( $result );
		$this->assertEquals( 'dark', $result->theme );
	}

	/**
	 * Test nested property access with dots.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_nested_properties(): void {
		$json = json_decode( '{"level1": {"level2": {"level3": {"value": "deep"}}}}' );

		$helper = new GhostCMSHelper();

		$result = $helper->get_json_data_from_path( 'level1.level2.level3', $json );

		$this->assertIsObject( $result );
		$this->assertEquals( 'deep', $result->value );
	}

	/**
	 * Test array index access with property.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_array_index_with_property(): void {
		$json = json_decode( '{"db": [{"name": "first"}, {"name": "second"}, {"name": "third"}]}' );

		$helper = new GhostCMSHelper();

		// Access first element.
		$result = $helper->get_json_data_from_path( 'db[0]', $json );
		$this->assertIsObject( $result );
		$this->assertEquals( 'first', $result->name );

		// Access second element.
		$result = $helper->get_json_data_from_path( 'db[1]', $json );
		$this->assertIsObject( $result );
		$this->assertEquals( 'second', $result->name );

		// Access third element.
		$result = $helper->get_json_data_from_path( 'db[2]', $json );
		$this->assertIsObject( $result );
		$this->assertEquals( 'third', $result->name );
	}

	/**
	 * Test typical Ghost CMS path: db[0].data
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_ghost_cms_typical_path(): void {
		$json = json_decode(
			'{
			"db": [{
				"meta": {"version": "5.0"},
				"data": {
					"posts": [{"title": "Hello"}],
					"tags": [{"name": "News"}],
					"users": [{"name": "Author"}]
				}
			}]
		}' 
		);

		$helper = new GhostCMSHelper();

		$result = $helper->get_json_data_from_path( 'db[0].data', $json );

		$this->assertIsObject( $result );
		$this->assertTrue( property_exists( $result, 'posts' ) );
		$this->assertTrue( property_exists( $result, 'tags' ) );
		$this->assertTrue( property_exists( $result, 'users' ) );
	}

	/**
	 * Test deeply nested path with multiple array indices.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_multiple_array_indices(): void {
		$json = json_decode(
			'{
			"databases": [{
				"tables": [{
					"rows": [{"id": 1}, {"id": 2}]
				}]
			}]
		}' 
		);

		$helper = new GhostCMSHelper();

		$result = $helper->get_json_data_from_path( 'databases[0].tables[0]', $json );

		$this->assertIsObject( $result );
		$this->assertTrue( property_exists( $result, 'rows' ) );
	}

	/**
	 * Test non-existent property returns null.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_nonexistent_property_returns_null(): void {
		$json = json_decode( '{"existing": {"value": 1}}' );

		$helper = new GhostCMSHelper();

		$result = $helper->get_json_data_from_path( 'nonexistent', $json );

		$this->assertNull( $result );
	}

	/**
	 * Test non-existent nested property returns null.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_nonexistent_nested_property_returns_null(): void {
		$json = json_decode( '{"level1": {"level2": {}}}' );

		$helper = new GhostCMSHelper();

		$result = $helper->get_json_data_from_path( 'level1.level2.level3', $json );

		$this->assertNull( $result );
	}

	/**
	 * Test array index out of bounds returns null.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_index_out_of_bounds_returns_null(): void {
		$json = json_decode( '{"items": [{"id": 1}, {"id": 2}]}' );

		$helper = new GhostCMSHelper();

		$result = $helper->get_json_data_from_path( 'items[99]', $json );

		$this->assertNull( $result );
	}

	/**
	 * Test accessing array index on non-array returns null.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_index_on_non_array_returns_null(): void {
		$json = json_decode( '{"notArray": {"key": "value"}}' );

		$helper = new GhostCMSHelper();

		$result = $helper->get_json_data_from_path( 'notArray[0]', $json );

		$this->assertNull( $result );
	}

	/**
	 * Test accessing property on non-existent intermediate path returns null.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_invalid_intermediate_path_returns_null(): void {
		$json = json_decode( '{"db": [{"data": {}}]}' );

		$helper = new GhostCMSHelper();

		$result = $helper->get_json_data_from_path( 'db[0].invalid.something', $json );

		$this->assertNull( $result );
	}

	/**
	 * Test path that resolves to non-object returns null.
	 *
	 * The method explicitly returns null if the final result is not an object.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_non_object_result_returns_null(): void {
		$json = json_decode( '{"config": {"name": "test"}}' );

		$helper = new GhostCMSHelper();

		// "name" is a string, not an object.
		$result = $helper->get_json_data_from_path( 'config.name', $json );

		$this->assertNull( $result );
	}

	/**
	 * Test that GhostCMS Helper will import from JSON file.
	 *
	 * @return void
	 */
	public function test_ghostcms_import(): void {

		// Run test.
		$test_ghostcms_helper = new GhostCMSHelper();
		$test_ghostcms_helper->ghostcms_import( 
			[], 
			[
				'json-file'       => 'tests/fixtures/ghostcms.json',
				'ghost-url'       => 'https://newspack.com/',
				'default-user-id' => 1,
			],
			''
		);

		// Posts.
		$posts = get_posts(
			[
				'title'       => 'The Title',
				'numberposts' => 1,
			]
		);
		$this->assertIsArray( $posts );
		$this->assertCount( 1, $posts );
		$this->assertEquals( 'the-title', $posts[0]->post_name );

		$user = get_user_by( 'login', 'some-user' );
		$this->assertInstanceOf( \WP_User::class, $user );
		
		// Guest Contributor created with correct role.
		$this->assertContains( Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME, $user->roles );

		// User data imported correctly.
		$this->assertEquals( 'Test author biography for unit tests.', $user->description );
		$this->assertEquals( 'https://newspack.com', $user->user_url );

		// Social links imported as user meta (twitter as handle, others as full URLs, as defined in Newspack theme, `function newspack_author_get_social_links()`).
		$this->assertEquals( 'someuser', get_user_meta( $user->ID, 'twitter', true ) );
		$this->assertEquals( 'https://instagram.com/someuser_insta', get_user_meta( $user->ID, 'instagram', true ) );
		$this->assertEquals( 'https://linkedin.com/in/someuser-linkedin', get_user_meta( $user->ID, 'linkedin', true ) );
		$this->assertEquals( 'https://bsky.app/profile/someuser.bsky.social', get_user_meta( $user->ID, 'bluesky', true ) );

		// Categories.
		$category = get_term_by( 'name', 'News', 'category' );
		$this->assertIsObject( $category );
		$this->assertEquals( 'news', $category->slug );
	}
}
