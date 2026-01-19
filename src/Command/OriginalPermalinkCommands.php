<?php
/**
 * WP-CLI commands for managing original permalinks from migrated content.
 *
 * Provides commands to retrieve and list original permalink data that is stored
 * during content migration. Original permalinks represent the original URLs from
 * the source site before migration to WordPress.
 *
 * To delete permalink data, use: wp nmt original-value delete permalink
 *
 * @see     docs/source-permalinks.md Full documentation with usage examples
 *
 * @package Newspack\MigrationTools
 */

namespace Newspack\MigrationTools\Command;

use Newspack\MigrationTools\NMT;
use Newspack\MigrationTools\Util\BatchLogic;
use Newspack\MigrationTools\Util\OriginalPermalink;
use WP_CLI;

/**
 * WP-CLI command class for original permalink management.
 *
 * @see docs/source-permalinks.md Full documentation with usage examples
 */
class OriginalPermalinkCommands implements WpCliCommandInterface {

	/**
	 * @inheritdoc
	 */
	public static function get_cli_commands(): array {
		$source_domain_arg  = [
			'type'        => 'assoc',
			'name'        => 'source-domain',
			'description' => 'Source domain to prepend to paths to output full URLs (e.g., example.com or https://example.com).',
			'optional'    => true,
			'repeating'   => false,
		];
		$format_arg         = [
			'type'        => 'assoc',
			'name'        => 'format',
			'description' => 'Render output in a particular format.',
			'optional'    => true,
			'default'     => 'table',
			'options'     => [ 'table', 'csv', 'json', 'yaml' ],
			'repeating'   => false,
		];
		$post_display_field = [
			'type'        => 'assoc',
			'name'        => 'field',
			'description' => 'Display a specific field (source_permalink_path, post_id, wp_path).',
			'optional'    => true,
			'repeating'   => false,
		];
		$term_display_field = [
			'type'        => 'assoc',
			'name'        => 'field',
			'description' => 'Display a specific field (source_permalink_path, term_id, wp_path).',
			'optional'    => true,
			'repeating'   => false,
		];

		return [
			[
				'newspack-migration-tools original-permalink post get',
				[ self::class, 'post_get' ],
				[
					'shortdesc' => 'Get the source permalink for a specific post.',
					'synopsis'  => [
						[
							'type'        => 'positional',
							'name'        => 'post-id',
							'description' => 'The post ID.',
							'optional'    => false,
							'repeating'   => false,
						],
						$post_display_field,
						$source_domain_arg,
						$format_arg,
					],
				],
			],
			[
				'newspack-migration-tools original-permalink post list',
				[ self::class, 'post_list' ],
				[
					'shortdesc' => 'List all posts that have a source permalink.',
					'synopsis'  => [
						$post_display_field,
						$source_domain_arg,
						$format_arg,
						...BatchLogic::get_batch_args(),
					],
				],
			],
			[
				'newspack-migration-tools original-permalink term get',
				[ self::class, 'term_get' ],
				[
					'shortdesc' => 'Get the source permalink for a specific term.',
					'synopsis'  => [
						[
							'type'        => 'positional',
							'name'        => 'term-id',
							'description' => 'The term ID.',
							'optional'    => false,
							'repeating'   => false,
						],
						$term_display_field,
						$source_domain_arg,
						$format_arg,
					],
				],
			],
			[
				'newspack-migration-tools original-permalink term list',
				[ self::class, 'term_list' ],
				[
					'shortdesc' => 'List all terms that have a source permalink.',
					'synopsis'  => [
						$term_display_field,
						$source_domain_arg,
						$format_arg,
						...BatchLogic::get_batch_args(),
					],
				],
			],
			[
				'newspack-migration-tools original-permalink post list-mismatches',
				[ self::class, 'post_list_mismatches' ],
				[
					'shortdesc' => 'List posts where the source permalink does not match the current WordPress permalink.',
					'synopsis'  => [
						$post_display_field,
						$source_domain_arg,
						$format_arg,
						...BatchLogic::get_batch_args(),
						[
							'type'        => 'flag',
							'name'        => 'check-redirects',
							'description' => 'Check if URLs resolve correctly via WordPress canonical redirects (slower). By default, uses fast string comparison.',
							'optional'    => true,
							'repeating'   => false,
						],
					],
				],
			],
			[
				'newspack-migration-tools original-permalink term list-mismatches',
				[ self::class, 'term_list_mismatches' ],
				[
					'shortdesc' => 'List terms where the source permalink does not match the current WordPress permalink.',
					'synopsis'  => [
						$term_display_field,
						$source_domain_arg,
						$format_arg,
						...BatchLogic::get_batch_args(),
					],
				],
			],
		];
	}

