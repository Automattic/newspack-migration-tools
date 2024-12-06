<?php

namespace Newspack\MigrationTools\Scaffold;

use Exception;
use Newspack\MigrationTools\Scaffold\Contracts\Migration;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationState;
use Newspack\MigrationTools\Scaffold\Contracts\RunAwareMigrationObject;
use Newspack\MigrationTools\Scaffold\MigrationStates\CompletedMigrationState;
use Newspack\MigrationTools\Scaffold\MigrationStates\StartedMigrationState;
use Newspack\MigrationTools\Scaffold\MigrationStates\StartingMigrationState;
use Newspack\MigrationTools\Scaffold\MigrationStates\RunningMigrationState;

/**
 * Class Migrator.
 */
class Migrator {

	/**
	 * Resumes the migration run.
	 *
	 * @param Migration $migration The migration to resume.
	 *
	 * @throws Exception If an error occurs.
	 */
	public function resume( Migration $migration ): void {
		// TODO Implement this method.
	}

	/**
	 * Restarts the migration run.
	 *
	 * @param Migration $migration The migration to restart.
	 *
	 * @throws Exception If an error occurs.
	 */
	public function restart( Migration $migration ): void {
		$this->cancel( $migration );
		$this->start( $migration );
	}

	/**
	 * Cancels the migration run.
	 *
	 * @param Migration $migration The migration to cancel.
	 *
	 * @return void
	 */
	public function cancel( Migration $migration ): void {
		// TODO Implement this method.
	}

	/**
	 * Starts the migration run.
	 *
	 * @param Migration $migration The migration to start.
	 *
	 * @throws Exception If an error occurs.
	 */
	public function start( Migration $migration ): void {
		$migration_run_context = new MigrationRunContext( $migration );
		$migration_run_context->transition( new StartingMigrationState( $migration_run_context ) );
		$migration_run_context->settle();

		$migration_run_context->transition( new StartedMigrationState( $migration_run_context ) );
		$migration_run_context->settle();

		$need_to_run_only_once = true;
		foreach ( $migration_run_context->get_container()->get_all() as $migration_object ) {

			if ( $need_to_run_only_once ) {
				$migration_run_context->transition( new RunningMigrationState( $migration_run_context ) );
				$need_to_run_only_once = false;
			}

			$migration_run_context->settle();

			if ( ! $migration_run_context->is_running() ) {
				break;
			}

			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found, Squiz.WhiteSpace.SuperfluousWhitespace.EndLine
			/* @var RunAwareMigrationObject $migration_object */
			$migration_object->store_original_data();
			$result = $migration->command( $migration_object );

			if ( $result instanceof MigrationState ) {
				$migration_run_context->transition( $result );
			}
		}

		$migration_run_context->transition( new CompletedMigrationState( $migration_run_context ) );
		$migration_run_context->settle();
	}
}
