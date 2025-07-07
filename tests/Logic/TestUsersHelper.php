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

	/**
	 * Comprehensive test for get_unused_fake_email method.
	 *
	 * Tests various scenarios including the bug fix where long emails were not properly handled
	 * when generating unique emails with prepended numbers.
	 */
	public function test_get_unused_fake_email_comprehensive() {
		// Test 1: Basic functionality - email that doesn't exist
		$test_email = 'test@example.com';
		$result     = UsersHelper::get_unused_fake_email( $test_email );
		$this->assertEquals( $test_email, $result );
		$this->assertNotFalse( is_email( $result ) );

		// Test 2: Email that already exists - should prepend a number
		$existing_user = $this->factory()->user->create(
			[
				'user_email' => 'existing@example.com',
			]
		);
		$result        = UsersHelper::get_unused_fake_email( 'existing@example.com' );
		$this->assertNotEquals( 'existing@example.com', $result );
		$this->assertStringStartsWith( '1', $result );
		$this->assertStringEndsWith( 'existing@example.com', $result );
		$this->assertNotFalse( is_email( $result ) );

		// Test 3: Multiple existing emails - should increment the prepended number
		$existing_user2 = $this->factory()->user->create(
			[
				'user_email' => '1existing@example.com',
			]
		);
		$result         = UsersHelper::get_unused_fake_email( 'existing@example.com' );
		$this->assertStringStartsWith( '2', $result );
		$this->assertStringEndsWith( 'existing@example.com', $result );
		$this->assertNotFalse( is_email( $result ) );

		// Test 4: Email that is too long (>100 characters) - should be shortened
		$long_email = str_repeat( 'a', 88 ) . '@example.com'; // 100 characters total (88 + 1 + 11)
		$result     = UsersHelper::get_unused_fake_email( $long_email );
		$this->assertEquals( 100, strlen( $result ) );
		$this->assertNotFalse( is_email( $result ) );
		$this->assertStringEndsWith( '@example.com', $result );

		// Test 5: The bug fix - long email that needs to be shortened AND has conflicts
		// This tests the critical bug where $original_email was used instead of $desired_email
		$very_long_email           = str_repeat( 'b', 89 ) . '@example.com'; // 101 characters (89 + 1 + 11)
		$very_long_email_shortened = str_repeat( 'b', 84 ) . '@example.com'; // 100 characters (84 + 1 + 11). peeled off 4 characters.
		$existing_user3            = $this->factory()->user->create(
			[
				'user_email' => $very_long_email_shortened, // The shortened version
			]
		);
		$result                    = UsersHelper::get_unused_fake_email( $very_long_email );
		$this->assertLessThanOrEqual( 100, strlen( $result ) );
		$this->assertStringStartsWith( '1', $result );
		$this->assertStringEndsWith( '@example.com', $result );
		$this->assertNotFalse( is_email( $result ) );

		// Test 6: Multiple conflicts with long email
		$existing_user4 = $this->factory()->user->create(
			[
				'user_email' => '1' . $very_long_email_shortened,
			]
		);
		$result         = UsersHelper::get_unused_fake_email( $very_long_email );
		$this->assertLessThanOrEqual( 100, strlen( $result ) );
		$this->assertStringStartsWith( '2', $result );
		$this->assertStringEndsWith( '@example.com', $result );
		$this->assertNotFalse( is_email( $result ) );

		// Test 7: Edge case - extremely long email
		$extremely_long_email = str_repeat( 'c', 200 ) . '@example.com';
		$result               = UsersHelper::get_unused_fake_email( $extremely_long_email );
		$this->assertLessThanOrEqual( 100, strlen( $result ) );
		$this->assertNotFalse( is_email( $result ) );

		// Test 8: Empty string (edge case)
		$result = UsersHelper::get_unused_fake_email( '' );
		$this->assertFalse( is_email( $result ) );

		// Test 9: Email with special characters
		$special_email = 'test+tag@example.com';
		$result        = UsersHelper::get_unused_fake_email( $special_email );
		$this->assertEquals( $special_email, $result );
		$this->assertNotFalse( is_email( $result ) );

		// Test 10: Email that is exactly 100 characters
		$exact_length_email = str_repeat( 'd', 88 ) . '@example.com'; // 100 characters (88 + 1 + 11)
		$result             = UsersHelper::get_unused_fake_email( $exact_length_email );
		$this->assertEquals( $exact_length_email, $result );
		$this->assertNotFalse( is_email( $result ) );
	}
}
