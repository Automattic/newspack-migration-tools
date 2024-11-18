<?php

namespace Newspack\MigrationTools\Scaffold;

use Newspack\MigrationTools\Scaffold\Contracts\MigrationDataChest;

/**
 * Represents a container for migration objects.
 */
abstract class AbstractMigrationDataChest implements MigrationDataChest {

	/**
	 * Data to be used to create the migration objects.
	 *
	 * @var iterable $data Data to be used to create the migration objects.
	 */
	protected iterable $data;

	/**
	 * Pointer to the property which uniquely identifies an object.
	 *
	 * @var string $pointer_to_identifier Pointer to the identifier.
	 */
	protected string $pointer_to_identifier;

	/**
	 * Describes the source for this data set. Some values could be: query, csv, json, xml. Default is: query.
	 *
	 * @var string $source_type Describes the source for this data set. Default: query.
	 */
	protected string $source_type = 'query';

	/**
	 * Constructor.
	 *
	 * @param iterable $data Data to be used to create the migration objects.
	 * @param string   $pointer_to_identifier Pointer to the identifier.
	 */
	public function __construct( iterable $data, string $pointer_to_identifier ) {
		$this->data                  = $data;
		$this->pointer_to_identifier = $pointer_to_identifier;
	}

	/**
	 * Gets the pointer to the identifier.
	 *
	 * @return string
	 */
	public function get_pointer_to_identifier(): string {
		return $this->pointer_to_identifier;
	}

	/**
	 * Returns the source type for the underlying data.
	 *
	 * @return string
	 */
	public function get_source_type(): string {
		return $this->source_type;
	}

	/**
	 * Returns the underlying data.
	 *
	 * @return iterable
	 */
	public function get_raw_data(): iterable {
		return $this->data;
	}
}
