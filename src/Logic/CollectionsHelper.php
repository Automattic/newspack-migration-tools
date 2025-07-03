<?php

namespace Newspack\MigrationTools\Logic;

use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use WP_Post;
use WP_Error;

/**
 * Collections Helper class.
 */
class CollectionsHelper {
	/**
	 * Meta key for the unique identifier for collections.
	 */
	public const UNIQUE_COLLECTION_IDENTIFIER_META_KEY = '_nmt_collection_uniqid';

    /**
     * Collection post type.
     */
    public const COLLECTION_POST_TYPE = 'newspack_collection';

    /**
     * Collection metadata map.
     */
    const COLLECTION_META_MAP = [
        'thumbnail'      => '_thumbnail_id',
        'volume'         => 'newspack_collection_volume',
        'number'         => 'newspack_collection_number',
        'period'         => 'newspack_collection_period',
        'subscribe_link' => 'newspack_collection_subscribe_link',
        'order_link'     => 'newspack_collection_order_link',
        'ctas'           => 'newspack_collection_ctas',
    ];

	/**
	 * Create or get a collection.
	 *
	 * @param array  $data              The data to create the collection with.
	 * @param string $unique_identifier The unique identifier for the collection.
	 *
	 * @return int|WP_Error The collection ID if created or found, or a WP_Error if the collection cannot be created.
	 */
	public static function create_or_get_collection( array $data, string $unique_identifier ): int|WP_Error {
		// Validate that the unique identifier is not empty.
		if ( empty( $unique_identifier ) ) {
			return new WP_Error( 'empty_unique_identifier', __( 'The unique identifier cannot be empty.', 'newspack-migration-tools' ) );
		}

		// First try with the uniqid for the collection.
		$wp_post_id = self::get_collection_by_unique_identifier( $unique_identifier );
		if ( $wp_post_id ) { // Great – we already have the collection!
			return $wp_post_id;
		}

		// If it doesn't exist, then create it.
		$wp_post_id = wp_insert_post( $data );
		if ( is_wp_error( $wp_post_id ) ) {
			return $wp_post_id;
		}

		// Set the unique identifier for the collection.
		update_post_meta( $wp_post_id, self::UNIQUE_COLLECTION_IDENTIFIER_META_KEY, $unique_identifier );

		return $wp_post_id;
	}

    /**
	 * Updates the collection metadata.
	 *
	 * @param int    $collection_id     The collection id.
	 * @param array  $data              The metadata to update.
	 *
	 * @return void
	 */
    public static function update_data( int $collection_id, array $data ): void {
        foreach ( $data as $key => $value ) {
            if ( isset( self::COLLECTION_META_MAP[ $key ] ) ) {
                update_post_meta( $collection_id, self::COLLECTION_META_MAP[ $key ], $value );
            }
        }
    }

	/**
	 * Get a collection by its unique identifier.
	 *
	 * The identifier was set when the collection was created (if it was created by this class), so you probably know what it is.
	 *
	 * @param string $unique_identifier The unique identifier to search for.
	 *
	 * @return int|false The collection ID if found, false otherwise.
	 */
	public static function get_collection_by_unique_identifier( string $unique_identifier ): int|false {
		if ( empty( $unique_identifier ) ) {
			FileLog::get_logger( __CLASS__ )->error(
				'Value is empty. Refusing to find a collection with empty values.',
				[
					'unique_identifier' => $unique_identifier,
				]
			);

			return false;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				self::UNIQUE_COLLECTION_IDENTIFIER_META_KEY,
				$unique_identifier
			)
		);

		return empty( $post_id ) ? false : (int) $post_id;
	}
}
