<?php

namespace Newspack\MigrationTools\Logic;

use Exception;
use InvalidArgumentException;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\Log\MultiLog;
use Newspack\MigrationTools\Util\UserMeta;
use WP_Error;
use WP_User;

/**
 * Helper for user getting/creation.
 *
 * See https://github.com/Automattic/newspack-migration-tools/tree/trunk/docs/users-helper.md for docs.
 */
class UsersHelper {

	const MAX_USER_LOGIN_LENGTH = 60;

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
	 * Confirms that the given username (`wp_users`.`user_login`) is unused.
	 *
	 * @param string $username The username (`wp_users`.`user_login`) to check.
	 * @param int    $exclude_user_id A user ID to exclude from the check.
	 *
	 * @return bool
	 */
	public static function is_username_unused( string $username, int $exclude_user_id = 0 ): bool {
		// Empty username should return false.
		if ( empty( $username ) ) {
			return false;
		}

		// Check to see if raw $username is unused. Assumption is that all sanitation (if necessary under the context) has already been performed on $username.
		// `get_user_by` is not used here because it does some sanitization (via `sanitize_user()`), as well as caching.
		global $wpdb;
		// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users -- We need raw uncached query results.
		$prepared_sql = $wpdb->prepare( "SELECT ID FROM $wpdb->users WHERE user_login = %s", $username );

		if ( $exclude_user_id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Reason: $sql_prepared is a prepared statement.
			$prepared_sql = $wpdb->prepare( "$prepared_sql AND ID <> %d", $exclude_user_id );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$username_is_unused = null === $wpdb->get_var( $prepared_sql );

		if ( $username_is_unused ) {
			/**
			 * We've confirmed that the `$username` is unused (in the `wp_users` table), but certain plugins might want
			 * to add their own logic to check for additional conditions. Specifically, if the co-authors-plus
			 * plugin is installed/activated, there should be additional checks for `$username` uniqueness
			 * on `wp_terms`.`name` and (`wp_postmeta`.`meta_key`, `wp_postmeta`.`meta_value`) =
			 * ( 'cap-user_login', $username ) for example.
			 *
			 * @param bool   $username_is_unused Default: True. Implementer should determine if this needs to be updated.
			 * @param string $username The username (`wp_users`.`user_login`) to check.
			 * @param int    $exclude_user_id User ID to exclude from the check.
			 *
			 * @since 0.1.3
			 */
			$username_is_unused = apply_filters( 'nmt_additional_unused_username_check', $username_is_unused, $username, $exclude_user_id );
		}

		return $username_is_unused;
	}

	/**
	 * Get a username/user_login (`wp_users`.`user_login`) that is not in use, starting with a desired username.
	 *
	 * If the desired username is in use, a counter will be appended to it until an unused username is found.
	 *
	 * @param string $desired_username Desired username (`wp_users`.`user_login`).
	 *
	 * @return string An unused username.
	 *
	 * @throws InvalidArgumentException If the desired username is empty.
	 */
	public static function get_unused_username( string $desired_username ): string {
		if ( empty( $desired_username ) ) {
			throw new InvalidArgumentException( 'Desired username (`wp_users`.`user_login`) cannot be empty.' );
		}

		$original_user_login = $desired_username;

		if ( is_email( $desired_username ) ) {
			$desired_username = trim( mb_substr( $desired_username, 0, strpos( $desired_username, '@' ) ) );
		}

		/**
		 * Sanitize the username the same way that wp_insert_user() sanitizes it.
		 */
		$desired_username_before_sanitation = $desired_username;
		$desired_username                   = self::sanitize_username( $desired_username );
		if ( is_wp_error( $desired_username ) ) {
			throw new InvalidArgumentException( sprintf( "ERROR, could not sanitize username '%s', error: %s", esc_html( $original_user_login ), esc_html( $desired_username->get_error_message() ), wp_json_encode( $desired_username ) ) );
		}
		if ( strlen( $desired_username_before_sanitation ) < strlen( $desired_username ) ) {
			FileLog::get_logger( 'UsersHelper' )->warning(
				sprintf(
					'Shortened user login to under %d chars from "%s" to "%s".',
					self::MAX_USER_LOGIN_LENGTH,
					$original_user_login,
					$desired_username
				)
			);
		}

		$i = 0;
		while ( ! self::is_username_unused( $desired_username ) ) {
			$desired_username = self::append_number_and_ensure_length( $desired_username, ++$i, self::MAX_USER_LOGIN_LENGTH );
		}
		if ( $i > 0 ) {
			CliLog::get_logger( 'UsersHelper' )->debug( sprintf( 'Generated user login: %s', $desired_username ) );
		}

		return $desired_username;
	}

	/**
	 * Sanitize the display_name.
	 *
	 * @param string $display_name The display_name to sanitize.
	 *
	 * @return string The sanitized display_name.
	 */
	public static function sanitize_display_name( string $display_name ): string {

		$display_name = trim( $display_name );

		// Don't allow email.
		if ( is_email( $display_name ) ) {
			$display_name = trim( mb_substr( $display_name, 0, strpos( $display_name, '@' ) ) );
		}

		// Trim to 250 chars (max database column length).
		$display_name = trim( mb_substr( $display_name, 0, 250 ) );

		return $display_name;

	}

	/**
	 * Sanitize the username/user_login the same way that wp_insert_user() sanitizes it.
	 *
	 * @param string $user_login The username to sanitize.
	 *
	 * @return string|WP_Error The sanitized username, or WP_Error if the sanitized username is empty.
	 */
	public static function sanitize_username( string $user_login ): string|WP_Error {

		// Trim the username.
		$sanitized_user_login = trim( $user_login );

		// Sanitize the username.
		$sanitized_user_login = sanitize_user( $sanitized_user_login, true );
		if ( empty( $sanitized_user_login ) ) {
			return new WP_Error( 'empty_username', sprintf( "Sanitation of desired username '%s' results in empty string, please choose another username.", $user_login ) );
		}

		// Ensure the username is not longer than the maximum length.
		if ( strlen( $sanitized_user_login ) >= self::MAX_USER_LOGIN_LENGTH ) {
			$sanitized_user_login = trim( mb_substr( $sanitized_user_login, 0, self::MAX_USER_LOGIN_LENGTH ) );
		}

		return $sanitized_user_login;
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
			// If the email is too long, we'll peel off characters till we get 96 characters.
			$email_parts   = explode( '@', $desired_email );
			$desired_email = substr( $email_parts[0], 0, ( 96 - strlen( $email_parts[1] ) - 1 ) ) . '@' . $email_parts[1];
			FileLog::get_logger( 'UsersHelper' )->warning( sprintf( 'Shortened email to under 100 chars from "%s" to "%s".', $original_email, $desired_email ) );
		}

		$generated_email = $desired_email;

		$i = 0;
		while ( false !== get_user_by( 'email', $generated_email ) ) {
			$generated_email = ( ++$i ) . $desired_email; // Prepend.
		}
		if ( $i > 0 ) {
			CliLog::get_logger( 'UsersHelper' )->debug( sprintf( 'Generated fake email: %s.', $generated_email ) );
		}

		return $generated_email;
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
	 * @throws InvalidArgumentException If the data array is empty or if the 'role' key is in the array and does not contain a valid role.
	 * @throws Exception If user creation fails.
	 */
	public static function create_or_get_user( array $data, string $unique_identifier ): WP_User|WP_Error {
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
		$display_name  = isset( $data['display_name'] ) ? self::sanitize_display_name( $data['display_name'] ) : '';

		// If we don't have an email, we'll create an ugly unusable one so that we can create the user.
		if ( empty( $user_email ) ) {
			$email_domain = apply_filters( 'nmt_user_email_default_domain', 'example.com' );
			$user_email   = self::get_short_sha_from_array( $data ) . '@' . $email_domain;
		}

		// We insist on setting a nicename to avoid WP setting it to the email without @.
		if ( empty( $user_nicename ) ) {
			$user_nicename = trim( ( $data['first_name'] ?? '' ) . ' ' . ( $data['last_name'] ?? '' ) );
			if ( empty( $user_nicename ) ) { // Yes, that is a whitespace and not an empty string.
				if ( ! empty( $display_name ) ) {
					$user_nicename = $display_name; // ok, since sanitize_display_name above will remove possible "@" (email) in string.
				} elseif ( ! empty( $user_login ) && ! str_contains( $user_login, '@' ) ) {
					$user_nicename = $user_login;
				} else {
					$user_nicename = self::get_short_sha_from_array( $data );
				}
			}
		}

		// If we don't hava a user_login, we'll try to create one from the nicename, display_name or hash of the data array.
		$user_login_options = [
			$user_login,
			$user_nicename,
			$display_name,

			// Hash the whole array to get an ugly, but unique username.
			self::get_short_sha_from_array( $data ),
		];

		foreach ( $user_login_options as $user_login_option ) {
			$sanitized_user_login_option = sanitize_user( $user_login_option, true );

			if ( ! empty( $sanitized_user_login_option ) ) {
				$user_login = $sanitized_user_login_option;
				break;
			}
		}

		// Sanitize the username the same way that wp_insert_user() sanitizes it.
		$user_login = self::sanitize_username( $user_login );
		if ( is_wp_error( $user_login ) ) {
			throw new InvalidArgumentException( sprintf( 'Could not sanitize username: %s. Context user_login: %s', esc_html( $user_login->get_error_message() ), wp_json_encode( $user_login ) ) );
		}

		if ( empty( $data['user_pass'] ) ) {
			$data['user_pass'] = wp_generate_password( 42 );
		}

		// Now make sure all these values are unused.
		$data['user_email']    = self::get_unused_fake_email( $user_email );
		$data['user_nicename'] = self::get_unused_nicename( $user_nicename );
		$data['user_login']    = self::get_unused_username( $user_login );
		$data['display_name']  = $display_name;

		// Add the unique identifier to the user's meta so we can find them later.
		$data['meta_input'][ self::UNIQUE_IDENTIFIER_META_KEY ] = $unique_identifier;

		// If the user website URL is longer than 100 characters, truncate it.
		if ( isset( $data['user_url'] ) ) {
			$data['user_url'] = apply_filters( 'pre_user_url', $data['user_url'] );
			if ( strlen( $data['user_url'] ) > 100 ) {
				$data['user_url'] = substr( $data['user_url'], 0, 100 );
			}
		}

		$data = apply_filters( 'nmt_user_user_pre_insert', $data, $unique_identifier );

		$user_id = wp_insert_user( $data );
		if ( is_wp_error( $user_id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( sprintf( 'Could not create user: %s. Context data: %s', $user_id->get_error_message(), wp_json_encode( $data ) ) );
		}
		if ( ! ( $user_id > 0 ) ) {
			// wp_insert_user might return integer 0 in really rare cases were a $data value has a
			// length or charset that is not allowed per the database column's length or charset.
			throw new Exception( sprintf( 'Could not create user: %s. Context data: %s', 'wp_insert_user return was not gt 0', wp_json_encode( $data ) ) );
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
	public static function append_number_and_ensure_length( string $string_to_append_to, int $number, int $max_length ): string {
		// Truncate the base string if needed so that after the number is appended, it is not longer than $max_length.
		$number_str      = (string) $number;
		$base_max_length = $max_length - strlen( $number_str );
		if ( strlen( $string_to_append_to ) > $base_max_length ) {
			$string_to_append_to = mb_substr( $string_to_append_to, 0, $base_max_length );
		}
		$string_to_append_to .= $number_str;

		return $string_to_append_to;
	}

	/**
	 * Helper to just get a short SHA1 hash from an array.
	 *
	 * @param array $data The data to hash.
	 *
	 * @return string A 10 char SHA1 hash.
	 */
	public static function get_short_sha_from_array( array $data ): string {
		return substr( sha1( wp_json_encode( $data ) ), 0, 10 );
	}

	/**
	 * Assign Authors to a Post.
	 * 
	 * This function will create the related 'author' taxonomy database rows using CoAuthorsPlus (our preferred plugin for managing
	 * "multiple authors per post"). CoAuthorsPlus will create the author taxonomy relationships and also update the post's `post_author`
	 * database column too.
	 * 
	 * Most likely you'll want to pass in an array of WP_User IDs as the $authors argument, so this requires the $query_type to be 'id'.
	 * 
	 * For other options, please see the underlying CoAuthors Plus function `add_coauthors` and $query_type ($field) options;
	 * 
	 * @link https://github.com/Automattic/Co-Authors-Plus/blob/30602dbd59c6cd73bd4aa3ff8a3e6eda0c1bccea/php/class-coauthors-plus.php#L1022
	 * @link https://github.com/Automattic/Co-Authors-Plus/blob/30602dbd59c6cd73bd4aa3ff8a3e6eda0c1bccea/php/class-coauthors-plus.php#L1050-L1052
	 *
	 * @param int    $post_id    Post ID.
	 * @param array  $authors    WP_User ids (preferred), but see links above for other options.
	 * @param bool   $append     Append to existing authors. (Default false - do not append, replace existing).
	 * @param string $query_type 'id' (for WP_User ids), but see links above for other options.
	 *
	 * @return bool|WP_Error True if successful, WP_Error if not.
	 */
	public static function assign_authors_to_post( int $post_id, array $authors, bool $append = false, string $query_type = 'id' ): bool|WP_Error {
	
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active( 'co-authors-plus/co-authors-plus.php' ) ) {
			return new WP_Error( 'ERROR_COAUTHORS_PLUS', 'Co-Authors Plus plugin not found. Install and activate it before using this function.' );
		}

		global $coauthors_plus;

		// Assign authors to post.
		$success = $coauthors_plus->add_coauthors( $post_id, $authors, $append, $query_type );
		if ( ! $success ) {
			return new WP_Error( 'ERROR_ASSIGN_CONTRIBUTORS', 'Failed to set authors. The underlying add_coauthors() function was not successful.' );
		}

		return true;
	}
}
