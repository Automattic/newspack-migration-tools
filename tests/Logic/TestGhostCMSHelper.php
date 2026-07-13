<?php

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\MigrationTools\Tests\Logic\TestableGhostCMSHelper;
use Newspack\Guest_Contributor_Role;
use Newspack\MigrationTools\Logic\GhostCMSHelper;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use Newspack\MigrationTools\Tests\AttachmentUnitTestTrait;
use ReflectionClass;
use WP_UnitTestCase;

class TestGhostCMSHelper extends WP_UnitTestCase {

	use AttachmentUnitTestTrait;
	
	/**
	 * Helper to invoke the private get_json_data_from_path method.
	 *
	 * @param GhostCMSHelper $helper The helper instance with JSON set.
	 * @param string         $path   The jq-style path to resolve.
	 * @param object         $json   The JSON object.
	 * @return object|null The resolved data node or null.
	 */
	private function invoke_get_json_data_from_path( GhostCMSHelper $helper, string $path, object $json ): ?object {
		$reflection = new ReflectionClass( $helper );
		$method     = $reflection->getMethod( 'get_json_data_from_path' );
		$method->setAccessible( true );

		return $method->invoke( $helper, $path, $json );
	}

	/**
	 * Test empty path returns the root JSON object.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_empty_path_returns_root(): void {
		$json = json_decode( '{"db": [{"data": {"posts": []}}]}' );

		$helper = new GhostCMSHelper();

		$result = $this->invoke_get_json_data_from_path( $helper, '', $json );

		$this->assertSame( $json, $result );
	}

	/**
	 * Test path with leading dot is stripped and works correctly.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_strips_leading_dot(): void {
		$json = json_decode( '{"db": [{"data": {"posts": []}}]}' );

		$helper = new GhostCMSHelper();

		$result = $this->invoke_get_json_data_from_path( $helper, '.db[0].data', $json );

		$this->assertIsObject( $result );
		$this->assertTrue( property_exists( $result, 'posts' ) );
	}

	/**
	 * Test simple property access.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_simple_property(): void {
		$json = json_decode( '{"settings": {"theme": "dark"}}' );

		$helper = new GhostCMSHelper();

		$result = $this->invoke_get_json_data_from_path( $helper, 'settings', $json );

		$this->assertIsObject( $result );
		$this->assertEquals( 'dark', $result->theme );
	}

	/**
	 * Test nested property access with dots.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_nested_properties(): void {
		$json = json_decode( '{"level1": {"level2": {"level3": {"value": "deep"}}}}' );

		$helper = new GhostCMSHelper();

		$result = $this->invoke_get_json_data_from_path( $helper, 'level1.level2.level3', $json );

		$this->assertIsObject( $result );
		$this->assertEquals( 'deep', $result->value );
	}

	/**
	 * Test array index access with property.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_array_index_with_property(): void {
		$json = json_decode( '{"db": [{"name": "first"}, {"name": "second"}, {"name": "third"}]}' );

		$helper = new GhostCMSHelper();

		// Access first element.
		$result = $this->invoke_get_json_data_from_path( $helper, 'db[0]', $json );
		$this->assertIsObject( $result );
		$this->assertEquals( 'first', $result->name );

		// Access second element.
		$result = $this->invoke_get_json_data_from_path( $helper, 'db[1]', $json );
		$this->assertIsObject( $result );
		$this->assertEquals( 'second', $result->name );

		// Access third element.
		$result = $this->invoke_get_json_data_from_path( $helper, 'db[2]', $json );
		$this->assertIsObject( $result );
		$this->assertEquals( 'third', $result->name );
	}

	/**
	 * Test typical Ghost CMS path: db[0].data
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_ghost_cms_typical_path(): void {
		$json = json_decode(
			'{
			"db": [{
				"meta": {"version": "5.0"},
				"data": {
					"posts": [{"title": "Hello"}],
					"tags": [{"name": "News"}],
					"users": [{"name": "Author"}]
				}
			}]
		}' 
		);

		$helper = new GhostCMSHelper();

		$result = $this->invoke_get_json_data_from_path( $helper, 'db[0].data', $json );

		$this->assertIsObject( $result );
		$this->assertTrue( property_exists( $result, 'posts' ) );
		$this->assertTrue( property_exists( $result, 'tags' ) );
		$this->assertTrue( property_exists( $result, 'users' ) );
	}

	/**
	 * Test deeply nested path with multiple array indices.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_multiple_array_indices(): void {
		$json = json_decode(
			'{
			"databases": [{
				"tables": [{
					"rows": [{"id": 1}, {"id": 2}]
				}]
			}]
		}' 
		);

		$helper = new GhostCMSHelper();

		$result = $this->invoke_get_json_data_from_path( $helper, 'databases[0].tables[0]', $json );

		$this->assertIsObject( $result );
		$this->assertTrue( property_exists( $result, 'rows' ) );
	}

	/**
	 * Test non-existent property returns null.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_nonexistent_property_returns_null(): void {
		$json = json_decode( '{"existing": {"value": 1}}' );

		$helper = new GhostCMSHelper();

		$result = $this->invoke_get_json_data_from_path( $helper, 'nonexistent', $json );

		$this->assertNull( $result );
	}

	/**
	 * Test non-existent nested property returns null.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_nonexistent_nested_property_returns_null(): void {
		$json = json_decode( '{"level1": {"level2": {}}}' );

		$helper = new GhostCMSHelper();

		$result = $this->invoke_get_json_data_from_path( $helper, 'level1.level2.level3', $json );

		$this->assertNull( $result );
	}

	/**
	 * Test array index out of bounds returns null.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_index_out_of_bounds_returns_null(): void {
		$json = json_decode( '{"items": [{"id": 1}, {"id": 2}]}' );

		$helper = new GhostCMSHelper();

		$result = $this->invoke_get_json_data_from_path( $helper, 'items[99]', $json );

		$this->assertNull( $result );
	}

	/**
	 * Test accessing array index on non-array returns null.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_index_on_non_array_returns_null(): void {
		$json = json_decode( '{"notArray": {"key": "value"}}' );

		$helper = new GhostCMSHelper();

		$result = $this->invoke_get_json_data_from_path( $helper, 'notArray[0]', $json );

		$this->assertNull( $result );
	}

	/**
	 * Test accessing property on non-existent intermediate path returns null.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_invalid_intermediate_path_returns_null(): void {
		$json = json_decode( '{"db": [{"data": {}}]}' );

		$helper = new GhostCMSHelper();

		$result = $this->invoke_get_json_data_from_path( $helper, 'db[0].invalid.something', $json );

		$this->assertNull( $result );
	}

	/**
	 * Test path that resolves to non-object returns null.
	 *
	 * The method explicitly returns null if the final result is not an object.
	 *
	 * @return void
	 */
	public function test_get_json_data_from_path_non_object_result_returns_null(): void {
		$json = json_decode( '{"config": {"name": "test"}}' );

		$helper = new GhostCMSHelper();

		// "name" is a string, not an object.
		$result = $this->invoke_get_json_data_from_path( $helper, 'config.name', $json );

		$this->assertNull( $result );
	}

