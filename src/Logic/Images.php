<?php
/**
 * Replaces strings in <img> elements in Posts.
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Logic;

/**
 * Image logic class.
 */
class Images {

	/**
	 * Does str_replace() in all the <img> elements in a Post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $search  Search string.
	 * @param string $replace Replace string.
	 *
	 * @return int|false Output from $wpdb->update -- the number of rows updated, or false on error.
	 */
	public function str_replace_in_img_elements_in_post( int $post_id, string $search, string $replace ) {
		global $wpdb;

		// Get post_content.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_content         = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );
		$post_content_updated = $post_content;

		// Match all <img> elements.
		preg_match_all( '/<img[^>]+>/i', $post_content, $img_matches );
		if ( ! isset( $img_matches[0] ) || empty( $img_matches[0] ) ) {
			// 0 is for no changed rows, false is for error.
			return 0;
		}

		// Run str_replace() on each <img> element.
		foreach ( $img_matches[0] as $img ) {
			$img_updated = str_replace( $search, $replace, $img );

			// If the <img> was updated, replace it in the post_content.
			if ( $img_updated != $img ) {
				$post_content_updated = str_replace( $img, $img_updated, $post_content_updated );
			}
		}

		// Update post_content.
		$updated = 0;
		if ( $post_content_updated != $post_content ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$updated = $wpdb->update( $wpdb->posts, [ 'post_content' => $post_content_updated ], [ 'ID' => $post_id ] );
		}

		return $updated;
	}

}
