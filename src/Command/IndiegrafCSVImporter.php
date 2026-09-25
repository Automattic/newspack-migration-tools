<?php
/**
 * Indiegraf CSV importer.
 *
 * @package newspack-migration-tools
 */

namespace Newspack\MigrationTools\Command;

use Newspack\MigrationTools\Hooks\MemoryCleanupHook;
use Newspack\MigrationTools\Logic\Attachments;
use Newspack\MigrationTools\Logic\GuestContributorsHelper;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use Newspack\MigrationTools\Logic\OriginalValueStore;
use Newspack\MigrationTools\Logic\Posts;
use Newspack\MigrationTools\Logic\SimpleLocalAvatars;
use Newspack\MigrationTools\Logic\Sponsors;
use Newspack\MigrationTools\Logic\Taxonomy;
use Newspack\MigrationTools\Logic\UsersHelper;
use Newspack\MigrationTools\Util\CsvIterator;
use Newspack\MigrationTools\Util\CsvWriter;
use Newspack\MigrationTools\Util\Log\MultiLog;
use Newspack\MigrationTools\Util\OriginalPermalink;
use Psr\Log\LoggerInterface;
use WP_CLI;
use WP_HTML_Tag_Processor;
use WP_User;

/**
 * Imports Indiegraf (WP All Export) CSVs of users, posts and pages.
 *
 * @see docs/IndiegrafCSVImporter.md How it works, how to extend it for other publications, and each feature.
 */
class IndiegrafCSVImporter implements WpCliCommandInterface {

	use WpCliCommandTrait;

	private const UID_POST         = 'indiegraf-post-';
	private const UID_USER         = 'indiegraf-user-';
	private const UID_BYLINE       = 'indiegraf-byline-';
	private const UID_CATEGORY     = 'indiegraf-category-';
	private const TOUCHED_IDS_FILE = 'indiegraf_touched_post_ids.txt';

	/**
	 * Post statuses imported as-is; any other status becomes a draft.
	 */
	private const POST_STATUSES = [ 'publish', 'draft', 'pending', 'private', 'future' ];

	/**
	 * First blocks that make the featured image redundant in the post header.
	 */
	private const MEDIA_BLOCKS = [ 'core/image', 'core/gallery', 'core/cover', 'core/video', 'core/embed' ];

	/**
	 * CSV column => wp_insert_post arg.
	 */
	private const POST_COLUMNS = [
		'Title'          => 'post_title',
		'Excerpt'        => 'post_excerpt',
		'Slug'           => 'post_name',
		'Status'         => 'post_status',
		'Post Type'      => 'post_type',
		'Order'          => 'menu_order',
		'Comment Status' => 'comment_status',
		'Ping Status'    => 'ping_status',
	];

	/**
	 * CSV column => post meta key, copied verbatim when non-empty.
	 */
	private const POST_META_COLUMNS = [
		'_yoast_wpseo_title'               => '_yoast_wpseo_title',
		'_yoast_wpseo_metadesc'            => '_yoast_wpseo_metadesc',
		'_yoast_wpseo_focuskw'             => '_yoast_wpseo_focuskw',
		'_yoast_wpseo_opengraph-title'     => '_yoast_wpseo_opengraph-title',
		'_yoast_wpseo_schema_page_type'    => '_yoast_wpseo_schema_page_type',
		'_yoast_wpseo_schema_article_type' => '_yoast_wpseo_schema_article_type',
		'footnotes'                        => 'footnotes',
	];

	/**
	 * Post columns consumed by handler methods; listed so the audit knows they are mapped.
	 */
	private const POST_HANDLED_COLUMNS = [
		'ID',
		'Content',
		'Date',
		'Post Modified Date',
		'Parent',
		'Categories',
		'_yoast_wpseo_primary_category',
		'Tags',
		'Authors',
		'Author ID',
		'Image Featured',
		'Image Title',
		'Image Caption',
		'Image Description',
		'Image Alt Text',
		'Permalink',
		'_wp_old_slug',
		'dt_original_post_url',
		'dt_original_site_name',
		'sponsor_settings_is_sponsored',
		'Sponsor settings_is_sponsored',
		'Sponsor settings_name',
		'Sponsor settings_link',
	];

	/**
	 * CSV column => wp_insert_user field.
	 */
	private const USER_COLUMNS = [
		'Username'        => 'user_login',
		'User Email'      => 'user_email',
		'First Name'      => 'first_name',
		'Last Name'       => 'last_name',
		'User Registered' => 'user_registered',
		'User URL'        => 'user_url',
	];

	/**
	 * User columns consumed by handler methods.
	 */
	private const USER_HANDLED_COLUMNS = [ 'ID', 'Display Name', 'biography', 'Description', 'title', 'profile_picture', 'User Role' ];

	/**
	 * Reviewed columns that are intentionally not migrated (reasons in indiegraf_field_mapping.csv), as fnmatch() patterns.
	 *
	 * The mapped and handled lists take precedence, so a pattern here never hides a mapped column.
	 */
	private const IGNORED_COLUMNS = [
		// Posts and pages.
		'Title2',
		'Indie Ads',
		'Indie Ads2',
		'Image URL',
		'Attachment URL',
		'Author Username',
		'Author Email',
		'Author First Name',
		'Author Last Name',
		'Parent Slug',
		'Format',
		'Template',
		'Hide on homepage',
		'Sponsor settings',
		'Sponsor settings_*',
		'sponsor_settings_content_sponsor',
		'sponsor_settings_sponsor_bottom',
		'_sponsor_settings_*',
		'ppma_*',
		'dt_original_post_id',
		'dt_original_source_id',
		'dt_original_site_url',
		'dt_syndicate_time',
		'dt_full_connection',
		'dt_subscription*',
		'dt_connection_map',
		'dt_unlinked',
		'post_views_count',
		'iawp_total_views',
		'_thumbnail_id',
		'_wp_page_template',
		'_wp_old_date',
		'_wp_trash_meta_*',
		'_wp_desired_post_slug',
		'_dp_original',
		'_wp_to_buffer_*',
		'wp_to_buffer_*',
		'_page_container',
		'_page_sidebar',
		'_header_*',
		'_disable_*',
		'_indie_*',
		'indie_ads_post_status',
		'_yoast_wpseo_estimated-reading-time-minutes',
		'_yoast_wpseo_wordproof_timestamp',
		'_yoast_wpseo_linkdex',
		'_yoast_wpseo_is_content_planner_banner_rendered',
		'_yoast_wpseo_opengraph-image*',
		'_yoast_wpseo_primary_towns_and_cities',
		'_yoast_wpseo_primary_series',
		// Users.
		'User Nicename',
		'Nickname',
		'User Pass',
		'User Activation Key',
		'User Status',
		'Rich Editing',
		'Comment Shortcuts',
		'Admin Color',
		'Use SSL',
		'Show Admin Bar Front',
		'Show Welcome Panel',
		'Dismissed WP Pointers',
		'Session Tokens',
		'WP *',
		'MetaboxhIDden *',
		'Closedpostboxes *',
		'Meta Box Order Dashboard',
		'Manageedit *',
		'Aim',
		'Yim',
		'Jabber',
		'syntax_highlighting',
		'locale',
		'default_password_nag',
		'community-events-location',
		'edit_post_per_page',
		'nav_menu_recently_edited',
		'managenav-menuscolumnshidden',
		'metaboxhidden_*',
		'closedpostboxes_*',
		'meta-box-order_post',
		'wp_persisted_preferences',
		'ppc_sidebar_metabox_state',
		'login_date',
		'wp_2fa_*',
		'gform_recent_forms',
		'tribe*',
		'my-jetpack-cache*',
		'jetpack_tracks_anon_id',
		'wpmdb_licence_key',
		'wp_yoast_notifications',
		'wpseo_*',
		'_yoast_wpseo_profile_updated',
		'_yoast_wpseo_introductions',
		'_yoast_wpseo_ai_content_planner_banner_permanently_dismissed',
		'_publishpress-authors_*',
		'_application_passwords',
		'user_id',
		'avatar',
		'email',
		'*_display',
		'_profile_picture',
		'_title',
		'_biography',
		'_email',
		'_email_link',
		'_website',
		'_twitter*',
		'_linkedin',
		'_facebook*',
		'_instagram*',
		'_bluesky',
		'_mastodon',
		'_threads',
		'author_category',
	];

	/**
	 * Block name fnmatch() pattern => 'drop' | 'unwrap' | 'keep' (logged for review) | handler method.
	 * Unknown indiegraf*\/ blocks default to 'unwrap'. Every rule except a handler is logged.
	 */
	private const BLOCK_TRANSFORMS = [
		'indiegraf/accordion'     => 'transform_accordion',
		'indiegraf/user-list'     => 'transform_user_list',
		'indiegraf/user-profile'  => 'transform_user_list',
		'indiegraf/featured-post' => 'drop',
		'indiegraf/latest-posts'  => 'drop',
		'indiegrafpay/*'          => 'drop',
		'core/shortcode'          => 'keep',
	];

	/**
	 * Logger for the running command: CLI + file. Only notable events go here; per-row outcomes go to $csv_log.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Audit CSV of the running command, header: source_id, new_id, status, message.
	 *
	 * @var CsvWriter
	 */
	private CsvWriter $csv_log;

	/**
	 * Count of logged rows per status, for the closing summary.
	 *
	 * @var int[]
	 */
	private array $log_counts = [];

	/**
	 * Source site URL from --live-rest-url, no trailing slash.
	 *
	 * @var string|null
	 */
	private ?string $rest_url = null;

	/**
	 * REST responses of this run, path => decoded body, or null on failure.
	 *
	 * @var array
	 */
	private array $rest_cache = [];

	/**
	 * Users CSV rows skipped by --import-roles, source ID => row; imported on demand when they turn out to be bylines.
	 *
	 * @var array
	 */
	private array $skipped_user_rows = [];

	/**
	 * Created and updated post IDs.
	 *
	 * @var int[]
	 */
	private array $touched_post_ids = [];

