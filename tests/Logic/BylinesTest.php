<?php
/**
 * Tests for the Posts class.
 */

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\MigrationTools\Logic\Bylines;
use WP_UnitTestCase;
use WP_Error;

/**
 * Test the Posts class.
 */
class BylinesTest extends WP_UnitTestCase {
	/**
	 * The Posts instance.
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
	 * Test parsing byline with single separator.
	 * 
	 * @dataProvider data_explode_by_single_separator
	 */
	public function test_byline_explode_by_single_separator( string $byline, array $expected ) {
		$result = $this->bylines->get_author_names_from_byline( $byline, [ ',' ] );
		$this->assertEquals( $expected, $result );	
	}

	/**
	 * Data provider for test_byline_explode_by_single_separator.
	 */
	public function data_explode_by_single_separator() {
		return [
			[ 'John Doe', [ 'John Doe' ] ],
			[ 'John Doe, Jane Doe', [ 'John Doe', 'Jane Doe' ] ],
			[ 'John Doe, Jane Doe, Jim Doe', [ 'John Doe', 'Jane Doe', 'Jim Doe' ] ],
		];
	}
}
