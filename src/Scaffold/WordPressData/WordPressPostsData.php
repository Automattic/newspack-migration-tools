<?php

namespace Newspack\MigrationTools\Scaffold\WordPressData;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
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

	/**
	 * WordPressPostsData constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->primary_key = 'ID';
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
	 */
	public function set_post_author( int|WP_User|MigrationObjectPropertyWrapper $post_author ): WordPressPostsData {
		if ( $post_author instanceof WP_User ) {
			$post_author = $post_author->ID;
		}

		$this->set_property( 'post_author', $post_author );

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
	 * Tries to create a DateTimeInterface object from a given date.
	 *
	 * @param mixed $date The date.
	 *
	 * @return DateTimeInterface
	 * @throws Exception If the date is not a valid date.
	 */
	private function get_date_time( mixed $date ): DateTimeInterface {
		if ( $date instanceof DateTimeInterface ) {
			return $date;
		}

		if ( $date instanceof MigrationObjectPropertyWrapper && $date->get_value() instanceof DateTimeInterface ) {
			return $date->get_value();
		}

		return new DateTimeImmutable( $date );
	}

	/**
	 * This function handles setting a date property.
	 *
	 * @param string|MigrationObjectPropertyWrapper|DateTimeInterface $date The date.
	 * @param string                                                  $property_name The property name.
	 *
	 * @return void
	 * @throws Exception If the date string is malformed.
	 */
	private function set_date_property( string|MigrationObjectPropertyWrapper|DateTimeInterface $date, string $property_name ): void {
		$date_time = $this->get_date_time( $date );

		$this->set_property( $property_name, $date_time->format( 'Y-m-d H:i:s' ) );
	}

	/**
	 * Sets a GMT date property.
	 *
	 * @param string|MigrationObjectPropertyWrapper|DateTimeInterface $date The date.
	 * @param string                                                  $property_name The property name.
	 *
	 * @return void
	 * @throws Exception If the date string is malformed.
	 */
	private function set_gmt_date_property( string|MigrationObjectPropertyWrapper|DateTimeInterface $date, string $property_name ): void {
		$date_time = $this->get_date_time( $date );

		if ( $date_time->getTimezone() instanceof DateTimeZone ) {
			if ( $date_time->getTimezone()->getName() !== 'UTC' ) {
				$copy_date_time = new DateTime( $date_time->format( 'Y-m-d H:i:s' ), $date_time->getTimezone() );
				$copy_date_time->setTimezone( new DateTimeZone( 'UTC' ) );
				$date_time = $copy_date_time;
			}
		}

		$this->set_property( $property_name, $date_time->format( 'Y-m-d H:i:s' ) );
	}
}