	/**
	 * Get the source permalink for a specific post.
	 *
	 * Retrieves and displays the source permalink metadata for a single post.
	 * Exits with error if post is not found or has no source permalink.
	 *
	 * @param array $pos_args   Positional arguments. Expected: [0] => post ID.
	 * @param array $assoc_args Associative arguments. Optional: 'source-domain', 'field', 'format'.
	 *
	 * @return void
	 */
	public static function post_get( array $pos_args, array $assoc_args ): void {
		$post = get_post( $pos_args[0] );
		if ( empty( $post ) ) {
			NMT::exit_with_message( sprintf( 'Post with ID %d not found.', $pos_args[0] ) );
		}

		$source_permalink = OriginalPermalink::get_post_source_permalink( $post->ID );
		if ( empty( $source_permalink ) ) {
			NMT::exit_with_message( sprintf( 'No source permalink found for post ID %d.', $post->ID ) );
		}

		$source_domain = $assoc_args['source-domain'] ?? '';
		$wp_permalink  = get_permalink( $post->ID );

		$data = [
			'post_id'               => $post->ID,
			'wp_path'               => empty( $source_domain ) ? OriginalPermalink::ensure_path_format( $wp_permalink ) : $wp_permalink,
			'source_permalink_path' => self::maybe_convert_to_url( $source_permalink, $source_domain ),
		];

		if ( isset( $assoc_args['field'] ) ) {
			if ( ! isset( $data[ $assoc_args['field'] ] ) ) {
				NMT::exit_with_message( sprintf( 'Invalid field: %s', $assoc_args['field'] ) );
			}
			WP_CLI::line( $data[ $assoc_args['field'] ] );

			return;
		}

		$format = $assoc_args['format'] ?? 'table';
		WP_CLI\Utils\format_items( $format, [ $data ], array_keys( $data ) );
	}

	/**
	 * List all posts that have source permalinks.
	 *
	 * Queries and displays all posts that have source permalink metadata stored.
	 * Supports batching via BatchLogic for handling large datasets efficiently.
	 *
	 * @param array $pos_args   Positional arguments (unused).
	 * @param array $assoc_args Associative arguments. Optional: 'source-domain', 'field', 'format',
	 *                          'start', 'end', 'num-items'.
	 *
	 * @return void
	 */
	public static function post_list( array $pos_args, array $assoc_args ): void {
		$source_domain = $assoc_args['source-domain'] ?? '';
		$posts_data    = OriginalValueCommands::get_posts_data_for_key( OriginalPermalink::KEY, $assoc_args );

		if ( empty( $posts_data['total'] ) ) {
			WP_CLI::warning( 'No posts found with source permalinks.' );
			return;
		}

		// Build data array with additional fields.
		$data = [];
		foreach ( $posts_data['results'] as $row ) {
			$post = get_post( $row->post_id );
			if ( empty( $post ) ) {
				continue;
			}

			$source_permalink = $row->meta_value;
			$wp_permalink     = get_permalink( $post->ID );
			$wp_path          = OriginalPermalink::ensure_path_format( $wp_permalink );

			$data[] = [
				'post_id'               => $row->post_id,
				'wp_path'               => empty( $source_domain ) ? untrailingslashit( $wp_path ) : $wp_permalink,
				'source_permalink_path' => self::maybe_convert_to_url( $source_permalink, $source_domain ),
			];
		}

		if ( empty( $data ) ) {
			WP_CLI::warning( 'No posts to display in this batch.' );
			return;
		}

		$format = $assoc_args['format'] ?? 'table';
		$fields = $assoc_args['fields'] ?? 'post_id,wp_path,source_permalink_path';
		WP_CLI\Utils\format_items( $format, $data, explode( ',', $fields ) );
	}

