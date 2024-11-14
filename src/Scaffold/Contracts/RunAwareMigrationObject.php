<?php

namespace Newspack\MigrationTools\Scaffold\Contracts;

interface RunAwareMigrationObject extends MigrationObject {

	/**
	 * Returns a Run Aware Migration Data Set Container.
	 *
	 * @return RunAwareMigrationDataContainer
	 */
	public function get_container(): RunAwareMigrationDataContainer;

	/**
	 * Returns the Migration Run Key.
	 *
	 * @return MigrationRunKey
	 */
	public function get_run_key(): MigrationRunKey;

	/**
	 * Mark the underlying migration data as having been processed.
	 *
	 * @return bool
	 */
	public function mark_as_processed(): bool;

	/**
	 * Returns whether the underlying migration data has been processed.
	 *
	 * @return bool
	 */
	public function has_been_processed(): bool;

	/**
	 * Records the underlying migration data in the database.
	 *
	 * @return bool
	 */
	public function store_original_data(): bool;

	/**
	 * Returns whether the underlying migration data has been stored in the database.
	 *
	 * @return bool
	 */
	public function has_been_stored(): bool;

	/**
	 * Stores discretionary, custom, metadata related to this particular migration object.
	 *
	 * @param string $key The custom key.
	 * @param string $value The data to store.
	 *
	 * @return bool
	 */
	public function store_migration_meta( string $key, string $value ): bool;

	/**
	 * Returns the database ID for this particular Migration Object.
	 *
	 * @return int
	 */
	public function get_id(): int;
}