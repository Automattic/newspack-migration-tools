<?php
/**
 * Gutenberg Block Transformer.
 *
 * Methods for encoding and decoding blocks in posts as base64.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Logic;

use WP_CLI;

/**
 * Class GutenbergBlockTransformer.
 *
 * Encodes blocks in posts as base64, so they don't get mangled by converting blocks from classic.
 */
class GutenbergBlockTransformer {

	/**
	 * Constructor private to ensure singleton.
	 */
	private function __construct() {
		$this->block_generator = new GutenbergBlockGenerator();
	}

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		static $instance = null;
		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Encode blocks in posts as base64.
	 *
	 * @param string $post_content The post content to encode.
	 *
	 * @return string The post content with all blocks base64 encoded.
	 */
	public function encode_post_content( string $post_content ): string {
		if ( ! str_contains( $post_content, '<!-- ' ) ) {
			return $post_content;
		}

		$blocks        = parse_blocks( $post_content );
		$actual_blocks = array_filter( $blocks, fn( $block ) => ! empty( $block['blockName'] ) && ! str_contains( $block['innerHTML'], '[BLOCK-TRANSFORMER:' ) );

		if ( empty( $actual_blocks ) ) {
			return $post_content;
		}

		$encoded_blocks = array_map( fn( $block ) => $this->encode_block( $block ), $actual_blocks );
		foreach ( $encoded_blocks as $idx => $encoded ) {
			$blocks[ $idx ] = $encoded;
		}

		return serialize_blocks( $blocks );
	}

	/**
	 * Encode a block's content as base64 string inside a paragraph block.
	 *
	 * @param array $block block to encode.
	 *
	 * @return array Paragraph block with the encoded block as innerHTML.
	 */
	public function encode_block( array $block ): array {
		$as_string = serialize_block( $block );

		$anchor  = '[BLOCK-TRANSFORMER:' . base64_encode( $as_string ) . ']';
		$content = '<pre class="wp-block-preformatted">' . $anchor . '</pre>' . str_repeat( PHP_EOL, 2 );

		return [
			'blockName'    => null, // On purpose.
			'attrs'        => [],
			'innerBlocks'  => [],
			'innerHTML'    => $content,
			'innerContent' => [ $content ],
		];
	}

	/**
	 * Decode blocks in posts from base64.
	 *
	 * @param string $post_content Post content to decode.
	 *
	 * @return string The post content with all blocks decoded.
	 */
	public function decode_post_content( string $post_content ): string {
		if ( ! str_contains( $post_content, '[BLOCK-TRANSFORMER:' ) ) {
			return $post_content;
		}
		$blocks         = parse_blocks( $post_content );
		$encoded_blocks = array_filter( $blocks, fn( $block ) => str_contains( $block['innerHTML'], '[BLOCK-TRANSFORMER:' ) );

		if ( empty( $encoded_blocks ) ) {
			return $post_content;
		}
		foreach ( $encoded_blocks as $idx => $encoded ) {
			$decoded = $this->decode_block( $encoded['innerHTML'] );
			if ( ! empty( $decoded ) ) {
				$blocks[ $idx ] = $decoded;
			} else {
				WP_CLI::log( sprintf( 'Failed to decode block %d', $idx ) );
			}
		}

		return serialize_blocks( $blocks );
	}

	/**
	 * Decode a block from base64.
	 *
	 * @param string $encoded_block Block to decode.
	 *
	 * @return array The decoded block.
	 */
	public function decode_block( string $encoded_block ): array {
		// See https://base64.guru/learn/base64-characters for chars in base64.
		preg_match( '/\[BLOCK-TRANSFORMER:([A-Za-z0-9+\\/=]+)\]/', $encoded_block, $matches );
		if ( empty( $matches[1] ) ) {
			return [];
		}

		$parsed = parse_blocks( base64_decode( $matches[1], true ) );
		if ( ! empty( $parsed[0]['blockName'] ) ) {
			return $parsed[0];
		}

		return [];
	}
}
