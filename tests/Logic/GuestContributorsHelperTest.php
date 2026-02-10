<?php

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\Guest_Contributor_Role;
use Newspack\MigrationTools\Logic\GuestContributorsHelper;
use WP_Error;
use WP_UnitTestCase;

class GuestContributorsHelperTest extends WP_UnitTestCase {

	/**
	 * Test that the Newspack Plugin is installed and activated.
	 */
	public function test_newspack_plugin_activated() {
		$helper = new GuestContributorsHelper();
		$this->assertTrue( $helper->validate_newspack_plugin() );
	}

	/**
	 * Test that sanitize_for_db works as expected.
	 *
	 * @dataProvider data_provider_sanitize_for_db
	 */
	public function test_sanitize_for_db( $input, $expected ) {
		$result = GuestContributorsHelper::sanitize_for_db( $input );
		$this->assertMatchesRegularExpression( $expected, $result );
	}

	public function data_provider_sanitize_for_db() {
		// type => input, expected (regex match).
		return [
			'empty string'   => [ '', '/^$/' ],
			'html content'   => [ '&nbsp; <div>', '/^$/' ], // will be stripped.
			'normal name'    => [ 'John Smith', '/^john-smith$/' ],
			'accented chars' => [ 'José ', '/^jose$/' ],
		];
	}

	/**
	 * Test that generate_username works as expected.
	 *
	 * @dataProvider data_provider_generate_username
	 */
	public function test_generate_username( $input, $expected, $expect_error = false ) {
		$result = GuestContributorsHelper::generate_username( $input );

		if ( $expect_error ) {
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertEquals( 'ERROR_SANITIZE_INPUT', $result->get_error_code() );
		} else {
			$this->assertMatchesRegularExpression( $expected, $result );
		}
	}

	public function data_provider_generate_username() {
		// type => input, expected (regex match or null if expected error), expect_error.
		return [
			'empty string' => [ '', null, true ], // expects error.
			'html content' => [ '&nbsp; <div>', null, true ], // expects error.
			'normal name'  => [ 'John Smith', '/^john-smith-[0-9]{5}$/' ],
			'long name'    => [ str_repeat( 'José ', 13 ), '/^' . str_repeat( 'jose-', 11 ) . '[0-9]{5}$/' ],
		];
	}

	/**
	 * Test that generate_email works as expected.
	 *
	 * @dataProvider data_provider_generate_email
	 */
	public function test_generate_email( $input, $expected, $expect_error = false ) {
		$result = GuestContributorsHelper::generate_email( $input );

		if ( $expect_error ) {
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertEquals( 'ERROR_SANITIZE_INPUT', $result->get_error_code() );
		} else {
			$this->assertMatchesRegularExpression( $expected, $result );
		}
	}

	public function data_provider_generate_email() {
		// type => input, expected (regex match or null if expected error), expect_error.
		return [
			'empty string' => [ '', null, true ], // expects error.
			'html content' => [ '&nbsp; <div>', null, true ], // expects error.
			'normal name'  => [ 'John Smith', '/^john-smith-[0-9]{5}@example.com$/' ],
			'long name'    => [ str_repeat( 'José ', 21 ), '/^' . str_repeat( 'jose-', 16 ) . 'jo-[0-9]{5}@example.com$/' ],
		];
	}

	/**
	 * Test that create_by_display_name works as expected.
	 *
	 * @dataProvider data_provider_create_by_display_name
	 */
	public function test_create_by_display_name( $input, $args = array(), $force = false, $expect_error = false ) {
		$result = GuestContributorsHelper::create_by_display_name( $input, $args, $force );

		if ( $expect_error ) {
			$this->assertInstanceOf( WP_Error::class, $result );
		} else {
			$this->assertMatchesRegularExpression( '/^\d+$/', $result );
		}
	}

	public function data_provider_create_by_display_name() {
		// type => input, args, force, expect_error.
		return [
			'empty string'        => [ '', [], false, true ], // expects error.
			'html content'        => [ '&nbsp; <div>', [], false, true ], // expects error.
			'normal name'         => [ 'John Smith' ], // success.
			'accented chars'      => [ 'José' ], // success.
			'long name ok'        => [ str_repeat( 'A', 250 ) ], // success.
			'long name too long'  => [ str_repeat( 'B', 251 ), [], false, true ], // expects error.
			'user_nicename'       => [ 'John Smith', [ 'user_nicename' => 'john-smith' ] ], // success.
			'user_nicename empty' => [ 'John Smith', [ 'user_nicename' => '' ], false, true ], // expects error.
			'user_nicename html'  => [ 'John Smith', [ 'user_nicename' => '&nbsp; <div>' ], false, true ], // expects error.
		];
	}

