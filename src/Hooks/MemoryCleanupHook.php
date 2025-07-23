<?php

namespace Newspack\MigrationTools\Hooks;

class MemoryCleanupHook {
	/**
	 * Cleanup memory.
	 * 
	 * @static
	 * @access public
	 * 
	 * @param int $sleep_time
	 */
	public static function cleanup( int $sleep_time = 3 ): void {
		self::reset_local_object_cache();
		self::reset_db_query_log();

		sleep( $sleep_time );
	}

	/**
	 * Reset the local WordPress object cache
	 *
	 * This only cleans the local cache in WP_Object_Cache, without
	 * affecting memcache.
	 * 
	 * @static
	 * @access public
	 */
	public static function reset_local_object_cache() {
		global $wp_object_cache;

		if ( ! is_object( $wp_object_cache ) ) {
			return;
		}

		$properties = [
			'group_ops',
			'memcache_debug',
			'cache',
		];

		foreach ( $properties as $property ) {
			if ( property_exists( $wp_object_cache, $property ) ) {
				$wp_object_cache->$property = [];
			}
		}

		if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
			$wp_object_cache->__remoteset(); // important
		}
	}

	/**
	 * Resets the WordPress DB query log.
	 * 
	 * @static
	 * @access public
	 * 
	 * @return void
	 */
	public static function reset_db_query_log(): void {
		global $wpdb;

		$wpdb->queries = [];
	}
}
