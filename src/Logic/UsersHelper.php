<?php

namespace Newspack\MigrationTools\Logic;

use Exception;
use InvalidArgumentException;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use WP_User;

class UsersHelper {

	/**
	 * Get a user by an array that contains either the ID, user_email, user_login or user_nicename.
	 *
	 * @param array $data Array with one (or more) of the following keys: 'ID', 'user_email', 'user_login', 'user_nicename'.
	 *
	 * @return WP_User|bool The user if found, false otherwise.
	 */
	public static function get_user( array $data ): WP_User|bool {
		$wp_user = false;
		if ( ! empty( $data['ID'] ) ) {
			$wp_user = get_user_by( 'ID', $data['ID'] );
		} elseif ( ! empty( $data['user_email'] ) ) {
			$wp_user = get_user_by( 'email', $data['user_email'] );
		} elseif ( ! empty( $data['user_login'] ) ) {
			$wp_user = get_user_by( 'login', $data['user_login'] );
		} elseif ( ! empty( $data['user_nicename'] ) ) {
			$wp_user = get_user_by( 'slug', $data['user_nicename'] );
		}

		return $wp_user ?? false;
	}

	/**
	 * Get a username that is not in use from a desired username.
	 *
	 * If the desired username is in use, a counter will be appended to it until an unused username is found.
	 *
	 * @param string $desired_username Desired username.
	 *
	 * @return string An unused username.
	 */
	public static function get_unused_username( string $desired_username ): string {
		$i = 0;
		while ( username_exists( $desired_username ) ) {
			$desired_username .= ( ++$i );
		}
		if ( $i > 0 ) {
			CliLog::get_logger( 'UsersHelper' )->debug( sprintf( 'Generated username: %s', $desired_username ) );
		}

		return $desired_username;
	}

	/**
	 * Get a nicename that is not in use from a desired nicename.
	 *
	 * If the desired nicename is in use, a counter will be appended to it until an unused nicename is found.
	 *
	 * @param string $desired_nicename Desired nicename.
	 *
	 * @return string An unused nicename.
	 */
	public static function get_unused_nicename( string $desired_nicename ): string {
		$i = 0;
		while ( self::nicename_exists( $desired_nicename ) ) {
			$desired_nicename .= ( ++$i );
		}
		if ( $i > 0 ) {
			CliLog::get_logger( 'UsersHelper' )->debug( sprintf( 'Generated nicename: %s', $desired_nicename ) );
		}

		return mb_substr( $desired_nicename, 0, 50 );
	}

	/**
	 * Get an email that is not in use from a desired (fake) email – don't use this for actual emails!
	 *
	 * The desired email will be prepended with a counter until an unused email is found.
	 *
	 * @param string $desired_email Desired email.
	 *
	 * @return string An unused email.
	 */
	public static function get_unused_fake_email( string $desired_email ): string {
		if ( strlen( $desired_email ) > 100 ) {
			// If the email is too long, we'll peel off a couple of characters from the beginning.
			$desired_email = substr( $desired_email, 4 );
			CliLog::get_logger( 'UsersHelper' )->debug( sprintf( 'Shortened email to under 100 chars: %s.', $desired_email ) );
		}

		$i = 0;
		while ( false !== get_user_by( 'email', $desired_email ) ) {
			$desired_email = ( ++$i ) . $desired_email; // Prepend.
		}
		if ( $i > 0 ) {
			CliLog::get_logger( 'UsersHelper' )->debug( sprintf( 'Generated fake email: %s.', $desired_email ) );
		}

		return $desired_email;
	}

