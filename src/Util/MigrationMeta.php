<?php

namespace Newspack\MigrationTools\Util;

use InvalidArgumentException;

/**
 * Helper class to help managing metadata for migrations.
 *
 * It uses a single meta key stored as an array. Please don't use this class to
 * store large amounts of data, but rather stuff like migration status or
 * version for a command for a post. E.g.: 'update_authors' => 'v1'.
 */
class MigrationMeta {

	/**
	 * The meta key used to store the migration meta.
	 */
	public const META_KEY = 'newspack_migration_meta';

	/**
	 * Get the value for a given key from the metadata array.
	 *
	 * @param int|string $object_id post, user or term id.
	 * @param string     $key       The key in the array in the metadata row.
	 * @param string     $type      One of post, term, or user.
	 *
	 * @return mixed|null The meta value, or null if not found.
	 */
	public static function get( int|string $object_id, string $key, string $type ): mixed {
		self::validate_type( $type );

		$meta = call_user_func( "get_{$type}_meta", $object_id, self::META_KEY, true );

		return $meta[ $key ] ?? null;
	}

	/**
	 * Update a value for a given key in the metadata array.
	 *
	 * @param int|string $object_id post, user or term id.
	 * @param string     $key       The key in the array in the metadata row.
	 * @param string     $type      One of post, term, or user.
	 * @param mixed      $value     Value to set.
	 *
	 * @return void
	 */
	public static function update( int|string $object_id, string $key, string $type, mixed $value ): void {
		self::validate_type( $type );

		$full_meta = call_user_func( "get_{$type}_meta", $object_id, self::META_KEY, true );
		if ( ! is_array( $full_meta ) ) {
			$full_meta = [];
		}
		$full_meta[ $key ] = $value;

		call_user_func( "update_{$type}_meta", $object_id, self::META_KEY, $full_meta );
	}


	/**
	 * Delete a value for a given key in the metadata array.
	 *
	 * @param int|string $object_id post, user or term id.
	 * @param string     $key       The key in the array in the metadata row.
	 * @param string     $type      One of post, term, or user.
	 *
	 * @return void
	 */
	public static function delete( int|string $object_id, string $key, string $type ): void {
		self::validate_type( $type );

		$full_meta = call_user_func( "get_{$type}_meta", $object_id, self::META_KEY, true );
		if ( is_array( $full_meta ) ) {
			unset( $full_meta[ $key ] );
		}
		if ( empty( $full_meta ) ) {
			call_user_func( "delete_{$type}_meta", $object_id, self::META_KEY );

			return;
		}

		call_user_func( "update_{$type}_meta", $object_id, self::META_KEY, $full_meta );
	}

	/**
	 * Check that type is one of post, term, or user.
	 *
	 * @param string $type Object type.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException If the type is not valid.
	 */
	private static function validate_type( string $type ): void {
		if ( ! in_array( $type, [ 'post', 'term', 'user' ] ) ) {
			throw new InvalidArgumentException( sprintf( 'Invalid type %s', esc_html( $type ) ) );
		}
	}
}
