<?php

namespace Newspack\MigrationTools\Scaffold\MigrationStates;

use Newspack\MigrationTools\Scaffold\Contracts\MigrationState;
use Newspack\MigrationTools\Scaffold\Enum\MigrationStatus;
use Newspack\MigrationTools\Scaffold\MigrationRunContext;
use Throwable;
use WP_Error;

/**
 * Class FailedMigrationState.
 */
class FailedMigrationState extends AbstractMigrationState {

	/**
	 * The status of this migration state.
	 *
	 * @var MigrationStatus $migration_status The status of this migration state
	 */
	protected MigrationStatus $migration_status = MigrationStatus::FAILED;

	/**
	 * The error that stopped the migration.
	 *
	 * @var WP_Error|Throwable|null $error The error that stopped the migration.
	 */
	private WP_Error|Throwable|null $error;

	/**
	 * Flag which determines whether the migration should continue or not, in spite of failure.
	 *
	 * @var bool $stop Whether to stop migrating objects.
	 */
	private bool $stop = true;

	/**
	 * Flag which determines whether a message should be sent conveying the Migration's failure.
	 *
	 * @var bool $notify Whether a notification should be sent.
	 */
	private bool $notify = true;

	/**
	 * Constructor.
	 *
	 * @param MigrationRunContext $migration_run_context The Migration Run Context.
	 */
	public function __construct( MigrationRunContext $migration_run_context ) {
		parent::__construct( $migration_run_context );
		$this->previous_run_key = $migration_run_context->get_run_key(); // Save a reference to the current Migration Run Key.
	}

	/**
	 * Handles the current migration state, and returns what the next migration state should be.
	 *
	 * @return MigrationState|null
	 */
	public function settle(): ?MigrationState {
		$this->migration_activity->set_status( $this->get_run_key(), $this->get_state_status() );

		/*
		 * FailedMigrationState is a very special case.
		 * Not only can it write state information to its own table, but we also send out a Slack message when it fails.
		 */

		$this->print_error();
		$this->store_error();
		$this->send_slack_message();

		if ( $this->should_stop() ) {
			// Stop any further progress.
			die();
		}

		return null;
	}

	/**
	 * Set the error that caused the migration command to fail/stop.
	 *
	 * @param Throwable|WP_Error $error The error that caused the migration command to fail/stop.
	 *
	 * @return FailedMigrationState
	 */
	public function set_error( Throwable|WP_Error $error ): FailedMigrationState {
		$this->error = $error;

		return $this;
	}

	/**
	 * Function to control whether the migration should continue or stop in spite of failure.
	 *
	 * @param bool $flag True or false. Default false.
	 *
	 * @return $this
	 */
	public function stop( bool $flag = false ): FailedMigrationState {
		$this->stop = $flag;

		return $this;
	}

	/**
	 * Returns flag controlling whether the migration should continue or not.
	 *
	 * @return bool
	 */
	public function should_stop(): bool {
		return $this->stop;
	}

	/**
	 * Function to control whether a message should be sent conveying the migration's failure.
	 *
	 * @param bool $flag True or false. Default false.
	 *
	 * @return $this
	 */
	public function notify( bool $flag = false ): FailedMigrationState {
		$this->notify = $flag;

		return $this;
	}

	/**
	 * Prints the error message to the console.
	 *
	 * @return void
	 */
	private function print_error(): void {
		if ( isset( $this->error ) ) {
			if ( $this->error instanceof Throwable ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- custom error logging in use.
				error_log( "{$this->error->getMessage()} in {$this->error->getFile()} on line {$this->error->getLine()}" );
			} else { // WP_Error.
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- custom error logging in use.
				error_log( $this->error->get_error_message() );
			}
		}
	}

	/**
	 * Stored the exception in the database.
	 *
	 * @return void
	 */
	private function store_error(): void {
		if ( isset( $this->error ) ) {
			// TODO - write error to DB
			if ( $this->error instanceof Throwable ) {

			} else { // WP_Error

			}
		}
	}

	/**
	 * Sends a Slack message regarding the failing of the Migration Command.
	 *
	 * @return void
	 */
	private function send_slack_message(): void {
		// TODO - send Slack message that this failed.
		if ( $this->notify ) {

		}
	}
}
