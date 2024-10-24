<?php

namespace Newspack\MigrationTools\Util;

use Newspack\MigrationTools\Log\CliLogger;

class PostSelect {
	/**
	 *
	 * @var array Args to use for commands.
	 */
	public static array $command_args = [
		'post-id'     => [
			'type'        => 'assoc',
			'name'        => 'post-id',
			'description' => 'The ID of the post to process.',
			'optional'    => true,
			'repeating'   => false,
		],
		'num-items'   => [
			'type'        => 'assoc',
			'name'        => 'num-items',
			'description' => 'The number of posts to process.',
			'optional'    => true,
			'repeating'   => false,
		],
		'min-post-id' => [
			'type'        => 'assoc',
			'name'        => 'min-post-id',
			'description' => 'The minimum post ID to process.',
			'optional'    => true,
			'repeating'   => false,
		],
		'max-post-id' => [
			'type'        => 'assoc',
			'name'        => 'max-post-id',
			'description' => 'The maximum post ID to process.',
			'optional'    => true,
			'repeating'   => false,
		],
		[
			'type'        => 'assoc',
			'name'        => 'post-types',
			'description' => 'Comma-separated list of post types to process. Default is "post".',
			'optional'    => true,
			'repeating'   => false,
		],
	];

	/**
	 * Get post IDs within the range specified in the arguments for the commands in this class.
	 *
	 * Note that min and max are both inclusive.
	 *
	 * @param array $args The associative arguments. See the $command_args var in this class too.
	 *                    post-types: Comma-separated list of post types to process. Default is "post".
	 *                    num-items: The number of posts to process.
	 *                    min-post-id: The minimum post ID to process.
	 *                    max-post-id: The maximum post ID to process.
	 *                    post-id: A comma-separated list of post IDs. If you pass this you just get the list back.
	 *
	 * @return array of post IDs.
	 */
	public static function get_id_range( array $args ): array {
		global $wpdb;

		if ( ! empty( $args['post-id'] ) ) {
			$post_ids = explode( ',', $args['post-id'] );
			// Prepare the SQL query to check if the post IDs exist (and are published).
			$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

			// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM $wpdb->posts WHERE ID IN ($placeholders) AND post_status = 'publish' ORDER BY ID DESC",
					...$post_ids
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			return $ids;
		}

		$post_types = [ 'post' ];
		if ( ! empty( $args['post-types'] ) ) {
			$post_types = array_map( 'trim', explode( ',', $args['post-types'] ) );
		}

		$num_items   = $args['num-items'] ?? PHP_INT_MAX;
		$min_post_id = $args['min-post-id'] ?? 0;
		$max_post_id = $args['max-post-id'] ?? PHP_INT_MAX;
		if ( $min_post_id > $max_post_id ) {
			CliLogger::error( 'min-post-id must be less than or equal to max-post-id', true );
		}

		$post_types_format = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );

		// The number of placeholders is correct - disable the check for the query.
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts 
						WHERE post_type IN ($post_types_format) 
							AND post_status = 'publish'
							AND ID >= %d
							AND ID <= %d
						ORDER BY ID DESC 
						LIMIT %d",
				[
					...$post_types,
					$min_post_id,
					$max_post_id,
					$num_items,
				]
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $ids;
	}
}
