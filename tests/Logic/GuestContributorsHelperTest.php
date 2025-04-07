<?php

namespace Newspack\MigrationTools\Tests\Logic;

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

	/*
	public function testCompleteGuestContributorFlow() {
		$unique_name = 'John ' . microtime() . ' ' . wp_rand( 11111, 99999 );
		
		// Initial get should return empty
		$result = GuestContributorsHelper::get_by_display_name( $unique_name );
		$this->assertMatchesRegularExpression( '/^$/', implode( ',', $result ) );
		
		// Create should succeed
		$result = GuestContributorsHelper::create_by_display_name( $unique_name );
		$this->assertMatchesRegularExpression( '/^\d+$/', $result );
		
		// Get should now return the ID
		$result = GuestContributorsHelper::get_by_display_name( $unique_name );
		$this->assertMatchesRegularExpression( '/^\d+$/', implode( ',', $result ) );
		
		// Create without force should fail
		$result = GuestContributorsHelper::create_by_display_name( $unique_name );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'ERROR_EXISTING_USERS', $result->get_error_code() );
		
		// Create with force should succeed
		$result = GuestContributorsHelper::create_by_display_name( $unique_name, [], true );
		$this->assertMatchesRegularExpression( '/^\d+$/', $result );
		
		// Get should now return multiple IDs
		$result = GuestContributorsHelper::get_by_display_name( $unique_name );
		$this->assertMatchesRegularExpression( '/^[\d,]+$/', implode( ',', $result ) );
	}
	*/

	/**
	 * Test that get_by_display_name works as expected.
	 * 
	 * @dataProvider data_provider_get_by_display_name
	 */
	/*
	public function test_get_by_display_name( $input, $expected ) {
		$result = GuestContributorsHelper::get_by_display_name( $input );
		if ( is_array( $result ) ) {
			$result = implode( ',', $result );
		}
		$this->assertMatchesRegularExpression( $expected, $result );
	}

	public function data_provider_get_by_display_name() {
		// type => input, expected (regex match).
		return [
			'empty string'      => [ '', '/^$/' ],
			'wildcard'          => [ '*', '/^$/' ],
			'non-existent name' => [ 'John ' . microtime() . ' ' . wp_rand( 11111, 99999 ), '/^$/' ],
		];
	}
	*/
}