	/**
	 * Get the source permalink for a specific term.
	 *
	 * Retrieves and displays the source permalink metadata for a single term.
	 * Exits with error if term is not found or has no source permalink.
	 *
	 * @param array $pos_args   Positional arguments. Expected: [0] => term ID.
	 * @param array $assoc_args Associative arguments. Optional: 'source-domain', 'field', 'format'.
	 *
	 * @return void
	 */
	public static function term_get( array $pos_args, array $assoc_args ): void {
		$term = get_term( $pos_args[0] );
		if ( empty( $term ) || is_wp_error( $term ) ) {
			NMT::exit_with_message( sprintf( 'Term with ID %d not found.', $pos_args[0] ) );
		}

		$source_permalink = OriginalPermalink::get_term_source_permalink( $term->term_id );
		if ( empty( $source_permalink ) ) {
			NMT::exit_with_message( sprintf( 'No source permalink found for term ID %d.', $term->term_id ) );
		}

		$source_domain = $assoc_args['source-domain'] ?? '';
		$term_link     = get_term_link( $term );
		$wp_path       = '';

		if ( ! is_wp_error( $term_link ) ) {
			$wp_path = empty( $source_domain ) ? OriginalPermalink::ensure_path_format( $term_link ) : $term_link;
		}

		$data = [
			'term_id'               => $term->term_id,
			'taxonomy'              => $term->taxonomy,
			'wp_path'               => $wp_path,
			'source_permalink_path' => self::maybe_convert_to_url( $source_permalink, $source_domain ),
		];

		if ( isset( $assoc_args['field'] ) ) {
			if ( ! isset( $data[ $assoc_args['field'] ] ) ) {
				NMT::exit_with_message( sprintf( 'Invalid field: %s', $assoc_args['field'] ) );
			}
			WP_CLI::line( $data[ $assoc_args['field'] ] );

			return;
		}

		$format = $assoc_args['format'] ?? 'table';
		WP_CLI\Utils\format_items( $format, [ $data ], array_keys( $data ) );
	}

	/**
	 * List all terms that have source permalinks.
	 *
	 * Queries and displays all terms that have source permalink metadata stored.
	 * Supports batching via BatchLogic for handling large datasets efficiently.
	 *
	 * @param array $pos_args   Positional arguments (unused).
	 * @param array $assoc_args Associative arguments. Optional: 'source-domain', 'field', 'format',
	 *                          'start', 'end', 'num-items'.
	 *
	 * @return void
	 */
	public static function term_list( array $pos_args, array $assoc_args ): void {
		$source_domain = $assoc_args['source-domain'] ?? '';
		$terms_data    = OriginalValueCommands::get_terms_data_for_key( OriginalPermalink::KEY, $assoc_args );

		if ( empty( $terms_data['total'] ) ) {
			WP_CLI::warning( 'No terms found with source permalinks.' );
			return;
		}

		WP_CLI::line( sprintf( 'Showing terms %d to %d of %d total.', $terms_data['batch_args']['start'], min( $terms_data['batch_args']['end'] - 1, $terms_data['total'] ), $terms_data['total'] ) );

		// Build data array with additional fields.
		$data = [];
		foreach ( $terms_data['results'] as $row ) {
			$term = get_term( $row->term_id );
			if ( empty( $term ) || is_wp_error( $term ) ) {
				continue;
			}

			$source_permalink = $row->meta_value;
			$term_link        = get_term_link( $term );
			$wp_path          = '';

			if ( ! is_wp_error( $term_link ) ) {
				$wp_path = empty( $source_domain ) ? OriginalPermalink::ensure_path_format( $term_link ) : $term_link;
			}

			$data[] = [
				'term_id'               => $row->term_id,
				'taxonomy'              => $term->taxonomy,
				'wp_path'               => untrailingslashit( $wp_path ),
				'source_permalink_path' => self::maybe_convert_to_url( $source_permalink, $source_domain ),
			];
		}

		if ( empty( $data ) ) {
			WP_CLI::warning( 'No terms to display in this batch.' );
			return;
		}

		$format = $assoc_args['format'] ?? 'table';
		$fields = $assoc_args['fields'] ?? 'term_id,taxonomy,wp_path,source_permalink_path';
		WP_CLI\Utils\format_items( $format, $data, explode( ',', $fields ) );
	}

