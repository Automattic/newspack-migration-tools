<?php

namespace Newspack\MigrationTools\Scaffold;

interface JSONMigrationObjects extends MigrationObjects {

	/**
	 * Saves the migration JSON to DB.
	 *
	 * @param string $full_path The full local path to the migration JSON.
	 * @param string $pointer_to_identifier The JSON attribute pointer that can be used to uniquely identify a JSON object.
	 *
	 * @return bool
	 */
	public function save( string $full_path, string $pointer_to_identifier = 'id' ): bool;

	/**
	 * Gets the JSON attribute pointer that can be used to uniquely identify a JSON object.
	 *
	 * @return string
	 */
	public function get_pointer_to_identifier(): string;
}