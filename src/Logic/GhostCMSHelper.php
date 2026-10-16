<?php
/**
 * GhostCMS Migration Logic.
 *
 * @link: https://ghost.org/
 * 
 * @package newspack-migration-tools
 */

namespace Newspack\MigrationTools\Logic;

use Exception;
use Newspack\Guest_Contributor_Role;
use Newspack\MigrationTools\Logic\UsersHelper;
use Newspack\MigrationTools\Logic\GuestContributorsHelper;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use Newspack\MigrationTools\NMT;
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\Log\MultiLog;
use Newspack\MigrationTools\Util\Log\PlainLineFormatter;
use Monolog\Level;
use Psr\Log\LogLevel;
use simplehtmldom\HtmlDocument;
use UnhandledMatchError;
use WP_CLI;
use WP_Error;
use WP_User;

/**
 * GhostCMS Helper.
 */
class GhostCMSHelper {

	/**
	 * List of approved HTML custom content elements from Ghost Koenig editor which render well enough in WordPress without any transformation.
	 * These elements will be skipped by the check_imported_posts_for_custom_html_content() so they don't show up as "unfamiliar/unhandled".
	 * 
	 * Do NOT add here elements that are transformed or removed by custom content transformers during import (e.g. kg-video-container, kg-audio-card),
	 * those should be transformed during the import and no longer exist in post_content, so detecting them is important to catch any transformer bugs.
	 * 
	 * @var array ACCEPTED_KG_ELEMENTS List of elements with "kg-*" classes which are kept in post_content without any transformation/replacement.
	 *   - html_element: the HTML tag name of the element.
	 *   - kg_classes: one or more kg-* classes which identify the element.
	 */
	// phpcs:disable -- Allow custom spacing in the const array for readability, WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound.
	const ACCEPTED_KG_ELEMENTS = [
		// Bookmark elements.
		[ 'html_element' => 'a',      'kg_classes' => [ 'kg-bookmark-container' ] ],
		[ 'html_element' => 'div',    'kg_classes' => [ 'kg-bookmark-content' ] ],
		[ 'html_element' => 'div',    'kg_classes' => [ 'kg-bookmark-description' ] ],
		[ 'html_element' => 'div',    'kg_classes' => [ 'kg-bookmark-metadata' ] ],
		[ 'html_element' => 'div',    'kg_classes' => [ 'kg-bookmark-thumbnail' ] ],
		[ 'html_element' => 'div',    'kg_classes' => [ 'kg-bookmark-title' ] ],
		[ 'html_element' => 'figure', 'kg_classes' => [ 'kg-card', 'kg-bookmark-card' ] ],
		[ 'html_element' => 'img',    'kg_classes' => [ 'kg-bookmark-icon' ] ],
		[ 'html_element' => 'span',   'kg_classes' => [ 'kg-bookmark-author' ] ],
		[ 'html_element' => 'span',   'kg_classes' => [ 'kg-bookmark-publisher' ] ],
		// Image elements.
		[ 'html_element' => 'figure', 'kg_classes' => [ 'kg-card', 'kg-image-card' ] ],
		[ 'html_element' => 'figure', 'kg_classes' => [ 'kg-card', 'kg-image-card', 'kg-card-hascaption' ] ],
		[ 'html_element' => 'figure', 'kg_classes' => [ 'kg-card', 'kg-image-card', 'kg-width-full', 'kg-card-hascaption' ] ],
		[ 'html_element' => 'figure', 'kg_classes' => [ 'kg-card', 'kg-image-card', 'kg-width-wide', 'kg-card-hascaption' ] ],
		[ 'html_element' => 'img',    'kg_classes' => [ 'kg-image' ] ],
		// Video elements (some types of videos which render correctly on frontend).
		[ 'html_element' => 'figure', 'kg_classes' => [ 'kg-card', 'kg-video-card' ] ],
		[ 'html_element' => 'figure', 'kg_classes' => [ 'kg-card', 'kg-video-card', 'kg-width-regular' ] ],
		[ 'html_element' => 'figure', 'kg_classes' => [ 'kg-card', 'kg-video-card', 'kg-width-regular', 'kg-card-hascaption' ] ],
		// Embed elements.
		[ 'html_element' => 'figure', 'kg_classes' => [ 'kg-card', 'kg-embed-card' ] ],
		[ 'html_element' => 'figure', 'kg_classes' => [ 'kg-card', 'kg-embed-card', 'kg-card-hascaption' ] ],
	];
	// phpcs:enable

	/**
	 * Lookup to convert json authors to Guest Contributor user objects.
	 * 
	 * Note: json author_id key may exist, but if json author (user) visibility was not public, value will be 0
	 *
	 * @var array $authors_to_wp_users
	 */
	private array $authors_to_wp_users;

	/**
	 * Ghost URL for image downloads.
	 *
	 * @var string ghost_url
	 */
	private string $ghost_url;

	/**
	 * JSON data node (resolved from json-data-path).
	 *
	 * @var object $data
	 */
	private ?object $data = null;

	/**
	 * Log slug. If left empty, logging is disabled (test-environment friendly).
	 *
	 * @var string $log_slug
	 */
	private string $log_slug;

	/**
	 * Lookup to convert json tags to wp categories.
	 * 
	 * Note: json tag_id key may exist, but if tag visibility was not public, value will be 0
	 *
	 * @var array $tags_to_categories
	 */
	private array $tags_to_categories;

