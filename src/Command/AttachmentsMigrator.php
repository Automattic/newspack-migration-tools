<?php
/**
 * AttachmentsMigrator class.
 *
 * @package newspack-migration-tools
 */

namespace Newspack\MigrationTools\Command;

use Newspack\MigrationTools\Logic\Attachments as AttachmentsLogic;
use WP_CLI;

/**
 * Attachments general Migrator command class.
 */
class AttachmentsMigrator {
	/**
	 * @var AttachmentsLogic.
	 */
	private $attachment_logic;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->attachment_logic = new AttachmentsLogic();
	}

	/**
	 * @var null|InterfaceCommand Instance.
	 */
	private static $instance = null;

	/**
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
	 * Register the migration commands.
	 */
	public function register_commands() {
		WP_CLI::add_command(
			'newspack-migration-tools attachments-get-ids-by-years',
			array( $this, 'cmd_get_atts_by_years' ),
		);
	}

	/**
	 * Gets a list of attachment IDs by years for those attachments which have files on local in (/wp-content/uploads).
	 */
	public function cmd_get_atts_by_years() {
		global $wpdb;
		$ids_years  = array();
		$ids_failed = array();

		// phpcs:ignore
		$att_ids = $wpdb->get_results( "select ID from {$wpdb->posts} where post_type = 'attachment' ; ", ARRAY_A );
		foreach ( $att_ids as $key_att_id => $att_id_row ) {
			$att_id = $att_id_row['ID'];
			WP_CLI::log( sprintf( '(%d)/(%d) %d', $key_att_id + 1, count( $att_ids ), $att_id ) );

			// Check if this attachment is in local wp-content/uploads.
			$url                        = wp_get_attachment_url( $att_id );
			$url_pathinfo               = pathinfo( $url );
			$dirname                    = $url_pathinfo['dirname'];
			$pathmarket                 = '/wp-content/uploads/';
			$pos_pathmarker             = strpos( $dirname, $pathmarket );
			$dirname_remainder          = substr( $dirname, $pos_pathmarker + strlen( $pathmarket ) );
			$dirname_remainder_exploded = explode( '/', $dirname_remainder );

			// Group by years folders.
			$year = isset( $dirname_remainder_exploded[0] ) && is_numeric( $dirname_remainder_exploded[0] ) && ( 4 === strlen( $dirname_remainder_exploded[0] ) ) ? (int) $dirname_remainder_exploded[0] : null;
			if ( is_null( $year ) ) {
				$ids_failed[ $att_id ] = $url;
			} else {
				$ids_years[ $year ][] = $att_id;
			}
		}

		// Save {$year}.txt file.
		foreach ( array_keys( $ids_years ) as $year ) {
			$att_ids = $ids_years[ $year ];
			$file    = $year . '.txt';
			file_put_contents( $file, implode( ' ', $att_ids ) . ' ' );
		}

		// Save 0_failed_ids.txt file for files which may not be on local.
		foreach ( $ids_failed as $att_id => $url ) {
			$file = '0_failed_ids.txt';
			file_put_contents( $file, $att_id . ' ' . $url . "\n", FILE_APPEND );
		}

		WP_CLI::log( sprintf( "> created {year}.txt's and %s", $file ) );
	}
}
