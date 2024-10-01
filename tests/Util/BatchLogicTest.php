<?php

namespace Newspack\MigrationTools\Tests\Util;

use Newspack\MigrationTools\Util\BatchLogic;
use WP_UnitTestCase;

class BatchLogicTest extends WP_UnitTestCase {

	/**
	 * Test that the batch args are correctly calculated.
	 *
	 * @return void
	 */
	public function test_batch_args(): void {

		$this->assertEquals(
			[
				'start' => 1,
				'end'   => 4,
				'total' => 3,
			],
			BatchLogic::validate_and_get_batch_args( [ 'num-items' => 3 ] ) 
		);
		$this->assertEquals(
			[
				'start' => 2000,
				'end'   => PHP_INT_MAX,
				'total' => ( PHP_INT_MAX - 2000 ),
			],
			BatchLogic::validate_and_get_batch_args( [ 'start' => 2000 ] ) 
		);

		// Test that if we pass less than 1 for start, it defaults to 1.
		$this->assertEquals(
			[
				'start' => 1,
				'end'   => 10,
				'total' => 9,
			],
			BatchLogic::validate_and_get_batch_args(
				[
					'start'     => 0,
					'num-items' => 9,
				] 
			) 
		);

		// TODO. Uncomment when https://github.com/Automattic/newspack-migration-tools/pull/16 is merged or we have a way to catch an exception.
		// $this->expectException( \Exception::class );
		// BatchLogic::validate_and_get_batch_args( [ 'start' => 100, 'end' => 10 ] );
	}
}
