<?php

namespace Newspack\MigrationTools\Command;

use Newspack\MigrationTools\Logic\Attachments;
use WP_CLI;

class SettingsMigrator implements WpCliCommandInterface {

	use WpCliCommandTrait;

	/**
	 * @var string Page settings exported data filename.
	 */
	const PAGES_SETTINGS_FILENAME = 'newspack-settings-pages.json';

	/**
	 * @var string Exported options for site identity.
	 */
	const SITE_IDENTITY_EXPORTED_OPTIONS_FILENAME = 'newspack-site-identity-exported-options.json';

	/**
	 * {@inheritDoc}
	 */
	public static function get_cli_commands(): array {
		return [
			[
				'newspack-content-migrator export-pages-settings',
				self::get_command_closure( 'cmd_export_pages_settings' ),
				[
					'shortdesc' => 'Exports settings for default Site Pages.',
					'synopsis'  => [
						[
							'type'        => 'assoc',
							'name'        => 'output-dir',
							'description' => 'Output directory full path (no ending slash).',
							'optional'    => false,
							'repeating'   => false,
						],
					],
				],
			],

			[
				'newspack-content-migrator import-pages-settings',
				self::get_command_closure( 'cmd_import_pages_settings' ),
				[
					'shortdesc' => 'Imports custom CSS from the export XML file.',
					'synopsis'  => [
						[
							'type'        => 'assoc',
							'name'        => 'input-dir',
							'description' => 'Input directory full path (no ending slash).',
							'optional'    => false,
							'repeating'   => false,
						],
					],
				],
			],

			[
				'newspack-content-migrator export-customize-site-identity-settings',
				self::get_command_closure( 'cmd_export_customize_site_identity_settings' ),
				[
					'shortdesc' => 'Exports Customizer site identity settings.',
					'synopsis'  => [
						[
							'type'        => 'assoc',
							'name'        => 'output-dir',
							'description' => 'Output directory full path (no ending slash).',
							'optional'    => false,
							'repeating'   => false,
						],
					],
				],
			],

			[
				'newspack-content-migrator import-customize-site-identity-settings',
				self::get_command_closure( 'cmd_import_customize_site_identity_settings' ),
				[
					'shortdesc' => 'Imports Customizer site identity settings from the Staging site.',
					'synopsis'  => [
						[
							'type'        => 'assoc',
							'name'        => 'input-dir',
							'description' => 'Input directory full path (no ending slash).',
							'optional'    => false,
							'repeating'   => false,
						],
					],
				],
			],

			[
				'newspack-content-migrator update-seo-settings',
				self::get_command_closure( 'cmd_update_seo_settings' ),
				[
					'shortdesc' => 'Checks and sets SEO settings.',
				],
			],
		];
	}

	/**
	 * Callable for export-customize-site-identity-settings command.
	 */
	public function cmd_export_customize_site_identity_settings( array $args, array $assoc_args ) {
		$output_dir = $assoc_args['output-dir'];
		if ( empty( $output_dir ) || ! is_dir( $output_dir ) ) {
			WP_CLI::error( 'Invalid output dir.' );
		}

		WP_CLI::line( sprintf( 'Exporting site identity settings...' ) );

		$result = $this->export_current_theme_site_identity( $output_dir );
		if ( false !== $result ) {
			WP_CLI::success( 'Done.' );
			exit( 0 );
		} else {
			WP_CLI::warning( 'Done with warnings.' );
			exit( 1 );
		}
	}

