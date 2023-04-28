<?php

namespace NewspackCustomContentMigrator\Logic;

use \WP_CLI;

class Attachments {
	/**
	 * Imports a media object from file and returns the ID.
	 *
	 * @deprecated Warning: this function is quite slow, and the new function `import_external_file` here should be used instead.
	 *
	 * @param string $file Media file full path.
	 *
	 * @return mixed ID of the imported media file.
	 */
	public function import_media_from_path( $file ) {
		$options = [ 'return' => true, ];
		$id      = WP_CLI::runcommand( "media import $file --title='favicon' --porcelain", $options );

		return $id;
	}

	/**
	 * Imports/downloads external media file to the Media Library, either from a URL or from a local path.
	 *
	 * To untangle the terminology, the optional params Title, Caption, Description and Alt are the params we see in the
	 * Attachment edit form in WP Admin.
	 *
	 * @param string $path        Media file full URL or full local path, or URL to the media file.
	 * @param string $title       Optional. Attachment title.
	 * @param string $caption     Optional. Attachment caption.
	 * @param string $description Optional. Attachment description.
	 * @param string $alt         Optional. Image Attachment `alt` attribute.
	 * @param int    $post_id     Optional.  Post ID the media is associated with; this will ensure it gets uploaded to the same
	 *                            `yyyy/mm` folder.
	 * @param array  $args        Optional. Attachment creation argument to override used by the \media_handle_sideload(), used
	 *                            internally by the \wp_insert_attachment(), and even more internally by the \wp_insert_post().
	 *
	 * @return int|WP_Error Attachment ID.
	 */
	public function import_external_file( $path, $title = null, $caption = null, $description = null, $alt = null, $post_id = 0, $args = [] ) {
		// Fetch remote or local file.
		$is_http = 'http' == substr( $path, 0, 4 );
		if ( $is_http ) {
			$tmpfname = download_url( $path );
			if ( is_wp_error( $tmpfname ) ) {
				return $tmpfname;
			}
		} else {
			// The `media_handle_sideload()` function deletes the local file after import, so to preserve the local path, we're
			// first saving it to a temp location, in exactly the same way the WP's own `\download_url()` function above does.
			$tmpfname = wp_tempnam( $path );
			copy( $path, $tmpfname );
		}
		$file_array = [
			'name'     => wp_basename( $path ),
			'tmp_name' => $tmpfname,
		];

		if ( $title ) {
			$args['post_title'] = $title;
		}
		if ( $caption ) {
			$args['post_excerpt'] = $caption;
		}
		if ( $description ) {
			$args['post_content'] = $description;
		}
		$att_id = media_handle_sideload( $file_array, $post_id, $title, $args );

		// If this was a download and there was an error then clean up the temp file.
		if ( is_wp_error( $att_id ) ) {
			@unlink( $file_array['tmp_name'] );
			WP_CLI::warning( $att_id->get_error_message() );
		}


		if ( $alt ) {
			update_post_meta( $att_id, '_wp_attachment_image_alt', $alt );
		}

		return $att_id;
	}

