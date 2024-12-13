<?php

namespace Newspack\MigrationTools\Scaffold\Contracts;

interface Migration {

	/**
	 * Sets the name of this particular command.
	 *
	 * @param string $name Command name.
	 *
	 * @return void
	 */
	public function set_name( string $name ): void;

	/**
	 * Returns the name of this particular command.
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * This function houses the logic for the command.
	 *
	 * @param RunAwareMigrationObject $migration_object The object to perform the migration on.
	 *
	 * @return bool|MigrationState|\WP_Error|null
	 * @throws \Exception If an error occurs.
	 */
	public function command( RunAwareMigrationObject $migration_object ): bool|MigrationState|\WP_Error|null;

	/**
	 * Returns the migration objects.
	 *
	 * @return MigrationDataChest
	 */
	public function get_data_chest(): MigrationDataChest;
}
