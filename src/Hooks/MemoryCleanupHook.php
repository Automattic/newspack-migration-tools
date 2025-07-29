<?php

namespace Newspack\MigrationTools\Hooks;

class MemoryCleanupHook {
	/**
	 * Cleanup memory.
	 * 
	 * @static
	 * @access public
	 * 
	 * @param int $sleep_time     Number of seconds to sleep between each flush.
	 * @param int $current_step   Current counter/step. If provided $current_step and $flush_interval, will only flush every $flush_interval steps.
	 * @param int $flush_interval Number of steps to wait before flushing again.
	 */
	public static function cleanup( int $sleep_time = 0, int $current_step = null, int $flush_interval = null ): void {

		// Determine if we should perform cleanup based on whether interval and step are provided.
		$should_cleanup = ( is_null( $current_step ) || is_null( $flush_interval ) )
			? true
			: ( 0 === ( $current_step % $flush_interval ) );

		if ( $should_cleanup ) {
			self::reset_local_object_cache();
			self::reset_db_query_log();

			if ( $sleep_time > 0 ) {
				sleep( $sleep_time );
			}
		}
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
				// Only set if the property is actually declared (not a magic property).
				$reflection = new \ReflectionObject( $wp_object_cache );
				if ( $reflection->hasProperty( $property ) ) {
					$wp_object_cache->$property = [];
				}
			}
		}

		gc_collect_cycles();

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

		unset( $wpdb->queries );

		gc_collect_cycles();

		$wpdb->queries = [];
	}
}
