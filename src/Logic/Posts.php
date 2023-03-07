<?php

namespace Newspack\MigrationTools\Logic;

use WP_CLI;

/**
 * Posts logic class.
 */
class Posts {
	/**
	 * @param string|array $post_type   Post type(s).
	 * @param array        $post_status Post statuses.
	 * @param boolean      $nopaging    Deprecated usage, optional param kept for backward compatibility. Always defaults to true.
	 *
	 * @return array
	 */
	public function get_all_posts_ids( $post_type = 'post', $post_status = [ 'publish', 'future', 'draft', 'pending', 'private', 'inherit' ], $nopaging = true ) {
		global $wpdb;

		// Create $post_type statement placeholders.
		if ( ! is_array( $post_type ) ) {
			$post_type = [ $post_type ];
		}
		$post_type_placeholders = implode( ',', array_fill( 0, count( $post_type ), '%s' ) );

		// Create $post_status statement placeholders.
		$post_status_placeholders = implode( ',', array_fill( 0, count( $post_status ), '%s' ) );

		// phpcs:disable -- used wpdb's prepare() and placeholders.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"select ID
			from {$wpdb->posts}
			where post_type IN ( $post_type_placeholders )
			and post_status IN ( $post_status_placeholders ) ;",
				array_merge( $post_type, $post_status )
			)
		);
		// phpcs:enable

		return $ids;
	}

	/**
	 * This function conveniently returns all post IDs in a given range.
	 *
	 * @param int          $from_id From Post ID.
	 * @param int          $to_id  To Post ID.
	 * @param array        $post_types Target Post Types.
	 * @param string|array $post_status Target Post Statuses.
	 *
	 * @return array
	 */
	public function get_post_ids_in_range( int $from_id, int $to_id, array $post_types, $post_status = '' ): array {
		if ( empty( $post_status ) ) {
			$post_status = [ 'publish', 'future', 'draft', 'pending', 'private', 'inherit' ];
		}
		global $wpdb;

		$post_type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

		// Create $post_status statement placeholders.
		$post_status_placeholders = implode( ',', array_fill( 0, count( $post_status ), '%s' ) );

		// phpcs:disable -- used wpdb's prepare() and placeholders.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID
			FROM {$wpdb->posts}
			WHERE ID BETWEEN %d AND %d
			AND post_type IN ( $post_type_placeholders )
			AND post_status IN ( $post_status_placeholders )",
				$from_id,
				$to_id,
				...$post_types,
				...$post_status
			)
		);
		// phpcs:enable

		return $ids;
	}

	/**
	 * Returns all Post IDs in a given category.
	 *
	 * @param int          $category_term_id Category ID.
	 * @param string|array $post_type        Post type(s).
	 * @param array        $post_status      Post statuses.
	 *
	 * @return array
	 */
	public function get_all_posts_ids_in_category( $category_term_id, $post_type = 'post', $post_status = [ 'publish', 'future', 'draft', 'pending', 'private', 'inherit' ] ) {
		global $wpdb;

		// Create $post_type statement placeholders.
		if ( ! is_array( $post_type ) ) {
			$post_type = [ $post_type ];
		}

		// Placeholders.
		$post_type_placeholders   = implode( ',', array_fill( 0, count( $post_type ), '%s' ) );
		$post_status_placeholders = implode( ',', array_fill( 0, count( $post_status ), '%s' ) );

		// Get term_taxonomy_id.
		$term_taxonomy_id = $wpdb->get_var(
			$wpdb->prepare(
				"select term_taxonomy_id from {$wpdb->term_taxonomy} where term_id = %d;",
				$category_term_id
			)
		);
		if ( ! $term_taxonomy_id ) {
			throw new \UnexpectedValueException( sprintf( 'Term taxonomy ID not found for term_id %s.', $category_term_id ) );
		}

		// phpcs:disable -- used wpdb's prepare() and placeholders.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"select wp.ID
				from {$wpdb->posts} wp
				join {$wpdb->term_relationships} wtr on wtr.object_id = wp.ID
				where wtr.term_taxonomy_id = %d
			    and wp.post_type IN ( $post_type_placeholders )
				and wp.post_status IN ( $post_status_placeholders ) ;",
				array_merge( [ $term_taxonomy_id ], $post_type, $post_status )
			)
		);
		// phpcs:enable

		return $ids;
	}

	/**

	 * Gets IDs of all the Posts.
	 * Uses WP_Query::get_posts. If $nopaging=true is used, might break on larger DBs on Atomic. Deprecation notice below.
	 *
	 * @deprecated May break on large DBs on Atomic due to Atomic resource restrictions, replaced by self::get_all_posts_ids().
	 * @param string|array $post_type   $post_type argument for \WP_Query::get_posts.
	 * @param array        $post_status $post_status argument for \WP_Query::get_posts.
	 * @param boolean      $nopaging    $nopaging argument for \WP_Query::get_posts.
	 *
	 * @return array Post IDs.
	 */
	public function get_all_posts_ids_old( $post_type = 'post', $post_status = [ 'publish', 'future', 'draft', 'pending', 'private', 'inherit' ], $nopaging = true ) {
		$ids = array();

		// Arguments in \WP_Query::parse_query .
		$args  = array(
			'nopaging'    => $nopaging,
			'post_type'   => $post_type,
			'post_status' => $post_status,
		);
		$query = new \WP_Query( $args );
		$posts = $query->get_posts();
		if ( ! empty( $posts ) ) {
			foreach ( $posts as $post ) {
				$ids[] = $post->ID;
			}
		}

		return $ids;
	}

	/**
	 * Gets posts which have tags with taxonomy.
	 *
	 * @param string $tag_taxonomy Tag taxonomy.
	 *
	 * @return array Array of post IDs found.
	 */
	public function get_posts_with_tag_with_taxonomy( $tag_taxonomy ) {
		global $wpdb;
		$post_ids = [];

		// TODO: switch to WP_Query instead of raw SQL ( e.g. if ( ! taxonomy_exists( $tag ) ) register_taxonomy( $tag, $object_type ) ).
		$sql_get_post_ids_with_taxonomy = <<<SQL
			SELECT DISTINCT wp.ID
			FROM {$wpdb->prefix}posts wp
			JOIN {$wpdb->prefix}term_relationships wtr ON wtr.object_id = wp.ID
			JOIN {$wpdb->prefix}term_taxonomy wtt ON wtt.term_taxonomy_id = wtr.term_taxonomy_id AND wtt.taxonomy = %s
			JOIN {$wpdb->prefix}terms wt ON wt.term_id = wtt.term_id
			WHERE wp.post_type = 'post'
			AND wp.post_status = 'publish'
			ORDER BY wp.ID;
SQL;
		// phpcs:ignore -- false positive, all params are fully sanitized.
		$results_post_ids               = $wpdb->get_results( $wpdb->prepare( $sql_get_post_ids_with_taxonomy, $tag_taxonomy ), ARRAY_A );

		if ( ! empty( $results_post_ids ) ) {
			foreach ( $results_post_ids as $result_post_id ) {
				$post_ids[] = $result_post_id['ID'];
			}
		}

		return $post_ids;
	}

	/**
	 * For a post ID, gets tags which have the given taxonomy.
	 *
	 * @param int    $post_id         Post ID.
	 * @param string $tag_taxonomy Tag tagxonomy.
	 *
	 * @return array Tag names with given taxonomy which this post has.
	 */
	public function get_post_tags_with_taxonomy( $post_id, $tag_taxonomy ) {
		global $wpdb;
		$names = [];

		// TODO: switch to WP_Query instead of raw SQL ( e.g. if ( ! taxonomy_exists( $tag ) ) register_taxonomy( $tag, $object_type ) ).
		$sql_get_post_ids_with_taxonomy = <<<SQL
			SELECT DISTINCT wt.name
			FROM {$wpdb->prefix}terms wt
			JOIN {$wpdb->prefix}term_taxonomy wtt ON wtt.taxonomy = %s AND wtt.term_id = wt.term_id
			JOIN {$wpdb->prefix}term_relationships wtr ON wtt.term_taxonomy_id = wtr.term_taxonomy_id
			JOIN {$wpdb->prefix}posts wp ON wp.ID = wtr.object_id AND wp.ID = %d
			WHERE wp.post_type = 'post'
			AND wp.post_status = 'publish'
SQL;
		// phpcs:ignore -- false positive, all params are fully sanitized.
		$results_names                  = $wpdb->get_results( $wpdb->prepare( $sql_get_post_ids_with_taxonomy, $tag_taxonomy, $post_id ), ARRAY_A );
		if ( ! empty( $results_names ) ) {
			foreach ( $results_names as $results_name ) {
				$names[] = $results_name['name'];
			}
		}

		return $names;
	}

	/**
	 * Returns a list of all `post_type`s defined in the posts DB table.
	 *
	 * @return array Post types.
	 */
	public function get_all_post_types() {
		global $wpdb;

		$post_types = [];
		// phpcs:ignore
		$results    = $wpdb->get_results( "SELECT DISTINCT post_type FROM {$wpdb->posts}" );
		foreach ( $results as $result ) {
			$post_types[] = $result->post_type;
		}

		return $post_types;
	}

	/**
	 * Gets all post objects with taxonomy and term.
	 * In order for this function to work, the Taxonomy must be registered on all post types, e.g. like this:
	 * ```
	 *      if ( ! taxonomy_exists( $taxonomy ) ) {
	 *          register_taxonomy( $taxonomy, 'any' );
	 *      }
	 * ```
	 *
	 * @param string $taxonomy   Taxonomy.
	 * @param int    $term_id    term_id.
	 * @param array  $post_types Post types.
	 *
	 * @return \WP_Post[]
	 */
	public function get_post_objects_with_taxonomy_and_term( $taxonomy, $term_id, $post_types = array( 'post', 'page' ) ) {
		return get_posts(
			[
				'posts_per_page' => -1,
				// Target all post_types.
				'post_type'      => $post_types,
				'tax_query'      => [
					[
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $term_id,
					],
				],
			]
		);
	}

	/**
	 * Gets taxonomy with custom meta.
	 *
	 * @param string $meta_key   Meta key.
	 * @param string $meta_value Meta value.
	 * @param string $taxonomy   Taxonomy.
	 *
	 * @return int|\WP_Error|\WP_Term[]
	 */
	public function get_terms_with_meta( $meta_key, $meta_value, $taxonomy = 'category' ) {
		return get_terms(
			[
				'hide_empty' => false,
				'meta_query' => [
					[
						'key'     => $meta_key,
						'value'   => $meta_value,
						'compare' => 'LIKE',
					],
				],
				'taxonomy'   => $taxonomy,
			]
		);
	}

	/**
	 * Gets all Posts which has the meta key and value.
	 *
	 * @param string $meta_key   Meta key.
	 * @param string $meta_value Meta value.
	 * @param array  $post_types Post types.
	 *
	 * @return array|null
	 */
	public function get_posts_with_meta_key_and_value( $meta_key, $meta_value, $post_types = [ 'post', 'page' ] ) {
		if ( empty( $meta_key ) || empty( $meta_value ) || empty( $post_types ) ) {
			return null;
		}

		global $wpdb;

		$post_types_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

		$args_prepare = [];
		array_push( $args_prepare, $meta_key, $meta_value );
		foreach ( $post_types as $post_type ) {
			array_push( $args_prepare, $post_type );
		}

		// phpcs:disable -- Used wpdb's prepare() and safely sanitized placeholders.
		$results_meta_post_ids = $wpdb->get_results(
			$wpdb->prepare(
				"select post_id from {$wpdb->prefix}postmeta pm
				join {$wpdb->prefix}posts p on p.id = pm.post_id
				where pm.meta_key = %s and pm.meta_value = %s
			    and p.post_type in ( $post_types_placeholders ); ",
				$args_prepare
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( empty( $results_meta_post_ids ) ) {
			return [];
		}

		$post_ids = [];
		foreach ( $results_meta_post_ids as $result_meta_post_id ) {
			$post_ids[] = (int) $result_meta_post_id['post_id'];
		}

		return $post_ids;
	}

	/**
	 * Returns IDs of all existing categories, both parents and children.
	 *
	 * @return array
	 */
	public function get_all_existing_categories() {
		$cats_ids_all = [];
		$cats_parents = get_categories( [ 'hide_empty' => false ] );
		foreach ( $cats_parents as $cat_parent ) {
			$cats_ids_all[] = $cat_parent->term_id;
			$cats_children  = get_categories(
				[
					'parent'     => $cat_parent->term_id,
					'hide_empty' => false,
				]
			);
			if ( empty( $cats_children ) ) {
				continue;
			}

			foreach ( $cats_children as $cat_child ) {
				$cats_ids_all[] = $cat_child->term_id;
			}
		}
		$cats_ids_all = array_unique( $cats_ids_all );

		return $cats_ids_all;
	}

	/**
	 * Returns all posts' IDs.
	 * Warning -- might break on Atomic with larger DBs due to resource restrictions. Try using self::get_all_posts_ids() instead.
	 *
	 * @param string|array $post_type   Post type.
	 * @param array        $post_status Post status.
	 *
	 * @return int[]|\WP_Post[]
	 */
	public function get_all_posts( $post_type = 'post', $post_status = array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ) ) {
		return get_posts(
			array(
				'posts_per_page' => -1,
				'post_type'      => $post_type,
				// `'post_status' => 'any'` doesn't work as expected.
				'post_status'    => $post_status,
			)
		);
	}

	/**
	 * Batch posts and execute a callback action on each one, with a wait time between the batches.
	 *
	 * @param array    $query_args Arguments to retrieve posts, the same as the ones for get_posts function.
	 * @param callable $callback The callback function to execute on each post, get the post as parameter.
	 * @param integer  $wait The waiting time between batches in seconds.
	 * @param integer  $posts_per_batch Total of posts tohandle per batch.
	 * @param integer  $batch Current batch in the loop.
	 * @return void
	 */
	public function throttled_posts_loop( $query_args, $callback, $wait = 3, $posts_per_batch = 1000, $batch = 1 ) {
		WP_CLI::line( sprintf( 'Batch #%d', $batch ) );

		$args = array_merge(
			array(
				'posts_per_page' => $posts_per_batch,
				'paged'          => $batch,
				'no_found_rows'  => true,
			),
			$query_args
		);

		$posts = get_posts( $args );

		if ( empty( $posts ) ) {
			return;
		}

		foreach ( $posts as $post ) {
			$callback( $post );
		}

		sleep( $wait );

		self::throttled_posts_loop( $query_args, $callback, $wait, $posts_per_batch, $batch + 1 );
	}

	/**
	 * Generate Newspack Iframe Block code from HTML
	 * By Creating an HTML file and uploading it to be set as the iframe source.
	 *
	 * @param string $html_to_embed HTML code to embed.
	 * @param int    $post_id Post ID where to embed the HTML, used to generate unique Iframe source filename.
	 * @return string Iframe block code to be add to the post content.
	 */
	public function embed_iframe_block_from_html( $html_to_embed, $post_id ) {
		$iframe_folder      = "iframe-$post_id-" . wp_generate_password( 8, false );
		$wp_upload_dir      = wp_upload_dir();
		$iframe_upload_dir  = '/newspack_iframes/';
		$iframe_upload_path = $wp_upload_dir['path'] . $iframe_upload_dir;
		$iframe_path        = $iframe_upload_path . $iframe_folder;

		// create iframe directory if not existing.
		if ( ! file_exists( $iframe_path ) ) {
			wp_mkdir_p( $iframe_path );
		}

		// Save iframe content in html file.
		file_put_contents( "$iframe_path/index.html", $html_to_embed );
		$iframe_src       = $wp_upload_dir['url'] . $iframe_upload_dir . $iframe_folder . DIRECTORY_SEPARATOR;
		$iframe_directory = path_join( $wp_upload_dir['subdir'] . $iframe_upload_dir, $iframe_folder );

		return '<!-- wp:newspack-blocks/iframe {"src":"' . $iframe_src . '","archiveFolder":"' . $iframe_directory . '"} /-->';
	}

	/**
	 * Generate Newspack Iframe Block code from URL.
	 *
	 * @param string $src Iframe source URL.
	 * @return string Iframe block code to be add to the post content.
	 */
	public function embed_iframe_block_from_src( $src ) {
		return '<!-- wp:newspack-blocks/iframe {"src":"' . $src . '"} /-->';
	}

	/**
	 * Generate Jetpack Slideshow Block code from Media Posts.
	 *
	 * @param int[] $post_ids Media Posts IDs.
	 * @return string Jetpack Slideshow block code to be add to the post content.
	 */
	public function generate_jetpack_slideshow_block_from_media_posts( $post_ids ) {
		$posts = [];
		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$posts[] = get_post( $post_id );
			}
		}

		if ( empty( $posts ) ) {
			return '';
		}

		$content  = '<!-- wp:jetpack/slideshow {"ids":[' . join( ',', $post_ids ) . '],"sizeSlug":"large"} -->';
		$content .= '<div class="wp-block-jetpack-slideshow aligncenter" data-effect="slide"><div class="wp-block-jetpack-slideshow_container swiper-container"><ul class="wp-block-jetpack-slideshow_swiper-wrapper swiper-wrapper">';
		foreach ( $posts as $post ) {
			$caption  = ! empty( $post->post_excerpt ) ? $post->post_excerpt : $post->post_title;
			$content .= '<li class="wp-block-jetpack-slideshow_slide swiper-slide"><figure><img alt="' . $post->post_title . '" class="wp-block-jetpack-slideshow_image wp-image-' . $post->ID . '" data-id="' . $post->ID . '" src="' . wp_get_attachment_url( $post->ID ) . '"/><figcaption class="wp-block-jetpack-slideshow_caption gallery-caption">' . $caption . '</figcaption></figure></li>';
		}
		$content .= '</ul><a class="wp-block-jetpack-slideshow_button-prev swiper-button-prev swiper-button-white" role="button"></a><a class="wp-block-jetpack-slideshow_button-next swiper-button-next swiper-button-white" role="button"></a><a aria-label="Pause Slideshow" class="wp-block-jetpack-slideshow_button-pause" role="button"></a><div class="wp-block-jetpack-slideshow_pagination swiper-pagination swiper-pagination-white"></div></div></div><!-- /wp:jetpack/slideshow -->';
		return $content;
	}

	/**
	 * Generate Jetpack Slideshow Block code from Media Posts.
	 *
	 * @param int[] $images Media Posts IDs.
	 * @return string Jetpack Slideshow block code to be add to the post content.
	 */
	public function generate_jetpack_slideshow_block_from_pictures( $images ) {
		foreach ( $images as $key => $image ) {
			$post          = get_page_by_title( $image['name'], OBJECT, 'attachment' );
			$attachment_id = 0;

			if ( ! $post ) {
				$attachment = array(
					'guid'           => $image['filename'],
					'post_mime_type' => "image/{$image['filetype']}",
					'post_title'     => $image['name'],
					'post_content'   => '',
					'post_status'    => 'inherit',
				);

				$attachment_id = wp_insert_attachment( $attachment, $image['filename'] );
			}

			$images[ $key ]['post_id'] = $post ? $post->ID : $attachment_id;
		}

		$images_posts_ids = array_map(
			function( $image ) {
				return $image['post_id'];
			},
			$images
		);

		$content  = '<!-- wp:jetpack/slideshow {"ids":[' . join( ',', $images_posts_ids ) . '],"sizeSlug":"large"} -->';
		$content .= '<div class="wp-block-jetpack-slideshow aligncenter" data-effect="slide"><div class="wp-block-jetpack-slideshow_container swiper-container"><ul class="wp-block-jetpack-slideshow_swiper-wrapper swiper-wrapper">';
		foreach ( $images as $image ) {
			$caption  = ! empty( $image['description'] ) ? $image['description'] : $image['name'];
			$content .= '<li class="wp-block-jetpack-slideshow_slide swiper-slide"><figure><img alt="' . $image['name'] . '" class="wp-block-jetpack-slideshow_image wp-image-' . $image['post_id'] . '" data-id="' . $image['post_id'] . '" src="' . $image['filename'] . '"/><figcaption class="wp-block-jetpack-slideshow_caption gallery-caption">' . $caption . '</figcaption></figure></li>';
		}
		$content .= '</ul><a class="wp-block-jetpack-slideshow_button-prev swiper-button-prev swiper-button-white" role="button"></a><a class="wp-block-jetpack-slideshow_button-next swiper-button-next swiper-button-white" role="button"></a><a aria-label="Pause Slideshow" class="wp-block-jetpack-slideshow_button-pause" role="button"></a><div class="wp-block-jetpack-slideshow_pagination swiper-pagination swiper-pagination-white"></div></div></div><!-- /wp:jetpack/slideshow -->';
		return $content;
	}

	/**
	 * Gets a list of post IDs and the number of revisions each one has. It will return only posts that have more than $num_of_revisions revisions
	 *
	 * @param integer $num_of_revisions The minimum number of revisions you want to search for.
	 * @return array An array of objects, each object having two keys: post_ID and num_of_revisions.
	 */
	public function get_posts_by_number_of_revisions( $num_of_revisions = 500 ) {
		global $wpdb;
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_parent as post_ID, COUNT(ID) as num_of_revisions
					FROM $wpdb->posts WHERE post_type = 'revision'
					GROUP BY post_parent
					HAVING COUNT(ID) > %d",
				$num_of_revisions
			)
		);
		return $results;
	}

	/**
	 * Gets the number of revisions a post has
	 *
	 * @param integer $post_id The post ID.
	 * @return integer
	 */
	public function get_post_number_of_revisions( $post_id ) {
		global $wpdb;
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = 'revision' AND post_parent = %d",
				$post_id
			)
		);
		return (int) $result;
	}

	/**
	 * Deletes revisions from a post, from the oldest to the newest
	 *
	 * @param integer $post_id The post ID.
	 * @param integer $chunk_size The number of revisions to be deleted.
	 * @return integer|false The number of revisions deleted or false on failure.
	 */
	public function delete_post_revisions( $post_id, $chunk_size ) {
		global $wpdb;
		$ids          = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_type = 'revision' AND post_parent = %d ORDER BY post_date ASC LIMIT %d",
				$post_id,
				$chunk_size
			)
		);
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$deleted      = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->posts WHERE ID IN ($placeholders)", // phpcs:ignore
				$ids
			)
		);
		return $deleted;
	}

	/**
	 * Fixes duplicate post_name entries for given post types.
	 *
	 * @param array $post_types Post types.
	 *
	 * @return array $updated {
	 *     Resulting updated records.
	 *
	 *     @type int ID                   Live Post ID.
	 *     @type string post_name_old     Previous post_name.
	 *     @type string post_name_updated New post_name.
	 * }
	 */
	public function fix_duplicate_slugs( array $post_types ): array {
		global $wpdb;

		$updated = [];

		// Query which post_names have dupes.
		$post_types_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		// phpcs:disable -- Placeholders used and query sanitized
		$results                 = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_name, COUNT(*) counter
				FROM $wpdb->posts
				WHERE post_type IN ( $post_types_placeholders )
					AND post_status = 'publish'
				GROUP BY post_name HAVING counter > 1 ;",
				$post_types
			),
			ARRAY_A
		);
		// phpcs:enable

		// Get the post_names.
		$post_names = [];
		foreach ( $results as $result ) {
			$post_names[] = $result['post_name'];
		}

		foreach ( $post_names as $key_post_name => $post_name ) {
			WP_CLI::log( sprintf( '(%d)/(%d) fixing %s ...', $key_post_name + 1, count( $post_names ), $post_name ) );

			// Get IDs with this post_name. Order by post_modified and ID descending.
			// phpcs:disable -- Placeholders used and query sanitized
			$post_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID
					FROM $wpdb->posts
					WHERE post_name = %s
			        AND post_type IN ( $post_types_placeholders )
					ORDER BY post_modified, ID DESC;",
					array_merge( [ $post_name ], $post_types )
				)
			);
			// phpcs:enable

			// Fix dupe post_names.
			foreach ( $post_ids as $key_post_id => $post_id ) {
				// Leave first/last one.
				if ( 0 === $key_post_id ) {
					continue;
				}

				// Rename slugs of previous one(s) by appending "-1" to them.
				$post_name_updated = $post_name . '-' . ( $key_post_id + 1 );
				$updated_res       = $wpdb->update(
					$wpdb->posts,
					[ 'post_name' => $post_name_updated ],
					[ 'ID' => $post_id ],
				);
				if ( 1 === $updated_res ) {
					$updated[] = [
						'ID'                => $post_id,
						'post_name_old'     => $post_name,
						'post_name_updated' => $post_name_updated,
					];

					WP_CLI::log( sprintf( 'Updated ID %d post_name to %s', $post_id, $post_name_updated ) );
				}
			}
		}

		// Needed for $wpdb->update.
		wp_cache_flush();

		return $updated;
	}
}
