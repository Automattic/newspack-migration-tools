<?php
/**
 * Class WordPressXMLHandler
 *
 * @package NewspackCustomContentMigrator\Utils
 */

namespace NewspackCustomContentMigrator\Utils;

use DOMNode;
use WP_CLI;

/**
 * Class WordPressXMLHandler
 *
 * @package NewspackCustomContentMigrator\Utils
 */
class WordPressXMLHandler {

	/**
	 * This function takes an XML node representing an author and returns a WP_User object.
	 * The node is expected to have the structure from a WordPress export file.
	 *
	 * @param DOMNode $author The XML node representing the author.
	 *
	 * @return false|\WP_User
	 * @throws WP_CLI\ExitException If there was an error creating the user.
	 */
	public static function get_or_create_author( DOMNode $author ) {
		$author_data = [
			'user_login'   => '',
			'user_email'   => '',
			'display_name' => '',
			'first_name'   => '',
			'last_name'    => '',
			'role'         => 'author',
			'user_pass'    => wp_generate_password(),
		];

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		foreach ( $author->childNodes as $node ) {
			/*
			@var DOMNode $node */
			$node_name = $node->nodeName;

			switch ( $node_name ) {
				case 'wp:author_login':
					$author_data['user_login'] = $node->nodeValue;
					break;
				case 'wp:author_email':
					$author_data['user_email'] = $node->nodeValue;
					break;
				case 'wp:author_display_name':
					$author_data['display_name'] = $node->nodeValue;
					break;
				case 'wp:author_first_name':
					$author_data['first_name'] = $node->nodeValue;
					break;
				case 'wp:author_last_name':
					$author_data['last_name'] = $node->nodeValue;
					break;
			}
		}

		$user = get_user_by( 'login', $author_data['user_login'] );

		if ( false === $user ) {
			// Try email.
			$user = get_user_by( 'email', $author_data['user_email'] );
		}

		if ( false === $user ) {
			$user_id = wp_insert_user( $author_data );

			if ( is_wp_error( $user_id ) ) {
				print_r( $author_data );
				WP_CLI::error( $user_id->get_error_message() );
			}

			$user = get_user_by( 'id', $user_id );
		}
		//phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		return $user;
	}

	/**
	 * This function will handle an XML node representing a post/page/attachment and return an array consisting
	 * of objects and data that could be used to create a post/page/attachment and meta data.
	 *
	 * @param DOMNode $item   The XML node representing the post/page/attachment.
	 * @param array   $authors An array of existing authors.
	 *
	 * @return array
	 */
	public static function get_parsed_data( DOMNode $item, array $authors ): array {
		$post_data  = [
			'post_type' => '',
			'meta'      => [],
		];
		$categories = [];
		$tags       = [];

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		foreach ( $item->childNodes as $node ) {
			/* @var \DOMNode $node */

			if ( 'wp:post_id' === $node->nodeName ) {
				$post_data['ID'] = $node->nodeValue;
			}

			if ( 'title' === $node->nodeName ) {
				$post_data['post_title'] = $node->nodeValue;
			}

			if ( 'dc:creator' === $node->nodeName ) {
				$post_data['post_author'] = $authors[ $node->nodeValue ]->ID ?? 0;
			}

			if ( 'link' === $node->nodeName ) {
				$post_data['guid'] = $node->nodeValue;
			}

			if ( 'wp:post_type' === $node->nodeName ) {
				$post_data['post_type'] = $node->nodeValue;
			}

			if ( 'content:encoded' === $node->nodeName ) {
				$post_data['post_content'] = $node->nodeValue;
			}

			if ( 'excerpt:encoded' === $node->nodeName ) {
				$post_data['post_excerpt'] = $node->nodeValue;
			}

			if ( 'wp:post_date' === $node->nodeName ) {
				$post_data['post_date'] = $node->nodeValue;
			}

			if ( 'wp:post_date_gmt' === $node->nodeName ) {
				$post_data['post_date_gmt'] = $node->nodeValue;
			}

			if ( 'wp:post_modified' === $node->nodeName ) {
				$post_data['post_modified'] = $node->nodeValue;
			}

			if ( 'wp:post_modified_gmt' === $node->nodeName ) {
				$post_data['post_modified_gmt'] = $node->nodeValue;
			}

			if ( 'wp:comment_status' === $node->nodeName ) {
				$post_data['comment_status'] = $node->nodeValue;
			}

			if ( 'wp:ping_status' === $node->nodeName ) {
				$post_data['ping_status'] = $node->nodeValue;
			}

			if ( 'wp:status' === $node->nodeName ) {
				$post_data['post_status'] = $node->nodeValue;
			}

			if ( 'wp:post_name' === $node->nodeName ) {
				$post_data['post_name'] = $node->nodeValue;
			}

			if ( 'wp:post_parent' === $node->nodeName ) {
				$post_data['post_parent'] = $node->nodeValue;
			}

			if ( 'wp:menu_order' === $node->nodeName ) {
				$post_data['menu_order'] = $node->nodeValue;
			}

			if ( 'wp:post_password' === $node->nodeName ) {
				$post_data['post_password'] = $node->nodeValue;
			}

			if ( 'wp:attachment_url' === $node->nodeName ) {
				$post_data['attachment_url'] = $node->nodeValue;
			}

			if ( 'wp:postmeta' === $node->nodeName ) {
				$meta_key   = $node->childNodes->item( 1 )->nodeValue;
				$meta_value = trim( $node->childNodes->item( 3 )->nodeValue );

				if ( empty( $meta_value ) || str_starts_with( $meta_value, 'field_' ) ) {
					continue;
				}

				$post_data['meta'][] = [
					'meta_key'   => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $meta_value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				];
			}

			if ( 'category' === $node->nodeName ) {
				if ( $node->attributes->getNamedItem( 'domain' )->nodeValue === 'category' ) {
					$categories[] = [
						'type'   => 'category',
						'name'   => htmlspecialchars_decode( $node->nodeValue ),
						'slug'   => $node->attributes->getNamedItem( 'nicename' )->nodeValue,
						'parent' => 0,
					];
				} elseif ( $node->attributes->getNamedItem( 'domain' )->nodeValue === 'post_tag' ) {
					$tags[] = [
						'type' => 'post_tag',
						'name' => htmlspecialchars_decode( $node->nodeValue ),
						'slug' => $node->attributes->getNamedItem( 'nicename' )->nodeValue,
					];
				}
			}
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		return [
			'post'       => $post_data,
			'categories' => $categories,
			'tags'       => $tags,
		];
	}
}
