<?php

namespace NewspackCustomContentMigrator\Command\General;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use NewspackCustomContentMigrator\Logic\Posts as PostsLogic;
use WP_CLI;

class ContentConverterPluginMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {
		WP_CLI::add_command( 'newspack-content-migrator import-blocks-content-from-staging-site', array( __CLASS__, 'cmd_import_blocks_content_from_staging_site' ), [
			'shortdesc' => "Imports previously backed up Newspack Content Converter plugin's Staging site table contents.",
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'table-prefix',
					'description' => 'WP DB table prefix.',
					'optional'    => false,
					'repeating'   => false,
				],
				[
					'type'        => 'assoc',
					'name'        => 'staging-hostname',
					'description' => "Staging site's hostname -- the site from which this site was cloned.",
					'optional'    => false,
					'repeating'   => false,
				],
			],
		] );
	}

	/**
	 * Callable for the back-up-converter-plugin-staging-table command.
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public static function cmd_import_blocks_content_from_staging_site( $args, $assoc_args ) {
		$table_prefix = isset( $assoc_args[ 'table-prefix' ] ) ? $assoc_args[ 'table-prefix' ] : null;
		if ( is_null( $table_prefix ) ) {
			WP_CLI::error( 'Invalid table prefix param.' );
		}
		$staging_host = isset( $assoc_args[ 'staging-hostname' ] ) ? $assoc_args[ 'staging-hostname' ] : null;
		if ( is_null( $staging_host ) ) {
			WP_CLI::error( 'Invalid Staging hostname param.' );
		}

		global $wpdb;

		$staging_posts_table = $wpdb->dbh->real_escape_string( 'staging_' . $table_prefix . 'posts' );
		$posts_table         = $wpdb->dbh->real_escape_string( $table_prefix . 'posts' );

		// Check if the backed up posts table from staging exists.
		$table_count = $wpdb->get_var(
			$wpdb->prepare (
				"SELECT COUNT(table_name) as table_count FROM information_schema.tables WHERE table_schema='%s' AND table_name='%s';",
				$wpdb->dbname,
				$staging_posts_table
			)
		);
		if ( 1 != $table_count ) {
			WP_CLI::error( sprintf( 'Table %s not found in DB, skipping importing block contents.', $staging_posts_table ) );
		}

		// Get Staging hostname, and this hostname..
		$this_options_table    = $wpdb->dbh->real_escape_string( $table_prefix . 'options' );
		$this_siteurl          = $wpdb->get_var( $wpdb->prepare ( "SELECT option_value FROM $this_options_table where option_name = 'siteurl';", $staging_posts_table ) );
		$url_parse             = wp_parse_url( $this_siteurl );
		$this_host             = $url_parse[ 'host' ] ?? null;
		if ( null === $this_host ) {
			WP_CLI::error( "Could not fetch this site's siteurl from the options table $this_options_table." );
		}


		// Update wp_posts with converted content from the Staging wp_posts backup.
		WP_CLI::line( 'Importing content previously converted to blocks from the Staging posts table...' );
		$wpdb->get_results(
			"UPDATE $posts_table wp
			JOIN $staging_posts_table swp
				ON swp.ID = wp.ID
				AND swp.post_title = wp.post_title
				AND swp.post_content <> wp.post_content
			SET wp.post_content = swp.post_content
			WHERE swp.post_content LIKE '<!-- wp:%'; "
		);


		// Now update hostnames, too.
		WP_CLI::line( sprintf( 'Updating hostnames in content brought over from Staging from %s to %s ...', $staging_host, $this_host ) );
		$posts_logic = new PostsLogic();
		$posts_ids = $posts_logic->get_all_posts_ids();
		foreach ( $posts_ids as $key_posts_ids => $post_id ) {
			$post                 = get_post( $post_id );
			$post_content_updated = str_replace( $staging_host, $this_host, $post->post_content );
			$post_excerpt_updated = str_replace( $staging_host, $this_host, $post->post_excerpt );
			if ( $post->post_content != $post_content_updated || $post->post_excerpt != $post_excerpt_updated ) {
				$wpdb->update(
					$wpdb->prefix . 'posts',
					[
						'post_content'  => $post_content_updated,
						'post_excerpt' => $post_excerpt_updated,
					],
					[ 'ID' => $post->ID ]
				);
			}
		}

		// Required for the $wpdb->update() sink in.
		wp_cache_flush();

		WP_CLI::success( 'Done.' );
	}
}