	/**
	 * Test that the complete flow works as expected.
	 */
	public function test_complete_flow() {

		$display_name = 'John Smith';

		// Verify not found.
		$result = GuestContributorsHelper::get_by_display_name( $display_name );
		$this->assertEmpty( $result );

		// Create by display name.
		$result = GuestContributorsHelper::create_by_display_name( $display_name );
		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		// Get will return ID now.
		$result = GuestContributorsHelper::get_by_display_name( $display_name );
		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
		$this->assertIsNumeric( reset( $result ) );
		$this->assertGreaterThan( 0, reset( $result ) );

		// Create again with same display name should fail.
		$result = GuestContributorsHelper::create_by_display_name( $display_name );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'ERROR_EXISTING_USERS', $result->get_error_code() );

		// Create with force should succeed.
		$result = GuestContributorsHelper::create_by_display_name( $display_name, [], true );
		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		// Get should now return multiple IDs
		$result = GuestContributorsHelper::get_by_display_name( $display_name );
		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
		$this->assertCount( 2, $result );
	}

	/**
	 * Test that create_or_get_contributor works as expected.
	 *
	 * @dataProvider data_provider_create_or_get_contributor
	 */
	public function test_create_or_get_contributor( $data, $unique_identifier, $expect_error = false ) {
		$result = GuestContributorsHelper::create_or_get_contributor( $data, $unique_identifier );

		if ( $expect_error ) {
			$this->assertInstanceOf( WP_Error::class, $result );
		} else {
			$this->assertInstanceOf( \WP_User::class, $result );
			// Verify the user has the correct role
			$this->assertContains( Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME, $result->roles );
		}
	}

	public function data_provider_create_or_get_contributor() {
		return [
			'valid user with email'             => [
				[
					'user_email'   => 'test@example.com',
					'display_name' => 'Test User',
				],
				'unique-id-1',
				false,
			],
			'valid user with login'             => [
				[
					'user_login'   => 'testuser',
					'display_name' => 'Test User',
				],
				'unique-id-2',
				false,
			],
			'valid user with nicename'          => [
				[
					'user_nicename' => 'test-user',
					'display_name'  => 'Test User',
				],
				'unique-id-3',
				false,
			],
			'valid user with display_name only' => [
				[
					'display_name' => 'Test User',
				],
				'unique-id-4',
				false,
			],
			'user with additional fields'       => [
				[
					'user_email'   => 'john@example.com',
					'display_name' => 'John Smith',
					'first_name'   => 'John',
					'last_name'    => 'Smith',
					'nickname'     => 'Johnny',
				],
				'unique-id-5',
				false,
			],
			'empty data array'                  => [
				[],
				'unique-id-6',
				true,
			],
			'empty unique identifier'           => [
				[
					'user_email'   => 'test@example.com',
					'display_name' => 'Test User',
				],
				'',
				true,
			],
		];
	}

	/**
	 * Test that create_or_get_contributor returns existing user when called with same unique identifier.
	 */
	public function test_create_or_get_contributor_returns_existing_user() {
		$data              = [
			'user_email'   => 'existing@example.com',
			'display_name' => 'Existing User',
		];
		$unique_identifier = 'existing-user-123';

		// Create user first time
		$user1 = GuestContributorsHelper::create_or_get_contributor( $data, $unique_identifier );
		$this->assertInstanceOf( \WP_User::class, $user1 );
		$this->assertContains( Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME, $user1->roles );

		// Call again with same unique identifier
		$user2 = GuestContributorsHelper::create_or_get_contributor( $data, $unique_identifier );
		$this->assertInstanceOf( \WP_User::class, $user2 );

		// Should be the same user
		$this->assertEquals( $user1->ID, $user2->ID );
		$this->assertEquals( $user1->user_email, $user2->user_email );
		$this->assertEquals( $user1->display_name, $user2->display_name );
	}

	/**
	 * Test that create_or_get_contributor creates new user when called with different unique identifier.
	 */
	public function test_create_or_get_contributor_creates_new_user_with_different_identifier() {
		$data = [
			'user_email'   => 'same@example.com',
			'display_name' => 'Same User',
		];

		// Create first user
		$user1 = GuestContributorsHelper::create_or_get_contributor( $data, 'unique-id-1' );
		$this->assertInstanceOf( \WP_User::class, $user1 );

		// Create second user with same data but different identifier
		$user2 = GuestContributorsHelper::create_or_get_contributor( $data, 'unique-id-2' );
		$this->assertInstanceOf( \WP_User::class, $user2 );

		// Should be the different user.
		$this->assertNotEquals( $user1->ID, $user2->ID );
		$this->assertNotEquals( $user1->user_email, $user2->user_email ); // Email should be different due to uniqueness
		$this->assertEquals( $user1->display_name, $user2->display_name );
	}

	/**
	 * Test that create_or_get_contributor properly sets the guest contributor role.
	 */
	public function test_create_or_get_contributor_sets_correct_role() {
		$data              = [
			'user_email'   => 'role-test@example.com',
			'display_name' => 'Role Test User',
		];
		$unique_identifier = 'role-test-123';

		$user = GuestContributorsHelper::create_or_get_contributor( $data, $unique_identifier );

		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertContains( Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME, $user->roles );
		$this->assertNotContains( 'subscriber', $user->roles );
		$this->assertNotContains( 'author', $user->roles );
		$this->assertNotContains( 'editor', $user->roles );
	}

	/**
	 * Test that create_or_get_contributor handles missing required fields gracefully.
	 */
	public function test_create_or_get_contributor_with_minimal_data() {
		// Test with only display_name
		$data              = [
			'display_name' => 'Minimal User',
		];
		$unique_identifier = 'minimal-user-123';

		$user = GuestContributorsHelper::create_or_get_contributor( $data, $unique_identifier );

		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertEquals( 'Minimal User', $user->display_name );
		$this->assertContains( Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME, $user->roles );
		$this->assertNotEmpty( $user->user_email );
		$this->assertNotEmpty( $user->user_login );
		$this->assertNotEmpty( $user->user_nicename );
	}

	/**
	 * Test that create_or_get_contributor preserves existing role when user already exists.
	 */
	public function test_create_or_get_contributor_preserves_existing_role() {
		$data              = [
			'user_email'   => 'preserve-role@example.com',
			'display_name' => 'Preserve Role User',
		];
		$unique_identifier = 'preserve-role-123';

		// Create user first time
		$user1 = GuestContributorsHelper::create_or_get_contributor( $data, $unique_identifier );
		$this->assertContains( Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME, $user1->roles );

		// Add another role to the user
		$user1->add_role( 'subscriber' );
		$this->assertContains( 'subscriber', $user1->roles );

		// Call create_or_get_contributor again
		$user2 = GuestContributorsHelper::create_or_get_contributor( $data, $unique_identifier );

		// Should still have both roles
		$this->assertContains( Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME, $user2->roles );
		$this->assertContains( 'subscriber', $user2->roles );
	}

	/**
	 * Test that create_or_get_contributor handles special characters in display name.
	 */
	public function test_create_or_get_contributor_with_special_characters() {
		$data              = [
			'user_email'   => 'special@example.com',
			'display_name' => 'José María O\'Connor-Smith',
		];
		$unique_identifier = 'special-chars-123';

		$user = GuestContributorsHelper::create_or_get_contributor( $data, $unique_identifier );

		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertEquals( 'José María O\'Connor-Smith', $user->display_name );
		$this->assertContains( Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME, $user->roles );
	}

	/**
	 * Test that create_or_get_contributor handles very long display names.
	 */
	public function test_create_or_get_contributor_with_long_display_name() {
		$long_name         = str_repeat( 'A', 250 ); // Maximum allowed length
		$data              = [
			'user_email'   => 'long-name@example.com',
			'display_name' => $long_name,
		];
		$unique_identifier = 'long-name-123';

		$user = GuestContributorsHelper::create_or_get_contributor( $data, $unique_identifier );

		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertEquals( $long_name, $user->display_name );
		$this->assertContains( Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME, $user->roles );
	}

	/**
	 * Test that create_or_get_contributor handles whitespace in data.
	 */
	public function test_create_or_get_contributor_with_whitespace() {
		$data              = [
			'user_email'   => '  whitespace@example.com  ',
			'display_name' => '  Whitespace User  ',
			'first_name'   => '  John  ',
			'last_name'    => '  Smith  ',
		];
		$unique_identifier = 'whitespace-123';

		$user = GuestContributorsHelper::create_or_get_contributor( $data, $unique_identifier );

		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertEquals( 'whitespace@example.com', $user->user_email );
		$this->assertEquals( 'Whitespace User', $user->display_name );
		$this->assertEquals( 'John', $user->first_name );
		$this->assertEquals( 'Smith', $user->last_name );
		$this->assertContains( Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME, $user->roles );
	}
}
