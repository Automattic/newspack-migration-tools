<?php

namespace Newspack\MigrationTools\Tests\Util;

use Newspack\MigrationTools\Util\PostSelect;
use WP_UnitTestCase;

class TestPostSelect extends WP_UnitTestCase {
	private array $post_ids;
	private array $page_ids;

	private int $num_posts = 14;
	private int $num_pages = 3;

	public function setUp(): void {
		parent::setUp();
		add_filter( 'newspack_migration_tools_log_file_logger_disable', '__return_true' );
		add_filter( 'newspack_migration_tools_log_clilog_disable', '__return_true' );

		$this->post_ids = array_map( fn() => self::factory()->post->create(), range( 0, $this->num_posts - 1 ) );
		$this->page_ids = array_map( fn() => self::factory()->post->create( [ 'post_type' => 'page' ] ), range( 0, $this->num_pages - 1 ) );
	}

	/**
	 * Test getting post IDs by range.
	 */
	public function test_id_range(): void {
		// Expect all posts with default type "post".
		$this->assertEquals( $this->num_posts, count( PostSelect::get_id_range( [] ) ) );

		// Expect all posts with types "page" and "post".
		$num_all_posts = ( $this->num_posts + $this->num_pages );
		$this->assertEquals( $num_all_posts, count( PostSelect::get_id_range( [ 'post-types' => 'page, post' ] ) ) );

		// Expect all posts with default type "page".
		$page_range = PostSelect::get_id_range( [ 'post-types' => 'page' ] );
		$this->assertEquals( $this->num_pages, count( $page_range ) );
		// Check that the posts are the same – note that the range is ordered by ID DESC.
		$expected_page_title = get_post( $this->page_ids[0] )->post_title;
		$fetched_page_title  = get_post( end( $page_range ) )->post_title;
		$this->assertEquals( $expected_page_title, $fetched_page_title );

		// Expect posts with a min and max ID.
		// The range should be inclusive of the min and max.
		// Note that the max ID is in the beginning of the array, and the min ID is at the end
		// because the get_id_range function orders by ID DESC.
		$random_post_ids = $this->get_random_post_ids( 5 );
		$min             = min( $random_post_ids );
		$max             = max( $random_post_ids );
		$args            = [
			'min-post-id' => $min,
			'max-post-id' => $max,
		];
		$range           = PostSelect::get_id_range( $args );
		$this->assertEquals( $min, end( $range ) );
		$this->assertEquals( $max, reset( $range ) );

		// Test that if we only want 3 – we get just that.
		$args['num-items'] = 3;
		$this->assertEquals( 3, count( PostSelect::get_id_range( $args ) ) );

		$args['post-id'] = implode( ', ', $random_post_ids ) . ', 9999999 , 28343';
		// Check that the non-existent posts IDs we added to the list are not returned.
		sort( $random_post_ids ); // Sort and reverse the array to match the ORDER BY ID DESC.
		$this->assertEquals( array_reverse( $random_post_ids ), PostSelect::get_id_range( $args ) );
	}

	/**
	 * Helper to get a random set of post IDs.
	 *
	 * @param int $num_post_ids Number of post IDs to get.
	 *
	 * @return array Random post IDs.
	 */
	private function get_random_post_ids( int $num_post_ids ): array {
		$random_keys = array_rand( $this->post_ids, $num_post_ids );

		return array_intersect_key( $this->post_ids, array_flip( (array) $random_keys ) );
	}
}
