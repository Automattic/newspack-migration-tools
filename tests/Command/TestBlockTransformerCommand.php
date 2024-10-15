<?php

namespace Newspack\MigrationTools\Tests\Command;

use Newspack\MigrationTools\Command\BlockTransformerCommand;
use WP_UnitTestCase;

/**
 * Class TestBlockTransformerCommand
 *
 * @package newspack-migration-tools
 */
class TestBlockTransformerCommand extends WP_UnitTestCase {

	private $tapir_string    = 'The not so quick tapir jumped over the lazy capybara.';
	private $block_paragraph = '';

	public function setUp(): void {
		parent::setUp();
		add_filter( 'newspack_migration_tools_log_file_logger_disable', '__return_true' );
		add_filter( 'newspack_migration_tools_log_clilog_disable', '__return_true' );

		$this->block_paragraph = "<!-- wp:paragraph  --><p>{$this->tapir_string}</p><!-- /wp:paragraph -->";
	}

	/**
	 * Test the nudge command. All it does is add a newline to the beginning of the post content, so this one is simple.
	 */
	public function test_nudge(): void {

		$post_id = self::factory()->post->create(
			[
				'post_content' => $this->block_paragraph,
			]
		);

		BlockTransformerCommand::cmd_blocks_nudge( [ $post_id ], [] );
		clean_post_cache( $post_id );
		$this->assertStringStartsWith( PHP_EOL, get_post( $post_id )->post_content );
	}

	/**
	 * Test encode and decode on a simple post with only a paragraph block.
	 */
	public function test_encode_and_decode(): void {

		$post_id = self::factory()->post->create(
			[
				'post_content' => $this->block_paragraph,
			]
		);

		BlockTransformerCommand::cmd_blocks_encode( [ $post_id ], [] );
		clean_post_cache( $post_id );
		$encoded_content = get_post( $post_id )->post_content;
		$this->assertStringContainsString( '[BLOCK-TRANSFORMER:', $encoded_content );
		$this->assertStringStartsNotWith( '<!-- wp:paragraph  -->', $encoded_content );

		BlockTransformerCommand::cmd_blocks_decode( [ $post_id ], [] );
		$decoded_content = get_post( $post_id )->post_content;
		clean_post_cache( $post_id );
		$this->assertStringStartsNotWith( '[BLOCK-TRANSFORMER:', $decoded_content );
		$blocks = parse_blocks( $decoded_content );
		$this->assertEquals( 'core/paragraph', $blocks[0]['blockName'] );
		$this->assertEquals( "<p>{$this->tapir_string}</p>", $blocks[0]['innerHTML'] );
	}
}
