<?php

namespace Newspack\MigrationTools\Scaffold;

interface MigrationObject {

	/**
	 * Sets a key which identifies a specific version of a migration run.
	 *
	 * @param MigrationRunKey $run_key Migration run key.
	 *
	 * @return void
	 */
	public function set_run_key( MigrationRunKey $run_key ): void;

	/**
	 * Returns the migration run key.
	 *
	 * @return MigrationRunKey
	 */
	public function get_run_key(): MigrationRunKey;

	/**
	 * Gets the pointer to the property that uniquely identifies a migration object.
	 *
	 * @return string
	 */
	public function get_pointer_to_identifier(): string;

	/**
	 * Sets the data to be migrated.
	 *
	 * @param array|object $data Data to be migrated.
	 *
	 * @return void
	 */
	public function set( array|object $data ): void;

	/**
	 * Gets the data to be migrated.
	 *
	 * @return array|object
	 */
	public function get(): array|object;

	/**
	 * Stores the object in the database.
	 *
	 * @return bool
	 */
	public function store(): bool;

	/**
	 * Marks the object as processed.
	 *
	 * @return bool
	 */
	public function store_processed_marker(): bool;

	/**
	 * Returns whether the object has been processed.
	 *
	 * @return bool
	 */
	public function has_been_processed(): bool;

	/**
	 * This function provides an auditing mechanism for the migration process. This should be used if you would
	 * like to keep track of the source for a particular piece of data.
	 *
	 * @param string $table Table where the data is stored.
	 * @param string $column Column where the data is stored.
	 * @param int    $id ID of the row.
	 * @param string $source Source of the data.
	 *
	 * @return bool
	 */
	public function record_source( string $table, string $column, int $id, string $source ): bool;
}