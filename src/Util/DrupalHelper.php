<?php
/**
 * Drupal helper for the great FG migration plugins.
 *
 * @package NewspackCustomContentMigrator
 */

namespace Newspack\MigrationTools\Util;

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
			CliLog::get_logger( 'DrupalHelper' )->alert(
				sprintf(
					'This function likely only going to work for Drupal 7. Your Drupal version is %d. Proceed at your own risk :)',
					$this->drupal_version 
				)
			);
		}

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
				$nid = substr( $row['source'], 5 ); // Remove 'node/' prefix.
				if ( ! empty( $row['alias'] ) ) {
					$this->nid_to_url_map[ $nid ] = $row['alias'];
				}
			}
		}

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
}