	/**
	 * Test basic video embed replacement.
	 *
	 * @return void
	 */
	public function test_replace_video_embeds_basic_replacement(): void {
		$helper = new GhostCMSHelper();

		$input    = 'Before txt <div class="kg-video-container"><video src="https://example.com/video.mp4"></video></div> After txt';
		$expected = 'Before txt <video src="https://example.com/video.mp4" controls style="width: 100%; height: auto;"></video> After txt';

		$result = $helper->replace_video_embeds( $input, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test multiple video containers are all replaced.
	 *
	 * @return void
	 */
	public function test_replace_video_embeds_multiple_containers(): void {
		$helper = new GhostCMSHelper();

		$input    = 'First <div class="kg-video-container"><video src="https://example.com/video1.mp4"></video></div> Middle <div class="kg-video-container"><video src="https://example.com/video2.mp4"></video></div> Last';
		$expected = 'First <video src="https://example.com/video1.mp4" controls style="width: 100%; height: auto;"></video> Middle <video src="https://example.com/video2.mp4" controls style="width: 100%; height: auto;"></video> Last';

		$result = $helper->replace_video_embeds( $input, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test content without video containers returns unchanged.
	 *
	 * @return void
	 */
	public function test_replace_video_embeds_no_containers_returns_unchanged(): void {
		$helper = new GhostCMSHelper();

		$input = 'Before txt <p>Some paragraph</p> After txt';

		$result = $helper->replace_video_embeds( $input, '123' );

		$this->assertSame( $input, $result );
	}

	/**
	 * Test video container without video element is skipped.
	 *
	 * @return void
	 */
	public function test_replace_video_embeds_container_without_video_element(): void {
		$helper = new GhostCMSHelper();

		$input = 'Before txt <div class="kg-video-container"><p>No video here</p></div> After txt';

		$result = $helper->replace_video_embeds( $input, '123' );

		$this->assertSame( $input, $result );
	}

	/**
	 * Test video element without src attribute is skipped.
	 *
	 * @return void
	 */
	public function test_replace_video_embeds_video_without_src(): void {
		$helper = new GhostCMSHelper();

		$input = 'Before txt <div class="kg-video-container"><video></video></div> After txt';

		$result = $helper->replace_video_embeds( $input, '123' );

		$this->assertSame( $input, $result );
	}

	/**
	 * Test video embed replacement works with both compact and formatted HTML.
	 *
	 * @return void
	 */
	public function test_replace_video_embeds_whitespace_agnostic(): void {
		$helper = new GhostCMSHelper();

		$compact   = 'Text <div class="kg-video-container"><video src="https://example.com/video.mp4"></video></div> More';
		$formatted = 'Text <div class="kg-video-container">
			<video src="https://example.com/video.mp4"></video>
		</div> More';
		$expected  = 'Text <video src="https://example.com/video.mp4" controls style="width: 100%; height: auto;"></video> More';

		$result_compact   = $helper->replace_video_embeds( $compact, '123' );
		$result_formatted = $helper->replace_video_embeds( $formatted, '123' );

		$this->assertSame( $expected, $result_compact );
		$this->assertSame( $expected, $result_formatted );
	}

	/**
	 * Test video replacement preserves surrounding paragraph content.
	 *
	 * @return void
	 */
	public function test_replace_video_embeds_preserves_surrounding_content(): void {
		$helper = new GhostCMSHelper();

		$input = '<p>Before paragraph</p> <div class="kg-video-container"><video src="https://example.com/video.mp4"></video></div> <p>After paragraph</p>';
		// Note: HTML parser normalizes whitespace between block elements - this is expected behavior.
		$expected = '<p>Before paragraph</p><video src="https://example.com/video.mp4" controls style="width: 100%; height: auto;"></video><p>After paragraph</p>';

		$result = $helper->replace_video_embeds( $input, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test basic audio embed replacement.
	 *
	 * @return void
	 */
	public function test_replace_audio_embeds_basic_replacement(): void {
		$helper = new GhostCMSHelper();

		$input    = 'Before txt <div class="kg-audio-card"><audio src="https://example.com/audio.mp3"></audio></div> After txt';
		$expected = 'Before txt <audio src="https://example.com/audio.mp3" controls></audio> After txt';

		$result = $helper->replace_audio_embeds( $input, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test multiple audio cards are all replaced.
	 *
	 * @return void
	 */
	public function test_replace_audio_embeds_multiple_cards(): void {
		$helper = new GhostCMSHelper();

		$input    = 'First <div class="kg-audio-card"><audio src="https://example.com/audio1.mp3"></audio></div> Middle <div class="kg-audio-card"><audio src="https://example.com/audio2.mp3"></audio></div> Last';
		$expected = 'First <audio src="https://example.com/audio1.mp3" controls></audio> Middle <audio src="https://example.com/audio2.mp3" controls></audio> Last';

		$result = $helper->replace_audio_embeds( $input, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test content without audio cards returns unchanged.
	 *
	 * @return void
	 */
	public function test_replace_audio_embeds_no_cards_returns_unchanged(): void {
		$helper = new GhostCMSHelper();

		$input = 'Before txt <p>Some paragraph</p> After txt';

		$result = $helper->replace_audio_embeds( $input, '123' );

		$this->assertSame( $input, $result );
	}

	/**
	 * Test audio card without audio element is skipped.
	 *
	 * @return void
	 */
	public function test_replace_audio_embeds_card_without_audio_element(): void {
		$helper = new GhostCMSHelper();

		$input = 'Before txt <div class="kg-audio-card"><p>No audio here</p></div> After txt';

		$result = $helper->replace_audio_embeds( $input, '123' );

		$this->assertSame( $input, $result );
	}

	/**
	 * Test audio element without src attribute is skipped.
	 *
	 * @return void
	 */
	public function test_replace_audio_embeds_audio_without_src(): void {
		$helper = new GhostCMSHelper();

		$input = 'Before txt <div class="kg-audio-card"><audio></audio></div> After txt';

		$result = $helper->replace_audio_embeds( $input, '123' );

		$this->assertSame( $input, $result );
	}

	/**
	 * Test audio replacement preserves surrounding content.
	 *
	 * @return void
	 */
	public function test_replace_audio_embeds_preserves_surrounding_content(): void {
		$helper = new GhostCMSHelper();

		$input = '<p>Before paragraph</p> <div class="kg-audio-card"><audio src="https://example.com/audio.mp3"></audio></div> <p>After paragraph</p>';
		// Note: HTML parser normalizes whitespace between block elements - this is expected behavior.
		$expected = '<p>Before paragraph</p><audio src="https://example.com/audio.mp3" controls></audio><p>After paragraph</p>';

		$result = $helper->replace_audio_embeds( $input, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test mixed video and audio embeds in same content.
	 *
	 * @return void
	 */
	public function test_replace_embeds_mixed_video_and_audio(): void {
		$helper = new GhostCMSHelper();

		$input    = 'Start <div class="kg-video-container"><video src="https://example.com/video.mp4"></video></div> Middle <div class="kg-audio-card"><audio src="https://example.com/audio.mp3"></audio></div> End';
		$expected = 'Start <video src="https://example.com/video.mp4" controls style="width: 100%; height: auto;"></video> Middle <audio src="https://example.com/audio.mp3" controls></audio> End';

		// Apply both replacements as they would be in the import process.
		$result = $helper->replace_video_embeds( $input, '123' );
		$result = $helper->replace_audio_embeds( $result, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test video/audio embeds nested in other markup.
	 *
	 * @return void
	 */
	public function test_replace_embeds_nested_in_other_markup(): void {
		$helper = new GhostCMSHelper();

		$input    = '<div><p>Text</p><div class="kg-video-container"><video src="https://example.com/video.mp4"></video></div><ul><li><div class="kg-audio-card"><audio src="https://example.com/audio.mp3"></audio></div></li></ul></div>';
		$expected = '<div><p>Text</p><video src="https://example.com/video.mp4" controls style="width: 100%; height: auto;"></video><ul><li><audio src="https://example.com/audio.mp3" controls></audio></li></ul></div>';

		$result = $helper->replace_video_embeds( $input, '123' );
		$result = $helper->replace_audio_embeds( $result, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test basic blockquote replacement.
	 *
	 * @return void
	 */
	public function test_replace_blockquotes_basic_replacement(): void {
		$helper          = new GhostCMSHelper();
		$block_generator = new GutenbergBlockGenerator();

		$input    = 'Before txt <blockquote class="kg-blockquote-alt">"Some quotation"</blockquote> After txt';
		$expected = 'Before txt ' . serialize_blocks( [ $block_generator->get_quote( '"Some quotation"' ) ] ) . ' After txt';

		$result = $helper->replace_blockquotes( $input, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test blockquote with multiline content collapses whitespace.
	 *
	 * @return void
	 */
	public function test_replace_blockquotes_multiline_content(): void {
		$helper          = new GhostCMSHelper();
		$block_generator = new GutenbergBlockGenerator();

		$input    = 'Before txt <blockquote class="kg-blockquote-alt">"Some
    quotation"</blockquote> After txt';
		$expected = 'Before txt ' . serialize_blocks( [ $block_generator->get_quote( '"Some quotation"' ) ] ) . ' After txt';

		$result = $helper->replace_blockquotes( $input, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test multiple blockquotes are all replaced.
	 *
	 * @return void
	 */
	public function test_replace_blockquotes_multiple(): void {
		$helper          = new GhostCMSHelper();
		$block_generator = new GutenbergBlockGenerator();

		$input     = 'First <blockquote class="kg-blockquote-alt">"Quote one"</blockquote> Middle <blockquote class="kg-blockquote-alt">"Quote two"</blockquote> Last';
		$quote_one = serialize_blocks( [ $block_generator->get_quote( '"Quote one"' ) ] );
		$quote_two = serialize_blocks( [ $block_generator->get_quote( '"Quote two"' ) ] );
		$expected  = 'First ' . $quote_one . ' Middle ' . $quote_two . ' Last';

		$result = $helper->replace_blockquotes( $input, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test content without kg-blockquote-alt returns unchanged.
	 *
	 * @return void
	 */
	public function test_replace_blockquotes_no_kg_blockquotes_returns_unchanged(): void {
		$helper = new GhostCMSHelper();

		$input = 'Before txt <blockquote>Regular quote</blockquote> After txt';

		$result = $helper->replace_blockquotes( $input, '123' );

		$this->assertSame( $input, $result );
	}

	/**
	 * Test blockquote with empty content is skipped.
	 *
	 * @return void
	 */
	public function test_replace_blockquotes_empty_content_skipped(): void {
		$helper = new GhostCMSHelper();

		$input = 'Before txt <blockquote class="kg-blockquote-alt">   </blockquote> After txt';

		$result = $helper->replace_blockquotes( $input, '123' );

		$this->assertSame( $input, $result );
	}

	/**
	 * Test blockquote replacement preserves surrounding content.
	 *
	 * @return void
	 */
	public function test_replace_blockquotes_preserves_surrounding_content(): void {
		$helper          = new GhostCMSHelper();
		$block_generator = new GutenbergBlockGenerator();

		$input = '<p>Before paragraph</p> <blockquote class="kg-blockquote-alt">"A quote"</blockquote> <p>After paragraph</p>';
		// Note: HTML parser normalizes whitespace between block elements - this is expected behavior (same as video/audio tests).
		$expected = '<p>Before paragraph</p>' . serialize_blocks( [ $block_generator->get_quote( '"A quote"' ) ] ) . '<p>After paragraph</p>';

		$result = $helper->replace_blockquotes( $input, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test basic callout card with emoji and text, no color class.
	 *
	 * @return void
	 */
	public function test_replace_callout_cards_basic_with_emoji_and_text(): void {
		$helper          = new GhostCMSHelper();
		$block_generator = new GutenbergBlockGenerator();

		$input    = 'Before txt <div class="kg-card kg-callout-card"><div class="kg-callout-emoji">💡</div><div class="kg-callout-text">Important note</div></div> After txt';
		$expected = 'Before txt ' . serialize_blocks( [ $block_generator->get_paragraph( '💡 Important note' ) ] ) . ' After txt';

		$result = $helper->replace_callout_cards( $input, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test callout card with color class produces background-colored paragraph.
	 *
	 * @return void
	 */
	public function test_replace_callout_cards_with_color_class(): void {
		$helper          = new GhostCMSHelper();
		$block_generator = new GutenbergBlockGenerator();

		$input    = 'Before txt <div class="kg-card kg-callout-card kg-callout-card-blue"><div class="kg-callout-emoji">💡</div><div class="kg-callout-text">Blue note</div></div> After txt';
		$expected = 'Before txt ' . serialize_blocks(
			[
				$block_generator->get_paragraph(
					'💡 Blue note',
					'',
					'',
					'',
					[ 'has-background' ],
					[ 'style' => [ 'color' => [ 'background' => '#E3F2FD' ] ] ],
					[ 'background-color' => '#E3F2FD' ]
				),
			] 
		) . ' After txt';

		$result = $helper->replace_callout_cards( $input, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test different color classes produce their respective background colors.
	 *
	 * @return void
	 */
	public function test_replace_callout_cards_different_colors(): void {
		$helper          = new GhostCMSHelper();
		$block_generator = new GutenbergBlockGenerator();

		// Yellow callout.
		$input_yellow    = '<div class="kg-card kg-callout-card kg-callout-card-yellow"><div class="kg-callout-emoji">⚠️</div><div class="kg-callout-text">Warning</div></div>';
		$expected_yellow = serialize_blocks(
			[
				$block_generator->get_paragraph(
					'⚠️ Warning',
					'',
					'',
					'',
					[ 'has-background' ],
					[ 'style' => [ 'color' => [ 'background' => '#FFF9E6' ] ] ],
					[ 'background-color' => '#FFF9E6' ]
				),
			] 
		);

		$result_yellow = $helper->replace_callout_cards( $input_yellow, '123' );
		$this->assertSame( $expected_yellow, $result_yellow );

		// White callout.
		$input_white    = '<div class="kg-card kg-callout-card kg-callout-card-white"><div class="kg-callout-emoji">📝</div><div class="kg-callout-text">Note</div></div>';
		$expected_white = serialize_blocks(
			[
				$block_generator->get_paragraph(
					'📝 Note',
					'',
					'',
					'',
					[ 'has-background' ],
					[ 'style' => [ 'color' => [ 'background' => '#FFFFFF' ] ] ],
					[ 'background-color' => '#FFFFFF' ]
				),
			] 
		);

		$result_white = $helper->replace_callout_cards( $input_white, '123' );
		$this->assertSame( $expected_white, $result_white );
	}

	/**
	 * Test multiple callout cards are all replaced.
	 *
	 * @return void
	 */
	public function test_replace_callout_cards_multiple(): void {
		$helper          = new GhostCMSHelper();
		$block_generator = new GutenbergBlockGenerator();

		$input = 'First <div class="kg-card kg-callout-card"><div class="kg-callout-emoji">💡</div><div class="kg-callout-text">Note one</div></div> Middle <div class="kg-card kg-callout-card kg-callout-card-blue"><div class="kg-callout-emoji">🔵</div><div class="kg-callout-text">Note two</div></div> Last';

		$block_one = serialize_blocks( [ $block_generator->get_paragraph( '💡 Note one' ) ] );
		$block_two = serialize_blocks(
			[
				$block_generator->get_paragraph(
					'🔵 Note two',
					'',
					'',
					'',
					[ 'has-background' ],
					[ 'style' => [ 'color' => [ 'background' => '#E3F2FD' ] ] ],
					[ 'background-color' => '#E3F2FD' ]
				),
			] 
		);
		$expected  = 'First ' . $block_one . ' Middle ' . $block_two . ' Last';

		$result = $helper->replace_callout_cards( $input, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test content without callout cards returns unchanged.
	 *
	 * @return void
	 */
	public function test_replace_callout_cards_no_callouts_returns_unchanged(): void {
		$helper = new GhostCMSHelper();

		$input = 'Before txt <p>Some paragraph</p> After txt';

		$result = $helper->replace_callout_cards( $input, '123' );

		$this->assertSame( $input, $result );
	}

	/**
	 * Test callout card with empty emoji and empty text is skipped.
	 *
	 * @return void
	 */
	public function test_replace_callout_cards_empty_content_skipped(): void {
		$helper = new GhostCMSHelper();

		$input = 'Before txt <div class="kg-card kg-callout-card"><div class="kg-callout-emoji"></div><div class="kg-callout-text"></div></div> After txt';

		$result = $helper->replace_callout_cards( $input, '123' );

		$this->assertSame( $input, $result );
	}

	/**
	 * Test callout with empty emoji div produces no leading space in output.
	 *
	 * @return void
	 */
	public function test_replace_callout_cards_empty_emoji_no_leading_space(): void {
		$helper          = new GhostCMSHelper();
		$block_generator = new GutenbergBlockGenerator();

		$input = 'Before txt <div class="kg-card kg-callout-card"><div class="kg-callout-emoji"></div><div class="kg-callout-text">Just text</div></div> After txt';
		// When emoji is empty, text should not have a leading space.
		$expected = 'Before txt ' . serialize_blocks( [ $block_generator->get_paragraph( 'Just text' ) ] ) . ' After txt';

		$result = $helper->replace_callout_cards( $input, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test callout with text containing inline HTML (bold, links) preserves it.
	 *
	 * @return void
	 */
	public function test_replace_callout_cards_text_with_inline_html(): void {
		$helper          = new GhostCMSHelper();
		$block_generator = new GutenbergBlockGenerator();

		$input    = 'Before txt <div class="kg-card kg-callout-card"><div class="kg-callout-emoji">⚠️</div><div class="kg-callout-text">This is <strong>important</strong> and <a href="https://example.com">linked</a></div></div> After txt';
		$expected = 'Before txt ' . serialize_blocks( [ $block_generator->get_paragraph( '⚠️ This is <strong>important</strong> and <a href="https://example.com">linked</a>' ) ] ) . ' After txt';

		$result = $helper->replace_callout_cards( $input, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test callout replacement preserves surrounding content.
	 *
	 * @return void
	 */
	public function test_replace_callout_cards_preserves_surrounding_content(): void {
		$helper          = new GhostCMSHelper();
		$block_generator = new GutenbergBlockGenerator();

		$input = '<p>Before paragraph</p> <div class="kg-card kg-callout-card"><div class="kg-callout-emoji">💡</div><div class="kg-callout-text">A note</div></div> <p>After paragraph</p>';
		// Note: HTML parser normalizes whitespace between block elements - this is expected behavior (same as video/audio/blockquote tests).
		$expected = '<p>Before paragraph</p>' . serialize_blocks( [ $block_generator->get_paragraph( '💡 A note' ) ] ) . '<p>After paragraph</p>';

		$result = $helper->replace_callout_cards( $input, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test content without galleries returns unchanged.
	 *
	 * @return void
	 */
	public function test_replace_galleries_no_galleries_returns_unchanged(): void {
		$helper = new GhostCMSHelper();

		$input = 'Before txt <p>Some paragraph</p> After txt';

		$result = $helper->replace_galleries( $input, '123' );

		$this->assertSame( $input, $result );
	}

	/**
	 * Test gallery with no images is skipped (returns unchanged).
	 *
	 * @return void
	 */
	public function test_replace_galleries_empty_gallery_skipped(): void {
		$helper = new GhostCMSHelper();

		$input = 'Before txt <figure class="kg-card kg-gallery-card"><div class="kg-gallery-container"><div class="kg-gallery-row"></div></div></figure> After txt';

		$result = $helper->replace_galleries( $input, '123' );

		$this->assertSame( $input, $result );
	}

	/**
	 * Test gallery images without src attribute are ignored.
	 *
	 * @return void
	 */
	public function test_replace_galleries_images_without_src_skipped(): void {
		$helper = new GhostCMSHelper();

		$input = 'Before txt <figure class="kg-card kg-gallery-card"><div class="kg-gallery-container"><div class="kg-gallery-row"><div class="kg-gallery-image"><img></div></div></div></figure> After txt';

		$result = $helper->replace_galleries( $input, '123' );

		// Gallery with images that have no src should be skipped.
		$this->assertSame( $input, $result );
	}

	/**
	 * Test gallery where no attachments resolve returns unchanged.
	 *
	 * @return void
	 */
	public function test_replace_galleries_unresolved_attachments_skipped(): void {
		$helper = new TestableGhostCMSHelper();
		// Empty map means no URLs will resolve to attachment IDs.
		$helper->set_attachment_map( [] );

		$input = 'Before txt <figure class="kg-card kg-gallery-card"><div class="kg-gallery-container"><div class="kg-gallery-row"><div class="kg-gallery-image"><img src="https://example.com/image1.jpg"></div></div></div></figure> After txt';

		$result = $helper->replace_galleries( $input, '123' );

		// Gallery with no resolved attachments should be skipped.
		$this->assertSame( $input, $result );
	}

	/**
	 * Test basic gallery replacement with mock attachments.
	 *
	 * @return void
	 */
	public function test_replace_galleries_basic_replacement(): void {
		// Create real attachment using the factory.
		$attachment_id          = $this->factory()->attachment->create_upload_object( $this->dummy_image );
		$this->attachment_ids[] = $attachment_id;

		$helper = new TestableGhostCMSHelper();
		$helper->set_attachment_map(
			[
				'https://example.com/image1.jpg' => $attachment_id,
			] 
		);

		$block_generator = new GutenbergBlockGenerator();

		$input    = '<figure class="kg-card kg-gallery-card"><div class="kg-gallery-container"><div class="kg-gallery-row"><div class="kg-gallery-image"><img src="https://example.com/image1.jpg"></div></div></div></figure>';
		$expected = serialize_blocks( [ $block_generator->get_jetpack_tiled_gallery( [ $attachment_id ], 'media' ) ] );

		$result = $helper->replace_galleries( $input, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test gallery with caption appends centered italic paragraph.
	 *
	 * @return void
	 */
	public function test_replace_galleries_with_caption(): void {
		// Create real attachment using the factory.
		$attachment_id          = $this->factory()->attachment->create_upload_object( $this->dummy_image );
		$this->attachment_ids[] = $attachment_id;

		$helper = new TestableGhostCMSHelper();
		$helper->set_attachment_map(
			[
				'https://example.com/image1.jpg' => $attachment_id,
			] 
		);

		$block_generator = new GutenbergBlockGenerator();

		$input = '<figure class="kg-card kg-gallery-card kg-card-hascaption"><div class="kg-gallery-container"><div class="kg-gallery-row"><div class="kg-gallery-image"><img src="https://example.com/image1.jpg"></div></div></div><figcaption>Photos by John Doe</figcaption></figure>';

		// Build expected output: gallery block + caption paragraph.
		$gallery_block = $block_generator->get_jetpack_tiled_gallery( [ $attachment_id ], 'media' );
		// Caption block is built manually in the implementation to avoid className attr coupling.
		$caption_block = [
			'blockName'    => 'core/paragraph',
			'attrs'        => [ 'align' => 'center' ],
			'innerBlocks'  => [],
			'innerHTML'    => '<p class="has-text-align-center"><em>Photos by John Doe</em></p>',
			'innerContent' => [ '<p class="has-text-align-center"><em>Photos by John Doe</em></p>' ],
		];
		$expected      = serialize_blocks( [ $gallery_block ] ) . "\n" . serialize_blocks( [ $caption_block ] );

		$result = $helper->replace_galleries( $input, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test multiple galleries in content are all processed.
	 *
	 * @return void
	 */
	public function test_replace_galleries_multiple_galleries(): void {
		// Create real attachments using the factory.
		$attachment_id_1        = $this->factory()->attachment->create_upload_object( $this->dummy_image );
		$this->attachment_ids[] = $attachment_id_1;
		
		$attachment_id_2        = $this->factory()->attachment->create_upload_object( $this->dummy_image );
		$this->attachment_ids[] = $attachment_id_2;

		$helper = new TestableGhostCMSHelper();
		$helper->set_attachment_map(
			[
				'https://example.com/image1.jpg' => $attachment_id_1,
				'https://example.com/image2.jpg' => $attachment_id_2,
			] 
		);

		$block_generator = new GutenbergBlockGenerator();

		$input = 'First <figure class="kg-card kg-gallery-card"><div class="kg-gallery-container"><div class="kg-gallery-row"><div class="kg-gallery-image"><img src="https://example.com/image1.jpg"></div></div></div></figure> Middle <figure class="kg-card kg-gallery-card"><div class="kg-gallery-container"><div class="kg-gallery-row"><div class="kg-gallery-image"><img src="https://example.com/image2.jpg"></div></div></div></figure> Last';

		// Build expected output.
		$gallery_block_1 = serialize_blocks( [ $block_generator->get_jetpack_tiled_gallery( [ $attachment_id_1 ], 'media' ) ] );
		$gallery_block_2 = serialize_blocks( [ $block_generator->get_jetpack_tiled_gallery( [ $attachment_id_2 ], 'media' ) ] );
		// Note: HTML parser normalizes whitespace between block elements.
		$expected = 'First ' . $gallery_block_1 . ' Middle ' . $gallery_block_2 . ' Last';

		$result = $helper->replace_galleries( $input, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test gallery replacement preserves surrounding content.
	 *
	 * @return void
	 */
	public function test_replace_galleries_preserves_surrounding_content(): void {
		// Create real attachment using the factory.
		$attachment_id          = $this->factory()->attachment->create_upload_object( $this->dummy_image );
		$this->attachment_ids[] = $attachment_id;

		$helper = new TestableGhostCMSHelper();
		$helper->set_attachment_map(
			[
				'https://example.com/image1.jpg' => $attachment_id,
			] 
		);

		$block_generator = new GutenbergBlockGenerator();

		$input = '<p>Before paragraph</p> <figure class="kg-card kg-gallery-card"><div class="kg-gallery-container"><div class="kg-gallery-row"><div class="kg-gallery-image"><img src="https://example.com/image1.jpg"></div></div></div></figure> <p>After paragraph</p>';

		// Note: HTML parser normalizes whitespace between block elements (same as video/audio/blockquote tests).
		$expected = '<p>Before paragraph</p>' . serialize_blocks( [ $block_generator->get_jetpack_tiled_gallery( [ $attachment_id ], 'media' ) ] ) . '<p>After paragraph</p>';

		$result = $helper->replace_galleries( $input, '123' );

		$this->assertSame( $expected, $result );
	}

	/**
	 * Test that GhostCMS Helper will import from JSON file.
	 *
	 * @return void
	 */
	public function test_ghostcms_import(): void {

		// Run test.
		$test_ghostcms_helper = new GhostCMSHelper();
		$test_ghostcms_helper->ghostcms_import( 
			[], 
			[
				'json-file'       => 'tests/fixtures/ghostcms.json',
				'ghost-url'       => 'https://example.com/',
				'default-user-id' => 1,
			],
			''
		);

		// Posts.
		$posts = get_posts(
			[
				'title'       => 'The Title',
				'numberposts' => 1,
			]
		);
		$this->assertIsArray( $posts );
		$this->assertCount( 1, $posts );
		$this->assertEquals( 'the-title', $posts[0]->post_name );

		$user = get_user_by( 'login', 'some-user' );
		$this->assertInstanceOf( \WP_User::class, $user );
		
		// Guest Contributor created with correct role.
		$this->assertContains( Guest_Contributor_Role::CONTRIBUTOR_NO_EDIT_ROLE_NAME, $user->roles );

		// User data imported correctly.
		$this->assertEquals( 'Test author biography for unit tests.', $user->description );
		$this->assertEquals( 'https://example.com', $user->user_url );

		// Social links imported as user meta (twitter as handle, others as full URLs, as defined in Newspack theme, `function newspack_author_get_social_links()`).
		$this->assertEquals( 'someuser', get_user_meta( $user->ID, 'twitter', true ) );
		$this->assertEquals( 'https://instagram.com/someuser_insta', get_user_meta( $user->ID, 'instagram', true ) );
		$this->assertEquals( 'https://linkedin.com/in/someuser-linkedin', get_user_meta( $user->ID, 'linkedin', true ) );
		$this->assertEquals( 'https://bsky.app/profile/someuser.bsky.social', get_user_meta( $user->ID, 'bluesky', true ) );

		// Categories.
		$category = get_term_by( 'name', 'News', 'category' );
		$this->assertIsObject( $category );
		$this->assertEquals( 'news', $category->slug );
	}

	/**
	 * Test that GhostCMS Helper will import from JSON file and rewrite author urls.
	 *
	 * @return void
	 */
	public function test_ghostcms_import_and_rewrite_author_urls(): void {

		// Run test.
		$test_ghostcms_helper = new GhostCMSHelper();
		$test_ghostcms_helper->ghostcms_import( 
			[], 
			[
				'json-file'       => 'tests/fixtures/ghostcms.json',
				'ghost-url'       => 'https://example.com/',
				'default-user-id' => 1,
			],
			''
		);

		// Posts.
		$posts = get_posts(
			[
				'title'       => 'Author Slug Test',
				'numberposts' => 1,
			]
		);
		$this->assertIsArray( $posts );
		$this->assertCount( 1, $posts );
		$this->assertEquals( 'author-slug-test', $posts[0]->post_name );

		
		// Author url fixed.
		$this->assertStringContainsString(
			'/author/user-with-really-long-name-over-user_nicename-50-c">Click to see all my posts',
			$posts[0]->post_content
		);
	}

	/**
	 * Test that check_imported_posts_for_custom_html_content writes output to a specified path
	 * and does not leave stray files in the repository working directory.
	 *
	 * @return void
	 */
	public function test_check_imported_posts_for_custom_html_content_uses_specified_output_path(): void {
		$output_file = sys_get_temp_dir() . '/test_ghost_kg_elements_' . uniqid() . '.jsonl';

		// Ensure file does not exist before the test.
		if ( file_exists( $output_file ) ) {
			unlink( $output_file );
		}

		$helper = new GhostCMSHelper();
		$helper->check_imported_posts_for_custom_html_content( 'test-check-kg', $output_file );

		// The output file must have been created at the specified path.
		$this->assertFileExists( $output_file );

		// The repository working directory must NOT contain a stray ghost_kg_elements.jsonl file.
		$this->assertFileDoesNotExist( 'ghost_kg_elements.jsonl' );

		// Cleanup.
		if ( file_exists( $output_file ) ) {
			unlink( $output_file );
		}
	}

	/**
	 * Test that check_imported_posts_for_custom_html_content defaults to the system temp directory.
	 *
	 * @return void
	 */
	public function test_check_imported_posts_for_custom_html_content_defaults_to_temp_dir(): void {
		$default_output = sys_get_temp_dir() . '/ghost_kg_elements.jsonl';

		// Remove the file if it already exists so we get a clean test.
		if ( file_exists( $default_output ) ) {
			unlink( $default_output );
		}

		$helper = new GhostCMSHelper();
		$helper->check_imported_posts_for_custom_html_content( 'test-check-kg-default' );

		// The default output file must be written to the temp directory, not the CWD.
		$this->assertFileExists( $default_output );
		$this->assertFileDoesNotExist( 'ghost_kg_elements.jsonl' );

		// Cleanup.
		if ( file_exists( $default_output ) ) {
			unlink( $default_output );
		}
	}
}
