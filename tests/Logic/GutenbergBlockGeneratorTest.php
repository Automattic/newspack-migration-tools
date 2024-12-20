<?php

namespace Newspack\MigrationTools\Tests\Logic;

use DOMDocument;
use DOMXPath;
use Newspack\MigrationTools\Logic\Attachments;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use Newspack\MigrationTools\Tests\AttachmentUnitTestTrait;
use WP_UnitTestCase;

class GutenbergBlockGeneratorTest extends WP_UnitTestCase {

	use AttachmentUnitTestTrait;

	private GutenbergBlockGenerator $block_generator;
	private int $category_id;
	private int $test_post_1_id;

	protected function setUp(): void {
		parent::setUp();
		add_filter( 'newspack_migration_tools_log_clilog_disable', '__return_true' );
		$this->block_generator = new GutenbergBlockGenerator();
		$this->category_id     = self::factory()->category->create( [ 'name' => 'My Little Category' ] );
		$this->test_post_1_id  = self::factory()->post->create(
			[
				'post_title'   => 'Test Post 1',
				'post_content' => 'This is the content for Test Post 1.',
			] 
		);
	}

	private function get_dom( string $html ): DOMDocument {
		$dom = new DOMDocument();
		// Suppress errors to handle invalid HTML (common with HTML snippets)
		libxml_use_internal_errors( true );
		$dom->loadHtml( $html );
		// Clear any potential parsing errors
		libxml_clear_errors();

		return $dom;
	}

	private function assert_xpath_node_exists( string $html, string $xpath_expression ): void {
		$dom   = $this->get_dom( $html );
		$xpath = new DOMXPath( $dom );
		$this->assertNotEmpty( $xpath->query( $xpath_expression ), sprintf( 'XPath expression "%s" not found in: "%s"', $xpath_expression, $html ) );
	}

	public function test_get_core_embed() {
		$rickroll_url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
		$block        = $this->block_generator->get_core_embed( $rickroll_url );
		$html         = serialize_block( $block );
		$this->assertStringStartsWith( '<!-- wp:embed', $html );
		$this->assert_xpath_node_exists( $html, '//figure/div[contains(text(), "' . $rickroll_url . '")]' );
	}

	public function test_get_heading() {
		$heading_text = 'Iz heading';

		// Test default heading.
		$block = $this->block_generator->get_heading( $heading_text );
		$html  = serialize_block( $block );
		$this->assertStringStartsWith( '<!-- wp:heading', $html );
		$this->assert_xpath_node_exists( $html, '//h2[contains(text(), "' . $heading_text . '")]' );

		// Test h5 with an anchor.
		$block = $this->block_generator->get_heading( $heading_text, 'h5', 'fancy-link' );
		$html  = serialize_block( $block );
		$this->assert_xpath_node_exists( $html, '//h5[contains(text(), "' . $heading_text . '")]' );
		$this->assert_xpath_node_exists( $html, '//h5[@id="fancy-link"]' );
	}

	public function test_get_homepage_articles_for_specific_posts() {
		$block = $this->block_generator->get_homepage_articles_for_specific_posts( [ $this->test_post_1_id ], [ 'className' => [ 'one', 'two' ] ] );
		$this->assertStringContainsString( 'one two', $block['attrs']['className'] );

		$block = $this->block_generator->get_homepage_articles_for_specific_posts( [ $this->test_post_1_id ], [ 'className' => 'one two' ] );
		$this->assertStringContainsString( 'one two', $block['attrs']['className'] );
	}

	public function test_get_homepage_articles_for_category() {
		// Category exists.
		$block = $this->block_generator->get_homepage_articles_for_category( [ $this->category_id ], [] );
		$this->assertEquals( $block['attrs']['categories'], [ $this->category_id ] );

		// No posts in category - should return empty block.
		$block = $this->block_generator->get_homepage_articles_for_category( [], [] );
		$this->assertEmpty( $block );

		// Categor(ies) does not exist - should throw an exception.
		$this->expectException( \Exception::class );
		$this->block_generator->get_homepage_articles_for_category( [ 999999, 1929283 ], [] );
	}


