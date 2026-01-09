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
	 * Test downloading an image.
	 * @dataProvider download_image_provider
	 */
	public function test_download_image( $file_name, $expected_download, $expected_sideload,
		$expected_mime, $expected_default_ext, $expected_wp_check ) {

		$result = Attachments::download_file( self::MIMES_AND_EXTS_FOLDER . $file_name );
		
		// Check for Download error just incase.
		if ( is_wp_error( $result ) ) {
			$this->assertSame( $expected_download, $result->get_error_code() );
			return;
		} 

		// Check values:
		$this->assertSame( $expected_download, $result['name'] );
		$this->assertFileExists( $result['tmp_name'] );

		// Verify mime, then, verify it's default ext.
		$this->assertSame( $expected_mime, mime_content_type( $result['tmp_name'] ) );
		$this->assertSame( $expected_mime, finfo_file( finfo_open( FILEINFO_MIME_TYPE ), $result['tmp_name'] ) );
		$this->assertSame( $expected_default_ext, wp_get_default_extension_for_mime_type( $expected_mime ) );
		$this->assertSame( $expected_wp_check, implode( ',', wp_check_filetype_and_ext( $result['tmp_name'], $result['name'] ) ) );
		
		// Verify result works as expecpted with sideload.
		$sideload_id = media_handle_sideload( $result );
		
		if ( is_wp_error( $sideload_id ) ) {
			$this->assertSame( $expected_sideload, $sideload_id->get_error_message() );
			return;
		} 

		// Verify created attachment name matches from the sideload.
		$sideloaded_path = wp_attachment_is_image( $sideload_id ) ? 
			wp_get_original_image_path( $sideload_id, true ) : 
			get_post_meta( $sideload_id, '_wp_attached_file', true );

		// Verify
		$this->assertSame( $expected_sideload, preg_replace(
			'/-\d+(?=\.[^.]+$)/', // remove any -2 duplicates just in case.
			'',
			wp_basename( $sideloaded_path )
		));

	}

	/**
	 * Data provider for test_download_image
	 *
	 * @return array[]
	 */
	public function download_image_provider(): array {

		
		/*
media: media_sideload_image - no tests - thin function
 ==> media: media_handle_sideload - no tests - thin function
        file: wp_handle_sideload - no tests - thin function
            file: _wp_handle_upload - no tests - thin function


media: media_handle_upload > tests/media.php - since this is tested, that means _wp_handle_upload is actually tested. Very limited tests!
    file: wp_handle_upload
        file: _wp_handle_upload
    
function: _wp_handle_upload

    $wp_filetype     = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );

        calls: $wp_filetype = wp_check_filetype( $filename, $mimes );
            uses: get_allowed_mime_types();
                runs:
                    - wp_get_mime_types() -- this is ALL MIMES
                sets:
                - unset( $t['swf'], $t['exe'] );
                - if...$unfiltered...unset( $t['htm|html'], $t['js'] )
        runs:
            $finfo     = finfo_open( FILEINFO_MIME_TYPE );
            $real_mime = finfo_file( $finfo, $file );


*/

		// old August code:

		return [
			[
				'no-file.nope',
				'File ' . self::MIMES_AND_EXTS_FOLDER .'no-file.nope was not found',
				'',
				'',
				'',
				'',
			],
			[ 
				'image-jpeg.jpeg',
				'image-jpeg.jpeg',
				'image-jpeg.jpeg',
				'image/jpeg',
				'jpg',
				'jpeg,image/jpeg,',
			],
			[ 
				'image-jpeg.jpg',
				'image-jpeg.jpg',
				'image-jpeg.jpg',
				'image/jpeg',
				'jpg',
				'jpg,image/jpeg,',
			],
			[ 
				'image-jpeg-no-extension',
				'image-jpeg-no-extension.jpg', // fixed by download.
				'image-jpeg-no-extension.jpg', // fixed by download.
				'image/jpeg',
				'jpg',
				'jpg,image/jpeg,', // fixed by download.
			],
			[ 
				'image-jpeg-unknown-extension.unknown',
				'image-jpeg-unknown-extension.unknown.jpg', // fixed with new PR
				'image-jpeg-unknown-extension.unknown.jpg', // fixed with new PR
				'image/jpeg',
				'jpg',
				'jpg,image/jpeg,', // fixed with new PR
			],
			[ 
				'image-jpeg-wrong-bad-extension.exe',
				'image-jpeg-wrong-bad-extension.exe.jpg', // fixed with new PR
				'image-jpeg-wrong-bad-extension.exe_.jpg', // fixed with new PR, what is _?
				'image/jpeg',
				'jpg',
				'jpg,image/jpeg,', // fixed with new PR
			],
			[ 
				'image-jpeg-wrong-extension.png',
				'image-jpeg-wrong-extension.png',
				'image-jpeg-wrong-extension.jpg',
				'image/jpeg',
				'jpg',
				'jpg,image/jpeg,image-jpeg-wrong-extension.jpg',
			],
			[ 
				'image-sgi-no-extension',
				'image-sgi-no-extension.psd', // "fixed" by download - same "psd" (application/octet-stream) bug...
				'image-sgi-no-extension.psd', // "fixed" by download - same "psd" (application/octet-stream) bug...
				'application/octet-stream',
				'psd', // bug??
				'psd,application/octet-stream,', // "fixed" by download - same "psd" (application/octet-stream) bug...
			],
			[ 
				'image-sgi.sgi',
				'image-sgi.sgi.psd', // new PR: same psd bug....
				'image-sgi.sgi_.psd', // new PR: same psd bug....
				'application/octet-stream',
				'psd', // bug??
				'psd,application/octet-stream,', // new PR: same psd bug....
			],
			[ 
				'image-sgi-uknown-extension.unknown',
				'image-sgi-uknown-extension.unknown.psd', // new PR: same psd bug....
				'image-sgi-uknown-extension.unknown.psd', // new PR: same psd bug....
				'application/octet-stream',
				'psd', // bug??
				'psd,application/octet-stream,', // new PR: same psd bug....
			],
			[ 
				'image-sgi-wrong-bad-extension.exe',
				'image-sgi-wrong-bad-extension.exe.psd', // new PR: same psd bug....
				'image-sgi-wrong-bad-extension.exe_.psd', // new PR: same psd bug....
				'application/octet-stream',
				'psd', // bug??
				'psd,application/octet-stream,', // new PR: same psd bug....
			],
			[ 
				'image-sgi-wrong-extension.png',
				'image-sgi-wrong-extension.png',
				'Sorry, you are not allowed to upload this file type.',
				'application/octet-stream',
				'psd', // bug??
				',,',
			],
			[ 
				// see functions.php => wp_check_filetype_and_ext => $nonspecific_types 
				// does this allow the possiblity of uploading any octet-stream as a different
				// $nonspecific_types ?
				// 1) try to get application/x-dosexec uploaded this way?
				// 2) try to bypass this: file.php: _wp_handle_upload: if ( ( ! $type || ! $ext ) && ! current_user_can( 'unfiltered_upload' ) ) {
				'image-sgi-wrong-extension-non-specific.zip',
				'image-sgi-wrong-extension-non-specific.zip',
				'image-sgi-wrong-extension-non-specific.zip',
				'application/octet-stream',
				'psd', // bug??
				'zip,application/zip,',
			],
			[ 
				// see functions.php => wp_check_filetype_and_ext => $nonspecific_types 
				// does this allow the possiblity of uploading any octet-stream as a different
				// $nonspecific_types ?
				'image-sgi-wrong-extension-non-specific-video.mov',
				'image-sgi-wrong-extension-non-specific-video.mov',
				'image-sgi-wrong-extension-non-specific-video.mov',
				'application/octet-stream',
				'psd', // bug??
				'mov,video/quicktime,',
			],
			[ 
				'photoshop-no-extension',
				'photoshop-no-extension',
				'Sorry, you are not allowed to upload this file type.',
				'image/vnd.adobe.photoshop',
				false, // bug?
				',,',
			],
			[ 
				'photoshop.psd',
				'photoshop.psd',
				'Sorry, you are not allowed to upload this file type.',
				'image/vnd.adobe.photoshop',
				false, // bug?
				',,',
			],
			[ 
				'photoshop-uknown-extension.unknown',
				'photoshop-uknown-extension.unknown',
				'Sorry, you are not allowed to upload this file type.',
				'image/vnd.adobe.photoshop',
				false, // bug?
				',,',
			],
			[ 
				'photoshop-wrong-bad-extension.exe',
				'photoshop-wrong-bad-extension.exe',
				'Sorry, you are not allowed to upload this file type.',
				'image/vnd.adobe.photoshop',
				false, // bug?
				',,',
			],
			[ 
				'photoshop-wrong-extension-non-specific.zip',
				'photoshop-wrong-extension-non-specific.zip',
				'Sorry, you are not allowed to upload this file type.',
				'image/vnd.adobe.photoshop',
				false, // bug?
				',,',
			],
			[ 
				'photoshop-wrong-extension.png',
				'photoshop-wrong-extension.png',
				'Sorry, you are not allowed to upload this file type.',
				'image/vnd.adobe.photoshop',
				false, // bug?
				',,',
			],
			// Shockwave Flash
			// created via: echo 'RldTBxAAAAAIAAAMAQAAAA==' | base64 --decode > test.swf
			// verified: file --mime-type test.swf
			// verified: php -r 'echo finfo_file( finfo_open( FILEINFO_MIME_TYPE ), "test.swf" ) . "\n";'
			[ 
				'shockwave-flash-no-extension',
				'shockwave-flash-no-extension.swf', // fixed by download.
				'Sorry, you are not allowed to upload this file type.',
				'application/x-shockwave-flash',
				'swf',
				',,',
			],
			[ 
				'shockwave-flash.swf',
				'shockwave-flash.swf.swf', // new PR 
				'Sorry, you are not allowed to upload this file type.',
				'application/x-shockwave-flash',
				'swf',
				',,',
			],
			[ 
				'shockwave-flash-unknown-extension.unknown',
				'shockwave-flash-unknown-extension.unknown.swf', // new PR 
				'Sorry, you are not allowed to upload this file type.',
				'application/x-shockwave-flash',
				'swf',
				',,',
			],
			[ 
				'shockwave-flash-wrong-bad-extension.exe',
				'shockwave-flash-wrong-bad-extension.exe.swf', // new PR 
				'Sorry, you are not allowed to upload this file type.',
				'application/x-shockwave-flash',
				'swf',
				',,',
			],
			[ 
				'shockwave-flash-wrong-extension.png',
				'shockwave-flash-wrong-extension.png',
				'Sorry, you are not allowed to upload this file type.',
				'application/x-shockwave-flash',
				'swf',
				',,',
			],
			[ 
				'truevision-no-extension',
				'truevision-no-extension',
				'Sorry, you are not allowed to upload this file type.',
				'image/x-tga',
				false,
				',,',
			],
			[ 
				'truevision.tga',
				'truevision.tga',
				'Sorry, you are not allowed to upload this file type.',
				'image/x-tga',
				false,
				',,',
			],
			[ 
				'truevision-unknown-extension.unknown',
				'truevision-unknown-extension.unknown',
				'Sorry, you are not allowed to upload this file type.',
				'image/x-tga',
				false,
				',,',
			],
			[ 
				'truevision-wrong-bad-extension.exe',
				'truevision-wrong-bad-extension.exe',
				'Sorry, you are not allowed to upload this file type.',
				'image/x-tga',
				false,
				',,',
			],
			[ 
				'truevision-wrong-extension.png',
				'truevision-wrong-extension.png',
				'Sorry, you are not allowed to upload this file type.',
				'image/x-tga',
				false,
				',,',
			],
			[ 
				'word.docx',
				'word.docx',
				'word.docx',
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'docx',
				'docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document,',
			],
			[ 
				'word-no-extension',
				'word-no-extension.docx', // fix by download.
				'word-no-extension.docx', // fix by download.
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'docx',
				'docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document,', // fix by download.
			],
			[ 
				'word-unknown-extension.unknown',
				'word-unknown-extension.unknown.docx', // new PR 
				'word-unknown-extension.unknown.docx', // new PR 
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'docx',
				'docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document,', // new PR 
			],
			[ 
				'word-wrong-bad-extension.exe',
				'word-wrong-bad-extension.exe.docx', // new PR 
				'word-wrong-bad-extension.exe_.docx', // ? what is _ ? // new PR 
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'docx',
				'docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document,', // new PR 
			],
			[ 
				'word-wrong-extension.png',
				'word-wrong-extension.png',
				'Sorry, you are not allowed to upload this file type.',
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'docx',
				',,',
			],
		];

	}
}
