<?php

namespace Newspack\MigrationTools\Tests;

use Newspack\MigrationTools\Logic\Attachments;
use WP_Error;

trait AttachmentUnitTestTrait {

	/**
	 * @var array Attachment IDs to clean up on tearDown().
	 */
	private array $attachment_ids = [];

	/**
	 * @var string Path to a real local image file.
	 */
	public string $dummy_image = 'tests/fixtures/koi.jpg';

	/**
	 * @var array Temp files to clean up on tearDown().
	 */
	private array $temp_files = [];

	/**
	 * {@inheritDoc}
	 */
	public function tearDown(): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->clean_up_attachments();
		$this->clean_up_temp_files();
		parent::tearDown();
	}

	/**
	 * This just wraps Attachments::import_attachment_for_post() and keeps track of the attachment IDs so we can delete them on tearDown().
	 *
	 * @param int    $post_id The post ID to attach the image to.
	 * @param string $path The path to the image.
	 * @param string $alt_text The (optional) alt text for the image.
	 * @param array  $attachment_args Additional (optional) arguments for wp_insert_attachment().
	 * @param string $desired_filename The (optional) desired filename for the attachment.
	 *
	 * @return int|WP_Error
	 */
	public function wrap_import_attachments_for_post( int $post_id, string $path, string $alt_text = '', array $attachment_args = [], string $desired_filename = '' ): int|WP_Error {
		$attachment_id          = Attachments::import_attachment_for_post( $post_id, $path, $alt_text, $attachment_args, $desired_filename );

		// If not an error, save the id for tear down.
		if ( ! is_wp_error( $attachment_id ) ) {
			$this->attachment_ids[] = $attachment_id;
		}
	
		return $attachment_id;
	}

	/**
	 * This just wraps Attachments::download_file() and keeps track of the /tmp/ files so we can delete them on tearDown().
	 *
	 * @param string $path             The path to the file.
	 * @param string $desired_filename (Optional) If set, file extension fixes will not be applied.
	 *
	 * @return array|WP_Error The file array.
	 */
	public function wrap_download_file( $path, $desired_filename = '' ) {
		$file_array = Attachments::download_file( $path, $desired_filename );
		
		// Keep a file path reference for deletion during tear down - only if file array is not an error, has path info, and file exists.
		if ( ! is_wp_error( $file_array ) && isset( $file_array['tmp_name'] ) && file_exists( $file_array['tmp_name'] )) {	
			$this->temp_files[] = $file_array['tmp_name'];
		}

		return $file_array;
	}

	/**
	 * This just wraps WP Core `media_handle_sideload()` and keeps track of the attachment IDs so we can delete them on tearDown().
	 *
	 * @param string[] $file_array Array that represents a `$_FILES` upload array.
	 * @param int      $post_id    Optional. The post ID the media is associated with.
	 * @param string   $desc       Optional. Description of the side-loaded file. Default null.
	 * @param array    $post_data  Optional. Post data to override. Default empty array.
	 * @return int|WP_Error The ID of the attachment or a WP_Error on failure.
	 */
	public function wrap_media_handle_sideload( $file_array, $post_id = 0, $desc = null, $post_data = array() ) {

		$attachment_id = media_handle_sideload( $file_array, $post_id, $desc, $post_data );

		if ( is_wp_error( $attachment_id ) ) {
			// If there was an error then remove the temp file.
			@unlink( $file_array['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		else {
			// Save the id for tear down.
			$this->attachment_ids[] = $attachment_id;
		}

		return $attachment_id;
	}

	/**
	 * Delete the files created during the test.
	 *
	 * @return void
	 */
	public function clean_up_attachments(): void {
		foreach ( $this->attachment_ids as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
	}

	/**
	 * Delete temp files created during the test.
	 *
	 * @return void
	 */
	public function clean_up_temp_files(): void {
		foreach ( $this->temp_files as $path ) {
			if ( file_exists( $path ) ) {
				unlink( $path );
			}
		}
	}
}
