<?php
/**
 * Drupal helper for the great FG migration plugins.
 *
 * @package NewspackCustomContentMigrator
 */

namespace Newspack\MigrationTools\Util;

use Newspack\MigrationTools\NMT;
use Newspack\MigrationTools\Util\Log\CliLog;

class DrupalHelper extends FgHelper {

	/**
	 * Nid (node id) to original URL map.
	 *
	 * @var array
	 */
	private array $nid_to_url_map = [];

	/**
	 * Tid (term id) to original URL map.
	 *
	 * @var array
	 */
	private array $tid_to_url_map = [];

	/**
	 * Drupal version.
	 *
	 * Use this var to warn for functions that may only work for certain Drupal versions.
	 *
	 * @var int
	 */
	private int $drupal_version;

	/**
	 * Construct.
	 *
	 * @param int $version Drupal version for the site being migrated.
	 */
	public function __construct( int $version ) {
		parent::__construct( 'drupal' );
		$this->drupal_version = $version;
	}

	/**
	 * Given a node ID, return its URL alias if available.
	 *
	 * Will work for all node types. An empty string is returned if no alias is found.
	 *
	 * @param int $nid Node ID.
	 *
	 * @return string URL alias from Drupal.
	 */
	public function get_alias_from_node_id( int $nid ): string {
		if ( $this->drupal_version > 7 ) {
			// It might be nice to copy the dynamic DB code from:
			// fg-drupal-to-wp-premium/admin/class-fg-drupal-to-wp-admin.php : line 2094 : function get_node_slug
			// it seems be creating a sql query based on different versions.
			// fg-drupal-to-wp-premium/admin/class-fg-drupal-to-wp-urls.php : line 145 : function get_urls
			// seems similar too.
			// If possible, this warning might not be needed.
			CliLog::get_logger( 'DrupalHelper' )->alert(
				sprintf(
					'This function likely only going to work for Drupal 7. Your Drupal version is %d. Proceed at your own risk :)',
					$this->drupal_version
				)
			);
		}
		
		// Possible alternative:
		global $wpdb;
		$prefix = $this->get_import_tables_prefix();
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT alias
				 FROM {$prefix}url_alias
				 WHERE source LIKE %s
				 AND alias IS NOT null
				 AND alias <> ''
				 ",
				$wpdb->esc_like( 'node/' . $nid )  . '%'
			)
		);
		return (string) $result;


		// can this be replaced by the sql above?
		// since $this->nid_to_url_map is just used by this function, and it's private, it seems to be creating a lookup
		// array that works much like the DB table would.  
		// If you keep this logic, please see further notes below.
		if ( empty( $this->nid_to_url_map ) ) {
			global $wpdb;

			$prefix               = $this->get_import_tables_prefix();
			$this->nid_to_url_map = [];

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$results = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT source, alias FROM {$prefix}url_alias
                     WHERE source LIKE %s",
					$wpdb->esc_like( 'node/' ) . '%'
				),
				ARRAY_A
			);

			foreach ( $results as $row ) {
				// this changes the passed in argument $nid value - or atleast it does on the first call to function.
				$nid = substr( $row['source'], 5 ); // Remove 'node/' prefix.
				if ( ! empty( $row['alias'] ) ) {
					$this->nid_to_url_map[ $nid ] = $row['alias'];
				}
			}
		}

		// this return value might be based on the last $nid set in the foreach loop.
		// the first call to this function will return the last db row.
		// subsequent calls will return the proper row.
		return $this->nid_to_url_map[ $nid ] ?? '';
	}

	/**
	 * Given a term ID, return its URL alias if available.
	 *
	 * Will work for all taxonomy terms. An empty string is returned if no alias is found.
	 *
	 * @param int $tid Term ID.
	 *
	 * @return string URL alias from Drupal.
	 */
	public function get_alias_from_term_id( int $tid ): string {

		// Please see same notes in function above.

		if ( $this->drupal_version > 7 ) {
			CliLog::get_logger( 'DrupalHelper' )->alert(
				sprintf(
					'This function likely only going to work for Drupal 7. Your Drupal version is %d. Proceed at your own risk :)',
					$this->drupal_version
				)
			);
		}

		if ( empty( $this->tid_to_url_map ) ) {
			global $wpdb;

			$prefix = $this->get_import_tables_prefix();

			// Get all URL aliases for taxonomy terms.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
			$results = $wpdb->get_results(
				$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT source, alias FROM {$prefix}url_alias 
                     WHERE source LIKE %s",
					$wpdb->esc_like( 'taxonomy/term/' ) . '%'
				),
				ARRAY_A
			);

			foreach ( $results as $row ) {
				$tid = substr( $row['source'], 14 ); // Remove 'taxonomy/term/' prefix.
				if ( ! empty( $row['alias'] ) ) {
					$this->tid_to_url_map[ $tid ] = $row['alias'];
				}
			}
		}

		return $this->tid_to_url_map[ $tid ] ?? '';
	}

	/**
	 * Given a Drupal file URI, return the file path according to the download protocol.
	 *
	 * @param string $uri Drupal file URI.
	 *
	 * @return string Image path according to the download protocol.
	 */
	public function get_file_path_from_uri( string $uri ): string {
		$download_protocol = $this->get_fg_option( 'download_protocol' );
		if ( 'ftp' === $download_protocol ) {
			NMT::exit_with_message( 'FTP download protocol is not supported for file path retrieval in this helper. But you could implement it!' );
		}

		$public  = $this->get_fg_option( 'file_public_path' ) ?? 'sites/default/files';
		$private = $this->get_fg_option( 'file_private_path' ) ?? 'sites/default/private/files';

		if ( 'http' === $download_protocol ) {
			// Otherwise, return URL.
			$url = $this->get_fg_option( 'url' );
			// as seen here: fg-drupal-to-wp-premium/admin/class-fg-drupal-to-wp-admin.php
			// function set_default_file_paths will add trailingslashit.
			// otherwise there is a missing / in the return value.
			$uri = str_replace( 'public://', trailingslashit( $url ) . trailingslashit( $public ), $uri );
			$uri = str_replace( 'private://', trailingslashit( $url ) . trailingslashit( $private ), $uri );
		}

		if ( 'file_system' === $download_protocol ) {
			$base_dir = $this->get_fg_option( 'base_dir' );
			if ( empty( $base_dir ) ) {
				NMT::exit_with_message( 'The "base_dir" FG option is not set. Cannot resolve file paths.' );
			}

			$uri = str_replace( 'public://', trailingslashit( $public ), $uri );
			$uri = str_replace( 'private://', trailingslashit( $private ), $uri );
			$uri = trailingslashit( $base_dir ) . $uri;
		}

		return apply_filters( 'fgd2wp_get_path_from_uri', $uri );
	}


	/**
	 * Given a Drupal node ID, return the WordPress post ID.
	 *
	 * @param int $nid Drupal node ID.
	 *
	 * @return int WordPress post ID, or 0 if not found.
	 */
	public static function get_post_id_from_nid( int $nid ): int {
		$maybe_nid = self::get_post_ids_from_nids( [ (string) $nid ] );
		return empty( $maybe_nid[ $nid ] ) ? 0 : $maybe_nid[ $nid ];
	}

	/**
	 * Given an array of Drupal node IDs, return a map of NIDs to WordPress post IDs.
	 *
	 * @param array $nids Array of Drupal node IDs (as strings or integers).
	 *
	 * @return array Associative array mapping NIDs to WordPress post IDs. Missing NIDs are not included in the result.
	 */
	public static function get_post_ids_from_nids( array $nids ): array {
		if ( empty( $nids ) ) {
			return [];
		}

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $nids ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$wpdb->prepare(
				"SELECT meta_value as nid, post_id
				FROM {$wpdb->postmeta}
				WHERE meta_key = '_fgd2wp_old_node_id'
				AND meta_value IN ($placeholders)",
				...$nids
			),
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return array_map( 'intval', array_column( $results, 'post_id', 'nid' ) );
	}
}