	/**
	 * Check if a nicename is already in use.
	 *
	 * @param string $nicename The nicename to check.
	 *
	 * @return int The user ID if the nicename is in use, 0 otherwise.
	 */
	public static function nicename_exists( string $nicename ): int {
		global $wpdb;
		// We could also use get_user_by( 'slug', $nicename ) but this is probably faster.
		$user_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->users} WHERE user_nicename = %s LIMIT 1", // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
				$nicename
			)
		);

		return is_null( $user_id ) ? 0 : (int) $user_id;
	}

	/**
	 * Create or get a user from an array of data.
	 *
	 * The array is the same as wp_insert_user accepts. https://developer.wordpress.org/reference/functions/wp_insert_user
	 *
	 * Note that you *have* to provide one of the following fields: 'user_email', 'user_login', 'user_nicename', 'display_name'.
	 *
	 * The difference to wp_insert_user is that this method will try to create a user even if the data array is incomplete,
	 * so email, user login, and user nicename will be generated if not provided. Care is taken to avoid duplicates and user facing
	 * values that would leak emails or other sensitive information.
	 *
	 * @param array $data The data to create the user with. If the 'role' key is present, the user will be assigned that role.
	 *
	 * @throws Exception If the user could not be created.
	 * @throws InvalidArgumentException If the data array is empty or if the 'role' key is in the array and does not contain a valid role. .
	 */
	public static function create_or_get_user( array $data ): WP_User {
		if ( empty( $data ) ) {
			throw new InvalidArgumentException( 'Data array is empty. Refusing to create user from nothing.' );
		}

		// Trim all that we can (so strings).
		$data = array_map( fn( $value ) => is_string( $value ) ? trim( $value ) : $value, $data );

		$vital_fields = [ 'user_email', 'user_login', 'user_nicename', 'display_name' ];
		// We need at least one of the above fields to be present, so check that filtering on not empty does not produce an empty array.
		if ( empty( array_filter( $vital_fields, fn( $field ) => ! empty( $data[ $field ] ) ) ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new InvalidArgumentException( sprintf( 'Data array is missing one or more of the vital fields: %s.', implode( ', ', $vital_fields ) ) );
		}

		if ( ! empty( $data['role'] ) && null === get_role( $data['role'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new InvalidArgumentException( sprintf( 'Role "%s"does not exist.', $data['role'] ) );
		}

		$wp_user = self::get_user( $data );
		if ( $wp_user ) { // Great – we already have the user!
			if ( ! empty( $data['role'] ) ) {
				// If the role was passed in the data array – add it before returning.
				$wp_user->add_role( $data['role'] );
			}

			return $wp_user;
		}

		// For creating the user, these fields in particular need to be processed, so get the variables here.
		$user_email    = $data['user_email'] ?? '';
		$user_nicename = $data['user_nicename'] ?? '';
		$user_login    = $data['user_login'] ?? '';


		// If we don't have an email, we'll create an ugly unusable one so that we can create the user.
		if ( empty( $user_email ) ) {
			$email_domain = apply_filters( 'nmt_user_email_default_domain', 'example.com' );
			$user_email   = self::get_short_sha_from_array( $data ) . '@' . $email_domain;
		}

		// We insist on setting a nicename to avoid WP setting it to the email sans @.
		if ( empty( $user_nicename ) ) {
			$user_nicename = ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' );
			if ( ' ' === $user_nicename ) { // Yes, that is a whitespace and not an empty string.
				if ( ! empty( $data['display_name'] ) ) {
					$user_nicename = $data['display_name'];
				} elseif ( ! empty( $user_login ) && ! str_contains( $user_login, '@' ) ) {
					$user_nicename = $user_login;
				} else {
					$user_nicename = self::get_short_sha_from_array( $data );
				}
			}
		}

		// If we don't hava a user_login, we'll try to create one from the nicename, display_name or hash of the data array.
		if ( empty( $user_login ) ) {
			if ( ! empty( $user_nicename ) ) {
				$user_login = $user_nicename;
			} elseif ( ! empty( $data['display_name'] ) ) {
				$user_login = $data['display_name'];
			} else {
				// Hash the whole array to get an ugly, but unique username.
				$user_login = self::get_short_sha_from_array( $data );
			}
		}

		if ( empty( $data['user_pass'] ) ) {
			$data['user_pass'] = wp_generate_password( 42 );
		}

		// Now make sure all these values are unused.
		$data['user_email']    = self::get_unused_fake_email( $user_email );
		$data['user_nicename'] = self::get_unused_nicename( $user_nicename );
		$data['user_login']    = self::get_unused_username( $user_login );

		$user_id = wp_insert_user( $data );
		if ( is_wp_error( $user_id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( sprintf( 'Could not create user: %s', $user_id->get_error_message() ) );
		}
		$wp_user = get_user_by( 'ID', $user_id );

		$log_message      = sprintf( 'Created user with ID %d.', $user_id );
		$log_array        = array_intersect_key( $wp_user->to_array(), array_flip( [ 'user_login', 'user_email', 'user_nicename', 'display_name', 'role' ] ) );
		$log_array['url'] = get_author_posts_url( $user_id );
		CliLog::get_logger( 'users-helper' )->notice( $log_message, [ $log_array['url'] ] );
		FileLog::get_logger( 'users-helper' )->notice( $log_message, $log_array );

		return $wp_user;
	}

	/**
	 * Helper to just get a short SHA1 hash from an array.
	 *
	 * @param array $data The data to hash.
	 *
	 * @return string A 10 char SHA1 hash.
	 */
	private static function get_short_sha_from_array( array $data ): string {
		return substr( sha1( wp_json_encode( $data ) ), 0, 10 );
	}
}