	/**
	 * Exports all relevant site identity settings, such as current Theme mods and site icon.
	 *
	 * @param string $output_dir Dir to output to.
	 *
	 * @return bool Success.
	 */
	private function export_current_theme_site_identity( $output_dir ) {
		wp_cache_flush();

		// Get theme mods with IDs.
		$json_data      = [];
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		if ( false !== $custom_logo_id ) {
			$json_data['custom_logo']      = $custom_logo_id;
			$json_data['custom_logo_file'] = get_attached_file( $custom_logo_id );
		}
		$newspack_footer_logo_id = get_theme_mod( 'newspack_footer_logo' );
		if ( false !== $newspack_footer_logo_id ) {
			$json_data['newspack_footer_logo']      = $newspack_footer_logo_id;
			$json_data['newspack_footer_logo_file'] = get_attached_file( $newspack_footer_logo_id );
		}

		// Get site icon Post (attachment) ID.
		$site_icon_id = get_option( 'site_icon', false );
		if ( $site_icon_id ) {
			$json_data['site_icon_file'] = get_attached_file( $site_icon_id );
		}

		if ( empty( $json_data ) ) {
			return false;
		}

		// Write JSON file.
		$file = $output_dir . '/' . self::SITE_IDENTITY_EXPORTED_OPTIONS_FILENAME;
		WP_CLI::line( 'Writing to file ' . $file );

		return file_put_contents( $file, wp_json_encode( $json_data ) );
	}

	/**
	 * Callable for import-customize-site-identity-settings.
	 */
	public function cmd_import_customize_site_identity_settings( $args, $assoc_args ) {
		$input_dir = isset( $assoc_args['input-dir'] ) ? $assoc_args['input-dir'] : null;
		if ( is_null( $input_dir ) || ! is_dir( $input_dir ) ) {
			WP_CLI::error( 'Invalid input dir.' );
		}

		$options_import_file = $input_dir . '/' . self::SITE_IDENTITY_EXPORTED_OPTIONS_FILENAME;
		if ( ! is_file( $options_import_file ) ) {
			WP_CLI::warning( sprintf( 'Site identity settings file not found %s.', $options_import_file ) );
			exit( 1 );
		}

		WP_CLI::line( 'Importing site identity settings from ' . $options_import_file . ' ...' );

		// Update current Theme mods.
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$imported_mods_and_options = json_decode( file_get_contents( $options_import_file ), true );

		// Import and set logo.
		$logo_id = null;
		if ( isset( $imported_mods_and_options['custom_logo_file'] ) && ! empty( $imported_mods_and_options['custom_logo_file'] ) ) {
			$logo_file = $imported_mods_and_options['custom_logo_file'];
			if ( file_exists( $logo_file ) ) {
				$logo_id = Attachments::import_external_file( $logo_file );
			}
		}

		// Import and set footer logo.
		$footer_logo_id = null;
		if ( isset( $imported_mods_and_options['newspack_footer_logo_file'] ) && ! empty( $imported_mods_and_options['newspack_footer_logo_file'] ) ) {
			$footer_logo_file = $imported_mods_and_options['newspack_footer_logo_file'];
			if ( file_exists( $footer_logo_file ) ) {
				$footer_logo_id = Attachments::import_external_file( $footer_logo_file );
			}
		}

		// Set current Theme mods IDs.
		$this->update_theme_mod_site_identity_post_ids( $logo_id, $footer_logo_id );

		// Import and set the site icon.
		if ( isset( $imported_mods_and_options['site_icon_file'] ) && ! empty( $imported_mods_and_options['site_icon_file'] ) ) {
			$icon_file = $imported_mods_and_options['site_icon_file'];
			if ( file_exists( $icon_file ) ) {
				$icon_id = Attachments::import_external_file( $icon_file );
				if ( is_numeric( $icon_id ) ) {
					update_option( 'site_icon', $icon_id );
				}
			}
		}

		WP_CLI::success( 'Done.' );
	}

	/**
	 * Updates the Theme mods logo images IDs.
	 *
	 * @param int $logo_id        Logo image ID.
	 * @param int $footer_logo_id Footer logo image ID.
	 */
	private function update_theme_mod_site_identity_post_ids( $logo_id = null, $footer_logo_id = null ) {
		wp_cache_flush();

		if ( $logo_id ) {
			set_theme_mod( 'custom_logo', $logo_id );
		}
		if ( $footer_logo_id ) {
			set_theme_mod( 'newspack_footer_logo', $footer_logo_id );
		}
	}

