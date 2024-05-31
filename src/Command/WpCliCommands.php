<?php

namespace Newspack\MigrationTools\Command;

class WpCliCommands {

	public static function get_classes_with_cli_commands(): array {
		$classes_with_cli_commands = [
			AttachmentsMigrator::class,
		];

		return apply_filters( 'newspack_migration_tools_command_classes', $classes_with_cli_commands );
	}

}
