<?php
/**
 * WP-CLI commands for managing original values from migrated content.
 *
 * Provides commands to retrieve, list, and delete original value metadata
 * that is stored during content migration. Original values represent any
 * data from the source site (IDs, titles, URLs, etc.).
 *
 * @package Newspack\MigrationTools
 */

namespace Newspack\MigrationTools\Command;

use Newspack\MigrationTools\Logic\OriginalValueStore;
use Newspack\MigrationTools\NMT;
use Newspack\MigrationTools\Util\BatchLogic;
use WP_CLI;

/**
 * WP-CLI command class for original value management.
 */
class OriginalValueCommands implements WpCliCommandInterface {

	/**
	 * @inheritdoc
	 */
	public static function get_cli_commands(): array {
		$format_arg = [
			'type'        => 'assoc',
			'name'        => 'format',
			'description' => 'Render output in a particular format.',
			'optional'    => true,
			'default'     => 'table',
			'options'     => [ 'table', 'csv', 'json', 'yaml' ],
			'repeating'   => false,
		];

		$key_arg = [
			'type'        => 'positional',
			'name'        => 'key',
			'description' => 'The key to list (e.g., "author_id", "category", "extra_byline", etc).',
			'optional'    => false,
			'repeating'   => false,
		];

		return [
			[
				'newspack-migration-tools original-value post get',
				[ self::class, 'post_get' ],
				[
					'shortdesc' => 'Get an original value for a post.',
					'synopsis'  => [
						[
							'type'        => 'positional',
							'name'        => 'post_id',
							'description' => 'Post ID.',
							'optional'    => false,
							'repeating'   => false,
						],
						$key_arg,
						$format_arg,
					],
				],
			],
			[
				'newspack-migration-tools original-value post list',
				[ self::class, 'post_list' ],
				[
					'shortdesc' => 'List all posts with an original value key.',
					'synopsis'  => [
						$key_arg,
						$format_arg,
						...BatchLogic::get_batch_args(),
					],
				],
			],
			[
				'newspack-migration-tools original-value term get',
				[ self::class, 'term_get' ],
				[
					'shortdesc' => 'Get an original value for a term.',
					'synopsis'  => [
						[
							'type'        => 'positional',
							'name'        => 'term_id',
							'description' => 'Term ID.',
							'optional'    => false,
							'repeating'   => false,
						],
						$key_arg,
						$format_arg,
					],
				],
			],
			[
				'newspack-migration-tools original-value term list',
				[ self::class, 'term_list' ],
				[
					'shortdesc' => 'List all terms with an original value key.',
					'synopsis'  => [
						$key_arg,
						$format_arg,
						...BatchLogic::get_batch_args(),
					],
				],
			],
			[
				'newspack-migration-tools original-value user get',
				[ self::class, 'user_get' ],
				[
					'shortdesc' => 'Get an original value for a user.',
					'synopsis'  => [
						[
							'type'        => 'positional',
							'name'        => 'user_id',
							'description' => 'User ID.',
							'optional'    => false,
							'repeating'   => false,
						],
						$key_arg,
						$format_arg,
					],
				],
			],
			[
				'newspack-migration-tools original-value user list',
				[ self::class, 'user_list' ],
				[
					'shortdesc' => 'List all users with an original value key.',
					'synopsis'  => [
						$key_arg,
						$format_arg,
						...BatchLogic::get_batch_args(),
					],
				],
			],
			[
				'newspack-migration-tools original-value delete',
				[ self::class, 'delete' ],
				[
					'shortdesc' => 'Delete all original values for a specific key.',
					'synopsis'  => [
						$key_arg,
						...BatchLogic::get_batch_args(),
						[
							'type'        => 'flag',
							'name'        => 'posts',
							'description' => 'Only delete post metadata.',
							'optional'    => true,
							'repeating'   => false,
						],
						[
							'type'        => 'flag',
							'name'        => 'terms',
							'description' => 'Only delete term metadata.',
							'optional'    => true,
							'repeating'   => false,
						],
						[
							'type'        => 'flag',
							'name'        => 'users',
							'description' => 'Only delete user metadata.',
							'optional'    => true,
							'repeating'   => false,
						],
					],
				],
			],
		];
	}
	/**
	 * Show a single post's original value.
	 *
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public static function post_get( array $pos_args, array $assoc_args ): void {
		$post_id = (int) $pos_args[0];
		$key     = $pos_args[1];

		$post = get_post( $post_id );
		if ( empty( $post ) ) {
			NMT::exit_with_message( sprintf( 'Post with ID %d not found.', $post_id ) );
		}

		$value = OriginalValueStore::get_for_post( $post_id, $key );
		if ( empty( $value ) ) {
			NMT::exit_with_message( sprintf( 'No value found for post ID %d with key "%s".', $post_id, $key ) );
		}

		$data = [
			[
				'post_id' => $post_id,
				'key'     => $key,
				'value'   => $value,
			],
		];

		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $data, [ 'post_id', 'key', 'value' ] );
	}

	/**
	 * Get posts data for a specific original value key.
	 *
	 * This is a reusable method that handles the querying logic for posts with a specific key.
	 * Other command classes can use this to get the base data and then add their own fields.
	 *
	 * @param string $key        The original value key.
	 * @param array  $assoc_args Associative arguments (batch args, etc).
	 *
	 * @return array|null Array with 'total', 'results', 'batch_args' keys, or null if no data.
	 */
	public static function get_posts_data_for_key( string $key, array $assoc_args ): ?array {
		global $wpdb;

		$meta_key   = OriginalValueStore::key_for( $key );
		$batch_args = BatchLogic::validate_and_get_batch_args( $assoc_args );

		// Get total count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_posts = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				$meta_key
			)
		);

		if ( 0 === $total_posts ) {
			return null;
		}

		$offset = $batch_args['start'] - 1;
		$limit  = min( $batch_args['end'], $total_posts ) - $batch_args['start'];

		if ( $offset >= $total_posts ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY post_id LIMIT %d OFFSET %d",
				$meta_key,
				$limit,
				$offset
			)
		);

		return [
			'total'      => $total_posts,
			'results'    => $results,
			'batch_args' => $batch_args,
		];
	}

	/**
	 * List all posts with a specific original value key.
	 *
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public static function post_list( array $pos_args, array $assoc_args ): void {
		$key        = $pos_args[0];
		$posts_data = self::get_posts_data_for_key( $key, $assoc_args );

		if ( null === $posts_data ) {
			WP_CLI::warning( sprintf( 'No posts found with key "%s".', $key ) );
			return;
		}

		WP_CLI::line( sprintf( 'Showing posts %d to %d of %d total.', $posts_data['batch_args']['start'], min( $posts_data['batch_args']['end'] - 1, $posts_data['total'] ), $posts_data['total'] ) );

		$data = [];
		foreach ( $posts_data['results'] as $row ) {
			$data[] = [
				'post_id' => $row->post_id,
				'value'   => $row->meta_value,
			];
		}

		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $data, [ 'post_id', 'value' ] );
	}

	/**
	 * Show a single term's original value.
	 *
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public static function term_get( array $pos_args, array $assoc_args ): void {
		$term_id = (int) $pos_args[0];
		$key     = $pos_args[1];

		$term = get_term( $term_id );
		if ( empty( $term ) || is_wp_error( $term ) ) {
			NMT::exit_with_message( sprintf( 'Term with ID %d not found.', $term_id ) );
		}

		$value = OriginalValueStore::get_for_term( $term_id, $key );
		if ( empty( $value ) ) {
			NMT::exit_with_message( sprintf( 'No value found for term ID %d with key "%s".', $term_id, $key ) );
		}

		$data = [
			[
				'term_id' => $term_id,
				'key'     => $key,
				'value'   => $value,
			],
		];

		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $data, [ 'term_id', 'key', 'value' ] );
	}

	/**
	 * Get terms data for a specific original value key.
	 *
	 * This is a reusable method that handles the querying logic for terms with a specific key.
	 * Other command classes can use this to get the base data and then add their own fields.
	 *
	 * @param string $key        The original value key.
	 * @param array  $assoc_args Associative arguments (batch args, etc).
	 *
	 * @return array|null Array with 'total', 'results', 'batch_args' keys, or null if no data.
	 */
	public static function get_terms_data_for_key( string $key, array $assoc_args ): ?array {
		global $wpdb;

		$meta_key   = OriginalValueStore::key_for( $key );
		$batch_args = BatchLogic::validate_and_get_batch_args( $assoc_args );

		// Get total count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_terms = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key = %s",
				$meta_key
			)
		);

		if ( 0 === $total_terms ) {
			return null;
		}

		$offset = $batch_args['start'] - 1;
		$limit  = min( $batch_args['end'], $total_terms ) - $batch_args['start'];

		if ( $offset >= $total_terms ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT term_id, meta_value FROM {$wpdb->termmeta} WHERE meta_key = %s ORDER BY term_id LIMIT %d OFFSET %d",
				$meta_key,
				$limit,
				$offset
			)
		);

		return [
			'total'      => $total_terms,
			'results'    => $results,
			'batch_args' => $batch_args,
		];
	}

	/**
	 * List all terms with a specific original value key.
	 *
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public static function term_list( array $pos_args, array $assoc_args ): void {
		$key        = $pos_args[0];
		$terms_data = self::get_terms_data_for_key( $key, $assoc_args );

		if ( null === $terms_data ) {
			WP_CLI::warning( sprintf( 'No terms found with key "%s".', $key ) );
			return;
		}

		WP_CLI::line( sprintf( 'Showing terms %d to %d of %d total.', $terms_data['batch_args']['start'], min( $terms_data['batch_args']['end'] - 1, $terms_data['total'] ), $terms_data['total'] ) );

		$data = [];
		foreach ( $terms_data['results'] as $row ) {
			$data[] = [
				'term_id' => $row->term_id,
				'value'   => $row->meta_value,
			];
		}

		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $data, [ 'term_id', 'value' ] );
	}

	/**
	 * Show a single user's original value.
	 *
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public static function user_get( array $pos_args, array $assoc_args ): void {
		$user_id = (int) $pos_args[0];
		$key     = $pos_args[1];

		$user = get_user_by( 'id', $user_id );
		if ( empty( $user ) ) {
			NMT::exit_with_message( sprintf( 'User with ID %d not found.', $user_id ) );
		}

		$value = OriginalValueStore::get_for_user( $user_id, $key );
		if ( empty( $value ) ) {
			NMT::exit_with_message( sprintf( 'No value found for user ID %d with key "%s".', $user_id, $key ) );
		}

		$data = [
			[
				'user_id' => $user_id,
				'key'     => $key,
				'value'   => $value,
			],
		];

		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $data, [ 'user_id', 'key', 'value' ] );
	}

	/**
	 * List all users with a specific original value key.
	 *
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public static function user_list( array $pos_args, array $assoc_args ): void {
		global $wpdb;

		$key        = $pos_args[0];
		$meta_key   = OriginalValueStore::key_for( $key );
		$batch_args = BatchLogic::validate_and_get_batch_args( $assoc_args );

		// Get total count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_users = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s",
				$meta_key
			)
		);

		if ( 0 === $total_users ) {
			WP_CLI::warning( sprintf( 'No users found with key "%s".', $key ) );
			return;
		}

		$offset = $batch_args['start'] - 1;
		$limit  = min( $batch_args['end'], $total_users ) - $batch_args['start'];

		if ( $offset >= $total_users ) {
			WP_CLI::warning( sprintf( 'Start index %d exceeds total users %d.', $batch_args['start'], $total_users ) );
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s ORDER BY user_id LIMIT %d OFFSET %d",
				$meta_key,
				$limit,
				$offset
			)
		);

		WP_CLI::line( sprintf( 'Showing users %d to %d of %d total.', $batch_args['start'], min( $batch_args['end'] - 1, $total_users ), $total_users ) );

		$data = [];
		foreach ( $results as $row ) {
			$data[] = [
				'user_id' => $row->user_id,
				'value'   => $row->meta_value,
			];
		}

		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $data, [ 'user_id', 'value' ] );
	}

	/**
	 * Delete all original values for a specific key.
	 *
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public static function delete( array $pos_args, array $assoc_args ): void {
		global $wpdb;

		$key = $pos_args[0];

		$delete_posts = isset( $assoc_args['posts'] );
		$delete_terms = isset( $assoc_args['terms'] );
		$delete_users = isset( $assoc_args['users'] );

		// If no flags, delete all.
		if ( ! $delete_posts && ! $delete_terms && ! $delete_users ) {
			$delete_posts = true;
			$delete_terms = true;
			$delete_users = true;
		}

		$batch_args = BatchLogic::validate_and_get_batch_args( $assoc_args );

		$deleted_count = 0;

		if ( $delete_posts ) {
			$meta_key = OriginalValueStore::key_for( $key );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted        = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
					$meta_key
				)
			);
			$deleted_count += $deleted;
			WP_CLI::line( sprintf( 'Deleted %d post meta rows.', $deleted ) );
		}

		if ( $delete_terms ) {
			$meta_key = OriginalValueStore::key_for( $key );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted        = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->termmeta} WHERE meta_key = %s",
					$meta_key
				)
			);
			$deleted_count += $deleted;
			WP_CLI::line( sprintf( 'Deleted %d term meta rows.', $deleted ) );
		}

		if ( $delete_users ) {
			$meta_key = OriginalValueStore::key_for( $key );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted        = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
					$meta_key
				)
			);
			$deleted_count += $deleted;
			WP_CLI::line( sprintf( 'Deleted %d user meta rows.', $deleted ) );
		}

		WP_CLI::success( sprintf( 'Deleted %d total rows for key "%s".', $deleted_count, $key ) );
	}
}
