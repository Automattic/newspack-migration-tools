<?php

namespace Newspack\MigrationTools\Tests\Logic;


use DOMDocument;
use DOMXPath;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use WP_UnitTestCase;

class GutenbergBlockGeneratorTest extends WP_UnitTestCase {

	private GutenbergBlockGenerator $block_generator;

	public function __construct() {
		parent::__construct();
		$this->block_generator = new GutenbergBlockGenerator();
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

	}

	public function testGet_block_json_array_from_content() {

	}

	public function testGet_iframe() {

	}

	public function testGet_site_logo() {

	}

	public function testGet_video() {

	}

	public function testGet_vimeo() {

	}

	public function testGet_jetpack_tiled_gallery() {

	}

	public function testGet_list() {

	}

	public function testGet_author_profile() {

	}

	public function testGet_quote() {

	}

	public function testGet_homepage_articles_for_category() {

	}

	public function testGet_facebook() {

	}

	public function testGet_gallery() {

	}

	public function testGet_html() {

	}

	public function testGet_file_pdf() {

	}

	public function testGet_pdf() {

	}

	public function testGet_twitter() {

	}

	public function testGet_columns() {

	}

	public function testGet_column() {

	}

	public function testGet_separator() {

	}

	public function testGet_paragraph() {

	}

	public function testGet_featured_image() {

	}

	public function testGet_jetpack_slideshow() {

	}

	public function testGet_group_constrained() {

	}

	public function testGet_accordion() {

	}

	public function testGet_image() {

	}

	public function testGet_youtube() {

	}
}
