<?php

namespace Newspack\MigrationTools\Tests\Command;

use Newspack\MigrationTools\Command\IndiegrafCSVImporter;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use Newspack\MigrationTools\Logic\UsersHelper;
use Newspack\MigrationTools\Util\CsvIterator;
use Newspack\MigrationTools\Util\CsvWriter;
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
		$this->set_property( 'logger', $this->logger );
		$this->set_property( 'csv_log', new CsvWriter( $this->dir . '/log.csv' ) );
		$this->set_property( 'log_counts', [] );
		$this->set_property( 'content_hosts', [] );
	}

	protected function tearDown(): void {
		array_map( 'unlink', glob( $this->dir . '/*' ) );
		rmdir( $this->dir ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir.
		parent::tearDown();
	}

	private function set_property( string $name, mixed $value ): void {
		( new ReflectionProperty( IndiegrafCSVImporter::class, $name ) )->setValue( IndiegrafCSVImporter::get_instance(), $value );
	}

	private function get_property( string $name ): mixed {
		return ( new ReflectionProperty( IndiegrafCSVImporter::class, $name ) )->getValue( IndiegrafCSVImporter::get_instance() );
	}

	private function transform( string $content ): string {
		return ( new ReflectionMethod( IndiegrafCSVImporter::class, 'transform_content' ) )->invoke( IndiegrafCSVImporter::get_instance(), $content, 'indiegraf-post-1' );
	}

	private function normalize( string $path ): void {
		( new ReflectionMethod( IndiegrafCSVImporter::class, 'normalize_csv_headers' ) )->invoke( IndiegrafCSVImporter::get_instance(), $path );
	}

	private function write_fixture( string $bytes ): string {
		$path = $this->dir . '/fixture.csv';
		file_put_contents( $path, $bytes );

		return $path;
	}

	public function test_home_page_blocks_are_dropped() {
		// Real markup from the Pages CSV (home, subscribe-and-support, checkout); attributes shortened.
		$paragraph = "<!-- wp:paragraph -->\n<p>Kept</p>\n<!-- /wp:paragraph -->";
		$content   = '<!-- wp:indiegraf/featured-post {"showLatest":true,"postId":3996,"categories":[81,69],"hidePosts":false} /-->' . "\n\n"
			. $paragraph . "\n\n"
			. '<!-- wp:indiegraf/latest-posts {"categories":[60,67],"columns":2,"postTotal":12,"sectionTitle":"News from the Yountville Sun","ajaxLoadMore":true} /-->' . "\n\n"
			. '<!-- wp:indiegrafpay/stripe-pricing-tables {"plans":["monthly","yearly"],"showCustom":false,"checkoutPageUrl":"https://yountvillesun.com/checkout"} /-->' . "\n\n"
			. '<!-- wp:indiegrafpay/stripe-checkout {"planPrefix":"Subscribe to Pro","addressField":true,"thankyouPageUrl":"https://yountvillesun.com/thank-you-supporter"} /-->';

		// Dropped blocks leave their surrounding blank lines.
		$this->assertSame( "\n\n$paragraph\n\n\n\n\n\n", $this->transform( $content ) );
		$this->assertSame( [ 'dropped' => 4 ], $this->get_property( 'log_counts' ) );
	}

	public function test_about_is_unwrapped() {
		// Real markup from the Pages CSV (home), image column shortened.
		$inner   = '<!-- wp:heading {"level":5,"placeholder":"Section title","className":"wp-block-indiegraf-about__title section-title"} -->
<h5 class="wp-block-heading wp-block-indiegraf-about__title section-title">About Us</h5>
<!-- /wp:heading -->';
		$columns = '<!-- wp:columns {"className":"section-content"} -->
<div class="wp-block-columns section-content"><!-- wp:column {"width":"60%","className":"wp-block-indiegraf-about__content"} -->
<div class="wp-block-column wp-block-indiegraf-about__content" style="flex-basis:60%"><!-- wp:paragraph {"placeholder":"Section content"} -->
<p>The <em>Yountville Sun</em> is committed to telling the stories that shape our community.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->';
		$content = "<!-- wp:indiegraf/about -->\n<div class=\"wp-block-indiegraf-about\">$inner\n\n$columns</div>\n<!-- /wp:indiegraf/about -->";

		$this->assertSame( $inner . $columns, $this->transform( $content ) );
		$this->assertSame( [ 'dropped' => 1 ], $this->get_property( 'log_counts' ) );
	}

	public function test_credit_without_inner_blocks_is_removed() {
		// Real markup from the Posts CSV (ID 2359), text shortened.
		$content = '<!-- wp:indiegraf/credit -->
<div class="credit"><!-- wp:indiegraf/credit-item {"logo":"https://yountvillesun.com/wp-content/uploads/2025/07/Yountville-Sun-Logo_Small.png"} -->
<div class="credit-item"><p class="credit-item-label font-small">Brought to you by</p><div class="credit-item-content"><img class="credit-item-logo" src="https://yountvillesun.com/wp-content/uploads/2025/07/Yountville-Sun-Logo_Small.png" alt="Logo" style="max-width:150px;max-height:150px"/><div class="credit-item-text"><h5 class="credit-item-title">Your Name &amp; Logo </h5></div></div></div>
<!-- /wp:indiegraf/credit-item --></div>
<!-- /wp:indiegraf/credit -->';

		$this->assertSame( '', $this->transform( $content ) );
		$this->assertSame( [ 'dropped' => 2 ], $this->get_property( 'log_counts' ) );
		$this->assertSame( [], $this->get_property( 'content_hosts' ), 'Hosts are collected from the transformed content only.' );
	}

	public function test_accordion_in_group_becomes_core_accordion() {
		// Real markup from the Pages CSV (subscribe-and-support), 2 of 5 items.
		$item    = fn( int $index, string $title, string $body ) => '<!-- wp:indiegraf/accordion-item {"instanceId":' . $index . ',"parentBlockId":"4a26c365","parentBackgroundColor":"#dfdfdf","parentHeadingTextColor":"#222222","parentBodyTextColor":"#222222"} -->
<div style="background-color:#dfdfdf" class="wp-block-indiegraf-accordion-item wp-block-indiegraf-accordion-item"><div id="accordion-item-header-4a26c365-' . $index . '" class="wp-block-indiegraf-accordion-item__header" role="button" aria-expanded="false" aria-controls="accordion-item-body-4a26c365-' . $index . '"><p class="wp-block-indiegraf-accordion-item__header-title" style="color:#222222">' . $title . '</p><span class="icon-accordion" style="color:#222222" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M7.5.625 16.25 10 7.5 19.375l-2.5-2.5L11.25 10 5 3.125Z"></path></svg></span></div><div id="accordion-item-body-4a26c365-' . $index . '" class="wp-block-indiegraf-accordion-item__body" aria-labelledby="accordion-item-header-4a26c365-' . $index . '"><div class="wp-block-indiegraf-accordion-item__body-content" style="color:#222222">' . $body . '</div></div></div>
<!-- /wp:indiegraf/accordion-item -->';
		$body_1  = "<!-- wp:freeform -->\n<p>All payments are processed through Stripe. Learn more&nbsp;<a href=\"https://stripe.com/docs/security/stripe\" target=\"_blank\" rel=\"noreferrer noopener\">here</a>.</p>\n<!-- /wp:freeform -->";
		$body_2  = "<!-- wp:freeform -->\n<p>Get in touch by selecting “Customer Service” on our&nbsp;<a href=\"/contact-us/\">contact page</a>.</p>\n<!-- /wp:freeform -->";
		$content = '<!-- wp:group {"className":"section-faq-support","layout":{"inherit":true,"type":"constrained"}} -->
<div id="faq" class="wp-block-group section-faq-support"><!-- wp:indiegraf/accordion {"blockId":"4a26c365"} -->
<div class="wp-block-indiegraf-accordion">' . $item( 0, '<strong>Is your website secure?</strong>', $body_1 ) . "\n\n" . $item( 4, '<strong><strong><strong>What if I have a &amp; different question?</strong></strong></strong>', $body_2 ) . '</div>
<!-- /wp:indiegraf/accordion --></div>
<!-- /wp:group -->';

		$accordion = ( new GutenbergBlockGenerator() )->get_accordion(
			[
				[
					'title'  => 'Is your website secure?',
					'blocks' => parse_blocks( $body_1 ),
				],
				[
					'title'  => 'What if I have a &amp; different question?',
					'blocks' => parse_blocks( $body_2 ),
				],
			]
		);
		$expected  = '<!-- wp:group {"className":"section-faq-support","layout":{"inherit":true,"type":"constrained"}} -->
<div id="faq" class="wp-block-group section-faq-support">' . serialize_block( $accordion ) . '</div>
<!-- /wp:group -->';

		$this->assertSame( $expected, $this->transform( $content ) );
		$this->assertSame( [], $this->get_property( 'log_counts' ) );
	}

	public function test_user_list_becomes_heading_and_author_profile() {
		$user_id = $this->factory()->user->create( [ 'display_name' => 'Newsroom' ] );
		update_user_meta( $user_id, UsersHelper::UNIQUE_IDENTIFIER_META_KEY, 'indiegraf-user-11' );
		// Real markup from the Pages CSV (our-team).
		$content = '<!-- wp:indiegraf/user-list -->
<div class="wp-block-indiegraf-user-list"><h6 class="team-title">Editorial Team</h6><!-- wp:indiegraf/user-profile {"userId":11} /--></div>
<!-- /wp:indiegraf/user-list -->';

		$generator = new GutenbergBlockGenerator();
		$expected  = serialize_blocks(
			[
				$generator->get_heading( 'Editorial Team', 'h6' ),
				$generator->get_author_profile( $user_id, false, true, true, false, true, false, true ),
			]
		);
		$this->assertSame( $expected, $this->transform( $content ) );
	}

	public function test_shortcode_is_kept_and_logged() {
		// Real markup from the Pages CSV (newsletter).
		$content = "<!-- wp:shortcode -->\n[cp_popup display=\"inline\" style_id=\"145\" step_id = \"1\"][/cp_popup]\n<!-- /wp:shortcode -->";

		$this->assertSame( $content, $this->transform( $content ) );
		$this->assertSame( [ 'unresolved' => 1 ], $this->get_property( 'log_counts' ) );
	}

	public function test_unknown_indiegraf_block_is_unwrapped() {
		$paragraph = "<!-- wp:paragraph -->\n<p>Inner</p>\n<!-- /wp:paragraph -->";

		$this->assertSame( $paragraph, $this->transform( "<!-- wp:indiegraf/unknown -->\n<div>$paragraph</div>\n<!-- /wp:indiegraf/unknown -->" ) );
		$this->assertSame( [ 'dropped' => 1 ], $this->get_property( 'log_counts' ) );
	}

	public function test_media_hosts_are_grouped() {
		// Real URLs from the Posts and Pages CSVs; classic (non-block) content is scanned too.
		$content = '<p><img src="https://yountvillesun.com/wp-content/uploads/2025/07/ugi-k-eSl_QHc_hFo-unsplash.jpg"></p>
<img src="https://indiedemo.wpengine.com/wp-content/uploads/2022/03/our-team-1024x652.jpg">
<img src="https://d1qvdom7axrrra.cloudfront.net/wp-content/uploads/2025/07/31192104/News-Worth-Mentioning-Logo-073125.png">
<img src="https://images.unsplash.com/photo-1.jpg"><img src="/wp-content/uploads/2024/01/relative.jpg">
<a href="https://yountvillesun.com/wp-content/uploads/2024/05/agenda.PDF">Agenda</a><a href="https://yountvillesun.com/about/">About</a>
<a href="https://www.usfa.fema.gov/downloads/pdf/publications/report.pdf">External PDF</a>';

		$this->assertSame( $content, $this->transform( $content ) );
		$this->assertSame(
			[
				'wp'  => [
					'yountvillesun.com'      => true,
					'indiedemo.wpengine.com' => true,
				],
				'cdn' => [ 'd1qvdom7axrrra.cloudfront.net' => true ],
				'pdf' => [ 'yountvillesun.com' => true ],
			],
			$this->get_property( 'content_hosts' )
		);
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
