<?php

namespace Newspack\MigrationTools\Scaffold;

interface JSONMigrationObject extends MigrationObject {

	/**
	 * Gets the JSON attribute pointer that can be used to uniquely identify a JSON object.
	 *
	 * @return string
	 */
	public function get_pointer_to_identifier(): string;
}