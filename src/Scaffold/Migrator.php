<?php

namespace Newspack\MigrationTools\Scaffold;

use Exception;
use Newspack\MigrationTools\Scaffold\Contracts\Migration;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationState;
use Newspack\MigrationTools\Scaffold\Contracts\RunAwareMigrationObject;
use Newspack\MigrationTools\Scaffold\MigrationStates\CompletedMigrationState;
use Newspack\MigrationTools\Scaffold\MigrationStates\FailedMigrationState;
use Newspack\MigrationTools\Scaffold\MigrationStates\StartedMigrationState;
use Newspack\MigrationTools\Scaffold\MigrationStates\StartingMigrationState;
use Newspack\MigrationTools\Scaffold\MigrationStates\RunningMigrationState;
use Throwable;

/**
 * Class Migrator.
 */
class Migrator {

	/**
	 * The migration run context.
	 *
	 * @var MigrationRunContext|null $run_context The context for the migration run.
	 */
	private ?MigrationRunContext $run_context;

	/**
	 * Constructor.
	 *
	 * Defines a shutdown process so that we at least record unexpected shutdowns.
	 */
	public function __construct() {
		register_shutdown_function(
			function () {
				$error = error_get_last();

				if ( null === $error ) {
					return;
				}

				$this->run_context->transition(
					( new FailedMigrationState( $this->run_context ) )->set_error(
						new Exception( "Unexpected shutdown: {$error['message']} in {$error['file']} on line #{$error['line']}", 0 )
					)->stop( true )
				);
				$this->run_context->settle();
			}
		);
	}


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
		$this->run_context = new MigrationRunContext( $migration );

		try {
			$this->run_context->transition( new StartingMigrationState( $this->run_context ) );
			$this->run_context->settle();

			$this->run_context->transition( new StartedMigrationState( $this->run_context ) );
			$this->run_context->settle();

			$need_to_run_only_once = true;
			foreach ( $this->run_context->get_data_chest()->get_all() as $migration_object ) {

				if ( $need_to_run_only_once ) {
					$this->run_context->transition( new RunningMigrationState( $this->run_context ) );
					$need_to_run_only_once = false;
				}

				$this->run_context->settle();

				if ( ! $this->run_context->is_running() ) {
					break;
				}

				// phpcs:ignore Squiz.PHP.CommentedOutCode.Found, Squiz.WhiteSpace.SuperfluousWhitespace.EndLine
				/* @var RunAwareMigrationObject $migration_object */
				$migration_object->store_original_data();

				$result = $migration->command( $migration_object );

				if ( $result instanceof MigrationState ) {
					$this->run_context->transition( $result );
				}
			}
		} catch ( Throwable $throwable ) {
			// Unexpected exception. Failed state has been recorded. Set `stop` so that the script stops running.
			$this->run_context->transition(
				( new FailedMigrationState( $this->run_context ) )->set_error( $throwable )->stop( true )
			);
			$this->run_context->settle();
		}

		$this->run_context->transition( new CompletedMigrationState( $this->run_context ) );
		$this->run_context->settle();
	}
}
