<?php

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\MigrationTools\Logic\UsersHelper;
use WP_UnitTestCase;

class TestUsersHelper extends WP_UnitTestCase {

	private int $peter_parker_id;
	private string $peter_parker_uniqid = '718-808-8342';

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
		// Not ideal that we set this here, but since we are using the test user factory, we can't set it at creation time.
		add_user_meta( $this->peter_parker_id, UsersHelper::UNIQUE_IDENTIFIER_META_KEY, $this->peter_parker_uniqid, true );
		add_filter( 'newspack_migration_tools_enable_file_log', '__return_true' );
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
			],
			$this->peter_parker_uniqid
		);
		$this->assertEquals( $peter->ID, $copycat->ID );
		$copycat = UsersHelper::create_or_get_user(
			[
				'user_email' => $peter->user_email,
			],
			$this->peter_parker_uniqid
		);
		$this->assertEquals( $peter->ID, $copycat->ID );
		$copycat = UsersHelper::create_or_get_user(
			[
				'user_nicename' => $peter->user_nicename,
			],
			$this->peter_parker_uniqid
		);
		$this->assertEquals( $peter->ID, $copycat->ID );
	}

	public function test_create_user_lacking_data() {
		$this->expectException( \InvalidArgumentException::class );
		// Not passing one of the 4 required fields should throw an exception.
		UsersHelper::create_or_get_user(
			[
				'role' => 'editor',
			],
			'bork'
		);
	}

	/**
	 * Test that when a user is created – the unique identifier is set.
	 */
	public function test_create_user_sets_unique_identifier() {
		$bob = UsersHelper::create_or_get_user(
			[
				'user_login' => 'bobby',
				'first_name' => 'Bob',
				'last_name'  => ' ', // Empty last name.
			],
			'bobsyouruncle'
		);
		// Do a quick assert and see that a last name as ' '  will be trimmed away in the nicename.
		$this->assertEquals( 'bob', $bob->user_nicename );
		// Now check that the unique identifier was set on the user.
		$this->assertNotEmpty( get_user_meta( $bob->ID, UsersHelper::UNIQUE_IDENTIFIER_META_KEY, true ) );
	}

	/**
	 * Test that a username that is too long is shortened.
	 *
	 * Also test that a generated username with an appended number is not too long.
	 */
	public function test_too_long_unused_username() {
		$max_length        = 60;
		$long_username     = 'this_is_a_very_long_username_that_is_too_long_to_be_used_so_should_come_back_shorter';
		$should_be_shorter = UsersHelper::get_unused_username( $long_username );
		$this->assertTrue( strlen( $should_be_shorter ) <= $max_length );
		$user = UsersHelper::create_or_get_user(
			[
				'user_login' => $should_be_shorter,
			],
			$long_username
		);
		$this->assertEquals( $should_be_shorter, $user->user_login );

		$should_also_be_shorter_and_different = UsersHelper::get_unused_username( $long_username );
		$this->assertTrue( strlen( $should_also_be_shorter_and_different ) <= $max_length );
		$this->assertNotEquals( $user->user_login, $should_also_be_shorter_and_different );
	}

	/**
	 * Test that a nicename that is too long is shortened.
	 *
	 * Also test that a generated nicename with an appended number is not too long.
	 */
	public function test_too_long_unused_nicename() {
		$max_length        = 50;
		$long_nicename     = 'Just such a long nicename that it really will not make sense to use it without shortening it';
		$should_be_shorter = UsersHelper::get_unused_nicename( $long_nicename );
		$this->assertTrue( strlen( $should_be_shorter ) <= $max_length );
		$user        = UsersHelper::create_or_get_user(
			[
				'user_nicename' => $should_be_shorter,
			],
			$long_nicename
		);
		$as_nicename = str_replace( ' ', '-', strtolower( $should_be_shorter ) );
		$this->assertEquals( $as_nicename, $user->user_nicename );

		$should_also_be_shorter_and_different = UsersHelper::get_unused_nicename( $long_nicename );
		$this->assertTrue( strlen( $should_also_be_shorter_and_different ) <= $max_length );
	}
}
