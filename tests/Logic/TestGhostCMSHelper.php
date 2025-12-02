<?php

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\Guest_Contributor_Role;
use Newspack\MigrationTools\Logic\GhostCMSHelper;
use WP_UnitTestCase;

class TestGhostCMSHelper extends WP_UnitTestCase {

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
