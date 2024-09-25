<?php

namespace Newspack\MigrationTools\Scaffold;

final class FinalMigrationRunKey implements MigrationRunKey {

	private $run_key;

	public function __construct( string $run_key ) {
		$this->run_key = $run_key;
	}

	/**
	 * @inheritDoc
	 */
	public function get(): string {
		return $this->run_key;
	}
}