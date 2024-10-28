<?php
/**
 * Logic for working with Newspack Sponsors
 *
 * @package NewspackCustomContentMigrator
 */

namespace NewspackCustomContentMigrator\Logic;

use NewspackCustomContentMigrator\Utils\Logger;

class Sponsors {

	/**
	 * @var string Sposnors Post Type.
	 */
	const SPONSORS_POST_TYPE = 'newspack_spnsrs_cpt';

	/**
	 * @var string Sponsors Taxonomy.
	 */
	const SPONSORS_TAXONOMY = 'newspack_spnsrs_tax';

	/**
	 * @var Logger.
	 */
	private $logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger = new Logger();
	}

	/**
	 * Assign a sponsor to a post
	 *
	 * @param int $sponsor ID of the sponsor post
	 * @param int $post    ID of the post to be sponsored
	 *
	 * @return bool True is successful, false on failure.
	 */
	public function add_sponsor_to_post( $sponsor, $post ) {

		// Make sure we have a sponsor post.
		$sponsor_post = get_post( $sponsor );
		if ( ! is_a( $sponsor_post, 'WP_Post' ) ) {
			$this->logger->log( 'sponsors', sprintf( 'No sponsor found with ID %d', $sponsor ), false );
			return false;
		}

		// Check it's definitely a sponsor.
		if ( $sponsor_post->post_type !== self::SPONSORS_POST_TYPE ) {
			$this->logger->log( 'sponsors', sprintf( 'Post ID %d is not a sponsor!', $sponsor ), false );
			return false;
		}

		// Make sure the target post exists, too.
		$target_post = get_post( $post );
		if ( ! is_a( $target_post, 'WP_Post' ) ) {
			$this->logger->log( 'sponsors', sprintf( 'No target post found with ID %d', $sponsor ), false );
			return false;
		}

		// Get the sponsor term.
		$sponsor_term = get_term_by( 'name', $sponsor_post->post_title, self::SPONSORS_TAXONOMY );
		if ( ! is_a( $sponsor_term, 'WP_Term' ) ) {
			$this->logger->log( 'sponsors', sprintf( 'No sponsor term found for sponsor %s', $sponsor_post->post_title ), false );
			return false;
		}

		// Add the Sponsor term to the target post.
		$add_terms = wp_set_object_terms( $target_post->ID, $sponsor_term->term_id, self::SPONSORS_TAXONOMY, true );
		if ( is_wp_error( $add_terms ) ) {
			$this->logger->log( 'sponsors', sprintf(
				'Failed to add sponsor term to post %d because %s',
				$target_post->ID,
				$add_terms->get_error_message()
			), false );
			return false;
		}

		clean_post_cache( $sponsor_post->ID );

		return true;

	}
}
