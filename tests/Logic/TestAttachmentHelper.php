<?php

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\MigrationTools\Logic\Attachments;
use Newspack\MigrationTools\Tests\AttachmentUnitTestTrait;
use WP_UnitTestCase;

class TestAttachmentHelper extends WP_UnitTestCase {

	use AttachmentUnitTestTrait;

	const MIMES_AND_EXTS_FOLDER = 'tests/fixtures/mimes-and-exts/';

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
	 * Test media_handle_sideload.
	 * 
	 * WordPress core doesn't have much test coverage for sideloading. 
	 * 
	 * 
	 * 
* 	 media: media_sideload_image - no tests - thin function
*  ==> media: media_handle_sideload - no tests - thin function
*         file: wp_handle_sideload - no tests - thin function
*             file: _wp_handle_upload - no tests - thin function
* 
* 
* media: media_handle_upload > tests/media.php - since this is tested, that means _wp_handle_upload is actually tested. Very limited tests!
*     file: wp_handle_upload
*         file: _wp_handle_upload
*     
* function: _wp_handle_upload
* 
*     $wp_filetype     = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
* 
*         calls: $wp_filetype = wp_check_filetype( $filename, $mimes );
*             uses: get_allowed_mime_types();
*                 runs:
*                     - wp_get_mime_types() -- this is ALL MIMES
*                 sets:
*                 - unset( $t['swf'], $t['exe'] );
*                 - if...$unfiltered...unset( $t['htm|html'], $t['js'] )
*         runs:
*             $finfo     = finfo_open( FILEINFO_MIME_TYPE );
*             $real_mime = finfo_file( $finfo, $file );
* 
	 * 
	 * @dataProvider provider_mimes_and_exts_fixtures
	 * 
	 */	
	public function test_media_handle_sideload( array $provider ) {

		$path = self::MIMES_AND_EXTS_FOLDER . $provider['test-file'];

		// The `media_handle_sideload()` function deletes the local file after import, so to preserve the local path, we're
		// first saving it to a temp location, in exactly the same way the WP's own `\download_url()` function above does.
		if ( ! file_exists( $path ) ) {
			$this->assertSame( $provider['sideloaded-wp-core'], sprintf( 'File %s was not found', $path ) );
			return;
		}
		$tmpfname = wp_tempnam( $path );
		// todo test: $tmpfname was writable...
		copy( $path, $tmpfname );
		if ( filesize( $tmpfname ) < 1 ) {
			$this->assertSame( $provider['sideloaded-wp-core'], sprintf( 'File %s was empty', $path ) );
			return;
		}

		$downloaded_file_array = [
			'name'     => wp_basename( $path ),
			'tmp_name' => $tmpfname,
		];

		// Verify result works as expecpted with sideload.
		$sideload_id = media_handle_sideload( $downloaded_file_array );
		
		if ( is_wp_error( $sideload_id ) ) {
			$this->assertSame( $provider['sideloaded-wp-core'], $sideload_id->get_error_message() );
			return;
		} 

		// Verify created attachment name matches from the sideload.
		$sideloaded_path = wp_attachment_is_image( $sideload_id ) ? 
			wp_get_original_image_path( $sideload_id, true ) : 
			get_post_meta( $sideload_id, '_wp_attached_file', true );

		// Verify
		$this->assertSame( $provider['sideloaded-wp-core'], preg_replace(
			'/-\d+(?=\.[^.]+$)/', // remove any -2 duplicates just in case.
			'',
			wp_basename( $sideloaded_path )
		));

	}

	/**
	 * Test downloading an image.
	 * @dataProvider provider_mimes_and_exts_fixtures
	 */
	public function test_download_file( array $provider ) {

		$downloaded_file_array = Attachments::download_file( self::MIMES_AND_EXTS_FOLDER . $provider['test-file'] );
		
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
		$this->assertSame( $provider['file-extension'], wp_get_default_extension_for_mime_type( mime_content_type( $downloaded_file_array['tmp_name'] ) ) );
		$this->assertSame( $provider['file-extension'], wp_get_default_extension_for_mime_type( $provider['file-binary-mime'] ) );
		$this->assertSame( $provider['wp-check'], implode( ',', wp_check_filetype_and_ext( $downloaded_file_array['tmp_name'], $downloaded_file_array['name'] ) ) );
		
		// Verify result works as expecpted with sideload.
		$sideload_id = media_handle_sideload( $downloaded_file_array );
		
		if ( is_wp_error( $sideload_id ) ) {
			$this->assertSame( $provider['sideloaded-file-name'], $sideload_id->get_error_message() );
			return;
		} 

		// Verify created attachment name matches from the sideload.
		$sideloaded_path = wp_attachment_is_image( $sideload_id ) ? 
			wp_get_original_image_path( $sideload_id, true ) : 
			get_post_meta( $sideload_id, '_wp_attached_file', true );

		// Verify
		$this->assertSame( $provider['sideloaded-file-name'], preg_replace(
			'/-\d+(?=\.[^.]+$)/', // remove any -2 duplicates just in case.
			'',
			wp_basename( $sideloaded_path )
		));

	}