	/**
	 * Media hosts found in transformed content: 'wp' (WordPress upload paths) | 'cdn' (timestamp-folder paths) | 'pdf' => [ host => true ].
	 *
	 * @var array
	 */
	private array $content_hosts = [];

	/**
	 * {@inheritDoc}
	 *
	 * Commands are listed in the order they are run.
	 */
	public static function get_cli_commands(): array {
		$live_rest_url = [
			'type'        => 'assoc',
			'name'        => 'live-rest-url',
			'description' => "Source site URL (e.g. https://yountvillesun.com). Its public WP REST API fills in what the CSVs lack or have outdated: each post's exact categories and the category tree (the CSV omits assigned parents; Yoast primary categories are source term IDs), the live image URLs in post content (the CSV has pre-CDN URLs that may 404), author profile pictures, byline and page author details missing from the Users CSV, and the source host whose links import-2-of-2 rewrites to this site.",
			'optional'    => false,
			'repeating'   => false,
		];

		return [
			[
				'newspack-migration-tools indiegraf import-1-of-2',
				self::get_command_closure( 'cmd_import' ),
				[
					'shortdesc' => 'Step 1: imports Indiegraf CSV exports (users, posts, pages). Every run is a diff run: it creates new, updates changed and skips unchanged content.',
					'synopsis'  => [
						[
							'type'        => 'assoc',
							'name'        => 'users-csv',
							'description' => 'Path to the Users export CSV.',
							'optional'    => false,
							'repeating'   => false,
						],
						[
							'type'        => 'assoc',
							'name'        => 'posts-csv',
							'description' => 'Path to the Posts export CSV.',
							'optional'    => false,
							'repeating'   => false,
						],
						[
							'type'        => 'assoc',
							'name'        => 'pages-csv',
							'description' => 'Path to the Pages export CSV.',
							'optional'    => true,
							'repeating'   => false,
						],
						[
							'type'        => 'assoc',
							'name'        => 'import-roles',
							'description' => 'CSV of source user roles to import. Rows with other roles are skipped, unless they are a post byline. By design, all imported users are imported with the Guest Contributor role.',
							'optional'    => true,
							'repeating'   => false,
							'default'     => 'administrator,editor,author,contributor',
						],
						$live_rest_url,
						[
							'type'        => 'flag',
							'name'        => 'update-already-imported-posts',
							'description' => 'Update posts that were edited on this site since the last import. By default they are skipped and logged as conflicts.',
							'optional'    => true,
						],
					],
				],
			],
			[
				'newspack-migration-tools indiegraf import-2-of-2',
				self::get_command_closure( 'cmd_finalize' ),
				[
					'shortdesc' => 'Step 2, after the media downloader: syncs block media IDs, rewrites links to the source site, and reports permalink mismatches.',
					'synopsis'  => [
						$live_rest_url,
						[
							'type'        => 'assoc',
							'name'        => 'post-ids-file',
							'description' => 'File with comma-separated post IDs to process, written by step 1.',
							'optional'    => true,
							'repeating'   => false,
							'default'     => self::TOUCHED_IDS_FILE,
						],
						[
							'type'        => 'flag',
							'name'        => 'all-imported-posts',
							'description' => 'Process all imported posts and pages instead of the IDs in --post-ids-file.',
							'optional'    => true,
						],
					],
				],
			],
		];
	}

