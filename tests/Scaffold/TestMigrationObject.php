<?php

namespace Newspack\MigrationTools\Tests\Scaffold;

use InvalidArgumentException;
use Newspack\MigrationTools\Scaffold\FinalMigrationRunKey;
use Newspack\MigrationTools\Scaffold\MigrationObjectClass;
use stdClass;
use WP_UnitTestCase;

class TestMigrationObject extends WP_UnitTestCase {

	/**
	 * Json object to be used in tests.
	 *
	 * @var stdClass
	 */
	private stdClass $data;

	public function setUp(): void {
		parent::setUp();
		$this->data = json_decode(
			'
			{
			    "ID": 2,
			    "name": "Bob Builder",
			    "email": "bob@example.com",
			    "profile": {
			        "age": 42,
			        "bio": "Can we fix it? Yes, we can!",
			        "social": {
			            "twitter": "@bobthebuilder",
			            "linkedin": "linkedin.com\/in\/bob-the-builder"
			        }
			    },
			    "address": {
			        "billing": {
			            "street": "456 Construction Lane",
			            "city": "Buildville",
			            "postal_code": "B98765",
			            "country": "Workland"
			        },
			        "shipping": {
			            "street": "789 Fixit Drive",
			            "city": "Buildtown",
			            "postal_code": "B98766",
			            "country": "Workland"
			        }
			    },
			    "preferences": {
			        "newsletter": false,
			        "notifications": {
			            "email": true,
			            "sms": true
			        }
			    }
			}'
		);
	}

	/**
	 * Test that that data can be gotten.
	 *
	 * Note that this really tests the underlying Symfony PropertyAccess component more than anything -
	 * it's just to get tests started :)
	 *
	 * @return void
	 */
	public function test_get_data(): void {
		$migration_object = new MigrationObjectClass( new FinalMigrationRunKey( 'test_key' ), $this->data, 'ID' );
		$this->assertEquals( $this->data->address->shipping->street, $migration_object->get( 'address.shipping.street' ) );
	}

	/**
	 * Test that a broken pointer gives an exception.
	 *
	 * @return void
	 */
	public function test_broken_pointer_to_id(): void {
		$broken_pointer_to_id = 'LOL_not_id';
		// That key is not in our data – it should throw an exception.
		$this->expectException( InvalidArgumentException::class );
		$migration_object = new MigrationObjectClass( new FinalMigrationRunKey( 'test_key2' ), $this->data, $broken_pointer_to_id );
	}
}
