<?php

namespace Newspack\MigrationTools\Scaffold\WordPressData;

use Exception;
use Newspack\MigrationTools\Scaffold\MigrationObjectPropertyWrapper;
use WP_Error;
use WP_Term;

/**
 * Class WordPressTermTaxonomiesData.
 *
 * @property int $term_taxonomy_id
 * @property int $term_id
 * @property string $taxonomy
 * @property string $description
 * @property int $parent
 * @property int $count
 */
class WordPressTermTaxonomiesData extends AbstractWordPressData {

	/**
	 * WordPressTermTaxonomiesData constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->primary_key = 'term_taxonomy_id';
	}

	/**
	 * Returns the name of the database table for this data object.
	 *
	 * @return string
	 */
	public function get_table_name(): string {
		if ( ! isset( $this->table_name ) ) {
			$this->table_name = $this->wpdb->term_taxonomy;
		}

		return parent::get_table_name();
	}

	/**
	 * Sets the term_taxonomy_id property.
	 *
	 * @param int|MigrationObjectPropertyWrapper $term_taxonomy_id The term_taxonomy_id.
	 *
	 * @return WordPressTermTaxonomiesData
	 */
	public function set_term_taxonomy_id( int|MigrationObjectPropertyWrapper $term_taxonomy_id ): WordPressTermTaxonomiesData {
		$this->set_property( 'term_taxonomy_id', $term_taxonomy_id );

		return $this;
	}

	/**
	 * Sets the term_id property.
	 *
	 * @param int|WP_Term|MigrationObjectPropertyWrapper $term_id The term_id.
	 *
	 * @return WordPressTermTaxonomiesData
	 */
	public function set_term_id( int|WP_Term|MigrationObjectPropertyWrapper $term_id ): WordPressTermTaxonomiesData {
		if ( $term_id instanceof WP_Term ) {
			$term_id = $term_id->term_id;
		}

		$this->set_property( 'term_id', $term_id );

		return $this;
	}

	/**
	 * Sets the taxonomy property.
	 *
	 * @param string|MigrationObjectPropertyWrapper $taxonomy The taxonomy.
	 *
	 * @return WordPressTermTaxonomiesData
	 */
	public function set_taxonomy( string|MigrationObjectPropertyWrapper $taxonomy ): WordPressTermTaxonomiesData {
		// Should we have a validation that checks if the taxonomy is allowed on the site?

		$this->set_property( 'taxonomy', $taxonomy );

		return $this;
	}

	/**
	 * Sets the description property.
	 *
	 * @param string|MigrationObjectPropertyWrapper $description The description.
	 *
	 * @return WordPressTermTaxonomiesData
	 */
	public function set_description( string|MigrationObjectPropertyWrapper $description ): WordPressTermTaxonomiesData {
		$this->set_property( 'description', $description );

		return $this;
	}

	/**
	 * Sets the parent property.
	 *
	 * @param int|WP_Term|MigrationObjectPropertyWrapper $parent_value The parent.
	 *
	 * @return WordPressTermTaxonomiesData
	 */
	public function set_parent( int|WP_Term|MigrationObjectPropertyWrapper $parent_value ): WordPressTermTaxonomiesData {
		if ( $parent_value instanceof \WP_Term ) {
			$parent_value = $parent_value->term_taxonomy_id;
		}

		$this->set_property( 'parent', $parent_value );

		return $this;
	}

	/**
	 * Sets the count property.
	 *
	 * @param int|MigrationObjectPropertyWrapper $count The count.
	 *
	 * @return WordPressTermTaxonomiesData
	 */
	public function set_count( int|MigrationObjectPropertyWrapper $count ): WordPressTermTaxonomiesData {
		$this->set_property( 'count', $count );

		return $this;
	}

	/**
	 * Creates a new term_taxonomy record.
	 *
	 * @return WP_Error|int The term_taxonomy_id if successful, WP_Error otherwise.
	 * @throws Exception If the term_id doesn't exist, or if unable to create the record.
	 */
	public function create(): WP_Error|int {
		$this->confirm_term_id_exists();

		if ( ! isset( $this->count ) ) {
			$this->count = 0;
		}

		if ( ! isset( $this->parent ) ) {
			$this->parent = 0;
		}

		/*
		 * Not doing any taxonomy validation here for the moment, as this would create duplicate queries (if this is
		 * being created from WordPressTermsData). Perhaps at some point, we could add a backtrace that checks
		 * if it is being created from WordPressTermsData, and if it isn't, then do taxonomy validations
		 * to make sure the taxonomy is not being changed to something that would not be unique.
		 */
		return parent::create();
	}

