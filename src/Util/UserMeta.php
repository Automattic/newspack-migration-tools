<?php


namespace Newspack\MigrationTools\Util;

use Newspack\MigrationTools\Util\Log\FileLog;

/**
 * Utils for user meta.
 *
 * See https://github.com/Automattic/newspack-migration-tools/tree/trunk/docs/user-meta.md
 */
class UserMeta {

	/**
	 * Get a user ID from a user meta key and value.
	 *
	 * Note that if the user meta key is not unique, this will return the first user ID found.
	 *
	 * @param string $key   The meta key.
	 * @param string $value The meta value to search for.
	 *
	 * @return int User ID or 0 if not found.
	 */
	public static function get_user_id_from_key_and_value( string $key, string $value ): int {
		if ( empty( $key ) || empty( $value ) ) {
			FileLog::get_logger( 'UserMeta' )->error(
				'Key or value is empty. Refusing to find a user with empty values.',
				[
					'key'   => $key,
					'value' => $value,
				] 
			);

			return 0;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$user_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM $wpdb->usermeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				$key,
				$value
			)
		);

		return empty( $user_id ) ? 0 : (int) $user_id;
	}
}
