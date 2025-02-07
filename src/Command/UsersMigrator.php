<?php
/**
 * UsersMigrator class.
 *
 * @package newspack-migration-tools
 */

namespace Newspack\MigrationTools\Command;

use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\Log\MultiLog;

/**
 * Users Migrator command class.
 */
class UsersMigrator implements WpCliCommandInterface {

	/**
	 * {@inheritDoc}
	 */
	public static function get_cli_commands(): array {
		return [
			[
				'newspack-migration-tools delete-users-by-role',
				[ __CLASS__, 'cmd_delete_users_by_role' ],
				[
					'shortdesc' => 'Delete users by their roles.',
					'synopsis'  => array(
						array(
							'type'        => 'assoc',
							'name'        => 'roles',
							'description' => 'Comma-separated list of roles to delete users from (e.g. subscriber,author). Cannot be used with exclude-roles.',
							'optional'    => true,
							'repeating'   => false,
						),
						array(
							'type'        => 'assoc',
							'name'        => 'exclude-roles',
							'description' => 'Comma-separated list of roles to exclude from deletion (e.g. administrator,editor). Cannot be used with roles.',
							'optional'    => true,
							'repeating'   => false,
						),
						array(
							'type'        => 'assoc',
							'name'        => 'reassign-to',
							'description' => 'User ID to reassign posts to before deleting users. If not provided, posts will be deleted.',
							'optional'    => true,
							'repeating'   => false,
						),
						array(
							'type'        => 'flag',
							'name'        => 'dry-run',
							'description' => 'If set, only logs the users that would be deleted without actually deleting them.',
							'optional'    => true,
							'repeating'   => false,
						),
					),
				],
			],
		];
	}

	/**
	 * Delete users by role command.
	 *
	 * @param array $pos_args  Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public static function cmd_delete_users_by_role( array $pos_args, array $assoc_args ): void {
		// Set log slug: Class name without namespace plus function.
		$log_slug = str_replace( __NAMESPACE__ . '\\', '', __CLASS__ ) . '_' . __FUNCTION__;

		$logger = MultiLog::get_logger(
			'multi-' . $log_slug,
			[
				CliLog::get_logger( $log_slug ),
				FileLog::get_logger( $log_slug ),
			]
		);

		$logger->info( 'Starting CLI - Delete Users by Role...' );

		// Validate that only one of roles or exclude-roles is provided
		if ( isset( $assoc_args['roles'] ) && isset( $assoc_args['exclude-roles'] ) ) {
			$logger->error( 'Cannot use both roles and exclude-roles parameters at the same time.' );
			return;
		}

		if ( ! isset( $assoc_args['roles'] ) && ! isset( $assoc_args['exclude-roles'] ) ) {
			$logger->error( 'Must provide either roles or exclude-roles parameter.' );
			return;
		}

		$is_dry_run = isset( $assoc_args['dry-run'] );

		// Get all available roles
		$wp_roles  = wp_roles();
		$all_roles = array_keys( $wp_roles->get_names() );

		// Determine which roles to process
		$roles_to_process = [];
		if ( isset( $assoc_args['roles'] ) ) {
			// Get roles from the comma-separated list
			$roles_to_process = array_map( 'trim', explode( ',', $assoc_args['roles'] ) );

			// Validate roles
			foreach ( $roles_to_process as $role ) {
				if ( ! get_role( $role ) ) {
					$logger->error( sprintf( 'Invalid role: %s', $role ) );
					return;
				}
			}
		} else {
			// Using exclude-roles
			$exclude_roles = array_map( 'trim', explode( ',', $assoc_args['exclude-roles'] ) );

			// Validate excluded roles
			foreach ( $exclude_roles as $role ) {
				if ( ! get_role( $role ) ) {
					$logger->error( sprintf( 'Invalid excluded role: %s', $role ) );
					return;
				}
			}

			// Get all roles except excluded ones
			$roles_to_process = array_diff( $all_roles, $exclude_roles );
		}

		// Handle reassign-to parameter
		$reassign_to = null;
		if ( isset( $assoc_args['reassign-to'] ) ) {
			$reassign_to   = (int) $assoc_args['reassign-to'];
			$reassign_user = get_user_by( 'id', $reassign_to );
			if ( ! $reassign_user ) {
				$logger->error( sprintf( 'Invalid reassign-to user ID: %d', $reassign_to ) );
				return;
			}
		}

		// Group users by role and collect them
		$users_by_role = [];
		foreach ( $roles_to_process as $role ) {
			$users = get_users( [ 'role' => $role ] );
			if ( ! empty( $users ) ) {
				$users_by_role[ $role ] = $users;
			}
		}

		// If no users found in any role
		if ( empty( $users_by_role ) ) {
			$logger->info( sprintf( 'No users found with roles: %s', implode( ', ', $roles_to_process ) ) );
			return;
		}

		// Log and delete users by role
		foreach ( $users_by_role as $role => $users ) {
			$logger->info( sprintf( '=== Processing users with role: %s ===', $role ) );
			$logger->info( sprintf( 'Found %d users', count( $users ) ) );

			foreach ( $users as $user ) {
				$user_info = [
					'ID'           => $user->ID,
					'user_login'   => $user->user_login,
					'user_email'   => $user->user_email,
					'display_name' => $user->display_name,
					'roles'        => implode( ', ', $user->roles ),
				];

				if ( $is_dry_run ) {
					$logger->info( sprintf( 'Would delete user: %s', wp_json_encode( $user_info ) ) );
					continue;
				}

				$logger->info( sprintf( 'Deleting user: %s', wp_json_encode( $user_info ) ) );
				// If no reassignment is specified, force delete all posts
				if ( null === $reassign_to ) {
					// Get all posts by this user
					$posts = get_posts(
						[
							'author'         => $user->ID,
							'post_type'      => 'post',
							'posts_per_page' => -1,
						]
					);
					foreach ( $posts as $post ) {
						wp_delete_post( $post->ID, true );
					}
				}
				$result = wp_delete_user( $user->ID, $reassign_to );
				if ( $result ) {
					$logger->info( sprintf( 'Successfully deleted user ID: %d', $user->ID ) );
				} else {
					$logger->error( sprintf( 'Failed to delete user ID: %d', $user->ID ) );
				}
			}
		}

		$logger->info( 'Finished processing users.' );
	}
}