	/**
	 * Callable for `newspack-migration-tools indiegraf import-1-of-2`.
	 *
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function cmd_import( array $pos_args, array $assoc_args ): void {
		$this->logger  = MultiLog::get_cli_and_file_logger( 'indiegraf-import-1-of-2' );
		$this->csv_log = new CsvWriter( 'indiegraf-import-1-of-2.csv' );
		$this->csv_log->set_header( [ 'source_id', 'new_id', 'status', 'message' ] );

		// Arguments.
		$this->set_rest_url( $assoc_args['live-rest-url'] );
		$import_roles = array_filter( array_map( 'trim', explode( ',', $assoc_args['import-roles'] ) ) );
		$post_csvs    = array_filter( [ $assoc_args['posts-csv'], $assoc_args['pages-csv'] ?? null ] );

		// Dependencies: CAP, Newspack guest contributor role, and the plugins for avatars, SEO meta, author profile blocks and the media hand-off.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		// The downloader bundles an older NMT that overrides NCCM's while active, so it runs only in the hand-off, with NCCM deactivated.
		$downloader = 'newspack-post-image-downloader/newspack-post-image-downloader.php';
		if ( is_plugin_active( $downloader ) ) {
			WP_CLI::error( 'Deactivate Newspack Post Image Downloader (wp plugin deactivate newspack-post-image-downloader): its bundled NMT clashes with this importer. The hand-off at the end prints when to activate it.' );
		}
		$cap     = UsersHelper::validate_co_authors_plus();
		$missing = array_filter(
			[
				is_wp_error( $cap ) ? $cap->get_error_message() : null,
				GuestContributorsHelper::validate_newspack_plugin() ? null : GuestContributorsHelper::ERROR_NEWSPACK_PLUGIN,
			]
		);
		$plugins = [
			'simple-local-avatars/simple-local-avatars.php' => 'Simple Local Avatars',
			'wordpress-seo/wp-seo.php'            => 'Yoast SEO',
			'newspack-blocks/newspack-blocks.php' => 'Newspack Blocks',
			'safe-svg/safe-svg.php'               => 'Safe SVG (lets the downloader import SVG images; delete it after import-2-of-2)',
		];
		foreach ( $plugins as $file => $name ) {
			if ( ! is_plugin_active( $file ) ) {
				$missing[] = sprintf( '%s plugin is not active.', $name );
			}
		}
		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $downloader ) ) {
			$missing[] = 'Newspack Post Image Downloader plugin is not installed (install it, but keep it inactive).';
		}
		if ( ! empty( $missing ) ) {
			WP_CLI::error( "Install and activate the missing dependencies, then re-run:\n- " . implode( "\n- ", $missing ) );
		}

		// Site settings gates: the timezone converts source dates to GMT; source URLs are flat /slug/.
		WP_CLI::confirm( sprintf( "This site's timezone is %s. Source post dates are imported in this timezone. If it is not the source site's timezone, set it now (e.g. wp option update timezone_string America/Los_Angeles), then press y to continue.", wp_timezone_string() ) );
		if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
			WP_CLI::confirm( sprintf( "This site's permalink structure is '%s'. This importer relies on /%%postname%%/ permalinks (source URLs are flat /slug/; old slugs redirect via _wp_old_slug). Set them now (wp rewrite structure '/%%postname%%/'), then press y to continue.", get_option( 'permalink_structure' ) ) );
		}
		// Settings changed from another shell while paused are only seen after the options cache is cleared.
		wp_cache_delete( 'alloptions', 'options' );

		foreach ( [ $assoc_args['users-csv'], ...$post_csvs ] as $csv ) {
			$this->normalize_csv_headers( $csv );
		}

		// Column audit and role counts; unmapped columns with data need a confirmation.
		$unmapped = $this->audit_columns( $assoc_args['users-csv'], [ ...array_keys( self::USER_COLUMNS ), ...self::USER_HANDLED_COLUMNS ] );
		foreach ( $post_csvs as $csv ) {
			$unmapped += $this->audit_columns( $csv, [ ...array_keys( self::POST_COLUMNS ), ...array_keys( self::POST_META_COLUMNS ), ...self::POST_HANDLED_COLUMNS ] );
		}
		$role_counts = array_count_values( array_map( fn( $row ) => $this->value( $row, 'User Role' ) ?? '(none)', iterator_to_array( ( new CsvIterator() )->items( $assoc_args['users-csv'], ',' ), false ) ) );
		$this->logger->info( 'Users CSV roles: ' . implode( ', ', array_map( fn( $role, $count ) => "$role: $count", array_keys( $role_counts ), $role_counts ) ) );
		if ( $unmapped > 0 ) {
			WP_CLI::confirm( sprintf( '%d unmapped columns with data, see indiegraf_unmapped_columns_*.csv. Continue?', $unmapped ) );
		}

		$this->start_importing();
		$this->import_users( $assoc_args['users-csv'], $import_roles );
		foreach ( $post_csvs as $csv ) {
			$this->import_posts( $csv, ! empty( $assoc_args['update-already-imported-posts'] ) );
		}

		// Touched post IDs scope the downloader and import-2-of-2 to this run's diff.
		$ids_file = getcwd() . '/' . self::TOUCHED_IDS_FILE;
		if ( false === file_put_contents( $ids_file, implode( ',', array_unique( $this->touched_post_ids ) ) ) ) {
			WP_CLI::error( sprintf( 'Could not write %s', $ids_file ) );
		}

		// Hand-off: newspack-post-image-downloader commands in its README order (scan, images, scan non-images, non-images), then step 2.
		// PDFs only from hosts that also serve the images, i.e. the source's own storage.
		$this->content_hosts['pdf'] = array_intersect_key( $this->content_hosts['pdf'] ?? [], ( $this->content_hosts['wp'] ?? [] ) + ( $this->content_hosts['cdn'] ?? [] ) );
		$posts                      = sprintf( '--post-types=post,page --post-statuses=%s --post-ids-csv=$(cat %s)', implode( ',', self::POST_STATUSES ), $ids_file );
		$hosts                      = fn( string $group ) => implode( ',', array_keys( $this->content_hosts[ $group ] ?? [] ) );
		// Run as an admin: Safe SVG allows SVG uploads only to users who can upload files, and WP-CLI runs as no user.
		$download = sprintf(
			'wp --user=%s newspack-post-image-downloader',
			get_users(
				[
					'role'   => 'administrator',
					'number' => 1,
					'fields' => 'user_login',
				] 
			)[0] ?? '<admin-login>' 
		);
		$commands = array_filter(
			[
				'# Deactivating NCCM for potential clash of NMT dependencies.',
				'wp plugin deactivate newspack-custom-content-migrator',
				'wp plugin activate newspack-post-image-downloader',
				"$download scan-existing-urls $posts",
				'' === $hosts( 'wp' ) ? null : "$download download-images $posts --do-not-download-root-relative-urls --only-download-from-hosts=" . $hosts( 'wp' ),
				'' === $hosts( 'cdn' ) ? null : "$download download-images $posts --do-not-download-root-relative-urls --only-download-from-hosts=" . $hosts( 'cdn' ) . ' --do-not-download-large-sizes',
				"$download scan-existing-urls --include-non-image-urls $posts",
				'' === $hosts( 'pdf' ) ? null : "$download download-non-images-files $posts --do-not-download-root-relative-urls --extensions=pdf --only-download-from-hosts=" . $hosts( 'pdf' ),
				'wp plugin deactivate newspack-post-image-downloader',
				'wp plugin activate newspack-custom-content-migrator',
			]
		);
		$this->logger->info(
			sprintf(
				"%s\nWhen those finish, run:\n  wp newspack-migration-tools indiegraf import-2-of-2 --live-rest-url=%s",
				empty( $this->touched_post_ids )
					? 'Import done. No posts were created or updated, so there is no media to download.'
					: "Import done. Next, download the media in post content, in this order, from the WP root.\nRead newspack-post-image-downloader's README to ensure these commands are correct, and check the hosts and extensions each scan lists:\n  " . implode( "\n  ", $commands ),
				$this->rest_url
			)
		);

		wp_cache_flush();
		WP_CLI::success(
			sprintf(
				"Done (%s).\nSee indiegraf-import-1-of-2.log and check for any warnings and errors; every row's outcome is in indiegraf-import-1-of-2.csv.",
				implode( ', ', array_map( fn( $status, $count ) => "$status: $count", array_keys( $this->log_counts ), $this->log_counts ) )
			)
		);
	}

	/**
	 * Callable for `newspack-migration-tools indiegraf import-2-of-2`.
	 *
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function cmd_finalize( array $pos_args, array $assoc_args ): void {
		global $wpdb;

		$this->logger  = MultiLog::get_cli_and_file_logger( 'indiegraf-import-2-of-2' );
		$this->csv_log = new CsvWriter( 'indiegraf-import-2-of-2.csv' );
		$this->csv_log->set_header( [ 'source_id', 'new_id', 'status', 'message' ] );
		$this->set_rest_url( $assoc_args['live-rest-url'] );

		// Target posts: every imported post, or the IDs step 1 touched (the file may be empty).
		if ( ! empty( $assoc_args['all-imported-posts'] ) ) {
			$post_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s", Posts::UNIQUE_POST_IDENTIFIER_META_KEY, $wpdb->esc_like( self::UID_POST ) . '%' )
			);
		} else {
			$ids = is_readable( $assoc_args['post-ids-file'] ) ? file_get_contents( $assoc_args['post-ids-file'] ) : false; // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
			if ( false === $ids ) {
				WP_CLI::error( sprintf( 'Cannot read %s. Run this from the directory of import-1-of-2, or use --post-ids-file or --all-imported-posts.', $assoc_args['post-ids-file'] ) );
			}
			$post_ids = explode( ',', $ids );
		}
		$post_ids = array_values( array_unique( array_filter( array_map( 'intval', $post_ids ) ) ) );
		$this->logger->info( sprintf( 'Processing %d posts.', count( $post_ids ) ) );

		// Block media IDs and links to the source site; saved without changing post_modified, or the next import sees the post as a conflict.
		$this->start_importing();
		foreach ( $post_ids as $index => $post_id ) {
			MemoryCleanupHook::cleanup( 0, $index, 50 );

			$uid     = (string) get_post_meta( $post_id, Posts::UNIQUE_POST_IDENTIFIER_META_KEY, true );
			$content = get_post_field( 'post_content', $post_id );
			$changed = false;
			$blocks  = $this->sync_block_media_ids( parse_blocks( $content ), $uid, $post_id, $changed );
			$synced  = $changed ? serialize_blocks( $blocks ) : $content;

			// Links: an imported post's source permalink becomes its permalink here, and the source home page this site's; other source links stay.
			$links = 0;
			$tags  = new WP_HTML_Tag_Processor( $synced );
			while ( $tags->next_tag( 'a' ) ) {
				$href = (string) $tags->get_attribute( 'href' );
				if ( ! $this->is_source_url( $href ) ) {
					continue;
				}
				$path      = OriginalPermalink::ensure_path_format( $href );
				$target_id = '' === $path ? null : $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", OriginalValueStore::key_for( OriginalPermalink::KEY ), $path ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$target    = '' === $path ? home_url( '/' ) : ( $target_id ? get_permalink( (int) $target_id ) : null );
				if ( $target ) {
					$tags->set_attribute( 'href', $target );
					++$links;
				}
			}
			$synced = $tags->get_updated_html();

			if ( $synced === $content ) {
				$this->log( $uid, $post_id, 'skipped', 'Block media IDs and links already in sync.' );
				continue;
			}
			$result = Posts::update_post_without_modified_date(
				wp_slash(
					[
						'ID'           => $post_id,
						'post_content' => $synced,
					]
				),
				true
			);
			if ( is_wp_error( $result ) || ! $result ) {
				$this->log( $uid, $post_id, 'error', 'Saving synced block media IDs and links failed: ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'wp_update_post() returned 0' ) );
			} else {
				$this->log( $uid, $post_id, 'updated', sprintf( 'Synced block media IDs%s.', $links ? " and $links links" : '' ) );
			}
		}

		// Permalink report: published posts whose source path differs from this site's, and no _wp_old_slug redirects the source slug.
		$mismatches = 0;
		foreach ( $post_ids as $post_id ) {
			$source_path   = OriginalPermalink::get_post_source_permalink( $post_id );
			$path          = OriginalPermalink::ensure_path_format( (string) get_permalink( $post_id ) );
			$is_same       = mb_strtolower( untrailingslashit( $source_path ) ) === mb_strtolower( untrailingslashit( $path ) );
			$is_redirected = in_array( basename( untrailingslashit( $source_path ) ), get_post_meta( $post_id, '_wp_old_slug' ), true ); // phpcs:ignore -- WordPress.WP.GetMetaSingle.Missing.
			if ( '' === $source_path || 'publish' !== get_post_status( $post_id ) || $is_same || $is_redirected ) {
				continue;
			}
			++$mismatches;
			$this->log( (string) get_post_meta( $post_id, Posts::UNIQUE_POST_IDENTIFIER_META_KEY, true ), $post_id, 'unresolved', sprintf( 'Permalink mismatch: source %s is %s here, and no _wp_old_slug redirects it. Add a redirect.', $source_path, $path ) );
		}

		wp_cache_flush();
		WP_CLI::success(
			sprintf(
				"Done (%s). Permalink mismatches: %d.\nSee indiegraf-import-2-of-2.log for warnings and errors; every post's outcome is in indiegraf-import-2-of-2.csv.\nMigration complete. For a content refresh, get fresh CSVs and re-run indiegraf import-1-of-2. Only new or changed posts are processed.",
				implode( ', ', array_map( fn( $status, $count ) => "$status: $count", array_keys( $this->log_counts ), $this->log_counts ) ),
				$mismatches
			)
		);

		// Safe SVG was only required so the downloader could import SVG images.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( is_plugin_active( 'safe-svg/safe-svg.php' ) ) {
			WP_CLI::warning( 'REMINDER: delete the Safe SVG plugin now, unless the site should allow SVG uploads: wp plugin deactivate safe-svg --uninstall' );
		}
	}

	/**
	 * Makes a CSV header safe for CsvIterator: removes a leading BOM and renames repeated column names to {name}2, {name}3.
	 *
	 * Only the header line is rewritten; the rest of the file is written back as-is. The original is backed up once to
	 * {name}__originalBackup.csv (an existing backup is never overwritten). Idempotent.
	 *
	 * @param string $path CSV file path.
	 *
	 * @return void
	 */
	private function normalize_csv_headers( string $path ): void {
		$content = is_writable( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		if ( false === $content ) {
			WP_CLI::error( sprintf( 'CSV is not readable and writable: %s', $path ) );
		}

		// Parse the header record; its end offset splits header from body.
		$has_bom = str_starts_with( $content, "\xEF\xBB\xBF" );
		$stream  = fopen( 'php://memory', 'r+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $stream, $has_bom ? substr( $content, 3 ) : $content ); // phpcs:ignore -- WordPress.WP.AlternativeFunctions.file_system_operations_fwrite.
		rewind( $stream );
		$header      = fgetcsv( $stream, null, ',', '"', '' );
		$body_offset = ftell( $stream );
		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$raw_header = substr( $content, $has_bom ? 3 : 0, $body_offset );
		if ( ! mb_check_encoding( $raw_header, 'UTF-8' ) ) {
			WP_CLI::error( sprintf( 'CSV header is not valid UTF-8, the file was not changed. Convert it to UTF-8 and re-run: %s', $path ) );
		}

		// Rename repeated column names: the Nth occurrence of "Title" becomes "TitleN".
		$messages    = $has_bom ? [ 'Removed the UTF-8 BOM.' ] : [];
		$name_counts = [];
		foreach ( $header as $index => $name ) {
			$name_counts[ $name ] = ( $name_counts[ $name ] ?? 0 ) + 1;
			if ( $name_counts[ $name ] > 1 ) {
				$header[ $index ] = $name . $name_counts[ $name ];
				$messages[]       = sprintf( "Column '%s' #%d (col %d) was renamed to '%s'.", $name, $name_counts[ $name ], $index + 1, $header[ $index ] );
			}
		}
		if ( count( array_unique( $header ) ) !== count( $header ) ) {
			WP_CLI::error( sprintf( 'CSV header still has duplicate names after renaming, fix it by hand: %s', $path ) );
		}
		if ( empty( $messages ) ) {
			return;
		}

		// Back up once, then write the new header line (keeping the file's own line ending) + the untouched body.
		$backup = preg_replace( '/\.csv$/i', '', $path ) . '__originalBackup.csv';
		if ( ! file_exists( $backup ) && ! copy( $path, $backup ) ) {
			WP_CLI::error( sprintf( 'Could not back up %s to %s', $path, $backup ) );
		}
		$eol         = str_ends_with( $raw_header, "\r\n" ) ? "\r\n" : "\n";
		$header_line = implode( ',', array_map( fn( $name ) => '"' . str_replace( '"', '""', $name ) . '"', $header ) ) . $eol;
		if ( false === file_put_contents( $path, $header_line . substr( $content, ( $has_bom ? 3 : 0 ) + $body_offset ) ) ) {
			WP_CLI::error( sprintf( 'Could not write %s; the original is in %s', $path, $backup ) );
		}

		$this->logger->notice( sprintf( '%s: %s Backup: %s', $path, implode( ' ', $messages ), $backup ) );
	}

	/**
	 * Reports non-empty columns that are neither mapped nor ignored to indiegraf_unmapped_columns_{csv}.csv.
	 *
	 * @param string $path          CSV file path.
	 * @param array  $known_columns Mapped and handled column names.
	 *
	 * @return int Number of unmapped columns that hold data.
	 */
	private function audit_columns( string $path, array $known_columns ): int {
		// Count non-empty values of columns that no list covers.
		$unmapped_columns = null;
		$fill_counts      = [];
		$samples          = [];
		foreach ( ( new CsvIterator() )->items( $path, ',' ) as $row ) {
			$unmapped_columns ??= array_filter(
				array_keys( $row ),
				fn( $column ) => ! in_array( $column, $known_columns, true )
					&& empty( array_filter( self::IGNORED_COLUMNS, fn( $pattern ) => fnmatch( $pattern, $column ) ) )
			);
			foreach ( $unmapped_columns as $column ) {
				if ( null !== $this->value( $row, $column ) ) {
					$fill_counts[ $column ] = ( $fill_counts[ $column ] ?? 0 ) + 1;
					$samples[ $column ]   ??= mb_substr( $row[ $column ], 0, 100 );
				}
			}
		}

		// Rewrite the report from scratch on each run.
		$report = sprintf( 'indiegraf_unmapped_columns_%s.csv', pathinfo( $path, PATHINFO_FILENAME ) );
		if ( file_exists( $report ) ) {
			wp_delete_file( $report );
		}
		if ( empty( $fill_counts ) ) {
			return 0;
		}
		$writer = new CsvWriter( $report );
		$writer->set_header( [ 'column', 'fill_count', 'sample' ] );
		foreach ( $fill_counts as $column => $count ) {
			$writer->put( [ $column, $count, $samples[ $column ] ] );
		}
		$writer->close();
		$this->logger->warning( sprintf( '%s: %d unmapped columns with data (%s), see %s', $path, count( $fill_counts ), implode( ', ', array_keys( $fill_counts ) ), $report ) );

		return count( $fill_counts );
	}

	/**
	 * Reads a column value; absent and empty columns both return null (plan section 0.1).
	 *
	 * @param array  $row    CSV row.
	 * @param string $column Column name.
	 *
	 * @return string|null Value, or null when absent or empty.
	 */
	private function value( array $row, string $column ): ?string {
		$value = $row[ $column ] ?? '';

		return '' === $value ? null : $value;
	}

	/**
	 * Normalizes a CSV date (e.g. single-digit hours) to MySQL format.
	 *
	 * @param string|null $date Date as exported.
	 *
	 * @return string|null "Y-m-d H:i:s", or null when empty or unparseable.
	 */
	private function to_mysql_date( ?string $date ): ?string {
		$timestamp = null === $date ? false : strtotime( $date );

		return false === $timestamp ? null : gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Validates and sets the --live-rest-url source site URL.
	 *
	 * @param string $url Source site URL.
	 *
	 * @return void
	 */
	private function set_rest_url( string $url ): void {
		if ( ! wp_http_validate_url( $url ) ) {
			WP_CLI::error( sprintf( 'Invalid --live-rest-url: %s', $url ) );
		}
		$this->rest_url = untrailingslashit( $url );
	}

	/**
	 * Whether a URL is on the source site (--live-rest-url host, with or without "www.").
	 *
	 * @param string $url URL.
	 *
	 * @return bool
	 */
	private function is_source_url( string $url ): bool {
		$host = fn( ?string $any_url ) => preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( (string) $any_url, PHP_URL_HOST ) ) );

		return '' !== $host( $url ) && $host( $url ) === $host( $this->rest_url );
	}

	/**
	 * GETs a path from the source site's public REST API, once per run.
	 *
	 * On failure (or before --live-rest-url is set) this returns null, and the first failure prints one warning.
	 * Callers check the response shape before use.
	 *
	 * @param string $path Path under /wp-json/wp/v2/, e.g. "media/981".
	 *
	 * @return array|null Decoded JSON body, or null.
	 */
	private function rest_get( string $path ): ?array {
		if ( null === $this->rest_url ) {
			return null;
		}
		if ( array_key_exists( $path, $this->rest_cache ) ) {
			return $this->rest_cache[ $path ];
		}

		$url      = $this->rest_url . '/wp-json/wp/v2/' . ltrim( $path, '/' );
		$response = wp_remote_get( $url, [ 'timeout' => 20 ] ); // phpcs:ignore -- WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout.
		$code     = wp_remote_retrieve_response_code( $response );
		$data     = 200 === $code ? json_decode( wp_remote_retrieve_body( $response ), true ) : null;
		$data     = is_array( $data ) ? $data : null;

		// Warn on the first failure only; later ones show up in the per-field log entries.
		if ( null === $data && ! in_array( null, $this->rest_cache, true ) ) {
			$reason = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . $code;
			$this->logger->warning( sprintf( 'REST lookup failed (%s: %s). Fields that need REST are skipped and logged.', $url, $reason ) );
		}
		$this->rest_cache[ $path ] = $data;

		return $data;
	}

	/**
	 * Records one per-row event in the audit CSV; events that need review also go to the log (CLI + file).
	 *
	 * @param string   $source_id Source unique identifier, e.g. "indiegraf-user-25".
	 * @param int|null $new_id    ID on this site, if any.
	 * @param string   $status    One of: created | updated | skipped | conflict | missing | unresolved | dropped | error.
	 * @param string   $message   Details.
	 *
	 * @return void
	 */
	private function log( string $source_id, ?int $new_id, string $status, string $message = '' ): void {
		$this->csv_log->put( [ $source_id, $new_id ?? '', $status, $message ] );
		$this->log_counts[ $status ] = ( $this->log_counts[ $status ] ?? 0 ) + 1;

		if ( in_array( $status, [ 'created', 'updated', 'skipped' ], true ) ) {
			return;
		}
		$this->logger->log( 'error' === $status ? 'error' : 'warning', sprintf( '%s %s%s: %s', $status, $source_id, null === $new_id ? '' : " -> $new_id", $message ) );
	}

	/**
	 * Saves posts like a WP importer: no pings or enclosures on publish, no kses stripping of iframes and scripts
	 * (WP-CLI runs as no user), and no revisions.
	 *
	 * @return void
	 */
	private function start_importing(): void {
		if ( ! defined( 'WP_IMPORTING' ) ) {
			define( 'WP_IMPORTING', true );
		}
		kses_remove_filters();
		add_filter( 'wp_revisions_to_keep', '__return_zero' );
	}

	/**
	 * Imports the Users CSV rows whose role is in $import_roles; keeps the other rows for byline lookups.
	 *
	 * @param string $csv          Users CSV file path.
	 * @param array  $import_roles Source roles to import.
	 *
	 * @return void
	 */
	private function import_users( string $csv, array $import_roles ): void {
		foreach ( ( new CsvIterator() )->items( $csv, ',' ) as $index => $row ) {
			MemoryCleanupHook::cleanup( 0, $index, 50 );

			// Without a User Role column there is nothing to filter on, so every row is imported.
			$role = $this->value( $row, 'User Role' );
			if ( array_key_exists( 'User Role', $row ) && ! in_array( $role, $import_roles, true ) ) {
				$this->skipped_user_rows[ $this->value( $row, 'ID' ) ] = $row;
				$this->log( self::UID_USER . $this->value( $row, 'ID' ), null, 'skipped', sprintf( "Role '%s' is not in --import-roles; imported later only if it is a post byline.", $role ) );
				continue;
			}

			$this->upsert_user( $row );
		}

		wp_cache_flush();
	}

	/**
	 * Creates a guest contributor from a Users CSV row, or refreshes the existing one.
	 *
	 * @param array $row Users CSV row.
	 *
	 * @return WP_User|null The user, or null on failure.
	 */
	private function upsert_user( array $row ): ?WP_User {
		$source_id = $this->value( $row, 'ID' );
		if ( null === $source_id ) {
			$this->log( '', null, 'error', 'Users CSV row without ID: ' . wp_json_encode( array_filter( $row ) ) );

			return null;
		}
		$uid = self::UID_USER . $source_id;

		// Build user data. Null marks a present-but-empty column: skipped on create, cleared on refresh. Absent columns are left out.
		$data = [];
		foreach ( self::USER_COLUMNS as $column => $field ) {
			if ( array_key_exists( $column, $row ) ) {
				$data[ $field ] = $this->value( $row, $column );
			}
		}
		// Identity fields are never cleared: WP would refill them from user_login (an email here) or the current date.
		$registered = $this->to_mysql_date( $data['user_registered'] ?? null );
		unset( $data['user_registered'] );
		if ( null !== $registered ) {
			$data['user_registered'] = $registered;
		}
		$display_name = $this->value( $row, 'Display Name' );
		if ( null !== $display_name ) {
			$data['display_name']  = $display_name;
			$data['nickname']      = $display_name;
			$data['user_nicename'] = substr( sanitize_title( $display_name ), 0, 50 );
		}
		if ( array_key_exists( 'biography', $row ) || array_key_exists( 'Description', $row ) ) {
			$biography           = $this->value( $row, 'biography' ) ?? $this->value( $row, 'Description' );
			$data['description'] = null === $biography ? null : $this->clean_biography( $biography );
		}

		// Refresh the existing user, or create it.
		$user = UsersHelper::get_user_by_unique_identifier( $uid );
		if ( $user ) {
			// Email is never refreshed (WP-ENTITIES) and changing it would email the user; wp_update_user() ignores user_login.
			unset( $data['user_email'], $data['user_login'] );
			// A nicename de-duplicated on create ("marc-hand1") is kept, or WP would re-suffix it and change the author URL.
			if ( str_starts_with( $user->user_nicename, $data['user_nicename'] ?? "\0" ) ) {
				unset( $data['user_nicename'] );
			}
			$result = wp_update_user( [ 'ID' => $user->ID ] + array_map( fn( $value ) => $value ?? '', $data ) );
			if ( is_wp_error( $result ) ) {
				$this->log( $uid, $user->ID, 'error', 'Refresh failed: ' . $result->get_error_message() );

				return null;
			}
			$user   = get_user_by( 'id', $user->ID );
			$status = 'updated';
		} else {
			$user = GuestContributorsHelper::create_or_get_contributor( array_filter( $data, fn( $value ) => null !== $value ), $uid );
			if ( is_wp_error( $user ) ) {
				$this->log( $uid, null, 'error', 'Create failed: ' . $user->get_error_message() );

				return null;
			}
			$status = 'created';
		}

		// Job title and avatar: an empty value on refresh removes them.
		if ( array_key_exists( 'title', $row ) ) {
			if ( null === $this->value( $row, 'title' ) ) {
				delete_user_meta( $user->ID, 'newspack_job_title' );
			} else {
				update_user_meta( $user->ID, 'newspack_job_title', $this->value( $row, 'title' ) );
			}
		}
		if ( array_key_exists( 'profile_picture', $row ) ) {
			$media_id = (int) $this->value( $row, 'profile_picture' );
			if ( $media_id > 0 ) {
				$this->set_user_avatar( $user->ID, $media_id );
			} else {
				delete_user_meta( $user->ID, SimpleLocalAvatars::AVATAR_META_KEY );
			}
		}

		$this->log( $uid, $user->ID, $status );

		return $user;
	}

	/**
	 * Cleans an Indiegraf author biography for the user description.
	 *
	 * @param string $biography Biography HTML.
	 *
	 * @return string Clean biography.
	 */
	private function clean_biography( string $biography ): string {
		$wrapper_start = '<div class="cover-description">';
		if ( str_starts_with( $biography, $wrapper_start ) && str_ends_with( $biography, '</div>' ) ) {
			$biography = substr( $biography, strlen( $wrapper_start ), -strlen( '</div>' ) );
		}

		return trim( wp_kses_post( $biography ) );
	}

	/**
	 * Sets a user's Simple Local Avatar from a source media ID, resolved to a file URL via REST.
	 *
	 * @param int $user_id         User ID.
	 * @param int $source_media_id Media ID on the source site.
	 *
	 * @return void
	 */
	private function set_user_avatar( int $user_id, int $source_media_id ): void {
		static $avatars = null;

		// Resolve the file URL via REST.
		$uid = (string) get_user_meta( $user_id, UsersHelper::UNIQUE_IDENTIFIER_META_KEY, true );
		$url = $this->rest_get( 'media/' . $source_media_id )['source_url'] ?? null;
		if ( ! is_string( $url ) || ! wp_http_validate_url( $url ) ) {
			$this->log( $uid, $user_id, 'unresolved', sprintf( 'Avatar: source media %d has no source_url (REST lookup failed).', $source_media_id ) );

			return;
		}

		// Existence check first, because import_external_file() downloads the file before it dedupes.
		$attachment_id = Attachments::get_attachment_by_unique_identifier( $url )
			?: Attachments::import_external_file( $url, null, null, null, null, 0, [], '', true, $url ); // phpcs:ignore -- Universal.Operators.DisallowShortTernary.Found.
		if ( is_wp_error( $attachment_id ) ) {
			$this->log( $uid, $user_id, 'error', sprintf( 'Avatar: import of %s failed: %s', $url, $attachment_id->get_error_message() ) );

			return;
		}

		// SimpleLocalAvatars registers its plugin hooks on construction, so build it once per run.
		$avatars ??= new SimpleLocalAvatars();
		if ( (int) $avatars->get_local_avatar_attachment_id( $user_id ) !== $attachment_id ) {
			$avatars->assign_avatar( $user_id, $attachment_id );
		}
	}

	/**
	 * Resolves a byline to a user, importing or creating one when needed.
	 *
	 * Order:
	 * 1. the imported user of the post's Author ID, when its name matches (disambiguates duplicate names);
	 * 2. a guest contributor with that exact display name;
	 * 3. a Users CSV row skipped by --import-roles with that name (Author ID's row first), imported now;
	 * 4. a new byline-only user, enriched from the source REST user when its name matches (D11).
	 * An empty $display_name (pages have no Authors column) resolves by Author ID only; an author found nowhere
	 * becomes a placeholder user "AuthorID_{id}".
	 *
	 * @param string   $display_name     Byline name, or '' to resolve by Author ID only.
	 * @param int|null $source_author_id Source post author ID (the "Author ID" column).
	 *
	 * @return WP_User|null The user, or null on failure.
	 */
	private function get_or_create_byline_user( string $display_name, ?int $source_author_id ): ?WP_User {
		$display_name   = trim( $display_name );
		$is_placeholder = false;
		$author_user    = $source_author_id ? UsersHelper::get_user_by_unique_identifier( self::UID_USER . $source_author_id ) : false;

		// No byline name: use the Author ID's user, or its name from REST, or a placeholder.
		if ( '' === $display_name ) {
			if ( ! $source_author_id ) {
				return null;
			}
			if ( $author_user ) {
				return $author_user;
			}
			$display_name = $this->value( $this->skipped_user_rows[ $source_author_id ] ?? [], 'Display Name' )
				?? $this->rest_get( 'users/' . $source_author_id )['name']
				?? '';
			if ( ! is_string( $display_name ) || '' === $display_name ) {
				$display_name   = 'AuthorID_' . $source_author_id;
				$is_placeholder = true;
			}
		}

		$uid          = self::UID_BYLINE . sanitize_title( $display_name );
		$is_same_name = fn( string $name ) => remove_accents( mb_strtolower( trim( $name ) ) ) === remove_accents( mb_strtolower( $display_name ) );

		// 1. Author ID's imported user.
		if ( $author_user && $is_same_name( $author_user->display_name ) ) {
			return $author_user;
		}

		// 2. Existing guest contributor with the exact name.
		$user_ids = GuestContributorsHelper::get_by_display_name( $display_name );
		if ( ! is_wp_error( $user_ids ) && ! empty( $user_ids ) ) {
			$user_ids = array_values( $user_ids );
			if ( count( $user_ids ) > 1 ) {
				$this->log( $uid, $user_ids[0], 'unresolved', sprintf( 'Ambiguous byline "%s" matches users %s; used the first.', $display_name, implode( ', ', $user_ids ) ) );
			}

			return get_user_by( 'id', $user_ids[0] ) ?: null; // phpcs:ignore -- Universal.Operators.DisallowShortTernary.Found.
		}

		// 3. Role-skipped Users CSV row with that name, the Author ID's row first.
		$skipped_rows = $this->skipped_user_rows;
		if ( isset( $skipped_rows[ $source_author_id ] ) ) {
			$skipped_rows = [ $source_author_id => $skipped_rows[ $source_author_id ] ] + $skipped_rows;
		}
		foreach ( $skipped_rows as $source_id => $row ) {
			if ( $is_same_name( $this->value( $row, 'Display Name' ) ?? '' ) ) {
				unset( $this->skipped_user_rows[ $source_id ] );

				return $this->upsert_user( $row );
			}
		}

		// 4. New byline-only user; Author ID is the source post_author account, often not the byline, so only a same-name REST user enriches it.
		$data        = [ 'display_name' => $display_name ];
		$rest_author = null;
		if ( $source_author_id && ! $author_user ) {
			$rest_user   = $this->rest_get( 'users/' . $source_author_id );
			$rest_author = is_string( $rest_user['name'] ?? null ) && $is_same_name( $rest_user['name'] ) ? ( $rest_user['acf'] ?? [] ) : null;
		}
		if ( is_string( $rest_author['biography'] ?? null ) && '' !== $rest_author['biography'] ) {
			$data['description'] = $this->clean_biography( $rest_author['biography'] );
		}
		if ( is_string( $rest_author['title'] ?? null ) && '' !== $rest_author['title'] ) {
			$data['meta_input']['newspack_job_title'] = $rest_author['title'];
		}
		$user = GuestContributorsHelper::create_or_get_contributor( $data, $uid );
		if ( is_wp_error( $user ) ) {
			$this->log( $uid, null, 'error', sprintf( 'Byline "%s": create failed: %s', $display_name, $user->get_error_message() ) );

			return null;
		}
		if ( is_numeric( $rest_author['profile_picture'] ?? null ) ) {
			$this->set_user_avatar( $user->ID, (int) $rest_author['profile_picture'] );
		}
		if ( $is_placeholder ) {
			$this->log( $uid, $user->ID, 'unresolved', sprintf( 'Author ID %d is neither in the Users CSV nor readable via REST; created placeholder user "%s".', $source_author_id, $display_name ) );
		} else {
			$this->log( $uid, $user->ID, 'created', sprintf( 'Byline-only user "%s"%s.', $display_name, null === $rest_author ? '' : ', enriched from source REST user ' . $source_author_id ) );
		}

		return $user;
	}

	/**
	 * Creates, updates or skips each post or page of a CSV (plan 0.2), then reports imported ones missing from it.
	 *
	 * @param string $csv              Posts or Pages CSV file path.
	 * @param bool   $update_conflicts Also update posts edited on this site since their import (--update-already-imported-posts).
	 *
	 * @return void
	 */
	private function import_posts( string $csv, bool $update_conflicts ): void {
		global $wpdb;

		$taxonomy   = new Taxonomy();
		$seen_uids  = [];
		$post_types = [];

		// Live data (REST shows published posts only): each post's exact categories, as CSV paths omit an assigned parent; its rendered
		// content, which has the current image URLs (the CSV may have pre-CDN ones that 404); and the category tree.
		$live_posts         = [];
		$category_tree      = [];
		$term_ids_by_source = [];
		$rows               = iterator_to_array( ( new CsvIterator() )->items( $csv, ',' ), false );
		foreach ( [
			'post' => 'posts',
			'page' => 'pages',
		] as $post_type => $rest_base ) {
			$source_ids = array_column( array_filter( $rows, fn( $row ) => ( $this->value( $row, 'Post Type' ) ?? 'post' ) === $post_type && null !== $this->value( $row, 'ID' ) ), 'ID' );
			foreach ( array_chunk( $source_ids, 100 ) as $chunk ) {
				foreach ( $this->rest_get( sprintf( '%s?include=%s&per_page=100&_fields=id,categories,content', $rest_base, implode( ',', $chunk ) ) ) ?? [] as $live_post ) {
					$live_posts[ (int) ( $live_post['id'] ?? 0 ) ] = $live_post;
				}
			}
		}
		if ( array_key_exists( 'Categories', $rows[0] ?? [] ) ) {
			$page = 0;
			do {
				$terms = $this->rest_get( sprintf( 'categories?per_page=100&page=%d&_fields=id,name,slug,parent', ++$page ) ) ?? [];
				foreach ( $terms as $term ) {
					$category_tree[ (int) ( $term['id'] ?? 0 ) ] = $term;
				}
			} while ( 100 === count( $terms ) ); // phpcs:ignore -- Squiz.PHP.DisallowSizeFunctionsInLoops.Found.
		}
		unset( $rows );

		foreach ( ( new CsvIterator() )->items( $csv, ',' ) as $index => $row ) {
			MemoryCleanupHook::cleanup( 0, $index, 50 );

			$source_id = $this->value( $row, 'ID' );
			$post_type = $this->value( $row, 'Post Type' ) ?? 'post';
			if ( null === $source_id || ! in_array( $post_type, [ 'post', 'page' ], true ) ) {
				$this->log( self::UID_POST . $source_id, null, 'error', sprintf( 'Row skipped: no ID, or post type "%s" is not post or page.', $post_type ) );
				continue;
			}
			$uid                      = self::UID_POST . $source_id;
			$seen_uids[ $uid ]        = true;
			$post_types[ $post_type ] = true;

			// Diff: skip when the source is unchanged; a post edited here since its import is a conflict, skipped unless $update_conflicts.
			$source_modified = $this->to_mysql_date( $this->value( $row, 'Post Modified Date' ) );
			$post_id         = Posts::get_post_by_unique_identifier( $uid );
			if ( $post_id ) {
				$imported_modified = (string) OriginalValueStore::get_for_post( $post_id, 'modified' );
				$is_conflict       = '' !== $imported_modified && get_post_field( 'post_modified', $post_id ) !== $imported_modified;
				if ( $is_conflict && ! $update_conflicts ) {
					$this->log( $uid, $post_id, 'conflict', sprintf( 'Edited on this site after the import (%s), skipped. Use --update-already-imported-posts to overwrite it.', get_post_field( 'post_modified', $post_id ) ) );
					continue;
				}
				if ( ! $is_conflict && null !== $source_modified && $source_modified <= $imported_modified ) {
					$this->log( $uid, $post_id, 'skipped', 'Unchanged.' );
					continue;
				}
			}

			// Post fields. Null marks a present-but-empty column: skipped on create, cleared on update. Absent columns are left out.
			$data = [ 'post_type' => $post_type ];
			foreach ( self::POST_COLUMNS as $column => $field ) {
				if ( array_key_exists( $column, $row ) ) {
					$data[ $field ] = $this->value( $row, $column );
				}
			}
			if ( isset( $data['post_status'] ) && ! in_array( $data['post_status'], self::POST_STATUSES, true ) ) {
				$this->log( $uid, $post_id ?: null, 'unresolved', sprintf( 'Unknown status "%s", imported as draft.', $data['post_status'] ) ); // phpcs:ignore -- Universal.Operators.DisallowShortTernary.Found.
				$data['post_status'] = 'draft';
			}
			// A 1970 date is an unset draft date: WP sets one.
			$date = $this->to_mysql_date( $this->value( $row, 'Date' ) );
			if ( null !== $date && ! str_starts_with( $date, '1970-01-01' ) ) {
				$data['post_date']     = $date;
				$data['post_date_gmt'] = get_gmt_from_date( $date );
			}
			$notes   = [];
			$content = null;
			if ( array_key_exists( 'Content', $row ) ) {
				/**
				 * Live image URLs. The CSV has the source's local image URLs, but the live site renders images from a dedicated image CDN,
				 * and the local files may be gone (404), e.g.:
				 * - CSV:  https://yountvillesun.com/wp-content/uploads/2025/07/Fair-Farm-Set-Up-1-1024x768.jpg (404)
				 * - live: https://d1qvdom7axrrra.cloudfront.net/wp-content/uploads/2025/07/31184942/Fair-Farm-Set-Up-1-1024x768.jpg
				 * The importer keeps the CSV content and uses the live HTML only to look up each image's current URL, matched by
				 * month and file name ("2025/07/Fair-Farm-Set-Up-1-1024x768.jpg"). The downloader then downloads from the CDN URL.
				 * Path shape: "/uploads/YYYY/MM/[{timestamp}/]file"; the optional timestamp folder is the CDN copy's.
				 */
				$upload_key = fn( string $url ) => preg_match( '#/uploads/(?<month>\d{4}/\d{2})/(?:\d+/)?(?<file>[^/]+)$#', (string) wp_parse_url( $url, PHP_URL_PATH ), $match ) ? $match['month'] . '/' . $match['file'] : null;
				$live_urls  = [];
				$tags       = new WP_HTML_Tag_Processor( $live_posts[ (int) $source_id ]['content']['rendered'] ?? '' );
				while ( $tags->next_tag( 'img' ) ) {
					foreach ( [ (string) $tags->get_attribute( 'src' ), ...explode( ',', (string) $tags->get_attribute( 'srcset' ) ) ] as $candidate ) {
						$url = strtok( trim( $candidate ), ' ' );
						if ( $url && $upload_key( $url ) ) {
							$live_urls[ $upload_key( $url ) ] ??= $url;
						}
					}
				}
				$content      = $this->value( $row, 'Content' ) ?? '';
				$replacements = [];
				$tags         = new WP_HTML_Tag_Processor( $content );
				while ( $tags->next_tag( 'img' ) ) {
					$src      = (string) $tags->get_attribute( 'src' );
					$live_url = $live_urls[ $upload_key( $src ) ?? '' ] ?? $src;
					if ( $live_url !== $src ) {
						$replacements[ $src ] = $live_url;
					}
				}
				if ( $replacements ) {
					$content = strtr( $content, $replacements );
					$notes[] = sprintf( '%d image URLs replaced with their live URLs.', count( $replacements ) );
				}
				$content              = $this->transform_content( $content, $uid );
				$data['post_content'] = $content;
			}
			if ( array_key_exists( 'Parent', $row ) ) {
				$source_parent_id    = (int) $this->value( $row, 'Parent' );
				$data['post_parent'] = $source_parent_id ? Posts::get_post_by_unique_identifier( self::UID_POST . $source_parent_id ) : 0;
				if ( false === $data['post_parent'] ) {
					$this->log( $uid, $post_id ?: null, 'unresolved', sprintf( 'Parent %d is not imported (yet); parent not set.', $source_parent_id ) ); // phpcs:ignore -- Universal.Operators.DisallowShortTernary.Found.
					unset( $data['post_parent'] );
				}
			}
			// Tags by name; tags_input replaces the post's tags.
			if ( array_key_exists( 'Tags', $row ) ) {
				$data['tags_input'] = array_map( 'html_entity_decode', explode( '|', $this->value( $row, 'Tags' ) ?? '' ) );
			}
			// Categories as name paths: the live post's exact categories when REST returned it, else the CSV's pipe-separated "Parent>Child" paths.
			if ( array_key_exists( 'Categories', $row ) ) {
				$csv_paths       = array_filter( array_map( fn( $path ) => array_values( array_filter( array_map( fn( $name ) => trim( html_entity_decode( $name ) ), explode( '>', $path ) ) ) ), explode( '|', $this->value( $row, 'Categories' ) ?? '' ) ) );
				$live_paths      = [];
				$live_categories = $live_posts[ (int) $source_id ]['categories'] ?? null;
				foreach ( is_array( $live_categories ) ? $live_categories : [] as $live_term_id ) {
					$path = [];
					for ( $term_id = (int) $live_term_id; isset( $category_tree[ $term_id ] ); $term_id = (int) $category_tree[ $term_id ]['parent'] ) {
						$path = [ $term_id => trim( html_entity_decode( $category_tree[ $term_id ]['name'] ) ) ] + $path;
					}
					// A path is complete only when the walk reached the root.
					$live_paths[ (int) $live_term_id ] = 0 === $term_id ? $path : null;
				}
				$is_live = is_array( $live_categories ) && ! in_array( null, $live_paths, true );

				// Log the source only when it matters: REST differs from the CSV, or REST lacks the post.
				$live_list = $is_live ? array_map( fn( $names ) => implode( '>', $names ), $live_paths ) : [];
				$csv_list  = array_map( fn( $names ) => implode( '>', $names ), $csv_paths );
				if ( $is_live && ( array_diff( $live_list, $csv_list ) || array_diff( $csv_list, $live_list ) ) ) {
					$notes[] = sprintf( 'Categories from live REST "%s"; the CSV lists "%s".', implode( '|', $live_list ), implode( '|', $csv_list ) );
				} elseif ( ! $is_live ) {
					$notes[] = 'Categories from the CSV: the post is not in live REST (e.g. a draft).';
				}

				// Each path segment is get-or-created under the previous one; the post gets the last one.
				// Live terms are keyed by their source ID and created with their live slug, so same-name siblings (2 top-level "Food & Wine") stay separate.
				$data['post_category'] = [];
				foreach ( $is_live ? $live_paths : $csv_paths as $key => $names ) {
					$term_id = 0;
					foreach ( $names as $segment_id => $name ) {
						$args    = [
							'cat_name'        => $name,
							'category_parent' => $term_id,
						];
						$term_id = $is_live
							? $taxonomy->get_or_create_category( $args + [ 'category_nicename' => $category_tree[ $segment_id ]['slug'] ?? '' ], self::UID_CATEGORY . $segment_id )
							: $taxonomy->get_or_create_category( $args );
						// A term created earlier from a CSV path, with the same name and slug, becomes this live term.
						if ( is_wp_error( $term_id ) && 'term_exists' === $term_id->get_error_code() ) {
							$term_id = (int) $term_id->get_error_data();
							update_term_meta( $term_id, Taxonomy::UNIQUE_CATEGORY_IDENTIFIER_META_KEY, self::UID_CATEGORY . $segment_id );
						}
						if ( is_wp_error( $term_id ) ) {
							$this->log( $uid, $post_id ?: null, 'error', sprintf( 'Category "%s": %s', implode( '>', $names ), $term_id->get_error_message() ) ); // phpcs:ignore -- Universal.Operators.DisallowShortTernary.Found.
							$term_id = 0;
							break;
						}
					}
					if ( $term_id ) {
						$data['post_category'][] = (int) $term_id;
						if ( $is_live ) {
							$term_ids_by_source[ $key ] = (int) $term_id;
						}
					}
				}
			}

			// Create, or update in place.
			if ( $post_id ) {
				$result = Posts::update_post_without_modified_date( wp_slash( [ 'ID' => $post_id ] + array_map( fn( $value ) => $value ?? '', $data ) ), true );
				$status = 'updated';
			} else {
				$result = Posts::create_or_get_post( wp_slash( array_filter( $data, fn( $value ) => null !== $value ) ), $uid );
				$status = 'created';
			}
			if ( is_wp_error( $result ) || ! $result ) {
				$this->log( $uid, $post_id ?: null, 'error', ucfirst( substr( $status, 0, -1 ) ) . ' failed: ' . ( is_wp_error( $result ) ? $result->get_error_message() : 'wp_insert_post() returned 0' ) ); // phpcs:ignore -- Universal.Operators.DisallowShortTernary.Found.
				continue;
			}
			$post_id = $result;

			// Yoast primary category: the post's only category, or the source term's local ID (mapped from live categories) when the post has it.
			if ( array_key_exists( '_yoast_wpseo_primary_category', $row ) ) {
				$source_term_id = (int) $this->value( $row, '_yoast_wpseo_primary_category' );
				$category_ids   = wp_get_post_categories( $post_id );
				$primary_id     = 1 === count( $category_ids ) ? $category_ids[0] : ( $term_ids_by_source[ $source_term_id ] ?? null );
				if ( $primary_id && $source_term_id && in_array( $primary_id, $category_ids, true ) ) {
					update_post_meta( $post_id, '_yoast_wpseo_primary_category', $primary_id );
				} else {
					delete_post_meta( $post_id, '_yoast_wpseo_primary_category' );
					if ( $source_term_id ) {
						$this->log( $uid, $post_id, 'unresolved', sprintf( 'Primary category: source term %d is not among the post categories (%s).', $source_term_id, isset( $category_tree[ $source_term_id ] ) ? 'not assigned to the post' : 'not in the live category tree, deleted on the source?' ) );
					}
				}
			}

			// Authors in byline order; pages have no Authors column and resolve by Author ID.
			if ( array_key_exists( 'Authors', $row ) || array_key_exists( 'Author ID', $row ) ) {
				$source_author_id = (int) $this->value( $row, 'Author ID' );
				$user_ids         = [];
				foreach ( explode( '|', $this->value( $row, 'Authors' ) ?? '' ) as $name ) {
					$user = $this->get_or_create_byline_user( $name, $source_author_id ?: null ); // phpcs:ignore -- Universal.Operators.DisallowShortTernary.Found.
					if ( $user ) {
						$user_ids[] = $user->ID;
					} else {
						$this->log( $uid, $post_id, 'unresolved', sprintf( 'Byline "%s" (Author ID %d) has no user.', $name, $source_author_id ) );
					}
				}
				$result = empty( $user_ids ) ? true : UsersHelper::assign_authors_to_post( $post_id, array_values( array_unique( $user_ids ) ) );
				if ( is_wp_error( $result ) ) {
					$this->log( $uid, $post_id, 'error', 'Authors: ' . $result->get_error_message() );
				}
			}

			// Featured image (pipe item 0), imported once per source URL; hidden in the header when the content opens with media.
			if ( array_key_exists( 'Image Featured', $row ) ) {
				$url  = explode( '|', $this->value( $row, 'Image Featured' ) ?? '' )[0];
				$item = fn( string $column ) => explode( '|', $this->value( $row, $column ) ?? '' )[0];
				if ( '' === $url ) {
					delete_post_thumbnail( $post_id );
				} else {
					$attachment_id = Attachments::get_attachment_by_unique_identifier( $url )
						?: Attachments::import_external_file( $url, $item( 'Image Title' ), $item( 'Image Caption' ), $item( 'Image Description' ), $item( 'Image Alt Text' ), $post_id, [], '', true, $url ); // phpcs:ignore -- Universal.Operators.DisallowShortTernary.Found.
					if ( is_wp_error( $attachment_id ) ) {
						$this->log( $uid, $post_id, 'error', sprintf( 'Featured image %s: %s', $url, $attachment_id->get_error_message() ) );
					} else {
						set_post_thumbnail( $post_id, $attachment_id );
					}
				}
				$first_block = current( array_filter( parse_blocks( $content ?? get_post_field( 'post_content', $post_id ) ), fn( $block ) => null !== $block['blockName'] || '' !== trim( $block['innerHTML'] ) ) );
				if ( has_post_thumbnail( $post_id ) && in_array( $first_block['blockName'] ?? null, self::MEDIA_BLOCKS, true ) ) {
					update_post_meta( $post_id, 'newspack_featured_image_position', 'hidden' );
				} else {
					delete_post_meta( $post_id, 'newspack_featured_image_position' );
				}
			}

			// Meta passthrough and provenance; an empty value clears the key.
			$meta_columns = self::POST_META_COLUMNS + [
				'dt_original_post_url'  => OriginalValueStore::key_for( 'syndicated_url' ),
				'dt_original_site_name' => OriginalValueStore::key_for( 'syndicated_site' ),
			];
			foreach ( array_intersect_key( $meta_columns, $row ) as $column => $meta_key ) {
				$value = $this->value( $row, $column );
				if ( null === $value ) {
					delete_post_meta( $post_id, $meta_key );
				} else {
					update_post_meta( $post_id, $meta_key, wp_slash( $value ) );
				}
			}
			if ( null !== $this->value( $row, 'Permalink' ) ) {
				OriginalPermalink::save_for_post( $post_id, $this->value( $row, 'Permalink' ) );
			}

			// The source's old slug redirects here; stored once.
			$post_name = get_post_field( 'post_name', $post_id );
			$old_slug  = $this->value( $row, '_wp_old_slug' );
			if ( null !== $old_slug && $post_name !== $old_slug && ! in_array( $old_slug, get_post_meta( $post_id, '_wp_old_slug' ), true ) ) { // phpcs:ignore -- WordPress.WP.GetMetaSingle.Missing.
				add_post_meta( $post_id, '_wp_old_slug', wp_slash( $old_slug ) );
			}

			// A source slug that another post holds gets a WP suffix; not an old slug, since it can't redirect while the other post holds it.
			$source_slug = $this->value( $row, 'Slug' );
			if ( null !== $source_slug && $post_name !== $source_slug ) {
				$holder = $wpdb->get_row( $wpdb->prepare( "SELECT ID, post_type, post_status FROM {$wpdb->posts} WHERE post_name = %s AND ID <> %d LIMIT 1", $source_slug, $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$this->log( $uid, $post_id, 'unresolved', sprintf( 'Slug "%s" was imported as "%s": %s.', $source_slug, $post_name, $holder ? sprintf( '%s %d (%s) holds "%s"', $holder->post_type, $holder->ID, $holder->post_status, $source_slug ) : 'WP changed it on save' ) );
			}

			// Sponsor (stub): a flagged post is linked to a get-or-added sponsor only when the sponsor name exists.
			if ( in_array( '1', [ $this->value( $row, 'sponsor_settings_is_sponsored' ), $this->value( $row, 'Sponsor settings_is_sponsored' ) ], true ) ) {
				$sponsor_name = $this->value( $row, 'Sponsor settings_name' );
				$sponsors     = null === $sponsor_name ? null : new Sponsors();
				$sponsor_id   = $sponsors?->get_or_add_sponsor( $sponsor_name, array_filter( [ 'url' => $this->value( $row, 'Sponsor settings_link' ) ] ) );
				if ( ! $sponsor_id || ! $sponsors->add_sponsor_to_post( $sponsor_id, $post_id ) ) {
					$this->log( $uid, $post_id, 'unresolved', null === $sponsor_name ? 'Flagged as sponsored, but has no sponsor name.' : sprintf( 'Sponsor "%s" could not be linked, see sponsors.log.', $sponsor_name ) );
				}
			}

			// Source modified date, written last because wp_insert_post() sets it to post_date; stored for the next run's diff.
			if ( null !== $source_modified ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->posts,
					[
						'post_modified'     => $source_modified,
						'post_modified_gmt' => get_gmt_from_date( $source_modified ),
					],
					[ 'ID' => $post_id ]
				);
				clean_post_cache( $post_id );
			}
			OriginalValueStore::save_for_post( $post_id, 'modified', get_post_field( 'post_modified', $post_id ) );

			$this->touched_post_ids[] = $post_id;
			$this->log( $uid, $post_id, $status, implode( ' ', $notes ) );
		}

		// Imported posts of this CSV's post types that are no longer in it are reported, never deleted.
		$imported = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value, p.post_type FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value LIKE %s",
				Posts::UNIQUE_POST_IDENTIFIER_META_KEY,
				$wpdb->esc_like( self::UID_POST ) . '%'
			)
		);
		foreach ( $imported as $post ) {
			if ( isset( $post_types[ $post->post_type ] ) && ! isset( $seen_uids[ $post->meta_value ] ) ) {
				$this->log( $post->meta_value, (int) $post->post_id, 'missing', sprintf( 'Not in %s: deleted or unpublished on the source? Not deleted here.', basename( $csv ) ) );
			}
		}
	}

