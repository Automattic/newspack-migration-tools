<?php

namespace Newspack\MigrationTools\Scaffold;

use Exception;
use Newspack\MigrationTools\Scaffold\Contracts\Migration;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationRunKey;
use Newspack\MigrationTools\Scaffold\Enum\MigrationStatus;

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
				FROM migration m 
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
					m.name, 
					m.version, 
					ms.status_id 
				FROM migrations m 
				    LEFT JOIN migration_status ms 
				        ON m.ID = ms.migration_id 
				WHERE m.name = %s 
				  AND m.version = ( 
				  	SELECT MAX( version ) 
				  	FROM migrations 
				  	WHERE name = %s 
				  	) 
				ORDER BY ms.created_at DESC
				LIMIT 1',
				$migration->get_name(), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
					ORDER BY ms.created_at DESC, m.created_at DESC',
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
	 * @param Migration $migration The migration.
	 *
	 * @return object|null
	 */
	public function get_latest_status( Migration $migration ): object|null {
		// phpcs:disable
		$latest_status = $this->wpdb->get_row(
			$this->wpdb->prepare(
				'SELECT 
    					m.id as migration_id, 
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
					WHERE m.name = %s 
					ORDER BY ms.created_at DESC, m.created_at DESC 
					LIMIT 1',
				$migration->get_name()
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
