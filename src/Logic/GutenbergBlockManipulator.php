<?php
/**
 * Helper functions for manipulating Gutenberg block content.
 */

namespace Newspack\MigrationTools\Logic;

class GutenbergBlockManipulator {

	/**
	 * Removes ALL blocks with the given class from the content string.
	 *
	 * @param string $class_name class on block to remove.
	 * @param string $content content string.
	 *
	 * @return array blocks.
	 */
	public static function remove_blocks_with_class( string $class_name, string $content ): array {
		return self::find_blocks_without_class( $class_name, parse_blocks( $content ) );
	}

	/**
	 * Replaces ALL blocks with the given class with the block provided.
	 *
	 * @param string $class_name class on block to replace.
	 * @param string $content content string.
	 * @param array  $new_block block to replace with.
	 *
	 * @return array blocks.
	 */
	public static function replace_blocks_with_class( string $class_name, string $content, array $new_block ): array {
		return array_map(
			fn( $block ) => self::block_has_class( $block, $class_name ) ? $new_block : $block,
			parse_blocks( $content )
		);
	}

	/**
	 * Appends the given block to ALL blocks with the given class.
	 *
	 * @param string $class_name class on block to append to.
	 * @param string $content content string.
	 * @param array  $new_block block to append.
	 *
	 * @return array blocks.
	 */
	public static function prepend_block_to_blocks_with_class( string $class_name, string $content, array $new_block ): array {
		$blocks            = parse_blocks( $content );
		$blocks_with_class = self::find_blocks_with_class( $class_name, $blocks );
		if ( empty( $blocks_with_class ) ) {
			return $blocks;
		}

		foreach ( $blocks_with_class as $idx => $block ) {
			array_splice( $blocks, $idx, 0, [ $new_block ] );
		}

		return $blocks;
	}

	/**
	 * Find all blocks with the given class in the given array of blocks.
	 *
	 * @param string $class_name class on blocks to find.
	 * @param array  $blocks blocks to search through.
	 *
	 * @return array blocks.
	 */
	public static function find_blocks_with_class( string $class_name, array $blocks ): array {
		return array_filter(
			$blocks,
			fn( $block ) => self::block_has_class( $block, $class_name )
		);
	}

	/**
	 * Find all blocks without the given class in an array of blocks.
	 *
	 * @param string $class_name class on blocks to leave behind.
	 * @param array  $blocks blocks to search through.
	 *
	 * @return array blocks.
	 */
	public static function find_blocks_without_class( string $class_name, array $blocks ): array {
		return array_filter(
			$blocks,
			fn( $block ) => ! self::block_has_class( $block, $class_name )
		);
	}

	/**
	 * Checks if a block has the given class.
	 *
	 * @param array  $block block to check.
	 * @param string $class_name class to check for.
	 *
	 * @return bool true if block has class, false otherwise.
	 */
	public static function block_has_class( array $block, string $class_name ): bool {
		return ! empty( $block['attrs']['className'] ) && preg_match( "/\b$class_name\b/", $block['attrs']['className'] );
	}
}
