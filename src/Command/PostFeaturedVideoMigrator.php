<?php
/**
 * Custom migration scripts for Post Featured Video.
 * 
 * @package NewspackCustomContentMigrator\Command\General
 */

namespace Newspack\MigrationTools\Command;

use WP_CLI;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;

/**
 * Custom migration scripts for Post Featured Video.
 */
class PostFeaturedVideoMigrator implements WpCliCommandInterface {

	/**
	 * Post meta key for featured video.
	 */
	const META_KEY = 'featured_video';

	/**
	 * Gutenberg block generator.
	 * 
	 * @var GutenbergBlockGenerator
	 */
	private GutenbergBlockGenerator $blocks;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->blocks = new GutenbergBlockGenerator();
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get_cli_commands(): array {
		return [
			[
				'newspack-migration-tools post-featured-video-to-postcontent',
				[ __CLASS__, 'cmd_migrate_post_featured_video_to_post_content' ],
				[
					'shortdesc' => "Appends 'featured_video' postmeta as a video block at the top of the post content.",
					'synopsis'  => [
						[
							'type'      => 'flag',
							'name'      => 'dry-run',
							'optional'  => true,
							'repeating' => false,
						],
						[
							'type'        => 'assoc',
							'name'        => 'post-ids',
							'description' => 'Optional, if not provided will replace for all posts and pages. IDs of posts and pages to remove shortcodes from their content separated by a comma (e.g. 123,456)',
							'optional'    => true,
							'repeating'   => false,
						],
						[
							'type'        => 'assoc',
							'name'        => 'post-types',
							'description' => 'Optional, CSV of post types, if not provided will replace for all posts and pages. E.g. --post-types=post,page',
							'optional'    => true,
							'repeating'   => false,
							'default'     => 'post,page',
						],
						[
							'type'        => 'assoc',
							'name'        => 'post-statuses',
							'description' => "Optional, CSV of post statuses, if not provided will default to 'publish'. E.g. --post-statuses=publish,draft,private",
							'optional'    => true,
							'repeating'   => false,
							'default'     => 'publish',
						],
					],
				],
			],
		];
	}

	/**
	 * Migrate featured video from postmeta to post content.
	 *
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function cmd_migrate_post_featured_video_to_post_content( $pos_args, $assoc_args ) {
		$dry_run       = isset( $assoc_args['dry-run'] ) ? true : false;
		$post_ids      = isset( $assoc_args['post-ids'] ) ? explode( ',', $assoc_args['post-ids'] ) : null;
		$post_types    = isset( $assoc_args['post-types'] ) ? explode( ',', $assoc_args['post-types'] ) : [ 'post', 'page' ];
		$post_statuses = isset( $assoc_args['post-statuses'] ) ? explode( ',', $assoc_args['post-statuses'] ) : [ 'publish' ];

		global $wpdb;
		
		if ( ! $post_ids ) {
			// Get all post IDs with meta.
			$post_types_placeholder    = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
			$post_statuses_placeholder = implode( ',', array_fill( 0, count( $post_statuses ), '%s' ) );
			// phpcs:disable -- WordPress.DB.PreparedSQL.InterpolatedNotPrepared, all parameters are sanitized.
			$post_ids                  = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT pm.post_id
				FROM {$wpdb->postmeta} pm
				JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '%s'
				AND pm.meta_value != ''
				AND p.post_type IN ($post_types_placeholder)
				AND p.post_status IN ($post_statuses_placeholder); ",
					array_merge(
						[ self::META_KEY ],
						$post_types,
						$post_statuses
					)
				) 
			);
			// phpcs:enable
		}

		if ( empty( $post_ids ) ) {
			WP_CLI::warning( 'No posts found with featured video meta.' );
			return;
		}

		// Update posts.
		foreach ( $post_ids as $key_post_id => $post_id ) {
			WP_CLI::line( sprintf( '(%d/%d) post ID %d', $key_post_id + 1, count( $post_ids ), $post_id ) );
			
			// Get postmeta.
			$video_url = get_post_meta( $post_id, self::META_KEY );
			if ( empty( trim( $video_url ) ) ) {
				continue;
			}

			// Skip invalid video URL.
			if ( ! filter_var( $video_url, FILTER_VALIDATE_URL ) ) {
				WP_CLI::warning( sprintf( 'Invalid video URL meta for post ID %d: %s', $post_id, $video_url ) );
				continue;
			}

			// Convert to video block or to <a> link if the video URL is not supported.
			$video_block_html = $this->convert_url_to_video_block( $video_url );
			if ( false === $video_block_html ) {
				WP_CLI::warning( sprintf( 'Invalid video URL for post ID %d: %s', $post_id, $video_url ) );
				continue;
			} elseif ( is_null( $video_block_html ) ) {
				WP_CLI::warning( sprintf( 'Inserting link for post ID %d since video is not supported: %s', $post_id, $video_url ) );
				$video_block_html = sprintf( '<a href="%s" target="_blank">%s</a>', $video_url, $video_url );
			}

			// Prepend video block to post content.
			$post_content = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ) ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.NoCaching.
			if ( null === $post_content ) {
				continue;
			}
			
			$post_content_updated = $video_block_html . "\n\n" . $post_content;

			// Update post.
			if ( ! $dry_run && ( $post_content_updated !== $post_content ) ) {
				// Hide featured image.
				update_post_meta( $post_id, 'newspack_featured_image_position', 'hidden' );

				// Update post_content.
				$updated = wp_update_post(
					[
						'ID'           => $post_id,
						'post_content' => $post_content_updated,
					] 
				);
				if ( is_wp_error( $updated ) ) {
					WP_CLI::warning( sprintf( 'Failed to update post ID %d: %s', $post_id, $updated->get_error_message() ) );
				}
			}
			WP_CLI::line( sprintf( 'Updated post ID %d', $post_id ) );
		}

		wp_cache_flush();
		WP_CLI::success( 'Done 👍' );
	}

	/**
	 * Convert a video URL to a video block.
	 * 
	 * @param string $video_url   Video URL.
	 * @return string|null|false  Video block, or null if not a supported video type/URL, or false if not a valid URL.
	 */
	public function convert_url_to_video_block( $video_url ): string|null {
		// Prepare URL.
		$video_url  = trim( $video_url );
		$parsed_url = wp_parse_url( $video_url );
		
		// Skip if not a valid URL.
		if ( ! $parsed_url || ! isset( $parsed_url['host'] ) ) {
			return false;
		}

		// Check if video URL is supported.
		$is_facebook_video = 1 === preg_match( '/(^|\.)facebook\.com$/', $parsed_url['host'] );
		$is_youtube_video  = 1 === preg_match( '/(^|\.)(youtube\.com|youtu\.be)$/', $parsed_url['host'] );
		$is_vimeo_video    = 1 === preg_match( '/(^|\.)vimeo\.com$/', $parsed_url['host'] );

		// Generate replacements for shortcode.
		$video_block_html = '';
		if ( $is_facebook_video ) {
			$video_block_html = serialize_block( $this->blocks->get_facebook( $video_url ) );
		} elseif ( $is_youtube_video ) {
			$video_block_html = serialize_block( $this->blocks->get_youtube( $video_url ) );
		} elseif ( $is_vimeo_video ) {
			$video_block_html = serialize_block( $this->blocks->get_vimeo( $video_url ) );
		}

		return $video_block_html;
	}
}
