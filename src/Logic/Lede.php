<?php

namespace NewspackCustomContentMigrator\Logic;

use \NewspackCustomContentMigrator\Logic\CoAuthorPlus;
use \NewspackCustomContentMigrator\Logic\Posts;
use \NewspackCustomContentMigrator\Utils\Logger;

/**
 * Lede migration logic.
 */
class Lede {

	/**
	 * This script knows how to convert following byline types. Add more if encountered.
	 */
	const KNOWN_LEDE_AUTHOR_BYLINE_TYPES = [
		'byline_id',
		'text',
	];

	/**
	 * CoAuthorPlus instance.
	 *
	 * @var CoAuthorPlus CoAuthorPlus instance.
	 */
	private $cap;

	/**
	 * Logger instance.
	 *
	 * @var Logger Logger instance.
	 */
	private $logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->cap    = new CoAuthorPlus();
		$this->posts  = new Posts();
		$this->logger = new Logger();
	}

	/**
	 * Converts Lede Authors to GA authors for a single post.
	 *
	 * @param string $live_table_prefix Live tables prefix. Needed to access live_posts and live_postmeta.
	 * @param int    $post_id           Post ID.
	 *
	 * @throws \RuntimeException If required live tables don't exist.
	 *
	 * @return array|null GA IDs.
	 */
	public function convert_lede_authors_to_gas_for_post( $live_table_prefix, $post_id ) {
		global $wpdb;

		/**
		 * Validate that tables live_posts and live_postmeta exist.
		 */
		$live_posts_table    = esc_sql( $live_table_prefix . 'posts' );
		$live_postmeta_table = esc_sql( $live_table_prefix . 'postmeta' );
		// phpcs:ignore -- Table name properly escaped.
		$test_var = $wpdb->get_var( "select 1 from $live_posts_table;" );
		if ( ! $test_var ) {
			throw new \RuntimeException(
				sprintf(
					'Table %s not found -- needed to access original Lede Author data.',
					$live_posts_table
				)
			);
		}
		// phpcs:ignore -- Table name properly escaped.
		$test_var = $wpdb->get_var( "select 1 from $live_postmeta_table;" );
		if ( ! $test_var ) {
			throw new \RuntimeException( sprintf( 'Table %s not found -- needed to access original Lede Author data.', $live_postmeta_table ) );
		}


		/**
		 * Get this post's byline postmeta.
		 */
		// phpcs:ignore -- Table name properly escaped.
		$byline_postmeta_row = $wpdb->get_row( "select * from {$wpdb->postmeta} where meta_key = 'byline' and post_id = %d ;", $post_id );
		if ( empty( $byline_postmeta_row ) ) {
			return null;
		}

		$byline_meta = unserialize( $byline_postmeta_row[0]['meta_value'] );
		foreach ( $byline_meta['profiles'] as $profile ) {
			$type = $profile['type'];

			// Check if script knows how to convert this byline type.
			if ( ! in_array( $type, self::KNOWN_LEDE_AUTHOR_BYLINE_TYPES ) ) {
				throw new \RuntimeException( sprintf( "Byline type '$type' is unknown. See self::KNOWN_LEDE_AUTHOR_BYLINE_TYPES and write more code to convert this byline type to GA." ) );
			}

			// Convert byline to GAs.
			$ga_ids = [];
			switch ( $type ) {
				case 'byline_id':
					// Unserialized data expects to contain 'term_id' and 'post_id'.
					$term_id  = $profile['type']['atts']['term_id'] ?? null;
					$post_id  = $profile['type']['atts']['post_id'] ?? null;
					$ga_id    = $this->convert_byline_id_author_profile_type_to_ga( $live_table_prefix, $term_id, $post_id );
					$ga_ids[] = $ga_id;
					break;

				case 'text':
					// Unserialized data is only expected to have 'text', i.e. display_name.
					$text     = $profile['type']['atts']['text'] ?? null;
					$ga_id    = $this->convert_text_author_profile_type_to_ga( $text );
					$ga_ids[] = $ga_id;
					break;
			}
		}

		// Finally assign GAs to the post.
		if ( $ga_ids ) {
			$this->cap->assign_guest_authors_to_post( $ga_ids, $post_id, $append_to_existing_users = false );
		}

		return $ga_ids;
	}

	/**
	 * Converts Lede Author byline of type 'text' to GA.
	 *
	 * @param string $text Text inside byline data, which is author's display name.
	 *
	 * @return int GA ID, existing or newly created.
	 */
	public function convert_text_author_profile_type_to_ga( $text ) {

		// Get existing GA.
		$result = $this->cap->get_guest_author_by_display_name( $text );
		if ( 1 == count( $result ) ) {
			$ga_id = $result->ID;

			return $ga_id;
		} elseif ( count( $result ) > 1 ) {
			$ga_id = $result[0]->ID;

			return $ga_id;
		}

		// Create GA.
		$ga_id = $this->cap->create_guest_author( [ 'display_name' => $text ] );

		return $ga_id;
	}

	/**
	 * Converts Lede Author byline of type 'profile' to GA.
	 *
	 * @param string $live_table_prefix Live tables prefix. Needed to access live_posts and live_postmeta.
	 * @param int    $term_id              Not really used because we seem to be finding all the info in author $post_id.
	 * @param int    $post_id              Author post ID.
	 *
	 * @throws \RuntimeException If GA wasn't created successfully.
	 *
	 * @return int|null GA ID, existing or newly created.
	 */
	public function convert_byline_id_author_profile_type_to_ga( $live_table_prefix, $term_id, $post_id ) {
		global $wpdb;

		$live_posts_table = esc_sql( $live_table_prefix . 'posts' );
		// phpcs:ignore -- Table name properly escaped.
		$profile_post_row = $wpdb->get_row( $wpdb->prepare( "select * from {$live_posts_table} where ID = %d;", $post_id ) );
		if ( empty( $profile_post_row ) ) {
			return null;
		}

		// post_title is mandatory.
		if ( empty( $profile_post_row['post_title'] ) ) {
			return null;
		}

		// GA creation array.
		$ga_args      = [];
		$social_links = [];

		// Get author data available from wp_posts.
		$ga_args['display_name'] = $profile_post_row['post_title'];
		if ( ! empty( $profile_post_row['post_content'] ) ) {
			$ga_args['description'] = $profile_post_row['post_content'];
		}
		if ( ! empty( $profile_post_row['post_name'] ) ) {
			$ga_args['user_login'] = $profile_post_row['post_name'];
		}

		// Get author data from wp_postmeta.
		// phpcs:ignore -- Table name properly escaped.
		$profile_postmeta_rows = $wpdb->get_results( "SELECT * FROM {$live_posts_table} WHERE post_id = {$profile_post_row['ID']};", ARRAY_A );
		foreach ( $profile_postmeta_rows as $profile_postmeta_row ) {

			// user_login can be stored as postmeta. Use it if exists.
			if ( 'user_login' == $profile_postmeta_row['meta_key'] && ! empty( $profile_postmeta_row['meta_value'] ) ) {
				$ga_args['user_login'] = $profile_postmeta_row['meta_value'];
			}

			// Email might be stored as two different metas.
			if ( 'user_email' == $profile_postmeta_row['meta_key'] && ! empty( $profile_postmeta_row['meta_value'] ) ) {
				$ga_args['user_email'] = $profile_postmeta_row['meta_value'];
			}
			if ( 'email' == $profile_postmeta_row['meta_key'] && ! empty( $profile_postmeta_row['meta_value'] ) ) {
				$ga_args['user_email'] = $profile_postmeta_row['meta_value'];
			}

			// Short bio can be stored as short_bio meta_key. Append it to description with a double line break.
			if ( 'short_bio' == $profile_postmeta_row['meta_key'] && ! empty( $profile_postmeta_row['meta_value'] ) ) {
				$ga_args['description'] .= ! empty( $ga_args['description'] ) ? "\n\n" : '';
				$ga_args['description'] .= $profile_postmeta_row['meta_value'];
			}

			if ( 'first_name' == $profile_postmeta_row['meta_key'] && ! empty( $profile_postmeta_row['meta_value'] ) ) {
				$ga_args['first_name'] = $profile_postmeta_row['meta_value'];
			}
			if ( 'last_name' == $profile_postmeta_row['meta_key'] && ! empty( $profile_postmeta_row['meta_value'] ) ) {
				$ga_args['last_name'] = $profile_postmeta_row['meta_value'];
			}

			// Avatar.
			if ( '_thumbnail_id' == $profile_postmeta_row['meta_key'] && ! empty( $profile_postmeta_row['meta_value'] ) ) {
				$ga_args['avatar'] = $profile_postmeta_row['meta_value'];
			}

			// Linked WP_User ID.
			$linked_wp_user_id = null;
			if ( 'user_id' == $profile_postmeta_row['meta_key'] && ! empty( $profile_postmeta_row['meta_value'] ) ) {
				$linked_wp_user_id = $profile_postmeta_row['meta_value'];
			}

			// Get additional social links.
			if ( 'twitter' == $profile_postmeta_row['meta_key'] && ! empty( $profile_postmeta_row['meta_value'] ) ) {
				$handle         = $profile_postmeta_row['meta_value'];
				$social_links[] = sprintf( '<a href="https://twitter.com/%s" target="_blank">Twitter @%s</a>', $handle, $handle );
			}
		}

		// Append social links to the description.
		if ( ! empty( $social_links ) ) {
			$ga_args['description'] .= ! empty( $ga_args['description'] ) ? ' ' : '';
			$ga_args['description'] .= implode( ' ', $social_links );
		}

		// Check if GA already exists.
		$ga_id = null;
		if ( isset( $ga_args['user_login'] ) && ! empty( $ga_args['user_login'] ) ) {
			$ga    = $this->cap->get_guest_author_by_user_login( $ga_args['user_login'] );
			$ga_id = $ga ? $ga->ID : null;
		} else {
			$ga    = $this->cap->get_guest_author_by_display_name( $ga_args['display_name'] );
			$ga_id = $ga ? $ga->ID : null;
		}
		if ( $ga_id ) {
			return $ga_id;
		}

		// Create GA.
		$ga_id = $this->cap->create_guest_author( $ga_args );
		if ( ! $ga_id ) {
			throw new \RuntimeException( sprintf( 'create_guest_author() did not return ga_id, ga_args:%s', json_encode( $ga_args ) ) );
		}

		// Link WP_User.
		if ( $linked_wp_user_id ) {
			$wp_user = get_user_by( 'ID', $linked_wp_user_id );
			if ( $wp_user ) {
				$this->cap->link_guest_author_to_wp_user( $ga_id, $wp_user );
			}
		}

		return $ga_id;
	}

}
