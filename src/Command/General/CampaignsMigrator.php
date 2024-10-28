<?php

namespace NewspackCustomContentMigrator\Command\General;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use Newspack\MigrationTools\Logic\Campaigns;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;

class CampaignsMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * @var string Campaigns.
	 */
	const CAMPAIGNS_EXPORT_FILE = 'newspack-campaigns.xml';

	/**
	 * @var Campaigns
	 */
	private Campaigns $campaigns_logic;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->campaigns_logic = new Campaigns();
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {
		WP_CLI::add_command( 'newspack-content-migrator export-campaigns', self::get_command_closure( 'cmd_export_campaigns' ), [
			'shortdesc' => 'Exports Newspack Campaigns.',
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

		WP_CLI::add_command( 'newspack-content-migrator import-campaigns', self::get_command_closure( 'cmd_import_campaigns' ), [
			'shortdesc' => 'Imports Newspack Campaigns.',
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
	 * Callable for export-campaigns command. Exits with code 0 on success or 1 otherwise.
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function cmd_export_campaigns( $args, $assoc_args ) {
		$output_dir = isset( $assoc_args[ 'output-dir' ] ) ? $assoc_args[ 'output-dir' ] : null;
		if ( is_null( $output_dir ) || ! is_dir( $output_dir ) ) {
			WP_CLI::error( 'Invalid output dir.' );
		}

		WP_CLI::line( sprintf( 'Exporting Newspack Campaigns...' ) );

		$result = $this->export_campaigns( $output_dir, self::CAMPAIGNS_EXPORT_FILE );
		if ( true === $result ) {
			WP_CLI::success( 'Done.' );
			exit(0);
		} else {
			WP_CLI::warning( 'Done with warnings.' );
			exit(1);
		}
	}

	/**
	 * Exports Newspack Campaigns.
	 *
	 * @param $output_dir
	 * @param $file_output_campaigns
	 *
	 * @return bool Success.
	 */
	public function export_campaigns( $output_dir, $file_output_campaigns ) {
		wp_cache_flush();

		$posts = $this->campaigns_logic->get_all_campaigns();
		if ( empty( $posts ) ) {
			WP_CLI::warning( sprintf( 'No Campaigns found.' ) );
			return false;
		}

		$post_ids = [];
		foreach ( $posts as $post ) {
			$post_ids[] = $post->ID;
		}

		return PostsMigrator::get_instance()->migrator_export_posts( $post_ids, $output_dir, $file_output_campaigns );
	}

	/**
	 * Callable for import-campaigns command.
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function cmd_import_campaigns( $args, $assoc_args ) {
		$input_dir = isset( $assoc_args[ 'input-dir' ] ) ? $assoc_args[ 'input-dir' ] : null;
		if ( is_null( $input_dir ) || ! is_dir( $input_dir ) ) {
			WP_CLI::error( 'Invalid input dir.' );
		}

		$import_file = $input_dir . '/' . self::CAMPAIGNS_EXPORT_FILE;
		if ( ! is_file( $import_file ) ) {
			WP_CLI::warning( sprintf( 'Campaigns file not found %s.', $import_file ) );
			exit(1);
		}

		WP_CLI::line( 'Importing Newspack Campaigns from ' . $import_file . ' ...' );

		$this->import_campaigns( $import_file );

		WP_CLI::success( 'Done.' );
	}

	/**
	 * Imports Newspack Campaigns.
	 *
	 * @param string $import_file XML file to import.
	 */
	private function import_campaigns( $import_file ) {
		wp_cache_flush();

		$this->delete_all_existing_campaigns();

		// Newspack Blocks registers the \Newspack_Popups::popup_default_fields callback on 'save_post_newspack_popups_cpt' action
		// which needs to be removed, or otherwise it will add the default meta_fields to the Campaigns, and the inserted entries
		// will get double metas -- the default ones from the action, and the exported/imported ones from the file.
		remove_action( 'save_post_newspack_popups_cpt', [ \Newspack_Popups::class, 'popup_default_fields' ], 10 );

		register_post_type( $this->campaigns_logic::CAMPAIGNS_POST_TYPE );

		// The reason why we're not running the `wp import` command, but instead are programmatically importing the file like
		// this, is that we need to remove the action above; if we ran a separate `wp import` command, we couldn't tap into its
		// action stack (since it starts a separate one, in a separate PHP execution run).
		$this->include_wp_importer_dependencies();
		$importer = new \WP_Import();
		$importer->import( $import_file );
	}

	/**
	 * Includes WP Importer dependencies to enable programmatic execution.
	 */
	private function include_wp_importer_dependencies() {
		require_once( ABSPATH . 'wp-admin/includes/class-wp-importer.php' );

		// For the following several classes, consider the different install structure on Atomic.
		$plugin_path = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : ABSPATH . 'wp-content/plugins';
		if ( file_exists( ABSPATH . 'wp-content/plugins/wordpress-importer/class-wp-import.php' ) ) {
			require_once( ABSPATH . 'wp-content/plugins/wordpress-importer/class-wp-import.php' );
		} else {
			require_once( $plugin_path . '/wordpress-importer/class-wp-import.php' );
		}
		if ( file_exists( ABSPATH . 'wp-content/plugins/wordpress-importer/parsers/class-wxr-parser.php' ) ) {
			require_once( ABSPATH . 'wp-content/plugins/wordpress-importer/parsers/class-wxr-parser.php' );
		} else {
			require_once( $plugin_path . '/wordpress-importer/parsers/class-wxr-parser.php' );
		}
		if ( file_exists( ABSPATH . 'wp-content/plugins/wordpress-importer/parsers/class-wxr-parser-simplexml.php' ) ) {
			require_once( ABSPATH . 'wp-content/plugins/wordpress-importer/parsers/class-wxr-parser-simplexml.php' );
		} else {
			require_once( $plugin_path . '/wordpress-importer/parsers/class-wxr-parser-simplexml.php' );
		}

		require_once( ABSPATH . 'wp-admin/includes/import.php' );
		require_once( ABSPATH . 'wp-admin/includes/post.php' );
	}

	/**
	 * Deletes all existing Campaigns.
	 */
	private function delete_all_existing_campaigns() {
		wp_cache_flush();

		$posts = $this->campaigns_logic->get_all_campaigns();
		if ( empty( $posts ) ) {
			return;
		}

		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID );
		}
	}
}
