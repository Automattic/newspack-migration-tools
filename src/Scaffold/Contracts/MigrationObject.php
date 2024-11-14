<?php

namespace Newspack\MigrationTools\Scaffold\Contracts;

interface MigrationObject {

	/**
	 * Gets the underlying data that needs to be migrated.
	 *
	 * @return array|object
	 */
	public function get(): array|object;

	/**
	 * Gets the pointer to the property that uniquely identifies a migration object.
	 *
	 * @return string
	 */
	public function get_pointer_to_identifier(): string;

	/**
	 * Returns the ID that uniquely identifies the underlying data object.
	 *
	 * @return string|int
	 */
	public function get_data_id(): string|int;

	/**
	 * Returns Migration Data Set Container, the container for the migration objects.
	 *
	 * @return MigrationDataContainer
	 */
	public function get_container(): MigrationDataContainer;
}