	/**
	 * List posts where the source permalink does not match the current WordPress permalink.
	 *
	 * Queries all posts with source permalinks and filters to show only those where
	 * the source path differs from the current WordPress path. Useful for debugging
	 * URL changes and identifying redirect opportunities.
	 *
	 * By default, uses fast string comparison to flag any URL differences. This is quick
	 * but may show URLs that actually work via WordPress's canonical redirect system.
	 *
	 * Use --check-redirects to verify if URLs actually resolve correctly via WordPress's
	 * url_to_postid(). This is slower but only flags URLs that genuinely don't work.
	 *
	 * @param array $pos_args   Positional arguments (unused).
	 * @param array $assoc_args Associative arguments. Optional: 'source-domain', 'field', 'format',
	 *                          'start', 'end', 'num-items', 'check-redirects'.
	 *
	 * @return void
	 */
	public static function post_list_mismatches( array $pos_args, array $assoc_args ): void {
		$source_domain   = $assoc_args['source-domain'] ?? '';
		$check_redirects = isset( $assoc_args['check-redirects'] ) && $assoc_args['check-redirects'];
		$posts_data      = OriginalValueCommands::get_posts_data_for_key( OriginalPermalink::KEY, $assoc_args );

		if ( empty( $posts_data['total'] ) ) {
			WP_CLI::warning( 'No posts found with source permalinks.' );
			return;
		}

		// Build data array and filter to mismatches.
		$data = [];
		foreach ( $posts_data['results'] as $row ) {
			$source_permalink = $row->meta_value;
			$wp_permalink     = get_permalink( $row->post_id );
			$wp_path          = OriginalPermalink::ensure_path_format( $wp_permalink );

			if ( $check_redirects ) {
				// Check redirects mode: Only flag if source URL doesn't resolve to the correct post.
				// WordPress's canonical redirect system can handle many URL variations.
				// Use current site's domain since url_to_postid() only works with current site URLs.
				$source_path      = OriginalPermalink::ensure_path_format( $source_permalink );
				$source_path_url = untrailingslashit( home_url( $source_path ) );
				// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.url_to_postid_url_to_postid
				$resolved_post_id = url_to_postid( $source_path_url );

				// Flag as mismatch if source URL doesn't resolve to this post.
				$is_mismatch = $resolved_post_id !== (int) $row->post_id;
			} else {
				// Default mode: Fast string comparison, flag any differences.
				$is_mismatch = mb_strtolower( untrailingslashit( $source_permalink ) ) !== mb_strtolower( untrailingslashit( $wp_path ) );
			}

			if ( $is_mismatch ) {
				$data[] = [
					'post_id'               => $row->post_id,
					'wp_path'               => empty( $source_domain ) ? untrailingslashit( $wp_path ) : untrailingslashit( $wp_permalink ),
					'source_permalink_path' => self::maybe_convert_to_url( $source_permalink, $source_domain ),
				];
			}
		}

		if ( empty( $data ) ) {
			$mode_text = $check_redirects ? ' (checked canonical redirects)' : ' (string comparison)';
			WP_CLI::success( 'No mismatches found in this batch' . $mode_text . '.' );
			return;
		}

		$format = $assoc_args['format'] ?? 'table';
		$fields = $assoc_args['fields'] ?? 'post_id,wp_path,source_permalink_path';
		WP_CLI\Utils\format_items( $format, $data, explode( ',', $fields ) );


		WP_CLI::line(
			sprintf(
				'Found %d mismatches',
				count( $data ),
			)
		);
		WP_CLI::line(
			$check_redirects ? '(URLs that resolve correctly via canonical redirect are not shown)' : '(string comparison - some URLs may work via canonical redirect)'
		);
	}

