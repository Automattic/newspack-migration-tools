<?php

namespace Newspack\MigrationTools\Util\Tests;

use PHPUnit\Framework\TestCase;
use Newspack\MigrationTools\Util\CsvWriter;

/**
 * Class CsvWriterTest
 * 
 * @package newspack-migration-tools
 */
class CsvWriterTest extends TestCase {
	private string $test_file;

	protected function setUp(): void {
		parent::setUp();

		$temp_dir = get_temp_dir();

		$random_dir_name = wp_unique_filename( $temp_dir, uniqid( time() ) );

		wp_mkdir_p( $random_dir_name );

		$this->test_file = $random_dir_name . '/test.csv';
		if ( file_exists( $this->test_file ) ) {
			unlink( $this->test_file );
		}
	}

	protected function tearDown(): void {
		if ( file_exists( $this->test_file ) ) {
			unlink( $this->test_file );
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir
			rmdir( dirname( $this->test_file ) );
		}
	}

	public function testConstructorCreatesFile(): void {
		$csv_writer = new CsvWriter( $this->test_file );
		$this->assertFileExists( $this->test_file );
		$csv_writer->close();
	}

	public function testSetHeaderWritesHeader(): void {
		$csv_writer = new CsvWriter( $this->test_file );
		$header     = [ 'Column1', 'Column2', 'Column3' ];
		$csv_writer->set_header( $header );
		$csv_writer->close();

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$content = file_get_contents( $this->test_file );
		$this->assertStringContainsString( 'Column1,Column2,Column3', $content );
	}

	public function testPutWritesRow(): void {
		$csv_writer = new CsvWriter( $this->test_file );
		$row        = [ 'Data1', 'Data2', 'Data3' ];
		$csv_writer->put( $row );
		$csv_writer->close();

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$content = file_get_contents( $this->test_file );
		$this->assertStringContainsString( 'Data1,Data2,Data3', $content );
	}

	public function testSetHeaderDoesNotOverwriteExistingContent(): void {
		$csv_writer = new CsvWriter( $this->test_file );
		$csv_writer->put( [ 'ExistingData' ] );
		$csv_writer->set_header( [ 'Header1', 'Header2' ] );
		$csv_writer->close();

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$content = file_get_contents( $this->test_file );
		$this->assertStringNotContainsString( 'Header1,Header2', $content );
	}
}