	public function test_get_gallery() {
		$attachment_id          = Attachments::import_attachment_for_post(
			$this->test_post_1_id,
			$this->dummy_image
		);
		$this->attachment_ids[] = $attachment_id;
		$block                  = $this->block_generator->get_gallery( [ $attachment_id ] );
		$this->assertEquals( 'core/image', $block['innerBlocks'][0]['blockName'] );
	}


	public function test_get_file_pdf() {
		$attachment_id          = Attachments::import_attachment_for_post(
			$this->test_post_1_id,
			'tests/fixtures/test.pdf'
		);
		$this->attachment_ids[] = $attachment_id;
		$no_download_link_block = $this->block_generator->get_file_pdf( get_post( $attachment_id ), 'My PDF', false );
		$this->assertEquals( 'core/file', $no_download_link_block['blockName'] );
		$this->assertStringNotContainsString( 'download', $no_download_link_block['innerHTML'] );

		$with_download_link_block = $this->block_generator->get_file_pdf( get_post( $attachment_id ) );
		$this->assertStringContainsString( 'download', $with_download_link_block['innerHTML'] );
	}

	public function test_get_audio() {
		$audio_url = 'https://v1.cdnpk.net/videvo_files/audio/premium/audio0060/conversions/mp3_option/CatMeowsPurring PE916904.mp3';

		$attachment_id = Attachments::import_attachment_for_post(
			$this->test_post_1_id,
			$audio_url
		);

		$this->attachment_ids[] = $attachment_id;

		$filename = wp_basename( $audio_url );

		$audio_block = $this->block_generator->get_audio( get_post( $attachment_id ), 'Test Caption', 'Test Description', false );
		$this->assertEquals( 'core/audio', $audio_block['blockName'] );
		$this->assertStringContainsString( sanitize_file_name( $filename ), $audio_block['innerHTML'] );

		$audio_block = $this->block_generator->get_audio( $audio_url, 'Another Test Caption', 'Another Test Description', true );
		$this->assertEquals( 'core/audio', $audio_block['blockName'] );
		$this->assertStringNotContainsString( $audio_url, $audio_block['innerHTML'] );
		$this->assertStringContainsString( sanitize_file_name( $filename ), $audio_block['innerHTML'] );

		$audio_block = $this->block_generator->get_audio( $audio_url, 'Final Test Caption', 'Final Test Description', false );
		$this->assertEquals( 'core/audio', $audio_block['blockName'] );
		$this->assertStringContainsString( $audio_url, $audio_block['innerHTML'] );
		$this->assertStringContainsString( 'Final Test Caption', $audio_block['innerHTML'] );
	}

	public function youtube_url_data_provider() {
		return [
			'with_v_param'                       => [
				'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
			],
			'with_v_param_and_additional_params' => [
				'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&param=value&another_param=should-be-removed',
			],
			'with_embed_version'                 => [
				'url' => 'https://www.youtube.com/embed/N5HbZd9aqR4',
			],
			'with_youtube_code_only'             => [
				'url' => 'N5HbZd9aqR4',
			],
		];
	}

	/**
	 * @dataProvider youtube_url_data_provider
	 * @return void
	 */
	public function test_get_youtube( $url ) {
		$internal_url = $url;
		if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
			$parsed_url = wp_parse_url( $url );

			$internal_url = $parsed_url['scheme'] . '://' . $parsed_url['host'] . $parsed_url['path'];

			if ( ! empty( $parsed_url['query'] ) ) {
				$query_params = wp_parse_args( $parsed_url['query'] );

				if ( array_key_exists( 'v', $query_params ) ) {
					$internal_url .= '?v=' . $query_params['v'];
				}
			}
		} else {
			$internal_url = 'https://www.youtube.com/watch?v=' . $url;
		}

		$youtube_block = $this->block_generator->get_youtube( $url );
		$this->assertEquals( 'core/embed', $youtube_block['blockName'] );
		$this->assertEquals( $internal_url, $youtube_block['attrs']['url'] );
		$this->assertStringContainsString( $internal_url, $youtube_block['innerHTML'] );
	}
}
