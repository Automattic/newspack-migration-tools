<?php

namespace Newspack\MigrationTools\Scaffold\WordPressData;

use Exception;
use Newspack\MigrationTools\Scaffold\Contracts\RunAwareMigrationObject;
use Newspack\MigrationTools\Scaffold\MigrationObjectPropertyWrapper;
use Newspack\MigrationTools\Scaffold\RunAwareMigrationObjectWrapper;
use WP_Error;

/**
 * Class WordPressTermsData.
 *
 * @property int $term_id
 * @property string $name
 * @property string $slug
 * @property int $term_group
 */
class WordPressTermsData extends AbstractWordPressData {

	/**
	 * Array containing the term's metadata.
	 *
	 * @var int[]|string[]|WordPressTermMetaData[]|MigrationObjectPropertyWrapper[] $meta_data Metadata for the term.
	 */
	protected array $meta_data = [];

	/**
	 * The term's taxonomy.
	 *
	 * @var string|MigrationObjectPropertyWrapper|null $taxonomy The taxonomy for the term.
	 */
	private string|MigrationObjectPropertyWrapper|null $taxonomy;

	/**
	 * Whether to perform a taxonomy check when creating or updating the term.
	 *
	 * @var bool $perform_taxonomy_check Whether to perform a taxonomy check before creating the term.
	 */
	private bool $perform_taxonomy_check = true;

	/**
	 * WordPressTermsData constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->primary_key = 'term_id';
	}

	/**
	 * Returns the table name for this data object.
	 *
	 * @return string
	 */
	public function get_table_name(): string {
		if ( ! isset( $this->table_name ) ) {
			$this->table_name = $this->wpdb->terms;
		}

		return parent::get_table_name();
	}

	/**
	 * Sets the term_id for this data object.
	 *
	 * @param int|MigrationObjectPropertyWrapper $term_id The term_id.
	 *
	 * @return WordPressTermsData
	 */
	public function set_term_id( int|MigrationObjectPropertyWrapper $term_id ): WordPressTermsData {
		$this->set_property( 'term_id', $term_id );

		return $this;
	}

	/**
	 * The name of the term.
	 *
	 * @param string|MigrationObjectPropertyWrapper $name The name of the term.
	 *
	 * @return WordPressTermsData
	 */
	public function set_name( string|MigrationObjectPropertyWrapper $name ): WordPressTermsData {
		$this->set_property( 'name', $name );

		return $this;
	}

	/**
	 * The slug of the term.
	 *
	 * @param string|MigrationObjectPropertyWrapper $slug The slug of the term.
	 *
	 * @return WordPressTermsData
	 */
	public function set_slug( string|MigrationObjectPropertyWrapper $slug ): WordPressTermsData {
		$this->set_property( 'slug', $slug );

		return $this;
	}

	/**
	 * The taxonomy for the term.
	 *
	 * @param string|MigrationObjectPropertyWrapper $taxonomy The taxonomy for the term.
	 *
	 * @return WordPressTermsData
	 */
	public function set_taxonomy( string|MigrationObjectPropertyWrapper $taxonomy ): WordPressTermsData {
		$this->taxonomy = $taxonomy;

		return $this;
	}

	/**
	 * Whether to consider taxonomy when creating or updating the term.
	 *
	 * @param bool $perform_taxonomy_check Whether to perform a taxonomy check before creating the term.
	 *
	 * @return WordPressTermsData
	 */
	public function set_taxonomy_check_flag( bool $perform_taxonomy_check ): WordPressTermsData {
		$this->perform_taxonomy_check = $perform_taxonomy_check;

		return $this;
	}

	/**
	 * The term group for the term.
	 *
	 * @param int|MigrationObjectPropertyWrapper $term_group The term group.
	 *
	 * @return WordPressTermsData
	 */
	public function set_term_group( int|MigrationObjectPropertyWrapper $term_group ): WordPressTermsData {
		$this->set_property( 'term_group', $term_group );

		return $this;
	}

	/**
	 * The metadata for the term.
	 *
	 * @param int[]|string[]|WordPressTermMetaData[]|MigrationObjectPropertyWrapper[] $meta_data Array of key-value pairs for the term's metadata.
	 *
	 * @return WordPressTermsData
	 */
	public function set_meta_data( array $meta_data ): WordPressTermsData {
		foreach ( $meta_data as $key => $value ) {
			if ( $value instanceof WordPressTermMetaData ) {
				$this->add_term_meta_data( $value );
			} else {
				$this->add_meta_data( $key, $value );
			}
		}

		return $this;
	}

