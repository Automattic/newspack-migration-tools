<?php

namespace Newspack\MigrationTools\Scaffold\WordPressData;

use CoAuthors_Plus;
use DateTimeInterface;
use Exception;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationObject;
use Newspack\MigrationTools\Scaffold\MigrationObjectPropertyWrapper;
use Newspack\MigrationTools\Scaffold\Singletons\WordPressData;
use WP_Error;
use WP_Term;
use WP_User;

/**
 * Class WordPressPostsData.
 *
 * @property int $ID
 * @property string $post_title
 * @property string $post_content
 * @property string $post_excerpt
 * @property int $post_author
 * @property string $post_date
 * @property string $post_date_gmt
 * @property string $post_modified
 * @property string $post_modified_gmt
 * @property string $post_status
 * @property string $post_type
 * @property string $post_mime_type
 * @property int $post_parent
 * @property string $guid
 * @property int $menu_order
 * @property string $comment_status
 * @property string $ping_status
 * @property string $post_password
 * @property string $post_name
 * @property string $to_ping
 * @property string $pinged
 * @property string $post_content_filtered
 * @property int $comment_count
 */
class WordPressPostsData extends AbstractWordPressData {

	const VALID_USER_CACHE_KEY = 'list_of_valid_user_ids';
	const CACHE_GROUP          = 'migration_scaffold';

	/**
	 * The CoAuthors Plus instance.
	 *
	 * @var CoAuthors_Plus $co_authors_plus The CoAuthors Plus instance.
	 */
	protected CoAuthors_Plus $co_authors_plus;

	/**
	 * The authors to set for a particular post.
	 *
	 * @var int[]|WP_User[]|MigrationObjectPropertyWrapper[] $authors The authors of a particular post.
	 */
	protected array $authors = [];

	/**
	 * The categories to set for a particular post.
	 *
	 * @var int[]|WP_Term[]|MigrationObjectPropertyWrapper[] $categories The categories of a particular post.
	 */
	protected array $categories = [];

	/**
	 * WordPressPostsData constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->primary_key     = 'ID';
		$this->co_authors_plus = new CoAuthors_Plus();

		if ( ! wp_cache_get( self::VALID_USER_CACHE_KEY, self::CACHE_GROUP ) ) {
			wp_cache_set( self::VALID_USER_CACHE_KEY, [], self::CACHE_GROUP, DAY_IN_SECONDS );
		}
	}

	/**
	 * Gets the post ID from the legacy ID.
	 *
	 * @param int|string $legacy_id The legacy ID.
	 *
	 * @return int|null
	 * @throws Exception If more than one Post ID exists for the given Legacy ID.
	 */
	public function get_post_id_from_legacy_id( int|string $legacy_id = '' ): ?int {
		if ( empty( $legacy_id ) ) { // Allow for ID retrieval if you don't have the Legacy ID in hand.
			if ( $this->get_migration_object() ) { // The Migration Object should have it.
				return $this->get_wordpress_object_id_from_migration_object();
			} else {
				// If you don't have a Legacy ID, and you don't have a Migration Object, you won't get any further.
				return null;
			}
		}

		return $this->get_wordpress_object_id_from_legacy_id( $legacy_id );
	}

	/**
	 * Returns the table name.
	 *
	 * @return string The table name.
	 */
	public function get_table_name(): string {
		if ( ! isset( $this->table_name ) ) {
			$this->table_name = $this->wpdb->posts;
		}

		return parent::get_table_name();
	}

	/**
	 * Sets the ID property.
	 *
	 * @param int|MigrationObjectPropertyWrapper $id The ID.
	 *
	 * @return WordPressPostsData
	 */
	public function set_id( int|MigrationObjectPropertyWrapper $id ): WordPressPostsData {
		$this->set_property( 'ID', $id );

		return $this;
	}

	/**
	 * Sets the post_title property.
	 *
	 * @param string|MigrationObjectPropertyWrapper $post_title The post title.
	 *
	 * @return WordPressPostsData
	 */
	public function set_post_title( string|MigrationObjectPropertyWrapper $post_title ): WordPressPostsData {
		$this->set_property( 'post_title', $post_title );

		return $this;
	}

