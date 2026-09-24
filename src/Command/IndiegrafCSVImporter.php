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
use Newspack\MigrationTools\Logic\SimpleLocalAvatars;
use Newspack\MigrationTools\Logic\UsersHelper;
use Newspack\MigrationTools\Util\CsvIterator;
use Newspack\MigrationTools\Util\CsvWriter;
use Newspack\MigrationTools\Util\Log\MultiLog;
use Psr\Log\LoggerInterface;
use WP_CLI;
use WP_User;

/**
 * Imports Indiegraf (WP All Export) CSVs of users, posts and pages.
 */
class IndiegrafCSVImporter implements WpCliCommandInterface {

	use WpCliCommandTrait;

	private const UID_POST         = 'indiegraf-post-';
	private const UID_USER         = 'indiegraf-user-';
	private const UID_BYLINE       = 'indiegraf-byline-';
	private const TOUCHED_IDS_FILE = 'indiegraf_touched_post_ids.txt';

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
	];

	/**
	 * Block name fnmatch() pattern => 'drop' | 'unwrap' | handler method. Unknown indiegraf*\/ blocks default to 'unwrap'.
	 */
	private const BLOCK_TRANSFORMS = [
		'indiegraf/accordion'     => 'transform_accordion',
		'indiegraf/user-list'     => 'transform_user_list',
		'indiegraf/user-profile'  => 'transform_user_list',
		'indiegraf/featured-post' => 'drop',
		'indiegraf/latest-posts'  => 'drop',
		'indiegrafpay/*'          => 'drop',
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
	 * Media hosts found in content, host => 'wp'|'cdn'.
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
						[
							'type'        => 'assoc',
							'name'        => 'live-rest-url',
							'description' => 'CSVs have only ID references for some values, so API requests are made to fetch up extra data: category names for Yoast primary categories, and author profile picture files.',
							'optional'    => true,
							'repeating'   => false,
						],
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
					'shortdesc' => 'Step 2, after the media downloader: syncs block media IDs and reports permalink mismatches.',
					'synopsis'  => [
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
		if ( ! empty( $assoc_args['live-rest-url'] ) ) {
			if ( ! wp_http_validate_url( $assoc_args['live-rest-url'] ) ) {
				WP_CLI::error( sprintf( 'Invalid --live-rest-url: %s', $assoc_args['live-rest-url'] ) );
			}
			$this->rest_url = untrailingslashit( $assoc_args['live-rest-url'] );
		}
		$import_roles = array_filter( array_map( 'trim', explode( ',', $assoc_args['import-roles'] ) ) );
		$post_csvs    = array_filter( [ $assoc_args['posts-csv'], $assoc_args['pages-csv'] ?? null ] );

		// Dependencies: CAP, Simple Local Avatars, Newspack guest contributor role.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$cap     = UsersHelper::validate_co_authors_plus();
		$missing = array_filter(
			[
				is_wp_error( $cap ) ? $cap->get_error_message() : null,
				is_plugin_active( 'simple-local-avatars/simple-local-avatars.php' ) ? null : 'Simple Local Avatars plugin is not active.',
				GuestContributorsHelper::validate_newspack_plugin() ? null : GuestContributorsHelper::ERROR_NEWSPACK_PLUGIN,
			]
		);
		if ( ! empty( $missing ) ) {
			WP_CLI::error( "Missing dependencies:\n- " . implode( "\n- ", $missing ) );
		}

		foreach ( [ $assoc_args['users-csv'], ...$post_csvs ] as $csv ) {
			$this->normalize_csv_headers( $csv );
		}

		// Permalink gate: source URLs are flat /slug/.
		if ( '/%postname%/' !== get_option( 'permalink_structure' ) ) {
			WP_CLI::confirm( "This importer relies on /%postname%/ permalinks (source URLs are flat /slug/; old slugs redirect via _wp_old_slug). Set them now (wp rewrite structure '/%postname%/'), then press y to continue." );
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

		$this->import_users( $assoc_args['users-csv'], $import_roles );

		$this->logger->warning( 'Posts and pages import is not implemented yet.' );
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
		WP_CLI::log( 'indiegraf import-2-of-2: not implemented yet.' );
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
	 * GETs a path from the source site's public REST API, once per run.
	 *
	 * REST is optional: without --live-rest-url or on failure this returns null, and the first failure prints one warning.
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
		$timestamp = strtotime( $data['user_registered'] ?? '' );
		unset( $data['user_registered'] );
		if ( false !== $timestamp ) {
			$data['user_registered'] = gmdate( 'Y-m-d H:i:s', $timestamp );
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
		if ( array_key_exists( 'title', $row ) ) {
			$data['meta_input']['newspack_job_title'] = $this->value( $row, 'title' ) ?? '';
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
			$data['meta_input'] = array_filter( $data['meta_input'] ?? [] );
			$user               = GuestContributorsHelper::create_or_get_contributor( array_filter( $data, fn( $value ) => null !== $value && [] !== $value ), $uid );
			if ( is_wp_error( $user ) ) {
				$this->log( $uid, null, 'error', 'Create failed: ' . $user->get_error_message() );

				return null;
			}
			$status = 'created';
		}

		// Avatar: an empty value on refresh removes it.
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
			$this->log( $uid, $user_id, 'unresolved', sprintf( 'Avatar: source media %d has no source_url (%s).', $source_media_id, null === $this->rest_url ? 'no --live-rest-url' : 'REST lookup failed' ) );

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
}
