<?php

namespace Newspack\MigrationTools\Tests;

use Newspack\MigrationTools\Logic\Attachments;
use WP_Error;

trait AttachmentUnitTestTrait {

	/**
	 * @var array Attachment IDs to clean up on tearDown().
	 */
	private array $attachment_ids = [];

	public string $dummy_image = 'https://dummyimage.com/600x400.jpg/000/fff&text=Iz+test';

	/**
	 * {@inheritDoc}
	 */
	public function tearDown(): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->clean_up_attachments();
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
		$this->attachment_ids[] = $attachment_id;
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
}
