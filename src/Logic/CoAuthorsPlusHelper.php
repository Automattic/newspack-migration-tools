<?php

namespace Newspack\MigrationTools\Logic;

use CoAuthors_Guest_Authors;
use CoAuthors_Plus;

class CoAuthorsPlusHelper {

	/**
	 * CoAuthors_Plus.
	 *
	 * @var null|CoAuthors_Plus $coauthors_plus
	 */
	public $coauthors_plus;

	/**
	 * CoAuthors_Guest_Authors.
	 *
	 * @var null|CoAuthors_Guest_Authors
	 */
	public $coauthors_guest_authors;

	/**
	 * CoAuthorPlus constructor.
	 */
	public function __construct() {
		// Set Co-Authors Plus dependencies.
		global $coauthors_plus;

		$plugin_path = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : ABSPATH . 'wp-content/plugins';

		$file_1     = $plugin_path . '/co-authors-plus/co-authors-plus.php';
		$file_2     = $plugin_path . '/co-authors-plus/php/class-coauthors-guest-authors.php';
		$included_1 = is_file( $file_1 ) && include_once $file_1;
		$included_2 = is_file( $file_2 ) && include_once $file_2;

		if ( is_null( $coauthors_plus ) || ( false === $included_1 ) || ( false === $included_2 ) || ( ! $coauthors_plus instanceof CoAuthors_Plus ) ) {
			// CoAuthors Plus is a dependency, and will have to be installed before the public functions/commands can be used.
			return;
		}

		$this->coauthors_plus          = $coauthors_plus;
		$this->coauthors_guest_authors = new CoAuthors_Guest_Authors();
	}

	/**
	 * Validates whether Co-Author Plus plugin's dependencies were successfully set.
	 *
	 * @return bool Is everything set up OK.
	 */
	public function validate_co_authors_plus_dependencies() {
		if ( ( ! $this->coauthors_plus instanceof CoAuthors_Plus ) || ( ! $this->coauthors_guest_authors instanceof CoAuthors_Guest_Authors ) ) {
			return false;
		}

		if ( false === $this->is_coauthors_active() ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks whether Co-authors Plus is installed and active.
	 *
	 * @return bool Is active.
	 */
	public function is_coauthors_active() {
		$active = false;
		foreach ( wp_get_active_and_valid_plugins() as $plugin ) {
			if ( false !== strrpos( $plugin, 'co-authors-plus.php' ) ) {
				$active = true;
			}
		}

		return $active;
	}

	/**
	 * Gets existing GA by display_name, or creates a new Guest Author if it doesn't exist.
	 *
	 * @param array $args         {
	 *                            The $args param for the \CoAuthors_Guest_Authors::create method.
	 *
	 * @type string $display_name This is the only required param.
	 * @type string $user_login   This param is optional, since this function automatically creates it from the $display_name.
	 * @type string $first_name
	 * @type string $last_name
	 * @type string $user_email
	 * @type string $website
	 * @type string $description
	 * @type int    $avatar       Attachment ID for the Avatar image.
	 *                            }
	 *
	 * @return int|array|WP_Error Created Guest Author ID, or an array of created Guest Author IDs, or WP_Error.
	 *
	 * @throws \UnexpectedValueException In case mandatory argument values aren't provided.
	 */
	public function create_guest_author( array $args ) {
		if ( ! isset( $args['display_name'] ) ) {
			throw new \UnexpectedValueException( 'The `display_name` param is mandatory for Guest Author creation.' );
		}

		// If not provided, automatically set `user_login` from display_name.
		if ( ! isset( $args['user_login'] ) ) {
			$args['user_login'] = sanitize_title( $args['display_name'] );
		} else {
			/**
			 * If user_login is provided, let's sanitize it both with urldecode() and sanitize_title(), to minimize errors when
			 * CAP saves and then retrieves it.
			 *
			 * See \CoAuthors_Guest_Authors::get_guest_author_by for why this is needed:
			 *   - because they do:
			 *       $guest_author['user_login'] = urldecode( $guest_author['user_login'] );
			 *
			 *   - so for example, if create() gets 'user_login' = '3.42488E+15',
			 *      - `post_name` will be == 'cap' + sanitize_title(            '3.42488E+15'   ) == "cap-6-20386e15"
			 *      - but `slug` will be  == 'cap' + sanitize_title( urldecode( '3.42488E+15' ) ) == "cap-6-20386e-15"
			 *      - which are different.
			 *
			 *      => And this is an edge case bug. These GAs will fail with errors if you try and assign them to Posts.
			 *
			 * Additionally see \CoAuthors_Plus::update_author_term where wp_insert_term() causes:
			 *      - wp_terms.name will be == "6.20386e 15"  (comes from get_guest_author_by()'s `user_login`)
			 *      - while description will contain "6-20386e-15"
			 */
			$args['user_login'] = sanitize_title( urldecode( $args['user_login'] ) );

			// If user_login is the same as existing WP_User.username, CAP will allow it to be created, but it will not allow it to be edited:
			// "There is a WordPress user with the same username as this guest author, please go back and link them in order to update."
			// So let's prevent that by giving it a unique user_login which is not used by WP_User. That way, we can keep the GA and WP_User separate.
			// or link them afterwards, both options will work fine.
			// Check if $args['user_login'] is used by WP_User.username.
			$args['user_login'] = $this->get_unique_user_login( $args['user_login'] );
		}

		$guest_author = $this->get_guest_author_by_display_name( $args['display_name'] );
		if ( is_null( $guest_author ) ) {
			$coauthor_id = $this->coauthors_guest_authors->create( $args );
		} else {
			$coauthor_id = $guest_author->ID;
		}

		return $coauthor_id;
	}

	/**
	 * Gets a unique user_login for a new Guest Author, making sure that WP_User.username is not using it.
	 * If $user_login is already used, will return a unique one with appended "_{random_string}" to it.
	 *
	 * @param string $user_login User login.
	 *
	 * @return string Unique user_login.
	 */
	public function get_unique_user_login( string $user_login ): string {
		global $wpdb;

		// Return this $user_login if it doesn't exist as WP_User.user_login or CAP postmeta 'cap-user_login'.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
		$wpuser_login_exists  = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->users WHERE user_login = %s", $user_login ) );
		$capuser_login_exists = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'cap-user_login' AND meta_value = %s", $user_login ) );
		// phpcs:enable
		if ( ! $wpuser_login_exists && ! $capuser_login_exists ) {
			return $user_login;
		}

		// Create a unique user_login one and return that if it's not taken.
		$unique_user_login = sprintf( '%s_%s', $user_login, $this->generate_random_string() );
		// phpcs:disable -- Allow querying users table.
		$unique_wpuser_login_exists = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->users WHERE user_login = %s", $unique_user_login ) );
		// phpcs:ensable
		$unique_capuser_login_exists = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $wpdb->postmeta WHERE meta_key = 'cap-user_login' AND meta_value = %s",
			$unique_user_login ) );
		if ( ! $unique_wpuser_login_exists && ! $unique_capuser_login_exists ) {
			return $unique_user_login;
		}

		// Recursively call until returns a unique user_login.
		return $this->get_unique_user_login( $user_login );
	}

