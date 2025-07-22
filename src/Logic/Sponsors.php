<?php
/**
 * Logic for working with Newspack Sponsors
 */

namespace Newspack\MigrationTools\Logic;

use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\Log\MultiLog;
use Psr\Log\LoggerInterface;

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
	 * @var LoggerInterface.
	 */
	private $logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger = MultiLog::get_logger(
			'Sponsor-multi',
			[
				CliLog::get_logger( 'Sponsors' ),
				FileLog::get_logger( 'Sponsors', 'sponsors.log' ),
			]
		);
	}

	/**
	 * Assign a sponsor to a post
	 *
	 * @param int $sponsor ID of the sponsor post.
	 * @param int $post    ID of the post to be sponsored.
	 *
	 * @return bool True is successful, false on failure.
	 */
	public function add_sponsor_to_post( $sponsor, $post ) {

		// Make sure we have a sponsor post.
		$sponsor_post = get_post( $sponsor );
		if ( ! is_a( $sponsor_post, 'WP_Post' ) ) {
			$this->logger->error( sprintf( 'No sponsor found with ID %d', $sponsor ) );

			return false;
		}

		// Check it's definitely a sponsor.
		if ( self::SPONSORS_POST_TYPE !== $sponsor_post->post_type ) {
			$this->logger->error( sprintf( 'Post ID %d is not a sponsor!', $sponsor ) );

			return false;
		}

		// Make sure the target post exists, too.
		$target_post = get_post( $post );
		if ( ! is_a( $target_post, 'WP_Post' ) ) {
			$this->logger->error( sprintf( 'No target post found with ID %d', $sponsor ) );

			return false;
		}

		// Get the sponsor term.
		$sponsor_term = get_term_by( 'name', $sponsor_post->post_title, self::SPONSORS_TAXONOMY );
		if ( ! is_a( $sponsor_term, 'WP_Term' ) ) {
			$this->logger->error( sprintf( 'No sponsor term found for sponsor %s', $sponsor_post->post_title ) );

			return false;
		}

		// Add the Sponsor term to the target post.
		$add_terms = wp_set_object_terms( $target_post->ID, $sponsor_term->term_id, self::SPONSORS_TAXONOMY, true );
		if ( is_wp_error( $add_terms ) ) {
			$this->logger->error(
				sprintf(
					'Failed to add sponsor term to post %d because %s',
					$target_post->ID,
					$add_terms->get_error_message()
				),
			);

			return false;
		}

		clean_post_cache( $sponsor_post->ID );

		return true;
	}

	/**
	 * Get or add a sponsor by name
	 *
	 * @param string $sponsor_name The name of the sponsor.
	 * @param array  $sponsor_data Optional array of sponsor data to set when creating a new sponsor.
	 *                              Supported fields: 'content', 'url', 'byline_prefix', 'flag_override', 'disclaimer_override', 'sponsorship_scope'.
	 *
	 * @return int|false The sponsor post ID if successful, false on failure.
	 */
	public function get_or_add_sponsor( $sponsor_name, $sponsor_data = [] ) {
		if ( empty( $sponsor_name ) ) {
			$this->logger->error( 'Sponsor name cannot be empty' );
			return false;
		}

		// Sanitize the sponsor name
		$sponsor_name = sanitize_text_field( $sponsor_name );

		// Try to find existing sponsor by name
		$existing_sponsor = get_page_by_title( $sponsor_name, OBJECT, self::SPONSORS_POST_TYPE );

		if ( $existing_sponsor ) {
			$this->logger->info( sprintf( 'Found existing sponsor: %s (ID: %d)', $sponsor_name, $existing_sponsor->ID ) );
			return $existing_sponsor->ID;
		}

		// Create new sponsor post
		$sponsor_post_data = [
			'post_title'   => $sponsor_name,
			'post_name'    => sanitize_title( $sponsor_name ),
			'post_content' => isset( $sponsor_data['content'] ) ? $sponsor_data['content'] : '',
			'post_status'  => 'publish',
			'post_type'    => self::SPONSORS_POST_TYPE,
		];

		$sponsor_id = wp_insert_post( $sponsor_post_data );

		if ( is_wp_error( $sponsor_id ) ) {
			$this->logger->error( sprintf( 'Failed to create sponsor "%s": %s', $sponsor_name, $sponsor_id->get_error_message() ) );
			return false;
		}

		$this->logger->info( sprintf( 'Created new sponsor: %s (ID: %d)', $sponsor_name, $sponsor_id ) );

		// Set sponsor meta fields if provided
		if ( ! empty( $sponsor_data ) ) {
			$this->set_sponsor_meta( $sponsor_id, $sponsor_data );
		}

		// Create the shadow taxonomy term
		$this->create_shadow_term( $sponsor_id );

		return $sponsor_id;
	}

	/**
	 * Set sponsor meta fields
	 *
	 * @param int   $sponsor_id The sponsor post ID.
	 * @param array $meta_data  Array of meta data to set.
	 *
	 * @return bool True if successful, false on failure.
	 */
	private function set_sponsor_meta( $sponsor_id, $meta_data ) {
		$meta_fields = [
			'url'                 => 'newspack_sponsor_url',
			'byline_prefix'       => 'newspack_sponsor_byline_prefix',
			'flag_override'       => 'newspack_sponsor_flag_override',
			'disclaimer_override' => 'newspack_sponsor_disclaimer_override',
			'sponsorship_scope'   => 'newspack_sponsor_sponsorship_scope',
		];

		foreach ( $meta_fields as $data_key => $meta_key ) {
			if ( isset( $meta_data[ $data_key ] ) ) {
				$result = update_post_meta( $sponsor_id, $meta_key, sanitize_text_field( $meta_data[ $data_key ] ) );
				if ( false === $result ) {
					$this->logger->warning( sprintf( 'Failed to set meta field %s for sponsor %d', $meta_key, $sponsor_id ) );
				}
			}
		}

		return true;
	}

	/**
	 * Create shadow taxonomy term for a sponsor
	 *
	 * @param int $sponsor_id The sponsor post ID.
	 *
	 * @return bool True if successful, false on failure.
	 */
	private function create_shadow_term( $sponsor_id ) {
		$sponsor_post = get_post( $sponsor_id );
		if ( ! is_a( $sponsor_post, 'WP_Post' ) || self::SPONSORS_POST_TYPE !== $sponsor_post->post_type ) {
			$this->logger->error( sprintf( 'Invalid sponsor post ID: %d', $sponsor_id ) );
			return false;
		}

		// Check if shadow term already exists
		$existing_term = get_term_by( 'name', $sponsor_post->post_title, self::SPONSORS_TAXONOMY );
		if ( $existing_term ) {
			$this->logger->info( sprintf( 'Shadow term already exists for sponsor %s', $sponsor_post->post_title ) );
			return true;
		}

		// Create new shadow term
		$term_result = wp_insert_term(
			$sponsor_post->post_title,
			self::SPONSORS_TAXONOMY,
			[
				'slug' => $sponsor_post->post_name,
			]
		);

		if ( is_wp_error( $term_result ) ) {
			$this->logger->error( sprintf( 'Failed to create shadow term for sponsor %s: %s', $sponsor_post->post_title, $term_result->get_error_message() ) );
			return false;
		}

		$this->logger->info( sprintf( 'Created shadow term for sponsor %s (term ID: %d)', $sponsor_post->post_title, $term_result['term_id'] ) );
		return true;
	}
}
