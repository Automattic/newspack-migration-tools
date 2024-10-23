<?php
/**
 * Gutenberg Block Transformer.
 *
 * Methods for encoding and decoding blocks in posts as base64 to "hide" them from the NCC.
 */

namespace Newspack\MigrationTools\Command;

use Newspack\MigrationTools\Log\CliLogger;
use Newspack\MigrationTools\Log\FileLogger;
use Newspack\MigrationTools\Log\Log;
use Newspack\MigrationTools\Logic\GutenbergBlockTransformer;
use Newspack\MigrationTools\Util\PostSelect;

class BlockTransformerCommand implements WpCliCommandInterface {

	/**
	 * {@inheritDoc}
	 */
	public static function get_cli_commands(): array {

		return [
			[
				'newspack-migration-tools transform-blocks-encode',
				[ __CLASS__, 'cmd_blocks_encode' ],
				[
					'shortdesc' => '"Obfuscate" blocks in posts by encoding them as base64.',
					'synopsis'  => [
						...PostSelect::$command_args,
					],
				],
			],
			[
				'newspack-migration-tools transform-blocks-decode',
				[ __CLASS__, 'cmd_blocks_decode' ],
				[
					'shortdesc' => '"Un-obfuscate" blocks in posts by decoding them.',
					'synopsis'  => [
						...PostSelect::$command_args,
					],
				],
			],
			[
				'newspack-migration-tools transform-blocks-nudge',
				[ __CLASS__, 'cmd_blocks_nudge' ],
				[
					'shortdesc' => '"Nudge" posts so NCC picks them up',
					'synopsis'  => [
						...PostSelect::$command_args,
					],
				],
			],
		];
	}

	/**
	 * Nudge posts so the NCC picks them up.
	 *
	 * This is very low-tech and just adds a newline to the beginning of the post content.
	 */
	public static function cmd_blocks_nudge( array $pos_args, array $assoc_args ): void {
		$post_range = PostSelect::get_id_range( $assoc_args );
		if ( empty( $post_range ) ) {
			CliLogger::log( 'No posts to nudge. Try a bigger range of post ids maybe?' );

			return;
		}

		$post_ids_format = implode( ', ', array_fill( 0, count( $post_range ), '%d' ) );
		global $wpdb;

		// Nudge the posts in the range that might need it.
		// The number of placeholders is correct - disable the check for the query.
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"UPDATE {$wpdb->posts} SET post_content = CONCAT(%s, post_content)
    				WHERE ID IN ($post_ids_format)
					AND post_content LIKE %s",
			[
				PHP_EOL,
				...$post_range,
				$wpdb->esc_like( '<!--' ) . '%',
			]
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$posts_nudged = $wpdb->query( $sql );
		$high         = max( $post_range );
		$low          = min( $post_range );

		CliLogger::log(
			sprintf(
				'Nudged %d posts between (and including) %d and %d ID needed nudging. Note that the nudge only happens on posts that have <!-- as the very first chars',
				$posts_nudged,
				$low,
				$high
			)
		);
	}

	/**
	 * @throws \Exception If things go wrong.
	 */
	public static function cmd_blocks_decode( array $pos_args, array $assoc_args ): void {
		$logfile = sprintf( '%s-%s.log', __FUNCTION__, wp_date( 'Y-m-d-H-i-s' ) );

		$block_transformer = GutenbergBlockTransformer::get_instance();

		$post_id_range   = PostSelect::get_id_range( $assoc_args );
		$post_ids_format = implode( ', ', array_fill( 0, count( $post_id_range ), '%d' ) );

		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$posts_to_decode = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts}
    				WHERE ID IN ($post_ids_format)
					AND post_content LIKE %s
					ORDER BY ID DESC",
				[ ...$post_id_range, '%' . $wpdb->esc_like( '[BLOCK-TRANSFORMER:' ) . '%' ]
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber


		$num_posts_found = count( $posts_to_decode );
		FileLogger::log( $logfile, sprintf( 'Found %d posts to decode', $num_posts_found ), Log::INFO );

