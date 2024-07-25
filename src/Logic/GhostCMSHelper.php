<?php
/**
 * GhostCMS Migration Logic.
 *
 * @link: https://ghost.org/
 * 
 * @package newspack-migration-tools
 */

namespace Newspack\MigrationTools\Logic;

// use Newspack\MigrationTools\Log\CliLogger;
use WP_Error;

// use NewspackCustomContentMigrator\Logic\Attachments as AttachmentsLogic;
// use NewspackCustomContentMigrator\Logic\CoAuthorPlus as CoAuthorPlusLogic;
// use NewspackCustomContentMigrator\Utils\Logger;
// use WP_CLI;
// use WP_Error;


/**
 * GhostCMS Helper.
 */
class GhostCMSHelper {

	/**
	 * AttachmentsLogic
	 * 
	 * @var AttachmentsLogic 
	 */
	private $attachments_logic;

	/**
	 * Lookup to convert json authors to wp objects (WP Users and/or CAP GAs).
	 * 
	 * Note: json author_id key may exist, but if json author (user) visibility was not public, value will be 0
	 *
	 * @var array $authors_to_wp_objects
	 */
	private array $authors_to_wp_objects;

	/**
	 * CoAuthorPlusLogic
	 * 
	 * @var CoAuthorPlusLogic 
	 */
	private $coauthorsplus_logic;

	/**
	 * Ghost URL for image downloads.
	 *
	 * @var string ghost_url
	 */
	private string $ghost_url;

	/**
	 * Instance
	 * 
	 * @var null|InterfaceCommand Instance.
	 */
	private static $instance = null;

	/**
	 * JSON from file
	 *
	 * @var object $json
	 */
	private object $json;

	/**
	 * Log (file path)
	 *
	 * @var string $log
	 */
	private string $log;

	/**
	 * Logger
	 * 
	 * @var Logger
	 */
	private $logger;

	/**
	 * Lookup to convert json tags to wp categories.
	 * 
	 * Note: json tag_id key may exist, but if tag visibility was not public, value will be 0
	 *
	 * @var array $tags_to_categories
	 */
	private array $tags_to_categories;

	/**
	 * Constructor.
	 */
	private function __construct() {
		// $this->attachments_logic   = new AttachmentsLogic();
		// $this->coauthorsplus_logic = new CoAuthorPlusLogic();
		// $this->logger              = new Logger();
	}

	/**
	 * Singleton get_instance().
	 *
	 * @return InterfaceCommand|null
	 */
	public static function get_instance() {
		$class = get_called_class();
		if ( null === self::$instance ) {
			self::$instance = new $class();
		}

		return self::$instance;
	}

