<?php


namespace Newspack\MigrationTools\Util;

use Newspack\MigrationTools\Util\Log\FileLog;

/**
 * Utils for taxonomy meta.
 *
 * See https://github.com/Automattic/newspack-migration-tools/tree/trunk/docs/taxonomy-meta.md
 */
class TaxonomyMeta {

	/**
	 * Get a taxonomy ID from a taxonomy meta key and value.
	 *
	 * Note that if the taxonomy meta key is not unique, this will return the first taxonomy ID found.
	 *
	 * @param string $key   The meta key.
	 * @param string $value The meta value to search for.
	 *
	 * @return int Term ID or 0 if not found.
	 */
	public static function get_term_id_from_key_and_value( string $key, string $value ): int {
		if ( empty( $key ) || empty( $value ) ) {
			FileLog::get_logger( 'TaxonomyMeta' )->error(
				'Key or value is empty. Refusing to find a taxonomy with empty values.',
				[
					'key'   => $key,
					'value' => $value,
				]
			);

			return 0;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$term_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT term_id FROM $wpdb->termmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				$key,
				$value
			)
		);

		return empty( $term_id ) ? 0 : (int) $term_id;
	}
}
