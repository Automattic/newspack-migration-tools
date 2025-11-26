<?php
/**
 * WP-CLI commands for managing source permalinks from migrated content.
 *
 * Provides commands to retrieve, list, and delete source permalink metadata
 * that is stored during content migration. Source permalinks represent the
 * original URLs from the source site before migration to WordPress. @see docs/source-permalinks.md Full documentation with usage examples.
 *
 * @package Newspack\MigrationTools
 */

namespace Newspack\MigrationTools\Command;

use Newspack\MigrationTools\Logic\SourcePermalinkHelper;
use Newspack\MigrationTools\NMT;
use Newspack\MigrationTools\Util\BatchLogic;
use WP_CLI;

/**
 * WP-CLI command class for source permalink management.
 *
 * @see docs/source-permalinks.md Full documentation with usage examples
 */
class SourcePermalink implements WpCliCommandInterface {

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
				'newspack-migration-tools source-permalink post get',
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
				'newspack-migration-tools source-permalink post list',
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
				'newspack-migration-tools source-permalink term get',
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
				'newspack-migration-tools source-permalink term list',
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
				'newspack-migration-tools source-permalink delete',
				[ self::class, 'delete_all' ],
				[
					'shortdesc' => 'Cleans up source permalink metadata for posts and terms. Use when you no longer need the source permalinks after a migration.',
					'synopsis'  => [
						[
							'type'        => 'assoc',
							'name'        => 'type',
							'description' => 'Type to delete: posts, terms, or both.',
							'optional'    => true,
							'default'     => 'both',
							'options'     => [ 'posts', 'terms', 'both' ],
							'repeating'   => false,
						],
						[
							'type'        => 'flag',
							'name'        => 'dry-run',
							'description' => 'Show what would be deleted without actually deleting.',
							'optional'    => true,
							'repeating'   => false,
						],
						[
							'type'        => 'flag',
							'name'        => 'yes',
							'description' => 'Skip confirmation prompt.',
							'optional'    => true,
							'repeating'   => false,
						],
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

		$source_permalink = SourcePermalinkHelper::get_post_source_permalink( $post->ID );
		if ( empty( $source_permalink ) ) {
			NMT::exit_with_message( sprintf( 'No source permalink found for post ID %d.', $post->ID ) );
		}

		$source_domain = $assoc_args['source-domain'] ?? '';
		$wp_permalink  = get_permalink( $post->ID );

		$data = [
			'post_id'               => $post->ID,
			'wp_path'               => empty( $source_domain ) ? SourcePermalinkHelper::ensure_path_format( $wp_permalink ) : $wp_permalink,
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
		global $wpdb;

		$batch_args    = BatchLogic::validate_and_get_batch_args( $assoc_args );
		$source_domain = $assoc_args['source-domain'] ?? '';

		// First get total count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_posts = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				SourcePermalinkHelper::POSTS_META_KEY
			)
		);

		if ( 0 === $total_posts ) {
			WP_CLI::warning( 'No posts found with source permalinks.' );

			return;
		}

		// Calculate offset and limit for SQL query.
		$offset = $batch_args['start'] - 1; // Convert to 0-indexed.
		$limit  = min( $batch_args['end'], $total_posts ) - $batch_args['start'];

		if ( $offset >= $total_posts ) {
			WP_CLI::warning( sprintf( 'Start index %d exceeds total posts %d.', $batch_args['start'], $total_posts ) );

			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY post_id LIMIT %d OFFSET %d",
				SourcePermalinkHelper::POSTS_META_KEY,
				$limit,
				$offset
			)
		);

		WP_CLI::line( sprintf( 'Showing posts %d to %d of %d total.', $batch_args['start'], min( $batch_args['end'] - 1, $total_posts ), $total_posts ) );

		// Build data array.
		$data = [];
		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( empty( $post ) ) {
				continue;
			}

			$source_permalink = SourcePermalinkHelper::get_post_source_permalink( $post_id );
			$wp_permalink     = get_permalink( $post->ID );