	/**
	 * Generates a random string.
	 *
	 * @param int $length Length of the random string to generate.
	 *
	 * @return string Random string.
	 */
	public function generate_random_string( int $length = 5 ): string {
		$characters = 'abcdefghijklmnopqrstuvwxyz0123456789';

		return substr( str_shuffle( str_repeat( $characters, $length ) ), 0, $length );
	}

	/**
	 * Updates Guest Author's data.
	 *
	 * @param int   $ga_id  Guest Author ID.
	 * @param array $args   {
	 *                      The $args param that can be updated (for the \CoAuthors_Guest_Authors::create method).
	 *
	 * @type string $display_name
	 * @type string $first_name
	 * @type string $last_name
	 * @type string $user_email
	 * @type string $website
	 * @type string $description
	 * @type int    $avatar Attachment ID for the Avatar image.
	 *                      }
	 *
	 * user_login field can presently not be updated. If needed, it can probably be done by updating:
	 *      - wp_postmeta.meta_value where meta_key = 'cap-user_login'
	 *      - wp_terms.name where term_id matches $ga_id post_id
	 *      - wp_terms.slug where term_id matches $ga_id post_id
	 *
	 * @throws \UnexpectedValueException If $args contains an unsupported key.
	 */
	public function update_guest_author( int $ga_id, array $args ) {
		global $wpdb;

		// Validate args keys.
		$allowed_args_keys = [
			'display_name',
			'first_name',
			'last_name',
			'user_email',
			'website',
			'description',
			'avatar',
		];
		foreach ( $args as $key => $value ) {
			if ( ! in_array( $key, $allowed_args_keys, true ) ) {
				throw new \UnexpectedValueException( 'The `' . $key . '` param is not allowed for Guest Author update.' );
			}
		}

		// Sanitize args.
		$args_sanitized = [];
		foreach ( $args as $key => $value ) {
			$key_sanitized                    = esc_sql( $key );
			$value_sanitized                  = esc_sql( $value );
			$args_sanitized[ $key_sanitized ] = $value_sanitized;
		}

		// Update display name.
		if ( isset( $args_sanitized['display_name'] ) && ! empty( $args_sanitized['display_name'] ) ) {
			$wpdb->update(
				$wpdb->posts,
				[ 'post_title' => $args_sanitized['display_name'] ],
				[ 'ID' => $ga_id ]
			);
			update_post_meta( $ga_id, 'cap-display_name', $args_sanitized['display_name'] );
		}

		// Update first_name.
		if ( isset( $args_sanitized['first_name'] ) && ! empty( $args_sanitized['first_name'] ) ) {
			update_post_meta( $ga_id, 'cap-first_name', $args_sanitized['first_name'] );
		}

		// Update last_name.
		if ( isset( $args_sanitized['last_name'] ) && ! empty( $args_sanitized['last_name'] ) ) {
			update_post_meta( $ga_id, 'cap-last_name', $args_sanitized['last_name'] );
		}

		// Update user_email.
		if ( isset( $args_sanitized['user_email'] ) && ! empty( $args_sanitized['user_email'] ) ) {
			update_post_meta( $ga_id, 'cap-user_email', $args_sanitized['user_email'] );
		}

		// Update website.
		if ( isset( $args_sanitized['website'] ) && ! empty( $args_sanitized['website'] ) ) {
			update_post_meta( $ga_id, 'cap-website', $args_sanitized['website'] );
		}

		// Update description.
		if ( isset( $args_sanitized['description'] ) && ! empty( $args_sanitized['description'] ) ) {
			update_post_meta( $ga_id, 'cap-description', $args_sanitized['description'] );
		}

		// Update avatar attachment ID.
		if ( isset( $args_sanitized['avatar'] ) && ! empty( $args_sanitized['avatar'] ) ) {
			update_post_meta( $ga_id, '_thumbnail_id', $args_sanitized['avatar'] );
		}

		wp_cache_flush();
	}

