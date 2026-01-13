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
	 * Test downloading a file.
	 * 
	 * @dataProvider provider_mimes_and_exts_fixtures
	 */
	public function test_download_file( array $provider ) {

		$downloaded_file_array = Attachments::download_file( $provider['path'] );
		
		// Check for Download error just incase.
		if ( is_wp_error( $downloaded_file_array ) ) {
			$this->assertSame( $provider['downloaded-file-name'], $downloaded_file_array->get_error_code() );
			return;
		} 

		// Check values:
		$this->assertSame( $provider['downloaded-file-name'], $downloaded_file_array['name'] );
		$this->assertFileExists( $downloaded_file_array['tmp_name'] );

		// Verify mime, then, verify it's default ext.
		$this->assertSame( $provider['file-binary-mime'], mime_content_type( $downloaded_file_array['tmp_name'] ) );
		$this->assertSame( $provider['file-binary-mime'], finfo_file( finfo_open( FILEINFO_MIME_TYPE ), $downloaded_file_array['tmp_name'] ) );
		$this->assertSame( $provider['mime-default-ext'], wp_get_default_extension_for_mime_type( $provider['file-binary-mime'] ) );
		
		// Verify sideload.
		$this->media_handle_sideload_asserts( $downloaded_file_array, $provider['sideloaded-file-name'] );

		// Verify sideload for WP Core by not running the download_file's extension fix...just use the basename as-is.
		// using wp_basename( $path ) is the same logic download_file would do if we had a "skip fix extension" argument.
		$downloaded_file_array = Attachments::download_file( $provider['path'], wp_basename( $provider['path'] ) );
		$this->media_handle_sideload_asserts( $downloaded_file_array, $provider['sideloaded-wp-core'] );        
	}

	/**
	 * WordPress core doesn't have much test coverage for sideloading. We should have some of our tests since we're doing
	 * filename manipulation in our `download_file` function in order to fix some of sideload's problems. We need to
	 * have tests of the core sideload function so we'll have a baseline of data to compare to our manipulations to make
	 * sure they are having the intended effects.
	 * 
	 * WP Core function calls: 
	 *   `media_handle_sideload` calls
	 *      `wp_handle_sideload` which calls
	 *          `_wp_handle_upload` (this is where magic happens) - which uses
	 *              `wp_check_filetype_and_ext` which uses
	 *                  `wp_check_filetype` which uses:
	 *                      `get_allowed_mime_types` which uses:
	 *                          `wp_get_mime_types` -- this is ALL MIMES
	 *                          `unset( $t['swf'], $t['exe'] );`
	 *                          `if...$unfiltered...unset( $t['htm|html'], $t['js'] )`
	 *              `wp_check_filetype_and_ext` which uses
	 *                  $finfo     = finfo_open( FILEINFO_MIME_TYPE );
	 *                  $real_mime = finfo_file( $finfo, $file );
	 */ 
	private function media_handle_sideload_asserts( array $downloaded_file_array, string $assert_value ) {

		// Verify result works as expecpted with sideload.
		$sideload_id = media_handle_sideload( $downloaded_file_array );
		
		if ( is_wp_error( $sideload_id ) ) {
			$this->assertSame( $assert_value, $sideload_id->get_error_message() );
			return;
		} 

		// Verify created attachment name matches from the sideload.
		$sideloaded_path = wp_attachment_is_image( $sideload_id ) ? 
			wp_get_original_image_path( $sideload_id, true ) : 
			get_post_meta( $sideload_id, '_wp_attached_file', true );

		// Verify. Need to remove any "-2" duplicates just in case. WP could put at end of string "-2.png" or mid-string "-2.png-and-something.png".
		$this->assertSame(
			$assert_value,
			preg_replace(
				'/-\d+\./',
				'.',
				wp_basename( $sideloaded_path )
			)
		);
	}

	/**
	 * Data provider for mimes and exts fixtures.
	 * 
	 * Most of these files were copied and renamed from existing WP Core test files (see link).
	 * If a file was created a different way, it will be noted below.
	 * 
	 * @link https://github.com/WordPress/wordpress-develop/tree/ac6e7c904a39fabaa0f3d3770554f05373552db4/tests
	 *
	 * @return array[]
	 */
	public function provider_mimes_and_exts_fixtures(): array {

		$fixtures_folder = 'tests/fixtures/mimes-and-exts/';

		return [
			'missing file'                               => [
				[
					'path'                 => $fixtures_folder . 'no-file.nope',
					'downloaded-file-name' => 'File ' . $fixtures_folder . 'no-file.nope was not found',
					'sideloaded-file-name' => '',
					'sideloaded-wp-core'   => '',
					'file-binary-mime'     => '',
					'mime-default-ext'     => '',
				],
			],
			'unallowed file'                             => [
				[ 
					// file created by hand.
					'path'                 => $fixtures_folder . 'html.html',
					'downloaded-file-name' => 'html.html',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'text/html',
					'mime-default-ext'     => 'htm',
				],
			],
			'filename is just the extension'             => [
				[ 
					// file created by hand.
					'path'                 => $fixtures_folder . 'html', 
					'downloaded-file-name' => 'html.htm', // default ext for mime is added.
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'text/html',
					'mime-default-ext'     => 'htm',
				],
			],
			'image (jpeg)'                               => [
				[ 
					'path'                 => $fixtures_folder . 'image-jpeg.jpeg',
					'downloaded-file-name' => 'image-jpeg.jpeg',
					'sideloaded-file-name' => 'image-jpeg.jpeg',
					'sideloaded-wp-core'   => 'image-jpeg.jpeg',
					'file-binary-mime'     => 'image/jpeg',
					'mime-default-ext'     => 'jpg',
				],
			],
			'image (jpg)'                                => [
				[ 
					'path'                 => $fixtures_folder . 'image-jpeg.jpg',
					'downloaded-file-name' => 'image-jpeg.jpg',
					'sideloaded-file-name' => 'image-jpeg.jpg',
					'sideloaded-wp-core'   => 'image-jpeg.jpg',
					'file-binary-mime'     => 'image/jpeg',
					'mime-default-ext'     => 'jpg',
				],
			],
			'image no ext'                               => [
				[ 
					'path'                 => $fixtures_folder . 'image-jpeg-no-extension',
					'downloaded-file-name' => 'image-jpeg-no-extension.jpg',
					'sideloaded-file-name' => 'image-jpeg-no-extension.jpg',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'image/jpeg',
					'mime-default-ext'     => 'jpg',
				],
			],
			'image unknown ext'                          => [
				[ 
					'path'                 => $fixtures_folder . 'image-jpeg-unknown-extension.unknown',
					'downloaded-file-name' => 'image-jpeg-unknown-extension.unknown.jpg',
					'sideloaded-file-name' => 'image-jpeg-unknown-extension.unknown.jpg',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'image/jpeg',
					'mime-default-ext'     => 'jpg',
				],
			],
			'image unallowed extension'                  => [
				[ 
					'path'                 => $fixtures_folder . 'image-jpeg-wrong-bad-extension.exe',
					'downloaded-file-name' => 'image-jpeg-wrong-bad-extension.exe',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'image/jpeg',
					'mime-default-ext'     => 'jpg',
				],
			],
			'image other extension'                      => [
				[ 
					'path'                 => $fixtures_folder . 'image-jpeg-wrong-extension.png',
					'downloaded-file-name' => 'image-jpeg-wrong-extension.png',
					'sideloaded-file-name' => 'image-jpeg-wrong-extension.jpg',
					'sideloaded-wp-core'   => 'image-jpeg-wrong-extension.jpg',
					'file-binary-mime'     => 'image/jpeg',
					'mime-default-ext'     => 'jpg',
				],
			],
			'application-octet bug?'                     => [
				[ 
					'path'                 => $fixtures_folder . 'image-sgi-no-extension',
					'downloaded-file-name' => 'image-sgi-no-extension.psd', // core bug?
					'sideloaded-file-name' => 'image-sgi-no-extension.psd', // core bug?
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.', // core does not sideload.
					'file-binary-mime'     => 'application/octet-stream',
					'mime-default-ext'     => 'psd', // core bug?
				],
			],
			'application-octet bug? again'               => [
				[ 
					'path'                 => $fixtures_folder . 'image-sgi.sgi',
					'downloaded-file-name' => 'image-sgi.sgi.psd', // core bug?
					'sideloaded-file-name' => 'image-sgi.sgi_.psd', // core bug?
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.', // core does not sideload.
					'file-binary-mime'     => 'application/octet-stream',
					'mime-default-ext'     => 'psd',  // core bug?
				],
			],
			'application-octet bug? again again'         => [
				[ 
					'path'                 => $fixtures_folder . 'image-sgi-uknown-extension.unknown',
					'downloaded-file-name' => 'image-sgi-uknown-extension.unknown.psd', // core bug?
					'sideloaded-file-name' => 'image-sgi-uknown-extension.unknown.psd', // core bug?
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.', // core does not sideload.
					'file-binary-mime'     => 'application/octet-stream',
					'mime-default-ext'     => 'psd', // core bug?
				],
			],
			'application-octet unallowed extension'      => [
				[ 
					'path'                 => $fixtures_folder . 'image-sgi-wrong-bad-extension.exe',
					'downloaded-file-name' => 'image-sgi-wrong-bad-extension.exe',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'application/octet-stream',
					'mime-default-ext'     => 'psd', // core bug?
				],
			],
			'application-octet bug? allowed ext'         => [
				[ 
					'path'                 => $fixtures_folder . 'image-sgi-wrong-extension.png',
					'downloaded-file-name' => 'image-sgi-wrong-extension.png',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'application/octet-stream',
					'mime-default-ext'     => 'psd', // core bug?
				],
			],
			'application-octet bug? other ext'           => [
				[ 
					'path'                 => $fixtures_folder . 'image-sgi-wrong-extension-non-specific.zip',
					'downloaded-file-name' => 'image-sgi-wrong-extension-non-specific.zip',
					'sideloaded-file-name' => 'image-sgi-wrong-extension-non-specific.zip',
					'sideloaded-wp-core'   => 'image-sgi-wrong-extension-non-specific.zip',
					'file-binary-mime'     => 'application/octet-stream',
					'mime-default-ext'     => 'psd', // core bug?
				],
			],
			'application-octet bug? other ext (again)'   => [
				[ 
					'path'                 => $fixtures_folder . 'image-sgi-wrong-extension-non-specific-video.mov',
					'downloaded-file-name' => 'image-sgi-wrong-extension-non-specific-video.mov',
					'sideloaded-file-name' => 'image-sgi-wrong-extension-non-specific-video.mov',
					'sideloaded-wp-core'   => 'image-sgi-wrong-extension-non-specific-video.mov',
					'file-binary-mime'     => 'application/octet-stream',
					'mime-default-ext'     => 'psd', // core bug?
				],
			],
			'photoshop (unknown mime)'                   => [
				[ 
					'path'                 => $fixtures_folder . 'photoshop.psd',
					'downloaded-file-name' => 'photoshop.psd',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'image/vnd.adobe.photoshop',
					'mime-default-ext'     => false,
				],
			],
			'photoshop (unknown mime) no extension'      => [
				[ 
					'path'                 => $fixtures_folder . 'photoshop-no-extension',
					'downloaded-file-name' => 'photoshop-no-extension',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'image/vnd.adobe.photoshop',
					'mime-default-ext'     => false,
				],
			],
			'photoshop (unknown mime) unknown ext'       => [
				[ 
					'path'                 => $fixtures_folder . 'photoshop-uknown-extension.unknown',
					'downloaded-file-name' => 'photoshop-uknown-extension.unknown',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'image/vnd.adobe.photoshop',
					'mime-default-ext'     => false,
				],
			],
			'photoshop (unknown mime) bad ext'           => [
				[ 
					'path'                 => $fixtures_folder . 'photoshop-wrong-bad-extension.exe',
					'downloaded-file-name' => 'photoshop-wrong-bad-extension.exe',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'image/vnd.adobe.photoshop',
					'mime-default-ext'     => false,
				],
			],
			'photoshop (unknown mime) other ext'         => [
				[ 
					'path'                 => $fixtures_folder . 'photoshop-wrong-extension-non-specific.zip',
					'downloaded-file-name' => 'photoshop-wrong-extension-non-specific.zip',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'image/vnd.adobe.photoshop',
					'mime-default-ext'     => false,
				],
			],
			'photoshop (unknown mime) other allowed ext' => [
				[ 
					'path'                 => $fixtures_folder . 'photoshop-wrong-extension.png',
					'downloaded-file-name' => 'photoshop-wrong-extension.png',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'image/vnd.adobe.photoshop',
					'mime-default-ext'     => false,
				],
			],
			// Shockwave Flash files created by: echo 'RldTBxAAAAAIAAAMAQAAAA==' | base64 --decode > test.swf
			// verified: file --mime-type test.swf
			// verified: php -r 'echo finfo_file( finfo_open( FILEINFO_MIME_TYPE ), "test.swf" ) . "\n";'
			'flash swf'                                  => [
				[ 
					'path'                 => $fixtures_folder . 'shockwave-flash.swf',
					'downloaded-file-name' => 'shockwave-flash.swf',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'application/x-shockwave-flash',
					'mime-default-ext'     => 'swf',
				],
			],
			'flash no ext'                               => [
				[ 
					'path'                 => $fixtures_folder . 'shockwave-flash-no-extension',
					'downloaded-file-name' => 'shockwave-flash-no-extension.swf',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'application/x-shockwave-flash',
					'mime-default-ext'     => 'swf',
				],
			],
			'flash unknown ext'                          => [
				[ 
					'path'                 => $fixtures_folder . 'shockwave-flash-unknown-extension.unknown',
					'downloaded-file-name' => 'shockwave-flash-unknown-extension.unknown.swf',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'application/x-shockwave-flash',
					'mime-default-ext'     => 'swf',
				],
			],
			'flash unallowed ext'                        => [
				[ 
					'path'                 => $fixtures_folder . 'shockwave-flash-wrong-bad-extension.exe',
					'downloaded-file-name' => 'shockwave-flash-wrong-bad-extension.exe',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'application/x-shockwave-flash',
					'mime-default-ext'     => 'swf',
				],
			],
			'flash wrong allowed ext'                    => [
				[ 
					'path'                 => $fixtures_folder . 'shockwave-flash-wrong-extension.png',
					'downloaded-file-name' => 'shockwave-flash-wrong-extension.png',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'application/x-shockwave-flash',
					'mime-default-ext'     => 'swf',
				],
			],
			'true vision uknown mime undetected ext'     => [
				[ 
					'path'                 => $fixtures_folder . 'truevision.tga',
					'downloaded-file-name' => 'truevision.tga',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'image/x-tga',
					'mime-default-ext'     => false,
				],
			],
			'true vision uknown mime no ext'             => [
				[ 
					'path'                 => $fixtures_folder . 'truevision-no-extension',
					'downloaded-file-name' => 'truevision-no-extension',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'image/x-tga',
					'mime-default-ext'     => false,
				],
			],
			'true vision uknown mime unknown ext'        => [
				[ 
					'path'                 => $fixtures_folder . 'truevision-unknown-extension.unknown',
					'downloaded-file-name' => 'truevision-unknown-extension.unknown',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'image/x-tga',
					'mime-default-ext'     => false,
				],
			],
			'true vision uknown mime unallowed ext'      => [
				[ 
					'path'                 => $fixtures_folder . 'truevision-wrong-bad-extension.exe',
					'downloaded-file-name' => 'truevision-wrong-bad-extension.exe',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'image/x-tga',
					'mime-default-ext'     => false,
				],
			],
			'true vision uknown mime wrong allowed ext'  => [
				[ 
					'path'                 => $fixtures_folder . 'truevision-wrong-extension.png',
					'downloaded-file-name' => 'truevision-wrong-extension.png',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'image/x-tga',
					'mime-default-ext'     => false,
				],
			],
			'word'                                       => [
				[ 
					'path'                 => $fixtures_folder . 'word.docx',
					'downloaded-file-name' => 'word.docx',
					'sideloaded-file-name' => 'word.docx',
					'sideloaded-wp-core'   => 'word.docx',
					'file-binary-mime'     => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
					'mime-default-ext'     => 'docx',
				],
			],
			'word no ext'                                => [
				[ 
					'path'                 => $fixtures_folder . 'word-no-extension',
					'downloaded-file-name' => 'word-no-extension.docx',
					'sideloaded-file-name' => 'word-no-extension.docx',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
					'mime-default-ext'     => 'docx',
				],
			],
			'word unknown ext'                           => [
				[ 
					'path'                 => $fixtures_folder . 'word-unknown-extension.unknown',
					'downloaded-file-name' => 'word-unknown-extension.unknown.docx',
					'sideloaded-file-name' => 'word-unknown-extension.unknown.docx',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
					'mime-default-ext'     => 'docx',
				],
			],
			'word unallowed ext'                         => [
				[ 
					'path'                 => $fixtures_folder . 'word-wrong-bad-extension.exe',
					'downloaded-file-name' => 'word-wrong-bad-extension.exe',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
					'mime-default-ext'     => 'docx',
				],
			],
			'word wrong allowed ext'                     => [
				[ 
					'path'                 => $fixtures_folder . 'word-wrong-extension.png',
					'downloaded-file-name' => 'word-wrong-extension.png',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
					'mime-default-ext'     => 'docx',
				],
			],
			'URL'                                        => [
				[
					'path'                 => 'https://i0.wp.com/newspack.com/wp-content/uploads/2025/02/newspack-logo.png',
					'downloaded-file-name' => 'newspack-logo.png',
					'sideloaded-file-name' => 'newspack-logo.png',
					'sideloaded-wp-core'   => 'newspack-logo.png',
					'file-binary-mime'     => 'image/png',
					'mime-default-ext'     => 'png',
				],
			],
			'URL with querystring'                       => [
				[
					'path'                 => 'https://i0.wp.com/newspack.com/wp-content/uploads/2025/02/newspack-logo.png?resize=768%2C156&ssl=1',
					'downloaded-file-name' => 'newspack-logo.png?resize=768%2C156&ssl=1.png',
					'sideloaded-file-name' => 'newspack-logo.pngresize7682C156ssl1.png',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'image/png',
					'mime-default-ext'     => 'png',
				],
			],
			'URL with querystring, but extension is followed by slash /' => [
				[
					'path'                 => 'https://dummyimage.com/600x400.jpg/',
					'downloaded-file-name' => '600x400.jpg.jpg',
					'sideloaded-file-name' => '600x400.jpg.jpg',
					'sideloaded-wp-core'   => '600x400.jpg', // basename => 600x400.jpg
					'file-binary-mime'     => 'image/jpeg',
					'mime-default-ext'     => 'jpg',
				],
			],
			'URL with querystring, but extension is followed by another /path/.' => [
				[
					'path'                 => 'https://dummyimage.com/600x400.jpg/000/',
					'downloaded-file-name' => '000.jpg',
					'sideloaded-file-name' => '000.jpg',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'image/jpeg',
					'mime-default-ext'     => 'jpg',
				],
			],
			'URL with querystring, but extension is followed by another /path/ and querystring.' => [
				[
					'path'                 => 'https://dummyimage.com/600x400.jpg/000/fff&text=Iz+test',
					'downloaded-file-name' => 'fff&text=Iz+test.jpg',
					'sideloaded-file-name' => 'ffftextIztest.jpg',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'image/jpeg',
					'mime-default-ext'     => 'jpg',
				],
			],
			'URL with querystring, but extension is followed by / and ?' => [
				[
					'path'                 => 'https://dummyimage.com/600x400.jpg/?text=hello',
					'downloaded-file-name' => '?text=hello.jpg',
					'sideloaded-file-name' => 'texthello.jpg',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'image/jpeg',
					'mime-default-ext'     => 'jpg',
				],
			],
			'URL with querystring, but extension is followed by / and ? with querystring having the extension' => [
				[
					'path'                 => 'https://dummyimage.com/600x400.jpg?text=hello.gif',
					'downloaded-file-name' => '600x400.jpg?text=hello.gif',
					'sideloaded-file-name' => '600x400.jpgtexthello.jpg',
					'sideloaded-wp-core'   => '600x400.jpgtexthello.jpg',
					'file-binary-mime'     => 'image/jpeg',
					'mime-default-ext'     => 'jpg',
				],
			],
			'URL with querystring, but extension is followed by / and ? with querystring having bad extension' => [
				[
					'path'                 => 'https://dummyimage.com/600x400.jpg?text=hello.swf',
					'downloaded-file-name' => '600x400.jpg?text=hello.swf',
					'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'image/jpeg',
					'mime-default-ext'     => 'jpg',
				],
			],
			'URL with querystring, but extension is followed by / and ? with querystring having unknown extension' => [
				[
					'path'                 => 'https://dummyimage.com/600x400.jpg?text=hello.unknown',
					'downloaded-file-name' => '600x400.jpg?text=hello.unknown.jpg',
					'sideloaded-file-name' => '600x400.jpgtexthello.unknown.jpg',
					'sideloaded-wp-core'   => 'Sorry, you are not allowed to upload this file type.',
					'file-binary-mime'     => 'image/jpeg',
					'mime-default-ext'     => 'jpg',
				],
			],
		];
	}
}