	/**
	 * Sets the post_content property.
	 *
	 * @param string|MigrationObjectPropertyWrapper $post_content The post content.
	 *
	 * @return WordPressPostsData
	 */
	public function set_post_content( string|MigrationObjectPropertyWrapper $post_content ): WordPressPostsData {
		$this->set_property( 'post_content', $post_content );

		return $this;
	}

	/**
	 * Sets the post_excerpt property.
	 *
	 * @param string|MigrationObjectPropertyWrapper $post_excerpt The post excerpt.
	 *
	 * @return WordPressPostsData
	 */
	public function set_post_excerpt( string|MigrationObjectPropertyWrapper $post_excerpt ): WordPressPostsData {
		$this->set_property( 'post_excerpt', $post_excerpt );

		return $this;
	}

	/**
	 * Sets the post_author property.
	 *
	 * @param int|WP_User|MigrationObjectPropertyWrapper $post_author The post author.
	 *
	 * @return WordPressPostsData
	 * @throws Exception If a valid WP_User cannot be obtained.
	 */
	public function set_post_author( int|WP_User|MigrationObjectPropertyWrapper $post_author ): WordPressPostsData {
		if ( $post_author instanceof WP_User ) {
			$post_author = $post_author->ID;
		}

		if ( $post_author instanceof MigrationObjectPropertyWrapper ) {
			$post_author = $this->validate_property_is_valid_user( $post_author );
		}

		$this->set_property( 'post_author', $post_author );

		$this->maintain_authors_array( $post_author );

		return $this;
	}

	/**
	 * Sets the post_date property.
	 *
	 * @param string|MigrationObjectPropertyWrapper|DateTimeInterface $post_date The post date.
	 *
	 * @return WordPressPostsData
	 * @throws Exception If the date string is malformed.
	 */
	public function set_post_date( string|MigrationObjectPropertyWrapper|DateTimeInterface $post_date ): WordPressPostsData {
		$this->set_date_property( $post_date, 'post_date' );

		if ( ! $this->is_property_set( 'post_date_gmt' ) ) {
			$this->set_post_date_gmt( $post_date );
		}

		return $this;
	}

	/**
	 * Sets the post_date_gmt property.
	 *
	 * @param string|MigrationObjectPropertyWrapper|DateTimeInterface $post_date The post date.
	 *
	 * @return WordPressPostsData
	 * @throws Exception If the date string is malformed.
	 */
	public function set_post_date_gmt( string|MigrationObjectPropertyWrapper|DateTimeInterface $post_date ): WordPressPostsData {
		$this->set_gmt_date_property( $post_date, 'post_date_gmt' );

		return $this;
	}

	/**
	 * Sets the post_modified property.
	 *
	 * @param string|MigrationObjectPropertyWrapper|DateTimeInterface $post_date The post date.
	 *
	 * @return WordPressPostsData
	 * @throws Exception If the date string is malformed.
	 */
	public function set_post_modified( string|MigrationObjectPropertyWrapper|DateTimeInterface $post_date ): WordPressPostsData {
		$this->set_date_property( $post_date, 'post_modified' );

		if ( ! $this->is_property_set( 'post_modified_gmt' ) ) {
			$this->set_post_modified_gmt( $post_date );
		}

		return $this;
	}

	/**
	 * Sets the post_modified_gmt property.
	 *
	 * @param string|MigrationObjectPropertyWrapper|DateTimeInterface $post_date The post date.
	 *
	 * @return WordPressPostsData
	 * @throws Exception If the date string is malformed.
	 */
	public function set_post_modified_gmt( string|MigrationObjectPropertyWrapper|DateTimeInterface $post_date ): WordPressPostsData {
		$this->set_gmt_date_property( $post_date, 'post_modified_gmt' );

		return $this;
	}

	/**
	 * Sets the post_status property.
	 *
	 * @param string|MigrationObjectPropertyWrapper $post_status The post status.
	 *
	 * @return WordPressPostsData
	 */
	public function set_post_status( string|MigrationObjectPropertyWrapper $post_status ): WordPressPostsData {
		$this->set_property( 'post_status', $post_status );

		return $this;
	}

	/**
	 * Sets the post_type property.
	 *
	 * @param string|MigrationObjectPropertyWrapper $post_type The post type.
	 *
	 * @return WordPressPostsData
	 */
	public function set_post_type( string|MigrationObjectPropertyWrapper $post_type ): WordPressPostsData {
		$this->set_property( 'post_type', $post_type );

		return $this;
	}

