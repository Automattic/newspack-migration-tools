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
	 * Test downloading an image.
	 * @dataProvider download_image_provider
	 */
	public function test_download_image( $path, $expected_download, $expected_sideload ) {
		$result = Attachments::download_file( $path );
		
		// Check for Download error just incase.
		if ( is_wp_error( $expected_download ) ) {
			$this->assertSame( $expected_download, $result->get_error_code() );
			return;
		} 

		// Check values:
		$this->assertEquals( $expected_download, $result['name'] );
		$this->assertFileExists( $result['tmp_name'] );

		// Verify result works as expecpted with sideload.
		$sideload_id = media_handle_sideload( $result );
		
		if ( is_wp_error( $sideload_id ) ) {
			$this->assertSame( $expected_sideload, $sideload_id->get_error_message() );
			return;
		} 

		$this->assertSame( $expected_sideload, preg_replace(
			'/-\d+(?=\.[^.]+$)/',
			'',
			wp_basename( wp_get_original_image_path( $sideload_id, true ) )
		) );

	}

	/**
	 * Data provider for test_download_image
	 *
	 * @return array[]
	 */
	public function download_image_provider(): array {
		return [

			// old August code:

			'image jpeg with correct extension (jpeg)' => [
				'tests/fixtures/mimes-and-exts/image-jpeg.jpeg',
				'image-jpeg.jpeg', // no change.
				'image-jpeg.jpeg',// no change.
			],
			'image jpeg with correct extension (jpg)' => [
				'tests/fixtures/mimes-and-exts/image-jpeg.jpg',
				'image-jpeg.jpg', // no change.
				'image-jpeg.jpg', // no change.
			],
			'image jpeg without extension' => [
				'tests/fixtures/mimes-and-exts/image-jpeg-without-extension',
				'image-jpeg-without-extension.jpg', // download will add extension.
				'image-jpeg-without-extension.jpg', // no change.
			],
			'image jpeg with unknown extension' => [
				'tests/fixtures/mimes-and-exts/image-jpeg-unknown-extension.unknown',
				'image-jpeg-unknown-extension.unknown', // no change.
				'Sorry, you are not allowed to upload this file type.', // sideload not allowed
			],
			'image jpeg with wrong extension but allowed' => [
				'tests/fixtures/mimes-and-exts/image-jpeg-wrong-extension-is-allowed.png',
				'image-jpeg-wrong-extension-is-allowed.png', // no change.
				'image-jpeg-wrong-extension-is-allowed.jpg', // sideload will rename.
			],
			'image jpeg with wrong not allowed extension' => [
				'tests/fixtures/mimes-and-exts/image-jpeg-wrong-extension-not-allowed.swf',
				'image-jpeg-wrong-extension-not-allowed.swf', // no change.
				'Sorry, you are not allowed to upload this file type.', // sideload not allowed
			],

			// NEW PR:










		];
	}
}