			$data[] = [
				'post_id'               => $post_id,
				'wp_path'               => empty( $source_domain ) ? SourcePermalinkHelper::ensure_path_format( $wp_permalink ) : $wp_permalink,
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

		$source_permalink = SourcePermalinkHelper::get_term_source_permalink( $term->term_id );
		if ( empty( $source_permalink ) ) {
			NMT::exit_with_message( sprintf( 'No source permalink found for term ID %d.', $term->term_id ) );
		}

		$source_domain = $assoc_args['source-domain'] ?? '';
		$term_link     = get_term_link( $term );
		$wp_path       = '';

		if ( ! is_wp_error( $term_link ) ) {
			$wp_path = empty( $source_domain ) ? SourcePermalinkHelper::ensure_path_format( $term_link ) : $term_link;
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
		global $wpdb;

		$batch_args    = BatchLogic::validate_and_get_batch_args( $assoc_args );
		$source_domain = $assoc_args['source-domain'] ?? '';

		// First get total count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_terms = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key = %s",
				SourcePermalinkHelper::TERMS_META_KEY
			)
		);

		if ( 0 === $total_terms ) {
			WP_CLI::warning( 'No terms found with source permalinks.' );

			return;
		}

		// Calculate offset and limit for SQL query.
		$offset = $batch_args['start'] - 1; // Convert to 0-indexed.
		$limit  = min( $batch_args['end'], $total_terms ) - $batch_args['start'];

