<?php

namespace Newspack\MigrationTools\Scaffold;

use Newspack\MigrationTools\Scaffold\Contracts\MigrationDataChest;

/**
 * Represents a migration object.
 */
class MigrationObject extends AbstractMigrationObject {

	/**
	 * Constructor.
	 *
	 * @param object|array       $data Data to be used to create the migration object.
	 * @param string             $pointer_to_identifier Pointer to the identifier.
	 * @param MigrationDataChest $data_container The data container.
	 */
	public function __construct( object|array $data, string $pointer_to_identifier, MigrationDataChest $data_container ) {
		parent::__construct( $data, $pointer_to_identifier, $data_container );
	}
}