	/**
	 * Sets the post_mime_type property.
	 *
	 * @param string|MigrationObjectPropertyWrapper $post_mime_type The post mime type.
	 *
	 * @return WordPressPostsData
	 */
	public function set_post_mime_type( string|MigrationObjectPropertyWrapper $post_mime_type ): WordPressPostsData {
		$this->set_property( 'post_mime_type', $post_mime_type );

		return $this;
	}

	/**
	 * Sets the post_parent property.
	 *
	 * @param int|string|MigrationObjectPropertyWrapper $post_parent The post parent.
	 *
	 * @return WordPressPostsData
	 */
	public function set_post_parent( int|string|MigrationObjectPropertyWrapper $post_parent ): WordPressPostsData {
		$this->set_property( 'post_parent', $post_parent );

		return $this;
	}

	/**
	 * Sets the guid property.
	 *
	 * @param string|MigrationObjectPropertyWrapper $guid The guid.
	 *
	 * @return WordPressPostsData
	 */
	public function set_guid( string|MigrationObjectPropertyWrapper $guid ): WordPressPostsData {
		$this->set_property( 'guid', $guid );

		return $this;
	}

	/**
	 * Sets the menu_order property.
	 *
	 * @param int|MigrationObjectPropertyWrapper $menu_order The menu order.
	 *
	 * @return WordPressPostsData
	 */
	public function set_menu_order( int|MigrationObjectPropertyWrapper $menu_order ): WordPressPostsData {
		$this->set_property( 'menu_order', $menu_order );

		return $this;
	}

	/**
	 * Sets the comment_status property.
	 *
	 * @param string|MigrationObjectPropertyWrapper $comment_status The comment status.
	 *
	 * @return WordPressPostsData
	 */
	public function set_comment_status( string|MigrationObjectPropertyWrapper $comment_status ): WordPressPostsData {
		$this->set_property( 'comment_status', $comment_status );

		return $this;
	}

	/**
	 * Sets the ping_status property.
	 *
	 * @param string|MigrationObjectPropertyWrapper $ping_status The ping status.
	 *
	 * @return WordPressPostsData
	 */
	public function set_ping_status( string|MigrationObjectPropertyWrapper $ping_status ): WordPressPostsData {
		$this->set_property( 'ping_status', $ping_status );

		return $this;
	}

	/**
	 * Sets the post_password property.
	 *
	 * @param string|MigrationObjectPropertyWrapper $post_password The post password.
	 *
	 * @return WordPressPostsData
	 */
	public function set_post_password( string|MigrationObjectPropertyWrapper $post_password ): WordPressPostsData {
		$this->set_property( 'post_password', $post_password );

		return $this;
	}

	/**
	 * Sets the post_name property.
	 *
	 * @param string|MigrationObjectPropertyWrapper $post_name The post name.
	 *
	 * @return WordPressPostsData
	 */
	public function set_post_name( string|MigrationObjectPropertyWrapper $post_name ): WordPressPostsData {
		$this->set_property( 'post_name', $post_name );

		return $this;
	}

	/**
	 * Sets the to_ping property.
	 *
	 * @param string|MigrationObjectPropertyWrapper $to_ping The to ping.
	 *
	 * @return WordPressPostsData
	 */
	public function set_to_ping( string|MigrationObjectPropertyWrapper $to_ping ): WordPressPostsData {
		$this->set_property( 'to_ping', $to_ping );

		return $this;
	}

	/**
	 * Sets the pinged property.
	 *
	 * @param string|MigrationObjectPropertyWrapper $pinged The pinged.
	 *
	 * @return WordPressPostsData
	 */
	public function set_pinged( string|MigrationObjectPropertyWrapper $pinged ): WordPressPostsData {
		$this->set_property( 'pinged', $pinged );

		return $this;
	}

	/**
	 * Sets the post_content_filtered property.
	 *
	 * @param string|MigrationObjectPropertyWrapper $post_content_filtered The post content filtered.
	 *
	 * @return WordPressPostsData
	 */
	public function set_post_content_filtered( string|MigrationObjectPropertyWrapper $post_content_filtered ): WordPressPostsData {
		$this->set_property( 'post_content_filtered', $post_content_filtered );

		return $this;
	}

