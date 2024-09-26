<?php

namespace Newspack\MigrationTools\Scaffold;

interface MigrationObjectInterface {

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
	 * Gets the data to be migrated.
	 *
	 * String $dot_path The dot separated path to the data.
	 *
	 * @return mixed
	 */
	public function get( string $dot_path ): mixed;

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