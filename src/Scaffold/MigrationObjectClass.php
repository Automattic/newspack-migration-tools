<?php

namespace Newspack\MigrationTools\Scaffold;

class MigrationObjectClass extends AbstractMigrationObject {

	public function __construct( MigrationRunKey $run_key, object|array $data, string $pointer_to_identifier ) {
		parent::__construct( $run_key );
		$this->set( $data, $pointer_to_identifier );
	}
}
