<?php

namespace Newspack\MigrationTools\Logic;

use Newspack\Guest_Contributor_Role;
use CoAuthors_Plus;
use WP_Error;
use WP_Role;

class GuestContributorsHelper {

	const ERROR_ATTEMPTS        = 'Might be in an infinite loop.';
	const ERROR_CREATE_USER     = 'Could not create user.';
	const ERROR_DISPLAY_NAME    = 'Display Name must be between 1 and 250 characters.';
	const ERROR_EMAIL_DOMAIN    = 'Guest_Contributor_Role::get_dummy_email_domain() is not callable.';
	const ERROR_EXISTING_USERS  = 'Existing user(s) found. Use $force = true to skip this check.';
	const ERROR_NEWSPACK_PLUGIN = "Newspack Plugin's Guest Contributors feature is required to use this function.";
	const ERROR_SANITIZE_INPUT  = 'Display name is (or sanitization created) a blank string.';
	const ERROR_USER_NICENAME   = 'User nicename can not be blank.';

	/**
	 * Validates whether Newspack Plugin's Guest Contributors feature is active.
	 *
	 * @return bool Is role active.
	 */
	public static function validate_newspack_plugin(): bool {

		// Const must be defined and registered.
		// @ todo: test get_role() before after admin_init? is this check neccessary?
		$role_const = '\Newspack\Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME';
		if ( ! defined( $role_const ) || ! \get_role( constant( $role_const ) ) instanceof \WP_Role ) {
			return false;
		}

		return true;
	}

	/**
	 * Gets Guest Contributor(s) by display name.
	 * 
	 * Display name string matching is exact (case senstive). 
	 * Multiple results may be returned.
	 *
	 * @param string $display_name Display name to find.
	 * @return array|\WP_Error Array of user ID(s) or WP_Error.
	 */
	public static function get_by_display_name( $display_name ): array|\WP_Error {
		
		if ( ! self::validate_newspack_plugin() ) {
			return new WP_Error( 'ERROR_NEWSPACK_PLUGIN', self::ERROR_NEWSPACK_PLUGIN );
		}

		// Preform initial search based on display name and role.
		// Note: Initial sql match is case-insensitive, and also if display name starts/ends with "*"
		// like " ** Special Person ** " then sql will also wildcard match.
		// To fix both these issues, exact match will be performed in foreach after this query.
		$get_users = get_users(
			array(
				'search'         => $display_name, 
				'search_columns' => array( 'display_name' ),
				'role'           => Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME,
				'fields'         => array( 'ID', 'display_name' ),
				'orderby'        => 'ID',
			) 
		);
		
		// Filter results on exact match of display name, return just the ID(s) of the remaining objects.
		return array_map(
			fn( $user ) => $user->ID, 
			array_filter( $get_users, fn( $user ) => $user->display_name === $display_name )
		);
	}

	/**
	 * Create a Guest Contributor by Display Name.
	 *
	 * @param string $display_name The Display Name of the new user.
	 * @param bool   $force        Force the creation even if existing user(s) found.
	 * @return int|\WP_Error  Inserted user ID or WP_Error.
	 */
	public static function create_by_display_name( $display_name, $force = false ): int|\WP_Error {
		
		if ( ! self::validate_newspack_plugin() ) {
			return new WP_Error( 'ERROR_NEWSPACK_PLUGIN', self::ERROR_NEWSPACK_PLUGIN );
		}

		$display_name = trim( $display_name );

		// Check for core bug when display name is > 250: https://core.trac.wordpress.org/ticket/53109
		if ( empty( $display_name ) || mb_strlen( $display_name ) > 250 ) {
			return new WP_Error( 'ERROR_DISPLAY_NAME', self::ERROR_DISPLAY_NAME );
		}
		
		// If we're not forcing user creation, check for existing user(s) - could be multiple.
		if ( ! $force ) {
			$existing = self::get_by_display_name( $display_name );
			// return if error or not empty (array has value(s)).
			if ( is_wp_error( $existing ) ) {
				return $existing;
			}
			if ( ! empty( $existing ) ) {
				return new WP_Error( 'ERROR_EXISTING_USERS', self::ERROR_EXISTING_USERS );
			}
		}

		// New user data.
		$userdata = [
			'display_name'  => $display_name,
			'nickname'      => $display_name, // set value so it doesn't get set to user_login.
			'role'          => Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME,
			'user_email'    => self::generate_email( $display_name ),
			'user_login'    => self::generate_username( $display_name ),
			'user_nicename' => self::sanitize_for_db( $display_name, 50 ),
			'user_pass'     => wp_generate_password(), // generate else wp will write to debug.log.
		];

		// Pre-insert checks.

		if ( is_wp_error( $userdata['user_email'] ) ) {
			return new WP_Error( 'ERROR_GENERATE_EMAIL', wp_json_encode( $userdata['user_email'] ) );
		}

		if ( is_wp_error( $userdata['user_login'] ) ) {
			return new WP_Error( 'ERROR_GENERATE_USERNAME', wp_json_encode( $userdata['user_login'] ) );
		}

		if ( empty( $userdata['user_nicename'] ) ) {
			// Set user_nicename ourselves for better security and nicer urls otherwise it will be created from user_login.
			// If duplicate already in db, WordPress will add -2, -3, etc.
			// this can happen if sanitization produced empty string. Ex: $display_name = "&nbsp; <div>"
			return new WP_Error( 'ERROR_USER_NICENAME', self::ERROR_USER_NICENAME );
		}

		// Insert.
		$user_id = wp_insert_user( $userdata );

		// Fail on any errors.
		if ( is_wp_error( $user_id ) ) {
			return new WP_Error( 'ERROR_INSERT_USER', wp_json_encode( $user_id ) );
		}
		// Fail if wp_insert_user didn't return a positive int (return of 0 can happen on other failures...)
		// core bug that results in 0 integer value: https://core.trac.wordpress.org/ticket/53109
		if ( ! is_int( $user_id ) || ! ( $user_id > 0 ) ) {
			return new WP_Error( 'ERROR_INSERT_USER_ID', 'returned non-positive integer: ' . wp_json_encode( $user_id ) );
		}

		return $user_id;
	}

