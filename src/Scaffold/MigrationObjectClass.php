<?php

namespace Newspack\MigrationTools\Scaffold;

use Newspack\MigrationTools\Scaffold\AbstractMigrationObject;

class MigrationObjectClass extends AbstractMigrationObject {

	public function __construct( MigrationRunKey $run_key, object|array $data, string $pointer_to_identifier = 'id' ) {
		parent::__construct( $run_key );
		$this->set( $data, $pointer_to_identifier );
	}
}