	/**
	 * Updates an existing term_taxonomy record.
	 *
	 * @return WP_Error|bool The true if successful, WP_Error otherwise.
	 * @throws Exception If term_id doesn't exist, if the taxonomy record doesn't exist, or if unable to update the record.
	 */
	public function update(): WP_Error|bool {
		$this->confirm_term_id_exists();

		/*
		 * Currently, there's no route from WordPressTermsData that would call this method. So we should check
		 * if the taxonomy is being changed, and whether the slug and taxonomy are unique.
		 */

		// phpcs:disable -- query is properly prepared and escaped.
		$current_term_and_taxonomy = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT 
    				* 
				FROM {$this->wpdb->terms} t 
				    INNER JOIN {$this->wpdb->term_taxonomy} tt ON t.term_id = tt.term_id 
				WHERE t.term_id = %d 
				  AND tt.term_taxonomy_id = %d",
				$this->term_id,
				$this->term_taxonomy_id
			)
		);
		// phpcs:enable

		if ( empty( $current_term_and_taxonomy ) ) {
			throw new Exception(
				sprintf(
					'Taxonomy does not exist. Term ID: %d | Taxonomy ID: %d',
					$this->term_id, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$this->term_taxonomy_id // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				)
			);
		}

		if ( $current_term_and_taxonomy->taxonomy !== $this->taxonomy ) {
			// phpcs:disable -- query is properly prepared and escaped.
			$term_and_taxonomy_exists = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT 
    					tt.term_taxonomy_id 
					FROM {$this->wpdb->terms} t 
					    INNER JOIN {$this->wpdb->term_taxonomy} tt 
					        ON t.term_id = tt.term_id 
					WHERE t.slug = %s 
					  AND tt.taxonomy = %s",
					$current_term_and_taxonomy->slug,
					$this->taxonomy
				)
			);
			// phpcs:enable

			if ( ! empty( $term_and_taxonomy_exists ) ) {
				throw new Exception(
					sprintf(
						'Term with slug: %s and taxonomy: %s already exists.',
						$current_term_and_taxonomy->slug, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
						$this->taxonomy // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					)
				);
			}
		}

		return parent::update();
	}

	/**
	 * Deletes a term_taxonomy record, and all associated terms.
	 *
	 * @return WP_Error|bool The true if successful, WP_Error otherwise.
	 * @throws Exception If term_id doesn't exist, or if unable to delete the record and associated terms.
	 */
	public function delete(): WP_Error|bool {
		// Although you're just deleting a term taxonomy, we want to make sure we delete the entire taxonomy line to keep things tidy.
		$this->confirm_term_id_exists();

		$term_id          = $this->term_id;
		$term_taxonomy_id = $this->term_taxonomy_id;
		$taxonomy         = $this->taxonomy;

		$taxonomy_deleted = parent::delete();

		if ( is_wp_error( $taxonomy_deleted ) ) {
			return $taxonomy_deleted;
		}

		$maybe_term_deleted = ( new WordPressTermsData() )->set_term_id( $term_id )->set_taxonomy( $taxonomy )->delete();

		if ( is_wp_error( $maybe_term_deleted ) ) {
			return $maybe_term_deleted;
		}

		$maybe_relationships_deleted = true;
		// phpcs:disable -- query is properly prepared and escaped.
		$count_relationships         = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->wpdb->term_relationships} WHERE term_taxonomy_id = %d",
				$term_taxonomy_id
			)
		);
		// phpcs:enable
		if ( 0 !== $count_relationships ) {
			$maybe_relationships_deleted = $this->wpdb->delete(
				$this->wpdb->term_relationships,
				[
					'term_taxonomy_id' => $term_taxonomy_id,
				]
			);
		}

		return $taxonomy_deleted && $maybe_term_deleted && $maybe_relationships_deleted;
	}

	/**
	 * Validates that the term_id exists before trying to create or update the record.
	 *
	 * @return void
	 * @throws Exception If the term_id doesn't exist.
	 */
	private function confirm_term_id_exists(): void {
		if ( ! isset( $this->term_id ) ) {
			throw new Exception( 'Term ID is required to create a term taxonomy.' );
		}

		// Let's make sure the term ID exists before inserting.
		// phpcs:disable -- query is properly prepared and escaped.
		$term_id_exists = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT term_id FROM {$this->wpdb->terms} WHERE term_id = %d",
				$this->term_id
			)
		);
		// phpcs:enable

		if ( empty( $term_id_exists ) ) {
			throw new Exception( 'Term ID does not exist.' );
		}
	}
}
