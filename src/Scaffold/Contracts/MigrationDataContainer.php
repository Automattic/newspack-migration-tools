<?php

namespace Newspack\MigrationTools\Scaffold\Contracts;

interface MigrationDataContainer {

	/**
	 * Gets the pointer to the identifier.
	 *
	 * @return string
	 */
	public function get_pointer_to_identifier(): string;

	/**
	 * Gets all migration objects.
	 *
	 * @return MigrationObject[]
	 */
	public function get_all();

	/**
	 * Describes the source for this data set. Some values could be: query, csv, json, xml. Default is: query.
	 *
	 * @return string
	 */
	public function get_source_type(): string;

	/**
	 * Returns the underlying migration data set.
	 *
	 * @return iterable
	 */
	public function get_raw_data(): iterable;
}