		if ( $offset >= $total_terms ) {
			WP_CLI::warning( sprintf( 'Start index %d exceeds total terms %d.', $batch_args['start'], $total_terms ) );

			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$term_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = %s ORDER BY term_id LIMIT %d OFFSET %d",
				SourcePermalinkHelper::TERMS_META_KEY,
				$limit,
				$offset
			)
		);

		WP_CLI::line( sprintf( 'Showing terms %d to %d of %d total.', $batch_args['start'], min( $batch_args['end'] - 1, $total_terms ), $total_terms ) );

		// Build data array.
		$data = [];
		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id );
			if ( empty( $term ) || is_wp_error( $term ) ) {
				continue;
			}

			$source_permalink = SourcePermalinkHelper::get_term_source_permalink( $term_id );
			$term_link        = get_term_link( $term );
			$wp_path          = '';

			if ( ! is_wp_error( $term_link ) ) {
				$wp_path = empty( $source_domain ) ? SourcePermalinkHelper::ensure_path_format( $term_link ) : $term_link;
			}

			$data[] = [
				'term_id'               => $term_id,
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
	 * Delete source permalink metadata for posts and terms.
	 *
	 * Bulk deletes source permalink metadata. Supports batching for large datasets,
	 * treating the combined dataset (terms first, then posts) as a single sequence.
	 * Use when source permalinks are no longer needed after migration is complete.
	 *
	 * The combined dataset is ordered as: [term 1..N, post 1..M]. Batching applies
	 * to this sequence, so --num-items=2 deletes 2 items total from this sequence.
	 *
	 * @param array $pos_args   Positional arguments (unused).
	 * @param array $assoc_args Associative arguments. Optional: 'type' (posts|terms|both),
	 *                          'dry-run', 'yes', 'start', 'end', 'num-items'.
	 *
	 * @return void
	 */
	public static function delete_all( array $pos_args, array $assoc_args ): void {
		global $wpdb;

		$batch_args = BatchLogic::validate_and_get_batch_args( $assoc_args );
		$dry_run    = isset( $assoc_args['dry-run'] );
		$yes        = isset( $assoc_args['yes'] );
		$type       = $assoc_args['type'] ?? 'both';

		$delete_posts = in_array( $type, [ 'posts', 'both' ], true );
		$delete_terms = in_array( $type, [ 'terms', 'both' ], true );

		// Get counts.
		$term_count = 0;
		$post_count = 0;

		if ( $delete_terms ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$term_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key = %s",
					SourcePermalinkHelper::TERMS_META_KEY
				)
			);
		}

		if ( $delete_posts ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$post_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
					SourcePermalinkHelper::POSTS_META_KEY
				)
			);
		}

		$total_count = $term_count + $post_count;

		if ( 0 === $total_count ) {
			WP_CLI::success( 'No metadata found to delete.' );

			return;
		}

		// Show total before batching.
		WP_CLI::line( sprintf( 'Found %d total metadata entries (%d terms, %d posts).', $total_count, $term_count, $post_count ) );

		// Apply batch logic to combined dataset.
		$start = $batch_args['start'];
		$end   = min( $batch_args['end'], $total_count );

		if ( $start > $total_count ) {
			WP_CLI::warning( sprintf( 'Start index %d exceeds total entries %d.', $start, $total_count ) );

			return;
		}

		$items_to_delete = $end - $start;

		if ( $items_to_delete <= 0 ) {
			WP_CLI::success( 'No metadata in this batch range.' );

			return;
		}

		WP_CLI::line( sprintf( 'Processing batch: items %d to %d.', $start, $end - 1 ) );

		$term_meta_ids = [];
		$post_meta_ids = [];

		// Collect term meta IDs if batch overlaps with terms (positions 1 to term_count).
		if ( $delete_terms && $start <= $term_count ) {
			$term_start_offset = $start - 1; // Convert to 0-indexed.
			$term_items        = min( $items_to_delete, $term_count - $term_start_offset );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$term_meta_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT meta_id FROM {$wpdb->termmeta} WHERE meta_key = %s ORDER BY meta_id LIMIT %d OFFSET %d",
					SourcePermalinkHelper::TERMS_META_KEY,
					$term_items,
					$term_start_offset
				)
			);
		}

		// Collect post meta IDs if batch extends into posts (positions term_count+1 onwards).
		if ( $delete_posts && $end > $term_count ) {
			$post_start        = max( $start, $term_count + 1 ); // First post position.
			$post_start_offset = $post_start - $term_count - 1; // Convert to 0-indexed relative to posts.
			$post_items        = $end - $post_start;

			if ( $post_items > 0 ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$post_meta_ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_id LIMIT %d OFFSET %d",
						SourcePermalinkHelper::POSTS_META_KEY,
						$post_items,
						$post_start_offset
					)
				);
			}
		}

		$total_to_delete = count( $term_meta_ids ) + count( $post_meta_ids );

		if ( 0 === $total_to_delete ) {
			WP_CLI::success( 'No metadata found to delete in this batch.' );

			return;
		}

		// Show summary.
		if ( count( $term_meta_ids ) > 0 && count( $post_meta_ids ) > 0 ) {
			WP_CLI::line( sprintf( 'Will delete %d term and %d post metadata entries (%d total).', count( $term_meta_ids ), count( $post_meta_ids ), $total_to_delete ) );
		} elseif ( count( $term_meta_ids ) > 0 ) {
			WP_CLI::line( sprintf( 'Will delete %d term metadata entries.', count( $term_meta_ids ) ) );
		} else {
			WP_CLI::line( sprintf( 'Will delete %d post metadata entries.', count( $post_meta_ids ) ) );
		}

		if ( $dry_run ) {
			WP_CLI::success( 'DRY RUN: No data was deleted.' );

			return;
		}

		// Confirm deletion once for all items.
		if ( ! $yes ) {
			WP_CLI::confirm( sprintf( 'Are you sure you want to delete %d metadata entries? This cannot be undone.', $total_to_delete ) );
		}

		$total_deleted = 0;

		// Delete term metadata.
		if ( ! empty( $term_meta_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $term_meta_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted_terms = $wpdb->query(
				$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"DELETE FROM {$wpdb->termmeta} WHERE meta_id IN ($placeholders)",
					...$term_meta_ids
				)
			);

			if ( false === $deleted_terms ) {
				WP_CLI::error( 'Failed to delete term metadata.' );
			}

			WP_CLI::line( sprintf( 'Deleted %d term metadata entries.', $deleted_terms ) );
			$total_deleted += $deleted_terms;
		}

		if ( ! empty( $post_meta_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $post_meta_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted_posts = $wpdb->query(
				$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"DELETE FROM {$wpdb->postmeta} WHERE meta_id IN ($placeholders)",
					...$post_meta_ids
				)
			);

			if ( false === $deleted_posts ) {
				WP_CLI::error( 'Failed to delete post metadata.' );
			}

			WP_CLI::line( sprintf( 'Deleted %d post metadata entries.', $deleted_posts ) );
			$total_deleted += $deleted_posts;
		}

		WP_CLI::success( sprintf( 'Deleted %d total metadata entries.', $total_deleted ) );
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
