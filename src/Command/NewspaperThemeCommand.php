<?php

namespace Newspack\MigrationTools\Command;

use Newspack\MigrationTools\Log\CliLogger;
use Newspack\MigrationTools\Logic\NewspaperThemeHelper;
use Newspack\MigrationTools\Util\MigrationMetaForCommand;

class NewspaperThemeCommand implements WpCliCommandInterface {

	use WpCliCommandTrait;

	/**
	 * {@inheritDoc}
	 */
	public static function get_cli_commands(): array {
		return [
			[
				'newspack-migration-tools newspaper-theme-list-post-settings',
				[
					__CLASS__,
					'cmd_list_post_settings',
				],
				[
					'shortdesc' => 'Lists all keys from `td_post_theme_settings` and how many posts have each key. Handy for identifying fields in use.',
				],
			],
			[
				'newspack-migration-tools newspaper-theme-migrate-post-fields',
				[
					__CLASS__,
					'cmd_migrate_post_fields',
				],
				[
					'shortdesc' => 'Migrates the `td_post_theme_settings` meta to relevant Newspack fields',
					'synopsis'  => [
						[
							'type'        => 'flag',
							'name'        => 'refresh-existing',
							'description' => 'Refreshes existing posts that have already been migrated.',
							'optional'    => true,
						],
					],
				],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public static function cmd_migrate_post_fields( array $pos_args, array $assoc_args ): void {
		$command_meta     = new MigrationMetaForCommand( __FUNCTION__, 1 );
		$helper           = new NewspaperThemeHelper();
		$refresh_existing = $assoc_args['refresh-existing'] ?? false;
		$command_meta->set_refresh_existing( $refresh_existing );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$posts = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = %s", 'td_post_theme_settings' ) );
		foreach ( $posts as $post ) {
			$newspaper_theme_post_metadata = maybe_unserialize( $post->meta_value );
			$helper->migrate_subtitle_to_newspack_subtitle( $post->post_id, $newspaper_theme_post_metadata, $command_meta );
		}
	}

	/**
	 * Lists all keys from `td_post_theme_settings` and how many posts use each key.
	 *
	 * This command doesn't migrate anything –it just lists the fields in use on posts by the Newspaper Theme.
	 *
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_list_post_settings( array $pos_args, array $assoc_args ): void {
		$theme_settings = [];

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$posts = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = %s", 'td_post_theme_settings' ) );
		foreach ( $posts as $post ) {
			$settings = maybe_unserialize( $post->meta_value );
			foreach ( $settings as $key => $value ) {
				if ( ! isset( $theme_settings[ $key ] ) ) {
					$theme_settings[ $key ] = 0;
				}
				$theme_settings[ $key ]++;
			}
		}
		CliLogger::log( 'These keys were found in `td_post_theme_settings` and the number of posts that have each key:' );
		array_map(
			function ( $key, $count ) {
				CliLogger::line( sprintf( '%s: %d', $key, $count ) );
			},
			array_keys( $theme_settings ),
			array_values( $theme_settings )
		);
	}
}
