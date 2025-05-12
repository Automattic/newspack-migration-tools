<?php

namespace Newspack\MigrationTools\Scaffold;

use Newspack\MigrationTools\Scaffold\Contracts\MigrationObject as MigrationObjectContract;

/**
 * JSON Migration Data Container.
 */
class JSONMigrationDataChest extends AbstractMigrationDataChest {

	/**
	 * The source type for the data container.
	 *
	 * @var string $source_type The source type for the data container.
	 */
	protected string $source_type = 'JSON';

	/**
	 * Constructor.
	 *
	 * @param string $json JSON string.
	 * @param string $pointer_to_identifier Pointer to the identifier.
	 */
	public function __construct( string $json, string $pointer_to_identifier ) {
		parent::__construct( json_decode( $json, true ), $pointer_to_identifier );
	}

	/**
	 * Gets all migration objects.
	 *
	 * @return MigrationObjectContract[]
	 */
	public function get_all(): array {
		return array_map(
			fn( $json ) => new MigrationObject( $json, $this->get_pointer_to_identifier(), $this ),
			(array) $this->get_raw_data()
		);
	}
}
