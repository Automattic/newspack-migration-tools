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
	 * @dataProvider data_byline_explode_by_single_separator
	 */
	public function test_byline_explode_by_single_separator( string $byline, array $separators, array $expected ) {
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
	 * Data provider for test_byline_explode_by_single_separator.
	 */
	public function data_byline_no_separators() {
		return [
			// No separators provided, nothing changes.
			[ 'John Doe', [], [ 'John Doe' ] ],
			[ 'John Doe, Jane Doe', [], [ 'John Doe, Jane Doe' ] ],
		];
	}

	/**
	 * Data provider for test_byline_explode_by_single_separator.
	 */
	public function data_byline_explode_by_single_separator() {
		return [
			// Single separator provided but not used.
			[ 'John Doe', [ ',' ], [ 'John Doe' ] ],
			[ 'John Doe', [ ' and ' ], [ 'John Doe' ] ],
			[ 'John Doe', [ '&' ], [ 'John Doe' ] ],
			// Different single separators.
			[ 'John Doe, Jane Doe', [ ',' ], [ 'John Doe', 'Jane Doe' ] ],
			[ 'John Doe and Jane Doe', [ ' and ' ], [ 'John Doe', 'Jane Doe' ] ],
			[ 'John Doe & Jane Doe', [ '&' ], [ 'John Doe', 'Jane Doe' ] ],
			// Wrong separator doesn't explode.
			[ 'John Doe & Jane Doe', [ ' and ' ], [ 'John Doe & Jane Doe' ] ],
		];
	}

	/**
	 * Data provider for test_byline_manual_substitutions.
	 */
	public function data_byline_manual_substitutions() {
		return [
			[
				'School of Journalism and Mass Communication',
				[ ' and ' ],
				[
					'School of Journalism and Mass Communication' => [
						'School of Journalism and Mass Communication',
					],
				],
				[ 'School of Journalism and Mass Communication' ],
			],
			// Manual substitution is applied before exploding, this prevents returning [ 'John Doe', 'School of Journalism', 'Mass Communication' ] which would be wrong.
			[
				'John Doe and School of Journalism and Mass Communication',
				[ ' and ' ],
				[
					'John Doe and School of Journalism and Mass Communication' => [
						'John Doe',
						'School of Journalism and Mass Communication',
					],
				],
				[ 'John Doe', 'School of Journalism and Mass Communication' ],
			],
			
		];
	}
}