	/**
	 * Data provider for mimes and exts fixtures.
	 *
	 * @return array[]
	 */
	public function provider_mimes_and_exts_fixtures(): array {

		return [
			[[
				'test-file' => 'no-file.nope',
				'downloaded-file-name' => 'File ' . self::MIMES_AND_EXTS_FOLDER .'no-file.nope was not found',
				'sideloaded-file-name' => '',
				'sideloaded-wp-core' => 'File ' . self::MIMES_AND_EXTS_FOLDER .'no-file.nope was not found',
				'file-binary-mime' => '',
				'file-extension' => '',
				'wp-check' => '',
			]],
			[[ 
				'test-file' => 'image-jpeg.jpeg',
				'downloaded-file-name' => 'image-jpeg.jpeg',
				'sideloaded-file-name' => 'image-jpeg.jpeg',
				'sideloaded-wp-core' => 'image-jpeg.jpeg',
				'file-binary-mime' => 'image/jpeg',
				'file-extension' => 'jpg',
				'wp-check' => 'jpeg,image/jpeg,',
			]],
			[[ 
				'test-file' => 'image-jpeg.jpg',
				'downloaded-file-name' => 'image-jpeg.jpg',
				'sideloaded-file-name' => 'image-jpeg.jpg',
				'sideloaded-wp-core' => 'image-jpeg.jpg',
				'file-binary-mime' => 'image/jpeg',
				'file-extension' => 'jpg',
				'wp-check' => 'jpg,image/jpeg,',
			]],
			[[ 
				'test-file' => 'image-jpeg-no-extension',
				'downloaded-file-name' => 'image-jpeg-no-extension.jpg', // fixed by download.
				'sideloaded-file-name' => 'image-jpeg-no-extension.jpg', // fixed by download.
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'image/jpeg',
				'file-extension' => 'jpg',
				'wp-check' => 'jpg,image/jpeg,', // fixed by download.
			]],
			[[ 
				'test-file' => 'image-jpeg-unknown-extension.unknown',
				'downloaded-file-name' => 'image-jpeg-unknown-extension.unknown.jpg', // fixed with new PR
				'sideloaded-file-name' => 'image-jpeg-unknown-extension.unknown.jpg', // fixed with new PR
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'image/jpeg',
				'file-extension' => 'jpg',
				'wp-check' => 'jpg,image/jpeg,', // fixed with new PR
			]],
			[[ 
				'test-file' => 'image-jpeg-wrong-bad-extension.exe',
				'downloaded-file-name' => 'image-jpeg-wrong-bad-extension.exe.jpg', // fixed with new PR
				'sideloaded-file-name' => 'image-jpeg-wrong-bad-extension.exe_.jpg', // fixed with new PR, what is _?
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'image/jpeg',
				'file-extension' => 'jpg',
				'wp-check' => 'jpg,image/jpeg,', // fixed with new PR
			]],
			[[ 
				'test-file' => 'image-jpeg-wrong-extension.png',
				'downloaded-file-name' => 'image-jpeg-wrong-extension.png',
				'sideloaded-file-name' => 'image-jpeg-wrong-extension.jpg',
				'sideloaded-wp-core' => 'image-jpeg-wrong-extension.jpg',
				'file-binary-mime' => 'image/jpeg',
				'file-extension' => 'jpg',
				'wp-check' => 'jpg,image/jpeg,image-jpeg-wrong-extension.jpg',
			]],
			[[ 
				'test-file' => 'image-sgi-no-extension',
				'downloaded-file-name' => 'image-sgi-no-extension.psd', // "fixed" by download - same "psd" (application/octet-stream) bug...
				'sideloaded-file-name' => 'image-sgi-no-extension.psd', // "fixed" by download - same "psd" (application/octet-stream) bug...
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'application/octet-stream',
				'file-extension' => 'psd', // bug??
				'wp-check' => 'psd,application/octet-stream,', // "fixed" by download - same "psd" (application/octet-stream) bug...
			]],
			[[ 
				'test-file' => 'image-sgi.sgi',
				'downloaded-file-name' => 'image-sgi.sgi.psd', // new PR: same psd bug....
				'sideloaded-file-name' => 'image-sgi.sgi_.psd', // new PR: same psd bug....
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'application/octet-stream',
				'file-extension' => 'psd', // bug??
				'wp-check' => 'psd,application/octet-stream,', // new PR: same psd bug....
			]],
			[[ 
				'test-file' => 'image-sgi-uknown-extension.unknown',
				'downloaded-file-name' => 'image-sgi-uknown-extension.unknown.psd', // new PR: same psd bug....
				'sideloaded-file-name' => 'image-sgi-uknown-extension.unknown.psd', // new PR: same psd bug....
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'application/octet-stream',
				'file-extension' => 'psd', // bug??
				'wp-check' => 'psd,application/octet-stream,', // new PR: same psd bug....
			]],
			[[ 
				'test-file' => 'image-sgi-wrong-bad-extension.exe',
				'downloaded-file-name' => 'image-sgi-wrong-bad-extension.exe.psd', // new PR: same psd bug....
				'sideloaded-file-name' => 'image-sgi-wrong-bad-extension.exe_.psd', // new PR: same psd bug....
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'application/octet-stream',
				'file-extension' => 'psd', // bug??
				'wp-check' => 'psd,application/octet-stream,', // new PR: same psd bug....
			]],
			[[ 
				'test-file' => 'image-sgi-wrong-extension.png',
				'downloaded-file-name' => 'image-sgi-wrong-extension.png',
				'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'application/octet-stream',
				'file-extension' => 'psd', // bug??
				'wp-check' => ',,',
			]],
			[[ 
				// see functions.php => wp_check_filetype_and_ext => $nonspecific_types 
				// does this allow the possiblity of uploading any octet-stream as a different
				// $nonspecific_types ?
				// 1) try to get application/x-dosexec uploaded this way?
				// 2) try to bypass this: file.php: _wp_handle_upload: if ( ( ! $type || ! $ext ) && ! current_user_can( 'unfiltered_upload' ) ) {
				'test-file' => 'image-sgi-wrong-extension-non-specific.zip',
				'downloaded-file-name' => 'image-sgi-wrong-extension-non-specific.zip',
				'sideloaded-file-name' => 'image-sgi-wrong-extension-non-specific.zip',
				'sideloaded-wp-core' => 'image-sgi-wrong-extension-non-specific.zip',
				'file-binary-mime' => 'application/octet-stream',
				'file-extension' => 'psd', // bug??
				'wp-check' => 'zip,application/zip,',
			]],
			[[ 
				// see functions.php => wp_check_filetype_and_ext => $nonspecific_types 
				// does this allow the possiblity of uploading any octet-stream as a different
				// $nonspecific_types ?
				'test-file' => 'image-sgi-wrong-extension-non-specific-video.mov',
				'downloaded-file-name' => 'image-sgi-wrong-extension-non-specific-video.mov',
				'sideloaded-file-name' => 'image-sgi-wrong-extension-non-specific-video.mov',
				'sideloaded-wp-core' => 'image-sgi-wrong-extension-non-specific-video.mov',
				'file-binary-mime' => 'application/octet-stream',
				'file-extension' => 'psd', // bug??
				'wp-check' => 'mov,video/quicktime,',
			]],
			[[ 
				'test-file' => 'photoshop-no-extension',
				'downloaded-file-name' => 'photoshop-no-extension',
				'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'image/vnd.adobe.photoshop',
				'file-extension' => 				false, // bug?
				'wp-check' => ',,',
			]],
			[[ 
				'test-file' => 'photoshop.psd',
				'downloaded-file-name' => 'photoshop.psd',
				'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'image/vnd.adobe.photoshop',
				'file-extension' => 				false, // bug?
				'wp-check' => ',,',
			]],
			[[ 
				'test-file' => 'photoshop-uknown-extension.unknown',
				'downloaded-file-name' => 'photoshop-uknown-extension.unknown',
				'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'image/vnd.adobe.photoshop',
				'file-extension' => 				false, // bug?
				'wp-check' => ',,',
			]],
			[[ 
				'test-file' => 'photoshop-wrong-bad-extension.exe',
				'downloaded-file-name' => 'photoshop-wrong-bad-extension.exe',
				'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'image/vnd.adobe.photoshop',
				'file-extension' => 				false, // bug?
				'wp-check' => ',,',
			]],
			[[ 
				'test-file' => 'photoshop-wrong-extension-non-specific.zip',
				'downloaded-file-name' => 'photoshop-wrong-extension-non-specific.zip',
				'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'image/vnd.adobe.photoshop',
				'file-extension' => 				false, // bug?
				'wp-check' => ',,',
			]],
			[[ 
				'test-file' => 'photoshop-wrong-extension.png',
				'downloaded-file-name' => 'photoshop-wrong-extension.png',
				'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'image/vnd.adobe.photoshop',
				'file-extension' => 				false, // bug?
				'wp-check' => ',,',
			]],
			// Shockwave Flash
			// created via: echo 'RldTBxAAAAAIAAAMAQAAAA==' | base64 --decode > test.swf
			// verified: file --mime-type test.swf
			// verified: php -r 'echo finfo_file( finfo_open( FILEINFO_MIME_TYPE ), "test.swf" ) . "\n";'
			[[ 
				'test-file' => 'shockwave-flash-no-extension',
				'downloaded-file-name' => 'shockwave-flash-no-extension.swf', // fixed by download.
				'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'application/x-shockwave-flash',
				'file-extension' => 'swf',
				'wp-check' => ',,',
			]],
			[[ 
				'test-file' => 'shockwave-flash.swf',
				'downloaded-file-name' => 'shockwave-flash.swf.swf', // new PR 
				'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'application/x-shockwave-flash',
				'file-extension' => 'swf',
				'wp-check' => ',,',
			]],
			[[ 
				'test-file' => 'shockwave-flash-unknown-extension.unknown',
				'downloaded-file-name' => 'shockwave-flash-unknown-extension.unknown.swf', // new PR 
				'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'application/x-shockwave-flash',
				'file-extension' => 'swf',
				'wp-check' => ',,',
			]],
			[[ 
				'test-file' => 'shockwave-flash-wrong-bad-extension.exe',
				'downloaded-file-name' => 'shockwave-flash-wrong-bad-extension.exe.swf', // new PR 
				'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'application/x-shockwave-flash',
				'file-extension' => 'swf',
				'wp-check' => ',,',
			]],
			[[ 
				'test-file' => 'shockwave-flash-wrong-extension.png',
				'downloaded-file-name' => 'shockwave-flash-wrong-extension.png',
				'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'application/x-shockwave-flash',
				'file-extension' => 'swf',
				'wp-check' => ',,',
			]],
			[[ 
				'test-file' => 'truevision-no-extension',
				'downloaded-file-name' => 'truevision-no-extension',
				'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'image/x-tga',
				'file-extension' => false,
				'wp-check' => ',,',
			]],
			[[ 
				'test-file' => 'truevision.tga',
				'downloaded-file-name' => 'truevision.tga',
				'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'image/x-tga',
				'file-extension' => false,
				'wp-check' => ',,',
			]],
			[[ 
				'test-file' => 'truevision-unknown-extension.unknown',
				'downloaded-file-name' => 'truevision-unknown-extension.unknown',
				'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'image/x-tga',
				'file-extension' => false,
				'wp-check' => ',,',
			]],
			[[ 
				'test-file' => 'truevision-wrong-bad-extension.exe',
				'downloaded-file-name' => 'truevision-wrong-bad-extension.exe',
				'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'image/x-tga',
				'file-extension' => false,
				'wp-check' => ',,',
			]],
			[[ 
				'test-file' => 'truevision-wrong-extension.png',
				'downloaded-file-name' => 'truevision-wrong-extension.png',
				'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'image/x-tga',
				'file-extension' => false,
				'wp-check' => ',,',
			]],
			[[ 
				'test-file' => 'word.docx',
				'downloaded-file-name' => 'word.docx',
				'sideloaded-file-name' => 'word.docx',
				'sideloaded-wp-core' => 'word.docx',
				'file-binary-mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'file-extension' => 'docx',
				'wp-check' => 'docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document,',
			]],
			[[ 
				'test-file' => 'word-no-extension',
				'downloaded-file-name' => 'word-no-extension.docx', // fix by download.
				'sideloaded-file-name' => 'word-no-extension.docx', // fix by download.
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'file-extension' => 'docx',
				'wp-check' => 'docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document,', // fix by download.
			]],
			[[ 
				'test-file' => 'word-unknown-extension.unknown',
				'downloaded-file-name' => 'word-unknown-extension.unknown.docx', // new PR 
				'sideloaded-file-name' => 'word-unknown-extension.unknown.docx', // new PR 
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'file-extension' => 'docx',
				'wp-check' => 'docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document,', // new PR 
			]],
			[[ 
				'test-file' => 'word-wrong-bad-extension.exe',
				'downloaded-file-name' => 'word-wrong-bad-extension.exe.docx', // new PR 
				'sideloaded-file-name' => 'word-wrong-bad-extension.exe_.docx', // ? what is _ ? // new PR 
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'file-extension' => 'docx',
				'wp-check' => 'docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document,', // new PR 
			]],
			[[ 
				'test-file' => 'word-wrong-extension.png',
				'downloaded-file-name' => 'word-wrong-extension.png',
				'sideloaded-file-name' => 'Sorry, you are not allowed to upload this file type.',
				'sideloaded-wp-core' => 'Sorry, you are not allowed to upload this file type.',
				'file-binary-mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'file-extension' => 'docx',
				'wp-check' => ',,',
			]],
		];
	}
}
