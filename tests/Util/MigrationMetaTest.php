<?php

namespace Newspack\MigrationTools\Tests\Util;

use Newspack\MigrationTools\Util\MigrationMeta;
use InvalidArgumentException;
use WP_UnitTestCase;

class MigrationMetaTest extends WP_UnitTestCase {

	private int $post_id = 1;

	/**
	 * Test CRUD operations on MigrationMeta.
	 *
	 * @return void
	 */
	public function testCrud() {
		$this->assertNull( MigrationMeta::get( $this->post_id, 'non_existent_key', 'post' ) );

		MigrationMeta::update( $this->post_id, 'first_key', 'post', 3 );
		MigrationMeta::update( $this->post_id, 'second_key', 'post', 2 );

		$this->assertEquals( 3, MigrationMeta::get( $this->post_id, 'first_key', 'post' ) );
		$this->assertEquals( 2, MigrationMeta::get( $this->post_id, 'second_key', 'post' ) );

		MigrationMeta::update( $this->post_id, 'first_key', 'post', 30 );
		MigrationMeta::update( $this->post_id, 'second_key', 'post', 20 );

		$this->assertEquals( 30, MigrationMeta::get( $this->post_id, 'first_key', 'post' ) );
		$this->assertEquals( 20, MigrationMeta::get( $this->post_id, 'second_key', 'post' ) );

		MigrationMeta::delete( $this->post_id, 'first_key', 'post' );
		$this->assertNull( MigrationMeta::get( $this->post_id, 'first_key', 'post' ) );
		$this->assertEquals( 20, MigrationMeta::get( $this->post_id, 'second_key', 'post' ) );
	}

	/**
	 * Test that we can only update with valid types.
	 *
	 * @return void
	 */
	public function testValidate() {
		$this->expectException( InvalidArgumentException::class );
		MigrationMeta::update( $this->post_id, 'first_key', 'type_that_is_NOT_valid', 1 );
	}
}
