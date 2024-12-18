<?php

namespace Newspack\MigrationTools\Scaffold\MigrationStates;

use Newspack\MigrationTools\Scaffold\Contracts\MigrationState;
use Newspack\MigrationTools\Scaffold\Enum\MigrationStatus;
use Newspack\MigrationTools\Scaffold\MigrationRunContext;

/**
 * Class CompletedMigrationState.
 */
class CompletedMigrationState extends AbstractMigrationState {

	/**
	 * The status of this migration state.
	 *
	 * @var MigrationStatus $migration_status The status of this migration state.
	 */
	protected MigrationStatus $migration_status = MigrationStatus::COMPLETED;

	/**
	 * Constructor.
	 *
	 * @param MigrationRunContext $migration_runner The migration run context.
	 */
	public function __construct( MigrationRunContext $migration_runner ) {
		parent::__construct( $migration_runner );
		$this->previous_run_key = $migration_runner->get_run_key(); // Save a reference to the current Migration Run Key.
	}

	/**
	 * Handles the current migration state, and returns what the next migration state should be.
	 *
	 * @return MigrationState|null
	 */
	public function settle(): ?MigrationState {
		$this->migration_activity->set_status( $this->get_run_key(), $this->get_state_status() );

		// TODO - Could add some cool functionality here. Perhaps a ping to #newspack-launch channel when long running migrations are completed?

		return null;
	}
}