		$decoded_posts_counter = 0;
		foreach ( $posts_to_decode as $post ) {
			$content = $block_transformer->decode_post_content( $post->post_content );
			if ( $content === $post->post_content ) {
				// No changes - no more to do here.
				continue;
			}

			$updated = wp_update_post(
				[
					'ID'           => $post->ID,
					'post_content' => $content,
				]
			);
			if ( 0 === $updated || is_wp_error( $updated ) ) {
				FileLogger::log( $logfile, sprintf( 'Could not decode blocks in ID %d %s', $post->ID, get_post_permalink( $post->ID ) ), Log::ERROR );
			} else {
				FileLogger::log( $logfile, sprintf( 'Decoded blocks in ID %d %s', $post->ID, get_post_permalink( $post->ID ) ), Log::SUCCESS );
				++$decoded_posts_counter;
			}

			if ( 0 === $decoded_posts_counter % 25 ) {
				$spacer = str_repeat( ' ', 10 );
				CliLogger::log(
					sprintf(
						'%s ==== Decoded %d of %d posts. %d remaining ==== %s',
						$spacer,
						$decoded_posts_counter,
						$num_posts_found,
						( $num_posts_found - $decoded_posts_counter ),
						$spacer
					)
				);
			}
		}

		FileLogger::log( $logfile, sprintf( '%d posts have been decoded', count( $posts_to_decode ) ), Log::SUCCESS );
		wp_cache_flush();
	}

	/**
	 * Obfuscate blocks in posts and optionally reset NCC to only work on the posts in the range.
	 *
	 * @param array $pos_args   The positional arguments passed to the command.
	 * @param array $assoc_args The associative arguments passed to the command.
	 *
	 * @return void
	 * @throws \Exception If things go wrong.
	 */
	public static function cmd_blocks_encode( array $pos_args, array $assoc_args ): void {
		$logfile = sprintf( '%s-%s.log', __FUNCTION__, wp_date( 'Y-m-d-H-i-s' ) );

		$block_transformer = GutenbergBlockTransformer::get_instance();

		$post_id_range   = PostSelect::get_id_range( $assoc_args );
		$post_ids_format = implode( ', ', array_fill( 0, count( $post_id_range ), '%d' ) );

		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$posts_to_encode = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts}
    				WHERE ID IN ($post_ids_format)
					AND post_content LIKE %s
					ORDER BY ID DESC",
				[ ...$post_id_range, $wpdb->esc_like( '<!-- ' ) . '%' ]
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$num_posts_found = count( $posts_to_encode );
		FileLogger::log( $logfile, sprintf( 'Found %d posts to encode', $num_posts_found ), Log::INFO );

		$encoded_posts_counter = 0;
		foreach ( $posts_to_encode as $post ) {
			$content = $block_transformer->encode_post_content( $post->post_content );
			if ( $content === $post->post_content ) {
				// No changes - no more to do here.
				continue;
			}

			$updated = wp_update_post(
				[
					'ID'           => $post->ID,
					'post_content' => $content,
				]
			);
			if ( 0 === $updated || is_wp_error( $updated ) ) {
				FileLogger::log( $logfile, sprintf( 'Could not encode blocks in post ID %d %s', $post->ID, get_post_permalink( $post->ID ) ), Log::ERROR );
				continue;
			} else {
				FileLogger::log( $logfile, sprintf( 'Encoded blocks in post ID %d  %s', $post->ID, get_post_permalink( $post->ID ) ), Log::SUCCESS );

				++$encoded_posts_counter;
			}

			if ( 0 === $encoded_posts_counter % 25 ) {
				$spacer = str_repeat( ' ', 10 );
				CliLogger::log(
					sprintf(
						'%s ==== Encoded %d of %d posts. %d remaining ==== %s',
						$spacer,
						$encoded_posts_counter,
						$num_posts_found,
						( $num_posts_found - $encoded_posts_counter ),
						$spacer
					)
				);
			}
		}

		if ( ( $assoc_args['post-id'] ?? false ) ) {
			$decode_command = sprintf( 'wp newspack-migration-tools transform-blocks-decode --post-id=%s', $assoc_args['post-id'] );
		} else {
			$decode_command = sprintf(
				'To decode the blocks AFTER running the NCC, run this:%s wp newspack-migration-tools transform-blocks-decode --min-post-id=%d --max-post-id=%d',
				PHP_EOL,
				min( $post_id_range ),
				max( $post_id_range )
			);
		}
		if ( ( $assoc_args['post-types'] ?? false ) ) {
			$decode_command .= ' --post-types=' . $assoc_args['post-types'];
		}
		FileLogger::log( $logfile, sprintf( '%d posts needed encoding', $encoded_posts_counter ), Log::SUCCESS );
		FileLogger::log( $logfile, sprintf( 'To decode the blocks AFTER running the NCC, run this:%s %s', PHP_EOL, $decode_command ), Log::INFO );

		wp_cache_flush();
	}
}
