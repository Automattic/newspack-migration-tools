<?php

namespace Newspack\MigrationTools\Scaffold;

use ArrayAccess;
use Exception;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationRunKey;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationObject;
use Newspack\MigrationTools\Scaffold\Contracts\RunAwareMigrationDataChest;
use Newspack\MigrationTools\Scaffold\Contracts\RunAwareMigrationObject;
use wpdb;

/**
 * Class RunAwareMigrationObjectWrapper.
 */
class RunAwareMigrationObjectWrapper implements RunAwareMigrationObject, ArrayAccess {

	/**
	 * Database connection.
	 *
	 * @var wpdb $wpdb Database connection.
	 */
	private wpdb $wpdb;

	/**
	 * The Migration Object.
	 *
	 * @var MigrationObject $migration_object The Migration Object.
	 */
	private MigrationObject $migration_object;

	/**
	 * Migration Data Set Container.
	 *
	 * @var RunAwareMigrationDataChest $data_container Migration Data Set Container.
	 */
	private RunAwareMigrationDataChest $data_container;

	/**
	 * The Migration Run Key.
	 *
	 * @var MigrationRunKey $run_key The Migration Run Key.
	 */
	private MigrationRunKey $run_key;

	/**
	 * The Database ID for this Migration Object.
	 *
	 * @var int $id The Database ID for this Migration Object.
	 */
	private int $id;

	/**
	 * Whether the underlying migration data is considered to have been successfully processed.
	 *
	 * @var bool $processed Whether the underlying migration data is considered to have been successfully processed.
	 */
	private bool $processed;

	/**
	 * Whether the underlying migration data has been stored in the database.
	 *
	 * @var bool $stored Whether the underlying migration data has been stored in the database.
	 */
	private bool $stored;

	/**
	 * Constructor.
	 *
	 * @param MigrationObject $migration_object The Migration Object.
	 * @param MigrationRunKey $run_key The Migration Run Key.
	 */
	public function __construct( MigrationObject $migration_object, MigrationRunKey $run_key ) {
		global $wpdb;
		$this->wpdb             = $wpdb;
		$this->run_key          = $run_key;
		$this->migration_object = $migration_object;
	}

	/**
	 * Gets the underlying data that needs to be migrated.
	 *
	 * @return array|object
	 */
	public function get(): array|object {
		return $this->migration_object->get();
	}

	/**
	 * Gets the pointer to the property that uniquely identifies a migration object.
	 *
	 * @return string
	 */
	public function get_pointer_to_identifier(): string {
		return $this->migration_object->get_pointer_to_identifier();
	}

	/**
	 * Returns the ID that uniquely identifies the underlying data object.
	 *
	 * @return string|int
	 */
	public function get_data_id(): string|int {
		return $this->migration_object->get_data_id();
	}

	/**
	 * Returns Migration Data Set Container, the container for the migration objects.
	 *
	 * @return RunAwareMigrationDataChest
	 */
	public function get_container(): RunAwareMigrationDataChest {
		if ( $this->migration_object instanceof RunAwareMigrationObject ) {
			return $this->migration_object->get_container();
		}

		if ( ! isset( $this->data_container ) ) {
			$this->data_container = new UnprocessedMigrationDataChestWrapper( $this->migration_object->get_container(), $this->run_key );
		}

		return $this->data_container;
	}

	/**
	 * Returns the Migration Run Key.
	 *
	 * @return MigrationRunKey
	 */
	public function get_run_key(): MigrationRunKey {
		return $this->run_key;
	}

	/**
	 * Mark the underlying migration data as having been processed.
	 *
	 * @return bool
	 */
	public function mark_as_processed(): bool {
		if ( $this->migration_object instanceof RunAwareMigrationObject ) {
			return $this->migration_object->mark_as_processed();
		}

		// As opposed to AbstractRunAwareMigrationObject, here we want to make sure the MigrationObject has been
		// successfully stored first, before attempting anything else.
		if ( ! $this->has_been_stored() ) {
			if ( ! $this->store_original_data() ) {
				return false;
			}
		}

		if ( $this->has_been_processed() ) {
			return true;
		}

		$this->processed = $this->update_as_processed();

		return $this->processed;
	}

	/**
	 * Returns whether the underlying migration data has been processed.
	 *
	 * @return bool
	 */
	public function has_been_processed(): bool {
		if ( ! isset( $this->processed ) ) {
			return false;
		}

		return $this->processed;
	}

	/**
	 * Records the underlying migration data in the database.
	 *
	 * @return bool
	 */
	public function store_original_data(): bool {
		if ( $this->migration_object instanceof RunAwareMigrationObject ) {
			return $this->migration_object->store_original_data();
		}

		if ( $this->has_been_stored() ) {
			return true;
		}

		$maybe_stored = $this->wpdb->insert(
			'migration_objects',
			[
				'migration_data_chest_id' => $this->get_container()->get_id(),
				'original_object_id'          => $this->get_data_id(),
				'json_data'                   => wp_json_encode( $this->get() ),
			]
		);

		if ( 1 !== $maybe_stored ) {
			$this->stored = false;
		} else {
			$this->id     = $this->wpdb->insert_id;
			$this->stored = true;
		}

		return $this->stored;
	}

