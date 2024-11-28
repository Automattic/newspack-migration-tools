<?php

namespace Newspack\MigrationTools\Scaffold\WordPressData;

use DateTimeInterface;
use Exception;
use Newspack\MigrationTools\Scaffold\MigrationObjectPropertyWrapper;
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
	const CACHE_GROUP = 'migration_scaffold';
	/**
	 * The authors to set for a particular post.
	 *
	 * @var int[]|WP_User[]|MigrationObjectPropertyWrapper[] $authors The authors of a particular post.
	 */
	protected array $authors = [];

	/**
	 * WordPressPostsData constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->primary_key = 'ID';
		if ( ! wp_cache_get( self::VALID_USER_CACHE_KEY, self::CACHE_GROUP ) ) {
			wp_cache_set( self::VALID_USER_CACHE_KEY, [], self::CACHE_GROUP, DAY_IN_SECONDS );
		}
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
	 * @throws Exception
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
				explode( '.', $property->get_path() )
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
}
