<?php

namespace Newspack\MigrationTools\Logic;

use WP_Error;
use WP_Post;

class Newsletters {
	/**
	 * The meta key used to store the import post ID.
	 * 
	 * @var string
	 */
	const META_KEY_SOURCE_ID = '_nmt_source_id';

	/**
	 * The Newsletter CPT.
	 * 
	 * @var string
	 */
	const NEWSLETTER_POST_TYPE = 'newspack_nl_cpt';

	/**
	 * The Newsletter Layout CPT.
	 * 
	 * @var string
	 */
	const NEWSLETTER_LAYOUT_POST_TYPE = 'newspack_nl_layo_cpt';

	/**
	 * Fetches all Newsletters.
	 *
	 * @param  array $post_status The Post Statuses to fetch.
	 * 						      Defaults to 'publish', 'draft', and 'trash'.
	 * @return array<int, WP_Post>
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

	/**
	 * Fetches all Newsletters layouts.
	 *
	 * @return array<int, WP_Post>
	 */
	public function get_all_newsletter_layouts( $post_status = [ 'any' ] ): array {
		return get_posts(
			[
				'posts_per_page' => -1,
				'post_type'      => [ self::NEWSLETTER_LAYOUT_POST_TYPE ],
				'post_status'    => $post_status,
			] 
		);
	}

	/**
	 * Exports Newsletters to a JSON file.
	 * 
	 * @return void
	 */
	public function export_newsletters(): void {
		$newsletters = $this->get_all_newsletters();
		if ( empty( $newsletters ) ) {
			return;
		}

		$export_data = [];
		foreach ( $newsletters as $newsletter ) {
			$export_data[] = [
				'post'        => $newsletter,
				'post_meta'   => get_post_meta( $newsletter->ID ),
				'category'    => get_the_terms( $newsletter->ID, 'category' ),
				'post_tag'    => get_the_terms( $newsletter->ID, 'post_tag' ),
				'post_author' => [
					'user'      => get_user_by( 'ID', $newsletter->post_author ),
					'user_meta' => get_user_meta( $newsletter->post_author ),
				]
			];
		}

		file_put_contents( 'newsletters.json', wp_json_encode( $export_data, JSON_PRETTY_PRINT ) );
	}

	/**
	 * Exports Newsletter layouts to a JSON file.
	 * 
	 * @return void
	 */
	public function export_newsletter_layouts(): void {
		$newsletter_layouts = $this->get_all_newsletter_layouts();
		if ( empty( $newsletter_layouts ) ) {
			return;
		}

		$export_data = [];
		foreach ( $newsletter_layouts as $newsletter_layout ) {
			$export_data[] = [
				'post'        => $newsletter_layout,
				'post_meta'   => get_post_meta( $newsletter_layout->ID ),
				'post_author' => [
					'user'      => get_user_by( 'ID', $newsletter_layout->post_author ),
					'user_meta' => get_user_meta( $newsletter_layout->post_author ),
				]
			];
		}

		file_put_contents( 'newsletter-layouts.json', wp_json_encode( $export_data, JSON_PRETTY_PRINT ) );
	}

	/**
	 * Import a Newsletter layout.
	 * 
	 * @return int The ID of the Newsletter layout.
	 */
	public function import_newsletter_layout( $newsletter_layout ): int|WP_Error {
		$newsletter_layout_id = $this->_import_post( $newsletter_layout );

		return $newsletter_layout_id;
	}

