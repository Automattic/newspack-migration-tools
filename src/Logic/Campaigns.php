<?php

namespace NewspackCustomContentMigrator\Logic;

class Campaigns {

	/**
	 * @var string Campaign Post Type.
	 */
	const CAMPAIGNS_POST_TYPE = 'newspack_popups_cpt';

	/**
	 * Fetches all Campaigns.
	 *
	 * @return int[]|\WP_Post[]
	 */
	public function get_all_campaigns( $post_status = [ 'publish', 'draft' ] ) {
		return get_posts( [
			'posts_per_page' => -1,
			'post_type'      => [ self::CAMPAIGNS_POST_TYPE ],
			'post_status'    => $post_status
		] );
	}
}
