<?php

namespace Newspack\MigrationTools\Scaffold;

interface MigrationObjects {

	/**
	 * Sets the migration run key.
	 *
	 * @param MigrationRunKey $run_key Migration run key.
	 */
	public function set_run_key( MigrationRunKey $run_key ): void;

	/**
	 * Gets the migration run key.
	 *
	 * @return MigrationRunKey
	 */
	public function get_run_key(): MigrationRunKey;

	/**
	 * Gets all migration objects.
	 *
	 * @return MigrationObject[]
	 */
	public function get_all(): iterable;

	/**
	 * Gets all processed migration objects.
	 *
	 * @return MigrationObject[]
	 */
	public function get_processed(): iterable;

	/**
	 * Gets all unprocessed migration objects.
	 *
	 * @return MigrationObject[]
	 */
	public function get_unprocessed(): iterable;
}