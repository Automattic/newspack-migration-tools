<?php

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use WP_UnitTestCase;

class GutenbergBlockGeneratorAccordionTest extends WP_UnitTestCase {

	/**
	 * Core's editor serialization of a default accordion.
	 *
	 * Copied from Gutenberg test/integration/fixtures/blocks/core__accordion.serialized.html,
	 * and matching the save() functions in WP 7.1.2 wp-includes/js/dist/block-library.js.
	 */
	private const CORE_FIXTURE = <<<'HTML'
<!-- wp:accordion -->
<div role="group" class="wp-block-accordion"><!-- wp:accordion-item -->
<div class="wp-block-accordion-item"><!-- wp:accordion-heading -->
<h3 class="wp-block-accordion-heading has-icon has-icon-right"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">Accordion Title</span><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h3>
<!-- /wp:accordion-heading -->

<!-- wp:accordion-panel -->
<div role="region" class="wp-block-accordion-panel"><!-- wp:paragraph -->
<p>Accordion  Panel Content</p>
<!-- /wp:paragraph --></div>
<!-- /wp:accordion-panel --></div>
<!-- /wp:accordion-item --></div>
<!-- /wp:accordion -->
HTML;

	private GutenbergBlockGenerator $block_generator;

	protected function setUp(): void {
		parent::setUp();
		$this->block_generator = new GutenbergBlockGenerator();
	}

	private function get_paragraph_block(): array {
		return parse_blocks( "<!-- wp:paragraph -->\n<p>Accordion  Panel Content</p>\n<!-- /wp:paragraph -->" )[0];
	}

	public function test_get_accordion_matches_core_serialization() {
		$block = $this->block_generator->get_accordion(
			[
				[
					'title'  => 'Accordion Title',
					'blocks' => [ $this->get_paragraph_block() ],
				],
			]
		);

		$this->assertSame( self::CORE_FIXTURE, serialize_blocks( [ $block ] ) );
	}

	public function test_get_accordion_parses_back_to_same_tree() {
		$items = [
			[
				'title'  => 'First <em>item</em>',
				'blocks' => [ $this->get_paragraph_block() ],
				'open'   => true,
			],
			[
				'title'  => 'Second',
				'blocks' => [ $this->get_paragraph_block(), $this->get_paragraph_block() ],
			],
		];
		$html  = serialize_blocks( [ $this->block_generator->get_accordion( $items ) ] );
		$tree  = parse_blocks( $html );

		$this->assertCount( 1, $tree );
		$this->assertSame( 'core/accordion', $tree[0]['blockName'] );
		$this->assertCount( 2, $tree[0]['innerBlocks'] );
		foreach ( $tree[0]['innerBlocks'] as $i => $item ) {
			$this->assertSame( 'core/accordion-item', $item['blockName'] );
			$this->assertSame( [ 'core/accordion-heading', 'core/accordion-panel' ], array_column( $item['innerBlocks'], 'blockName' ) );
			$this->assertCount( count( $items[ $i ]['blocks'] ), $item['innerBlocks'][1]['innerBlocks'] );
			$this->assertStringContainsString( '<span class="wp-block-accordion-heading__toggle-title">' . $items[ $i ]['title'] . '</span>', $item['innerBlocks'][0]['innerHTML'] );
		}
		$this->assertSame( [ 'openByDefault' => true ], $tree[0]['innerBlocks'][0]['attrs'] );
		$this->assertStringContainsString( 'class="wp-block-accordion-item is-open"', $tree[0]['innerBlocks'][0]['innerHTML'] );
		$this->assertSame( [], $tree[0]['innerBlocks'][1]['attrs'] );
		$this->assertSame( $html, serialize_blocks( $tree ) );
	}

	public function test_get_accordion_non_default_options() {
		$items = [
			[
				'title'  => 'Title',
				'blocks' => [ $this->get_paragraph_block() ],
			],
		];

		$html = serialize_blocks( [ $this->block_generator->get_accordion( $items, 2, true, 'left', true ) ] );
		$this->assertStringStartsWith( '<!-- wp:accordion {"headingLevel":2,"iconPosition":"left","autoclose":true} -->', $html );
		$this->assertStringContainsString( '<!-- wp:accordion-heading {"level":2,"iconPosition":"left"} -->', $html );
		$this->assertStringContainsString( '<h2 class="wp-block-accordion-heading has-icon has-icon-left"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span><span class="wp-block-accordion-heading__toggle-title">Title</span></button></h2>', $html );

		$html = serialize_blocks( [ $this->block_generator->get_accordion( $items, 3, false ) ] );
		$this->assertStringStartsWith( '<!-- wp:accordion {"showIcon":false} -->', $html );
		$this->assertStringContainsString( '<h3 class="wp-block-accordion-heading"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">Title</span></button></h3>', $html );
	}

	public function test_get_accordion_genesis_block() {
		$block = $this->block_generator->get_accordion_genesis_block( 'Title', 'Body', false, true );

		$this->assertSame( 'genesis-blocks/gb-accordion', $block['blockName'] );
		$this->assertSame( [ 'accordionOpen' => true ], $block['attrs'] );
		$this->assertSame( 'core/paragraph', $block['innerBlocks'][0]['blockName'] );
	}
}
