<?php

namespace NewspackCustomContentMigrator\Command\General;

use NewspackCustomContentMigrator\Command\InterfaceCommand;
use NewspackCustomContentMigrator\Logic\GutenbergBlockTransformer;
use NewspackCustomContentMigrator\Logic\Posts;
use WP_CLI;

class BlockTransformerCommand implements InterfaceCommand {


	private Posts $posts_logic;

	private function __construct() {
		$this->posts_logic = new Posts();
	}

	public static function get_instance(): self {
		static $instance;
		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * @throws \Exception
	 */
	public function register_commands(): void {
		$generic_args = [
			'synopsis' => '[--post-id=<post-id>] [--dry-run] [--num-items=<num-items>] [--min-post-id=<post-id>] [--max-post-id=<post-id>]',
		];

		WP_CLI::add_command(
			'newspack-content-migrator transform-blocks-encode',
			[ $this, 'cmd_blocks_encode' ],
			[
				'shortdesc' => '"Obfuscate" blocks in posts by encoding them as base64.',
				...$generic_args,
			]
		);
		WP_CLI::add_command(
			'newspack-content-migrator transform-blocks-decode',
			[ $this, 'cmd_blocks_decode' ],
			[
				'shortdesc' => '"Un-obfuscate" blocks in posts by decoding them.',
				...$generic_args,
			]
		);
	}

	public function cmd_blocks_decode( array $pos_args, array $assoc_args ): void {
		$all_posts         = $this->get_all_wp_posts( [ 'publish' ], $assoc_args );
		$block_transformer = GutenbergBlockTransformer::get_instance();

		foreach ( $all_posts as $post ) {
			$content = $block_transformer->decode_post_content( $post->post_content );
			if ( $content === $post->post_content ) {
				// No changes - no more to do here.
				continue;
			}

			wp_update_post(
				[
					'ID'           => $post->ID,
					'post_content' => $content,
				]
			);
			WP_CLI::log( sprintf( 'Decoded blocks in %s', get_permalink( $post->ID ) ) );
		}
	}

	public function cmd_blocks_encode( array $pos_args, array $assoc_args ): void {
		$all_posts         = $this->get_all_wp_posts( [ 'publish' ], $assoc_args );
		$block_transformer = GutenbergBlockTransformer::get_instance();

		foreach ( $all_posts as $post ) {
			$content = $block_transformer->encode_post_content( $post->post_content );
			if ( $content === $post->post_content ) {
				// No changes - no more to do here.
				continue;
			}
			wp_update_post(
				[
					'ID'           => $post->ID,
					'post_content' => $content,
				]
			);
			WP_CLI::log( sprintf( 'Encoded blocks in %s', get_permalink( $post->ID ) ) );
		}
	}

	private function get_all_wp_posts( array $post_statuses, array $assoc_args = [], bool $log_progress = true ): iterable {
		if ( ! empty( $assoc_args['post-id'] ) ) {
			$all_ids = [ $assoc_args['post-id'] ];
		} elseif ( ! empty( $assoc_args['min-post-id'] ) || ! empty( $assoc_args['max-post-id'] ) ) {
			$low     = $assoc_args['min-post-id'] ?? 0;
			$high    = $assoc_args['max-post-id'] ?? PHP_INT_MAX;
			$all_ids = $this->posts_logic->get_post_ids_in_range( $low, $high, [ 'post' ], $post_statuses );
		} else {
			$all_ids = $this->posts_logic->get_all_posts_ids( 'post', $post_statuses );
		}
		if ( ! empty( $assoc_args['num-items'] ) ) {
			$all_ids = array_slice( $all_ids, 0, $assoc_args['num-items'] );
		}

		$total_posts = count( $all_ids );
		$home_url    = home_url();
		$counter     = 0;
		if ( $log_progress ) {
			WP_CLI::log( sprintf( 'Processing %d posts', count( $all_ids ) ) );
		}

		foreach ( $all_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( $post instanceof \WP_Post ) {
				if ( $log_progress ) {
					WP_CLI::log( sprintf( 'Processing post %d/%d: %s', ++ $counter, $total_posts, "${home_url}?p=${post_id}" ) );
				}
				yield $post;
			}
		}
	}
}
