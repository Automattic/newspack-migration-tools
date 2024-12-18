<?php

namespace Newspack\MigrationTools\Scaffold\MigrationStates;

use Newspack\MigrationTools\Scaffold\Contracts\MigrationRunKey;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationState;
use Newspack\MigrationTools\Scaffold\Enum\MigrationStatus;
use Newspack\MigrationTools\Scaffold\MigrationActivity;
use Newspack\MigrationTools\Scaffold\MigrationRunContext;

/**
 * Class AbstractMigrationState
 */
abstract class AbstractMigrationState implements MigrationState {

	/**
	 * Context for the migration run.
	 *
	 * @var MigrationRunContext
	 */
	protected MigrationRunContext $migration_run_context;

	/**
	 * Class which facilitates obtaining the current migration activity.
	 *
	 * @var MigrationActivity
	 */
	protected MigrationActivity $migration_activity;

	/**
	 * The status of the migration state.
	 *
	 * @var MigrationStatus
	 */
	protected MigrationStatus $migration_status;

	/**
	 * The previous run key.
	 *
	 * @var MigrationRunKey
	 */
	protected MigrationRunKey $previous_run_key;

	/**
	 * The current run key.
	 *
	 * @var MigrationRunKey
	 */
	protected MigrationRunKey $current_run_key;

	/**
	 * AbstractMigrationState constructor.
	 *
	 * @param MigrationRunContext $migration_runner The migration run context.
	 */
	public function __construct( MigrationRunContext $migration_runner ) {
		$this->migration_run_context = $migration_runner;
		$this->migration_activity    = new MigrationActivity();
	}

	/**
	 * Returns the Migration Run Key.
	 *
	 * @return MigrationRunKey
	 */
	public function get_run_key(): MigrationRunKey {
		return $this->current_run_key ?? $this->previous_run_key;
	}

	/**
	 * Returns the Migration Status.
	 *
	 * @return MigrationStatus
	 */
	public function get_state_status(): MigrationStatus {
		return $this->migration_status;
	}

	/**
	 * Returns the ID value of the Migration Status.
	 *
	 * @return int
	 */
	public function get_state_status_id(): int {
		return $this->migration_status->value;
	}
}