<?php

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\MigrationTools\Logic\UsersHelper;
use WP_UnitTestCase;

class TestUsersHelper extends WP_UnitTestCase {

	private int $peter_parker_id;

	protected function setUp(): void {
		parent::setUp();
		$this->peter_parker_id = $this->factory()->user->create(
			[
				'display_name'  => 'Peter Parker',
				'user_login'    => 'spidey',
				'user_password' => wp_generate_password(),
				'role'          => 'author',
			]
		);
	}

	public function test_unused_email() {
		$peter = get_user_by( 'ID', $this->peter_parker_id );

		// Get an unused email from Peter's email.
		$unused_email = UsersHelper::get_unused_fake_email( $peter->user_email );
		$this->assertNotEquals( $peter->user_email, $unused_email );
		// Check that it is still an email that works.
		$this->assertNotFalse( is_email( $unused_email ) );
		// Test that passing an email that is too long will return a shorter email.
		$bytes                  = random_bytes( 50 ); // 50 bytes = 100 hex characters
		$email_that_is_too_long = bin2hex( $bytes ) . '@example.com';
		$fake_shorter_email     = UsersHelper::get_unused_fake_email( $email_that_is_too_long );
		$this->assertNotEquals( strlen( $fake_shorter_email ), strlen( $email_that_is_too_long ) );
	}

	public function test_unused_nicename() {
		$peter = get_user_by( 'ID', $this->peter_parker_id );

		$unused_nicename = UsersHelper::get_unused_nicename( $peter->user_nicename );
		$this->assertNotEquals( $peter->user_nicename, $unused_nicename );
		$this->assertFalse( get_user_by( 'slug', $unused_nicename ) );
		$this->assertTrue( strlen( $unused_nicename ) <= 50 );
	}

	public function test_unused_username() {
		$peter = get_user_by( 'ID', $this->peter_parker_id );

		$incremented_peter = UsersHelper::get_unused_username( $peter->user_login );
		$this->assertNotEquals( $peter->user_login, $incremented_peter );
		$this->assertTrue( validate_username( $incremented_peter ) );
	}

	public function test_create_getting_user() {
		$peter = get_user_by( 'ID', $this->peter_parker_id );
		// Test that trying to create a user with the same data as an existing user returns the existing user.
		$copycat = UsersHelper::create_or_get_user(
			[
				'user_login' => $peter->user_login,
			]
		);
		$this->assertEquals( $peter->ID, $copycat->ID );
		$copycat = UsersHelper::create_or_get_user(
			[
				'user_email' => $peter->user_email,
			]
		);
		$this->assertEquals( $peter->ID, $copycat->ID );
		$copycat = UsersHelper::create_or_get_user(
			[
				'user_nicename' => $peter->user_nicename,
			]
		);
		$this->assertEquals( $peter->ID, $copycat->ID );
	}

	public function test_create_user_lacking_data() {
		$this->expectException( \InvalidArgumentException::class );
		// Not passing one of the 4 required fields should throw an exception.
		UsersHelper::create_or_get_user(
			[
				'role' => 'editor',
			]
		);
	}
}