	/**
	 * Assigns Guest Authors to the Post.
	 *
	 * @param array $guest_author_ids         Guest Author IDs.
	 * @param int   $post_id                  Post IDs.
	 * @param bool  $append_to_existing_users Append to existing Guest Authors.
	 */
	public function assign_guest_authors_to_post( array $guest_author_ids, $post_id, bool $append_to_existing_users = false ) {
		$coauthors_user_nicenames = [];
		$coauthors                = [];
		foreach ( $guest_author_ids as $guest_author_id ) {
			$guest_author = $this->coauthors_guest_authors->get_guest_author_by( 'id', $guest_author_id );

			$coauthors_user_nicenames[] = $guest_author->user_nicename;
			$coauthors[]                = $guest_author;
		}
		$this->coauthors_plus->add_coauthors( $post_id, $coauthors_user_nicenames, $append_to_existing_users );

		// Validate if the authors were assigned correctly.
		$valid = $this->validate_authors_for_post( $post_id, $coauthors );
		if ( is_wp_error( $valid ) ) {
			$ga_ids = array_map( function ( $author ) {
				return $author->ID;
			}, $coauthors );
			throw new \RuntimeException(
				sprintf(
					'Failed to assign guest author IDs %s to post ID %d. Error: %s',
					implode( ',', $ga_ids ),
					$post_id,
					$valid->get_error_message() )
			);
		}
	}

	/**
	 * Assigns either GAs and/or WPUsers authors to Post.
	 *
	 * @param array $authors                  Array of mixed Guest Author \stdClass objects and/or \WP_User objects.
	 * @param int   $post_id                  Post IDs.
	 * @param bool  $append_to_existing_users Append to existing Guest Authors.
	 *
	 * @throws \UnexpectedValueException If $authors contains an unsupported class.
	 * @throws \RuntimeException         If authors were not assigned correctly.
	 */
	public function assign_authors_to_post( array $authors, $post_id, bool $append_to_existing_users = false ) {
		$coauthors_nicenames = [];
		foreach ( $authors as $author ) {
			if ( 'stdClass' === $author::class ) {
				$coauthors_nicenames[] = $author->user_nicename;
			} elseif ( 'WP_User' === $author::class ) {
				$coauthors_nicenames[] = $author->data->user_nicename;
			} else {
				throw new \UnexpectedValueException( 'The `' . $author::class . '` class is not allowed for Guest Author update.' );
			}
		}
		$this->coauthors_plus->add_coauthors( $post_id, $coauthors_nicenames, $append_to_existing_users );

		// Validate if the authors were assigned correctly.
		$valid = $this->validate_authors_for_post( $post_id, $authors );
		if ( is_wp_error( $valid ) ) {
			$author_ids = array_map( function ( $author ) {
				return $author->ID;
			}, $authors );
			throw new \RuntimeException( sprintf( 'Failed to assign author IDs %s to post ID %d. Error: %s', implode( ',', $author_ids ), $post_id, $valid->get_error_message() ) );
		}
	}

