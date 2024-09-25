<?php

namespace NewspackCustomContentMigrator\Command\General;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use \WP_CLI;

class CssMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * @var string Current theme's export file name.
	 */
	const CSS_CURRENT_THEME_EXPORT_FILE = 'newspack-custom-css-current-theme.xml';

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {
		WP_CLI::add_command(
			'newspack-content-migrator export-current-theme-custom-css',
			self::get_command_closure( 'cmd_export_current_theme_custom_css' ),
			[
			'shortdesc' => 'Exports custom CSS for current active Theme. Exits with code 0 on success or 1 otherwise.',
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'output-dir',
					'description' => 'Output directory full path (no ending slash).',
					'optional'    => false,
					'repeating'   => false,
				],
			],
		] );

		WP_CLI::add_command(
			'newspack-content-migrator import-custom-css-file',
			self::get_command_closure( 'cmd_import_custom_css_file' ),
			[
			'shortdesc' => 'Imports custom CSS which was exported from the Staging site.',
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'input-dir',
					'description' => 'Input directory full path (no ending slash).',
					'optional'    => false,
					'repeating'   => false,
				],
			],
		] );
	}

	/**
	 * Callable for export-css command. Exits with code 0 on success or 1 otherwise.
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function cmd_export_current_theme_custom_css( $args, $assoc_args ) {
		$output_dir = isset( $assoc_args[ 'output-dir' ] ) ? $assoc_args[ 'output-dir' ] : null;
		if ( is_null( $output_dir ) || ! is_dir( $output_dir ) ) {
			WP_CLI::error( 'Invalid output dir.' );
		}

		WP_CLI::line( sprintf( 'Exporting custom CSS...' ) );

		$result = $this->export_current_theme_custom_css( $output_dir, self::CSS_CURRENT_THEME_EXPORT_FILE );
		if ( true === $result ) {
			WP_CLI::success( 'Done.' );
			exit(0);
		} else {
			WP_CLI::warning( 'Done with warnings.' );
			exit(1);
		}
	}

	/**
	 * Exports current active Theme's custom CSS to a file.
	 *
	 * @param $output_dir
	 * @param $file_output_css
	 *
	 * @return bool Success.
	 */
	public function export_current_theme_custom_css( $output_dir, $file_output_css ) {
		wp_cache_flush();
		$theme_mods = get_theme_mods();
		if ( ! isset( $theme_mods[ 'custom_css_post_id' ] ) || empty( $theme_mods[ 'custom_css_post_id' ] ) ) {
			return false;
		}

		$post_id = $theme_mods[ 'custom_css_post_id' ];

		return PostsMigrator::get_instance()->migrator_export_posts( array( $post_id ), $output_dir, $file_output_css );
	}

	/**
	 * Callable for import-custom-css-file command.
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function cmd_import_custom_css_file( $args, $assoc_args ) {
		$input_dir = isset( $assoc_args[ 'input-dir' ] ) ? $assoc_args[ 'input-dir' ] : null;
		if ( is_null( $input_dir ) || ! is_dir( $input_dir ) ) {
			WP_CLI::error( 'Invalid input dir.' );
		}

		$import_file = $input_dir . '/' . self::CSS_CURRENT_THEME_EXPORT_FILE;
		if ( ! is_file( $import_file ) ) {
			WP_CLI::warning( sprintf( 'CSS file not found %s.', $import_file ) );
			exit(1);
		}

		WP_CLI::line( 'Importing custom CSS from ' . $import_file . ' ...' );

		$this->delete_all_custom_css();
		PostsMigrator::get_instance()->import_posts( $import_file );
		$this->update_theme_mod_custom_css();

		WP_CLI::success( 'Done.' );
	}

	/**
	 * Updates Theme Modifications with newly migrated/imported post ID.
	 */
	private function update_theme_mod_custom_css() {
		$css_post_id = $this->get_imported_custom_css_post_id();
		wp_cache_flush();
		set_theme_mod( 'custom_css_post_id', $css_post_id );
		wp_cache_flush();
	}

	/**
	 * Deletes all existing Custom CSS.
	 */
	private function delete_all_custom_css() {
		wp_cache_flush();

		$args = array(
			'posts_per_page' => -1,
			'post_type'      => 'custom_css',
		);
		$query = new \WP_Query( $args );
		if ( ! $query->have_posts() ) {
			return false;
		}

		foreach ( $query->get_posts() as $post ) {
			wp_delete_post( $post->ID );
		}

		wp_cache_flush();
	}

	/**
	 * Gets the newly imported custom_css post type's ID.
	 *
	 * @return false|int Custom CSS post ID.
	 */
	private function get_imported_custom_css_post_id() {
		wp_cache_flush();

		// All args in \WP_Query::parse_query.
		$args = array(
			'posts_per_page' => 1,
			'post_type'      => 'custom_css',
			'post_status'    => 'publish',
			'meta_key'       => PostsMigrator::META_KEY_ORIGINAL_ID,

		);
		$query = new \WP_Query( $args );
		if ( ! $query->have_posts() ) {
			return false;
		}

		foreach ( $query->get_posts() as $post ) {
			return $post->ID;
		}

		wp_cache_flush();
	}
}