	/**
	 * Applies BLOCK_TRANSFORMS to post content, and collects its media hosts for the downloader hand-off.
	 *
	 * @param string $content Source post content.
	 * @param string $uid     Source unique identifier, for the log.
	 *
	 * @return string Transformed content.
	 */
	private function transform_content( string $content, string $uid ): string {
		$content = serialize_blocks( $this->transform_blocks( parse_blocks( $content ), $uid ) );

		// Media hosts: <img> sources and PDF links under upload paths; other links are not the source's media.
		$tags = new WP_HTML_Tag_Processor( $content );
		while ( $tags->next_tag() ) {
			$url = match ( $tags->get_tag() ) {
				'IMG'   => $tags->get_attribute( 'src' ),
				'A'     => $tags->get_attribute( 'href' ),
				default => null,
			};
			$host = is_string( $url ) ? wp_parse_url( $url, PHP_URL_HOST ) : null;
			$path = is_string( $url ) ? (string) wp_parse_url( $url, PHP_URL_PATH ) : '';
			// "/uploads/YYYY/MM/file" is a WordPress upload; "/uploads/YYYY/MM/{timestamp}/file" is a CDN copy.
			if ( ! $host || ! preg_match( '#/uploads/\d{4}/\d{2}/(?<cdn_folder>\d+/)?[^/]+$#', $path, $match ) ) {
				continue;
			}
			if ( 'IMG' === $tags->get_tag() ) {
				$this->content_hosts[ empty( $match['cdn_folder'] ) ? 'wp' : 'cdn' ][ $host ] = true;
			} elseif ( str_ends_with( strtolower( $path ), '.pdf' ) ) {
				$this->content_hosts['pdf'][ $host ] = true;
			}
		}

		return $content;
	}