	/**
	 * Simple Local Avatars helper instance.
	 *
	 * @var ?SimpleLocalAvatars $simple_local_avatars
	 */
	private ?SimpleLocalAvatars $simple_local_avatars = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Nothing for now.
	}

	/**
	 * Set log slug.
	 * 
	 * @param string $log_slug Log slug.
	 */
	public function set_log_slug( string $log_slug ): void {
		$this->log_slug = $log_slug;
	}

	/**
	 * Import GhostCMS Content from JSON file.
	 * 
	 * @param array  $pos_args Positional arguments.
	 * @param array  $assoc_args Associative arguments.
	 * @param string $log_slug Slug for logging.
	 */
	public function ghostcms_import( array $pos_args, array $assoc_args, string $log_slug ): void {

		// Set log slug from args.
		$this->set_log_slug( $log_slug );

		// Validate dependencies.
		$validate_cap = UsersHelper::validate_co_authors_plus();
		if ( true !== $validate_cap ) {
			$this->log( 'CoAuthorsPlus plugin must be active before running this command: ' . ( is_wp_error( $validate_cap ) ? $validate_cap->get_error_message() : 'Unknown error' ), LogLevel::ERROR, true );
		}
		if ( ! GuestContributorsHelper::validate_newspack_plugin() ) {
			$this->log( 'Newspack Plugin\'s Guest Contributors feature is required.', LogLevel::ERROR, true );
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active( 'simple-local-avatars/simple-local-avatars.php' ) ) {
			$this->log( 'Simple Local Avatars plugin must be active for avatar imports.', LogLevel::ERROR, true );
		}

		// Argument parsing.

		// --visibility-csv, default is 'public'.
		$visibilities_to_import = [ 'public' ];
		if ( isset( $assoc_args['visibility-csv'] ) ) {
			$visibilities_to_import = explode( ',', $assoc_args['visibility-csv'] );
		}

		// --created-after.
		$created_after = null;
		if ( isset( $assoc_args['created-after'] ) ) {
			$created_after = strtotime( $assoc_args['created-after'] );
			if ( false === $created_after ) {
				$this->log( '--created-after date was not parseable by strtotime().', LogLevel::ERROR, true );
			}
		}

		// --default-user-id.

		if ( ! isset( $assoc_args['default-user-id'] ) || ! is_numeric( $assoc_args['default-user-id'] ) ) {
			$this->log( 'Default user id must be integer.', LogLevel::ERROR, true );
		}

		$default_user = get_user_by( 'ID', $assoc_args['default-user-id'] );

		if ( ! is_a( $default_user, 'WP_User' ) ) {
			$this->log( 'Default user id does not match a wp user.', LogLevel::ERROR, true );
		}

		if ( ! $default_user->has_cap( 'publish_posts' ) ) {
			$this->log( 'Default user found, but does not have publish posts capability.', LogLevel::ERROR, true );
		}
		
		// --ghost-url.

		if ( ! isset( $assoc_args['ghost-url'] ) || ! preg_match( '#^https?://[^/]+/?$#i', $assoc_args['ghost-url'] ) ) {
			$this->log( 'Ghost URL does not match regex: ^https?://[^/]+/?$', LogLevel::ERROR, true );
		}

		$this->ghost_url = preg_replace( '#/$#', '', $assoc_args['ghost-url'] );

		// --json-file.

		if ( ! isset( $assoc_args['json-file'] ) || ! file_exists( $assoc_args['json-file'] ) ) {
			$this->log( 'JSON file not found.', LogLevel::ERROR, true );
		}

		$json = json_decode( file_get_contents( $assoc_args['json-file'] ), null, 2147483647 );

		if ( ! is_object( $json ) || 0 != json_last_error() || 'No error' != json_last_error_msg() ) {
			$this->log( 'JSON file could not be parsed.', LogLevel::ERROR, true );
		}

		// --json-data-path (optional, defaults to .db[0].data).

		$json_data_path = $assoc_args['json-data-path'] ?? '.db[0].data';
		$this->data     = $this->get_json_data_from_path( $json_data_path, $json );

		if ( null === $this->data ) {
			$this->log( sprintf( 'JSON data path "%s" could not be resolved.', $json_data_path ), LogLevel::ERROR, true );
		}

		if ( empty( $this->data->posts ) ) {
			$this->log( sprintf( 'JSON file contained no posts at data path: %s', $json_data_path ), LogLevel::ERROR, true );
		}

		// Start processing.
		$this->log( 'Doing migration.' );
		$this->log( '--json-file: ' . $assoc_args['json-file'] );
		$this->log( '--json-data-path: ' . $json_data_path );
		$this->log( '--ghost-url: ' . $this->ghost_url );
		$this->log( '--default-user-id: ' . $default_user->ID );
		
		if ( $created_after ) {
			// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			$this->log( '--created-after: ' . date( 'Y-m-d H:i:s', $created_after ) );
		}

		// Check if there are any additional post visibility values in JSON data, besides the ones which are selected for import.
		$visibilities_existing         = $this->get_visibility_values( $this->data );
		$are_all_visibilities_selected = empty( array_diff( $visibilities_existing, $visibilities_to_import ) );
		if ( false === $are_all_visibilities_selected ) {
			$this->log(
				sprintf(
					'There are %d existing `visibility` values found in JSON posts: %s. Only the posts with visibility value(s) %s will be imported.',
					count( $visibilities_existing ),
					'`' . implode( '`, `', $visibilities_existing ) . '`',
					'`' . implode( '`, `', $visibilities_to_import ) . '`'
				),
				LogLevel::WARNING
			);
			WP_CLI::confirm( 'Continue with the import (y), or stop here (n) and set the `--visibility-csv` argument to the target values?' );
		}

		// Insert posts.
		foreach ( $this->data->posts as $json_post ) {

			$this->log( '---- json id: ' . $json_post->id );
			$this->log( 'Title/Slug: ' . $json_post->title . ' / ' . $json_post->slug );
			$this->log( 'Created/Published: ' . $json_post->created_at . ' / ' . $json_post->published_at );

			// Date cut-off.
			if ( $created_after && strtotime( $json_post->created_at ) <= $created_after ) {

				$this->log( 'Created before cut-off date.', LogLevel::WARNING );
				continue;

			}
			
			// Check for skips, log, and continue.
			$skip_reason = $this->skip( $json_post, $visibilities_to_import );
			if ( ! empty( $skip_reason ) ) {
			
				$this->log( 'Skip JSON post (review by hand -skips.log): ' . $skip_reason, LogLevel::NOTICE );

				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
				FileLog::get_logger( $this->log_slug . '-skips' )->notice( json_encode( array( $skip_reason, $json_post ) ) );

				continue;

			}

			/**
			 * Post content processing.
			 */
			$post_content = str_replace( '__GHOST_URL__', $this->ghost_url, $json_post->html );
			// Replace various HTML elements to compatible HTML.
			$post_content = $this->replace_video_embeds( $post_content, $json_post->id );
			$post_content = $this->replace_audio_embeds( $post_content, $json_post->id );
			$post_content = $this->replace_blockquotes( $post_content, $json_post->id );
			$post_content = $this->replace_callout_cards( $post_content, $json_post->id );
			$post_content = $this->replace_galleries( $post_content, $json_post->id );

			// Post.
			$args = array(
				'post_author'  => $default_user->ID,
				'post_content' => $post_content,
				'post_date'    => $json_post->published_at,
				'post_excerpt' => $json_post->custom_excerpt ?? '',
				'post_name'    => $json_post->slug,
				'post_status'  => 'publish',
				'post_title'   => $json_post->title,
			);

			$wp_post_id = wp_insert_post( $args, true );

			if ( is_wp_error( $wp_post_id ) || ! is_numeric( $wp_post_id ) || ! ( $wp_post_id > 0 ) ) {
				$this->log( 'Could not insert post.', LogLevel::ERROR, false );
				if ( is_wp_error( $wp_post_id ) ) {
					$this->log( 'Insert Post Error: ' . $wp_post_id->get_error_message(), LogLevel::ERROR, false );
				}
				continue;
			}

			$this->log( 'Inserted new post: ' . $wp_post_id );

			// Post meta.
			update_post_meta( $wp_post_id, 'newspack_ghostcms_id', $json_post->id );
			update_post_meta( $wp_post_id, 'newspack_ghostcms_uuid', $json_post->uuid );
			update_post_meta( $wp_post_id, 'newspack_ghostcms_slug', $json_post->slug );
			if ( ! empty( $json_post->custom_excerpt ) ) {
				update_post_meta( $wp_post_id, 'newspack_post_subtitle', $json_post->custom_excerpt );
			}
			
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			update_post_meta( $wp_post_id, 'newspack_ghostcms_checksum', md5( json_encode( $json_post ) ) );            

			// Featured image (with alt and caption).
			// Note: json value does not contain "d": feature(d)_image.
			if ( empty( $json_post->feature_image ) ) {
				$this->log( 'No featured image.' );
			} else {
				$this->set_post_featured_image( $wp_post_id, $json_post->id, $json_post->feature_image );
			}

			// Post authors to WP Users/CAP GAs.
			$this->set_post_authors( $wp_post_id, $json_post->id );

			// Post tags to categories.
			$this->set_post_tags_to_categories( $wp_post_id, $json_post->id );

		}

		$this->log( 'Done importing posts from Ghost.', LogLevel::INFO );

		// Rewrite author URLs in content if any nicenames changed during import.
		$this->rewrite_ghost_author_urls_in_content( $this->log_slug, $this->ghost_url );

		// Run command to check for custom Ghost HTML content.
		$this->check_imported_posts_for_custom_html_content( $this->log_slug );
	}

	/**
	 * Check imported posts for custom Ghost Koenig editor HTML content, by scanning all HTML elements with kg-* classes.
	 * 
	 * @param string $log_slug The logger slug.
	 */
	public function check_imported_posts_for_custom_html_content( string $log_slug ): void {
		global $wpdb;

		// Init logger usage in this class.
		$this->set_log_slug( $log_slug );

		// Setup a logger. Be sure to use NMT's FileLog Util so that this log file will adhere to NMT's 
		// logging filters such as `newspack_migration_tools_enable_file_log` and `newspack_migration_tools_log_dir`.
		$output_file   = 'ghost_kg_elements.jsonl';
		$output_logger = FileLog::get_logger( $output_file, $output_file, new PlainLineFormatter() );

		// For each run, clear out any existing data already in file.
		FileLog::truncate_files( $output_logger );

		/**
		 * Check all published posts migrated from Ghost for custom Ghost editor HTML content -- HTML elements with "kg-*" classes.
		 */
		$post_ids = $wpdb->get_col( // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.DirectQuery WordPress.DB.DirectDatabaseQuery.NoCaching.
			"select p.ID from {$wpdb->posts} p
			join {$wpdb->postmeta} pm on p.ID = pm.post_id
			where pm.meta_key = 'newspack_ghostcms_id'
			and pm.meta_value is not null
			and p.post_type = 'post'
			and p.post_status = 'publish'"
		);
		$this->log( sprintf( 'Checking %d posts imported from Ghost for custom Ghost editor HTML content...', count( $post_ids ) ) );
		$elements     = [];
		$failed_posts = [];
		foreach ( $post_ids as $post_id ) {
			$post_content = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ) ); // phpcs:ignore -- WordPress.DB.DirectDatabaseQuery.DirectQuery WordPress.DB.DirectDatabaseQuery.NoCaching.
			if ( empty( $post_content ) ) {
				continue;
			}

			try {
				// Parse all elements.
				$doc          = new HtmlDocument( $post_content );
				$all_elements = $doc->find( '*' );
				foreach ( $all_elements as $element ) {
					$class_attr = $element->getAttribute( 'class' );
					if ( empty( $class_attr ) ) {
						continue;
					}

					// Extract kg-* classes.
					$classes    = explode( ' ', $class_attr );
					$kg_classes = [];
					foreach ( $classes as $class ) {
						$class = trim( $class );
						if ( str_starts_with( $class, 'kg-' ) ) {
							$kg_classes[] = $class;
						}
					}
					if ( empty( $kg_classes ) ) {
						continue;
					}

					// Get full opening tag for example.
					$outertext           = $element->outertext;
					$closing_bracket_pos = strpos( $outertext, '>' );
					// If there's no closing bracket, use the whole outertext (e.g. malformed `<img src="broken`).
					if ( false === $closing_bracket_pos ) {
						$full_opening_tag = $outertext;
					} else {
						// From start to the first closing bracket inclusive (e.g. `<img src="x" class="kg-image">` from `<img ...>content</img>`).
						$full_opening_tag = substr( $outertext, 0, $closing_bracket_pos + 1 );
					}

					// Group elements by keys = tag name + sorted kg-classes.
					$tag_name = $element->tag;
					sort( $kg_classes );
					$grouping_key = $tag_name . '|' . implode( ',', $kg_classes );

					// Skip accepted kg-* elements which are intentionally kept in post_content as-is.
					if ( $this->is_accepted_kg_element( $tag_name, $kg_classes ) ) {
						continue;
					}

					// Initialize array element if first time adding it.
					if ( ! isset( $elements[ $grouping_key ] ) ) {
						$elements[ $grouping_key ] = [
							'html_element'           => $tag_name,
							'kg_classes'             => $kg_classes,
							'post_ids'               => [],
							'first_example_full_tag' => $full_opening_tag, // Store first full example for convenience.
						];
					}

					// Add this post ID where the element appears.
					if ( ! in_array( $post_id, $elements[ $grouping_key ]['post_ids'], true ) ) {
						$elements[ $grouping_key ]['post_ids'][] = $post_id;
					}
				}
			} catch ( \Exception $e ) {
				$failed_posts[] = $post_id;
				$this->log( sprintf( 'Failed to parse post ID %d: %s', $post_id, $e->getMessage() ), LogLevel::WARNING );
			}
		}

		/**
		 * Write results to JSONL file.
		 */
		foreach ( $elements as $element_data ) {
			$data = [
				'html_element'           => $element_data['html_element'],
				'kg_classes'             => $element_data['kg_classes'],
				'first_example_full_tag' => $element_data['first_example_full_tag'],
				'post_ids'               => $element_data['post_ids'],
			];
			try {
				$output_logger->info( wp_json_encode( $data ) );
			} catch ( \Throwable $e ) {                   
				$this->log( sprintf( 'Failed to write to "%s" -- check file permissions, newspack_migration_tools_enable_file_log and newspack_migration_tools_log_dir, then try running the custom Ghost content check command again.', $output_file ), LogLevel::ERROR );
				return;
			}
		}

		/**
		 * Log summary.
		 */
		if ( ! empty( $failed_posts ) ) {
			$this->log( sprintf( 'Failed to parse %d posts: %s', count( $failed_posts ), implode( ', ', $failed_posts ) ), LogLevel::ERROR );
		}
		if ( ! empty( $elements ) ) {
			$this->log( sprintf( "Found %d unfamiliar/unhandled 'kg-*' elements in total %d posts, full list was saved to %s. Please QA these findings: if they display correctly/well enough in WP frontend/backend, simply add them to ACCEPTED_KG_ELEMENTS constant in GhostCMSHelper; if they don't, write fixers/transformers for them.", count( $elements ), count( $post_ids ), $output_file ), LogLevel::WARNING );
		} else {
			$this->log( sprintf( "No unfamiliar/unhandled 'kg-*' elements found in total %d posts.", count( $post_ids ) ), LogLevel::INFO );
		}
		$this->log( 'Done checking for custom Ghost HTML content.', LogLevel::INFO );
	}

	/**
	 * Rewrite Ghost author URLs in imported post content.
	 *
	 * When users are imported from Ghost, we try to preserve their original Ghost slug in `user_nicename`,
	 * but that specific `user_nicename` may already be taken during user insertion, and so the resulting nicename may 
	 * still differ from the original Ghost slug.
	 * 
	 * This method finds all such users and rewrites author URLs in post content from the old Ghost slug to the new nicename.
	 *
	 * @param string $log_slug  The logger slug.
	 * @param string $ghost_url The Ghost site URL (e.g., https://www.liveghost.com).
	 */
	public function rewrite_ghost_author_urls_in_content( string $log_slug, string $ghost_url ): void {
		global $wpdb;

		// Init logger usage in this class.
		$this->set_log_slug( $log_slug );

		$hostname = wp_parse_url( $ghost_url, PHP_URL_HOST );
		if ( empty( $hostname ) ) {
			$this->log( 'Could not determine hostname from provided Ghost URL.', LogLevel::ERROR );
			return;
		}

		// Get Ghost users where user_nicename differs from their original Ghost slug.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
		$users_to_update = $wpdb->get_results(
			"SELECT u.ID, um.meta_value AS ghost_slug, u.user_nicename
			FROM $wpdb->users u
			JOIN $wpdb->usermeta um ON u.ID = um.user_id
			WHERE um.meta_key = 'newspack_ghostcms_slug'
			AND u.user_nicename <> um.meta_value",
			ARRAY_A
		);
		// phpcs:enable
		if ( empty( $users_to_update ) ) {
			$this->log( 'No users with changed nicenames found. No author URL rewrites needed.', LogLevel::INFO );
			return;
		}

		$this->log( sprintf( 'Found %d users with nicenames different from their original Ghost slugs.', count( $users_to_update ) ), LogLevel::INFO );

		// Get all posts imported from Ghost.
		$post_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT DISTINCT p.ID FROM $wpdb->posts p
			JOIN $wpdb->postmeta pm ON pm.post_id = p.ID
			WHERE p.post_type = 'post' AND p.post_status = 'publish'
			AND pm.meta_key = 'newspack_ghostcms_id'"
		);
		if ( empty( $post_ids ) ) {
			$this->log( 'No Ghost posts found.', LogLevel::WARNING );
			return;
		}

		$this->log( sprintf( 'Scanning %d Ghost posts for author URL rewrites...', count( $post_ids ) ), LogLevel::INFO );

		// Rewrite author URLs in content.
		foreach ( $post_ids as $post_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$post_content = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM $wpdb->posts WHERE ID = %d", $post_id ) );
			if ( empty( $post_content ) ) {
				continue;
			}

			$replaced_user_nicenames = [];
			$post_content_updated    = $post_content;

			foreach ( $users_to_update as $user ) {
				$ghost_slug                      = $user['ghost_slug'];
				$user_nicename                   = $user['user_nicename'];
				$post_content_before_replacement = $post_content_updated;

				// Replace author URL, with or without trailing slash.
				$post_content_updated = str_replace(
					sprintf( '//%s/author/%s"', $hostname, $ghost_slug ),
					sprintf( '//%s/author/%s"', $hostname, $user_nicename ),
					$post_content_updated
				);
				$post_content_updated = str_replace(
					sprintf( '//%s/author/%s/"', $hostname, $ghost_slug ),
					sprintf( '//%s/author/%s/"', $hostname, $user_nicename ),
					$post_content_updated
				);

				// Note if a replacement was made.
				if ( $post_content_before_replacement !== $post_content_updated ) {
					$replaced_user_nicenames[] = $user_nicename;
				}
			}

			if ( $post_content !== $post_content_updated ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$wpdb->posts,
					[ 'post_content' => $post_content_updated ],
					[ 'ID' => $post_id ]
				);
				$this->log( sprintf( 'Updated post ID %d with URLs to user_nicename(s): %s', $post_id, implode( ', ', $replaced_user_nicenames ) ), LogLevel::INFO );
			}
		}

		wp_cache_flush();
	}

	/**
	 * Checks if an element matches any entry in ACCEPTED_KG_ELEMENTS.
	 *
	 * @param string $tag_name   HTML tag name.
	 * @param array  $kg_classes Sorted array of kg-* classes.
	 * @return bool True if this element is an accepted kg-* element.
	 */
	private function is_accepted_kg_element( string $tag_name, array $kg_classes ): bool {
		foreach ( self::ACCEPTED_KG_ELEMENTS as $accepted ) {
			$accepted_kg_classes = $accepted['kg_classes'];
			sort( $accepted_kg_classes );
			if ( $accepted['html_element'] === $tag_name && $accepted_kg_classes === $kg_classes ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get JSON author (user) object from data array.
	 *
	 * @param string $json_author_user_id JSON author id.
	 * @return null|Object
	 */
	private function get_json_author_user_by_id( string $json_author_user_id ): ?object {

		if ( empty( $this->data->users ) ) {
			return null;
		}

		foreach ( $this->data->users as $json_author_user ) {

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

		if ( empty( $this->data->posts_meta ) ) {
			return null;
		}

		foreach ( $this->data->posts_meta as $json_post_meta ) {

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

		if ( empty( $this->data->tags ) ) {
			return null;
		}

		foreach ( $this->data->tags as $json_tag ) {

			if ( $json_tag->id == $json_tag_id ) {
				return $json_tag;
			}       
		} 

		return null;
	}

	/**
	 * Get attachment (based on URL) from database else import external file from URL
	 * 
	 * Function visibility set to `protected` to allow overriding and mocking in tests.
	 *
	 * @param string  $path URL.
	 * @param string  $title URL or title string.
	 * @param ?string $caption Image caption (optional).
	 * @param ?string $description Image desc (optional).
	 * @param ?string $alt Image alt (optional).
	 * @param int     $post_id Post ID (optional).
	 * @return int|WP_Error $attachment_id
	 */
	protected function get_or_import_url( string $path, string $title, ?string $caption = null, ?string $description = null, ?string $alt = null, int $post_id = 0 ): int|WP_Error {

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
			
			$this->log( 'Image already exists: ' . $attachment_id );

			return $attachment_id;

		}

		// this function will check if existing, but only after re-downloading.
		return Attachments::import_external_file( $path, $title, $caption, $description, $alt, $post_id );
	}

	/**
	 * Insert JSON author (user) as Guest Contributor.
	 *
	 * @param object $json_author_user json author (user) object.
	 * @return int|WP_User Return of integer 0 means not inserted, otherwise WP_User is returned.
	 */
	private function insert_json_author_user( object $json_author_user ): int|WP_User {

		// Must have visibility property with value of 'public'.
		if ( empty( $json_author_user->visibility ) || 'public' != $json_author_user->visibility ) {
			$this->log( 'JSON user not visible. Could not be inserted.', LogLevel::WARNING );
			return 0;
		}

		$display_name = $json_author_user->name;
		$this->log( sprintf( "Get or insert author: '%s'", $display_name ) );

		// Unique identifier is JSON author ID with prefix.
		$unique_identifier = 'ghostauthorid_' . $json_author_user->id;

		// 1) Check if user exists by unique identifier.
		$existing_user = UsersHelper::get_user_by_unique_identifier( $unique_identifier );
		if ( $existing_user ) {
			$this->log( 'Found existing WP_User.' );

			// Save old slug for possible redirect.
			update_user_meta( $existing_user->ID, 'newspack_ghostcms_slug', $json_author_user->slug );

			return $existing_user;
		}

		// 2) Check for existing WP User with same email.
		$json_email = $json_author_user->email ?? null;
		if ( $json_email ) {
			$user_by_email = get_user_by( 'email', $json_email );
			if ( $user_by_email ) {
				$this->log( sprintf( 'Using existing WP_User with same email (user ID %d).', $user_by_email->ID ), LogLevel::WARNING );
				update_user_meta( $user_by_email->ID, UsersHelper::UNIQUE_IDENTIFIER_META_KEY, $unique_identifier );
				update_user_meta( $user_by_email->ID, 'newspack_ghostcms_slug', $json_author_user->slug );
				return $user_by_email;
			}
		}

		// 3) Check for existing WP User with same display name.
		$user_query = new \WP_User_Query(
			array(
				'search'         => $display_name,
				'search_columns' => array( 'display_name' ),
				'role__in'       => array( 'administrator', 'editor', 'author', Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME, 'contributor' ),
			)
		);
		$users      = $user_query->get_results();
		if ( ! empty( $users ) ) {
			$user_by_name = $users[0];

			$message = '';
			if ( count( $users ) > 1 ) {
				$message = sprintf( 'Multiple WP users (count %d) with same display name found, using the first one: ', count( $users ) );
			}
			$this->log( $message . sprintf( "Using existing WP_User with same display name '%s' (user ID %d).", $display_name, $user_by_name->ID ), LogLevel::WARNING );

			update_user_meta( $user_by_name->ID, UsersHelper::UNIQUE_IDENTIFIER_META_KEY, $unique_identifier );
			update_user_meta( $user_by_name->ID, 'newspack_ghostcms_slug', $json_author_user->slug );
			return $user_by_name;
		}

		// Create Guest Contributor.
		$desired_slug = $json_author_user->slug ?? sanitize_title( $display_name );
		$user_data    = [
			'display_name'  => $display_name,
			'user_login'    => $desired_slug,
			'user_nicename' => $desired_slug, // Try and preserve same user slug for author URLs.
		];
		if ( ! empty( $json_author_user->email ) ) {
			$user_data['user_email'] = $json_author_user->email;
		}
		if ( ! empty( $json_author_user->bio ) ) {
			$user_data['description'] = $json_author_user->bio;
		}
		if ( ! empty( $json_author_user->website ) ) {
			$user_data['user_url'] = $json_author_user->website;
		}

		try {
			$wp_user = GuestContributorsHelper::create_or_get_contributor( $user_data, $unique_identifier );
		} catch ( Exception $e ) {
			$this->log( 'Guest Contributor create failed: ' . $e->getMessage(), LogLevel::ERROR );
			return 0;
		}
		if ( is_wp_error( $wp_user ) ) {
			$this->log( sprintf( 'Guest Contributor create failed, message: %s, context: %s', $wp_user->get_error_message(), wp_json_encode( $json_author_user ) ), LogLevel::ERROR );
			return 0;
		}
		if ( ! ( $wp_user instanceof WP_User ) || ! ( $wp_user->ID > 0 ) ) {
			$this->log( 'Guest Contributor create failed: Invalid user object returned, context: ' . wp_json_encode( $json_author_user ), LogLevel::ERROR );
			return 0;
		}

		$this->log( 'Created new Guest Contributor.' );

		// Save old slug for possible redirect.
		update_user_meta( $wp_user->ID, 'newspack_ghostcms_slug', $json_author_user->slug );

		// Newspack Theme implements `function newspack_author_get_social_links` which adds social fields.
		// Note: 'twitter' expects handle only while others expect full URLs.
		if ( ! empty( $json_author_user->twitter ) ) {
			update_user_meta( $wp_user->ID, 'twitter', ltrim( $json_author_user->twitter, '@' ) );
		}
		if ( ! empty( $json_author_user->instagram ) ) {
			$instagram = $json_author_user->instagram;
			if ( ! str_starts_with( $instagram, 'http' ) ) {
				$instagram = 'https://instagram.com/' . ltrim( $instagram, '@' );
			}
			update_user_meta( $wp_user->ID, 'instagram', $instagram );
		}
		if ( ! empty( $json_author_user->linkedin ) ) {
			$linkedin = $json_author_user->linkedin;
			if ( ! str_starts_with( $linkedin, 'http' ) ) {
				$linkedin = 'https://linkedin.com/in/' . $linkedin;
			}
			update_user_meta( $wp_user->ID, 'linkedin', $linkedin );
		}
		if ( ! empty( $json_author_user->bluesky ) ) {
			$bluesky = $json_author_user->bluesky;
			if ( ! str_starts_with( $bluesky, 'http' ) ) {
				$bluesky = 'https://bsky.app/profile/' . $bluesky;
			}
			update_user_meta( $wp_user->ID, 'bluesky', $bluesky );
		}

		// Import profile image as avatar.
		if ( ! empty( $json_author_user->profile_image ) ) {
			$this->import_author_avatar( $wp_user->ID, $json_author_user->profile_image, $display_name );
		}

		return $wp_user;
	}

	/**
	 * Import author avatar from Ghost profile_image URL.
	 *
	 * @param int    $user_id      WP User ID.
	 * @param string $image_url    Ghost profile image URL.
	 * @param string $display_name Author display name for logging.
	 */
	private function import_author_avatar( int $user_id, string $image_url, string $display_name ): void {
		// Fill in the Ghost CMS URL placeholder.
		$image_url = str_replace( '__GHOST_URL__', $this->ghost_url, $image_url );

		// Import the image as attachment.
		$attachment_id = $this->get_or_import_url( $image_url, sprintf( 'Avatar: %s', $display_name ) );
		if ( is_wp_error( $attachment_id ) || ! is_numeric( $attachment_id ) || $attachment_id <= 0 ) {
			$this->log( sprintf( 'Could not import avatar for user %d: %s', $user_id, $image_url ), LogLevel::WARNING );
			return;
		}

		// Assign avatar with Simple Local Avatars.
		$this->get_simple_local_avatars()->assign_avatar( $user_id, $attachment_id );
		$this->log( sprintf( 'Assigned avatar (attachment %d) to user %d.', $attachment_id, $user_id ) );
	}

	/**
	 * Get Simple Local Avatars helper.
	 *
	 * @return SimpleLocalAvatars
	 */
	private function get_simple_local_avatars(): SimpleLocalAvatars {
		if ( null === $this->simple_local_avatars ) {
			$this->simple_local_avatars = new SimpleLocalAvatars();
		}
		return $this->simple_local_avatars;
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
			
			$this->log( 'JSON tag not visible. Could not be inserted.', LogLevel::WARNING );

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
				$this->log( 'Insert term failed (' . $json_tag->slug . ') ' . $term_arr->get_error_message(), LogLevel::WARNING );
				return 0;
			}

			$this->log( 'Inserted category term: ' . $term_arr['term_id'] );

		}
		
		// Save old slug for possible redirect.
		update_term_meta( $term_arr['term_id'], 'newspack_ghostcms_slug', $json_tag->slug );

		return $term_arr['term_id'];
	}

	/**
	 * Get JSON data node from a jq-style path.
	 *
	 * @param string $path jq-style path (e.g., ".db[0].data" or "data").
	 * @param object $json JSON object.
	 * 
	 * @return object|null The resolved data node or null if path is invalid.
	 */
	private function get_json_data_from_path( string $path, object $json ): ?object {
		// Strip leading dot if present.
		$path = ltrim( $path, '.' );

		if ( empty( $path ) ) {
			// path was either blank or dot - which means the "root" object.
			return $json;
		}

		// $current is the current object we are working on, it's like a bookmark that tracks where we are as we walk through a nested JSON structure.
		$current = $json;

		// Split jq-style path by dots: "db[0].data" becomes ["db[0]", "data"].
		$tokens = explode( '.', $path );
		foreach ( $tokens as $token ) {
			if ( empty( $token ) ) {
				continue;
			}

			/**
			 * Pattern to match bracket notation: property[index] or just [index].
			 *
			 * ^           - Start of string
			 * ([^\[]*)    - Capture group 1 -- zero or more characters that are NOT "[".
			 *              This captures the property name (e.g., "db" in "db[0]").
			 *              Can be empty for standalone indices like "[0]".
			 * \[          - Literal "["
			 * (\d+)       - Capture group 2 -- one or more digits (the array index)
			 * \]          - Literal "]"
			 * $           - End of string
			 *
			 * Examples:
			 *   "db[0]"  => matches, $matches[1] = "db",  $matches[2] = "0"
			 *   "[0]"    => matches, $matches[1] = "",    $matches[2] = "0"
			 *   "data"   => no match (no brackets)
			 */
			$pattern = '/^([^\[]*)\[(\d+)\]$/';
			if ( preg_match( $pattern, $token, $matches ) ) {
				$property = $matches[1];
				$index    = (int) $matches[2];

				// Access property first if present.
				if ( ! empty( $property ) ) {
					if ( is_object( $current ) && isset( $current->{$property} ) ) {
						$current = $current->{$property};
					} else {
						return null;
					}
				}

				// Then access array index.
				if ( is_array( $current ) && isset( $current[ $index ] ) ) {
					$current = $current[ $index ];
				} else {
					return null;
				}
			} elseif ( is_object( $current ) && isset( $current->{$token} ) ) {
				// Simple property access.
				$current = $current->{$token};
			} else {
				return null;
			}
		}

		return is_object( $current ) ? $current : null;
	}

	/**
	 * Log wrapper function incase logging needs to be updated in future it can be changed here.
	 *
	 * @param string  $message The message to log.
	 * @param string  $level Info, error, warning, etc.
	 * @param boolean $exit_on_error For error messages if desired.
	 * @return void
	 */
	private function log( string $message, string $level = 'debug', bool $exit_on_error = false ): void {
		// Skip logging if log_slug is not set (e.g., in unit tests).
		if ( empty( $this->log_slug ) ) {
			return;
		}

		$logger = MultiLog::get_cli_and_file_logger( $this->log_slug );

		try {
			$level = Level::fromName( $level );
		} catch ( UnhandledMatchError $e ) {
			$level = Level::fromName( 'debug' );
		}
		
		$logger->log( $level, $message );

		if ( $exit_on_error ) {
			NMT::exit_with_message( $message, [ $logger ] );
		}           
	}

	/**
	 * Set post authors using JSON relationship(s).
	 *
	 * @param int    $wp_post_id wp_posts id.
	 * @param string $json_post_id json post id.
	 * @return void
	 */
	private function set_post_authors( int $wp_post_id, string $json_post_id ): void {

		if ( empty( $this->data->posts_authors ) ) {
			$this->log( 'JSON has no post author relationships.', LogLevel::WARNING );
			return;
		}

		$wp_user_ids = [];

		// Each posts_authors relationship.
		foreach ( $this->data->posts_authors as $json_post_author ) {
			// Skip if post id does not match relationship.
			if ( $json_post_author->post_id != $json_post_id ) {
				continue;
			}

			$this->log( 'Relationship found for author: ' . $json_post_author->author_id );

			// If author_id wasn't already processed.
			if ( ! isset( $this->authors_to_wp_users[ $json_post_author->author_id ] ) ) {
				// Get the json author (user) object.
				$json_author_user = $this->get_json_author_user_by_id( $json_post_author->author_id );

				// Verify related author (user) was found in json.
				if ( empty( $json_author_user ) ) {
					$this->log( 'JSON author (user) not found: ' . $json_post_author->author_id, LogLevel::WARNING );
					continue;
				}

				// Attempt insert and save return value into lookup.
				$this->authors_to_wp_users[ $json_post_author->author_id ] = $this->insert_json_author_user( $json_author_user );
			}

			// Verify lookup value is a WP_User object.
			// A value of 0 means json author (user) did not have visibility of public.
			// In that case, don't add to return array.
			if ( $this->authors_to_wp_users[ $json_post_author->author_id ] instanceof WP_User ) {
				$wp_user_ids[] = $this->authors_to_wp_users[ $json_post_author->author_id ]->ID;
			}       
		} // foreach relationship

		if ( empty( $wp_user_ids ) ) {
			$this->log( 'No authors.' );
			return;
		}

		// Assign WP User IDs (Guest Contributors) to post using UsersHelper.
		$result = UsersHelper::assign_authors_to_post( $wp_post_id, $wp_user_ids );
		if ( is_wp_error( $result ) ) {
			$this->log( 'Failed to assign authors: ' . $result->get_error_message(), LogLevel::ERROR );
			return;
		}

		$this->log( 'Assigned authors (guest contributors). Count: ' . count( $wp_user_ids ) );
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

		$this->log( 'Featured image fetch url: ' . $old_image_url );

		// Get alt and caption if exists in json meta node.
		$json_meta = $this->get_json_post_meta( $json_post_id );

		$old_image_alt     = $json_meta->feature_image_alt ?? '';
		$old_image_caption = $json_meta->feature_image_caption ?? '';

		// get existing or upload new.
		$featured_image_id = $this->get_or_import_url( $old_image_url, $old_image_url, $old_image_caption, $old_image_caption, $old_image_alt, $wp_post_id );

		if ( ! is_numeric( $featured_image_id ) || ! ( $featured_image_id > 0 ) ) {
			
			$this->log( 'Featured image import failed for: ' . $old_image_url, LogLevel::WARNING );

			if ( is_wp_error( $featured_image_id ) ) {

				$this->log( 'Featured image import wp error: ' . $featured_image_id->get_error_message(), LogLevel::WARNING );

			}
			
			return;
		}

		update_post_meta( $wp_post_id, '_thumbnail_id', $featured_image_id );

		$this->log( 'Set _thumbnail_id: ' . $featured_image_id );
	}

	/**
	 * Set post tags (categories) using JSON relationship(s).
	 *
	 * @param int    $wp_post_id wp_posts ID.
	 * @param string $json_post_id json post id.
	 * @return void
	 */
	private function set_post_tags_to_categories( int $wp_post_id, string $json_post_id ): void {

		if ( empty( $this->data->posts_tags ) ) {
			
			$this->log( 'JSON has no post tags (category) relationships.', LogLevel::WARNING );

			return;
		
		}

		$category_ids = [];

		// Each posts_tags relationship.
		foreach ( $this->data->posts_tags as $json_post_tag ) {
			
			// Skip if post id does not match relationship.
			if ( $json_post_tag->post_id != $json_post_id ) {
				continue;
			}

			$this->log( 'Relationship found for tag: ' . $json_post_tag->tag_id );

			// If tag_id wasn't already processed.
			if ( ! isset( $this->tags_to_categories[ $json_post_tag->tag_id ] ) ) {

				// Get the json tag object.
				$json_tag = $this->get_json_tag_by_id( $json_post_tag->tag_id );

				// Verify related tag was found in json.
				if ( empty( $json_tag ) ) {
				
					$this->log( 'JSON tag not found: ' . $json_post_tag->tag_id, LogLevel::WARNING );

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
		
			$this->log( 'No categories.' );

			return;
		
		}
		
		wp_set_post_categories( $wp_post_id, $category_ids );

		$this->log( 'Set post categories. Count: ' . count( $category_ids ) );
	}

	/**
	 * Check if need to skip this JSON post.
	 *
	 * @param object $json_post             JSON post object.
	 * @param array  $visibilities_to_import Visibility values to import.
	 * @return string|null
	 */
	private function skip( object $json_post, array $visibilities_to_import ): ?string {

		global $wpdb;

		// JSON properites.

		if ( 'post' != $json_post->type ) {
			return 'not_post';
		}
		if ( 'published' != $json_post->status ) {
			return 'not_published';
		}
		if ( ! in_array( $json_post->visibility, $visibilities_to_import, true ) ) {
			return 'visibility_is_different';
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

	/**
	 * Replace Ghost's "Koenig editor" video embeds with <video> elements, and logs the updates.
	 * 
	 * The resulting <video> element(s):
	 *   - are not Gutenberg blocks, because the input HTML is not in expected to be in blocks either,
	 *   - are simple HTML5 video players with controls,
	 *   - are given the `style="width: 100%%; height: auto;"` to ensure they are displayed correctly in WP.
	 *
	 * @param string $content Content to replace video embeds in.
	 * @param string $ghost_id Ghost ID of the content.
	 * 
	 * @return string Processed content.
	 */
	public function replace_video_embeds( string $content, string $ghost_id ): string {
		// Find all kg-video-container divs.
		$doc              = new HtmlDocument( $content );
		$video_containers = $doc->find( 'div.kg-video-container' );
		if ( empty( $video_containers ) ) {
			return $content;
		}

		foreach ( $video_containers as $container ) {
			// Find the first video element within this container.
			$video_element = $container->find( 'video', 0 );
			if ( ! $video_element ) {
				continue;
			}

			// Get the src attribute.
			$src = $video_element->getAttribute( 'src' );
			if ( empty( $src ) ) {
				continue;
			}

			// Replace the entire kg-video-container with the simple video element.
			$replacement          = sprintf(
				'<video src="%s" controls style="width: 100%%; height: auto;"></video>',
				esc_attr( $src )
			);
			$container->outertext = $replacement;
		}

		$this->log(
			sprintf( 'Replaced %d video embeds in Ghost ID %s.', count( $video_containers ), $ghost_id ),
			LogLevel::INFO
		);

		return (string) $doc;
	}

	/**
	 * Replace Ghost's "Koenig editor" audio embeds with <audio> elements, and logs the updates.
	 * 
	 * The resulting <audio> element(s):
	 *   - are not Gutenberg blocks, because the input HTML is not expected to be in blocks either,
	 *   - are simple HTML5 audio players with controls.
	 *
	 * @param string $content Content to replace audio embeds in.
	 * @param string $ghost_id Ghost ID of the content.
	 * 
	 * @return string Processed content.
	 */
	public function replace_audio_embeds( string $content, string $ghost_id ): string {
		// Find all kg-audio-card divs.
		$doc              = new HtmlDocument( $content );
		$audio_containers = $doc->find( 'div.kg-audio-card' );
		if ( empty( $audio_containers ) ) {
			return $content;
		}

		foreach ( $audio_containers as $container ) {
			// Find the first audio element within this container.
			$audio_element = $container->find( 'audio', 0 );
			if ( ! $audio_element ) {
				continue;
			}

			// Get the src attribute.
			$src = $audio_element->getAttribute( 'src' );
			if ( empty( $src ) ) {
				continue;
			}

			// Replace the entire kg-audio-card with the simple audio element.
			$replacement          = sprintf(
				'<audio src="%s" controls></audio>',
				esc_attr( $src )
			);
			$container->outertext = $replacement;
		}

		$this->log(
			sprintf( 'Replaced %d audio embeds in Ghost ID %s.', count( $audio_containers ), $ghost_id ),
			LogLevel::INFO
		);

		return (string) $doc;
	}

	/**
	 * Replace Ghost's "Koenig editor" `blockquote.kg-blockquote-alt` with Gutenberg quote blocks
	 * and logs the updates.
	 *
	 * @param string $content Content to replace blockquotes in.
	 * @param string $ghost_id Ghost ID of the content.
	 * 
	 * @return string Processed content.
	 */
	public function replace_blockquotes( string $content, string $ghost_id ): string {
		// Find all kg-blockquote-alt blockquotes.
		$doc         = new HtmlDocument( $content );
		$blockquotes = $doc->find( 'blockquote.kg-blockquote-alt' );
		if ( empty( $blockquotes ) ) {
			return $content;
		}

		/** @var GutenbergBlockGenerator $block_generator */
		$block_generator = new GutenbergBlockGenerator();

		foreach ( $blockquotes as $blockquote ) {
			// Get the inner text content.
			$inner_text = $blockquote->innertext;
			if ( empty( trim( $inner_text ) ) ) {
				continue;
			}

			// Normalize whitespaces: collapse newlines/tabs/spaces into single spaces, and trim.
			$inner_text = preg_replace( '/\s+/', ' ', $inner_text );
			$inner_text = trim( $inner_text );

			// Get the Gutenberg wp:pullquote block.
			$replacement = serialize_blocks( [ $block_generator->get_quote( $inner_text ) ] );

			$blockquote->outertext = $replacement;
		}

		$this->log(
			sprintf( 'Replaced %d blockquotes in Ghost ID %s.', count( $blockquotes ), $ghost_id ),
			LogLevel::INFO
		);

		return (string) $doc;
	}

	/**
	 * Replace Ghost's "Koenig editor" callout cards with Gutenberg paragraphs, and logs the updates.
	 * 
	 * @see Ghost Koenig editor documentation: https://ghost.org/docs/themes/content/
	 * 
	 * Callout card consists of:
	 *   1. a parent wrapper:
	 *     - a `div` element with required classes `kg-card kg-callout-card`
	 *     - optional additional classes:
	 *       - `kg-callout-card-accent`
	 *       - `kg-callout-card-blue`
	 *       - `kg-callout-card-grey`
	 *       - `kg-callout-card-green`
	 *       - `kg-callout-card-white`
	 *       - `kg-callout-card-yellow`
	 *   2. children elements:
	 *     - a `div.kg-callout-emoji`
	 *     - a `div.kg-callout-text`
	 * 
	 * @param string $content Content to replace callout cards in.
	 * @param string $ghost_id Ghost ID of the content.
	 * 
	 * @return string Processed content.
	 */
	public function replace_callout_cards( string $content, string $ghost_id ): string {
		/**
		 * Map Ghost callout color classes to hex background colors.
		 * The following classes have been taken from Ghost's documentation https://ghost.org/docs/themes/content/ 
		 * and Ghost's source code https://github.com/TryGhost/Ghost/blob/c667620d8f2e32c96fe376ad0f3dabc79488532a/ghost/core/core/frontend/src/cards/css/callout.css
		 * where the rgba codes are here converted to hex.
		 */
		$color_codes = [
			'kg-callout-card-accent' => '#7C8B9A21',
			'kg-callout-card-blue'   => '#E3F2FD',
			'kg-callout-card-grey'   => '#7C8B9A21',
			'kg-callout-card-green'  => '#34b7431f',
			'kg-callout-card-yellow' => '#FFF9E6',
			'kg-callout-card-red'    => '#d12e2e1c',
			'kg-callout-card-pink'   => '#e147ae1c',
			'kg-callout-card-purple' => '#8755ec1f',
			'kg-callout-card-white'  => '#FFFFFF',
		];

		// Find all kg-callout-card divs.
		$doc      = new HtmlDocument( $content );
		$callouts = $doc->find( 'div.kg-callout-card' );
		if ( empty( $callouts ) ) {
			return $content;
		}

		/** @var GutenbergBlockGenerator $block_generator */
		$block_generator = new GutenbergBlockGenerator();

		foreach ( $callouts as $callout ) {
			// Get emoji.
			$emoji_text = '';
			$emoji_div  = $callout->find( 'div.kg-callout-emoji', 0 );
			if ( $emoji_div ) {
				$emoji_text = trim( $emoji_div->innertext );
			}

			// Get text.
			$callout_text = '';
			$text_div     = $callout->find( 'div.kg-callout-text', 0 );
			if ( $text_div ) {
				$callout_text = trim( $text_div->innertext );
			}

			// Skip if both are empty.
			if ( empty( $emoji_text ) && empty( $callout_text ) ) {
				continue;
			}
			// Insert space between emoji and text only when both are present.
			$paragraph_content = trim( $emoji_text . ( $emoji_text && $callout_text ? ' ' : '' ) . $callout_text );

			// Detect background color from optional color classes.
			$class_attr = $callout->getAttribute( 'class' );
			$bg_color   = '';
			$classes    = explode( ' ', $class_attr );
			foreach ( $classes as $class ) {
				$class = trim( $class );
				if ( isset( $color_codes[ $class ] ) ) {
					$bg_color = $color_codes[ $class ];
					break;
				}
			}

			// Build paragraph block with optional background color.
			if ( ! empty( $bg_color ) ) {
				$block = $block_generator->get_paragraph(
					$paragraph_content,
					'',
					'',
					'',
					[ 'has-background' ],
					[ 'style' => [ 'color' => [ 'background' => $bg_color ] ] ],
					[ 'background-color' => $bg_color ]
				);
			} else {
				$block = $block_generator->get_paragraph( $paragraph_content );
			}

			$callout->outertext = serialize_blocks( [ $block ] );
		}

		$this->log(
			sprintf( 'Replaced %d callout cards in Ghost ID %s.', count( $callouts ), $ghost_id ),
			LogLevel::INFO
		);

		return (string) $doc;
	}

	/**
	 * Replace Ghost's "Koenig editor" galleries with Gutenberg galleries, and logs the updates.
	 * 
	 * Koenig editor gallery structure:
	 *   - parent `figure` with classes "kg-card kg-gallery-card" (optionally "kg-width-wide" or "kg-width-full", and "kg-card-hascaption" if caption present)
	 *   - child of `figure.kg-gallery-card` -- `div` with class "kg-gallery-container"
	 *   - children of `div.kg-gallery-container` -- multiple rows `div` with class "kg-gallery-row"
	 *   - children of `div.kg-gallery-row` -- multiple images per row `div` with class "kg-gallery-image"
	 *   - child of `div.kg-gallery-image` -- `img` (with attributes: src, width, height, loading="lazy", srcset, sizes)
	 *   - child of `figure.kg-gallery-card`, sibling to `div.kg-gallery-container` -- `figcaption` (only present when kg-card-hascaption class exists)
	 * 
	 * There are no captions per images, just a single optional caption for the entire gallery.
	 * 
	 * The Koenig editor gallery looks like a tile grid, so we'll use the Jetpack Tiled Gallery block, however the Jetpack Tiled Gallery block generator
	 * doesn't always produce the correct CSS layout, because it's computed in frontend, so a QA is always advised after the replacement,
	 * which is why a warning is logged.
	 * 
	 * @param string $content Content to replace galleries in.
	 * @param string $ghost_id Ghost ID of the content.
	 * 
	 * @return string Processed content.
	 */
	public function replace_galleries( string $content, string $ghost_id ): string {
		// Find all kg-gallery-card figures.
		$doc       = new HtmlDocument( $content );
		$galleries = $doc->find( 'figure.kg-gallery-card' );
		if ( empty( $galleries ) ) {
			return $content;
		}

		/** @var GutenbergBlockGenerator $block_generator */
		$block_generator = new GutenbergBlockGenerator();

		$galleries_replaced = 0;

		foreach ( $galleries as $gallery ) {
			// Find all images within this gallery.
			$images         = $gallery->find( 'div.kg-gallery-image img' );
			$attachment_ids = [];

			foreach ( $images as $img ) {
				$src = $img->getAttribute( 'src' );
				if ( empty( $src ) ) {
					continue;
				}

				// Get WP attachment ID from the image URL.
				$attachment_id = $this->get_or_import_url( $src, $src );

				if ( ! is_wp_error( $attachment_id ) && $attachment_id > 0 ) {
					$attachment_ids[] = $attachment_id;
				} else {
					$this->log(
						sprintf( 'Image attachment with URL %s not found for gallery in Ghost ID %s.', $src, $ghost_id ),
						LogLevel::ERROR
					);
				}
			}

			// Skip if no valid attachments found.
			if ( empty( $attachment_ids ) ) {
				$this->log(
					sprintf( 'No valid attachments found for gallery in Ghost ID %s.', $ghost_id ),
					LogLevel::WARNING
				);
				continue;
			}

			// Check for optional gallery caption (this is not per-image, just a single caption for the entire gallery, like "Photos by John Doe").
			$caption    = '';
			$figcaption = $gallery->find( 'figcaption', 0 );
			if ( $figcaption ) {
				$caption = trim( $figcaption->innertext );
			}

			// Generate Jetpack Tiled Gallery block.
			$gallery_block = $block_generator->get_jetpack_tiled_gallery( $attachment_ids, 'media' );
			$replacement   = serialize_blocks( [ $gallery_block ] );

			// If gallery caption exists, append it as a centered italic paragraph.
			if ( ! empty( $caption ) ) {
				// Strip HTML tags from caption (Ghost may include <p><span>...</span></p>).
				$caption_text = wp_strip_all_tags( $caption );
				// Build caption block manually since get_paragraph couples className attr with <p> class,
				// but for alignment we need class="has-text-align-center" on <p> without className in attrs.
				$caption_block = [
					'blockName'    => 'core/paragraph',
					'attrs'        => [ 'align' => 'center' ],
					'innerBlocks'  => [],
					'innerHTML'    => '<p class="has-text-align-center"><em>' . $caption_text . '</em></p>',
					'innerContent' => [ '<p class="has-text-align-center"><em>' . $caption_text . '</em></p>' ],
				];
				$replacement  .= "\n" . serialize_blocks( [ $caption_block ] );
			}

			$gallery->outertext = $replacement;
			++$galleries_replaced;
		}

		$this->log(
			sprintf( 'Replaced %d galleries in Ghost ID %s.', $galleries_replaced, $ghost_id ),
			LogLevel::INFO
		);
		$this->log(
			sprintf( 'QA is advised of Jetpack Tiled Galleries used in Ghost ID %s -- gallery layout may not be correct until refreshed in frontend/Gutenberg editor.', $ghost_id ),
			LogLevel::WARNING
		);

		return (string) $doc;
	}

	/**
	 * Get all visibility values from JSON data.
	 *
	 * @param object $data JSON data.
	 * @return array Visibility values.
	 */
	private function get_visibility_values( object $data ): array {
		$visibilities = [];
		foreach ( $data->posts as $json_post ) {
			if ( ! in_array( $json_post->visibility, $visibilities, true ) ) {
				$visibilities[] = $json_post->visibility;
			}
		}

		return $visibilities;
	}
}