	/**
	 * Sets the comment_count property.
	 *
	 * @param int|MigrationObjectPropertyWrapper $comment_count The comment count.
	 *
	 * @return WordPressPostsData
	 */
	public function set_comment_count( int|MigrationObjectPropertyWrapper $comment_count ): WordPressPostsData {
		$this->set_property( 'comment_count', $comment_count );

		return $this;
	}

	/**
	 * This function will store the list of co-authors to be set upon post creation.
	 *
	 * @param int[]|WP_User[]|MigrationObjectPropertyWrapper[] $authors The authors to set for a particular post.
	 *
	 * @return WordPressPostsData
	 * @throws Exception If the author is not a valid user.
	 */
	public function set_authors( array $authors ): WordPressPostsData {
		$this->authors = [];

		if ( isset( $this->post_author ) ) {
			$this->maintain_authors_array( $this->post_author );
		}

		foreach ( $authors as $author ) {
			if ( ! isset( $this->post_author ) ) {
				$this->set_post_author( $author );
				continue;
			}

			$this->maintain_authors_array( $author );
		}

		return $this;
	}

	/**
	 * This function will add an author to the list of co-authors to be set upon post creation.
	 *
	 * @param int|WP_User|MigrationObjectPropertyWrapper $author The author to add to the authors array.
	 *
	 * @throws Exception If the author is not a valid user.
	 */
	public function add_author( int|WP_User|MigrationObjectPropertyWrapper $author ): WordPressPostsData {
		$this->maintain_authors_array( $author );

		return $this;
	}

	/**
	 * Sets the categories for this post.
	 *
	 * @param int[]|string[]|WP_Term[]|MigrationObjectPropertyWrapper[] $categories The categories to set for the post.
	 * @param bool                                                      $create_if_not_found Whether to create the category if it does not exist.
	 *
	 * @return WordPressPostsData
	 * @throws Exception If the category is not found and $create_if_not_found is false.
	 */
	public function set_categories( array $categories, bool $create_if_not_found = false ): WordPressPostsData {
		foreach ( $categories as $category ) {
			$this->maintain_terms_arrays( $this->categories, 'category', $category, $create_if_not_found );
		}

		return $this;
	}

	/**
	 * Adds a category to the list of categories for this post.
	 *
	 * @param int|string|WP_Term|MigrationObjectPropertyWrapper $category The category to add to the categories array.
	 * @param bool                                              $create_if_not_found Whether to create the category if it does not exist.
	 *
	 * @return $this
	 * @throws Exception If the category is not found and $create_if_not_found is false.
	 */
	public function add_category( int|string|WP_Term|MigrationObjectPropertyWrapper $category, bool $create_if_not_found = false ): WordPressPostsData {
		$this->maintain_terms_arrays( $this->categories, 'category', $category, $create_if_not_found );

		return $this;
	}

	/**
	 * Creates a post with the given data.
	 *
	 * @return WP_Error|int
	 * @throws Exception If the co-authors cannot be set after post creation.
	 */
	public function create(): WP_Error|int {
		$copy_migration_object = $this->get_migration_object();

		$result = parent::create();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// At this point, $data, $data_sources, $migration_object have been reset.

		if ( ! empty( $this->authors ) ) {
			$this->handle_author_assignment( $result, $copy_migration_object );
		}

		if ( ! empty( $this->categories ) ) {
			$this->handle_term_assignment( $this->categories, 'category', $result, $copy_migration_object );
		}

		return $result;
	}

	/**
	 * Updates a post with the given data.
	 *
	 * @return bool|WP_Error
	 * @throws Exception If authors or categories cannot be set.
	 */
	public function update(): bool|WP_Error {
		$copy_migration_object = $this->get_migration_object();

		$result = parent::update();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! empty( $this->authors ) ) {
			$this->handle_author_assignment( $result, $copy_migration_object );
		}

		if ( ! empty( $this->categories ) ) {
			$this->handle_term_assignment( $this->categories, 'category', $result, $copy_migration_object );
		}

