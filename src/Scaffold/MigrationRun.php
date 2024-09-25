<?php

namespace Newspack\MigrationTools\Scaffold;

interface MigrationRun extends MigrationCommand {

	/**
	 * Starts the migration run.
	 *
	 * @return void
	 */
	public function start(): void;

	/**
	 * Resumes the migration run if it has been interrupted.
	 *
	 * @return void
	 */
	public function resume(): void;

	/**
	 * Restarts the migration run. This will delete all data and start from scratch.
	 *
	 * @return void
	 */
	public function restart(): void;

	/**
	 * Cancels the migration run.
	 *
	 * @param bool $delete_data Whether to delete the data that has been migrated.
	 *
	 * @return void
	 */
	public function cancel( bool $delete_data ): void;

	/**
	 * Returns the migration run key.
	 *
	 * @return MigrationRunKey
	 */
	public function get_run_key(): MigrationRunKey;

	/**
	 * Returns the migration objects.
	 *
	 * @return MigrationObjects
	 */
	public function get_migration_objects(): MigrationObjects;
}
