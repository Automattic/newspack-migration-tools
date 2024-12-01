<?php

namespace Newspack\MigrationTools\Scaffold;

use Exception;
use Newspack\MigrationTools\Scaffold\Contracts\RunAwareMigrationObject;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationRunKey;
use Newspack\MigrationTools\Scaffold\Contracts\RunAwareMigrationDataChest;

/**
 * AbstractRunAwareMigrationObject.
 */
abstract class AbstractRunAwareMigrationObject extends AbstractMigrationObject implements RunAwareMigrationObject {

	/**
	 * Database connection.
	 *
	 * @var \wpdb $wpdb Database connection.
	 */
	private \wpdb $wpdb;

	/**
	 * Whether the underlying data has been stored in the database.
	 *
	 * @var bool $stored Whether the underlying data has been stored in the database.
	 */
	private bool $stored;

	/**
	 * Whether this Migration Object has already been processed by the Migration Command.
	 *
	 * @var bool $processed Whether this Migration Object has already been processed by the Migration Command.
	 */
	private bool $processed;

	/**
	 * Constructor.
	 *
	 * @param object|array               $data The underlying data that needs to be migrated.
	 * @param string                     $pointer_to_identifier Pointer to the data attribute which uniquely identifies underlying data.
	 * @param RunAwareMigrationDataChest $data_container Migration Data Set Container.
	 * @param int|null                   $id The Database ID for this Migration Object.
	 * @param bool|null                  $stored Flag determining whether this Migration Object was already stored in the Database.
	 *
	 * @throws Exception If the $id does not exist in `migration_object` table.
	 */
	public function __construct( object|array $data, string $pointer_to_identifier, RunAwareMigrationDataChest $data_container, ?int $id = null, ?bool $stored = null ) {
		parent::__construct( $data, $pointer_to_identifier, $data_container );

		global $wpdb;
		$this->wpdb = $wpdb;

		if ( null !== $id ) {
			$id_exists = $this->wpdb->get_row(
				$this->wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					'SELECT ID, processed FROM migration_object WHERE ID = %d',
					$id // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				)
			);

			if ( ! $id_exists ) {
				$exception_message = sprintf( 'Migration Object Database ID (%d) does not exist', $id );
				throw new Exception( wp_kses( $exception_message, wp_kses_allowed_html( 'post' ) ) );
			}

			$this->id        = $id;
			$this->stored    = true;
			$this->processed = (bool) $id_exists->processed;
		}

		// This allows someone to set $stored, but not $id. Don't know if we need this capability, can leave like this for now, but this can likely be removed at some point.
		if ( null !== $stored && ! isset( $this->stored ) ) {
			$this->stored = $stored;
		}
	}

	/**
	 * Returns the Migration Data Set Container that also has the Migration Run Key.
	 *
	 * @return RunAwareMigrationDataChest
	 */
	public function get_container(): RunAwareMigrationDataChest {
		return $this->data_container;
	}

	/**
	 * Returns the Migration Run Key.
	 *
	 * @return MigrationRunKey
	 */
	public function get_run_key(): MigrationRunKey {
		return $this->get_container()->get_run_key();
	}

	/**
	 * Saves the migration objects container data.
	 *
	 * @return bool
	 */
	public function store_original_data(): bool {
		if ( $this->has_been_stored() ) {
			return true;
		}

		if ( isset( $this->id ) ) {
			return true; // We do not want to allow updating a Migration Object.
		} else {
			$maybe_stored = $this->wpdb->insert(
				'migration_object',
				[
					'migration_data_chest_id' => $this->get_container()->get_id(),
					'original_object_id'      => $this->get_data_id(),
					'json_data'               => wp_json_encode( $this->data ),
				]
			);

			if ( ! is_bool( $maybe_stored ) ) {
				$this->stored = false;
			} else {
				if ( true === $maybe_stored ) {
					$this->id = $this->wpdb->insert_id;
				}

				$this->stored = $maybe_stored;
			}
		}

		return $this->stored;
	}

	/**
	 * Returns whether the underlying migration data has been stored in the database.
	 *
	 * @return bool
	 */
	public function has_been_stored(): bool {
		if ( ! isset( $this->stored ) ) {
			$data = $this->wpdb->get_row(
				$this->wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					'SELECT * FROM migration_objects WHERE migration_data_chest_id = %d AND original_object_id = %s',
					$this->get_container()->get_id(), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$this->get_data_id(), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				)
			);

			if ( ! empty( $data ) ) {
				$this->stored    = true;
				$this->id        = intval( $data->id );
				$this->processed = (bool) $data->processed;
			} else {
				$this->stored    = false;
				$this->processed = false;
			}
		}

		return $this->stored;
	}

	/**
	 * Marks this Migration Object as processed.
	 *
	 * @return bool
	 */
	public function mark_as_processed(): bool {
		if ( $this->has_been_processed() ) {
			return true;
		}

		if ( ! $this->has_been_stored() ) {
			if ( ! $this->store_original_data() ) {
				return false;
			}
		}

		$this->processed = $this->update_as_processed();

		return $this->processed;
	}

	/**
	 * Returns whether the Migration Object has been processed.
	 *
	 * @return bool
	 */
	public function has_been_processed(): bool {
		if ( ! isset( $this->processed ) ) {
			if ( ! $this->has_been_stored() ) {
				return false;
			}
		}

		return $this->processed;
	}

	/**
	 * Returns the Database ID of this Migration Object.
	 *
	 * @return int
	 * @throws Exception If Migration Object does not exist in the Database.
	 */
	public function get_id(): int {
		if ( ! isset( $this->id ) ) {
			if ( ! $this->has_been_stored() ) {
				$this->store_original_data();
			} else {
				$row = $this->wpdb->get_row(
					$this->wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						'SELECT id FROM migration_object 
          					WHERE migration_data_chest_id = %d 
          					  AND original_object_id = %s',
						$this->get_container()->get_id(), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						$this->get_data_id() // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					)
				);

				if ( ! $row ) {
					throw new Exception( 'Supposedly stored Migration Object does not exist.' );
				}

				$this->id = $row->id;
			}
		}

		return $this->id;
	}

	/**
	 * Stores metadata pertaining to this Migration Object.
	 *
	 * @param string $key The metadata key.
	 * @param string $value The metadata value.
	 *
	 * @return bool
	 */
	public function store_migration_meta( string $key, string $value ): bool {
		if ( ! $this->has_been_stored() ) {
			if ( ! $this->store_original_data() ) {
				return false;
			}
		}

		$maybe_inserted = $this->wpdb->insert(
			'migration_object_meta',
			[
				'migration_data_chest_id' => $this->get_container()->get_id(),
				'migration_object_id'     => $this->get_id(),
				'meta_key'                => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'              => $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);

		if ( is_wp_error( $maybe_inserted ) ) {
			return false;
		}

		return (bool) $maybe_inserted;
	}

	/**
	 * Convenience function to handle marking this Migration Object as processed.
	 *
	 * @return bool
	 */
	private function update_as_processed(): bool {
		$maybe_updated = $this->wpdb->update(
			'migration_object',
			[
				'processed' => true,
			],
			[
				'id' => $this->get_id(),
			]
		);

		if ( is_wp_error( $maybe_updated ) ) {
			return false;
		}

		return (bool) $maybe_updated;
	}
}
