<?php

namespace Newspack\MigrationTools\Scaffold\Contracts;

use Newspack\MigrationTools\Scaffold\MigrationRunContext;

interface RunAwareMigrationDataChest extends MigrationDataChest {

	/**
	 * Returns the Migration Run Context.
	 *
	 * @return MigrationRunContext
	 */
	public function get_run_context(): MigrationRunContext;

	/**
	 * Returns the Migration Run Key.
	 *
	 * @return MigrationRunKey
	 */
	public function get_run_key(): MigrationRunKey;

	/**
	 * Returns the maximum allowed packet byte size.
	 *
	 * @return int
	 */
	public function get_max_allowed_packet_byte_size(): int;

	/**
	 * Returns whether the size of the underlying data is larger than the maximum allowed packet byte size.
	 *
	 * @return bool
	 */
	public function is_larger_than_max_allowed_packet(): bool;

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