	/**
	 * Import GhostCMS Content from JSON file.
	 * 
	 * @param array $pos_args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function ghostcms_import( array $pos_args, array $assoc_args ): void {

		echo "bye";
		return;
		

		global $wpdb;
		
		// Plugin dependencies.

		if ( ! $this->coauthorsplus_logic->validate_co_authors_plus_dependencies() ) {
			WP_CLI::error( 'Co-Authors Plus plugin not found. Install and activate it before using this command.' );
		}

		// Argument parsing.

		// --created-after.
		$created_after = null;
		if ( isset( $assoc_args['created-after'] ) ) {
			$created_after = strtotime( $assoc_args['created-after'] );
			if ( false === $created_after ) {
				WP_CLI::error( '--created-after date was not parseable by strtotime().' );
			}
		}

		// --default-user-id.

		if ( ! isset( $assoc_args['default-user-id'] ) || ! is_numeric( $assoc_args['default-user-id'] ) ) {
			WP_CLI::error( 'Default user id must be integer.' );
		}

		$default_user = get_user_by( 'ID', $assoc_args['default-user-id'] );

		if ( ! is_a( $default_user, 'WP_User' ) ) {
			WP_CLI::error( 'Default user id does not match a wp user.' );
		}

		if ( ! $default_user->has_cap( 'publish_posts' ) ) {
			WP_CLI::error( 'Default user found, but does not have publish posts capability.' );
		}
		
		// --ghost-url.

		if ( ! isset( $assoc_args['ghost-url'] ) || ! preg_match( '#^https?://[^/]+/?$#i', $assoc_args['ghost-url'] ) ) {
			WP_CLI::error( 'Ghost URL does not match regex: ^https?://[^/]+/?$' );
		}

		$this->ghost_url = preg_replace( '#/$#', '', $assoc_args['ghost-url'] );

		// --json-file.

		if ( ! isset( $assoc_args['json-file'] ) || ! file_exists( $assoc_args['json-file'] ) ) {
			WP_CLI::error( 'JSON file not found.' );
		}

		$this->json = json_decode( file_get_contents( $assoc_args['json-file'] ), null, 2147483647 );
		
		if ( 0 != json_last_error() || 'No error' != json_last_error_msg() ) {
			WP_CLI::error( 'JSON file could not be parsed.' );
		}
		
		if ( empty( $this->json->db[0]->data->posts ) ) {
			WP_CLI::error( 'JSON file contained no posts.' );
		}

		// Start processing.

		$this->log = str_replace( __NAMESPACE__ . '\\', '', __CLASS__ ) . '_' . __FUNCTION__ . '.log';

		$this->logger->log( $this->log, 'Doing migration.' );
		$this->logger->log( $this->log, '--json-file: ' . $assoc_args['json-file'] );
		$this->logger->log( $this->log, '--ghost-url: ' . $this->ghost_url );
		$this->logger->log( $this->log, '--default-user-id: ' . $default_user->ID );
		
		if ( $created_after ) {
			// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			$this->logger->log( $this->log, '--created-after: ' . date( 'Y-m-d H:i:s', $created_after ) );
		}
		
		// Insert posts.
		foreach ( $this->json->db[0]->data->posts as $json_post ) {

			$this->logger->log( $this->log, '---- json id: ' . $json_post->id );
			$this->logger->log( $this->log, 'Title/Slug: ' . $json_post->title . ' / ' . $json_post->slug );
			$this->logger->log( $this->log, 'Created/Published: ' . $json_post->created_at . ' / ' . $json_post->published_at );

			// Date cut-off.
			if ( $created_after && strtotime( $json_post->created_at ) <= $created_after ) {

				$this->logger->log( $this->log, 'Created before cut-off date.', $this->logger::WARNING );
				continue;

			}
			
			// Check for skips, log, and continue.
			$skip_reason = $this->skip( $json_post );
			if ( ! empty( $skip_reason ) ) {
			
				$this->logger->log( $this->log, 'Skip JSON post (review by hand -skips.log): ' . $skip_reason, $this->logger::WARNING );

				// Save to skips file, and do not write to console.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
				$this->logger->log( $this->log . '-skips.log', json_encode( array( $skip_reason, $json_post ) ), false );

				continue;

			}

			// Post.
			$args = array(
				'post_author'  => $default_user->ID,
				'post_content' => str_replace( '__GHOST_URL__', $this->ghost_url, $json_post->html ),
				'post_date'    => $json_post->published_at,
				'post_excerpt' => $json_post->custom_excerpt ?? '',
				'post_name'    => $json_post->slug,
				'post_status'  => 'publish',
				'post_title'   => $json_post->title,
			);

			$wp_post_id = wp_insert_post( $args, true );

			if ( is_wp_error( $wp_post_id ) || ! is_numeric( $wp_post_id ) || ! ( $wp_post_id > 0 ) ) {
				$this->logger->log( $this->log, 'Could not insert post.', $this->logger::ERROR, false );
				if ( is_wp_error( $wp_post_id ) ) {
					$this->logger->log( $this->log, 'Insert Post Error: ' . $wp_post_id->get_error_message(), $this->logger::ERROR, false );
				}
				continue;
			}

			$this->logger->log( $this->log, 'Inserted new post: ' . $wp_post_id );

			// Post meta.
			update_post_meta( $wp_post_id, 'newspack_ghostcms_id', $json_post->id );
			update_post_meta( $wp_post_id, 'newspack_ghostcms_uuid', $json_post->uuid );
			update_post_meta( $wp_post_id, 'newspack_ghostcms_slug', $json_post->slug );
			
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			update_post_meta( $wp_post_id, 'newspack_ghostcms_checksum', md5( json_encode( $json_post ) ) );            

			// Featured image (with alt and caption).
			// Note: json value does not contain "d": feature(d)_image.
			if ( empty( $json_post->feature_image ) ) {
				$this->logger->log( $this->log, 'No featured image.' );
			} else {
				$this->set_post_featured_image( $wp_post_id, $json_post->id, $json_post->feature_image );
			}

			// Post authors to WP Users/CAP GAs.
			$this->set_post_authors( $wp_post_id, $json_post->id );

			// Post tags to categories.
			$this->set_post_tags_to_categories( $wp_post_id, $json_post->id );

		}

		$this->logger->log( $this->log, 'Done.', $this->logger::SUCCESS );
	}

	/**
	 * Get JSON author (user) object from data array.
	 *
	 * @param string $json_author_user_id JSON author id.
	 * @return null|Object
	 */
	private function get_json_author_user_by_id( string $json_author_user_id ): ?object {

		if ( empty( $this->json->db[0]->data->users ) ) {
			return null;
		}

		foreach ( $this->json->db[0]->data->users as $json_author_user ) {

			if ( $json_author_user->id == $json_author_user_id ) {
				return $json_author_user;
			}       
		} 

		return null;
	}

