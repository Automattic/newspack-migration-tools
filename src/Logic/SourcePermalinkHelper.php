<?php
/**
 * Helper to save and retrieve source permalink paths for posts and terms.
 *
 * A source permalink is the original path of a post or term from the source site.
 * For example /news/some-article-title or /category/some-category.
 *
 * @see docs/source-permalinks.md Full documentation with usage examples
 *
 * @package Newspack\MigrationTools
 */

namespace Newspack\MigrationTools\Logic;

class SourcePermalinkHelper {

	/**
	 * Meta key for post source permalink paths.
	 *
	 * @var string
	 */
	const string POSTS_META_KEY = '_newspack_post_source_permalink';

	/**
	 * Meta key for term source permalink paths.
	 *
	 * @var string
	 */
	const string TERMS_META_KEY = '_newspack_term_source_permalink';

	/**
	 * Saves a path or url.
	 *
	 * @param int    $post_id          Post ID.
	 * @param string $source_permalink Source permalink URL or path.
	 *
	 * @return string The saved path – eg. /some/path/here.
	 */
	public static function save_post_source_permalink( int $post_id, string $source_permalink ): string {
		$path = self::ensure_path_format( $source_permalink );
		update_post_meta( $post_id, self::POSTS_META_KEY, $path );

		return $path;
	}

	/**
	 * Gets the saved source permalink (path) for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return string The saved path – eg. /some/path/here.
	 */
	public static function get_post_source_permalink( int $post_id ): string {
		return get_post_meta( $post_id, self::POSTS_META_KEY, true );
	}

	/**
	 * Saves a term source permalink.
	 *
	 * @param int    $term_id          Term ID.
	 * @param string $source_permalink Source permalink URL or path.
	 *
	 * @return string The saved path – eg. /some/path/here.
	 */
	public static function save_term_source_permalink( int $term_id, string $source_permalink ): string {
		$path = self::ensure_path_format( $source_permalink );
		update_term_meta( $term_id, self::TERMS_META_KEY, $path );

		return $path;
	}

	/**
	 * Get the saved source permalink (path) for a term.
	 *
	 * @param int $term_id Term ID.
	 *
	 * @return string The saved path – eg. /some/path/here.
	 */
	public static function get_term_source_permalink( int $term_id ): string {
		return get_term_meta( $term_id, self::TERMS_META_KEY, true );
	}

	/**
	 * Ensures that a url or path is in path format, starting with a single leading slash.
	 *
	 * @param string $url_or_path The url or path to convert.
	 *
	 * @return string The path formatted string – eg. /some/path/here.
	 */
	public static function ensure_path_format( string $url_or_path ): string {
		return '/' . ltrim( wp_make_link_relative( $url_or_path ), '/' );
	}
}
