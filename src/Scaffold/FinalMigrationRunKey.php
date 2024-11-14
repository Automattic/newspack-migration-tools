<?php

namespace Newspack\MigrationTools\Scaffold;

use Newspack\MigrationTools\Scaffold\Enum\MigrationStatus;
use Newspack\MigrationTools\Scaffold\Contracts\Migration;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationRunKey;

/**
 * Represents a final migration run key.
 */
final class FinalMigrationRunKey implements MigrationRunKey {

	/**
	 * The ID of the migration being executed.
	 *
	 * @var int The ID of the migration being executed.
	 */
	private int $migration_id;

	/**
	 * The version of the migration being executed.
	 *
	 * @var int The version of the migration being executed.
	 */
	private int $migration_version;

	/**
	 * The current migration status.
	 *
	 * @var MigrationStatus $status The current migration status.
	 */
	private MigrationStatus $status;

	/**
	 * The migration being executed.
	 *
	 * @var Migration $migration The migration being executed.
	 */
	private Migration $migration;

	/**
	 * Constructor.
	 *
	 * @param int       $migration_id The ID of the migration being executed.
	 * @param int       $migration_version The version of the migration being executed.
	 * @param Migration $migration The migration being executed.
	 */
	public function __construct( int $migration_id, int $migration_version, Migration $migration ) {
		$this->migration_id      = $migration_id;
		$this->migration_version = $migration_version;
		$this->migration         = $migration;
	}


	/**
	 * Returns the ID of the migration run key.
	 *
	 * @return int The ID of the migration run key.
	 */
	public function get_migration_id(): int {
		return $this->migration_id;
	}

	/**
	 * Returns the version of the migration run key.
	 *
	 * @return int
	 */
	public function get_migration_version(): int {
		return $this->migration_version;
	}

	/**
	 * Returns the enum status of the migration run key.
	 *
	 * @return MigrationStatus
	 */
	public function get_migration_status(): MigrationStatus {
		return $this->status;
	}

	/**
	 * The migration object.
	 *
	 * @return Migration The migration object.
	 */
	public function get_migration(): Migration {
		return $this->migration;
	}
}
