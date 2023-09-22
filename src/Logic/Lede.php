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
	 * Posts instance.
	 *
	 * @var Posts Posts instance.
	 */
	private $posts;

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
	 * @param string $live_table_prefix Live tables prefix. Needed to access live_posts, live_postmeta and live_users.
	 * @param int    $post_id           Post ID.
	 *
	 * @throws \RuntimeException If required live tables don't exist.
	 *
	 * @return array|null GA IDs.
	 */
	public function convert_lede_authors_to_gas_for_post( $live_table_prefix, $post_id ) {
		global $wpdb;

		/**
		 * Get this post's byline postmeta.
		 */
		// phpcs:ignore -- Table name properly escaped.
		$byline_postmeta_row = $wpdb->get_row( $wpdb->prepare( "select * from {$wpdb->postmeta} where meta_key = 'byline' and post_id = %d ;", $post_id ), ARRAY_A );
		if ( empty( $byline_postmeta_row ) ) {
			return null;
		}

		$byline_meta = unserialize( $byline_postmeta_row['meta_value'] );
		$ga_ids      = [];
		foreach ( $byline_meta['profiles'] as $profile ) {

			// Check if script knows how to convert this byline type.
			$type = $profile['type'];
			if ( ! in_array( $type, self::KNOWN_LEDE_AUTHOR_BYLINE_TYPES ) ) {
				throw new \RuntimeException( sprintf( "Byline type '$type' is unknown. See self::KNOWN_LEDE_AUTHOR_BYLINE_TYPES and write more code to convert this byline type to GA." ) );
			}

			// Convert byline types to GAs.
			switch ( $type ) {
				case 'byline_id':
					// Unserialized postmeta data expects to contain 'term_id' and 'post_id'.
					$byline_term_id = $profile['atts']['term_id'] ?? null;
					$byline_post_id = $profile['atts']['post_id'] ?? null;
					$ga_id          = $this->convert_byline_id_author_profile_type_to_ga( $live_table_prefix, $byline_term_id, $byline_post_id );
					$ga_ids[]       = $ga_id;
					break;

				case 'text':
					// Unserialized postmeta data is only expected to have 'text', i.e. display_name.
					$text     = $profile['atts']['text'] ?? null;
					$ga_id    = $this->convert_text_author_profile_type_to_ga( $text );
					$ga_ids[] = $ga_id;
					break;
			}
		}

		// Assign GAs to the post.
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
		if ( $result && is_object( $result ) ) {
			$ga_id = $result->ID;

			return $ga_id;
		} elseif ( $result && is_array( $result ) && count( $result ) > 1 ) {
			$ga_id = $result[0]->ID;

			return $ga_id;
		}

		// Create GA.
		$ga_id = $this->cap->create_guest_author( [ 'display_name' => $text ] );

		return $ga_id;
	}

	/**
	 * Converts Lede Author byline of type 'profile' to GA.
	 * If a GA exists with same display_name, it will return that GA's ID and also update its data from Lede Author data.
	 *
	 * @param string $live_table_prefix Live tables prefix. Needed to access live_posts, live_postmeta and live_users.
	 * @param int    $byline_term_id    Not really used because we seem to be finding all the info in author $post_id.
	 * @param int    $byline_post_id    Author post ID.
	 *
	 * @return int|null GA ID, existing or newly created.
	 * @throws \RuntimeException If GA wasn't created successfully.
	 */
	public function convert_byline_id_author_profile_type_to_ga( $live_table_prefix, $byline_term_id, $byline_post_id ) {
		global $wpdb;

		$live_posts_table = esc_sql( $live_table_prefix . 'posts' );
		// phpcs:ignore -- Table name properly escaped.
		$profile_post_row = $wpdb->get_row( $wpdb->prepare( "select * from {$live_posts_table} where ID = %d;", $byline_post_id ), ARRAY_A );
		if ( empty( $profile_post_row ) ) {
			return null;
		}

		// post_title is display name is mandatory.
		if ( empty( $profile_post_row['post_title'] ) ) {
			return null;
		}

		// GA creation array.
		$ga_args      = [];
		$social_links = [];

		// Get author data stored in wp_post.
		$ga_args['display_name'] = $profile_post_row['post_title'];
		if ( ! empty( $profile_post_row['post_content'] ) ) {
			$ga_args['description'] = $profile_post_row['post_content'];
		}
		if ( ! empty( $profile_post_row['post_name'] ) ) {
			$ga_args['user_login'] = $profile_post_row['post_name'];
		}

		// Get author data stored in wp_postmeta.
		$live_postmeta_table = esc_sql( $live_table_prefix . 'postmeta' );
		// phpcs:ignore -- Table name properly escaped.
		$profile_postmeta_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$live_postmeta_table} WHERE post_id = %d;", $profile_post_row['ID'] ), ARRAY_A );
		foreach ( $profile_postmeta_rows as $profile_postmeta_row ) {

			// user_login.
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

			// Short bio. Append it to description after a double line break.
			if ( 'short_bio' == $profile_postmeta_row['meta_key'] && ! empty( $profile_postmeta_row['meta_value'] ) ) {
				if ( ! isset( $ga_args['description'] ) ) {
					$ga_args['description'] = '';
				}
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

			// Additional social links.
			if ( 'twitter' == $profile_postmeta_row['meta_key'] && ! empty( $profile_postmeta_row['meta_value'] ) ) {
				$handle         = $profile_postmeta_row['meta_value'];
				$social_links[] = sprintf( '<a href="https://twitter.com/%s" target="_blank">Twitter @%s</a>', $handle, $handle );
			}
		}

		// Append social links to the description.
		if ( ! empty( $social_links ) ) {
			if ( ! isset( $ga_args['description'] ) ) {
				$ga_args['description'] = '';
			}
			$ga_args['description'] .= ! empty( $ga_args['description'] ) ? ' ' : '';
			$ga_args['description'] .= implode( ' ', $social_links );
		}

		// Check if GA already exists.
		$ga    = $this->cap->get_guest_author_by_display_name( $ga_args['display_name'] );
		$ga    = is_array( $ga ) && count( $ga ) > 1 ? $ga[0] : $ga;
		$ga_id = $ga ? $ga->ID : null;

		// Create GA if doesn't exist, or update existing GA's data.
		if ( ! $ga_id ) {
			$ga_id = $this->cap->create_guest_author( $ga_args );
			if ( ! $ga_id ) {
				throw new \RuntimeException( sprintf( 'create_guest_author() did not return ga_id, ga_args:%s', json_encode( $ga_args ) ) );
			}
		} else {
			$update_args = [];
			if ( isset( $ga_args['first_name'] ) ) {
				$update_args['first_name'] = $ga_args['first_name'];
			}
			if ( isset( $ga_args['last_name'] ) ) {
				$update_args['last_name'] = $ga_args['last_name'];
			}
			if ( isset( $ga_args['user_email'] ) ) {
				$update_args['user_email'] = $ga_args['user_email'];
			}
			if ( isset( $ga_args['website'] ) ) {
				$update_args['website'] = $ga_args['website'];
			}
			if ( isset( $ga_args['description'] ) ) {
				$update_args['description'] = $ga_args['description'];
			}
			if ( isset( $ga_args['avatar'] ) ) {
				$update_args['avatar'] = $ga_args['avatar'];
			}

			$this->cap->update_guest_author( $ga_id, $update_args );
		}

		// Link GA to WP_User.
		if ( $linked_wp_user_id ) {
			$live_users_table = esc_sql( $live_table_prefix . 'users' );
			// Get user with original ID from live table. Then find the new user ID.
			// phpcs:ignore -- Table name properly escaped.
			$wp_user_email = $wpdb->get_var( $wpdb->prepare( "select user_email from {$live_users_table} where ID = %d;", $linked_wp_user_id ), ARRAY_A );
			if ( $wp_user_email ) {
				$wp_user = get_user_by( 'email', $wp_user_email );
				if ( $wp_user ) {
					$this->cap->link_guest_author_to_wp_user( $ga_id, $wp_user );
				}
			}
		}

		return $ga_id;
	}
}
