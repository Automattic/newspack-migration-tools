<?php

namespace Newspack\MigrationTools\Logic;

use Exception;
use InvalidArgumentException;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\UserMeta;
use WP_User;

/**
 * Helper for user getting/creation.
 *
 * See https://github.com/Automattic/newspack-migration-tools/tree/trunk/docs/users-helper.md for docs.
 */
class UsersHelper {

	/**
	 * Meta key for the unique identifier for users.
	 */
	public const UNIQUE_IDENTIFIER_META_KEY = '_nmt_user_uniqid';

	/**
	 * Get a user by its unique identifier.
	 *
	 * The identifier was set when the user was created (if it was created by this class), so you probably know what it is.
	 * Make sure you read the docs linked to at the top of the class.
	 *
	 * @param string $unique_identifier The unique identifier to search for.
	 *
	 * @return WP_User|bool A user object if found, false otherwise.
	 */
	public static function get_user_by_unique_identifier( string $unique_identifier ): WP_User|bool {
		$user_id = UserMeta::get_user_id_from_key_and_value( self::UNIQUE_IDENTIFIER_META_KEY, $unique_identifier );
		if ( empty( $user_id ) ) {
			return false;
		}
		$wp_user = get_user_by( 'ID', $user_id );

		return $wp_user ?? false;
	}

	/**
	 * Get a user by an array that contains either the ID, user_email, user_login or user_nicename.
	 *
	 * PLEASE don't use this method if you have a unique identifier for your user. Use `get_user_by_unique_identifier` instead.
	 * Make sure you read the docs linked to at the top of the class.
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
		$original_username = $desired_username;
		$desired_username  = trim( $desired_username );
		$max_length        = 60;
		if ( strlen( $desired_username ) >= $max_length ) {
			$desired_username = trim( mb_substr( $desired_username, 0, $max_length ) );
			FileLog::get_logger( 'UsersHelper' )->warning(
				sprintf(
					'Shortened username to under %d chars from "%s" to "%s".',
					$max_length,
					$original_username,
					$desired_username
				)
			);
		}

		$i = 0;
		while ( username_exists( $desired_username ) ) {
			$desired_username = self::append_number_and_ensure_length( $desired_username, ++$i, $max_length );
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
		$original_nicename = $desired_nicename;
		$desired_nicename  = trim( $desired_nicename );
		$max_length        = 50;
		if ( strlen( $desired_nicename ) >= $max_length ) {
			$desired_nicename = trim( mb_substr( $desired_nicename, 0, $max_length ) );
			FileLog::get_logger( 'UsersHelper' )->warning(
				sprintf(
					'Shortened nicename to under %d chars from "%s" to "%s".',
					$max_length,
					$original_nicename,
					$desired_nicename
				)
			);
		}

		$i = 0;
		while ( self::nicename_exists( $desired_nicename ) ) {
			$desired_nicename = self::append_number_and_ensure_length( $desired_nicename, ++$i, $max_length );
		}
		if ( $i > 0 ) {
			CliLog::get_logger( 'UsersHelper' )->debug( sprintf( 'Generated nicename: %s', $desired_nicename ) );
		}

		return $desired_nicename;
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
		$original_email = $desired_email;
		if ( strlen( $desired_email ) > 100 ) {
			// If the email is too long, we'll peel off a couple of characters from the beginning.
			$desired_email = substr( $desired_email, 4 );
			FileLog::get_logger( 'UsersHelper' )->warning( sprintf( 'Shortened email to under 100 chars from "%s" to "%s".', $original_email, $desired_email ) );
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
				"SELECT ID FROM $wpdb->users WHERE user_nicename = %s LIMIT 1", // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
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
	 * @param array  $data              The data to create the user with. If the 'role' key is present, the user will be assigned that role.
	 * @param string $unique_identifier A unique identifier for your user – can be any string, but should be unique.
	 *
	 * @throws Exception If the user could not be created.
	 * @throws InvalidArgumentException If the data array is empty or if the 'role' key is in the array and does not contain a valid role. .
	 */
	public static function create_or_get_user( array $data, string $unique_identifier ): WP_User {
		if ( empty( trim( $unique_identifier ) ) ) {
			throw new InvalidArgumentException( 'Refusing to create user without a unique identifier.' );
		}

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

		// First try with the uniqid for the user.
		$wp_user = self::get_user_by_unique_identifier( $unique_identifier );
		if ( ! $wp_user ) {
			// OK, no unique identifier found, let's try to find the user by the data.
			$wp_user = self::get_user( $data );
		}
		if ( $wp_user ) { // Great – we already have the user!
			if ( ! empty( $data['role'] ) ) {
				// If the role was passed in the data array – add it before returning.
				$wp_user->add_role( $data['role'] );
				FileLog::get_logger( 'UsersHelper' )->notice( sprintf( 'Added role "%s" to existing user with id %d', $data['role'], $wp_user->ID ) );
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

		// We insist on setting a nicename to avoid WP setting it to the email without @.
		if ( empty( $user_nicename ) ) {
			$user_nicename = trim( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) );
			if ( empty( $user_nicename ) ) { // Yes, that is a whitespace and not an empty string.
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

		// Add the unique identifier to the user's meta so we can find them later.
		$data['meta_input'][ self::UNIQUE_IDENTIFIER_META_KEY ] = $unique_identifier;

		$data = apply_filters( 'nmt_user_user_pre_insert', $data, $unique_identifier );

		$user_id = wp_insert_user( $data );
		if ( is_wp_error( $user_id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( sprintf( 'Could not create user: %s', $user_id->get_error_message() ) );
		}
		$wp_user = get_user_by( 'ID', $user_id );

		FileLog::get_logger( 'UsersHelper' )->notice(
			'Created user:',
			[
				'ID'            => $wp_user->ID,
				'url'           => get_author_posts_url( $wp_user->ID ),
				'user_login'    => $wp_user->user_login,
				'user_nicename' => $wp_user->user_nicename,
				'display_name'  => $wp_user->display_name,
				'user_email'    => $wp_user->user_email,
				'roles'         => $wp_user->roles,
				'uniqid'        => $unique_identifier,
			]
		);

		return $wp_user;
	}

	/**
	 * Append a number to a string and ensure it is not longer than a given length.
	 *
	 * If the string is too long, characters will be removed from the beginning so we don't
	 * chop off the incrementor at the end.
	 *
	 * @param string $string_to_append_to For example a nicename.
	 * @param int    $number              The number to append – you are responsible for incrementing it if you need that.
	 * @param int    $max_length          The maximum length you will allow the string to be.
	 *
	 * @return string A maybe truncated string with the number appended.
	 */
	private static function append_number_and_ensure_length( string $string_to_append_to, int $number, int $max_length ): string {
		$string_to_append_to .= $number;

		// If the string is too long, we'll peel off a couple of characters from the beginning
		$length = strlen( $string_to_append_to );
		if ( $length >= $max_length ) {
			$string_to_append_to = mb_substr( $string_to_append_to, ( $length - $max_length ), $length );
		}

		return $string_to_append_to;
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
