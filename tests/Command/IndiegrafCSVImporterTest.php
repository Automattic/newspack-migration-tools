<?php

namespace Newspack\MigrationTools\Tests\Command;

use Newspack\MigrationTools\Command\IndiegrafCSVImporter;
use Newspack\MigrationTools\Util\CsvIterator;
use Psr\Log\AbstractLogger;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Stringable;
use WP_UnitTestCase;

/**
 * Tests the pure-logic parts of IndiegrafCSVImporter.
 *
 * Fixture CSVs are written byte-exact in each test, because a BOM, CRLF and invalid UTF-8 would not survive git or editors.
 */
class IndiegrafCSVImporterTest extends WP_UnitTestCase {

	private string $dir;
	private AbstractLogger $logger;

	protected function setUp(): void {
		parent::setUp();
		if ( ! class_exists( 'WP_CLI' ) ) {
			class_alias( WpCliStub::class, 'WP_CLI' );
		}

		$this->dir = get_temp_dir() . 'indiegraf-csv-test-' . uniqid();
		mkdir( $this->dir ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir.

		$this->logger = new class() extends AbstractLogger {
			public array $messages = [];

			public function log( $level, string|Stringable $message, array $context = [] ): void {
				$this->messages[] = (string) $message;
			}
		};
		( new ReflectionProperty( IndiegrafCSVImporter::class, 'logger' ) )->setValue( IndiegrafCSVImporter::get_instance(), $this->logger );
	}

	protected function tearDown(): void {
		array_map( 'unlink', glob( $this->dir . '/*' ) );
		rmdir( $this->dir ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir.
		parent::tearDown();
	}

	private function normalize( string $path ): void {
		( new ReflectionMethod( IndiegrafCSVImporter::class, 'normalize_csv_headers' ) )->invoke( IndiegrafCSVImporter::get_instance(), $path );
	}

	private function write_fixture( string $bytes ): string {
		$path = $this->dir . '/fixture.csv';
		file_put_contents( $path, $bytes );

		return $path;
	}

	public function test_bom_duplicate_headers_and_crlf() {
		$header = "\xEF\xBB\xBF" . '"ID","Title","Content","Title","Indie Ads","Indie Ads"' . "\r\n";
		// Body has a multi-line quoted field and a non-UTF-8 byte: both must pass through untouched.
		$body = "1,\"First\",\"Line one\r\nline two, \"\"quoted\"\"\",,1,\r\n2,Caf\xE9,x,,,1\r\n";
		$path = $this->write_fixture( $header . $body );

		$this->normalize( $path );

		$result = file_get_contents( $path ); // phpcs:ignore -- WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown.
		$this->assertStringStartsNotWith( "\xEF\xBB\xBF", $result );
		$new_header_length = strpos( $result, "\r\n" ) + 2;
		$this->assertSame( $body, substr( $result, $new_header_length ), 'Body bytes changed.' );
		$this->assertSame( [ 'ID', 'Title', 'Content', 'Title2', 'Indie Ads', 'Indie Ads2' ], str_getcsv( substr( $result, 0, $new_header_length - 2 ), ',', '"', '' ) );
		$this->assertSame( $header . $body, file_get_contents( $this->dir . '/fixture__originalBackup.csv' ) );
		$this->assertSame( [ "{$path}: Removed the UTF-8 BOM. Column 'Title' #2 (col 4) was renamed to 'Title2'. Column 'Indie Ads' #2 (col 6) was renamed to 'Indie Ads2'. Backup: {$this->dir}/fixture__originalBackup.csv" ], $this->logger->messages );

		$rows = iterator_to_array( ( new CsvIterator() )->items( $path, ',' ), false );
		$this->assertSame( "Line one\r\nline two, \"quoted\"", $rows[0]['Content'] );
		$this->assertSame( '1', $rows[1]['Indie Ads2'] );

		// Second run is a no-op.
		$this->logger->messages = [];
		$this->normalize( $path );
		$this->assertSame( $result, file_get_contents( $path ) ); // phpcs:ignore -- WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown.
		$this->assertSame( [], $this->logger->messages );
	}

	public function test_lf_is_preserved() {
		$path = $this->write_fixture( "A,A\nx,y\n" );

		$this->normalize( $path );

		$this->assertSame( "\"A\",\"A2\"\nx,y\n", file_get_contents( $path ) ); // phpcs:ignore -- WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown.
	}

	public function test_rename_collision_aborts_without_changes() {
		$bytes = "A,A,A2\nx,y,z\n";
		$path  = $this->write_fixture( $bytes );

		try {
			$this->normalize( $path );
			$this->fail( 'Expected an abort on a rename collision.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'duplicate names', $e->getMessage() );
		}

		$this->assertSame( $bytes, file_get_contents( $path ) ); // phpcs:ignore -- WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown.
		$this->assertFileDoesNotExist( $this->dir . '/fixture__originalBackup.csv' );
	}

	public function test_clean_file_is_not_touched() {
		$bytes = "ID,Title\n1,x\n";
		$path  = $this->write_fixture( $bytes );

		$this->normalize( $path );

		$this->assertSame( $bytes, file_get_contents( $path ) ); // phpcs:ignore -- WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown.
		$this->assertFileDoesNotExist( $this->dir . '/fixture__originalBackup.csv' );
	}

	public function test_existing_backup_is_never_overwritten() {
		$backup = $this->dir . '/fixture__originalBackup.csv';
		file_put_contents( $backup, 'older backup' );
		$path = $this->write_fixture( "\xEF\xBB\xBFID,Title\n1,x\n" );

		$this->normalize( $path );

		$this->assertSame( "\"ID\",\"Title\"\n1,x\n", file_get_contents( $path ) ); // phpcs:ignore -- WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown.
		$this->assertSame( 'older backup', file_get_contents( $backup ) ); // phpcs:ignore -- WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown.
	}

	public function test_invalid_utf8_header_aborts_without_changes() {
		$bytes = "\xEF\xBB\xBFID,T\xE9tle,T\xE9tle\n1,x,y\n";
		$path  = $this->write_fixture( $bytes );

		try {
			$this->normalize( $path );
			$this->fail( 'Expected an abort on an invalid UTF-8 header.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'not valid UTF-8', $e->getMessage() );
		}

		$this->assertSame( $bytes, file_get_contents( $path ) ); // phpcs:ignore -- WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown.
		$this->assertFileDoesNotExist( $this->dir . '/fixture__originalBackup.csv' );
	}
}

/**
 * Minimal WP_CLI stand-in: the test suite runs without WP-CLI, and WP_CLI::error() must stop execution.
 */
// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
class WpCliStub {

	/**
	 * Aborts like WP_CLI::error().
	 *
	 * @param string $message Error message.
	 *
	 * @throws RuntimeException Always.
	 */
	public static function error( string $message ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		throw new RuntimeException( $message );
	}

	/**
	 * Ignores every other WP_CLI call.
	 *
	 * @param string $name      Method name.
	 * @param array  $arguments Arguments.
	 */
	public static function __callStatic( string $name, array $arguments ): void {}
}
