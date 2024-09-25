<?php

namespace Newspack\MigrationTools\Scaffold;

use Newspack\MigrationTools\Scaffold\MigrationObjects;

abstract class AbstractMigrationObjects implements MigrationObjects {

	private MigrationRunKey $run_key;

	protected iterable $data;

	public function __construct( iterable $data, MigrationRunKey $run_key ) {
		$this->data    = $data;
		$this->run_key = $run_key;
	}

	/**
	 * @inheritDoc
	 */
	public function set_run_key( MigrationRunKey $run_key ): void {
		$this->run_key = $run_key;
	}

	/**
	 * @inheritDoc
	 */
	public function get_run_key(): MigrationRunKey {
		return $this->run_key;
	}

	/**
	 * Returns all processed migration objects.
	 *
	 * @return MigrationObject[]
	 */
	public function get_processed(): iterable {
		foreach ( $this->get_all() as $migration_object ) {
			if ( $migration_object->has_been_processed() ) {
				yield $migration_object;
			}
		}
	}

	/**
	 * Returns all unprocessed migration objects.
	 *
	 * @return MigrationObject[]
	 */
	public function get_unprocessed(): iterable {
		foreach ( $this->get_all() as $migration_object ) {
			if ( ! $migration_object->has_been_processed() ) {
				yield $migration_object;
			}
		}
	}
}