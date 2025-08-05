<?php

use PHPUnit\Framework\TestCase;
use Newspack\MigrationTools\Util\JsonWriter;

class JsonWriterTest extends TestCase {
	private string $test_file = 'test_output.json';

	protected function tearDown(): void {
		if ( file_exists( $this->test_file ) ) {
			unlink( $this->test_file );
		}
	}

	public function testWritesSingleItem() {
		$writer = new JsonWriter( $this->test_file );
		$item   = [
			'foo' => 'bar',
			'baz' => 123,
		];
		$writer->put( $item );
		$writer->close();

		$content = file_get_contents( $this->test_file );
		$this->assertJsonStringEqualsJsonString(
			json_encode( [ $item ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			$content
		);
	}

	public function testWritesMultipleItems() {
		$writer = new JsonWriter( $this->test_file );
		$item1  = [ 'a' => 1 ];
		$item2  = [ 'b' => 2 ];
		$writer->put( $item1 );
		$writer->put( $item2 );
		$writer->close();

		$content = file_get_contents( $this->test_file );
		$this->assertJsonStringEqualsJsonString(
			json_encode( [ $item1, $item2 ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			$content
		);
	}

	public function test_fileIsClosedOnDestruct() {
		$item     = [ 'x' => 'y' ];
		$filename = $this->test_file;
		// Use a closure to force __destruct
		$createAndWrite = function() use ( $filename, $item ) {
			$writer = new JsonWriter( $filename );
			$writer->put( $item );
			// No explicit close
		};
		$createAndWrite();
		$content = file_get_contents( $filename );
		$this->assertJsonStringEqualsJsonString(
			json_encode( [ $item ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			$content
		);
	}
}