	/**
	 * Returns whether the underlying migration data has been stored in the database.
	 *
	 * @return bool
	 */
	public function has_been_stored(): bool {
		if ( $this->migration_object instanceof RunAwareMigrationObject ) {
			return $this->migration_object->has_been_stored();
		}

		if ( ! isset( $this->stored ) ) {
			$data = $this->wpdb->get_var(
				$this->wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					'SELECT json_data FROM migration_objects WHERE migration_data_chest_id = %d AND original_object_id = %s',
					$this->get_container()->get_id(), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$this->get_data_id(), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				)
			);

			$this->stored = ! empty( $data );
		}

		return $this->stored;
	}

	/**
	 * Stores discretionary, custom, metadata related to this particular migration object.
	 *
	 * @param string $key The custom key.
	 * @param string $value The data to store.
	 *
	 * @return bool
	 */
	public function store_migration_meta( string $key, string $value ): bool {
		if ( $this->migration_object instanceof RunAwareMigrationObject ) {
			return $this->migration_object->store_migration_meta( $key, $value );
		}

		if ( ! $this->has_been_stored() ) {
			if ( ! $this->store_original_data() ) {
				return false;
			}
		}

		$maybe_inserted = $this->wpdb->insert(
			'migration_object_meta',
			[
				'migration_data_chest_id' => $this->get_container()->get_id(),
				'migration_object_id'         => $this->get_id(),
				'meta_key'                    => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'                  => $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);

		if ( is_wp_error( $maybe_inserted ) ) {
			return false;
		}

		return (bool) $maybe_inserted;
	}

	/**
	 * Returns the database ID for this particular Migration Object.
	 *
	 * @return int
	 * @throws Exception If unable to create a record in Migration Object table.
	 */
	public function get_id(): int {
		if ( $this->migration_object instanceof RunAwareMigrationObject ) {
			return $this->migration_object->get_id();
		}

		if ( ! isset( $this->id ) ) {
			if ( ! $this->has_been_stored() ) {
				if ( ! $this->store_original_data() ) {
					$exception_message = sprintf( 'Unable to obtain a Migration Object ID for Object: %s', $this->get_data_id() );
					throw new Exception( wp_kses( $exception_message, wp_kses_allowed_html( 'post' ) ) );
				}
			}
		}

		return $this->id;
	}

	/**
	 * Convenience function to handle marking this Migration Object as processed.
	 *
	 * @return bool
	 */
	private function update_as_processed(): bool {
		$maybe_updated = $this->wpdb->update(
			'migration_objects',
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

	/**
	 * Whether a offset exists.
	 *
	 * @param mixed $offset An offset to check for.
	 *
	 * @return bool
	 */
	public function offsetExists( mixed $offset ): bool {
		return $this->__isset( $offset );
	}

	/**
	 * Offset to retrieve.
	 *
	 * @param mixed $offset The offset to retrieve.
	 *
	 * @return mixed
	 */
	public function offsetGet( mixed $offset ): MigrationObjectPropertyWrapper {
		return $this->__get( $offset );
	}

	/**
	 * Offset to set.
	 *
	 * @param mixed $offset The offset to assign the value to.
	 * @param mixed $value The value to set.
	 *
	 * @return void
	 * @throws Exception Cannot set values directly on a MigrationObject.
	 */
	public function offsetSet( mixed $offset, mixed $value ): void {
		$this->__set( $offset, $value );
	}

	/**
	 * Offset to unset.
	 *
	 * @param mixed $offset The offset to unset.
	 *
	 * @return void
	 * @throws Exception Cannot unset values directly on a MigrationObject.
	 */
	public function offsetUnset( mixed $offset ): void {
		$this->__unset( $offset );
	}

	/**
	 * Magic method to get properties.
	 *
	 * @param string $name Property name.
	 *
	 * @return MigrationObjectPropertyWrapper
	 */
	public function __get( string $name ): MigrationObjectPropertyWrapper {
		return $this->migration_object->$name;
	}

	/**
	 * Magic method to set properties.
	 *
	 * @param int|string $key Property name.
	 * @param mixed      $value Property value.
	 *
	 * @return void
	 * @throws Exception Cannot set values directly on a MigrationObject.
	 */
	public function __set( int|string $key, $value ): void {
		throw new Exception( 'RunAwareMigrationObjectWrapper is read-only' );
	}

	/**
	 * Magic method to check if a property is set.
	 *
	 * @param int|string $key Property name.
	 *
	 * @return bool
	 */
	public function __isset( int|string $key ): bool {
		return isset( $this->migration_object->$key );
	}

	/**
	 * Magic method to unset properties.
	 *
	 * @param int|string $key Property name.
	 *
	 * @return void
	 * @throws Exception Cannot unset values directly on a MigrationObject.
	 */
	public function __unset( int|string $key ): void {
		throw new Exception( 'RunAwareMigrationObjectWrapper is read-only' );
	}

	/**
	 * Magic method to convert the object to a string.
	 *
	 * @return string
	 */
	public function __toString(): string {
		return (string) $this->migration_object;
	}
}
