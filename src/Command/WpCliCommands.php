<?php

namespace Newspack\MigrationTools\Command;

class WpCliCommands {

	/**
	 * Register classes with CLI commands.
	 *
	 * @return array
	 */
	public static function get_classes_with_cli_commands(): array {
		// Add class names that implement WpCliCommandInterface here to register them with WP CLI.
		$classes_with_cli_commands = [
			AttachmentsMigrator::class,
			NewspaperThemeCommand::class,
		];

		return apply_filters( 'newspack_migration_tools_command_classes', $classes_with_cli_commands );
	}
}