	/**
	 * Validates if expected authors (or author names) are assigned to post.
	 *
	 * @param int     $post_id                 Post ID.
	 * @param array   $author_names            Array of author objects (either WP_User or CAP GA objects) or just author display_names.
	 * @param boolean $strict_order_or_authors Optional. Should the order of authors be observed strictly? Default is yes/true.
	 *
	 * @return boolean|WP_Error                True if authors/author names are as expected, WP_Error if not.
	 */
	public function validate_authors_for_post( int $post_id, array $authors_expected, bool $strict_order_or_authors = true ): bool|WP_Error {

		// Get actual authors. If no GAs are assigned to post, get WP Post author.
		$authors_actual = $this->get_all_authors_for_post( $post_id );
		if ( ! $authors_actual ) {
			$wp_post        = get_post( $post_id );
			$authors_actual = [ get_userdata( $wp_post->post_author ) ];
		}

		// If number of authors is different, return error.
		if ( count( $authors_expected ) !== count( $authors_actual ) ) {
			return new WP_Error( sprintf( 'Post ID %d does not have the expected number of authors which is %d.', $post_id, count( $authors_expected ) ) );
		}

		// Validate expected authors.
		foreach ( $authors_expected as $key_author_expected => $author_expected ) {

			// If $strict_order_of_authors is true, also check exact author position.
			if ( true === $strict_order_or_authors ) {

				$author_actual = $authors_actual[ $key_author_expected ];
				if ( is_object( $author_expected ) ) {
					$author_actual_display_name   = $author_actual->display_name ?? '';
					$author_expected_display_name = $author_expected->display_name ?? '';
					$does_name_match              = $author_actual_display_name === $author_expected_display_name;
					if ( ! $does_name_match ) {
						return new WP_Error( sprintf( "Post ID %d author index %d actual author display_name '%s' is different from expected display name '%s'.", $post_id,
							$key_author_expected, $author_actual_display_name, $author_expected_display_name ) );
					}
					$does_ID_match = $author_expected->ID == $author_actual->ID;
					if ( ! $does_ID_match ) {
						return new WP_Error( sprintf( "Post ID %d author index %d actual author ID %d is different than expected object ID %d.", $post_id, $key_author_expected,
							$author_actual->ID, $author_expected->ID ) );
					}
				} elseif ( is_string( $author_expected ) ) {
					$does_name_match = $author_expected == $author_actual->display_name;
					if ( ! $does_name_match ) {
						return new WP_Error( sprintf( "Post ID %d author index %d actual author display_name '%s' is different from expected display name '%s'.", $post_id,
							$key_author_expected, $author_actual->display_name, $author_expected ) );
					}
				} else {
					return new WP_Error( sprintf( 'Expected author index %d is neither WP_User, nor GAP GA object, nor display_name string.', $key_author_expected ) );
				}

			} else {

				// If not observing strict order of authors, just check if the author names are present in the list of authors.
				if ( is_object( $author_expected ) ) {
					$does_name_match = in_array( $author_expected->display_name, array_column( $authors_actual, 'display_name' ) );
					if ( ! $does_name_match ) {
						return new WP_Error( sprintf( "Expected author index '%s' and display_name '%s' is not an author of post ID %d.", $key_author_expected,
							$author_expected->display_name, $post_id ) );
					}
					$does_ID_match = in_array( $author_expected->ID, array_column( $authors_actual, 'ID' ) );
					if ( ! $does_ID_match ) {
						return new WP_Error( sprintf( "Expected author index '%s' and ID '%d' is not an author of post ID %d.", $key_author_expected, $author_expected->ID,
							$post_id ) );
					}
				} elseif ( is_string( $author_expected ) ) {
					$does_name_match = in_array( $author_expected, array_column( $authors_actual, 'display_name' ) );
					if ( ! $does_name_match ) {
						return new WP_Error( sprintf( "Expected author index '%s' and display_name '%s' is not an author of post ID %d.", $key_author_expected, $author_expected,
							$post_id ) );
					}
				} else {
					return new WP_Error( sprintf( 'Expected author index %d is neither a WP_User, nor a GAP GA object, nor a string.', $key_author_expected ) );
				}

			}
		}

		return true;
	}

