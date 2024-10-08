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
	 * This mostly tests that the plugin is installed and activated correctly in the test.
	 *
	 * The reason this test is here is that the CoAuthorsPlusHelper makes a lot of assumptions about
	 * code inclusion, so think of this test as a canary.
	 */
	public function test_plugin_activated() {
		$helper = new CoAuthorsPlusHelper();
		$this->assertTrue( $helper->validate_co_authors_plus_dependencies() );
	}

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
}
