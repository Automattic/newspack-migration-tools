<?php

namespace Newspack\MigrationTools\Scaffold\MigrationStates;

use Newspack\MigrationTools\Scaffold\Contracts\MigrationState;
use Newspack\MigrationTools\Scaffold\Enum\MigrationStatus;
use Newspack\MigrationTools\Scaffold\MigrationRunContext;
use Newspack\MigrationTools\Scaffold\UnprocessedMigrationDataChestWrapper;

/**
 * Represents a migration state that has been started.
 */
class StartedMigrationState extends AbstractMigrationState {

	/**
	 * The status of this migration state.
	 *
	 * @var MigrationStatus $migration_status The status of this migration state.
	 */
	protected MigrationStatus $migration_status = MigrationStatus::STARTED;

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
		if ( $this->migration_activity->set_status( $this->previous_run_key, MigrationStatus::STARTED ) ) {

			$data_container = new UnprocessedMigrationDataChestWrapper(
				$this->migration_run_context->get_migration()->get_data_chest(),
				$this->get_run_key()
			);

			$data_container->store();

			if ( $data_container->has_been_stored() ) {
				$this->migration_run_context->set_data_chest(
					$data_container
				);
			}
		}

		return null;
	}
}