	/**
	 * Unassigns all Guest Authors from the Post.
	 * After running this, the Post will have no Guest Authors, and authorship will be determined by wp_posts.post_author WP_User.
	 *
	 * @param integer $post_id Post ID.
	 *
	 * @return void
	 * @throws \UnexpectedValueException If GA not removed successfully.
	 */
	public function unassign_all_guest_authors_from_post( int $post_id ): void {
		global $wpdb;

		// Get all object relationships for this post where taxonomy is 'author'.
		$results = $wpdb->get_results( $wpdb->prepare(
			"select wtr.object_id, wtr.term_taxonomy_id
			from $wpdb->term_relationships wtr
			join $wpdb->term_taxonomy wtt on wtt.term_taxonomy_id = wtr.term_taxonomy_id and wtt.taxonomy = 'author'
			where wtr.object_id = %s;",
			$post_id
		), ARRAY_A );

		// Remove these term relationships.
		foreach ( $results as $result ) {
			$deleted = $wpdb->delete(
				$wpdb->term_relationships,
				[
					'object_id'        => $result['object_id'],
					'term_taxonomy_id' => $result['term_taxonomy_id'],
				]
			);
			if ( false === $deleted ) {
				throw new \RuntimeException( sprintf( 'ERROR -- failed to delete term relationship for post/object ID %d and term taxonomy ID %d.', $result['object_id'],
					$result['term_taxonomy_id'] ) );
			}
		}

		wp_cache_flush();
	}

	/**
	 * Links a Guest Author to an existing WP User.
	 *
	 * @param int      $ga_id Guest Author ID.
	 * @param \WP_User $user  WP User.
	 */
	public function link_guest_author_to_wp_user( $ga_id, $user ) {
		// Since GAs and WP Users can't have the same login, update it if they're the same.
		$cap_user_login = get_post_meta( $ga_id, 'cap-user_login', true );
		if ( $cap_user_login == $user->user_nicename ) {
			update_post_meta( $ga_id, 'cap-user_login', 'cap-' . $cap_user_login );
		}

		// WP User mapping.
		update_post_meta( $ga_id, 'cap-linked_account', $user->user_login );
	}

	/**
	 * Links a Guest Author to an existing WP User.
	 *
	 * @param int     $ga_id Guest Author ID.
	 * @param \WPUser $user  WP User.
	 */
	public function unlink_wp_user_from_guest_author( $ga_id, $user ) {
		delete_post_meta( $ga_id, 'cap-linked_account', $user->user_login );
	}

	/**
	 * Returns the Guest Author object which has a linked WP_User with given $user_login_of_linked_wpuser account.
	 *
	 * @param int  $user_login_of_linked_wpuser user_login of linked WP_User account.
	 * @param bool $force                       Force a new query.
	 *
	 * @return ?object Guest Author object or null.
	 */
	public function get_guest_author_by_linked_wpusers_user_login( $user_login_of_linked_wpuser, $force = false ) {
		$ga = $this->coauthors_guest_authors->get_guest_author_by( 'linked_account', $user_login_of_linked_wpuser, $force );

		if ( false === $ga ) {
			return null;
		}

		$ga = ( 'guest-author' == $ga->type ) ? $ga : null;

		return $ga;
	}

	/**
	 * Returns the Guest Author object by ID (as defined by the CAP plugin).
	 *
	 * @param int $ga_id Guest Author ID.
	 *
	 * @return false|object Guest Author object.
	 */
	public function get_guest_author_by_id( $ga_id ) {
		return $this->coauthors_guest_authors->get_guest_author_by( 'ID', $ga_id );
	}

	/**
	 * Gets the Guest Author object by `user_login` (as defined by the CAP plugin).
	 *
	 * @param int $ga_user_login Guest Author ID.
	 *
	 * @return false|object Guest Author object.
	 */
	public function get_guest_author_by_user_login( $ga_user_login ) {
		return $this->coauthors_guest_authors->get_guest_author_by( 'user_login', $ga_user_login );
	}

	/**
	 * This returns a GA object if that GA's user_login matches the provided user_login, or if that GA's WP_User linked_account
	 * matches the provided user_login.
	 *
	 * Also see self::get_guest_author_by_user_login().
	 *
	 * P.S. CAP can sometimes be a bit complicated.
	 *
	 * @param int $user_login user_login of existing GA object, or of existing WP_User object linked to a GA user.
	 *
	 * @return false|object Guest Author object which has that user_login, or has a linked WP_User with that user_login.
	 */
	public function get_guest_author_by_user_login_including_linked_account_login( $user_login ) {
		$ga = $this->coauthors_plus->get_coauthor_by( 'user_login', $user_login, true );
		$ga = ( 'guest-author' == $ga->type ) ? $ga : false;

		return $ga;
	}

	/**
	 * Gets the Guest Author object by `email` (as defined by the CAP plugin).
	 *
	 * @param string $ga_email Guest Author email.
	 *
	 * @return false|object Guest Author object.
	 */
	public function get_guest_author_by_email( string $ga_email ) {
		return $this->coauthors_guest_authors->get_guest_author_by( 'user_email', $ga_email );
	}

