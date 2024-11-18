<?php

namespace Newspack\MigrationTools\Scaffold;

use wpdb;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationDataChest;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationRunKey;
use Newspack\MigrationTools\Scaffold\Contracts\RunAwareMigrationDataChest;
use Newspack\MigrationTools\Scaffold\Contracts\RunAwareMigrationObject;

/**
 * UnprocessedMigrationDataContainerWrapper.
 */
class UnprocessedMigrationDataChestWrapper implements RunAwareMigrationDataChest {

	/**
	 * Database connection.
	 *
	 * @var wpdb $wpdb Database connection.
	 */
	private wpdb $wpdb;

	/**
	 * The Migration Data Set Container.
	 *
	 * @var MigrationDataChest $data_container The Migration Data Set Container.
	 */
	private MigrationDataChest $data_container;

	/**
	 * The migration run key.
	 *
	 * @var MigrationRunKey $run_key The migration run key.
	 */
	private MigrationRunKey $run_key;

	/**
	 * The Database ID for the underlying Migration Data Set Container.
	 *
	 * @var int $id The Database ID for the underlying Migration Data Set Container.
	 */
	private int $id;

	/**
	 * Whether the underlying data has been stored in the database.
	 *
	 * @var bool $stored Whether the underlying data has been stored in the database.
	 */
	private bool $stored;

	/**
	 * Constructor.
	 *
	 * @param MigrationDataChest $data_container The Migration Data Set Container.
	 * @param MigrationRunKey    $run_key The migration run key.
	 */
	public function __construct( MigrationDataChest $data_container, MigrationRunKey $run_key ) {
		global $wpdb;
		$this->wpdb           = $wpdb;
		$this->run_key        = $run_key;
		$this->data_container = $data_container;
	}

	/**
	 * Gets the pointer to the identifier.
	 *
	 * @return string
	 */
	public function get_pointer_to_identifier(): string {
		return $this->data_container->get_pointer_to_identifier();
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
	 * Returns the source type for the underlying data.
	 *
	 * @return string
	 */
	public function get_source_type(): string {
		return $this->data_container->get_source_type();
	}

	/**
	 * Stores the underlying data that has been defined as needing to be migrated.
	 *
	 * @return bool
	 */
	public function store(): bool {
		if ( $this->data_container instanceof RunAwareMigrationDataChest ) {
			return $this->data_container->store();
		}

		if ( ! isset( $this->stored ) || ! $this->stored ) {
			$maybe_inserted = $this->wpdb->insert(
				'migration_data_containers',
				[
					'json_data'            => wp_json_encode( $this->get_raw_data() ),
					'pointer_to_object_id' => $this->get_pointer_to_identifier(),
					'source_type'          => $this->get_source_type(),
					'migration_id'         => $this->get_run_key()->get_migration_id(),
				]
			);

			if ( 1 !== $maybe_inserted ) {
				$this->stored = false;
			} else {
				$this->stored = true;
				$this->id     = $this->wpdb->insert_id;
			}
		}

		return $this->stored;
	}

	/**
	 * Returns whether the underlying data has been stored to the database.
	 *
	 * @return bool
	 */
	public function has_been_stored(): bool {
		if ( $this->data_container instanceof RunAwareMigrationDataChest ) {
			return $this->data_container->has_been_stored();
		}

		if ( ! isset( $this->stored ) ) {
			// phpcs:disable
			$container = $this->wpdb->get_row(
				$this->wpdb->prepare(
					'SELECT * FROM migration_data_containers 
         				WHERE migration_id = %d 
         				  AND pointer_to_object_id = %s 
         				  ORDER BY created_at DESC',
					$this->get_run_key()->get_migration_id(),
					$this->get_pointer_to_identifier(),
				)
			);
			// phpcs:enable

			$this->stored = $container && wp_json_encode( $this->get_raw_data() ) === $container->json_data;
		}

		return $this->stored;
	}

	/**
	 * Returns the individual data objects that need to be migrated.
	 *
	 * @return RunAwareMigrationObject
	 */
	public function get_all(): iterable {
		foreach ( $this->data_container->get_all() as $migration_object ) {
			if ( $migration_object instanceof RunAwareMigrationObject ) {
				if ( ! $migration_object->has_been_processed() ) {
					yield $migration_object;
				}
			} else {
				yield new RunAwareMigrationObjectWrapper( $migration_object, $this->get_run_key() );
			}
		}
	}

	/**
	 * Returns the Database ID of the migration data container.
	 *
	 * @return int
	 */
	public function get_id(): int {
		if ( $this->data_container instanceof RunAwareMigrationDataChest ) {
			return $this->data_container->get_id();
		}

		if ( ! $this->has_been_stored() ) {
			$this->store();
		} else {
			// phpcs:disable
			$this->id = $this->wpdb->get_var(
				$this->wpdb->prepare(
					'SELECT id FROM migration_data_containers 
		 				WHERE migration_id = %d 
		 				  AND pointer_to_object_id = %s 
		 				  AND json_data = %s 
		 				  ORDER BY created_at DESC',
					$this->get_run_key()->get_migration_id(),
					$this->get_pointer_to_identifier(),
					wp_json_encode( $this->get_raw_data() )
				)
			);
			// phpcs:enable
		}

		return $this->id;
	}

	/**
	 * Returns the underlying migration data set.
	 *
	 * @return iterable
	 */
	public function get_raw_data(): iterable {
		return $this->data_container->get_raw_data();
	}
}