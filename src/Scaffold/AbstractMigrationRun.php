<?php

namespace Newspack\MigrationTools\Scaffold;

use Newspack\MigrationTools\Scaffold\MigrationRun;

abstract class AbstractMigrationRun implements MigrationRun {

	protected $name;

	protected FinalMigrationRunKey $run_key;

	/**
	 * @inheritDoc
	 */
	public function get_application_key(): string {
		return '_data_migration';
	}

	/**
	 * @inheritDoc
	 */
	public function set_name( string $name ): void {
		$this->name = sanitize_title( $name );
	}

	/**
	 * @inheritDoc
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * @inheritDoc
	 *
	 * @throws \Exception If an error occurs.
	 */
	public function start(): void {
		$this->command( $this->get_migration_objects() );
	}

	/**
	 * @inheritDoc
	 */
	public function resume(): void {
		// TODO: Implement resume() method.
	}

	/**
	 * @inheritDoc
	 *
	 * @throws \Exception If an error occurs.
	 */
	public function restart(): void {
		$this->cancel( false );
		$this->start();
	}

	/**
	 * @inheritDoc
	 */
	public function cancel( bool $delete_data ): void {
		// TODO: Implement cancel() method.
	}

	/**
	 * @inheritDoc
	 */
	public function get_run_key(): FinalMigrationRunKey {
		return new FinalMigrationRunKey( $this->get_name() . '1' );
	}
}