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
			BlockTransformerCommand::class,
			CampaignsMigrator::class,
			ContentConverterPluginMigrator::class,
			CraftCMSMigrator::class,
			CssMigrator::class,
			EnviraGalleryMigrator::class,
			FooGalleryMigrator::class,
			GhostCMSMigrator::class,
			MenusMigrator::class,
			MetaToContentMigrator::class,
			NewspaperThemeCommand::class,
			OriginalPermalinkCommands::class,
			OriginalValueCommands::class,
			PostFeaturedVideoMigrator::class,
			PostsMigrator::class,
			SettingsMigrator::class,
			ShortcodesMigrator::class,
			WooCommMigrator::class,
		];

		return apply_filters( 'newspack_migration_tools_command_classes', $classes_with_cli_commands );
	}
}