	/**
	 * "Simpler" function which adds a metadata item to the term. Adding WordPressTermMetaData turns out to be
	 * simpler than this function in adding items to the $meta_data array, but WordPressTermMetaData
	 * has already been set up by the Migrator at this point. For example, a specific
	 * MigrationObject has been set by the implementing dev which determines
	 * which value gets stored in `migration_destination_sources`.
	 *
	 * @param string|MigrationObjectPropertyWrapper     $key The metadata key.
	 * @param int|string|MigrationObjectPropertyWrapper $value The metadata value.
	 *
	 * @return WordPressTermsData
	 */
	public function add_meta_data( string|MigrationObjectPropertyWrapper $key, int|string|MigrationObjectPropertyWrapper $value ): WordPressTermsData {
		if ( $value instanceof MigrationObjectPropertyWrapper ) {
			$migration_object = $value->get_migration_object();
			if ( ! ( $migration_object instanceof RunAwareMigrationObject ) ) {
				$migration_object = new RunAwareMigrationObjectWrapper( $migration_object, $this->get_migration_object()->get_run_context() );
			}
			$term_meta_data = new WordPressTermMetaData();
			$term_meta_data->set_migration_object( $migration_object );
			$term_meta_data->set_meta_key( $key )->set_meta_value( $value );

			if ( $key instanceof MigrationObjectPropertyWrapper ) {
				$key = $key->get_value();
			}

			$value = $term_meta_data;
		} elseif ( $key instanceof MigrationObjectPropertyWrapper ) {
			$migration_object = $key->get_migration_object();
			if ( ! ( $migration_object instanceof RunAwareMigrationObject ) ) {
				$migration_object = new RunAwareMigrationObjectWrapper( $migration_object, $this->get_migration_object()->get_run_context() );
			}
			$term_meta_data = new WordPressTermMetaData();
			$term_meta_data->set_migration_object( $migration_object );
			$term_meta_data->set_meta_key( $key->get_value() )->set_meta_value( $value );
			$key   = $key->get_value();
			$value = $term_meta_data;
		}

		$this->meta_data[ $key ] = $value;

		return $this;
	}

	/**
	 * Adds a WordPressTermMetaData object to the term's metadata.
	 *
	 * @param WordPressTermMetaData $meta_data The WordPressTermMetaData object to add.
	 *
	 * @return WordPressTermsData
	 */
	public function add_term_meta_data( WordPressTermMetaData $meta_data ): WordPressTermsData {
		$this->meta_data[ $meta_data->meta_key ] = $meta_data;

		return $this;
	}

	/**
	 * Creates the term in the WordPress database.
	 *
	 * @return WP_Error|int
	 * @throws Exception If taxonomy is not missing or duplicate, or if the term already exists.
	 */
	public function create(): WP_Error|int {
		if ( ! isset( $this->slug ) && isset( $this->name ) ) {
			if ( array_key_exists( 'name', $this->data_sources ) ) {
				$this->concatenate_to_set_property( 'slug', [ 'name' ], sanitize_title( $this->name ) );
			} else {
				$this->slug = sanitize_title( $this->name );
			}
		}

		$copy_migration_object = $this->get_migration_object();

		if ( $this->perform_taxonomy_check ) {
			if ( ! isset( $this->taxonomy ) ) {
				throw new Exception( 'Taxonomy must be set before creating terms.' );
			}

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
					$this->slug,
					$this->taxonomy
				)
			);
			// phpcs:enable

			if ( ! empty( $term_and_taxonomy_exists ) ) {
				throw new Exception(
					sprintf(
						'Term already exists: Name: %s | Slug: %s | Taxonomy: %s',
						$this->name, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
						$this->slug, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
						$this->taxonomy // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					)
				);
			}

			$maybe_term_id = parent::create();

			if ( ! is_int( $maybe_term_id ) ) {
				return $maybe_term_id;
			}

			$this->create_term_taxonomy_record( $maybe_term_id, $this->taxonomy );

			if ( ! empty( $this->meta_data ) ) {
				$this->handle_meta_data( $maybe_term_id, $copy_migration_object );
			}

			return $maybe_term_id;
		}

		$maybe_term_id = parent::create();

		if ( is_wp_error( $maybe_term_id ) ) {
			return $maybe_term_id;
		}

		$this->taxonomy = null;

		if ( ! empty( $this->meta_data ) ) {
			$this->handle_meta_data( $maybe_term_id, $copy_migration_object );
		}