	/**
	 * Import a Newsletter.
	 * 
	 * @return int The ID of the Newsletter.
	 */
	public function import_newsletter( $newsletter ): int|WP_Error {
		$newsletter_id = $this->_import_post( $newsletter );

		if ( is_wp_error( $newsletter_id ) ) {
			return $newsletter_id;
		}

		global $wpdb;

		// Replace reference to Newsletter Layout.
		$template_id     = get_post_meta( $newsletter_id, 'template_id', true );
		$new_template_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `post_id`
				FROM `{$wpdb->postmeta}`
				WHERE `meta_key` = %s
				AND `meta_value` = %s",
				self::META_KEY_SOURCE_ID,
				$template_id
			)
		);

		if ( $new_template_id ) {
			update_post_meta( $newsletter_id, 'template_id', $new_template_id, $template_id );
		}

		// Replace tracking pixel.
		$search      = sprintf( '?np_newsletters_click=1&id=%d', $newsletter->post->ID );
		$replacement = sprintf( '?np_newsletters_click=1&id=%d', $newsletter_id );
		
		// Replace tracking pixel in post_content.
		$post_content         = get_post_field( 'post_content', $newsletter_id );
		$post_content_updated = str_replace( $search, $replacement, $post_content );
		if ( $post_content !== $post_content_updated ) {
			$wpdb->update(
				$wpdb->posts,
				[
					'post_content' => $post_content_updated,
				],
				[
					'ID' => $newsletter_id,
				]
			);
		}

		// Replace tracking pixel in newspack_email_html meta_key.
		$newspack_email_html         = get_post_meta( $newsletter_id, 'newspack_email_html', true );
		$newspack_email_html_updated = str_replace( $search, $replacement, $newspack_email_html );

		if ( $newspack_email_html !== $newspack_email_html_updated ) {
			update_post_meta( $newsletter_id, 'newspack_email_html', $newspack_email_html_updated, $newspack_email_html );
		}

		delete_post_meta( $newsletter_id, 'sending_scheduled' ); // Remove sending_scheduled meta, so that old Newsletters are not sent again.

		return $newsletter_id;
	}

	/**
	 * Import a newsletter related post.
	 * 
	 * @return int The ID of the inserted post.
	 */
	private function _import_post( $post ): int|WP_Error {
		// Search for existing post.
		$existing_post = get_post( $post->post->ID );
		
		if (
			$existing_post
			&& $existing_post->post_type == $post->post->post_type
			&& $existing_post->post_name == $post->post->post_name
			&& $existing_post->post_title == $post->post->post_title
			&& $existing_post->post_status == $post->post->post_status
			&& $existing_post->post_date == $post->post->post_date
			&& $existing_post->post_modified == $post->post->post_modified
		) {
			return $existing_post->ID;
		}

		// Search for existing post by import meta.
		global $wpdb;
		$post_id_new = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `post_id`
				 FROM `{$wpdb->postmeta}`
				 WHERE `meta_key` = %s
				 AND `meta_value` = %s",
				self::META_KEY_SOURCE_ID,
				$post->post->ID
			)
		);

		if ( $post_id_new ) {
			return (int) $post_id_new;
		}

		// Otherwise, import as new post.
		$post_data = (array) clone $post->post;
		unset( $post_data['ID'] );
		unset( $post_data['filter'] );

		$inserted = $wpdb->insert( $wpdb->posts, $post_data );

		if ( 1 != $inserted ) {
			return new WP_Error(
				'nmt_newsletters_import_failed',
				'Failed to import Newsletter Post',
				[
					'newsletter_layout' => $post,
				]
			);
		}

		$post_id = $wpdb->insert_id;

		// Insert Post Meta.
		foreach ( $post->post_meta as $meta_key => $meta_value ) {
			foreach ( $meta_value as $value ) {
				add_post_meta( $post_id, $meta_key, $value );
			}
		}

		// Append Post Categories.
		if ( isset( $post->category ) && is_array( $post->category ) && ! empty( $post->category ) ) {
			foreach ( $post->category as $category ) {
				$category_term = get_term_by( 'name', $category->name, 'category' );

				if ( $category_term ) {
					wp_set_post_terms( $post_id, [ $category_term->term_id ], 'category', true );
				}
			}
		}

		// Append Post Tags.
		if ( isset( $post->post_tag ) && is_array( $post->post_tag ) && ! empty( $post->post_tag ) ) {
			foreach ( $post->post_tag as $post_tag ) {
				$post_tag_term = get_term_by( 'name', $post_tag->name, 'post_tag' );

				if ( $post_tag_term ) {
					wp_set_post_terms( $post_id, [ $post_tag_term->term_id ], 'post_tag', true );
				}
			}
		}

		// Append Post Author.
		// @todo

		// Set import ID meta.
		update_post_meta( $post_id, self::META_KEY_SOURCE_ID, $post->post->ID );

		return $post_id;
	}
}
