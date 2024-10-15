<?php

namespace Newspack\MigrationTools\Tests\Logic;

use DOMDocument;
use DOMXPath;
use Newspack\MigrationTools\Logic\AttachmentHelper;
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
		$attachment_id          = AttachmentHelper::import_attachment_for_post(
			$this->test_post_1_id,
			$this->dummy_image
		);
		$this->attachment_ids[] = $attachment_id;
		$block                  = $this->block_generator->get_gallery( [ $attachment_id ] );
		$this->assertEquals( 'core/image', $block['innerBlocks'][0]['blockName'] );
	}


	public function test_get_file_pdf() {
		$attachment_id          = AttachmentHelper::import_attachment_for_post(
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
}
