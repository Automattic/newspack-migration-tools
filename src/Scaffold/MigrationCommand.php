<?php

namespace Newspack\MigrationTools\Scaffold;

interface MigrationCommand {

	/**
	 * Returns the application key. This is a unique key that identifies this implementation in the WordPress options table.
	 *
	 * @return string
	 */
	public function get_application_key(): string;

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
	 * @param MigrationObjects $migration_objects The objects to perform the migration on.
	 *
	 * @return bool|\WP_Error
	 * @throws \Exception If an error occurs.
	 */
	public function command( MigrationObjects $migration_objects ): bool|\WP_Error;
}