<?php

namespace Newspack\MigrationTools\Logic;

use Newspack\MigrationTools\Util\Log\CliLog;
use WP_User;

class CoAuthorDuplicateSlugTest extends CoAuthorsPlusHelper {

	protected $authors_without_unique_slugs = array(
		array(
			'username' => 'Sanding Down',
			'password' => '',
			'email'    => 'sanding@down.com',
		),
		array(
			'username' => 'Quarantine Cuarentena',
			'password' => '',
			'email'    => 'wear.a.mask@everywhere.com',
		),
		array(
			'username' => 'wanda_vision_',
			'password' => '',
			'email'    => 'w.maxi@disney.com',
		),
	);

	protected $authors_with_unique_slugs = array(
		array(
			'username' => 'Monkey D. Luffy',
			'password' => '',
			'email'    => 'luffy@onepiece.com',
		),
		array(
			'username' => 'Roronoa Zoro',
			'password' => '',
			'email'    => 'zoro@onepiece.com',
		),
		array(
			'username' => 'Vinsmoke Sanji',
			'password' => '',
			'email'    => 'sanji@onepiece.com',
		),
	);

	protected $users_without_unique_slugs = array(
		array(
			'username' => 'Eren Yeager',
			'password' => '',
			'email'    => 'eren@aot.com',
		),
		array(
			'username' => 'Mikasa Ackerman',
			'password' => '',
			'email'    => 'mikasa@aot.com',
		),
		array(
			'username' => 'Armin Arlet',
			'password' => '',
			'email'    => 'armin@aot.com',
		),
	);

	protected $users_with_unique_slugs = array(
		array(
			'username' => 'Takumi Fujiwara',
			'password' => '',
			'email'    => 'a86@initiald.com',
		),
		array(
			'username' => 'Itsuki Takeuchi',
			'password' => '',
			'email'    => 'itsuki@initiald.com',
		),
		array(
			'username' => 'Satou Mako',
			'password' => '',
			'email'    => 'mako@initiald.com',
		),
	);

	public function __construct() {
		parent::__construct();
		$this->cli_logger = CliLog::get_logger( 'CAP-duplicate-slug-test' );
	}


	/**
	 * @return void
	 */
	public function run() {
		$this->cli_logger->info( 'Setting up test data' );
		$this->setup_test_data();
	}

	/**
	 * Creates necessary data for 4 different scenarios:
	 * 1. Authors who have unique slugs. This is to test that the migration will not create a slug
	 * which already matches someone else's slug.
	 * 2. Authors without unique slugs. This is the main reason for the script. These slugs
	 * should be updated once the script is finished.
	 * 3. Users with unique slugs. This is to make sure that only Authors are affected by the
	 * migration script.
	 * 4. Users without unique slugs. Even though they don't have unique slugs, these records should
	 * not be touched because they are not Authors.
	 *
	 * @return void
	 */
	protected function setup_test_data() {
		foreach ( $this->authors_with_unique_slugs as $author ) {
			$this->cli_logger->info( "Creating author! u:{$author['username']} e:{$author['email']}" );

			$author_id = wp_create_user( $author['username'], $author['password'], $author['email'] );
			$this->cli_logger->info( "User ID: {$author_id}" );
			$this->cli_logger->info( 'Setting Author Role.' );
			( new WP_User( $author_id ) )->set_role( 'author' );
		}

		foreach ( $this->authors_without_unique_slugs as $author ) {
			$this->cli_logger->info( "Creating author! u:{$author['username']} e:{$author['email']}" );

			$author_id = wp_create_user( $author['username'], $author['password'], $author['email'] );
			$this->cli_logger->info( "User ID: {$author_id}" );
			$this->cli_logger->info( 'Setting Author Role.' );
			( new WP_User( $author_id ) )->set_role( 'author' );

			$this->create_guest_author_for_test(
				array(
					'display_name' => $author['username'],
					'user_email'   => $author['email'],
				)
			);
		}

		foreach ( $this->users_with_unique_slugs as $user ) {
			$this->cli_logger->info( "Creating user! u:{$user['username']} e:{$user['email']}" );

			$user_id = wp_create_user( $user['username'], $user['password'], $user['email'] );
			$this->cli_logger->info( "User ID: {$user_id}" );
		}

		foreach ( $this->users_without_unique_slugs as $user ) {
			$this->cli_logger->info( "Creating user! u:{$user['username']} e:{$user['email']}" );

			$user_id = wp_create_user( $user['username'], $user['password'], $user['email'] );
			$this->cli_logger->info( "User ID: {$user_id}" );

			$this->create_guest_author_for_test(
				array(
					'display_name' => $user['username'],
					'user_email'   => $user['email'],
				)
			);
		}
	}

	/**
	 * Creates Guest Authors
	 *
	 * @param array $args Array with the following keys: display_name, user_email.
	 *
	 * @return void
	 */
	protected function create_guest_author_for_test( array $args ) {
		$this->cli_logger->info( "Creating Guest Author Record for {$args['user_email']}" );
		// First Create Post
		$post_id = wp_insert_post(
			array(
				// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				'post_date'      => date( 'Y-m-d H:i:s', time() ),
				'post_title'     => $args['display_name'],
				'post_status'    => 'publish',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'post_name'      => 'cap-test-' . sanitize_title( $args['display_name'] ),
				// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				'post_modified'  => date( 'Y-m-d H:i:s', time() ),
				'post_type'      => 'guest-author',
			)
		);

		// Then Create PostMeta
		add_post_meta( $post_id, 'cap-user_login', sanitize_title( $args['display_name'] ) );
		add_post_meta( $post_id, 'cap-display_name', $args['display_name'] );
	}
}