	/**
	 * Applies BLOCK_TRANSFORMS to a list of blocks, recursively.
	 *
	 * @param array  $blocks Parsed blocks.
	 * @param string $uid    Source unique identifier, for the log.
	 *
	 * @return array Transformed blocks.
	 */
	private function transform_blocks( array $blocks, string $uid ): array {
		$transformed = [];
		foreach ( $blocks as $block ) {
			$name  = (string) $block['blockName'];
			$rules = array_filter( self::BLOCK_TRANSFORMS, fn( $pattern ) => fnmatch( $pattern, $name ), ARRAY_FILTER_USE_KEY );
			$rule  = current( $rules ) ?: ( fnmatch( 'indiegraf*/*', $name ) ? 'unwrap' : 'keep' ); // phpcs:ignore -- Universal.Operators.DisallowShortTernary.Found.

			if ( 'drop' === $rule ) {
				$this->log( $uid, null, 'dropped', sprintf( 'Block %s dropped.', $name ) );
			} elseif ( 'unwrap' === $rule ) {
				$inner_blocks = $this->transform_blocks( $block['innerBlocks'], $uid );
				$this->log( $uid, null, 'dropped', sprintf( 'Block %s unwrapped, %d inner blocks kept.', $name, count( $inner_blocks ) ) );
				array_push( $transformed, ...$inner_blocks );
			} elseif ( 'keep' === $rule ) {
				if ( ! empty( $rules ) ) {
					$this->log( $uid, null, 'unresolved', sprintf( 'Block %s kept as-is, review it: %s', $name, trim( $block['innerHTML'] ) ) );
				}
				// Recurse, with one innerContent placeholder per resulting inner block.
				$inner_blocks  = [];
				$inner_content = [];
				$inner_index   = 0;
				foreach ( $block['innerContent'] as $chunk ) {
					$replacements  = null === $chunk ? $this->transform_blocks( [ $block['innerBlocks'][ $inner_index++ ] ], $uid ) : [];
					$inner_content = [ ...$inner_content, ...( null === $chunk ? array_fill( 0, count( $replacements ), null ) : [ $chunk ] ) ];
					$inner_blocks  = [ ...$inner_blocks, ...$replacements ];
				}
				$transformed[] = [
					'innerBlocks'  => $inner_blocks,
					'innerContent' => $inner_content,
				] + $block;
			} else {
				array_push( $transformed, ...$this->$rule( $block, $uid ) );
			}
		}

		return $transformed;
	}

