<?php

namespace Newspack\MigrationTools\Logic;

use Exception;
use Newspack\MigrationTools\Traits\HasUniqueIdentifier;
use Newspack\Optional_Modules\Collections;
use WP_Error;
use WP_Term;

/**
 * Collections Helper class.
 */
class CollectionsHelper {
	use HasUniqueIdentifier;

	public const UNIQUE_COLLECTION_IDENTIFIER_META_KEY          = '_nmt_collection_uniqid';
	public const UNIQUE_COLLECTION_SECTION_IDENTIFIER_META_KEY  = '_nmt_collection_section_uniqid';
	public const UNIQUE_COLLECTION_CATEGORY_IDENTIFIER_META_KEY = '_nmt_collection_category_uniqid';

	/**
	 * Collection metadata map.
	 */
	const COLLECTION_META_MAP = [
		'thumbnail_id'   => '_thumbnail_id',
		'volume'         => 'newspack_collection_volume',
		'number'         => 'newspack_collection_number',
		'period'         => 'newspack_collection_period',
		'subscribe_link' => 'newspack_collection_subscribe_link',
		'order_link'     => 'newspack_collection_order_link',
		'ctas'           => 'newspack_collection_ctas',
	];

	/**
	 * Collection Section metadata map.
	 */
	const COLLECTION_SECTION_META_MAP = [
		'order' => 'newspack_collection_section_order',
	];

	/**
	 * Collection Category metadata map.
	 */
	const COLLECTION_CATEGORY_META_MAP = [
		'subscribe_link' => 'newspack_collection_subscribe_link',
		'order_link'     => 'newspack_collection_order_link',
	];

	/**
	 * Constructor.
	 * 
	 * @throws Exception If the Newspack Plugin is not installed or active, or if the Collections module is not enabled.
	 */
	public function __construct() {
		if ( ! is_plugin_active( 'newspack-plugin/newspack.php' ) ) {
			throw new Exception( 'Newspack Plugin is not installed or active.' );
		}

		if ( ! class_exists( Collections::class ) || ! Collections::is_feature_enabled() ) {
			throw new Exception( 'Collections module is not enabled.' );
		}
	}

	/**
	 * Get or create a Collection by its unique identifier.
	 *
	 * @param array  $data              The data to create the collection with.
	 * @param string $unique_identifier The unique identifier for the collection.
	 * @return int|WP_Error The collection ID if created or found, or a WP_Error if the collection cannot be created.
	 */
	public function get_or_create_collection( array $data, string $unique_identifier ): int|WP_Error {
		$this->ensure_valid_unique_identifier( $unique_identifier );

		$wp_post_id = $this->get_post_id_by_unique_identifier(
			$unique_identifier,
			self::UNIQUE_COLLECTION_IDENTIFIER_META_KEY,
			$this->get_collection_post_type()
		);

		if ( $wp_post_id ) {
			return $wp_post_id;
		}

		// Unfortunately, collection titles can not contain commas because of a known core issue, re: https://core.trac.wordpress.org/ticket/14691.
		if ( str_contains( $data['post_title'], ',' ) ) {
			return new WP_Error( 'invalid_collection_title', 'Collection title cannot contain a comma.' );
		}

		$data['post_type'] = $this->get_collection_post_type();

		$wp_post_id = wp_insert_post( $data );

		if ( is_wp_error( $wp_post_id ) ) {
			return $wp_post_id;
		}

		update_post_meta( $wp_post_id, self::UNIQUE_COLLECTION_IDENTIFIER_META_KEY, $unique_identifier );

		return $wp_post_id;
	}

	/**
	 * Updates the collection metadata.
	 *
	 * @param int   $collection_id     The collection id.
	 * @param array $data              The metadata to update.
	 * @return void
	 */
	public function update_collection_metadata( int $collection_id, array $data ): void {
		foreach ( $data as $key => $value ) {
			if ( isset( self::COLLECTION_META_MAP[ $key ] ) ) {
				update_post_meta( $collection_id, self::COLLECTION_META_MAP[ $key ], $value );
			}
		}
	}
	
