<?php

namespace Newspack\MigrationTools\Scaffold\MigrationStates;

use Exception;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationState;
use Newspack\MigrationTools\Scaffold\Enum\MigrationStatus;
use Newspack\MigrationTools\Scaffold\FinalMigrationRunKey;

/**
 * Class StartingMigrationState
 */
class StartingMigrationState extends AbstractMigrationState {

	/**
	 * The status of the migration state.
	 *
	 * @var MigrationStatus
	 */
	protected MigrationStatus $migration_status = MigrationStatus::STARTING;

	/**
	 * Handles the current migration state, and returns what the next migration state should be.
	 *
	 * @return MigrationState|null
	 * @throws Exception If the migration activity cannot be created.
	 */
	public function settle(): ?MigrationState {

		$latest_activity = $this->migration_activity->get_latest( $this->migration_run_context->get_migration() );

		if ( ! $latest_activity ) {
			$this->current_run_key = $this->migration_activity->create_initial_record( $this->migration_run_context->get_migration() );
		} else {
			$current_status = MigrationStatus::tryFrom( $latest_activity->status_id );

			if ( in_array( $current_status, [ MigrationStatus::FAILED, MigrationStatus::COMPLETED ], true ) ) {
				$this->current_run_key = $this->migration_activity->create_record( $this->migration_run_context->get_migration(), $latest_activity->version + 1 );
			} else {
				$this->current_run_key = new FinalMigrationRunKey( $latest_activity->id, $latest_activity->version, $this->migration_run_context->get_migration() );
			}
		}

		return null;
	}
}