	/**
	 * Gets the Guest Author object by `display_name` (as defined by the CAP plugin).
	 * This search is case insensitive.
	 *
	 * @param string $display_name Guest Author ID.
	 *
	 * @return null|object|array Null, a single Guest Author object, or an array of multiple Guest Author objects.
	 */
	public function get_guest_author_by_display_name( $display_name ) {

		/**
		 * These two don't work as expected:
		 *
		 *      return $this->coauthors_guest_authors->get_guest_author_by( 'display_name', $display_name );
		 *
		 *      return $this->coauthors_guest_authors->get_guest_author_by( 'post_title', $display_name );
		 */

		// Manually querying ID from DB.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_ids_results = $wpdb->get_results( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = 'guest-author';", $display_name ), ARRAY_A );

		$gas = [];
		foreach ( $post_ids_results as $post_id_result ) {
			$gas[] = $this->get_guest_author_by_id( $post_id_result['ID'] );
		}

		if ( 1 === count( $post_ids_results ) ) {
			return $gas[0];
		}

		if ( 0 === count( $gas ) ) {
			return null;
		}

		return $gas;


		/**
		 * Another possible approach, get using user_login, since this class' method self::create_guest_author just sanitizes 'display_name' to get 'user_login'
		 *      $user_login = sanitize_title( $display_name );
		 *      return $this->get_guest_author_by_user_login( $user_login );
		 */
	}

	/**
	 * Gets Guest Author's avatar attachment ID.
	 *
	 * @param int $ga_id GA ID.
	 *
	 * @return int|null Attachment ID or null.
	 */
	public function get_guest_authors_avatar_attachment_id( int $ga_id ) {
		$_thumbnail_id = get_post_meta( $ga_id, '_thumbnail_id', true );
		$attachment_id = is_numeric( $_thumbnail_id ) ? (int) $_thumbnail_id : null;

		return $attachment_id;
	}

	/**
	 * Convenience function to tell if a user has a Guest Author record.
	 *
	 * @param WP_User|int $wp_user ID of the User or a WP_User object.
	 *
	 * @return bool
	 */
	public function is_user_a_guest_author( $wp_user ) {
		$wp_user = $this->validate_wp_user_object( $wp_user );

		if ( false === $wp_user ) {
			return false;
		}

		// Get the GA by using the user login.
		$guest_author = $this->get_guest_author_by_user_login( $wp_user->data->user_login );

		return ! is_bool( $guest_author );
	}

	/**
	 * Gets the corresponding Guest Author for a WP User, creating it if necessary.
	 *
	 * @param WP_User|int $wp_user ID of the User or a WP_User object.
	 *
	 * @return false|object Guest author object.
	 */
	public function get_or_create_guest_author_from_user( $wp_user ) {
		$wp_user = $this->validate_wp_user_object( $wp_user );

		if ( false === $wp_user ) {
			return false;
		}

		// Get the GA by using the user login.
		$guest_author = $this->get_guest_author_by_user_login( $wp_user->data->user_login );
		if ( ! $guest_author ) {
			// Doesn't exist, let's create it!
			$guest_author = $this->create_guest_author_from_wp_user( $wp_user->ID );
		}

		return $guest_author;
	}

	/**
	 * Gets Post's Guest Authors, leaving out WP User authors.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array Array of Guest Author objects.
	 */
	public function get_guest_authors_for_post( $post_id ) {
		$guest_authors = [];

		$coauthors = get_coauthors( $post_id );

		// Also returns \WP_User type authors. Let's filter those out.
		foreach ( $coauthors as $coauthor ) {
			if ( $coauthor instanceof \WP_User ) {
				continue;
			}

			$guest_authors[] = $coauthor;
		}

		return $guest_authors;
	}

	/**
	 * Gets all Post authors, both Guest Authors and WP User authors.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return mixed|void
	 */
	public function get_all_authors_for_post( $post_id ) {
		$coauthors = get_coauthors( $post_id );

		return $coauthors;
	}

	/**
	 * Returns a list of GA IDs assigned to Post.
	 * It works off of self::get_guest_authors_for_post() and just filters the GA IDs.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array List of GA IDs.
	 */
	public function get_posts_existing_ga_ids( $post_id ) {
		$existing_guest_author_ids = [];

		$existing_guest_authors = $this->get_guest_authors_for_post( $post_id );
		foreach ( $existing_guest_authors as $existing_guest_author ) {
			if ( 'guest-author' == $existing_guest_author->type ) {
				$existing_guest_author_ids[] = $existing_guest_author->ID;
			}
		}

		return $existing_guest_author_ids;
	}

	/**
	 * Creates a Guest Author user from an existing WP user.
	 *
	 * @param int $user_id WP User ID.
	 *
	 * @return int|\WP_Error Guest Author ID.
	 */
	public function create_guest_author_from_wp_user( $user_id ) {
		return $this->coauthors_guest_authors->create_guest_author_from_user_id( $user_id );
	}