		return $result;
	}

	/**
	 * This function handles some logistics around author assignment. Given the result of the post creation, it assigns
	 * co-authors to the post. If this is a post update, it will also handle removing any existing author-post
	 * relationships if necessary. Finally, it also handles the recording of the co-author assignments
	 * in the migration_destination_sources table.
	 *
	 * @param int             $post_id The ID of the post.
	 * @param MigrationObject $migration_object The migration object.
	 *
	 * @return void
	 * @throws Exception If the co-authors cannot be set after post creation.
	 */
	private function handle_author_assignment( int $post_id, MigrationObject $migration_object ): void {
		// TODO add check to see if full CAP is being used, or using just guest-contributors.

		$maybe_coauthors_have_been_set = $this->co_authors_plus->add_coauthors(
			$post_id,
			array_keys( $this->authors ),
			false, // Removes existing author-post relationships.
			'id'
		);

		if ( ! $maybe_coauthors_have_been_set ) {
			throw new Exception( 'Unable to set co-authors successfully after post was created.' );
		}

		foreach ( $this->authors as $author_id => $author ) {
			$user = get_user_by( 'id', $author_id );

			$guest_authors_enabled = false;

			if ( $guest_authors_enabled ) {
				$user = $this->co_authors_plus->get_coauthor_by( 'email', $user->user_email );
			}


			$author_term = $this->co_authors_plus->get_author_term( $user );

			$this->wpdb->insert(
				'migration_destination_sources',
				[
					'migration_object_id'       => $migration_object->get_id(),
					'wordpress_table_column_id' => WordPressData::get_instance()->get_column_id( 'term_relationships_view', 'virtual_primary_key' ),
					'wordpress_object_id'       => WordPressTermRelationshipsData::get_virtual_primary_key( $post_id, $author_term->term_taxonomy_id ),
					'json_path'                 => $author instanceof MigrationObjectPropertyWrapper ? $author->get_path() : '',
				]
			);
		}

		$this->authors = [];
	}

	/**
	 * Generalizes the process of handling category and tag assignment. This function handles some logistics around
	 * term assignment. Given the result of the post creation it assigns terms to the post. If this is a post
	 * update, it will also handle removing any existing term-post relationships if necessary. Finally, it
	 * also handles the recording of the term assignments in the migration_destination_sources table.
	 *
	 * @param array           $terms An array containing the terms to be assigned.
	 * @param string          $taxonomy The taxonomy to which the terms will be assigned.
	 * @param int             $post_id The ID of the post to which the terms will be assigned.
	 * @param MigrationObject $migration_object The migration object.
	 *
	 * @return void
	 * @throws Exception If unable to set terms successfully.
	 */
	private function handle_term_assignment( array $terms, string $taxonomy, int $post_id, MigrationObject $migration_object ): void {
		// phpcs:disable -- query properly formatted and escaped.
		$existing_post_terms = $this->wpdb->get_col(
			$this->wpdb->prepare(
				"SELECT 
    				tr.term_taxonomy_id 
				FROM {$this->wpdb->term_relationships} tr 
				    INNER JOIN {$this->wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id 
				WHERE tt.taxonomy = %s 
				  AND tr.object_id = %d",
				$taxonomy,
				$post_id
			)
		);
		// phpcs:enable
		$existing_post_terms = array_map( 'intval', $existing_post_terms );

		$terms_to_delete = [];
		foreach ( $existing_post_terms as $index => $term_taxonomy_id ) {
			if ( ! array_key_exists( $term_taxonomy_id, $terms ) ) {
				$terms_to_delete[ $term_taxonomy_id ] = $index;
				unset( $existing_post_terms[ $index ] );
			}
		}

		if ( ! empty( $terms_to_delete ) ) {
			$term_id_placeholders = implode( ',', array_fill( 0, count( $terms_to_delete ), '%d' ) );
			// phpcs:disable -- query properly formatted and escaped.
			$maybe_deleted = $this->wpdb->query(
				$this->wpdb->prepare(
					"DELETE FROM {$this->wpdb->term_relationships} WHERE object_id = %d AND term_taxonomy_id IN ({$term_id_placeholders})",
					$post_id,
					...array_keys( $terms_to_delete ),
				)
			);
			// phpcs:enable

			if ( false === (bool) $maybe_deleted ) {
				throw new Exception(
					sprintf(
						'Unable to delete existing post-%s relationships successfully.',
						$taxonomy // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					)
				);
			}
		}

		$term_order = 0;
		foreach ( $terms as $term_taxonomy_id => $term ) {
			if ( in_array( $term_taxonomy_id, $existing_post_terms, true ) ) {
				$this->wpdb->update(
					$this->wpdb->term_relationships,
					[
						'term_order' => $term_order,
					],
					[
						'object_id'        => $post_id,
						'term_taxonomy_id' => $term_taxonomy_id,
					]
				);
				++$term_order;

				$this->wpdb->insert(
					'migration_destination_sources',
					[
						'migration_object_id'       => $migration_object->get_id(),
						'wordpress_table_column_id' => WordPressData::get_instance()->get_column_id( 'term_relationships_view', 'virtual_primary_key' ),
						'wordpress_object_id'       => WordPressTermRelationshipsData::get_virtual_primary_key( $post_id, $term_taxonomy_id ),
						'json_path'                 => $term instanceof MigrationObjectPropertyWrapper ? $term->get_path() : '',
					]
				);
				continue;
			}

			$maybe_inserted = $this->wpdb->insert(
				$this->wpdb->term_relationships,
				[
					'object_id'        => $post_id,
					'term_taxonomy_id' => $term_taxonomy_id,
					'term_order'       => $term_order,
				]
			);

			if ( false === (bool) $maybe_inserted ) {
				throw new Exception(
					sprintf(
						'Unable to insert new post-%s relationships successfully.',
						$taxonomy // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					)
				);
			}

			++$term_order;

			$this->wpdb->insert(
				'migration_destination_sources',
				[
					'migration_object_id'       => $migration_object->get_id(),
					'wordpress_table_column_id' => WordPressData::get_instance()->get_column_id( 'term_relationships_view', 'virtual_primary_key' ),
					'wordpress_object_id'       => WordPressTermRelationshipsData::get_virtual_primary_key( $post_id, $term_taxonomy_id ),
					'json_path'                 => $term instanceof MigrationObjectPropertyWrapper ? $term->get_path() : '',
				]
			);
		}
	}

	/**
	 * Maintains a cached list of valid user IDs.
	 *
	 * @param int|WP_User $user The user to validate.
	 *
	 * @return bool
	 */
	private function is_in_valid_users_cache( int|WP_User $user ): bool {
		$cached_valid_users = wp_cache_get( self::VALID_USER_CACHE_KEY, self::CACHE_GROUP );

		if ( $user instanceof WP_User ) {
			if ( ! array_key_exists( $user->ID, $cached_valid_users ) ) {
				$cached_valid_users[ $user->ID ] = true;

				return wp_cache_set(
					self::VALID_USER_CACHE_KEY,
					$cached_valid_users,
					self::CACHE_GROUP,
					DAY_IN_SECONDS
				);
			}

			return true;
		}

		$wp_user = get_user_by( 'id', $user );

		if ( false === $wp_user ) {
			return false;
		}

		return $this->is_in_valid_users_cache( $wp_user );
	}

	/**
	 * Validates whether a valid WP_User can be obtained from the given $property.
	 *
	 * @param MigrationObjectPropertyWrapper $property A MigrationObjectPropertyWrapper possibly containing a pointer to a WP_User.
	 *
	 * @return MigrationObjectPropertyWrapper
	 * @throws Exception If a valid WP_User cannot be obtained from the given $property value.
	 */
	private function validate_property_is_valid_user( MigrationObjectPropertyWrapper $property ): MigrationObjectPropertyWrapper {
		$value = $property->get_value();

		if ( $value instanceof WP_User ) {
			$this->is_in_valid_users_cache( $value );

			return new MigrationObjectPropertyWrapper(
				$value->ID,
				explode( '.', $property->get_path() ),
				$property->get_migration_object()
			);
		}

		if ( is_string( $value ) ) {
			if ( ! is_numeric( $value ) ) {
				throw new Exception(
					sprintf(
						"A valid user cannot be obtained from this value: '%s'",
						$value // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					)
				);
			}

			$value = intval( $value );
		}

		if ( is_int( $value ) ) {
			if ( ! $this->is_in_valid_users_cache( $value ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new Exception( sprintf( 'User ID: %d does not exist.', $value ) );
			}
		}

		return $property;
	}

	/**
	 * Maintains a list of authors to be assigned as co-authors upon post creation.
	 *
	 * @param int|WP_User|MigrationObjectPropertyWrapper $author The author to maintain in the authors array.
	 *
	 * @return void
	 * @throws Exception Throws exception if the author is not a valid user.
	 */
	private function maintain_authors_array( int|WP_User|MigrationObjectPropertyWrapper $author ): void {
		if ( $author instanceof MigrationObjectPropertyWrapper ) {
			$author = $this->validate_property_is_valid_user( $author );

			$this->authors[ $author->get_value() ] = $author;
		} else {
			$author_id = $author;

			if ( $author instanceof WP_User ) {
				$author_id = $author->ID;
			}

			if ( ! $this->is_in_valid_users_cache( $author ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new Exception( sprintf( 'User ID: %d does not exist.', $author ) );
			}

			$this->authors[ $author_id ] = $author;
		}
	}

	/**
	 * Generalizes the retrieval or creation of terms for the given taxonomy.
	 *
	 * @param array                                             $terms An array to store the retrieved or created terms.
	 * @param string                                            $taxonomy The taxonomy to which the terms belong.
	 * @param int|string|WP_Term|MigrationObjectPropertyWrapper $term The term to retrieve or create.
	 * @param bool                                              $create_if_not_found Whether to create the term if it does not exist.
	 *
	 * @return void
	 * @throws Exception If the term does not exist and $create_if_not_found is false.
	 */
	private function maintain_terms_arrays( array &$terms, string $taxonomy, int|string|WP_Term|MigrationObjectPropertyWrapper $term, bool $create_if_not_found = false ): void {
		$value = $term;
		if ( $term instanceof MigrationObjectPropertyWrapper ) {
			if ( $term->get_value() instanceof WP_Term ) {
				$terms[ $term->get_value()->term_taxonomy_id ] = new MigrationObjectPropertyWrapper(
					$term->get_value()->term_taxonomy_id,
					explode( '.', $term->get_path() ),
					$term->get_migration_object()
				);

				return;
			}

			$value = $term->get_value();
		}

		if ( $value instanceof WP_Term && $taxonomy === $value->taxonomy ) {
			$terms[ $value->term_taxonomy_id ] = $value->term_taxonomy_id;
		} elseif ( is_string( $value ) && ! is_numeric( $value ) ) {
			// try to get the term by name, and if not found, then by slug.
			$db_term = get_term_by( 'name', $value, $taxonomy );
			if ( false === $db_term ) {
				$db_term = get_term_by( 'slug', $value, $taxonomy );
			}

			if ( false === $db_term ) {
				if ( ! $create_if_not_found ) {
					throw new Exception(
						sprintf(
							'%s with name or slug: %s does not exist.',
							ucwords( $taxonomy ), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
							$value // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
						)
					);
				}

				// Unable to find the term, create it if flag is set.
				$db_term = (object) wp_insert_term(
					$value,
					$taxonomy,
					[
						'description' => '',
						'slug'        => sanitize_title( $value ),
					]
				);

				if ( is_wp_error( $db_term ) ) {
					throw new Exception(
						sprintf(
							'Unable to create %s: %s',
							ucwords( $taxonomy ), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
							$term->get_error_message() // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
						)
					);
				}
			}

			$terms[ $db_term->term_taxonomy_id ] = $term instanceof MigrationObjectPropertyWrapper ?
				new MigrationObjectPropertyWrapper(
					$db_term->term_taxonomy_id,
					explode( '.', $term->get_path() ),
					$term->get_migration_object()
				) : $db_term->term_taxonomy_id;
		} elseif ( is_numeric( $value ) ) {
			$db_term = get_term_by( 'term_taxonomy_id', $value, 'category' );

			if ( false === $db_term ) {
				throw new Exception(
					sprintf(
						'%s with `term_taxonomy_id`: %d does not exist.',
						ucwords( $taxonomy ), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
						$$value // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					)
				);
			}

			$terms[ $term->term_taxonomy_id ] = $term instanceof MigrationObjectPropertyWrapper ?
				new MigrationObjectPropertyWrapper(
					$db_term->term_taxonomy_id,
					explode( '.', $term->get_path() ),
					$term->get_migration_object()
				) : $db_term->term_taxonomy_id;
		}
	}
}