	/**
	 * List terms where the source permalink does not match the current WordPress permalink.
	 *
	 * Queries all terms with source permalinks and filters to show only those where
	 * the source path differs from the current WordPress path. Useful for debugging
	 * URL changes and identifying redirect opportunities.
	 *
	 * Uses exact string comparison. Future enhancements could include:
	 * - Case-insensitive comparison
	 * - Ignoring trailing slashes
	 * - Partial/fuzzy matching
	 *
	 * @param array $pos_args   Positional arguments (unused).
	 * @param array $assoc_args Associative arguments. Optional: 'source-domain', 'field', 'format',
	 *                          'start', 'end', 'num-items'.
	 *
	 * @return void
	 */
	public static function term_list_mismatches( array $pos_args, array $assoc_args ): void {
		$source_domain = $assoc_args['source-domain'] ?? '';
		$terms_data    = OriginalValueCommands::get_terms_data_for_key( OriginalPermalink::KEY, $assoc_args );

		if ( empty( $terms_data['total'] ) ) {
			WP_CLI::warning( 'No terms found with source permalinks.' );
			return;
		}

		// Build data array and filter to mismatches.
		$data = [];
		foreach ( $terms_data['results'] as $row ) {
			$term = get_term( $row->term_id );
			if ( empty( $term ) || is_wp_error( $term ) ) {
				continue;
			}

			$source_permalink = $row->meta_value;
			$term_link        = get_term_link( $term );
			$wp_path          = '';

			if ( ! is_wp_error( $term_link ) ) {
				$wp_path = OriginalPermalink::ensure_path_format( $term_link );
			}

			// Only include if paths don't match (exact comparison, normalized).
			if ( untrailingslashit( $source_permalink ) !== untrailingslashit( $wp_path ) ) {
				$data[] = [
					'term_id'               => $row->term_id,
					'taxonomy'              => $term->taxonomy,
					'wp_path'               => empty( $source_domain ) ? untrailingslashit( $wp_path ) : $term_link,
					'source_permalink_path' => self::maybe_convert_to_url( $source_permalink, $source_domain ),
				];
			}
		}

		if ( empty( $data ) ) {
			WP_CLI::success( 'No mismatches found in this batch.' );
			return;
		}

		WP_CLI::line(
			sprintf(
				'Found %d mismatches in batch (showing terms %d to %d of %d total).',
				count( $data ),
				$terms_data['batch_args']['start'],
				min( $terms_data['batch_args']['end'] - 1, $terms_data['total'] ),
				$terms_data['total'] 
			) 
		);

		$format = $assoc_args['format'] ?? 'table';
		$fields = $assoc_args['fields'] ?? 'term_id,taxonomy,wp_path,source_permalink_path';
		WP_CLI\Utils\format_items( $format, $data, explode( ',', $fields ) );
	}

	/**
	 * Convert path to URL if source domain is provided.
	 *
	 * @param string $path          The path to convert.
	 * @param string $source_domain The source domain to prepend (optional). Can be with or without https://.
	 *
	 * @return string The path or full URL.
	 */
	private static function maybe_convert_to_url( string $path, string $source_domain = '' ): string {
		if ( empty( $source_domain ) ) {
			return $path;
		}

		// Add https:// if not present.
		if ( ! preg_match( '#^https?://#', $source_domain ) ) {
			$source_domain = 'https://' . $source_domain;
		}

		return untrailingslashit( $source_domain ) . $path;
	}
}
