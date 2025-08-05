<?php
/**
 * Tests for the Bylines class.
 */

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\MigrationTools\Logic\Bylines;
use WP_UnitTestCase;

/**
 * Test the Bylines class.
 */
class BylinesTest extends WP_UnitTestCase {
	/**
	 * The Bylines instance.
	 *
	 * @var Bylines
	 */
	private $bylines;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();
		$this->bylines = new Bylines();
	}

	/**
	 * Test parse_byline() without separators.
	 * 
	 * @dataProvider data_byline_no_separators
	 */
	public function test_byline_no_separators( string $byline, array $separators, array $expected ) {
		$result = $this->bylines->parse_byline( $byline, $separators );
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test parse_byline() using a single separator.
	 * 
	 * @dataProvider data_byline_single_separator
	 */
	public function test_byline_single_separator( string $byline, array $separators, array $expected ) {
		$result = $this->bylines->parse_byline( $byline, $separators );
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test parse_byline() correct order of exploding multiple separators.
	 * 
	 * @dataProvider data_byline_order_of_exploding_multiple_separators
	 */
	public function test_byline_order_of_exploding_multiple_separators( string $byline, array $separators, array $expected ) {
		$result = $this->bylines->parse_byline( $byline, $separators );
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test parse_byline() using manual substitutions.
	 * 
	 * @dataProvider data_byline_manual_substitutions
	 */
	public function test_byline_manual_substitutions( string $byline, array $separators, array $manual_substitutions, array $expected ) {
		$result = $this->bylines->parse_byline( $byline, $separators, $manual_substitutions );
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test parse_byline() removing prefixes and suffixes.
	 * 
	 * @dataProvider data_byline_remove_prefixes_and_suffixes
	 */
	public function test_byline_remove_prefixes_and_suffixes( string $byline, array $remove_prefixes, array $remove_suffixes, array $expected ) {
		$result = $this->bylines->parse_byline( $byline, [], [], $remove_prefixes, $remove_suffixes );
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test parse_byline() should not return empty author name after exploding.
	 * 
	 * @dataProvider data_byline_should_not_return_empty_author_name_after_exploding
	 */
	public function test_byline_should_not_return_empty_author_name_after_exploding( string $byline, array $separators, array $expected ) {
		$result = $this->bylines->parse_byline( $byline, $separators );
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Data provider for test_byline_no_separators.
	 */
	public function data_byline_no_separators() {
		return [
			// No separators provided, nothing changes.
			[
				// Byline.
				'John Doe',
				// Separators.
				[],
				// Expected.
				[ 'John Doe' ],
			],
			[
				// Byline.
				'John Doe, Jane Doe',
				// Separators.
				[],
				// Expected.
				[ 'John Doe, Jane Doe' ],
			],
		];
	}

	/**
	 * Data provider for test_byline_single_separator.
	 */
	public function data_byline_single_separator() {
		return [
			// Single separator provided but not used.
			[
				// Byline.
				'John Doe',
				// Separators.
				[ ',' ],
				// Expected.
				[ 'John Doe' ],
			],
			[
				// Byline.
				'John Doe',
				// Separators.
				[ ' and ' ],
				// Expected.
				[ 'John Doe' ],
			],
			[
				// Byline.
				'John Doe',
				// Separators.
				[ '&' ],
				// Expected.
				[ 'John Doe' ],
			],
			// Different single separators.
			[
				// Byline.
				'John Doe, Jane Doe',
				// Separators.
				[ ',' ],
				// Expected.
				[ 'John Doe', 'Jane Doe' ],
			],
			[
				// Byline.
				'John Doe and Jane Doe',
				// Separators.
				[ ' and ' ],
				// Expected.
				[ 'John Doe', 'Jane Doe' ],
			],
			[
				// Byline.
				'John Doe & Jane Doe',
				// Separators.
				[ '&' ],
				// Expected.
				[ 'John Doe', 'Jane Doe' ],
			],
			// Irrelevant separator will not explode anything.
			[
				// Byline.
				'John Doe & Jane Doe',
				// Separators.
				[ ' and ' ],
				// Expected.
				[ 'John Doe & Jane Doe' ],
			],
		];
	}

	/**
	 * Data provider for test_byline_order_of_exploding_multiple_separators.
	 */
	public function data_byline_order_of_exploding_multiple_separators() {
		return [
			[
				// Byline.
				'John Doe, Jane Doe, and Jim Doe',
				// Separators.
				[ ', and ', ',' ],
				// Expected.
				[ 'John Doe', 'Jane Doe', 'Jim Doe' ],
			],
			[
				// Byline.
				'John Doe, Jane Doe, and Jim Doe',
				// Separators.
				[ ',', ', and ' ],
				// Expected.
				[ 'John Doe', 'Jane Doe', 'and Jim Doe' ],
			],
		];
	}

	/**
	 * Data provider for test_byline_manual_substitutions.
	 */
	public function data_byline_manual_substitutions() {
		return [
			[
				// Byline.
				'School of Journalism and Mass Communication',
				// Separators.
				[ ' and ' ],
				// Manual substitutions.
				[
					'School of Journalism and Mass Communication' => [
						'School of Journalism and Mass Communication',
					],
				],
				// Expected.
				[ 'School of Journalism and Mass Communication' ],
			],
			// Manual substitution is applied before exploding, this prevents returning [ 'John Doe', 'School of Journalism', 'Mass Communication' ] which would be wrong.
			[
				// Byline.
				'John Doe and School of Journalism and Mass Communication',
				// Separators.
				[ ' and ' ],
				// Manual substitutions.
				[
					'John Doe and School of Journalism and Mass Communication' => [
						'John Doe',
						'School of Journalism and Mass Communication',
					],
				],
				// Expected.
				[ 'John Doe', 'School of Journalism and Mass Communication' ],
			],
			
		];
	}

	/**
	 * Data provider for test_byline_remove_prefixes_and_suffixes.
	 */
	public function data_byline_remove_prefixes_and_suffixes() {
		return [
			[
				// Byline.
				'By John Doe | copyright',
				// Remove prefixes, case-insensitive.
				[ 'by ', 'Author:' ],
				// Remove suffixes.
				[ '| copyright' ],
				// Expected.
				[ 'John Doe' ],
			],
			[
				// Byline.
				'Author: John Doe',
				// Remove prefixes, case-insensitive.
				[ 'by ', 'author:' ],
				// Remove suffixes.
				[ '| copyright' ],
				// Expected.
				[ 'John Doe' ],
			],
		];
	}

	/**
	 * Data provider for test_byline_should_not_return_empty_author_name_after_exploding.
	 */
	public function data_byline_should_not_return_empty_author_name_after_exploding() {
		return [
			[
				// Byline.
				'John Doe,',
				// Separators.
				[ ',' ],
				// Expected.
				[ 'John Doe' ],
			],
			[
				// Byline.
				'John Doe, Jane Doe,',
				// Separators.
				[ ',' ],
				// Expected.
				[ 'John Doe', 'Jane Doe' ],
			],
		];
	}
}
