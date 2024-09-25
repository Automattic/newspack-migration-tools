<?php

namespace Newspack\MigrationTools\Scaffold;

interface MigrationRunKey {

	/**
	 * Returns the migration run key.
	 *
	 * @return string
	 */
	public function get(): string;
}
