<?php

namespace Newspack\MigrationTools\Logic;

class Newsletters {

	/**
	 * @var string Newsletter Post Type.
	 */
	const NEWSLETTER_POST_TYPE = 'newspack_nl_cpt';

	/**
	 * Fetches all Newsletters.
	 *
	 * @return int[]|\WP_Post[]
	 */
	public function get_all_newsletters( $post_status = [ 'publish', 'draft', 'trash' ] ): array {
		return get_posts(
			[
				'posts_per_page' => -1,
				'post_type'      => [ self::NEWSLETTER_POST_TYPE ],
				'post_status'    => $post_status,
			] 
		);
	}
}
