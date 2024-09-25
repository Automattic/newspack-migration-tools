<?php
/**
 * Gutenberg Block Transformer.
 *
 * Methods for encoding and decoding blocks in posts as base64 to "hide" them from the NCC.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Command\General;

use Newspack\MigrationTools\Command\WpCliCommandTrait;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use NewspackCustomContentMigrator\Logic\GutenbergBlockTransformer;
use NewspackCustomContentMigrator\Utils\Logger;
use WP_CLI;
use WP_CLI\ExitException;

class BlockTransformerCommand implements RegisterCommandInterface {

	use WpCliCommandTrait;

	private Logger $logger;

	private function __construct() {
		$this->logger = new Logger();
	}

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {
		$generic_args = [
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

		WP_CLI::add_command(
			'newspack-content-migrator transform-blocks-encode',
			self::get_command_closure( 'cmd_blocks_encode' ),
			[
				'shortdesc' => '"Obfuscate" blocks in posts by encoding them as base64.',
				'synopsis'  => [
					...$generic_args,
				],
			]
		);
		WP_CLI::add_command(
			'newspack-content-migrator transform-blocks-decode',
			self::get_command_closure( 'cmd_blocks_decode' ),
			[
				'shortdesc' => '"Un-obfuscate" blocks in posts by decoding them.',
				'synopsis'  => [
					...$generic_args,
				],
			]
		);

		WP_CLI::add_command(
			'newspack-content-migrator transform-blocks-nudge',
			self::get_command_closure( 'cmd_blocks_nudge' ),
			[
				'shortdesc' => '"Nudge" posts so NCC picks them up',
				'synopsis'  => [
					...$generic_args,
				],
			]
		);
	}

	/**
	 * @throws ExitException
	 */
	public function cmd_blocks_nudge( array $pos_args, array $assoc_args ): void {
		$post_range = $this->get_post_id_range( $assoc_args );
		if ( empty( $post_range ) ) {
			WP_CLI::log( 'No posts to nudge. Try a bigger range of post ids maybe?' );

			return;
		}

		$post_ids_format = implode( ', ', array_fill( 0, count( $post_range ), '%d' ) );
		global $wpdb;

		// Nudge the posts in the range that might need it.
		$sql = $wpdb->prepare(
			"UPDATE {$wpdb->posts} SET post_content = CONCAT(%s, post_content)
    				WHERE ID IN ($post_ids_format)
					AND post_content LIKE %s",
			[
				PHP_EOL,
				...$post_range,
				$wpdb->esc_like( '<!--' ) . '%',
			]
		);

		$posts_nudged = $wpdb->query( $sql );
		$high         = max( $post_range );
		$low          = min( $post_range );

		WP_CLI::log( sprintf( 'Nudged %d posts between (and including) %d and %d ID', $posts_nudged, $low, $high ) );
	}


	public function cmd_blocks_decode( array $pos_args, array $assoc_args ): void {
		$logfile = sprintf( '%s-%s.log', __FUNCTION__, wp_date( 'Y-m-d-H-i-s' ) );

		$block_transformer = GutenbergBlockTransformer::get_instance();

		$post_id_range   = $this->get_post_id_range( $assoc_args );
		$post_ids_format = implode( ', ', array_fill( 0, count( $post_id_range ), '%d' ) );

		global $wpdb;
		$sql             = $wpdb->prepare(
			"SELECT ID, post_content FROM {$wpdb->posts}
    				WHERE ID IN ($post_ids_format)
					AND post_content LIKE %s
					ORDER BY ID DESC",
			[ ...$post_id_range, '%' . $wpdb->esc_like( '[BLOCK-TRANSFORMER:' ) . '%' ]
		);
		$posts_to_decode = $wpdb->get_results( $sql );

		$num_posts_found = count( $posts_to_decode );
		$this->logger->log( $logfile, sprintf( 'Found %d posts to decode', $num_posts_found ), Logger::INFO );

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
				$this->logger->log( $logfile, sprintf( 'Could not decode blocks in ID %d %s', $post->ID, get_permalink( $post->ID ) ), Logger::ERROR );
			} else {
				$this->logger->log( $logfile, sprintf( 'Decoded blocks in ID %d %s', $post->ID, get_permalink( $post->ID ) ), Logger::SUCCESS );
				++$decoded_posts_counter;
			}

			if ( 0 === $decoded_posts_counter % 25 ) {
				$spacer = str_repeat( ' ', 10 );
				WP_CLI::log(
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

		$this->logger->log( $logfile, sprintf( '%d posts have been decoded', count( $posts_to_decode ) ), Logger::SUCCESS );
		wp_cache_flush();
	}

	/**
	 * Obfuscate blocks in posts and optionally reset NCC to only work on the posts in the range.
	 *
	 * @param array $pos_args The positional arguments passed to the command.
	 * @param array $assoc_args The associative arguments passed to the command.
	 *
	 * @return void
	 * @throws ExitException
	 */
	public function cmd_blocks_encode( array $pos_args, array $assoc_args ): void {
		$logfile = sprintf( '%s-%s.log', __FUNCTION__, wp_date( 'Y-m-d-H-i-s' ) );

		$block_transformer = GutenbergBlockTransformer::get_instance();

		$post_id_range   = $this->get_post_id_range( $assoc_args );
		$post_ids_format = implode( ', ', array_fill( 0, count( $post_id_range ), '%d' ) );

		global $wpdb;
		$posts_to_encode = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts}
    				WHERE ID IN ($post_ids_format)
					AND post_content LIKE %s
					ORDER BY ID DESC",
				[ ...$post_id_range, $wpdb->esc_like( '<!-- ' ) . '%' ]
			)
		);
		$num_posts_found = count( $posts_to_encode );
		$this->logger->log( $logfile, sprintf( 'Found %d posts to encode', $num_posts_found ), Logger::INFO );

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
				$this->logger->log( $logfile, sprintf( 'Could not encode blocks in post ID %d %s', $post->ID, get_permalink( $post->ID ) ), Logger::ERROR );
				continue;
			} else {
				$this->logger->log( $logfile, sprintf( 'Encoded blocks in post ID %d  %s', $post->ID, get_permalink( $post->ID ) ), Logger::SUCCESS );

				++$encoded_posts_counter;
			}

			if ( 0 === $encoded_posts_counter % 25 ) {
				$spacer = str_repeat( ' ', 10 );
				WP_CLI::log(
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
			$decode_command = sprintf( 'wp newspack-content-migrator transform-blocks-decode --post-id=%s', $assoc_args['post-id'] );
		} else {
			$decode_command = sprintf(
				'To decode the blocks AFTER running the NCC, run this:%s wp newspack-content-migrator transform-blocks-decode --min-post-id=%d --max-post-id=%d',
				PHP_EOL,
				min( $post_id_range ),
				max( $post_id_range )
			);
		}
		if ( ( $assoc_args['post-types'] ?? false ) ) {
			$decode_command .= ' --post-types=' . $assoc_args['post-types'];
		}
		$this->logger->log( $logfile, sprintf( '%d posts needed encoding', $encoded_posts_counter ), Logger::SUCCESS );
		$this->logger->log( $logfile, sprintf( 'To decode the blocks AFTER running the NCC, run this:%s %s', PHP_EOL, $decode_command ), Logger::INFO );

		wp_cache_flush();
	}


	/**
	 * Get posts within the range specified in the arguments for the commands in this class.
	 *
	 * @param array $assoc_args The associative arguments passed to the command.
	 *
	 * @return array of post IDs.
	 */
	private function get_post_id_range( array $assoc_args ): array {
		$post_types = [ 'post' ];
		if ( ! empty( $assoc_args['post-types'] ) ) {
			$post_types = explode( ',', $assoc_args['post-types'] );
		}
		global $wpdb;

		if ( ! ( $assoc_args['post-id'] ?? false ) ) {
			$num_items   = $assoc_args['num-items'] ?? PHP_INT_MAX;
			$min_post_id = $assoc_args['min-post-id'] ?? 0;
			$max_post_id = $assoc_args['max-post-id'] ?? PHP_INT_MAX;
			if ($min_post_id > $max_post_id ) {
				WP_CLI::error( 'min-post-id must be less than or equal to max-post-id' );
			}

			$post_types_format = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );

			$post_range = $wpdb->get_col(
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
		} else {
			$post_range = explode( ',', $assoc_args['post-id'] );
		}

		return $post_range;
	}

}
