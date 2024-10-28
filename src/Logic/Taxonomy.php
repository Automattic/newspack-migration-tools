<?php
/**
 * Logic for working Taxonomies.
 */

namespace Newspack\MigrationTools\Logic;

use InvalidArgumentException;

/**
 * Taxonomy logic
 */
class Taxonomy {

	/**
	 * Fixes counts for taxonomy.
	 *
	 * @param string $taxonomy Taxonomy, e.g. 'category'.
	 *
	 * @return void
	 */
	public function fix_taxonomy_term_counts( string $taxonomy ) {
		$get_terms_args = [
			'taxonomy'   => $taxonomy,
			'fields'     => 'ids',
			'hide_empty' => false,
		];

		$update_term_ids = get_terms( $get_terms_args );
		foreach ( $update_term_ids as $key_term_id => $term_id ) {
			wp_update_term_count_now( [ $term_id ], $taxonomy );
		}

		wp_cache_flush();
	}

	/**
	 * Reassigns all content from one taxonomy to a different taxonomy.
	 *
	 * @param string $taxonomy            Source taxonomy, e.g. 'category'.
	 * @param int    $source_term_id      Source term_id.
	 * @param int    $destination_term_id Destination term_id.
	 *
	 * @return void
	 */
	public function reassign_all_content_from_one_taxonomy_to_another( string $taxonomy, int $source_term_id, int $destination_term_id ): void {
		// Get post IDs with both terms.
		$posts_with_both_terms = get_posts(
			[
				'fields'           => 'ids',
				'posts_per_page'   => -1,
				'post_type'        => 'any',
				'post_status'      => 'any',
				'suppress_filters' => true, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters -- Suppress filters is needed here.
				'tax_query'        => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					'relation' => 'AND',
					[
						'taxonomy' => $taxonomy,
						'field'    => 'term_taxonomy_id',
						'terms'    => [ $source_term_id ],
					],
					[
						'taxonomy' => $taxonomy,
						'field'    => 'term_taxonomy_id',
						'terms'    => [ $destination_term_id ],
					],
				],
			]
		);

		// Delete the source term from these posts.
		if ( ! empty( $posts_with_both_terms ) ) {
			$this->delete_object_relational_mapping_term_taxonomy_id( $source_term_id, $posts_with_both_terms );
		}

		$this->update_object_relational_mapping_term_taxonomy_id( $source_term_id, $destination_term_id );