	/**
	 * Converts an indiegraf/accordion block to a core accordion.
	 *
	 * @param array  $block Parsed indiegraf/accordion block.
	 * @param string $uid   Source unique identifier, for the log.
	 *
	 * @return array Replacement blocks.
	 */
	private function transform_accordion( array $block, string $uid ): array {
		$items = [];
		foreach ( $block['innerBlocks'] as $item ) {
			$items[] = [
				'title'  => esc_html( $this->get_text_by_class( $item['innerHTML'], 'wp-block-indiegraf-accordion-item__header-title' )['text'] ?? '' ),
				'blocks' => $this->transform_blocks( $item['innerBlocks'], $uid ),
			];
		}

		return empty( $items ) ? [] : [ ( new GutenbergBlockGenerator() )->get_accordion( $items ) ];
	}

	/**
	 * Converts indiegraf/user-list to its heading + its profiles, and indiegraf/user-profile to a Newspack author profile.
	 *
	 * @param array  $block Parsed indiegraf/user-list or indiegraf/user-profile block.
	 * @param string $uid   Source unique identifier, for the log.
	 *
	 * @return array Replacement blocks.
	 */
	private function transform_user_list( array $block, string $uid ): array {
		$generator = new GutenbergBlockGenerator();

		// List: heading + inner profiles.
		if ( 'indiegraf/user-list' === $block['blockName'] ) {
			$heading = $this->get_text_by_class( $block['innerHTML'], 'team-title' );

			return [
				...( null === $heading ? [] : [ $generator->get_heading( esc_html( $heading['text'] ), $heading['tag'] ) ] ),
				...$this->transform_blocks( $block['innerBlocks'], $uid ),
			];
		}

		// Profile: the source user ID resolves like a page author.
		$source_user_id = (int) ( $block['attrs']['userId'] ?? 0 );
		$user           = $source_user_id ? $this->get_or_create_byline_user( '', $source_user_id ) : null;
		if ( ! $user ) {
			$this->log( $uid, null, 'dropped', sprintf( 'Block %s dropped: source user %d has no user.', $block['blockName'], $source_user_id ) );

			return [];
		}

		return [ $generator->get_author_profile( $user->ID, false, true, true, false, true, false, true ) ];
	}

