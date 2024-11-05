<?php

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\MigrationTools\Logic\UsersHelper;
use WP_UnitTestCase;

class TestUsersHelper extends WP_UnitTestCase {

	private int $peter_parker_id;

	protected function setUp(): void {
		parent::setUp();
		add_filter( 'newspack_migration_tools_enable_cli_log', '__return_true' ); //TODO: Remove this line

		$this->peter_parker_id = $this->factory()->user->create(
			[
				'display_name' => 'Peter Parker',
				'user_login'    => 'spidey',
				'user_password' => wp_generate_password(),
				'role'          => 'author',
			]
		);
	}

	public function test_unused_email() {
		$peter = get_user_by( 'ID', $this->peter_parker_id );

		$unused_email = UsersHelper::get_unused_fake_email( $peter->user_email );
		$this->assertNotEquals( $peter->user_email, $unused_email );
		// Check that it is still an email that works.
		$this->assertNotFalse( is_email( $unused_email ) );
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

	public function test_create_user() {
		$peter = get_user_by( 'ID', $this->peter_parker_id );

		$copycat = UsersHelper::create_or_get_user( [
			'user_login'    => $peter->user_login,
			'user_email'    => $peter->user_email,
			'user_nicename' => $peter->user_nicename,
		]);
		$trut = '';
	}
}
