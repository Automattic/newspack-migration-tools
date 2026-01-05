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
	 */
	public function test_download_image() {
		$result = Attachments::download_file( 'tests/fixtures/koi.jpg' );

		$this->assertEquals( $result['name'], 'koi.jpg' );
		$this->assertFileExists( $result['tmp_name'] );
	}

	/**
	 * Test downloading an image.
	 */
	public function test_download_image_without_extension() {
		$result = Attachments::download_file( 'tests/fixtures/koi' );

		$this->assertEquals( $result['name'], 'koi.jpg' );
		$this->assertFileExists( $result['tmp_name'] );
	}

	/**
	 * Test downloading an image with different mime types and extensions.
	 */
	public function test_download_mimes_and_exts() {

		
	// maybe:
	// use files from here: /tmp/wordpress-tests-lib/data/images/
	// https://github.com/WordPress/wordpress-develop/tree/trunk/tests/phpunit/data/images


		// When `$path` has allowed extension (`.jpg`), with matching allowed binary mime (`image/jpeg`): do not modify filename.
		$test_file_name = 'image-jpeg.jpg';
		// 	["ext"]=> "jpg"
		// 	["type"]=> "image/jpeg"
		// 	["proper_filename"]=> bool(false)
		//   string(63) "http://example.org/wp-content/uploads/2025/12/image-jpeg-14.jpg"
		$result = Attachments::download_file( 'tests/fixtures/mimes-and-exts/' . $test_file_name );
		// $this->assertEquals( $result['name'], $test_file_name );
		// $this->assertFileExists( $result['tmp_name'] );
		
		// When `$path` has allowed extension (`.jpg`), with different allowed binary mime (`image/png`): append correct extension (`.png`).
		$test_file_name = 'image-jpeg-ext-allowed.png';
		//   ["ext"]=>"jpg"
		//   ["type"]=>"image/jpeg"
		//   ["proper_filename"]=>"image-jpeg-ext-allowed.jpg"
		// string(74) "http://example.org/wp-content/uploads/2025/12/image-jpeg-ext-allowed-6.jpg"
		$result = Attachments::download_file( 'tests/fixtures/mimes-and-exts/' . $test_file_name );

		// When `$path` has unallowed extension (`.exe`), with different allowed binary mime (`image/png`): append correct extension (`.png`).
		$test_file_name = 'image-jpeg-ext-not-allowed.swf';
		// wp_check_filetype_and_ext all false, sorry
		$result = Attachments::download_file( 'tests/fixtures/mimes-and-exts/' . $test_file_name );

		// When `$path` has unknown extension (`.unknown`), with allowed binary mime (`image/png`): append correct extension (`.png`).
		$test_file_name = 'image-jpeg-ext-unknown.unknown';
		// wp_check_filetype_and_ext all false, sorry
		$result = Attachments::download_file( 'tests/fixtures/mimes-and-exts/' . $test_file_name );

		// When `$path` has no extension, with allowed binary mime (`image/png`): append correct extension (`.png`).
		$test_file_name = 'image-jpeg-ext-none';
		// wp_check_filetype_and_ext all false, sorry
		$result = Attachments::download_file( 'tests/fixtures/mimes-and-exts/' . $test_file_name );


		// ---- these are all wp_check_filetype_and_ext all false and sorry can't upload ----

		// When `$path` has allowed extension (`.jpg`), with different unallowed binary mime (`application/x-msdownload`): ??
		$test_file_name = 'application-x-dosexec-ext-allowed.png';
		$result = Attachments::download_file( 'tests/fixtures/mimes-and-exts/' . $test_file_name );

		// When `$path` has allowed extension (`.jpg`), with unknown binary mime (`unknown`): ??
		$test_file_name = 'unknown-mime-ext-allowed.png';
		$result = Attachments::download_file( 'tests/fixtures/mimes-and-exts/' . $test_file_name );

		// When `$path` has unallowed extension (`.exe`), with matching unallowed binary mime (`application/x-msdownload`): ??
		$test_file_name = 'application-x-dosexec.exe';
		$result = Attachments::download_file( 'tests/fixtures/mimes-and-exts/' . $test_file_name );

		// When `$path` has unallowed extension (`.exe`), with different unallowed binary mime (`application/x-shockwave-flash`): ??
		$test_file_name = 'application-x-dosexec-ext-not-allowed.swf';
		$result = Attachments::download_file( 'tests/fixtures/mimes-and-exts/' . $test_file_name );

		// When `$path` has unallowed extension (`.exe`), with unknown binary mime (`unknown`): ??
		$test_file_name = 'unknown-mime-ext-not-allowed.swf';
		$result = Attachments::download_file( 'tests/fixtures/mimes-and-exts/' . $test_file_name );

		// When `$path` has unknown extension (`.unknown`), with non allowed binary mime (`application/x-msdownload`): ??
		$test_file_name = 'application-x-dosexec-ext-unknown.unknown';
		$result = Attachments::download_file( 'tests/fixtures/mimes-and-exts/' . $test_file_name );

		// When `$path` has unknown extension (`.unknown`), with unknown binary mime (`unknown`): ??
		$test_file_name = 'unknown-mime.unknown';
		$result = Attachments::download_file( 'tests/fixtures/mimes-and-exts/' . $test_file_name );

		// When `$path` has no extension, with unallowed binary mime (`application/x-msdownload`): ??
		$test_file_name = 'application-x-dosexec-ext-none';
		$result = Attachments::download_file( 'tests/fixtures/mimes-and-exts/' . $test_file_name );

		// When `$path` has no extension, with unknown binary mime (`unknown`): ??
		$test_file_name = 'unknown-mime-ext-none';
		$result = Attachments::download_file( 'tests/fixtures/mimes-and-exts/' . $test_file_name );

	}
}