	/**
	 * Gets all post IDs authored by a WP User. Returns all post IDs where th WP_User is:
	 *   - either the post's single author,
	 *   - or one of post's co-authors,
	 *   - and if the WP_user is linked to a Guest Author, this also returns all posts authored by that Guest Author.
	 *
	 * Ideally we would use get_posts() with 'author__in' param for this (see https://wordpress.org/support/topic/query-all-posts-of-author-even-if-he-she-is-co-author/),
	 * but we have witnessed some bugs where it returns postIDs not authored by the WP_User, so we're using WP_Query instead.
	 *
	 * More detailed explanation of how this works follows.
	 *
	 * -------------------------------------------------
	 *
	 * Post authorship can be stored in two different places:
	 *   1. If CAP plugin was not used (active) on site when a WP_User was created and assigned as post author,
	 *      the classic `wp_posts`.`post_author` is what determines the post authorship.
	 *   2. But if CAP plugin was used (active) on site when a WP User was created and assigned to post as author,
	 *      `wp_term_relationships` will take over as the thing that determines post authors, and `wp_posts`.`post_author`
	 *      stops being used in authorship data.
	 *
	 * It is important to note that both classic WP_Users objects and Guest Authors objects can be used as co-authors (multiple authors).
	 * A term is always used to represent a Guest Author object, but a WP User may or may not get a term assigned to it if it's used as one of co-authors.
	 *
	 * A WP_user may or may not have a corresponding term row which gets created by CAP. If WP_User was assigned as author before CAP was active,
	 * there will be no term (and `terms_relationships`) row. But if CAP was active while any (co)author was/were assigned to a post,
	 * a terms row will be created for this WP_User, and `terms_relationships` will be used to designate post (co)authorship.
	 *
	 * It is possible that both these data points (`post_author` and `term_relationships`) are used at the same time on site for different
	 * posts to signify authorship -- old historic posts might have no `term_relationship` entries, and the `post_author` column will be used
	 * as author, while new posts where CAP was used will be using term_relationships for authors.
	 *
	 * Lastly, let's explain how linked/mapped accounts work. If a WP_User is linked to a Guest Author:
	 *   1. The GA `wp_post` object will get a `wp_postmeta`.`meta_key` = 'cap-linked_account' and `meta_value` == `wp_users`.`user_login`.
	 *   2. When such a GA is assigned as co-author, the WP_User's term will instead be assigned as co-author via term_relationships,
	 *      not the GA's term.
	 * This kind of completely skips using GA as co-author from the data point of view and instead just uses the WP_User as co-author.
	 *
	 * @param int    $wpuser_id   WP User ID.
	 * @param string $post_type   Post type.
	 * @param array  $post_status Post statuses.
	 *
	 * @return array List of post IDs authored by WP_User.
	 */
	public function get_all_posts_by_wp_user( int $wpuser_id, string $post_type = 'post', array $post_status = [
		'publish',
		'draft',
		'pending',
		'future',
		'private',
		'inherit',
		'trash',
	] ): array {
		global $wpdb;

		// Posts authored by this WP User.
		$post_ids = [];

		// Get the wp_users row.
		// phpcs:ignore -- usage of users table is needed.
		$wp_user_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->users WHERE ID = %d", $wpuser_id ), ARRAY_A );

		// Let's check if this WP_User has a term/term_taxonomy_id assigned to it.
		$term_id          = $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM $wpdb->terms WHERE name = %s", $wp_user_row['user_login'] ) );
		$term_taxonomy_id = null;
		if ( $term_id ) {
			$term_taxonomy_id = $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM $wpdb->term_taxonomy WHERE term_id = %d", $term_id ) );
		}

		// 1 -- get post IDs where this WP_User is a co-author as managed by CAP (via `wp_term_relationships`).
		$post_status_placeholders = implode( ',', array_fill( 0, count( $post_status ), '%s' ) );
		// phpcs:disable -- $wpdb->prepare is used and all params are prepared.
		$post_ids__as_coauthor = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT object_id
				FROM $wpdb->term_relationships wtr
				JOIN $wpdb->posts wp ON wp.ID = wtr.object_id
				WHERE wtr.term_taxonomy_id = %d
				AND wp.post_type = %s
				AND wp.post_status IN ($post_status_placeholders)",
				array_merge(
					[ $term_taxonomy_id ],
					[ $post_type ],
					$post_status
				)
			)
		);
		// phpcs:enable

		// 2 -- get all post IDs where `wp_posts`.`post_author` == this $wpuser_id.
		// phpcs:disable -- $wpdb->prepare is used and all params are prepared.
		$post_ids__as_post_author = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID
				FROM $wpdb->posts
				WHERE post_author = %d
				AND post_type = %s
				AND post_status IN ($post_status_placeholders)",
				array_merge(
					[ $wpuser_id ],
					[ $post_type ],
					$post_status,
				)
			)
		);
		// phpcs:enable

		// 3 -- now we must also check whether CAP has assigned any other co-authors to $post_ids__as_post_author
		// and exclude those IDs from our results if it has, because existing co-authors will override usage of `wp_posts`.`post_author`.
		foreach ( $post_ids__as_post_author as $key_post_id__as_post_author => $post_id__as_post_author ) {

			// Get all co-authors -- both WP_Users and Guest Authors types.
			$authors = $this->get_all_authors_for_post( $post_id__as_post_author );

			// Is our WP_User one of the co-authors for this post?
			$wp_author_is_one_of_coauthors = false;
			foreach ( $authors as $author ) {
				if ( 'stdClass' === $author::class ) {
					// This is a Guest Author. Doing nothing, just noting that a GA is one of the co-authors here.
					$do_nothing = true;
				} elseif ( 'WP_User' === $author::class ) {
					if ( $author->ID === $wpuser_id ) {
						$wp_author_is_one_of_coauthors = true;
					}
				}
			}

			// If coauthors were assigned to this post, and our WP_User is NOT one of them, then we must not include this post ID in our results.
			if ( ! empty( $authors ) && ( false === $wp_author_is_one_of_coauthors ) ) {
				unset( $post_ids__as_post_author[ $key_post_id__as_post_author ] );
				$post_ids__as_post_author = array_values( $post_ids__as_post_author );
			}
		}

		// And the final post IDs are both these arrays merged -- $post_ids__as_post_author and $post_ids__as_coauthor.
		$post_ids = array_unique( array_merge( $post_ids__as_post_author, $post_ids__as_coauthor ) );

		return $post_ids;
	}

	/**
	 * This function will facilitate obtaining all posts by Guest Author.
	 *
	 * @param int  $ga_id            Guest Author ID (Post ID).
	 * @param bool $get_post_objects Flag which determines whether to return array of Post IDs, or Post Objects.
	 *
	 * @return int[]|WP_Post[] Array of Post IDs if $get_post_objects is false, or Post Objects if $get_post_objects is true.
	 */
	public function get_all_posts_by_guest_author( int $ga_id, bool $get_post_objects = false ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$records = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
	                object_id
				FROM $wpdb->term_relationships
				WHERE term_taxonomy_id = (
				    SELECT
				           term_taxonomy_id
				    FROM $wpdb->term_relationships
				    WHERE object_id = %d )",
				$ga_id
			)
		);

		$post_ids = array_map( fn( $row ) => (int) $row->object_id, $records );
		$post_ids = array_filter( $post_ids, fn( $post_id ) => $post_id !== $ga_id );

		if ( ! empty( $post_ids ) ) {
			if ( ! $get_post_objects ) {
				return $post_ids;
			}

			return get_posts(
				[
					'numberposts' => -1,
					'post__in'    => $post_ids,
				]
			);
		}

		return [];
	}

	/**
	 * Delete a guest author. This function is just a wrapper for \CoAuthors_Guest_Authors::delete.
	 *
	 * @param int    $id          The ID for the guest author profile.
	 * @param string $reassign_to User login value for the co-author to reassign posts to.
	 *
	 * @return bool|WP_Error $success True on success, WP_Error on a failure.
	 */
	public function delete_ga( $id, $reassign_to = false ) {
		if ( false !== filter_var( $reassign_to, FILTER_VALIDATE_EMAIL ) ) {
			// This means $reasign_to is an email, so let's sanitize it.
			$reassign_to = sanitize_title( $reassign_to );
		}

		return $this->coauthors_guest_authors->delete( $id, $reassign_to );
	}

	/**
	 * Gets all guest author objects.
	 *
	 * @return array Array of GA objects.
	 */
	public function get_all_gas() {
		global $wpdb;

		// This query was taken directly from \CoAuthors_Guest_Authors::get_guest_author_by.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_ids = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type = 'guest-author';" );

		$all_gas = [];
		foreach ( $post_ids as $post_id ) {
			$all_gas[] = $this->coauthors_guest_authors->get_guest_author_by( 'ID', $post_id );
		}

		return $all_gas;
	}

	/**
	 * Convenience function to help validate a WP User object. If passed a User ID,
	 * it will convert it to a WP User object.
	 *
	 * @param \WP_User|int $wp_user WP User object or ID.
	 *
	 * @return false|mixed|\WP_User
	 */
	private function validate_wp_user_object( $wp_user ) {
		// Convert IDs to WP User objects.
		if ( is_int( $wp_user ) ) {
			$wp_user = get_user_by( 'id', $wp_user );
		}

		// Make sure it's a valid user.
		if ( ! is_a( $wp_user, 'WP_User' ) ) {
			return false;
		}

		return $wp_user;
	}
}
