<?php

namespace Newspack\MigrationTools\Command;

interface WpCliCommandInterface {

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self;

	/**
	 * Get CLI commands for the class.
	 *
	 * The array should contain an array of arrays. Each entry is a command.
	 * Each command should have 2 or 3 elements that are the same as you would
	 * pass to WP_CLI::add_command().
	 * See https://make.wordpress.org/cli/handbook/references/internal-api/wp-cli-add-command/
	 *
	 * @return array
	 */
	public function get_cli_commands(): array;

}