	/**
	 * Sanitize display name for use in a database the same way WordPress does it.
	 *
	 * @param string   $display_name The user display name.
	 * @param int|null $length The maximum length of the sanitized string. Defaults to null (no limit).
	 * @return string The sanitized string.
	 */
	public static function sanitize_for_db( $display_name, $length = null ) {
		return mb_substr( \sanitize_title( \sanitize_user( $display_name, true ) ), 0, $length );
	}

	/**
	 * Generate a unique dummy email address with a random suffix.
	 * 
	 * @param string $display_name The user display name.
	 * @return string|\WP_Error Example: ron-chambers-12345@example.com
	 */
	public static function generate_email( $display_name ): string|\WP_Error {
		
		if ( ! is_callable( 'Guest_Contributor_Role', 'get_dummy_email_domain' ) ) {
			return new WP_Error( 'ERROR_EMAIL_DOMAIN', self::ERROR_EMAIL_DOMAIN );
		}

		// sanitize input.
		$sanitized_display_name = self::sanitize_for_db( $display_name );
		if ( empty( $sanitized_display_name ) ) {
			return new WP_Error( 'ERROR_SANITIZE_INPUT', self::ERROR_SANITIZE_INPUT );
		}

		// hard code email column char length from db.
		$db_max_chars = 100; 

		// initial dummy email suffix.
		$email_suffix = '@' . Guest_Contributor_Role::get_dummy_email_domain();

		// stop infinite loops.
		$attempts = 0;

		do {

			if ( ++$attempts > 9999 ) {
				// stop...this could cause an ininite loop.
				return new WP_Error( 'ERROR_ATTEMPTS', self::ERROR_ATTEMPTS );
			}

			// try a different random suffix on each loop
			$suffix = '-' . \wp_rand( 11111, 99999 ) . $email_suffix;

			// make room if needed for the random suffix, then add it to the string.
			$email_out = mb_substr( $sanitized_display_name, 0, $db_max_chars - mb_strlen( $suffix ) ) . $suffix;

		} while ( \email_exists( $email_out ) );

		return $email_out;
	}

	/**
	 * Generate a unique username (user_login) with a random suffix.
	 *
	 * @param string $display_name The user display name.
	 * @return string|\WP_Error Example: ron-chambers-12345
	 */ 
	public static function generate_username( $display_name ): string|\WP_Error {

		// sanitize in the same way wp_insert_user would.
		$sanitized_display_name = self::sanitize_for_db( $display_name );
		if ( empty( $sanitized_display_name ) ) {
			return new WP_Error( 'ERROR_SANITIZE_INPUT', self::ERROR_SANITIZE_INPUT );
		}

		// hard code char length from db.
		$db_max_chars = 60; 

		// stop infinite loops.
		$attempts = 0;

		do {

			if ( ++$attempts > 9999 ) {
				// stop...this could cause an ininite loop.
				return new WP_Error( 'ERROR_ATTEMPTS', self::ERROR_ATTEMPTS );
			}

			// try a different random suffix on each loop
			$suffix = '-' . \wp_rand( 11111, 99999 );

			// make room in the username if needed for the random suffix, then add it to the string.
			$username_out = mb_substr( $sanitized_display_name, 0, $db_max_chars - mb_strlen( $suffix ) ) . $suffix;

		} while ( \username_exists( $username_out ) );

		return $username_out;
	}

	/**
	 * Assigns Authors to the Post.
	 *
	 * @param array $user_ids WP User IDs.
	 * @param int   $post_id  Post ID.
	 * @param bool  $append   Append to existing authors.
	 */
	public static function assign_authors_to_post( array $user_ids, int $post_id, bool $append = false ): bool|\WP_Error {

		global $coauthors_plus;

		// CAP Plugin is required.
		if ( ! is_plugin_active( 'co-authors-plus/co-authors-plus.php' ) ) {
			$this->logger->error( 'ERROR_CAP_PLUGIN_NOT_FOUND', 'Co-Authors Plus plugin not found. Install and activate it before using this command.' );
			exit();
		}
		
		if ( ! $coauthors_plus instanceof CoAuthors_Plus ) {
			return new WP_Error( 'ERROR_COAUTHORS_PLUS_PLUGIN_IS_REQUIRED', 'CoAuthors Plus plugin is required to use this function.' );
		}

		// $what_is_return_value = $coauthors_plus->add_coauthors( $post_id, $user_ids, $append, 'id' );

		// CoAuhorsPlus helpers uses this to validate success: $valid = $this->validate_authors_for_post( $post_id, $authors );
		// could I just do a select on taxonomy tables to grap the user ids on the post id?
		return false; // $what_is_return_value; // ????
	}
}
