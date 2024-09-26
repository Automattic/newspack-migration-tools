<?php

namespace Newspack\MigrationTools\Tests\Scaffold;

use Newspack\MigrationTools\Scaffold\FinalMigrationRunKey;
use Newspack\MigrationTools\Scaffold\MigrationObject;
use stdClass;
use WP_UnitTestCase;

class TestMigrationObject extends WP_UnitTestCase {

	private stdClass $data;

	public function setUp(): void {
		parent::setUp();
		$this->data = json_decode( '
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


	public function test_get_data() {
		$migrationObject = new MigrationObject( new FinalMigrationRunKey( 'horse_key' ), $this->data, 'ID' );
		$this->assertEquals( $this->data->address->shipping->street, $migrationObject->get( 'address.shipping.street' ) );
	}
}