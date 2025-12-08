<?php
/**
 * Store for saving and retrieving original values from migration sources.
 *
 * This class provides a consistent way to store original values from a migration
 * source (like IDs, titles, URLs, etc.) on posts, terms, and users.
 *
 * All keys follow the format: _nmt_original_<entity>_<key>
 * For example: _nmt_original_post_permalink, _nmt_original_term_taxonomy, _nmt_original_user_email
 *
 * @package Newspack\MigrationTools
 */

namespace Newspack\MigrationTools\Logic;

class OriginalValueStore {

	/**
	 * Meta key prefix for original values.
	 *
	 * @var string
	 */
	const string META_KEY_PREFIX = '_nmt_original_';

	/**
	 * Saves an original value for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     The key for this value (e.g., 'permalink', 'author_id').
	 * @param mixed  $value   The value to save.
	 *
	 * @return void
	 */
	public static function save_for_post( int $post_id, string $key, mixed $value ): void {
		update_post_meta( $post_id, self::key_for( $key ), $value );
	}

	/**
	 * Gets an original value for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     The key for this value (e.g., 'permalink', 'author_id').
	 *
	 * @return mixed The saved value, or empty string if not found.
	 */
	public static function get_for_post( int $post_id, string $key ): mixed {
		return get_post_meta( $post_id, self::key_for( $key ), true );
	}

	/**
	 * Saves an original value for a term.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $key     The key for this value (e.g., 'permalink', 'taxonomy').
	 * @param mixed  $value   The value to save.
	 *
	 * @return void
	 */
	public static function save_for_term( int $term_id, string $key, mixed $value ): void {
		update_term_meta( $term_id, self::key_for( $key ), $value );
	}

	/**
	 * Gets an original value for a term.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $key     The key for this value (e.g., 'permalink', 'taxonomy').
	 *
	 * @return mixed The saved value, or empty string if not found.
	 */
	public static function get_for_term( int $term_id, string $key ): mixed {
		return get_term_meta( $term_id, self::key_for( $key ), true );
	}

	/**
	 * Saves an original value for a user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     The key for this value (e.g., 'username', 'email').
	 * @param mixed  $value   The value to save.
	 *
	 * @return void
	 */
	public static function save_for_user( int $user_id, string $key, mixed $value ): void {
		update_user_meta( $user_id, self::key_for( $key ), $value );
	}

	/**
	 * Gets an original value for a user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $key     The key for this value (e.g., 'username', 'email').
	 *
	 * @return mixed The saved value, or empty string if not found.
	 */
	public static function get_for_user( int $user_id, string $key ): mixed {
		return get_user_meta( $user_id, self::key_for( $key ), true );
	}

	/**
	 * Gets the full meta key for an original value.
	 *
	 * @param string $key The key (e.g., 'permalink', 'author_id').
	 *
	 * @return string The full meta key (e.g., '_nmt_original_permalink').
	 */
	public static function key_for( string $key ): string {
		return self::META_KEY_PREFIX . $key;
	}
}
