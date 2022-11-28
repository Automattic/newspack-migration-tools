<?php

namespace NewspackCustomContentMigrator\Logic;

class Ads {

	/**
	 * @var string Ad Unit Post Type.
	 */
	const ADS_POST_TYPE = 'newspack_ad_codes';

	/**
	 * Fetches all Ad Units.
	 *
	 * @return int[]|\WP_Post[]
	 */
	public function get_all_ad_units( $post_status = [ 'publish', 'draft' ] ) {
		return get_posts( [
			'posts_per_page' => -1,
			'post_type'      => [ self::ADS_POST_TYPE ],
			'post_status'    => $post_status
		] );
	}
}