	/**
	 * Get or create a Collection Section by its unique identifier.
	 *
	 * @param array  $data              The data to create the collection section with.
	 * @param string $unique_identifier The unique identifier for the collection section.
	 * @return WP_Term|WP_Error The collection Section WP_Term if created or found. WP_Error, otherwise.
	 */
	public function get_or_create_collection_section( array $data, string $unique_identifier ): WP_Term|WP_Error {
		$this->ensure_valid_unique_identifier( $unique_identifier );

		$term_name        = $data['name'];
		$term_description = $data['description'] ?? '';

		$wp_term = $this->get_taxonomy_term_by_unique_identifier(
			$unique_identifier,
			self::UNIQUE_COLLECTION_SECTION_IDENTIFIER_META_KEY,
			$this->get_collection_section_taxonomy()
		);

		if ( $wp_term instanceof WP_Term ) {
			return $wp_term;
		}

		$collection_section = wp_insert_term(
			$term_name,
			$this->get_collection_section_taxonomy(),
			[
				'description' => $term_description,
			]
		);

		if ( is_wp_error( $collection_section ) ) {
			return $collection_section;
		}

		update_term_meta( $collection_section['term_id'], self::UNIQUE_COLLECTION_SECTION_IDENTIFIER_META_KEY, $unique_identifier );

		return get_term_by( 'term_id', $collection_section['term_id'], $this->get_collection_section_taxonomy() );
	}

	/**
	 * Updates the collection section metadata.
	 *
	 * @param int   $collection_section_id     The collection section id.
	 * @param array $data              The metadata to update.
	 * @return void
	 */
	public function update_collection_section_metadata( int $collection_section_id, array $data ): void {
		foreach ( $data as $key => $value ) {
			if ( isset( self::COLLECTION_SECTION_META_MAP[ $key ] ) ) {
				update_term_meta( $collection_section_id, self::COLLECTION_SECTION_META_MAP[ $key ], $value );
			}
		}
	}

	/**
	 * Get or create a Collection Category by its unique identifier.
	 *
	 * @param array  $data              The data to create the collection category with.
	 * @param string $unique_identifier The unique identifier for the collection category.
	 * @return WP_Term|WP_Error The collection category WP_Term if created or found. WP_Error, otherwise.
	 */
	public function get_or_create_collection_category( array $data, string $unique_identifier ): WP_Term|WP_Error {
		$this->ensure_valid_unique_identifier( $unique_identifier );

		$term_name        = $data['name'];
		$term_description = $data['description'] ?? '';

		$wp_term = $this->get_taxonomy_term_by_unique_identifier(
			$unique_identifier,
			self::UNIQUE_COLLECTION_CATEGORY_IDENTIFIER_META_KEY,
			$this->get_collection_category_taxonomy()
		);

		if ( $wp_term instanceof WP_Term ) {
			return $wp_term;
		}

		$collection_category = wp_insert_term(
			$term_name,
			$this->get_collection_category_taxonomy(),
			[
				'description' => $term_description,
			]
		);

		if ( is_wp_error( $collection_category ) ) {
			return $collection_category;
		}

		update_term_meta( $collection_category['term_id'], self::UNIQUE_COLLECTION_CATEGORY_IDENTIFIER_META_KEY, $unique_identifier );

		return get_term_by( 'term_id', $collection_category['term_id'], $this->get_collection_category_taxonomy() );
	}

	/**
	 * Updates the collection category metadata.
	 *
	 * @param int   $collection_category_id     The collection category id.
	 * @param array $data              The metadata to update.
	 * @return void
	 */
	public function update_collection_category_metadata( int $collection_category_id, array $data ): void {
		foreach ( $data as $key => $value ) {
			if ( isset( self::COLLECTION_CATEGORY_META_MAP[ $key ] ) ) {
				update_term_meta( $collection_category_id, self::COLLECTION_CATEGORY_META_MAP[ $key ], $value );
			}
		}
	}

	/**
	 * Assign Post to Collections Post Type.
	 * 
	 * @param int       $post_id The post ID to assign to collections.
	 * @param int|array $collection_posts_ids The collection post ID or an array of collection posts IDs to assign.
	 * @return void
	 */
	public function assign_post_to_collections_posts( int $post_id, int|array $collection_posts_ids ): void {
		if ( ! is_array( $collection_posts_ids ) ) {
			$collection_posts_ids = [ $collection_posts_ids ];
		}

		$collection_terms_ids = array_map(
			function ( $collection_post_id ) {
				return $this->get_collection_linked_term_id( $collection_post_id );
			},
			$collection_posts_ids
		);

		$this->assign_post_to_collections_terms( $post_id, $collection_terms_ids );
	}

