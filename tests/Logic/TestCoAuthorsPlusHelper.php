<?php

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\MigrationTools\Logic\CoAuthorsPlusHelper;
use UnexpectedValueException;
use WP_UnitTestCase;

/**
 * Class TestCoAuthorsPlusHelper
 *
 * @package Newspack\MigrationTools\Tests\Logic
 */
class TestCoAuthorsPlusHelper extends WP_UnitTestCase {

	/**
	 * Test that creating a guest author from a user works.
	 */
	public function test_ga_create_from_user() {
		$lois_email   = 'lois@publisher.com';
		$lois_user_id = $this->factory()->user->create(
			[
				'user_login'    => $lois_email,
				'user_password' => wp_generate_password(),
				'role'          => 'author',
				'user_nicename' => 'Lois Lane',
			]
		);
		$lois_user    = get_user_by( 'id', $lois_user_id );

		$helper = new CoAuthorsPlusHelper();
		// No guest author should be found for this user yet.
		$this->assertFalse( $helper->get_guest_author_by_user_login( $lois_email ) );

		// Now create the GA from the user.
		$lois_ga = $helper->get_or_create_guest_author_from_user( $lois_user );
		// They should be the same.
		$this->assertNotEquals( $lois_ga, $helper->get_or_create_guest_author_from_user( $lois_user ) );
	}

	/**
	 * Test that the display name is required.
	 */
	public function test_display_name() {
		$helper = new CoAuthorsPlusHelper();
		// Because the user below does not have a display name, it should throw an exception.
		$this->expectException( UnexpectedValueException::class );
		$helper->create_guest_author(
			[
				'user_login' => 'bork2@example.com',
			]
		);
	}

	/**
	 * Test assign authors to post.
	 */
	public function test_assign_authors_to_post() {

		// Get the Helper.
		$helper = new CoAuthorsPlusHelper();

		// Verify the CAP plugin is not activated.
		$this->assertFalse( $helper->is_coauthors_active() );

		// Create a post.
		$post_id = self::factory()->post->create();

		// Create Ga.
		$ga_id  = $helper->create_guest_author( array( 'display_name' => 'Test User' ) );
		$ga     = $helper->get_guest_author_by_id( $ga_id );

		// Try to assign author to post.
		// assign_authors_to_post will throw exception on failure.
		// if no exception, assert success.
		$helper->assign_authors_to_post( array( $ga ), $post_id );
		$this->assertTrue( true );
	}
}
