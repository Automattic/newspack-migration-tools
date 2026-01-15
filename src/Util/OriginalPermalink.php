<?php
/**
 * Helper to save original permalink paths for posts and terms.
 *
 * It's a thin wrapper for the OriginalValueStore class, that ensures path normalization.
 *
 * An original permalink is the original path of a post or term from the source site.
 * For example /news/some-article-title or /category/some-category.
 *
 * This class handles path normalization when saving. To retrieve permalinks,
 * use OriginalValueStore::get_for_post() or OriginalValueStore::get_for_term()
 * with the key 'permalink'.
 *
 * @see docs/source-permalinks.md Full documentation with usage examples
 *
 * @package Newspack\MigrationTools
 */

namespace Newspack\MigrationTools\Util;

use Newspack\MigrationTools\Logic\OriginalValueStore;

class OriginalPermalink {

	/**
	 * The key used for storing permalinks with the OriginalValueStore.
	 *
	 * @var string
	 */
	const string KEY = 'permalink';

	/**
	 * Saves a post source permalink.
	 *
	 * @param int    $post_id          Post ID.
	 * @param string $source_permalink Source permalink URL or path.
	 *
	 * @return string The saved path – eg. /some/path/here.
	 */
	public static function save_for_post( int $post_id, string $source_permalink ): string {
		$path = self::ensure_path_format( $source_permalink );
		OriginalValueStore::save_for_post( $post_id, self::KEY, $path );

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
		return OriginalValueStore::get_for_post( $post_id, self::KEY );
	}

	/**
	 * Saves a term source permalink.
	 *
	 * @param int    $term_id          Term ID.
	 * @param string $source_permalink Source permalink URL or path.
	 *
	 * @return string The saved path – eg. /some/path/here.
	 */
	public static function save_for_term( int $term_id, string $source_permalink ): string {
		$path = self::ensure_path_format( $source_permalink );
		OriginalValueStore::save_for_term( $term_id, self::KEY, $path );

		return $path;
	}


	/**
	 * Ensures that a url or path is in path format, starting with a single leading slash.
	 *
	 * @param string $url_or_path The url or path to convert.
	 *
	 * @return string The path formatted string – eg. /some/path/here.
	 */
	public static function ensure_path_format( string $url_or_path ): string {
		// I've seen sites where /health is a category, but /health/ is a tag.
		// also /about might be the about page, but /about/ could be a folder.
		// possibly don't remove the trailing slash....up to you.
		return untrailingslashit( '/' . ltrim( wp_make_link_relative( $url_or_path ), '/' ) );
	}
}
