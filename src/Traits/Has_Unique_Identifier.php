<?php

namespace Newspack\MigrationTools\Traits;

use InvalidArgumentException;
use WP_Term;

trait Has_Unique_Identifier {

	/**
	 * Get a Post ID by its unique identifier.
	 * 
	 * @param string      $unique_identifier The unique identifier to search for.
	 * @param string      $unique_identifier_meta_key The meta key for the unique identifier.
	 * @param string|null $post_type The Post Type to search for.
	 * @return int|false The Post ID if found, or false if not found.
	 */
	private function get_post_id_by_unique_identifier( string $unique_identifier, string $unique_identifier_meta_key, ?string $post_type ): int|false {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `post_id` FROM `$wpdb->postmeta` WHERE `meta_key` = %s AND `meta_value` = %s LIMIT 1",
				$unique_identifier_meta_key,
				$unique_identifier
			)
		);

		if ( empty( $post_id ) ) {
			return false;
		}

		if ( ! empty( $post_type ) && get_post_type( $post_id ) !== $post_type ) {
			return false;
		}

		return (int) $post_id;
	}

	/**
	 * Get a Term ID by its unique identifier.
	 * 
	 * @param string      $unique_identifier The unique identifier to search for.
	 * @param string      $unique_identifier_meta_key The meta key for the unique identifier.
	 * @param string|null $taxonomy The Taxonomy to search in.
	 * @return int|WP_Term|false The Term ID if found and no taxonomy is specified, or a WP_Term object if a taxonomy is specified, or false if not found.
	 */
	private function get_taxonomy_term_by_unique_identifier( string $unique_identifier, string $unique_identifier_meta_key, ?string $taxonomy ): int|WP_Term|false {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$term_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT term_id FROM $wpdb->termmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				$unique_identifier_meta_key,
				$unique_identifier
			)
		);

		if ( empty( $term_id ) ) {
			return false;
		}

		if ( empty( $taxonomy ) ) {
			return (int) $term_id;
		}

		return get_term_by( 'term_id', $term_id, $taxonomy );
	}

	/**
	 * Ensures that the unique identifier is not empty.
	 * 
	 * @param string $unique_identifier The unique identifier to validate.
	 * @return void
	 * @throws InvalidArgumentException If the unique identifier is empty.
	 */
	public function ensure_valid_unique_identifier( string $unique_identifier ): void {
		if ( empty( $unique_identifier ) ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new InvalidArgumentException( __( 'The unique identifier cannot be empty.', 'newspack-migration-tools' ) );
		}
	}
}
