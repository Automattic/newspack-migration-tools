<?php

namespace Newspack\MigrationTools\Scaffold;

use Exception;
use Newspack\MigrationTools\Scaffold\Contracts\Migration;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationRunKey;
use Newspack\MigrationTools\Scaffold\Enum\MigrationStatus;
use Newspack\MigrationTools\Scaffold\Singletons\WordPressData;

/**
 * Class for migration activity.
 */
class MigrationActivity {

	/**
	 * Database object.
	 *
	 * @var \wpdb $wpdb Database object.
	 */
	private \wpdb $wpdb;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;

		$this->wpdb = $wpdb;
	}

	/**
	 * Returns the activity history for a migration.
	 *
	 * @param Migration $migration The migration to get the activity for.
	 *
	 * @return iterable
	 */
	public function get_all( Migration $migration ): iterable {
		return $this->wpdb->get_results(
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare(
				'SELECT
    				m.id, 
    				m.name, 
    				m.version, 
    				m.status_id 
				FROM migrations m 
				    LEFT JOIN migration_status ms 
				        ON m.ID = ms.migration_id 
				WHERE m.name = %s 
				  AND m.version = ( 
				  	SELECT MAX( version ) 
				  	FROM migration 
				  	WHERE name = %s 
				  	) 
				ORDER BY ms.created_at',
				$migration->get_name(), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$migration->get_name(), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			)
		);
	}

	/**
	 * Returns the latest activity for a migration.
	 *
	 * @param Migration $migration The migration to get the latest activity for.
	 *
	 * @return object|null
	 */
	public function get_latest( Migration $migration ): ?object {
		return $this->wpdb->get_row(
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare(
				'SELECT
					m.id,
					m.namespace_and_class,
					m.name, 
					m.version, 
					ms.status_id 
				FROM migrations m 
				    LEFT JOIN migration_status ms 
				        ON m.ID = ms.migration_id 
				WHERE m.namespace_and_class = %s
				  AND m.name = %s 
				  AND m.version = ( 
				  	SELECT MAX( version ) 
				  	FROM migrations 
				  	WHERE migrations.namespace_and_class = %s 
				  	  AND name = %s 
				  	) 
				ORDER BY ms.created_at DESC, FIELD( status_id, 3, 5, 4, 2, 1)
				LIMIT 1',
				get_class( $migration ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$migration->get_name(), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				get_class( $migration ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$migration->get_name(), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			)
		);
	}

	/**
	 * Obtains all the versions a specific Migration has spawned.
	 *
	 * @param Migration $migration The migration.
	 *
	 * @return array
	 */
	public function get_versions( Migration $migration ): array {
		// phpcs:disable
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				'SELECT id, version FROM migrations WHERE name = %s',
				$migration->get_name()
			)
		);
		// phpcs:enable
	}

	/**
	 * Obtains the most recent version number for a particular migration.
	 *
	 * @param Migration $migration The migration.
	 *
	 * @return int|null
	 */
	public function get_latest_version( Migration $migration ): ?int {
		// phpcs:disable
		$latest_version = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT version FROM migrations WHERE name = %s ORDER BY version DESC LIMIT 1',
				$migration->get_name()
			)
		);
		// phpcs:enable

		if ( null !== $latest_version ) {
			return intval( $latest_version );
		}

		return null;
	}

	/**
	 * Gets all statuses a Migration has gone through.
	 *
	 * @param Migration $migration The migration.
	 * @param int|null  $migration_id Filter for a specific Migration ID.
	 * @param int|null  $version Filter for a specific version number.
	 *
	 * @return array|null
	 */
	public function get_statuses( Migration $migration, int $migration_id = null, int $version = null ): array|null {
		// phpcs:disable
		$migration_statuses = $this->wpdb->get_results(
			$this->wpdb->prepare(
				'SELECT 
    					m.id as migration_id, 
    					m.name as migration_name, 
    					m.version as migration_version, 
    					m.created_at as migration_created_at, 
    					ms.id as status_table_id,
    					ms.status_id, 
    					mse.name, 
    					ms.created_at
					FROM migrations m 
					    LEFT JOIN migration_status ms ON m.id = ms.migration_id 
					    INNER JOIN migration_status_enum mse ON mse.id = ms.status_id 
					WHERE m.name = %s 
					ORDER BY ms.created_at DESC, FIELD( ms.status_id, 3, 5, 4, 2, 1), m.created_at DESC',
				$migration->get_name()
			)
		);
		// phpcs:enable

		foreach ( $migration_statuses as $index => &$migration_status_record ) {
			if ( null !== $migration_id && intval( $migration_status_record->id ) !== $migration_id ) {
				unset( $migration_statuses[ $index ] );
				continue;
			}

			if ( null !== $version && intval( $migration_status_record->version ) !== $version ) {
				unset( $migration_statuses[ $index ] );
				continue;
			}

			$migration_status_record->status = MigrationStatus::tryFrom( $migration_status_record->status_id );
		}

		return array_values( $migration_statuses );
	}

	/**
	 * Obtains the latest status for a specific Migration.
	 *
	 * @param string $namespace_and_class The migration namespace and class.
	 * @param string $migration_name The migration name.
	 *
	 * @return object|null
	 */
	public function get_latest_status( string $namespace_and_class, string $migration_name ): object|null {
		// phpcs:disable
		$latest_status = $this->wpdb->get_row(
			$this->wpdb->prepare(
				'SELECT 
    					m.id as migration_id, 
    					m.namespace_and_class as migration_namespace_and_class, 
    					m.name as migration_name, 
    					m.version migration_version, 
    					m.created_at migration_created_at, 
    					ms.id as status_table_id,
    					ms.status_id, 
    					mse.name, 
    					ms.created_at 
					FROM migrations m 
					    LEFT JOIN migration_status ms ON m.id = ms.migration_id 
					    INNER JOIN migration_status_enum mse ON mse.id = ms.status_id 
					WHERE m.namespace_and_class = %s 
					  AND m.name = %s 
					ORDER BY ms.created_at DESC, FIELD( ms.status_id, 3, 5, 4, 2, 1), m.created_at DESC 
					LIMIT 1',
				$namespace_and_class,
				$migration_name
			)
		);
		// phpcs:enable

		if ( $latest_status ) {
			$latest_status->status = MigrationStatus::tryFrom( $latest_status->status_id );

			return $latest_status;
		}

		return null;
	}

	/**
	 * Get any migration object records with the same original object ID and JSON data.
	 *
	 * @param int          $original_object_id The original object ID.
	 * @param array|object $json_data The JSON data.
	 *
	 * @return array
	 */
	public function get_duplicate_migration_objects( int $original_object_id, array|object $json_data ): array {
		// TODO 2025-03-19 - at this point, there isn't an easy way to create a MigrationObject class, due to the dependency on MigrationDataChest. Would be nice to switch the params to just MigrationObject, once we do.
		// phpcs:disable -- query is properly prepared.
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT 
    					mo.*, 
    					mdc.source_type, 
    					mdc.pointer_to_object_id, 
    					m.id as migration_id, 
    					m.namespace_and_class as migration_namespace_and_class, 
    					m.name as migration_name, 
    					m.version as migration_version 
					FROM migration_objects mo 
					    INNER JOIN migration_data_chests mdc ON mdc.id = mo.migration_data_chest_id 
					    INNER JOIN migrations m ON m.id = mdc.migration_id 
					WHERE mo.original_object_id = %d 
					  AND mo.json_data = %s",
				$original_object_id,
				wp_json_encode( $json_data )
			)
		);
		//phpcs:enable
	}

	/**
	 * Getting the migration objects that spawned the creation of a specific WordPress object involves getting any
	 * migration objects directly linked via the `migration_destination_sources` table. From there, at least
	 * one of those migration objects will be the original object that initially created the WordPress
	 * object. Returning any migration object records where that original object was used will give
	 * a full accounting of which migration objects were involved in shaping the WordPress object.
	 *
	 * @param int    $wordpress_object_id The WordPress object ID. (e.g. post ID, term ID, etc.).
	 * @param string $table_name The table names where the WordPress object ID is located.
	 *
	 * @return array
	 * @throws Exception Throws Exception if the given $table_name does not exist.
	 */
	public function get_source_migration_objects_for_wordpress_object( int $wordpress_object_id, string $table_name ): array {
		$table_column_ids = [];
		$table_column_ids = array_merge( $table_column_ids, WordPressData::get_instance()->get_column_ids_for_table( $table_name ) );

		$table_column_id_placeholders = implode( ',', array_fill( 0, count( $table_column_ids ), '%d' ) );

		// phpcs:disable -- query is properly prepared.
		$first_order_sources = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT 
    					mo.*,
    					mdc.source_type, 
    					mdc.pointer_to_object_id, 
    					m.id as migration_id, 
    					m.namespace_and_class as migration_namespace_and_class,
    					m.name as migration_name, 
    					m.version as migration_version 
					FROM migration_objects mo
						INNER JOIN migration_data_chests mdc ON mdc.id = mo.migration_data_chest_id 
					    INNER JOIN migrations m ON m.id = mdc.migration_id 
					WHERE mo.id IN (
						SELECT 
						    DISTINCT migration_object_id
						FROM migration_destination_sources
						WHERE wordpress_object_id = %d 
						  AND wordpress_table_column_id IN ( $table_column_id_placeholders )
    				)",
				$wordpress_object_id,
				...$table_column_ids
			)
		);

		// phpcs:enable

		$migration_object_ids_map = array_fill_keys( array_column( $first_order_sources, 'id' ), true );
		$second_order_sources     = [];

		foreach ( $first_order_sources as $first_order_source ) {
			$second_order_duplicate_sources = $this->get_duplicate_migration_objects(
				$first_order_source->original_object_id,
				json_decode( $first_order_source->json_data )
			);

			$second_order_sources = array_merge(
				$second_order_sources,
				array_filter(
					$second_order_duplicate_sources,
					// Filter out any second-order sources that are the same as the first-order source.
					// Remember, in order to filter out, the return value should be false.
					fn( $second_order_source ) => ! isset( $migration_object_ids_map[ $second_order_source->id ] )
				)
			);
		}

		return array_merge( $first_order_sources, $second_order_sources );
	}

	/**
	 * Sets the status of a migration.
	 *
	 * @param MigrationRunKey $run_key The migration run key.
	 * @param MigrationStatus $status The status to set.
	 *
	 * @return bool
	 */
	public function set_status( MigrationRunKey $run_key, MigrationStatus $status ): bool {
		$maybe_insert_migration_status = $this->wpdb->insert(
			'migration_status',
			[
				'migration_id' => $run_key->get_migration_id(),
				'status_id'    => $status->value,
			]
		);

		if ( is_wp_error( $maybe_insert_migration_status ) ) {
			return false;
		}

		return (bool) $maybe_insert_migration_status;
	}

	/**
	 * This function will create a Migration record at the specified version.
	 *
	 * @param Migration $migration The migration.
	 * @param int       $version The version.
	 *
	 * @return MigrationRunKey
	 * @throws Exception If unable to insert the Migration record.
	 */
	public function create_migration_record( Migration $migration, int $version ): MigrationRunKey {
		$maybe_inserted = $this->wpdb->insert(
			'migrations',
			[
				'namespace_and_class' => get_class( $migration ),
				'name'    => $migration->get_name(),
				'version' => $version,
			]
		);

		if ( false === $maybe_inserted ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Exception( $this->wpdb->last_error );
		}

		return new FinalMigrationRunKey(
			$this->wpdb->insert_id,
			$version,
			$migration
		);
	}

	/**
	 * This function will create a Migration record at version 1.
	 *
	 * @param Migration $migration The migration.
	 *
	 * @return MigrationRunKey
	 * @throws Exception If unable to insert the Migration record.
	 */
	public function create_initial_migration_record( Migration $migration ): MigrationRunKey {
		return $this->create_migration_record( $migration, 1 );
	}
}
