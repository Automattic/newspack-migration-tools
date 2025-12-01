<?php

namespace Newspack\MigrationTools\Tests\Command;

use Newspack\MigrationTools\Command\UsersMigrator;
use WP_UnitTestCase;

/**
 * Class TestUsersMigrator
 *
 * @package newspack-migration-tools
 */
class TestUsersMigrator extends WP_UnitTestCase {

	/**
	 * Admin user ID for reassignment.
	 *
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * Store the original CAP delete user action priority.
	 *
	 * @var int|false
	 */
	private $cap_delete_user_action_priority;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		// Disable logging for tests
		add_filter( 'newspack_migration_tools_log_file_logger_disable', '__return_true' );
		add_filter( 'newspack_migration_tools_log_clilog_disable', '__return_true' );

		// Create an admin user for reassignment
		$this->admin_user_id = self::factory()->user->create(
			[
				'role' => 'administrator',
			]
		);

		// Store the original CAP delete user action priority and remove the action
		$this->store_cap_delete_user_action_priority();
		$this->remove_cap_delete_user_action();
	}

	/**
	 * Store the original CAP delete user action priority.
	 * This is needed to restore it after temporarily removing it.
	 */
	private function store_cap_delete_user_action_priority(): void {
		global $wp_filter;
		if ( ! isset( $wp_filter['delete_user'] ) ) {
			$this->cap_delete_user_action_priority = false;
			return;
		}

		// Find the CoAuthors Plus delete_user_action callback
		foreach ( $wp_filter['delete_user']->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) && is_object( $callback['function'][0] ) && get_class( $callback['function'][0] ) === 'CoAuthors_Plus' ) {
					$this->cap_delete_user_action_priority = $priority;
					return;
				}
			}
		}

		$this->cap_delete_user_action_priority = false;
	}

	/**
	 * Temporarily remove the CAP delete user action.
	 * This is a workaround for https://github.com/Automattic/Co-Authors-Plus/issues/1096
	 */
	private function remove_cap_delete_user_action(): void {
		if ( false === $this->cap_delete_user_action_priority ) {
			return;
		}

		global $coauthors_plus;
		if ( ! isset( $coauthors_plus ) ) {
			return;
		}

		remove_action( 'delete_user', [ $coauthors_plus, 'delete_user_action' ], $this->cap_delete_user_action_priority );
	}

	/**
	 * Restore the CAP delete user action.
	 */
	private function restore_cap_delete_user_action(): void {
		if ( false === $this->cap_delete_user_action_priority ) {
			return;
		}

		global $coauthors_plus;
		if ( ! isset( $coauthors_plus ) ) {
			return;
		}

		add_action( 'delete_user', [ $coauthors_plus, 'delete_user_action' ], $this->cap_delete_user_action_priority );
	}

	/**
	 * Test deleting users with invalid role.
	 */
	public function test_delete_users_invalid_role(): void {
		// Create a test user with 'author' role
		$user_id = self::factory()->user->create(
			[
				'role' => 'author',
			]
		);

		// Try to delete users with an invalid role
		UsersMigrator::cmd_delete_users_by_role(
			[],
			[
				'roles'       => 'nonexistent-role',
				'reassign-to' => $this->admin_user_id,
			]
		);

		// Assert that no users were deleted (author still exists)
		$authors = get_users( [ 'role' => 'author' ] );
		$this->assertEquals( 1, count( $authors ) );

		// Cleanup
		wp_delete_user( $user_id, $this->admin_user_id );
	}

	/**
	 * Test dry run mode doesn't delete users.
	 */
	public function test_delete_users_dry_run(): void {
		// Create test users
		$subscriber_ids = self::factory()->user->create_many( 3, [ 'role' => 'subscriber' ] );
		$author_ids     = self::factory()->user->create_many( 2, [ 'role' => 'author' ] );

		// Run delete command in dry-run mode
		UsersMigrator::cmd_delete_users_by_role(
			[],
			[
				'roles'       => 'subscriber,author',
				'reassign-to' => $this->admin_user_id,
				'dry-run'     => true,
			]
		);

		// Assert that no users were deleted
		$subscribers = get_users( [ 'role' => 'subscriber' ] );
		$authors     = get_users( [ 'role' => 'author' ] );
		$this->assertEquals( 3, count( $subscribers ) );
		$this->assertEquals( 2, count( $authors ) );

		// Cleanup
		foreach ( array_merge( $subscriber_ids, $author_ids ) as $user_id ) {
			wp_delete_user( $user_id, $this->admin_user_id );
		}
	}

	/**
	 * Test actual deletion of users with reassignment.
	 */
	public function test_delete_users_with_reassignment(): void {
		// Create test users
		$subscriber_ids = self::factory()->user->create_many( 3, [ 'role' => 'subscriber' ] );
		$author_ids     = self::factory()->user->create_many( 2, [ 'role' => 'author' ] );
		$editor_id      = self::factory()->user->create( [ 'role' => 'editor' ] );

		// Create some test posts for authors
		foreach ( $author_ids as $author_id ) {
			self::factory()->post->create(
				[
					'post_author' => $author_id,
					'post_type'   => 'post',
					'post_status' => 'publish',
				]
			);
		}

		// Delete subscribers and authors with reassignment
		UsersMigrator::cmd_delete_users_by_role(
			[],
			[
				'roles'       => 'subscriber,author',
				'reassign-to' => $this->admin_user_id,
			]
		);

		// Assert that subscribers and authors were deleted but editor remains
		$subscribers = get_users( [ 'role' => 'subscriber' ] );
		$authors     = get_users( [ 'role' => 'author' ] );
		$editors     = get_users( [ 'role' => 'editor' ] );

		$this->assertEquals( 0, count( $subscribers ) );
		$this->assertEquals( 0, count( $authors ) );
		$this->assertEquals( 1, count( $editors ) );

		// Verify posts were reassigned
		$admin_posts = get_posts(
			[
				'author'         => $this->admin_user_id,
				'post_type'      => 'post',
				'posts_per_page' => -1,
			]
		);
		$this->assertEquals( count( $author_ids ), count( $admin_posts ) );
	}

	/**
	 * Test deleting users when none exist for the specified roles.
	 */
	public function test_delete_users_none_exist(): void {
		// Create a user with editor role
		$editor_id = self::factory()->user->create( [ 'role' => 'editor' ] );

		// Try to delete subscribers (which don't exist)
		UsersMigrator::cmd_delete_users_by_role(
			[],
			[
				'roles'       => 'subscriber',
				'reassign-to' => $this->admin_user_id,
			]
		);

		// Assert that editor still exists
		$editors = get_users( [ 'role' => 'editor' ] );
		$this->assertEquals( 1, count( $editors ) );
	}

	/**
	 * Test deleting users with exclude-roles parameter.
	 */
	public function test_delete_users_with_exclude_roles(): void {
		// Create test users with different roles
		$subscriber_ids = self::factory()->user->create_many( 3, [ 'role' => 'subscriber' ] );
		$author_ids     = self::factory()->user->create_many( 2, [ 'role' => 'author' ] );
		$editor_ids     = self::factory()->user->create_many( 2, [ 'role' => 'editor' ] );
		$admin_ids      = self::factory()->user->create_many( 2, [ 'role' => 'administrator' ] );

		// Create some test posts for authors
		$post_ids = [];
		foreach ( $author_ids as $author_id ) {
			$post_ids[] = self::factory()->post->create(
				[
					'post_author' => $author_id,
					'post_type'   => 'post',
					'post_status' => 'publish',
				]
			);
		}

		// Delete all users except editors and administrators
		UsersMigrator::cmd_delete_users_by_role(
			[],
			[
				'exclude-roles' => 'editor,administrator',
				'reassign-to'   => $this->admin_user_id,
			]
		);

		// Assert that subscribers and authors were deleted
		$subscribers = get_users( [ 'role' => 'subscriber' ] );
		$authors     = get_users( [ 'role' => 'author' ] );
		$this->assertEquals( 0, count( $subscribers ) );
		$this->assertEquals( 0, count( $authors ) );

		// Assert that editors and administrators were preserved
		$editors = get_users( [ 'role' => 'editor' ] );
		$admins  = get_users(
			[
				'role'    => 'administrator',
				'exclude' => [ 1 ], // Exclude the default WordPress admin user
			]
		);
		$this->assertEquals( 2, count( $editors ), 'Wrong number of editors' );
		$this->assertEquals(
			3,
			count( $admins ),
			sprintf(
				'Wrong number of administrators (excluding default admin). Found: %s',
				wp_json_encode(
					array_map(
						function( $user ) {
							return [
								'ID'         => $user->ID,
								'user_login' => $user->user_login,
							];
						},
						$admins
					)
				)
			)
		);

		// Debug information
		$admin_info = array_map(
			function( $user ) {
				return [
					'ID'         => $user->ID,
					'user_login' => $user->user_login,
				];
			},
			$admins
		);
		$this->assertEquals( 3, count( $admins ), sprintf( 'Wrong number of administrators. Found: %s', wp_json_encode( $admin_info ) ) );

		// Verify posts were reassigned
		$admin_posts = get_posts(
			[
				'author'         => $this->admin_user_id,
				'post_type'      => 'post',
				'posts_per_page' => -1,
			]
		);
		$this->assertEquals( count( $post_ids ), count( $admin_posts ) );
	}

	/**
	 * Test that roles and exclude-roles cannot be used together.
	 */
	public function test_roles_and_exclude_roles_mutually_exclusive(): void {
		// Create a test user
		$user_id = self::factory()->user->create( [ 'role' => 'author' ] );

		// Try to use both parameters
		UsersMigrator::cmd_delete_users_by_role(
			[],
			[
				'roles'         => 'author',
				'exclude-roles' => 'administrator',
				'reassign-to'   => $this->admin_user_id,
			]
		);

		// Assert that no users were deleted
		$authors = get_users( [ 'role' => 'author' ] );
		$this->assertEquals( 1, count( $authors ) );
	}

	/**
	 * Test that at least one of roles or exclude-roles must be provided.
	 */
	public function test_roles_or_exclude_roles_required(): void {
		// Create a test user
		$user_id = self::factory()->user->create( [ 'role' => 'author' ] );

		// Try to run command without either parameter
		UsersMigrator::cmd_delete_users_by_role(
			[],
			[]
		);

		// Assert that no users were deleted
		$authors = get_users( [ 'role' => 'author' ] );
		$this->assertEquals( 1, count( $authors ) );
	}
}
