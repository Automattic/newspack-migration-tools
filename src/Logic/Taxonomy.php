<?php
/**
 * Logic for working Taxonomies.
 */

namespace Newspack\MigrationTools\Logic;

use InvalidArgumentException;
use RuntimeException;
use WP_Term;
use WP_Error;

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
	 * Gets or creates a category by its name and parent term_id.
	 *
	 * The array is the same as wp_insert_category accepts. https://developer.wordpress.org/reference/functions/wp_insert_category
	 *
	 * Note that you *have* to provide at least the 'cat_name' field.
	 *
	 * @param array $data              The data to create the category with.
	 *
	 * @return int|null|WP_Error Category term ID or WP_Error if the category cannot be created.
	 * @throws \RuntimeException If the category cannot be created.
	 */
	public function get_or_create_category( array $data ) {
		global $wpdb;

		if ( ! array_key_exists( 'cat_name', $data ) || empty( $data['cat_name'] ) ) {
			return new WP_Error( 'missing_cat_name', 'Refusing to create category without a name.' );
		}

		$cat_name        = $data['cat_name'];
		$cat_parent_id   = $data['category_parent'] ?? 0;
		$cat_description = $data['category_description'] ?? '';
		$cat_nicename    = $data['category_nicename'] ?? '';

		// Get term_id if it exists.
		$existing_term_id = $this->get_term_id_by_taxonmy_name_and_parent( 'category', $cat_name, $cat_parent_id );
		if ( ! is_null( $existing_term_id ) ) {
			return (int) $existing_term_id;
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
				return new WP_Error( 'wrong_parent_category_term_id', sprintf( 'Wrong parent category term_id=%d given, does not exist.', $cat_parent_id ) );
			}
		}

		// Create cat.
		return wp_insert_category(
			[
				'cat_name'             => $cat_name,
				'category_parent'      => $cat_parent_id,
				'category_description' => $cat_description,
				'category_nicename'    => $cat_nicename,
			]
		);
	}

	/**
	 * Gets or creates a tag by its name.
	 *
	 * The array is the same as wp_insert_term accepts for tags. https://developer.wordpress.org/reference/functions/wp_insert_term
	 *
	 * Note that you *have* to provide at least the 'name' field.
	 *
	 * @param array $data The data to create the tag with. Must include 'name' field.
	 *
	 * @return int|WP_Error Tag term ID or WP_Error if the tag cannot be created.
	 */
	public function get_or_create_tag( array $data ) {
		global $wpdb;

		if ( ! array_key_exists( 'name', $data ) || empty( $data['name'] ) ) {
			return new WP_Error( 'missing_tag_name', 'Refusing to create tag without a name.' );
		}

		$tag_name        = $data['name'];
		$tag_description = $data['description'] ?? '';
		$tag_slug        = $data['slug'] ?? '';

		// Get term_id if it exists.
		$existing_term_id = $this->get_term_id_by_taxonmy_name_and_parent( 'post_tag', $tag_name, 0 );
		if ( ! is_null( $existing_term_id ) ) {
			return (int) $existing_term_id;
		}

		// Create tag.
		$result = wp_insert_term(
			$tag_name,
			'post_tag',
			[
				'description' => $tag_description,
				'slug'        => $tag_slug,
			]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result['term_id'];
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

	/**
	 * Sets a new top parent category for a post, moving all categories under the new top parent.
	 *
	 * *** WARNING *** only run this method once on a post! If ran more times, it will create duplicately nested categories.
	 *
	 * @param int $post_id                    Post ID.
	 * @param int $new_top_parent_category_id New top parent category ID.
	 *
	 * @throws \InvalidArgumentException      If the post or new top parent category does not exist.
	 *
	 * @return bool|null                      true if successful, null if no categories were assigned to the post.
	 */
	public function move_category_tree_under_new_top_parent( int $post_id, int $new_top_parent_category_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new InvalidArgumentException( 'Invalid post_id.' );
		}
		$category = get_category( $new_top_parent_category_id );
		if ( ! $category ) {
			throw new InvalidArgumentException( 'Invalid new_top_parent_category.' );
		}

		// Get all categories assigned to the post.
		$categories = get_the_category( $post_id );
		if ( empty( $categories ) ) {
			return null;
		}

		// Process each assigned category so that its tree becomes nested under the given new top parent category.
		$new_category_ids = [];
		foreach ( $categories as $category ) {
			$new_category_ids[] = $this->recreate_category_tree( $category, $new_top_parent_category_id );
		}

		// Update post categories.
		wp_set_post_categories( $post_id, $new_category_ids );

		return true;
	}

	/**
	 * Gets or creates a category under a new parent, respecting the original hierarchy.
	 *
	 * $category is the category to recreate under the new parent, along with its full category tree.
	 * E.g., if the existing category is "Child Category < Parent Category",
	 * the newly created category tree will be nested as "Parent Category < Child Category < New Top Parent".
	 *
	 * @param WP_Term $category Category to recreate.
	 * @param int     $parent_id    New parent category ID.
	 *
	 * @throws \RuntimeException If the category cannot be created.
	 *
	 * @return int              New category ID.
	 */
	public function recreate_category_tree( WP_Term $category, int $parent_id ) {
		// Recursively create the parent categories first.
		if ( $category->parent ) {
			$parent_category = get_category( $category->parent );
			$parent_id       = $this->recreate_category_tree( $parent_category, $parent_id );
		}

		// Check if the current category already exists under the new parent.
		$existing_category = term_exists( $category->name, 'category', $parent_id );
		if ( ! $existing_category ) {
			// Create the current category under the new parent.
			$new_category = wp_insert_term(
				$category->name,
				'category',
				[
					'parent' => $parent_id,
					'slug'   => $category->slug,
				]
			);
			if ( is_wp_error( $new_category ) ) {
				throw new RuntimeException( 'Failed to create category: ' . wp_kses_allowed_html( $new_category->get_error_message() ) );
			}

			$new_category_id = $new_category['term_id'];
		} else {
			$new_category_id = $existing_category['term_id'];
		}

		return $new_category_id;
	}
}
