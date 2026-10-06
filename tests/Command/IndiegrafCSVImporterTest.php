<?php

namespace Newspack\MigrationTools\Tests\Command;

use Newspack\MigrationTools\Command\IndiegrafCSVImporter;
use Newspack\MigrationTools\Logic\GutenbergBlockGenerator;
use Newspack\MigrationTools\Logic\Posts;
use Newspack\MigrationTools\Logic\Sponsors;
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
 * Tests IndiegrafCSVImporter: transforms, media-ID sync, header normalization, and the posts import's re-run paths.
 *
 * Fixture CSVs are written byte-exact in each test, because a BOM, CRLF and invalid UTF-8 would not survive git or editors.
 */
class IndiegrafCSVImporterTest extends WP_UnitTestCase {

	private string $dir;
	private AbstractLogger $logger;
	private array $image_ids = [];

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
		$this->set_property( 'rest_url', null );
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

		$accordion = ( new GutenbergBlockGenerator() )->get_core_accordion(
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

	private function sync_media_ids( string $content ): array {
		$changed = false;
		$blocks  = ( new ReflectionMethod( IndiegrafCSVImporter::class, 'sync_block_media_ids' ) )->invokeArgs( IndiegrafCSVImporter::get_instance(), [ parse_blocks( $content ), 'indiegraf-post-1', 1, &$changed ] );

		return [ serialize_blocks( $blocks ), $changed ];
	}

	/**
	 * Real gallery markup from the Posts CSV (ID 472), captions removed, after the downloader: local src + wp-image-{new ID}, block id still the source ID.
	 *
	 * @param integer $image_1 Source ID of the first image in the gallery.
	 * @param integer $image_2 Source ID of the second image in the gallery.
	 * @param integer $file_id Source ID of the file block.
	 * @return string The gallery and file block markup with local media URLs and synced media IDs.
	 */
	private function media_fixture( int $image_1, int $image_2, int $file_id ): string {
		$uploads = wp_get_upload_dir()['baseurl'];

		return '<!-- wp:gallery {"linkTo":"none"} -->
<figure class="wp-block-gallery has-nested-images columns-default is-cropped"><!-- wp:image {"id":' . $image_1 . ',"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="' . $uploads . '/2025/06/show-04-1024x731.jpg" alt="" class="wp-image-' . $this->image_ids[0] . '"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":' . $image_2 . ',"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="' . $uploads . '/2025/06/show-05-768x1024.jpg" alt="" class="wp-image-' . $this->image_ids[1] . '"/></figure>
<!-- /wp:image --></figure>
<!-- /wp:gallery -->

<!-- wp:file {"id":' . $file_id . ',"href":"' . $uploads . '/2026/04/media-kit.pdf"} -->
<div class="wp-block-file"><a href="' . $uploads . '/2026/04/media-kit.pdf">Media Kit</a></div>
<!-- /wp:file -->';
	}

	private function create_media(): int {
		$this->image_ids = [
			$this->factory()->attachment->create_object(
				[
					'file'           => '2025/06/show-04.jpg',
					'post_mime_type' => 'image/jpeg',
				] 
			),
			$this->factory()->attachment->create_object(
				[
					'file'           => '2025/06/show-05.jpg',
					'post_mime_type' => 'image/jpeg',
				] 
			),
		];

		return $this->factory()->attachment->create_object(
			[
				'file'           => '2026/04/media-kit.pdf',
				'post_mime_type' => 'application/pdf',
			] 
		);
	}

	public function test_media_ids_are_synced_in_nested_gallery_and_file() {
		$file_id = $this->create_media();

		[ $content, $changed ] = $this->sync_media_ids( $this->media_fixture( 1944, 1943, 5350 ) );

		$this->assertTrue( $changed );
		$this->assertSame( $this->media_fixture( $this->image_ids[0], $this->image_ids[1], $file_id ), $content );
		$this->assertSame( [], $this->get_property( 'log_counts' ) );
	}

	public function test_media_text_id_and_source_media_link_are_synced() {
		$this->create_media();
		$this->set_property( 'rest_url', 'https://yountvillesun.com' );
		// Real markup from the Posts CSV (ID 1196), content removed; mediaLink is the source attachment page, on a "www." variant of the host.
		$media_text = fn( int $id, string $link ) => '<!-- wp:media-text {"mediaId":' . $id . ',"mediaLink":"' . $link . '","mediaType":"image","mediaWidth":39} -->
<div class="wp-block-media-text is-stacked-on-mobile" style="grid-template-columns:39% auto"><figure class="wp-block-media-text__media"><img src="' . wp_get_upload_dir()['baseurl'] . '/2025/06/show-04-1024x731.jpg" alt="" class="wp-image-' . $this->image_ids[0] . ' size-full"/></figure><div class="wp-block-media-text__content"></div></div>
<!-- /wp:media-text -->';

		[ $content, $changed ] = $this->sync_media_ids( $media_text( 5420, 'https://www.yountvillesun.com/ken_mcnab/' ) );

		$this->assertTrue( $changed );
		$this->assertSame( $media_text( $this->image_ids[0], get_attachment_link( $this->image_ids[0] ) ), $content );
	}

	public function test_synced_media_ids_are_a_no_op() {
		$file_id = $this->create_media();
		$synced  = $this->media_fixture( $this->image_ids[0], $this->image_ids[1], $file_id );

		[ $content, $changed ] = $this->sync_media_ids( $synced );

		$this->assertFalse( $changed );
		$this->assertSame( $synced, $content );
	}

	public function test_unresolvable_media_ids_are_kept_and_logged() {
		// Not downloaded: remote src, the class still has the source ID. Local src, but no such attachment. Media-text showing the featured image: no <img>, skipped.
		$content = '<!-- wp:image {"id":5420} -->
<figure class="wp-block-image"><img src="https://yountvillesun.com/wp-content/uploads/2025/06/remote.jpg" alt="" class="wp-image-5420"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":5421} -->
<figure class="wp-block-image"><img src="' . wp_get_upload_dir()['baseurl'] . '/2025/06/gone.jpg" alt="" class="wp-image-999999"/></figure>
<!-- /wp:image -->

<!-- wp:media-text {"mediaType":"image","imageFill":false,"useFeaturedImage":true} -->
<div class="wp-block-media-text is-stacked-on-mobile"><figure class="wp-block-media-text__media"></figure><div class="wp-block-media-text__content"></div></div>
<!-- /wp:media-text -->';

		[ $synced, $changed ] = $this->sync_media_ids( $content );

		$this->assertFalse( $changed );
		$this->assertSame( $content, $synced );
		$this->assertSame( [ 'unresolved' => 2 ], $this->get_property( 'log_counts' ) );
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

	/**
	 * A Posts CSV row with the columns the diff needs. Without --live-rest-url, nothing is fetched and categories come from the CSV.
	 *
	 * @param int    $id        Source ID.
	 * @param string $modified  Post Modified Date (GMT).
	 * @param array  $overrides Column values to change or add.
	 * @return array CSV row.
	 */
	private function post_row( int $id, string $modified, array $overrides = [] ): array {
		return array_merge(
			[
				'ID'                 => (string) $id,
				'Post Type'          => 'post',
				'Title'              => "Post $id",
				'Content'            => "<!-- wp:paragraph -->\n<p>Body $id</p>\n<!-- /wp:paragraph -->",
				'Date'               => '2025-01-01 10:00:00',
				'Post Modified Date' => $modified,
				'Status'             => 'publish',
				'Slug'               => "post-$id",
				'Parent'             => '0',
			],
			$overrides
		);
	}

	/**
	 * Writes the rows to a CSV and imports it, with fresh per-run state.
	 *
	 * @param array $rows             CSV rows, all with the same columns.
	 * @param bool  $update_conflicts --update-already-imported-posts.
	 */
	private function import_rows( array $rows, bool $update_conflicts = false ): void {
		$path = $this->dir . '/posts.csv';
		$fh   = fopen( $path, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $fh, array_keys( $rows[0] ), ',', '"', '' ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv.
		foreach ( $rows as $row ) {
			fputcsv( $fh, $row, ',', '"', '' ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv.
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$this->set_property( 'log_counts', [] );
		$this->set_property( 'touched_post_ids', [] );
		( new ReflectionMethod( IndiegrafCSVImporter::class, 'import_posts' ) )->invoke( IndiegrafCSVImporter::get_instance(), $path, $update_conflicts );
	}

	private function imported_id( int $source_id ): int {
		return (int) Posts::get_post_by_unique_identifier( 'indiegraf-post-' . $source_id );
	}

	public function test_rerun_creates_skips_and_updates_by_source_modified_date() {
		$this->import_rows( [ $this->post_row( 1, '2025-02-01 10:00:00' ), $this->post_row( 2, '2025-02-01 10:00:00' ) ] );
		$this->assertEquals( [ 'created' => 2 ], $this->get_property( 'log_counts' ) );
		$post_id = $this->imported_id( 1 );
		$this->assertSame( '2025-01-01 10:00:00', get_post_field( 'post_date_gmt', $post_id ) );
		$this->assertSame( '2025-02-01 10:00:00', get_post_field( 'post_modified_gmt', $post_id ) );

		// Same CSV again.
		$this->import_rows( [ $this->post_row( 1, '2025-02-01 10:00:00' ), $this->post_row( 2, '2025-02-01 10:00:00' ) ] );
		$this->assertEquals( [ 'skipped' => 2 ], $this->get_property( 'log_counts' ) );
		$this->assertSame( [], $this->get_property( 'touched_post_ids' ) );

		// Post 1 changed on the source.
		$this->import_rows( [ $this->post_row( 1, '2025-03-01 10:00:00', [ 'Title' => 'Changed' ] ), $this->post_row( 2, '2025-02-01 10:00:00' ) ] );
		$this->assertEquals(
			[
				'updated' => 1,
				'skipped' => 1,
			],
			$this->get_property( 'log_counts' )
		);
		$this->assertSame( $post_id, $this->imported_id( 1 ), 'Updated in place.' );
		$this->assertSame( 'Changed', get_the_title( $post_id ) );
		$this->assertSame( '2025-03-01 10:00:00', get_post_field( 'post_modified_gmt', $post_id ) );
		$this->assertSame( [ $post_id ], $this->get_property( 'touched_post_ids' ) );
	}

	public function test_local_edit_is_a_conflict_unless_overridden() {
		$this->import_rows( [ $this->post_row( 1, '2025-02-01 10:00:00' ) ] );
		$post_id = $this->imported_id( 1 );
		wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => 'Edited here',
			]
		);

		$this->import_rows( [ $this->post_row( 1, '2025-03-01 10:00:00' ) ] );
		$this->assertEquals( [ 'conflict' => 1 ], $this->get_property( 'log_counts' ) );
		$this->assertSame( 'Edited here', get_the_title( $post_id ) );

		$this->import_rows( [ $this->post_row( 1, '2025-03-01 10:00:00' ) ], true );
		$this->assertEquals( [ 'updated' => 1 ], $this->get_property( 'log_counts' ) );
		$this->assertSame( $post_id, $this->imported_id( 1 ), 'Updated in place.' );
		$this->assertSame( 'Post 1', get_the_title( $post_id ) );
		$this->assertSame( '2025-03-01 10:00:00', get_post_field( 'post_modified_gmt', $post_id ) );
	}

	public function test_posts_missing_from_the_csv_are_reported_not_deleted() {
		$this->import_rows( [ $this->post_row( 1, '2025-02-01 10:00:00' ), $this->post_row( 2, '2025-02-01 10:00:00' ) ] );

		$this->import_rows( [ $this->post_row( 1, '2025-02-01 10:00:00' ) ] );

		$this->assertEquals(
			[
				'skipped' => 1,
				'missing' => 1,
			],
			$this->get_property( 'log_counts' )
		);
		$this->assertSame( 'publish', get_post_status( $this->imported_id( 2 ) ) );
	}

	public function test_empty_source_modified_date_updates_on_every_run() {
		$this->import_rows( [ $this->post_row( 1, '' ) ] );
		$this->assertEquals( [ 'created' => 1 ], $this->get_property( 'log_counts' ) );

		$this->import_rows( [ $this->post_row( 1, '' ) ] );
		$this->assertEquals( [ 'updated' => 1 ], $this->get_property( 'log_counts' ) );
		$this->assertStringContainsString( 'No Post Modified Date', file_get_contents( $this->dir . '/log.csv' ) ); // phpcs:ignore -- WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown.
	}

	public function test_parent_after_its_child_in_the_csv_is_set() {
		$page = fn( int $id, int $parent ) => $this->post_row( // phpcs:ignore -- Universal.NamingConventions.NoReservedKeywordParameterNames.parentFound.
			$id,
			'2025-02-01 10:00:00',
			[
				'Post Type' => 'page',
				'Parent'    => (string) $parent,
			]
		);

		$this->import_rows( [ $page( 20, 10 ), $page( 10, 0 ), $page( 30, 99 ) ] );

		$child_id = $this->imported_id( 20 );
		$this->assertSame( $this->imported_id( 10 ), wp_get_post_parent_id( $child_id ) );
		$this->assertSame( '2025-02-01 10:00:00', get_post_field( 'post_modified_gmt', $child_id ), 'Setting the parent keeps post_modified.' );
		$this->assertSame( 0, wp_get_post_parent_id( $this->imported_id( 30 ) ), 'Source parent 99 is not in the CSV.' );
		$this->assertEquals(
			[
				'created'    => 3,
				'unresolved' => 1,
			],
			$this->get_property( 'log_counts' )
		);

		// No conflict on the next run.
		$this->import_rows( [ $page( 20, 10 ), $page( 10, 0 ) ] );
		$this->assertEquals(
			[
				'skipped' => 2,
				'missing' => 1,
			],
			$this->get_property( 'log_counts' )
		);
	}

	public function test_sponsors_are_cleared_when_unflagged_and_kept_when_unresolved() {
		// Newspack Sponsors is not active in tests; its taxonomy is enough for the replace and clear paths.
		register_taxonomy( Sponsors::SPONSORS_TAXONOMY, 'post' );
		$sponsor_row = fn( string $flag, string $modified ) => $this->post_row(
			1,
			$modified,
			[
				'sponsor_settings_is_sponsored' => $flag,
				'Sponsor settings_name'         => '',
			]
		);
		try {
			$this->import_rows( [ $sponsor_row( '0', '2025-02-01 10:00:00' ) ] );
			$post_id = $this->imported_id( 1 );
			wp_set_object_terms( $post_id, 'Old Sponsor', Sponsors::SPONSORS_TAXONOMY );

			// Flagged, but no sponsor name: the current sponsor stays.
			$this->import_rows( [ $sponsor_row( '1', '2025-03-01 10:00:00' ) ] );
			$this->assertEquals(
				[
					'updated'    => 1,
					'unresolved' => 1,
				],
				$this->get_property( 'log_counts' )
			);
			$this->assertSame( [ 'Old Sponsor' ], wp_get_object_terms( $post_id, Sponsors::SPONSORS_TAXONOMY, [ 'fields' => 'names' ] ) );

			// No longer sponsored.
			$this->import_rows( [ $sponsor_row( '0', '2025-04-01 10:00:00' ) ] );
			$this->assertSame( [], wp_get_object_terms( $post_id, Sponsors::SPONSORS_TAXONOMY, [ 'fields' => 'names' ] ) );
		} finally {
			unregister_taxonomy( Sponsors::SPONSORS_TAXONOMY );
		}
	}

	public function test_byline_merged_by_slug_is_logged() {
		$get_byline = fn( string $name ) => ( new ReflectionMethod( IndiegrafCSVImporter::class, 'get_or_create_byline_user' ) )->invoke( IndiegrafCSVImporter::get_instance(), $name, null );

		$first  = $get_byline( 'Smith-Jones' );
		$second = $get_byline( 'Smith Jones' );

		$this->assertSame( $first->ID, $second->ID );
		$this->assertEquals(
			[
				'created'    => 1,
				'unresolved' => 1,
			],
			$this->get_property( 'log_counts' )
		);
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