	/**
	 * Finds the first element with a class and returns its tag and text content.
	 *
	 * @param string $html       HTML.
	 * @param string $class_name Class name.
	 *
	 * @return array|null [ 'tag' => lowercase tag name, 'text' => trimmed, decoded text ], or null when not found.
	 */
	private function get_text_by_class( string $html, string $class_name ): ?array {
		$tags = new WP_HTML_Tag_Processor( $html );
		if ( ! $tags->next_tag( [ 'class_name' => $class_name ] ) ) {
			return null;
		}

		$tag  = $tags->get_tag();
		$text = '';
		while ( $tags->next_token() && ! ( $tags->is_tag_closer() && $tag === $tags->get_tag() ) ) {
			$text .= '#text' === $tags->get_token_type() ? $tags->get_modifiable_text() : '';
		}

		return [
			'tag'  => strtolower( $tag ),
			'text' => trim( $text ),
		];
	}

	/**
	 * Sets block media IDs from the block's own markup, recursively: core/image id and core/media-text mediaId from the
	 * wp-image-{id} class the downloader writes on the <img>, core/file id from its href.
	 *
	 * @param array  $blocks  Parsed blocks.
	 * @param string $uid     Source unique identifier, for the log.
	 * @param int    $post_id Post ID, for the log.
	 * @param bool   $changed Set to true when any ID changes.
	 *
	 * @return array Blocks with synced media IDs.
	 */
	private function sync_block_media_ids( array $blocks, string $uid, int $post_id, bool &$changed ): array {
		foreach ( $blocks as $index => $block ) {
			$blocks[ $index ]['innerBlocks'] = $this->sync_block_media_ids( $block['innerBlocks'], $uid, $post_id, $changed );

			$attribute = [
				'core/image'      => 'id',
				'core/media-text' => 'mediaId',
				'core/file'       => 'id',
			][ $block['blockName'] ] ?? null;
			if ( null === $attribute ) {
				continue;
			}

			// Media URL and ID from the markup; a media-text without an <img> shows the featured image or a video.
			if ( 'core/file' === $block['blockName'] ) {
				$url      = (string) ( $block['attrs']['href'] ?? '' );
				$media_id = attachment_url_to_postid( $url ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.attachment_url_to_postid_attachment_url_to_postid.
			} else {
				$tags = new WP_HTML_Tag_Processor( $block['innerHTML'] );
				if ( ! $tags->next_tag( 'img' ) ) {
					continue;
				}
				$url      = (string) $tags->get_attribute( 'src' );
				$media_id = 0;
				foreach ( $tags->class_list() as $class_name ) {
					$media_id = str_starts_with( $class_name, 'wp-image-' ) ? (int) substr( $class_name, strlen( 'wp-image-' ) ) : $media_id;
				}
			}

			// Only a local file proves the download; a remote <img> still has the source site's ID in its class.
			$current_id = (int) ( $block['attrs'][ $attribute ] ?? 0 );
			if ( ! str_starts_with( $url, wp_get_upload_dir()['baseurl'] ) || 'attachment' !== get_post_type( $media_id ) ) {
				$this->log( $uid, $post_id, 'unresolved', sprintf( 'Block %s: %s is not a media file on this site; %s %d kept.', $block['blockName'], $url, $attribute, $current_id ) );
				continue;
			}
			if ( $media_id !== $current_id ) {
				$blocks[ $index ]['attrs'][ $attribute ] = $media_id;
				$changed                                 = true;
			}
			// The media-text attachment page link (editor only) points to the source site's attachment page.
			if ( 'core/media-text' === $block['blockName'] && $this->is_source_url( (string) ( $block['attrs']['mediaLink'] ?? '' ) ) ) {
				$blocks[ $index ]['attrs']['mediaLink'] = get_attachment_link( $media_id );
				$changed                                = true;
			}
		}

		return $blocks;
	}
}