		$this->fix_taxonomy_term_counts( $taxonomy );
	}

	/**
	 * Runs a direct DB UPDATE on wp_term_relationships table and updates term_taxonomy_id from one value to a different one.
	 *
	 * @param int $old_term_taxonomy_id Old term_taxonomy_id.
	 * @param int $new_term_taxonomy_id New term_taxonomy_id.
	 *
	 * @return string|null Return from $wpdb::get_var().
	 */
	public function update_object_relational_mapping_term_taxonomy_id( $old_term_taxonomy_id, $new_term_taxonomy_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->update( $wpdb->term_relationships, [ 'term_taxonomy_id' => $new_term_taxonomy_id ], [ 'term_taxonomy_id' => $old_term_taxonomy_id ] );
	}

	/**
	 * Runs a direct DB DELETE on wp_term_relationships table and deletes all rows with a given term_taxonomy_id and post_ids.
	 *
	 * @param int   $term_taxonomy_id Term_taxonomy_id.
	 * @param array $post_ids         Post IDs.
	 *
	 * @return string|null Return from $wpdb::query().
	 */
	public function delete_object_relational_mapping_term_taxonomy_id( $term_taxonomy_id, $post_ids ) {
		global $wpdb;

		$object_id_placeholders = implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->term_relationships} WHERE object_id IN( $object_id_placeholders ) and term_taxonomy_id = %d",      //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( $post_ids, [ $term_taxonomy_id ] )
			)
		);
	}

	/**
	 * Gets a term_id by its taxonomy, name and parent ID.
	 * For example, you can search for a category with a name and optional parent ID.
	 *
	 * @param string $taxonomy       Taxonomy, e.g. 'category'.
	 * @param string $name           Taxonomy name, e.g. 'Some category name'.
	 * @param int    $parent_term_id Parent term_id, e.g. 123 or 0.
	 *
	 * @return string|null Term ID or null if not found.
	 */
	public function get_term_id_by_taxonmy_name_and_parent( string $taxonomy, string $name, int $parent_term_id = 0 ) {
		global $wpdb;

		$query_prepare = "select t.term_id
			from {$wpdb->terms} t
			join {$wpdb->term_taxonomy} tt on tt.term_id = t.term_id
			where tt.taxonomy = %s and t.name = %s and tt.parent = %d;";

		// First try with converting name chars to HTML entities.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing_term_id = $wpdb->get_var(
			$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$query_prepare,
				$taxonomy,
				htmlentities( $name ),
				$parent_term_id
			)
		);

		// Try without converting name chars to HTML entities.
		if ( ! $existing_term_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$existing_term_id = $wpdb->get_var(
				$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$query_prepare,
					$taxonomy,
					$name,
					$parent_term_id
				)
			);
		}

		return $existing_term_id;
	}

	/**
	 * Gets or creates a category by its name and parent term_id.
	 *
	 * @param string $cat_name      Category name.
	 * @param int    $cat_parent_id Category's parent term_id.
	 *
	 * @return string|null Category term ID.
	 * @throws \RuntimeException If nonexisting $cat_parent_id is given.
	 */
	public function get_or_create_category_by_name_and_parent_id( string $cat_name, int $cat_parent_id = 0 ) {
		global $wpdb;

		// Get term_id if it exists.

		$existing_term_id = $this->get_term_id_by_taxonmy_name_and_parent( 'category', $cat_name, $cat_parent_id );
		if ( ! is_null( $existing_term_id ) ) {
			return $existing_term_id;
		}

		// If it doesn't exist, then create it.

		// Double check this parent exists.
		if ( 0 != $cat_parent_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$existing_cat_parent_id = $wpdb->get_var(
				$wpdb->prepare(
					"select t.term_id
						from {$wpdb->terms} t
						join {$wpdb->term_taxonomy} tt on tt.term_id = t.term_id
						where tt.taxonomy = 'category' and tt.term_id = %d;",
					$cat_parent_id
				)
			);
			if ( is_null( $existing_cat_parent_id ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new \RuntimeException( sprintf( 'Wrong parent category term_id=%d given, does not exist.', $cat_parent_id ) );
			}
		}

		// Create cat.
		$cat_id = wp_insert_category(
			[
				'cat_name'        => $cat_name,
				'category_parent' => $cat_parent_id,
			]
		);

		return $cat_id;
	}

	/**
	 * Gets duplicate term slugs.
	 *
	 * @return array
	 */
	public function get_duplicate_term_slugs() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			"SELECT
			t.slug,
			GROUP_CONCAT( DISTINCT tt.taxonomy ORDER BY tt.taxonomy SEPARATOR ', ' ) as taxonomies,
			GROUP_CONCAT(
			    CONCAT( tt.term_id, ':', tt.term_taxonomy_id, ':', tt.taxonomy )
			    ORDER BY t.term_id, tt.term_taxonomy_id ASC SEPARATOR '  |  '
			    ) as 'term_id:term_taxonomy_id:taxonomy',
			COUNT( DISTINCT tt.term_taxonomy_id ) as term_taxonomy_id_count
			FROM $wpdb->terms t
			LEFT JOIN $wpdb->term_taxonomy tt ON t.term_id = tt.term_id
			GROUP BY t.slug, tt.term_id
			HAVING term_taxonomy_id_count > 1
			ORDER BY term_taxonomy_id_count DESC"
		);
	}

	/**
	 * Gets terms and taxonomies by slug.
	 *
	 * @param string $slug       Slug.
	 * @param array  $taxonomies Taxonomies.
	 *
	 * @return array
	 */
	public function get_terms_and_taxonomies_by_slug( string $slug, array $taxonomies = [ 'category', 'post_tag' ] ) {
		global $wpdb;

		$query = "SELECT
	                t.term_id,
	                t.name, t.slug,
	                tt.term_taxonomy_id,
	                tt.taxonomy,
	                tt.parent,
	                tt.count
				FROM $wpdb->terms t
				INNER JOIN $wpdb->term_taxonomy tt ON t.term_id = tt.term_id
				WHERE t.slug = %s";

		if ( ! empty( $taxonomies ) ) {
			$query .= 'AND tt.taxonomy IN ( '
						. implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) )
						. ' )';
		}

		$query .= ' ORDER BY t.term_id, tt.term_taxonomy_id ASC';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$query,
				array_merge( [ $slug ], $taxonomies )
			)
		);
	}

	/**
	 * Obtains a new slug that does not exist in the database.
	 *
	 * @param string $slug   Slug.
	 * @param int    $offset Offset.
	 *
	 * @return string
	 */
	public function get_new_term_slug( string $slug, int $offset = 1 ) {
		$new_slug = $slug . '-' . $offset;

		do {
			$slug_exists = ! is_null( term_exists( $new_slug ) );

			if ( $slug_exists ) {
				$offset++;
				$new_slug = $slug . '-' . $offset;
			}
		} while ( $slug_exists );

		return $new_slug;
	}

	/**
	 * Get terms from a taxonomy that are assigned to fewer than or equal to $assigned_to_max_num_posts posts.
	 * This is useful for finding terms that are not assigned to many posts.
	 *
	 * @param string $taxonomy                  Taxonomy name - e.g. 'post_tag'.
	 * @param int    $assigned_to_max_num_posts Max number of posts a term can be assigned to.
	 *
	 * @return array Ids of terms assigned to fewer than or equal to $assigned_to_max_num_posts posts.
	 * @throws InvalidArgumentException If the taxonomy does not exist.
	 */
	public function get_terms_assigned_to_max_num_posts( string $taxonomy, int $assigned_to_max_num_posts ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			throw new InvalidArgumentException( esc_html( sprintf( 'Taxonomy "%s" does not exist.', $taxonomy ) ) );
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT t.term_id
				FROM $wpdb->terms t
					LEFT JOIN $wpdb->term_taxonomy tt ON t.term_id = tt.term_id
					LEFT JOIN $wpdb->term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
				WHERE tt.taxonomy = %s
			GROUP BY t.term_id
			HAVING COUNT(tr.object_id) <= %d",
				$taxonomy,
				$assigned_to_max_num_posts
			)
		);
	}
}
