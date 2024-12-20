<?php

namespace Newspack\MigrationTools\Scaffold\Contracts;

use Newspack\MigrationTools\Scaffold\Enum\MigrationStatus;

interface MigrationRunKey {

	/**
	 * Returns the ID of the migration run key.
	 *
	 * @return int
	 */
	public function get_migration_id(): int;

	/**
	 * Returns the version of the migration run key.
	 *
	 * @return int
	 */
	public function get_migration_version(): int;

	/**
	 * Returns the enum status of the migration run key.
	 *
	 * @return MigrationStatus
	 */
	public function get_migration_status(): MigrationStatus;

	/**
	 * Returns the migration object.
	 *
	 * @return Migration
	 */
	public function get_migration(): Migration;
}
