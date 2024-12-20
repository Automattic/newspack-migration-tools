<?php

namespace Newspack\MigrationTools\Scaffold\Contracts;

use Exception;
use Newspack\MigrationTools\Scaffold\Enum\MigrationStatus;

interface MigrationState {

	/**
	 * Handles the current migration state, and returns what the next migration state should be.
	 *
	 * @return MigrationState|null
	 */
	public function settle(): ?MigrationState;

	/**
	 * Returns the Migration Run Key.
	 *
	 * @return MigrationRunKey
	 */
	public function get_run_key(): MigrationRunKey;

	/**
	 * Returns the Migration Status.
	 *
	 * @return MigrationStatus
	 */
	public function get_state_status(): MigrationStatus;

	/**
	 * Returns the ID value of the Migration Status.
	 *
	 * @return int
	 */
	public function get_state_status_id(): int;
}