	/**
	 * Assign Post to Collections Taxonomy.
	 * 
	 * @param int       $post_id The post ID to assign to collections.
	 * @param int|array $collection_terms_ids The collection term ID or an array of collection term IDs to assign.
	 * @return void
	 */
	public function assign_post_to_collections_terms( int $post_id, int|array $collection_terms_ids ): void {
		if ( ! is_array( $collection_terms_ids ) ) {
			$collection_terms_ids = [ $collection_terms_ids ];
		}

		$collection_terms_ids = array_map( 'intval', $collection_terms_ids );

		wp_set_post_terms( $post_id, $collection_terms_ids, $this->get_collection_taxonomy(), true );
	}

	/**
	 * Assign Post to Collection Sections.
	 * 
	 * @param int       $post_id The post ID to assign to collections.
	 * @param int|array $collection_sections_terms_ids The collection sections term ID or an array of collection sections term IDs to assign.
	 * @return void
	 */
	public function assign_post_to_collection_sections( int $post_id, int|array $collection_sections_terms_ids ): void {
		if ( ! is_array( $collection_sections_terms_ids ) ) {
			$collection_sections_terms_ids = [ $collection_sections_terms_ids ];
		}

		$collection_sections_terms_ids = array_map( 'intval', $collection_sections_terms_ids );

		wp_set_post_terms( $post_id, $collection_sections_terms_ids, $this->get_collection_section_taxonomy(), true );
	}

	/**
	 * Assign Post to Collection Categories.
	 * 
	 * @param int       $post_id The post ID to assign to collections.
	 * @param int|array $collection_categories_terms_ids The collection categories term ID or an array of collection categories term IDs to assign.
	 * @return void
	 */
	public function assign_post_to_collection_categories( int $post_id, int|array $collection_categories_terms_ids ): void {
		if ( ! is_array( $collection_categories_terms_ids ) ) {
			$collection_categories_terms_ids = [ $collection_categories_terms_ids ];
		}

		$collection_categories_terms_ids = array_map( 'intval', $collection_categories_terms_ids );

		wp_set_post_terms( $post_id, $collection_categories_terms_ids, $this->get_collection_category_taxonomy(), true );
	}

	/**
	 * Get the linked Collection term ID for Collection post ID.
	 * 
	 * @param int $post_id The post ID to get the collection term for.
	 * @return int
	 */
	public function get_collection_linked_term_id( int $post_id ): int {
		return (int) get_post_meta( $post_id, \Newspack\Collections\Sync::LINKED_TERM_META_KEY, true );
	} 

	/**
	 * Get the linked Collection post ID for Collection term ID.
	 * 
	 * @param int $term_id The term ID to get the collection term for.
	 * @return int
	 */
	public function get_collection_linked_post_id( int $term_id ): int {
		return (int) get_term_meta( $term_id, \Newspack\Collections\Sync::LINKED_POST_META_KEY, true );
	} 

	/**
	 * Shorthand to get the collection post type.
	 * 
	 * @return string The post type for collections.
	 */
	public function get_collection_post_type(): string {
		return \Newspack\Collections\Post_Type::get_post_type();
	}

	/**
	 * Shorthand to get the collection taxonomy.
	 * 
	 * @return string The taxonomy for collections.
	 */
	public function get_collection_taxonomy(): string {
		return \Newspack\Collections\Collection_Taxonomy::get_taxonomy();
	}

	/**
	 * Shorthand to get the collection section taxonomy.
	 * 
	 * @return string The taxonomy name for the collection section.
	 */
	public function get_collection_section_taxonomy(): string {
		return \Newspack\Collections\Collection_Section_Taxonomy::get_taxonomy();
	}

	/**
	 * Shorthand to get the collection category taxonomy.
	 * 
	 * @return string The taxonomy name for the collection category.
	 */
	public function get_collection_category_taxonomy(): string {
		return \Newspack\Collections\Collection_Category_Taxonomy::get_taxonomy();
	}
}