	/**
	 * Callable for the `newspack-content-migrator export-pages-settings` command.
	 */
	public function cmd_export_pages_settings( $args, $assoc_args ) {
		$output_dir = isset( $assoc_args['output-dir'] ) ? $assoc_args['output-dir'] : null;
		if ( is_null( $output_dir ) || ! is_dir( $output_dir ) ) {
			WP_CLI::error( 'Invalid output dir.' );
		}

		WP_CLI::line( 'Exporting default pages settings...' );

		$file = $output_dir . '/' . self::PAGES_SETTINGS_FILENAME;
		$data = array(
			// This is the radio button setting on Customize > Homepage settings > "Your homepage displays".
			'show_on_front'             => get_option( 'show_on_front' ),
			// Homepage post ID.
			'page_on_front'             => get_option( 'page_on_front' ),
			// Posts page ID.
			'page_for_posts'            => get_option( 'page_for_posts' ),
			// Donation page ID.
			'newspack_donation_page_id' => get_option( 'newspack_donation_page_id' ),
		);

		$written = file_put_contents( $file, wp_json_encode( $data ) );
		if ( false === $written ) {
			exit( 1 );
		}

		WP_CLI::line( 'Writing to file ' . $file );
		WP_CLI::success( 'Done.' );
		exit( 0 );
	}

	/**
	 * Callable for import-pages-settings command.
	 */
	public function cmd_import_pages_settings( array $args, array $assoc_args ) {
		$input_dir = isset( $assoc_args['input-dir'] ) ? $assoc_args['input-dir'] : null;
		if ( is_null( $input_dir ) || ! is_dir( $input_dir ) ) {
			WP_CLI::error( 'Invalid input dir.' );
		}

		$import_file = $input_dir . '/' . self::PAGES_SETTINGS_FILENAME;
		if ( ! is_file( $import_file ) ) {
			WP_CLI::error( sprintf( 'Pages settings file not found %s.', $import_file ) );
		}

		WP_CLI::line( 'Importing default pages settings from ' . $import_file . ' ...' );

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$contents = file_get_contents( $import_file );
		if ( false === $contents ) {
			WP_CLI::error( 'Options contents empty.' );
		}

		$options        = json_decode( $contents, true );
		$posts_migrator = PostsMigrator::get_instance();

		// Copy over these as they are.
		$option_names = array( 'show_on_front' );
		foreach ( $option_names as $option_name ) {
			update_option( $option_name, $options[ $option_name ] );
		}

		// Update IDs for these Pages saved as option values, by referring to the PostsMigrator::META_KEY_ORIGINAL_ID meta.
		$option_names = array( 'page_on_front', 'page_for_posts', 'newspack_donation_page_id' );
		foreach ( $option_names as $option_name ) {
			$original_id = isset( $options[ $option_name ] ) && ! empty( $options[ $option_name ] ) ? $options[ $option_name ] : null;
			if ( null !== $original_id && 0 != $original_id ) {
				$current_id = $posts_migrator->get_current_post_id_from_original_post_id( $original_id );
				update_option( $option_name, $current_id );
			}
		}

		WP_CLI::success( 'Done.' );
	}

	/**
	 * Callable for update-seo-settings.
	 */
	public function cmd_update_seo_settings( array $args, array $assoc_args ) {
		WP_CLI::success( 'Disabling Yoast XML sitemaps...' );
		$this->turn_off_yoast_xml_sitemap();

		WP_CLI::success( 'Unchecking the `Discourage search engines from indexing this site` option...' );
		$this->uncheck_discourage_search_engines();

		WP_CLI::success( 'Done.' );
	}

	/**
	 * Disables Yoast's XML sitemap.
	 */
	private function turn_off_yoast_xml_sitemap() {
		$option_name  = 'wpseo';
		$option_param = 'enable_xml_sitemap';

		$option = get_option( $option_name );
		if ( ! $option || ! isset( $option[ $option_param ] ) ) {
			return;
		}

		$option[ $option_param ] = false;

		update_option( $option_name, $option );
	}

	/**
	 * Updates the `blog_public` option to true, which in turn unchecks the `Discourage search engines from indexing this site`
	 * WP Settings > Reading option.
	 */
	private function uncheck_discourage_search_engines() {
		update_option( 'blog_public', 1 );
	}
}
