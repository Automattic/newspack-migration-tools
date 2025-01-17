<?php

namespace Newspack\MigrationTools\Logic;

class DrupalHelper {

	/**
	 * This is just an empty array constant with a name that is a bit more readable than [].
	 *
	 * Use it to return from implementation of the 'fgd2wp_pre_insert_post' filter to skip importing a post.
	 *
	 * @var array
	 */
	const SKIP_IMPORTING_POST = [];

	public static function get_nid_from_post_id( int $post_id ): ?int {
		return get_post_meta( $post_id, '_fgd2wp_old_node_id', true ) ?? 0;
	}

	/**
	 * Get the prefix for the drupal tables in the database.
	 *
	 * If the constant is not set, then we default to 'drupal_'.
	 *
	 * @return string
	 */
	public static function get_tables_prefix(): string {
		return defined( 'NMT_DRUPAL_PREFIX' ) ? NMT_DRUPAL_PREFIX : 'drupal_';
	}
}