		return $maybe_term_id;
	}

	/**
	 * This method handles updating the term (and possibly creating a taxonomy) record.
	 *
	 * @return WP_Error|bool
	 * @throws Exception If the taxonomy is changed without being able to check for uniqueness, if the slug and taxonomy are duplicate, or if the term_id is missing.
	 */
	public function update(): WP_Error|bool {
		// When updating a term, we want to make sure that the new slug and taxonomy are unique.

		if ( null === $this->get_primary_id() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( "Primary key `{$this->get_table_name()}`.`term_id` must be set before updating terms." );
		}

		$term_id               = $this->get_primary_id();
		$copy_migration_object = $this->get_migration_object();

		// Currently, we don't have the "before" or current state of the particular term being updated. So let's get that.

		/*
		 * term_id to taxonomy (term_taxonomy_id) should be 1-to-1.
		 * @see https://make.wordpress.org/core/2015/02/16/taxonomy-term-splitting-in-4-2-a-developer-guide/
		 */
		// phpcs:disable -- query is properly prepared and escaped.
		$current_term_taxonomy = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT 
    					* 
					FROM {$this->wpdb->terms} t 
					    LEFT JOIN {$this->wpdb->term_taxonomy} tt ON t.term_id = tt.term_id 
					WHERE t.term_id = %d",
				$term_id,
			)
		);
		// phpcs:enable

		if ( $this->perform_taxonomy_check ) {
			if ( ! isset( $this->taxonomy ) ) {
				throw new Exception( 'Taxonomy must be set before updating terms.' );
			}

			if ( $current_term_taxonomy->slug !== $this->slug || $current_term_taxonomy->taxonomy !== $this->taxonomy ) {
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
						$this->slug,
						$this->taxonomy
					)
				);
				// phpcs:enable

				if ( ! empty( $term_and_taxonomy_exists ) ) {
					throw new Exception(
						sprintf(
							'Term with slug: %s and taxonomy: %s already exists.',
							$this->slug, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
							$this->taxonomy // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
						)
					);
				}
			}

			// phpcs:disable -- query is properly prepared and escaped.
			$taxonomy_exists = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT 
    					* 
					FROM {$this->wpdb->term_taxonomy} 
					WHERE term_id = %d 
					  AND taxonomy = %s",
					$term_id,
					$this->taxonomy
				)
			);
			// phpcs:enable
			$count_taxonomy_exists = count( $taxonomy_exists );

			if ( $count_taxonomy_exists >= 2 ) {
				$term_taxonomy_ids_placeholders = implode( ',', array_fill( 0, count( $taxonomy_exists ), '%d' ) );
				throw new Exception(
					sprintf(
						"The term with ID: %d has more than one taxonomy row assigned to it. Term Taxonomy IDs: $term_taxonomy_ids_placeholders", // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
						$term_id, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
						...wp_list_pluck( $taxonomy_exists, 'term_taxonomy_id' ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					)
				);
			} elseif ( 0 === $count_taxonomy_exists ) {
				$this->create_term_taxonomy_record( $term_id, $this->taxonomy );
			}

			return parent::update();
		}

		if ( $current_term_taxonomy->taxonomy !== $this->taxonomy ) {
			throw new Exception(
				sprintf(
					'Taxonomy was changed for term ID: %d, but the taxonomy check was set to false. Current Taxonomy: %s | New Taxonomy: %s',
					$term_id, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$current_term_taxonomy->taxonomy, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$this->taxonomy // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				)
			);
		}

		$maybe_updated = parent::update();

		if ( is_wp_error( $maybe_updated ) ) {
			return $maybe_updated;
		}

		$this->taxonomy = null;

		if ( ! empty( $this->meta_data ) ) {
			$this->handle_meta_data( $term_id, $copy_migration_object );
		}

		return $maybe_updated;
	}

	/**
	 * This method handles deleting the term, taxonomy, and its associated metadata.
	 *
	 * @return bool|WP_Error
	 */
	public function delete(): bool|WP_Error {
		$term_id = $this->get_primary_id();

		$maybe_deleted = parent::delete();

		if ( is_wp_error( $maybe_deleted ) ) {
			return $maybe_deleted;
		}

		$this->taxonomy = null;

		$maybe_meta_deleted = true;
		// phpcs:disable -- query is properly prepared and escaped.
		$count_meta = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->wpdb->termmeta} WHERE term_id = %d",
                $term_id,
			)
		);
		// phpcs:enable
		if ( 0 !== $count_meta ) {
			$maybe_meta_deleted = $this->wpdb->delete(
				$this->wpdb->termmeta,
				[
					'term_id' => $term_id,
				]
			);
		}

		$maybe_taxonomy_deleted = true;
		// phpcs:disable -- query is properly prepared and escaped.
		$count_taxonomy = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wpdb->term_taxonomy} WHERE term_id = %d",
                $term_id,
            )
		);
		// phpcs:enable
		if ( 0 !== $count_taxonomy ) {
			$maybe_taxonomy_deleted = $this->wpdb->delete(
				$this->wpdb->term_taxonomy,
				[
					'term_id' => $term_id,
				]
			);
		}

		return $maybe_deleted && $maybe_meta_deleted && $maybe_taxonomy_deleted;
	}

	/**
	 * Handles creating or updating the metadata for the term.
	 *
	 * @param int                     $term_id       The ID of the term.
	 * @param RunAwareMigrationObject $migration_object The migration object.
	 *
	 * @return void
	 * @throws Exception If the term ID is not set, or if the metadata cannot be created or updated.
	 */
	private function handle_meta_data( int $term_id, RunAwareMigrationObject $migration_object ): void {
		// phpcs:disable -- query is properly prepared and escaped.
		$existing_meta_data = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->termmeta} WHERE term_id = %d",
				$term_id
			)
		);
		// phpcs:enable

		foreach ( $existing_meta_data as $meta_data ) {
			if ( array_key_exists( $meta_data->meta_key, $this->meta_data ) ) {
				$value = $this->meta_data[ $meta_data->meta_key ];
				// phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- this is intentional, values from the DB will be strings. Letting PHP handle the implicit type conversions.
				if ( $value instanceof WordPressTermMetaData && $value->meta_value != $meta_data->meta_value ) {
					if ( ! array_key_exists( 'migration_object', get_object_vars( $value ) ) ) {
						$value->set_migration_object( $migration_object );
					}
					$value->set_meta_id( $meta_data->meta_id )->set_meta_value( $value->meta_value )->update();
					unset( $this->meta_data[ $meta_data->meta_key ] );
					continue;
				}

				// phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- this is intentional, values from the DB will be strings. Letting PHP handle the implicit type conversions.
				if ( $value instanceof MigrationObjectPropertyWrapper && $value->get_value() != $meta_data->meta_value ) {
					$term_meta_data = new WordPressTermMetaData();
					$term_meta_data->set_migration_object( $value->get_migration_object() );
					$term_meta_data->set_meta_id( $meta_data->meta_id )->set_meta_value( $value->get_value() )->update();
					unset( $this->meta_data[ $meta_data->meta_key ] );
					continue;
				}

				$this->wpdb->update(
					$this->wpdb->termmeta,
					[
						'meta_value' => $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- this query targets metadata by meta ID.
					],
					[
						'meta_id' => $meta_data->meta_id,
					]
				);
				unset( $this->meta_data[ $meta_data->meta_key ] );
			} else {
				$this->wpdb->delete(
					$this->wpdb->termmeta,
					[
						'meta_id' => $meta_data->meta_id,
					]
				);
			}
		}

		foreach ( $this->meta_data as $key => $value ) {
			if ( $value instanceof WordPressTermMetaData ) {
				if ( ! array_key_exists( 'migration_object', get_object_vars( $value ) ) ) {
					$value->set_migration_object( $migration_object );
				}
				$value->set_term_id( $term_id )->create();
				continue;
			}

			if ( $value instanceof MigrationObjectPropertyWrapper ) {
				$term_meta_data         = new WordPressTermMetaData();
				$value_migration_object = $value->get_migration_object();
				if ( ! ( $value_migration_object instanceof RunAwareMigrationObject ) ) {
					$migration_object = new RunAwareMigrationObjectWrapper( $value_migration_object, $migration_object->get_run_context() );
				}
				$term_meta_data->set_migration_object( $value_migration_object );
				$term_meta_data->set_term_id( $term_id )->set_meta_key( $key )->set_meta_value( $value )->create();
				continue;
			}

			$this->wpdb->insert(
				$this->wpdb->termmeta,
				[
					'term_id'    => $term_id,
					'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				]
			);
		}

		$this->meta_data = [];
	}

	/**
	 * Creates a new term taxonomy record for the given term ID and taxonomy.
	 *
	 * @param int    $term_id   The term ID.
	 * @param string $taxonomy The taxonomy.
	 *
	 * @return WordPressTermTaxonomiesData
	 * @throws Exception If unable to create term taxonomy record.
	 */
	private function create_term_taxonomy_record( int $term_id, string $taxonomy ): WordPressTermTaxonomiesData {
		$taxonomies_data = new WordPressTermTaxonomiesData();
		$taxonomies_data->set_migration_object( $this->get_migration_object() );
		$maybe_term_taxonomy_id = $taxonomies_data->set_term_id( $term_id )
													->set_taxonomy( $taxonomy )
													->create();

		if ( ! is_int( $maybe_term_taxonomy_id ) ) {
			throw new Exception(
				sprintf(
					'Failed to create term taxonomy for Term ID: %d.',
					$term_id // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				)
			);
		}

		return $taxonomies_data;
	}
}