	/**
	 * Get JSON meta object from data array.
	 *
	 * @param string $json_post_id JSON post id.
	 * @return null|Object
	 */
	private function get_json_post_meta( string $json_post_id ): ?object {

		if ( empty( $this->json->db[0]->data->posts_meta ) ) {
			return null;
		}

		foreach ( $this->json->db[0]->data->posts_meta as $json_post_meta ) {

			if ( $json_post_meta->post_id == $json_post_id ) {
				return $json_post_meta;
			}       
		} 

		return null;
	}

	/**
	 * Get JSON tag object from data array.
	 *
	 * @param string $json_tag_id JSON tag id.
	 * @return null|Object
	 */
	private function get_json_tag_by_id( string $json_tag_id ): ?object {

		if ( empty( $this->json->db[0]->data->tags ) ) {
			return null;
		}

		foreach ( $this->json->db[0]->data->tags as $json_tag ) {

			if ( $json_tag->id == $json_tag_id ) {
				return $json_tag;
			}       
		} 

		return null;
	}

	/**
	 * Get attachment (based on URL) from database else import external file from URL
	 *
	 * @param string $path URL.
	 * @param string $title URL or title string.
	 * @param string $caption Image caption (optional).
	 * @param string $description Image desc (optional).
	 * @param string $alt Image alt (optional).
	 * @param int    $post_id Post ID (optional).
	 * @return int|WP_Error $attachment_id
	 */
	private function get_or_import_url( string $path, string $title, string $caption = null, string $description = null, string $alt = null, int $post_id = 0 ): int|WP_Error {

		global $wpdb;

		// have to check if alredy exists so that multiple calls do not download() files already inserted.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' and post_title = %s",
				$title 
			)
		);

		if ( is_numeric( $attachment_id ) && $attachment_id > 0 ) {
			
			$this->logger->log( $this->log, 'Image already exists: ' . $attachment_id );

			return $attachment_id;

		}

		// this function will check if existing, but only after re-downloading.
		return $this->attachments_logic->import_external_file( $path, $title, $caption, $description, $alt, $post_id );
	}

	/**
	 * Insert JSON author (user)
	 *
	 * @param object $json_author_user json author (user) object.
	 * @return int|object|WP_User Return of integer 0 means not inserted, otherwise generic "Guest Author" object or WP_User is returned.
	 */
	private function insert_json_author_user( object $json_author_user ): mixed {

		// Must have visibility property with value of 'public'.
		if ( empty( $json_author_user->visibility ) || 'public' != $json_author_user->visibility ) {

			$this->logger->log( $this->log, 'JSON user not visible. Could not be inserted.', $this->logger::WARNING );

			return 0;

		} 
		
		// Get existing GA if exists.
		// As of 2024-03-19 the use of 'coauthorsplus_logic->create_guest_author()' to return existing match
		// may return an error. WP Error occures if existing database GA is "Jon A. Doe" but new GA is "Jon A Doe".
		// New GA will not match on display name, but will fail on create when existing sanitized slug is found.
		// Use a more direct approach here.
		
		$user_login = sanitize_title( urldecode( $json_author_user->name ) );

		$this->logger->log( $this->log, 'Get or insert author: ' . $user_login );

		$ga = $this->coauthorsplus_logic->get_guest_author_by_user_login( $user_login );

		// GA Exists.
		if ( is_object( $ga ) ) {

			$this->logger->log( $this->log, 'Found existing GA.' );

			// Save old slug for possible redirect.
			update_post_meta( $ga->ID, 'newspack_ghostcms_slug', $json_author_user->slug );

			return $ga;
		
		}

		// Check for WP user with admin access.
		$user_query = new \WP_User_Query(
			array( 
				'login'    => $user_login,
				'role__in' => array( 'Administrator', 'Editor', 'Author', 'Contributor' ),
			)
		);

		foreach ( $user_query->get_results() as $wp_user ) {

			$this->logger->log( $this->log, 'Found existing WP User.' );

			// Save old slug for possible redirect.
			update_user_meta( $wp_user->ID, 'newspack_ghostcms_slug', $json_author_user->slug );

			// Return the first user found.
			return $wp_user;

		}

		// Create a GA.
		$ga_id = $this->coauthorsplus_logic->create_guest_author( array( 'display_name' => $json_author_user->name ) );

		if ( is_wp_error( $ga_id ) || ! is_numeric( $ga_id ) || ! ( $ga_id > 0 ) ) {

			$this->logger->log( $this->log, 'GA create failed: ' . $json_author_user->name, $this->logger::WARNING );

			return 0;

		}

		$this->logger->log( $this->log, 'Created new GA.' );

		$ga = $this->coauthorsplus_logic->get_guest_author_by_id( $ga_id );
	
		// Save old slug for possible redirect.
		update_post_meta( $ga->ID, 'newspack_ghostcms_slug', $json_author_user->slug );

		return $ga;
	}

	/**
	 * Insert JSON tag as category
	 *
	 * @param object $json_tag json tag object.
	 * @return 0|int
	 */
	private function insert_json_tag_as_category( object $json_tag ): int {

		// Must have visibility property with value of 'public'.
		if ( empty( $json_tag->visibility ) || 'public' != $json_tag->visibility ) {
			
			$this->logger->log( $this->log, 'JSON tag not visible. Could not be inserted.', $this->logger::WARNING );

			return 0;

		} 
		
		// Check if category exists in db.
		// Logic from https://github.com/WordPress/wordpress-importer/blob/71bdd41a2aa2c6a0967995ee48021037b39a1097/src/class-wp-import.php#L784-L801 .
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.term_exists_term_exists
		$term_arr = term_exists( $json_tag->slug, 'category' );

		// Category does not exist.
		if ( ! $term_arr ) {

			// Insert it.
			$term_arr = wp_insert_term( $json_tag->name, 'category', array( 'slug' => $json_tag->slug ) );

			// Log and return 0 if insert failed.
			if ( is_wp_error( $term_arr ) ) {
				$this->logger->log( $this->log, 'Insert term failed (' . $json_tag->slug . ') ' . $term_arr->get_error_message(), $this->logger::WARNING );
				return 0;
			}

			$this->logger->log( $this->log, 'Inserted category term: ' . $term_arr['term_id'] );

		}
		
		// Save old slug for possible redirect.
		update_term_meta( $term_arr['term_id'], 'newspack_ghostcms_slug', $json_tag->slug );

		return $term_arr['term_id'];
	}

	/**
	 * Set post authors using JSON relationship(s).
	 *
	 * @param int    $wp_post_id wp_posts id.
	 * @param string $json_post_id json post id.
	 * @return void
	 */
	private function set_post_authors( int $wp_post_id, string $json_post_id ): void {

		if ( empty( $this->json->db[0]->data->posts_authors ) ) {
			
			$this->logger->log( $this->log, 'JSON has no post author relationships.', $this->logger::WARNING );

			return;

		}

		$wp_objects = [];

		// Each posts_authors relationship.
		foreach ( $this->json->db[0]->data->posts_authors as $json_post_author ) {
			
			// Skip if post id does not match relationship.
			if ( $json_post_author->post_id != $json_post_id ) {
				continue;
			}

			$this->logger->log( $this->log, 'Relationship found for author: ' . $json_post_author->author_id );

			// If author_id wasn't already processed.
			if ( ! isset( $this->authors_to_wp_objects[ $json_post_author->author_id ] ) ) {

				// Get the json author (user) object.
				$json_author_user = $this->get_json_author_user_by_id( $json_post_author->author_id );

				// Verify related author (user) was found in json.
				if ( empty( $json_author_user ) ) {

					$this->logger->log( $this->log, 'JSON author (user) not found: ' . $json_post_author->author_id, $this->logger::WARNING );

					continue;

				}

				// Attempt insert and save return value into lookup.
				$this->authors_to_wp_objects[ $json_post_author->author_id ] = $this->insert_json_author_user( $json_author_user );

			}

			// Verify lookup value is an object
			// A value of 0 means json author (user) did not have visibility of public.
			// In that case, don't add to return array.
			if ( is_object( $this->authors_to_wp_objects[ $json_post_author->author_id ] ) ) {
				$wp_objects[] = $this->authors_to_wp_objects[ $json_post_author->author_id ];
			}       
		} // foreach relationship

		if ( empty( $wp_objects ) ) {

			$this->logger->log( $this->log, 'No authors.' );

			return;
		
		}

		// WP Users and/or CAP GAs.
		$this->coauthorsplus_logic->assign_authors_to_post( $wp_objects, $wp_post_id );

		$this->logger->log( $this->log, 'Assigned authors (wp users and/or cap gas). Count: ' . count( $wp_objects ) );
	}

	/**
	 * Set post featured image
	 * 
	 * Note: json property does not contain "d": feature(d)_image
	 *
	 * @param int    $wp_post_id wp_posts ID.
	 * @param string $json_post_id json post id.
	 * @param string $old_image_url URL scheme with domain.
	 * @return void
	 */
	private function set_post_featured_image( int $wp_post_id, string $json_post_id, string $old_image_url ): void {

		// The old image url may already contain the domain name ( https://mywebsite.com/.../image.jpg ).
		// But if not, replace the placeholder ( __GHOST_URL__/.../image.jpg ).
		$old_image_url = preg_replace( '#^__GHOST_URL__#', $this->ghost_url, $old_image_url );

		$this->logger->log( $this->log, 'Featured image fetch url: ' . $old_image_url );

		// Get alt and caption if exists in json meta node.
		$json_meta = $this->get_json_post_meta( $json_post_id );

		$old_image_alt     = $json_meta->feature_image_alt ?? '';
		$old_image_caption = $json_meta->feature_image_caption ?? '';

		// get existing or upload new.
		$featured_image_id = $this->get_or_import_url( $old_image_url, $old_image_url, $old_image_caption, $old_image_caption, $old_image_alt, $wp_post_id );

		if ( ! is_numeric( $featured_image_id ) || ! ( $featured_image_id > 0 ) ) {
			
			$this->logger->log( $this->log, 'Featured image import failed for: ' . $old_image_url, $this->logger::WARNING );

			if ( is_wp_error( $featured_image_id ) ) {

				$this->logger->log( $this->log, 'Featured image import wp error: ' . $featured_image_id->get_error_message(), $this->logger::WARNING );

			}
			
			return;
		}

		update_post_meta( $wp_post_id, '_thumbnail_id', $featured_image_id );

		$this->logger->log( $this->log, 'Set _thumbnail_id: ' . $featured_image_id );
	}

	/**
	 * Set post tags (categories) using JSON relationship(s).
	 *
	 * @param int    $wp_post_id wp_posts ID.
	 * @param string $json_post_id json post id.
	 * @return void
	 */
	private function set_post_tags_to_categories( int $wp_post_id, string $json_post_id ): void {

		if ( empty( $this->json->db[0]->data->posts_tags ) ) {
			
			$this->logger->log( $this->log, 'JSON has no post tags (category) relationships.', $this->logger::WARNING );

			return;
		
		}

		$category_ids = [];

		// Each posts_tags relationship.
		foreach ( $this->json->db[0]->data->posts_tags as $json_post_tag ) {
			
			// Skip if post id does not match relationship.
			if ( $json_post_tag->post_id != $json_post_id ) {
				continue;
			}

			$this->logger->log( $this->log, 'Relationship found for tag: ' . $json_post_tag->tag_id );

			// If tag_id wasn't already processed.
			if ( ! isset( $this->tags_to_categories[ $json_post_tag->tag_id ] ) ) {

				// Get the json tag object.
				$json_tag = $this->get_json_tag_by_id( $json_post_tag->tag_id );

				// Verify related tag was found in json.
				if ( empty( $json_tag ) ) {
				
					$this->logger->log( $this->log, 'JSON tag not found: ' . $json_post_tag->tag_id, $this->logger::WARNING );

					continue;
				
				}

				// Attempt insert and save return value into lookup.
				$this->tags_to_categories[ $json_post_tag->tag_id ] = $this->insert_json_tag_as_category( $json_tag );

			}

			// Verify lookup value > 0
			// A value of 0 means json tag did not have visibility of public.
			// In that case, don't add to return array.
			if ( $this->tags_to_categories[ $json_post_tag->tag_id ] > 0 ) {
				$category_ids[] = $this->tags_to_categories[ $json_post_tag->tag_id ];
			}       
		} // foreach post_tag relationship

		if ( empty( $category_ids ) ) {
		
			$this->logger->log( $this->log, 'No categories.' );

			return;
		
		}
		
		wp_set_post_categories( $wp_post_id, $category_ids );

		$this->logger->log( $this->log, 'Set post categories. Count: ' . count( $category_ids ) );
	}

	/**
	 * Check if need to skip this JSON post.
	 *
	 * @param object $json_post JSON post object.
	 * @return string|null
	 */
	private function skip( object $json_post ): ?string {

		global $wpdb;

		// JSON properites.

		if ( 'post' != $json_post->type ) {
			return 'not_post';
		}
		if ( 'published' != $json_post->status ) {
			return 'not_published';
		}
		if ( 'public' != $json_post->visibility ) {
			return 'not_public';
		}

		// Empty properties.

		if ( empty( $json_post->html ) ) {
			return 'empty_html';
		}
		if ( empty( $json_post->published_at ) ) {
			return 'empty_published_at';
		}
		if ( empty( $json_post->title ) ) {
			return 'empty_title';
		}
		
		// WP Lookups.

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var(
			$wpdb->prepare( 
				"SELECT 1 FROM $wpdb->postmeta WHERE meta_key = 'newspack_ghostcms_id' AND meta_value = %s", 
				$json_post->id 
			) 
		) ) {
			return 'post_already_imported';
		}

		// Title and date already existed in WordPress. (from WXR Importer).
		if ( post_exists( $json_post->title, '', $json_post->published_at, 'post' ) ) {
			return 'post_exists_title_date';
		}

		// If post_name / slug exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var(
			$wpdb->prepare( 
				"SELECT ID FROM $wpdb->posts WHERE post_type = 'post' and post_name = %s", 
				$json_post->slug 
			) 
		) ) {
			return 'post_exists_slug';
		}
			
		return null;
	}
}


