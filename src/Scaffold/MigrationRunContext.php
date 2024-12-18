<?php

namespace Newspack\MigrationTools\Scaffold;

use Newspack\MigrationTools\Scaffold\Contracts\Migration;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationState;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationRunKey;
use Newspack\MigrationTools\Scaffold\Contracts\RunAwareMigrationDataChest;
use Newspack\MigrationTools\Scaffold\MigrationStates\RunningMigrationState;
use Newspack\MigrationTools\Scaffold\MigrationStates\StartedMigrationState;

/**
 * Class MigrationRunContext.
 */
class MigrationRunContext {

	/**
	 * The migration.
	 *
	 * @var Migration $migration The migration.
	 */
	private Migration $migration;

	/**
	 * The migration state.
	 *
	 * @var MigrationState $migration_state The migration state.
	 */
	private MigrationState $migration_state;

	/**
	 * Whether the state has been settled.
	 *
	 * @var bool $settled_state Whether the state has been settled.
	 */
	private bool $settled_state = false;

	/**
	 * The data container.
	 *
	 * @var RunAwareMigrationDataChest $data_container The data container.
	 */
	private RunAwareMigrationDataChest $data_container;

	/**
	 * Constructor.
	 *
	 * @param Migration $migration The migration.
	 */
	public function __construct( Migration $migration ) {
		$this->migration = $migration;
	}

	/**
	 * Handles the transition to the next migration state.
	 *
	 * @param MigrationState $migration_state The migration state.
	 *
	 * @return void
	 */
	public function transition( MigrationState $migration_state ): void {
		if ( ! isset( $this->migration_state ) || get_class( $migration_state ) !== get_class( $this->migration_state ) ) {
			$this->migration_state = $migration_state;
			$this->settled_state   = false;
		}
	}

	/**
	 * Handles the settling of the migration state.
	 *
	 * @return void
	 */
	public function settle(): void {
		if ( ! $this->settled_state ) {
			$this->migration_state->settle();
			$this->settled_state = true;
		}
	}

	/**
	 * Returns whether the migration is running.
	 *
	 * @return bool Whether the migration is running.
	 */
	public function is_running(): bool {
		return $this->migration_state instanceof RunningMigrationState;
	}

	/**
	 * Returns the migration.
	 *
	 * @return Migration The migration.
	 */
	public function get_migration(): Migration {
		return $this->migration;
	}

	/**
	 * Returns the current Migration Run Key.
	 *
	 * @return MigrationRunKey The migration run key.
	 */
	public function get_run_key(): MigrationRunKey {
		return $this->migration_state->get_run_key();
	}

	/**
	 * Handles the setting of the Migration Data Container. This can only be set if the migration state is Started.
	 *
	 * @param RunAwareMigrationDataChest $data_container The data container.
	 *
	 * @return bool
	 */
	public function set_container( RunAwareMigrationDataChest $data_container ): bool {
		if ( $this->migration_state instanceof StartedMigrationState ) {
			$this->data_container = $data_container;
			return true;
		}

		return false;
	}

	/**
	 * Returns the Migration Data Container.
	 *
	 * @return RunAwareMigrationDataChest The data container.
	 */
	public function get_data_chest(): RunAwareMigrationDataChest {
		return $this->data_container;
	}
}
