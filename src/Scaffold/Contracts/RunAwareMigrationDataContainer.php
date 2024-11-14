<?php

namespace Newspack\MigrationTools\Scaffold\Contracts;

interface RunAwareMigrationDataContainer extends MigrationDataContainer {

	/**
	 * Returns the Migration Run Key.
	 *
	 * @return MigrationRunKey
	 */
	public function get_run_key(): MigrationRunKey;

	/**
	 * Stores the underlying data that has been defined as needing to be migrated.
	 *
	 * @return bool
	 */
	public function store(): bool;

	/**
	 * Returns whether the underlying data has been stored to the database.
	 *
	 * @return bool
	 */
	public function has_been_stored(): bool;

	/**
	 * Returns the individual data objects that need to be migrated.
	 *
	 * @return RunAwareMigrationObject
	 */
	public function get_all(): iterable;

	/**
	 * Returns the Database ID of the migration data container.
	 *
	 * @return int
	 */
	public function get_id(): int;
}