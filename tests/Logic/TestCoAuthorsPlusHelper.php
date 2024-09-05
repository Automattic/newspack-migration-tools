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

		// Verify the CAP plugin is not properly activated.
		$this->assertFalse( $helper->validate_co_authors_plus_dependencies() );

		// So, the CAP plugin is not active.  
		// If the plugin is not active, then the following 
		// test below should fail?  But PHPUnit is "activating" the plugin somehow,
		// and the test below will pass.

		// Create a post.
		$post_id = self::factory()->post->create();

		// Create Ga.
		$ga_id = $helper->create_guest_author( array( 'display_name' => 'Test User' ) );
		$ga    = $helper->get_guest_author_by_id( $ga_id );

		// Try to assign author to post.
		// assign_authors_to_post will throw exception on failure.
		// if no exception, assert success.
		$helper->assign_authors_to_post( array( $ga ), $post_id );

		// For extra carity, call the validate again (it was called before in the assign_to_posts above)
		// but let's be extra specific for this test.
		$this->assertTrue( $helper->validate_authors_for_post( $post_id, array( $ga ) ) );
	}

	/**
	 * Test that the CAP plugin is not active, but PHPUNIT will still "activated" it.
	 */
	public function test_cap_not_active_but_phpunit_still_activated_it() {

		// Get the Helper.
		$helper = new CoAuthorsPlusHelper();

		// Verify the CAP plugin is not activated.
		$this->assertFalse( $helper->validate_co_authors_plus_dependencies() );

		// Verify there are no must use plugins.
		$this->assertEmpty( get_mu_plugins() );
		
		// Assert PHPUnit is still activated the plugin.
		$this->assertTrue( $helper->validate_co_authors_plus_cpt_tax_loaded() );
	}
}
