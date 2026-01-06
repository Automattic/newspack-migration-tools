<?php

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\MigrationTools\Logic\Attachments;
use Newspack\MigrationTools\Tests\AttachmentUnitTestTrait;
use WP_UnitTestCase;

class TestAttachmentHelper extends WP_UnitTestCase {

	use AttachmentUnitTestTrait;

	private int $post_id;

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();
		$this->post_id = self::factory()->post->create();
		add_filter( 'newspack_migration_tools_log_clilog_disable', '__return_true' );
	}

	/**
	 * Test that the attachment ID can be retrieved by the filename.
	 *
	 * @return void
	 */
	public function test_get_attachment_id_by_filename(): void {
		$desired_file_name = uniqid() . '.jpeg';
		$attachment_id     = $this->wrap_import_attachments_for_post(
			self::factory()->post->create(),
			$this->dummy_image,
			'Test image',
			[],
			$desired_file_name
		);

		$found_attachment_id = Attachments::get_attachment_id_by_filename( $desired_file_name );
		$this->assertEquals( $attachment_id, $found_attachment_id, 'Found attachment ID matches' );
		$should_not_find_attachment_id = Attachments::get_attachment_id_by_filename( uniqid() . 'nonexistent.jpg' );
		$this->assertEmpty( $should_not_find_attachment_id );
	}


	/**
	 * Test importing an image and creating an attachment for a post.
	 *
	 * @return void
	 */
	public function test_import_attachment_for_post(): void {
		$post_title   = 'Test image';
		$post_excerpt = 'Some text about the image.';
		$post_content = 'Some more lengthy text about the image.';

		$attachment_id = $this->wrap_import_attachments_for_post(
			$this->post_id,
			$this->dummy_image,
			'Test image',
			compact( 'post_title', 'post_excerpt', 'post_content' ),
		);

		$this->attachment_ids[] = $attachment_id;

		$this->assertIsInt( $attachment_id );
		$file_path = get_attached_file( $attachment_id );
		$this->assertFileExists( $file_path );

		$attachment_post = get_post( $attachment_id );
		$this->assertEquals( $post_title, $attachment_post->post_title );
		$this->assertEquals( $post_excerpt, $attachment_post->post_excerpt );
		$this->assertEquals( $post_content, $attachment_post->post_content );
	}

	/**
	 * Test importing an image and creating an attachment for a post with a desired name.
	 *
	 * This is for urls that don't have a filename in the URL.
	 *
	 * @return void
	 */
	public function test_import_attachment_for_post_w_desired_name(): void {

		$desired_file_name = uniqid() . '.jpeg';

		$attachment_id          = $this->wrap_import_attachments_for_post(
			$this->post_id,
			$this->dummy_image,
			'Named test image',
			[],
			$desired_file_name
		);
		$this->attachment_ids[] = $attachment_id;

		$this->assertIsInt( $attachment_id );
		$file_path = get_attached_file( $attachment_id );
		$this->assertFileExists( $file_path );
		$this->assertStringEndsWith( $desired_file_name, $file_path );
	}

	/**
	 * Test that files with valid extensions don't get double extensions.
	 *
	 * This is a regression test for a bug where the old extension validation logic
	 * used array_search() on wp_get_mime_types() values instead of keys, causing
	 * valid extensions like 'jpg' to not be recognized. This resulted in the code
	 * appending another extension based on mime type detection, creating filenames
	 * like 'image.jpg.jpg'.
	 *
	 * @return void
	 */
	public function test_download_file_does_not_create_double_extension(): void {
		// Use a URL with a valid .jpg extension.
		$url_with_extension = $this->dummy_image;

		$file_array = Attachments::download_file( $url_with_extension );

		$this->assertIsArray( $file_array, 'download_file should return an array' );
		$this->assertArrayHasKey( 'name', $file_array, 'File array should have a name key' );

		$filename = $file_array['name'];

		// Count how many times common image extensions appear in the filename.
		// A double extension bug would result in something like "image.jpg.jpg".
		$extension_pattern = '/\.(jpg|jpeg|png|gif|webp)/i';
		preg_match_all( $extension_pattern, $filename, $matches );

		$this->assertCount(
			1,
			$matches[0],
			sprintf(
				'Filename "%s" should have exactly one image extension, but found %d. This indicates a double extension bug.',
				$filename,
				count( $matches[0] )
			)
		);

		// Clean up the temp file.
		if ( file_exists( $file_array['tmp_name'] ) ) {
			unlink( $file_array['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}

	/**
	 * Test that files without extensions get an extension added based on mime type.
	 *
	 * @return void
	 */
	public function test_download_file_adds_extension_when_missing(): void {
		// Use a local test file without an extension.
		$test_file_without_ext = sys_get_temp_dir() . '/test_image_no_ext_' . uniqid();

		// Copy the fixture image to a file without extension.
		$fixture_image = dirname( __DIR__ ) . '/fixtures/koi.jpg';
		copy( $fixture_image, $test_file_without_ext );

		$file_array = Attachments::download_file( $test_file_without_ext );

		$this->assertIsArray( $file_array, 'download_file should return an array' );
		$this->assertArrayHasKey( 'name', $file_array, 'File array should have a name key' );

		$filename  = $file_array['name'];
		$extension = pathinfo( $filename, PATHINFO_EXTENSION );

		$this->assertNotEmpty(
			$extension,
			sprintf( 'Filename "%s" should have an extension added based on mime type detection.', $filename )
		);

		// The extension should be a valid image extension since we used an image file.
		$valid_extensions = [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ];
		$this->assertContains(
			strtolower( $extension ),
			$valid_extensions,
			sprintf( 'Extension "%s" should be a valid image extension.', $extension )
		);

		// Clean up.
		if ( file_exists( $file_array['tmp_name'] ) ) {
			unlink( $file_array['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
		if ( file_exists( $test_file_without_ext ) ) {
			unlink( $test_file_without_ext ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}
}