	/**
	 * Return broken attachment URLs from posts.
	 *
	 * @param int[]     $post_ids The post IDs we need to check the images in their content, if not set, the function looks for all the posts in the database.
	 * @param boolean   $is_hosted_on_s3 Flag to be set to true if we're using S3_uploads plugin or other to host the images on S3 instead of locally.
	 * @param integer   $posts_per_batch Total of posts tohandle per batch.
	 * @param integer   $batch Current batch in the loop.
	 * @param integer   $start_index Index from where to start the loop.
	 * @param func|null $logger Method to log results.
	 *
	 * @return mixed[] Array of the broken URLs indexed by the post IDs.
	 */
	public function get_broken_attachment_urls_from_posts( $post_ids = [], $is_hosted_on_s3 = false, $posts_per_batch = -1, $batch = 1, $start_index = 0, $logger = false ) {
		$broken_images = [];

		$posts = get_posts(
            [
				'posts_per_page' => $posts_per_batch,
				'paged'          => $batch,
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private', 'inherit' ),
				'post__in'       => $post_ids,
			]
        );

		$total_posts = count( $posts );
		$logs        = file_exists( 'broken_media_urls_batch.log' ) ? file_get_contents( 'broken_media_urls_batch.log' ) : '';
		$batch_logs  = file_exists( "broken_media_urls_batch_$batch.log" ) ? file_get_contents( "broken_media_urls_batch_$batch.log" ) : '';

		foreach ( $posts as $index => $post ) {
			if ( $start_index - 1 > $index ) {
				continue;
			}
			if ( str_contains( $logs, $post->ID . '' ) || str_contains( $batch_logs, $post->ID . '' ) ) {
				continue;
			}
			WP_CLI::line( sprintf( 'Checking Post(%d/%d): %d', $index + 1, $total_posts, $post->ID ) );
			$broken_post_images = [];

			// get responsive images.
			$image_urls_to_check = $this->get_images_sources_from_content( $post->post_content );

			foreach ( $image_urls_to_check as $image_url_to_check ) {
				// Skip non-local and non-S3 URLs.
				$local_domain = str_replace( [ 'http://', 'https://' ], '', get_site_url() );
				if ( ! str_contains( $image_url_to_check, $local_domain ) && ! str_contains( $image_url_to_check, '.s3.amazonaws.com' ) ) {
					WP_CLI::warning( sprintf( 'Skipping non-local URL: %s', $image_url_to_check ) );
					continue;
				}

				$image_request = wp_remote_head( $image_url_to_check, [ 'redirection' => 5 ] );

				if ( is_wp_error( $image_request ) ) {
					WP_CLI::warning(
                        sprintf(
                            'Local image ID (%s) returned an error: %s',
                            $image_url_to_check,
                            $image_request->get_error_message()
                        )
                    );

					$broken_post_images[] = $image_url_to_check;

					continue;
				}

				if ( 200 !== $image_request['response']['code'] ) {
					// Check if the URL is managed by s3_uploads plugin.
					if ( $is_hosted_on_s3 && class_exists( \S3_Uploads\Plugin::class ) ) {
						$bucket       = \S3_Uploads\Plugin::get_instance()->get_s3_bucket();
						$exploded_url = explode( '/', $image_url_to_check );
						$filename     = end( $exploded_url );
						$month        = prev( $exploded_url );
						$year         = prev( $exploded_url );
						$s3_url       = 'https://' . $bucket . ".s3.amazonaws.com/wp-content/uploads/$year/$month/$filename";

						$image_request_from_s3 = wp_remote_head( $s3_url, [ 'redirection' => 5 ] );

						if ( is_wp_error( $image_request_from_s3 ) ) {
							WP_CLI::warning(
								sprintf(
									'Image ID (%s) from S3 returned an error: %s',
									$s3_url,
									$image_request_from_s3->get_error_message()
								)
							);

							$broken_post_images[] = $image_url_to_check;
							continue;
						}

						if ( 200 !== $image_request_from_s3['response']['code'] ) {
							$broken_post_images[] = $image_url_to_check;
						}
					} else {
						$broken_post_images[] = $image_url_to_check;
					}
				}
			}

			if ( ! empty( $broken_post_images ) ) {
				$broken_images[ $post->ID ] = $broken_post_images;

				if ( is_callable( $logger ) ) {
					foreach ( $broken_post_images as $broken_post_image ) {
						$logger( $post->ID, $broken_post_image );
					}
				}
			}
		}

		return $broken_images;
	}

	/**
	 * Retreive all the image sources from the srcset attribute in post content.
	 *
	 * @param string $post_content post content to look for the images sources on.
	 * @return array
	 */
	public function get_images_sources_from_content( $post_content ) {
		$image_urls = [];

		// Get URLs from src attribute.
		preg_match_all( '/<img[^>]+(?:src)="([^">]+)"/', $post_content, $image_sources_match );
		if ( array_key_exists( 1, $image_sources_match ) ) {
			$image_urls = $image_sources_match[1];
		}

		// Get URLs from responsive sources.
		preg_match_all( '/<img[^>]+(?:srcset)="([^">]+)"/', $post_content, $image_sources_match );
		if ( array_key_exists( 1, $image_sources_match ) ) {
			foreach ( $image_sources_match[1] as $srcset ) {
				$srcset_array = explode( ',', $srcset );
				foreach ( $srcset_array as $src_string ) {
					// split on whitespace - optional descriptor.
					$img_details = explode( ' ', trim( $src_string ) );
					// cast w or x descriptor as an Integer.
					$image_urls[] = $img_details[0];
				}
			}
		}

		return $image_urls;
	}

	/**
	 * Find an attachment by its filename.
	 * 
	 * @param string $filename The filename.
	 * @return int The attachment ID.
	 */
	public function get_attachment_by_filename( $filename ) {
		global $wpdb;

		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value LIKE '%s'",
				'%' . $filename . '%',
			),
		);

		return $attachment_id;
	}
}
