<?php

namespace Newspack\MigrationTools\Logic;

use Newspack\MigrationTools\Util\Log\CliLog;
use WP_Error;

/**
 * Attachments logic.
 */
class Attachments {
	/**
	 * Meta key for the unique identifier for posts.
	 */
	public const UNIQUE_ATTACHMENT_IDENTIFIER_META_KEY = '_nmt_attachment_uniqid';

	/**
	 * Wrapper for import_external_file() with fewer args and enforced post_id.
	 *
	 * @param int    $post_id              Post ID of the post the media should be attached to.
	 * @param string $path              Media file full URL or full local path, or URL to the media file.
	 * @param string $alt_text          Optional. Image Attachment `alt` attribute.
	 * @param array  $attachment_args    Optional. Attachment creation argument to override used by the \media_handle_sideload(), used
	 *                                       internally by the \wp_insert_attachment(), and even more internally by the \wp_insert_post().
	 * @param string $desired_filename  Optional. If the file you are importing has a different (or no) file extension than the one
	 *                                      you want the resulting attachment to have, you can specify it here. Make sure that it
	 *                                      actually matches the file mime type.
	 *
	 * @return int|WP_Error
	 */
	public static function import_attachment_for_post( int $post_id, string $path, string $alt_text = '', array $attachment_args = [], string $desired_filename = '' ): int|WP_Error {
		return self::import_external_file( $path, null, null, null, $alt_text, $post_id, $attachment_args, $desired_filename );
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
	 * @param string $desired_filename Optional. If the file you are importing has a different (or no) file extension than the one
	 *                                 you want the resulting attachment to have, you can specify it here. Make sure that it
	 *                                 actually matches the file mime type.
	 * @param bool   $try_existing Optional. Default true. Try to match an existing file using self::maybe_get_existing_attachment_id().
	 *                             Set this to false to skip the existing lookup if, for example, you are uploading two images that have the
	 *                             same filename and binary data, but you need them to be unique attachments in the db, so they can have different
	 *                             alt/captions and also different postmeta.
	 *
	 * @return int|WP_Error Attachment ID.
	 */
	public static function import_external_file( $path, $title = null, $caption = null, $description = null, $alt = null, $post_id = 0, $args = [], $desired_filename = '', $try_existing = true, $unique_identifier = null, $cropped_url = null ) {
		$file_array         = self::download_file( $path, $desired_filename );
		$cropped_file_array = $cropped_url ? self::download_file( $cropped_url, $desired_filename ) : null;

		if ( is_wp_error( $file_array ) ) {
			return $file_array;
		}

		$maybe_exising_attachment_id = ( $try_existing ) ? self::maybe_get_existing_attachment_id( $file_array['tmp_name'], $file_array['name'], $unique_identifier ) : null;
		if ( null !== $maybe_exising_attachment_id ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $file_array['tmp_name'] );
			return $maybe_exising_attachment_id;
		}

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
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $file_array['tmp_name'] );
			return new WP_Error( sprintf( 'File %s was not sideloaded: %s', $file_array['name'], $att_id->get_error_message() ) );
		}

		if ( $cropped_url && ! is_wp_error( $cropped_file_array ) ) {
			// This code is copied from wp_save_image() function.
			require_once ABSPATH . 'wp-admin/includes/image-edit.php';
			require_once ABSPATH . 'wp-includes/class-wp-image-editor.php';
			require_once ABSPATH . 'wp-includes/class-wp-image-editor-gd.php';

			$attachment = get_post( $att_id );
			$img        = new \WP_Image_Editor_GD( $cropped_file_array['tmp_name'] );
			$img->load();

			$meta         = wp_get_attachment_metadata( $att_id );
			$backup_sizes = get_post_meta( $att_id, '_wp_attachment_backup_sizes', true );

			if ( ! is_array( $meta ) ) {
				// If there is any error with the cropped image, we return the original attachment ID.
				return $att_id;
			}

			if ( ! is_array( $backup_sizes ) ) {
				$backup_sizes = array();
			}

			// Generate new filename.
			$path = get_attached_file( $att_id );

			$basename = pathinfo( $path, PATHINFO_BASENAME );
			$dirname  = pathinfo( $path, PATHINFO_DIRNAME );
			$ext      = pathinfo( $path, PATHINFO_EXTENSION );
			$filename = pathinfo( $path, PATHINFO_FILENAME );
			$suffix   = time() . wp_rand( 100, 999 );

			if ( defined( 'IMAGE_EDIT_OVERWRITE' ) && IMAGE_EDIT_OVERWRITE
				&& isset( $backup_sizes['full-orig'] ) && $backup_sizes['full-orig']['file'] !== $basename
			) {
				$new_path = $path;
			} else {
				while ( true ) {
					$filename     = preg_replace( '/-e([0-9]+)$/', '', $filename );
					$filename    .= "-e{$suffix}";
					$new_filename = "{$filename}.{$ext}";
					$new_path     = "{$dirname}/$new_filename";

					if ( file_exists( $new_path ) ) {
						++$suffix;
					} else {
						break;
					}
				}
			}

			try {
				$saved_image = wp_save_image_file( $new_path, $img, $attachment->post_mime_type, $att_id );
			} catch ( \Throwable $e ) {
				return $att_id;
			}

			// Save the full-size file, also needed to create sub-sizes.
			if ( ! $saved_image ) {
				// If there is any error with the cropped image, we return the original attachment ID.
				return $att_id;
			}

			$tag    = false;
			$delete = false;

			if ( isset( $backup_sizes['full-orig'] ) ) {
				if ( ( ! defined( 'IMAGE_EDIT_OVERWRITE' ) || ! IMAGE_EDIT_OVERWRITE )
					&& $backup_sizes['full-orig']['file'] !== $basename
				) {
					$tag = "full-$suffix";
				}
			} else {
				// TODO: This is a hack to avoid the issue of the full-orig size not being set.
				$tag = 'full-orig';
			}

			if ( $tag ) {
				$backup_sizes[ $tag ] = array(
					'width'    => $meta['width'],
					'height'   => $meta['height'],
					'filesize' => $meta['filesize'],
					'file'     => $basename,
				);
			}

			$success = ( $path === $new_path ) || update_attached_file( $att_id, $new_path );

			$meta['file'] = \_wp_relative_upload_path( $new_path );

			$size             = $img->get_size();
			$meta['width']    = $size['width'];
			$meta['height']   = $size['height'];
			$meta['filesize'] = $saved_image['filesize'];

			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_intermediate_image_sizes_get_intermediate_image_sizes
			$sizes = get_intermediate_image_sizes();

			/*
			* We need to remove any existing resized image files because
			* a new crop or rotate could generate different sizes (and hence, filenames),
			* keeping the new resized images from overwriting the existing image files.
			* https://core.trac.wordpress.org/ticket/32171
			*/
			if ( defined( 'IMAGE_EDIT_OVERWRITE' ) && IMAGE_EDIT_OVERWRITE && ! empty( $meta['sizes'] ) ) {
				foreach ( $meta['sizes'] as $size ) {
					if ( ! empty( $size['file'] ) && preg_match( '/-e[0-9]{13}-/', $size['file'] ) ) {
						$delete_file = path_join( $dirname, $size['file'] );
						wp_delete_file( $delete_file );
					}
				}
			}

			if ( isset( $sizes ) ) {
				$_sizes = array();

				foreach ( $sizes as $size ) {
					$tag = false;

					if ( isset( $meta['sizes'][ $size ] ) ) {
						if ( isset( $backup_sizes[ "$size-orig" ] ) ) {
							if ( ( ! defined( 'IMAGE_EDIT_OVERWRITE' ) || ! IMAGE_EDIT_OVERWRITE )
								&& $backup_sizes[ "$size-orig" ]['file'] !== $meta['sizes'][ $size ]['file']
							) {
								$tag = "$size-$suffix";
							}
						} else {
							$tag = "$size-orig";
						}

						if ( $tag ) {
							$backup_sizes[ $tag ] = $meta['sizes'][ $size ];
						}
					}

					$nocrop = false;
					if ( isset( $_wp_additional_image_sizes[ $size ] ) ) {
						$width  = (int) $_wp_additional_image_sizes[ $size ]['width'];
						$height = (int) $_wp_additional_image_sizes[ $size ]['height'];
						$crop   = ( $nocrop ) ? false : $_wp_additional_image_sizes[ $size ]['crop'];
					} else {
						$height = get_option( "{$size}_size_h" );
						$width  = get_option( "{$size}_size_w" );
						$crop   = ( $nocrop ) ? false : get_option( "{$size}_crop" );
					}

					$_sizes[ $size ] = array(
						'width'  => $width,
						'height' => $height,
						'crop'   => $crop,
					);
				}

				$meta['sizes'] = array_merge( $meta['sizes'], $img->multi_resize( $_sizes ) );
			}

			unset( $img );

			if ( $success ) {
				wp_update_attachment_metadata( $att_id, $meta );
				update_post_meta( $att_id, '_wp_attachment_backup_sizes', $backup_sizes );
			} else {
				$delete = true;
			}

			if ( $delete ) {
				wp_delete_file( $new_path );
			}
		}

		if ( $alt ) {
			update_post_meta( $att_id, '_wp_attachment_image_alt', $alt );
		}

		if ( $unique_identifier ) {
			update_post_meta( $att_id, self::UNIQUE_ATTACHMENT_IDENTIFIER_META_KEY, $unique_identifier );
		}

		return $att_id;
	}

	/**
	 * Download a file from a URL or a local path.
	 *
	 * @param string $path The path to the file.
	 *
	 * @return array|WP_Error The file array.
	 */
	public static function download_file( $path, $desired_filename = '' ) {
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
			if ( ! file_exists( $path ) ) {
				return new WP_Error( sprintf( 'File %s was not found', $path ) );
			}
			copy( $path, $tmpfname );
		}

		if ( filesize( $tmpfname ) < 1 ) {
			return new WP_Error( sprintf( 'File %s was empty', $path ) );
		}

		$file_array = [
			'name'     => wp_basename( $path ),
			'tmp_name' => $tmpfname,
		];

		if ( ! empty( $desired_filename ) ) {
			$file_array['name'] = $desired_filename;
		} elseif ( ! self::has_valid_extension( $path ) ) {
			// If the path does not have a file extension, let's try to find one for it.
			// Without the extension, the upload will fail because WP will not allow that "file type".
			$mimetype           = mime_content_type( $tmpfname );
			$probably_extension = array_search( $mimetype, wp_get_mime_types() );

			// Sometimes the extension is in the format `jpg|jpeg|jpe`. In that case, we need to get the first one.
			if ( str_contains( $probably_extension, '|' ) ) {
				$probably_extension = explode( '|', $probably_extension )[0];
			}

			if ( ! empty( $probably_extension ) ) {
				$file_array['name'] .= '.' . $probably_extension;
			}
		}

		return $file_array;
	}

	/**
	 * Check if a file path has a valid, recognized extension.
	 *
	 * @param string $path File path or URL.
	 *
	 * @return bool True if the extension is valid and recognized by WordPress.
	 */
	private static function has_valid_extension( string $path ): bool {
		$extension = pathinfo( $path, PATHINFO_EXTENSION );

		if ( empty( $extension ) ) {
			return false;
		}

		// Use wp_check_filetype which properly checks extensions against allowed mime types.
		$filetype = wp_check_filetype( '.' . $extension );

		return ! empty( $filetype['ext'] );
	}

	/**
	 * Try to get the attachment ID for a file if one just like it has already been uploaded.
	 *
	 * @param string $filepath The path on the file system of the file to check if we have already uploaded.
	 * @param string $filename (Optional) The file name including file extension – exclude if it is on the file path.
	 *
	 * @return int|null Attachment ID if found, null otherwise.
	 */
	public static function maybe_get_existing_attachment_id( string $filepath, string $filename = '', $unique_identifier = null ) {
		if ( ! file_exists( $filepath ) ) {
			return null;
		}

		if ( empty( $filename ) ) {
			$filename = basename( $filepath );
		}

		global $wpdb;

		// If the unique identifier is set, we'll use it to check for existing attachments.
		if ( ! empty( $unique_identifier ) ) {
			$attachment_id = self::get_attachment_by_unique_identifier( $unique_identifier );
			if ( $attachment_id ) {
				return $attachment_id;
			}
		}

		// Check if the file with same name exists in the DB.
		$like = '%/' . $wpdb->esc_like( sanitize_file_name( $filename ) );

		/*
		 * Check if files with numeric suffix like `filename-1.jpg` exist in DB.
		 *
		 * In case of imported duplicates, WP changes the file name to something like `filename-1.jpg`. In most cases, it wouldn't
		 * be necessary to search for duplicates, because the original `filename.jpg` should already be found in the DB.
		 * But some cases were encountered where the original was deleted and duplicates '-1', '-2' remained, and this script would
		 * not catch those without this part.
		 */
		$filename_path_parts    = pathinfo( $filename );
		$filename_before_suffix = $filename_path_parts['filename'];
		$filename_after_suffix  = '.' . $filename_path_parts['extension'];
		/**
		 * Constructs a regular expression to find attachments that could be duplicates.
		 * The regex matches filenames with optional numeric and "-scaled" suffixes,
		 * where the "-scaled" suffix appears after any numeric suffix if both are present.
		 * For a base filename like "myimage.jpg", it will match:
		 * - myimage.jpg
		 * - myimage-scaled.jpg
		 * - myimage-1.jpg
		 * - myimage-1-scaled.jpg
		 * It also handles paths, e.g., "2023/10/myimage.jpg".
		 *
		 * Regex breakdown:
		 *  - (?:^|.*\/) : Matches the beginning of the string or any characters followed by a slash.
		 *                 This handles cases where the meta_value is just "filename.ext" or "path/to/filename.ext".
		 *  - %s         : The base filename (e.g., "myimage"), after being escaped by preg_quote.
		 *  - (-[0-9]+)? : Optionally matches a numeric suffix (e.g., "-1", "-123").
		 *  - (-scaled)? : Optionally matches a "-scaled" suffix. This comes after the numeric suffix if present.
		 *  - %s         : The file extension (e.g., "\.jpg"), after being escaped by preg_quote. The dot is escaped.
		 *  - $          : Matches the end of the string.
		 */
		$regex_pattern = sprintf(
			'(?:^|.*/)%s(-[0-9]+)?(-scaled)?%s$',
			preg_quote( $filename_before_suffix, '/' ),
			preg_quote( $filename_after_suffix, '/' )
		);

		// The $regex_pattern is used with $wpdb->prepare, which will handle SQL escaping.
		// phpcs:disable -- Direct SQL query is OK here and the filename is escaped.
		$sql = $wpdb->prepare(
			"SELECT post_id
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_wp_attached_file'
			AND (meta_value LIKE %s OR meta_value REGEXP %s);",
			$like,
			$regex_pattern
		);

		$attachment_ids = $wpdb->get_col( $sql );
		// phpcs:enable

		foreach ( $attachment_ids as $attachment_id ) {

			$candidate_path = get_attached_file( $attachment_id );
			// Remove the "-scaled" suffix from the candidate path to check the original file size.
			$candidate_path = str_replace( '-scaled.', '.', $candidate_path );
			// Check the file sizes first. It's a fast operation and will save us from having to do the md5 check.
			if ( ! file_exists( $candidate_path ) || ( filesize( $candidate_path ) !== filesize( $filepath ) ) ) {
				continue;
			}

			if ( md5_file( $candidate_path ) === md5_file( $filepath ) ) {
				return intval( $attachment_id );
			}
		}

		return null;
	}

	/**
	 * Return broken attachment URLs from posts.
	 *
	 * @param int[]         $post_ids The post IDs we need to check the images in their content, if not set, the function looks for all the posts in the database.
	 * @param boolean       $is_hosted_on_s3 Flag to be set to true if we're using S3_uploads plugin or other to host the images on S3 instead of locally.
	 * @param integer       $posts_per_batch Total of posts tohandle per batch.
	 * @param integer       $batch Current batch in the loop.
	 * @param integer       $start_index Index from where to start the loop.
	 * @param callable|null $logger Method to log results.
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

		$cli_logger = CliLog::get_logger( 'attachments' );

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
			$cli_logger->info( sprintf( 'Checking Post(%d/%d): %d', $index + 1, $total_posts, $post->ID ) );
			$broken_post_images = [];

			// get responsive images.
			$image_urls_to_check = self::get_images_sources_from_content( $post->post_content );

			foreach ( $image_urls_to_check as $image_url_to_check ) {
				// Skip non-local and non-S3 URLs.
				$local_domain = str_replace( [ 'http://', 'https://' ], '', get_site_url() );
				if ( ! str_contains( $image_url_to_check, $local_domain ) && ! str_contains( $image_url_to_check, '.s3.amazonaws.com' ) ) {
					$cli_logger->warning( sprintf( 'Skipping non-local URL: %s', $image_url_to_check ) );
					continue;
				}

				$image_request = wp_remote_head( $image_url_to_check, [ 'redirection' => 5 ] );

				if ( is_wp_error( $image_request ) ) {
					$cli_logger->warning(
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
							$cli_logger->warning(
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
	public static function get_attachment_by_filename( $filename ) {
		global $wpdb;

		$filename = esc_sql( $filename );
		// phpcs:disable -- $filename is properly sanitized.
		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value LIKE '%s'",
				'%' . $filename,
			),
		);
		// phpcs:enable

		return $attachment_id;
	}

	/**
	 * This helper wraps the `wp_get_attachment_image_src()` function to bypass Jetpack's Photon.
	 *
	 * We don't want the CDN urls in migration data because they are harder to replace later.
	 *
	 * @param int    $attachment_id The attachment ID.
	 * @param string $size The image size.
	 * @param bool   $icon Whether the image should be an icon.
	 *
	 * @return array|false
	 */
	public static function get_attachment_image_src( $attachment_id, $size = 'thumbnail', $icon = false ) {
		static $filter_callback = null;
		if ( null === $filter_callback ) {
			$filter_callback = fn() => true;
		}
		add_filter( 'jetpack_photon_skip_for_url', $filter_callback, 10, 4 );
		$src = wp_get_attachment_image_src( $attachment_id, $size, $icon );
		remove_filter( 'jetpack_photon_skip_for_url', $filter_callback );

		return $src;
	}

	/**
	 * Find an attachment by its filename.
	 *
	 * The search is finding the first attachment with a filename that ends with the provided filename.
	 * The filename can also contain a path – the search just finds a file that ends in the string.
	 *
	 * @param string $filename The filename or path.
	 *
	 * @return int The attachment ID or zero if none was found.
	 */
	public static function get_attachment_id_by_filename( string $filename ): int {
		global $wpdb;

		$filename = esc_sql( $filename );
		// phpcs:disable -- Direct SQL query is OK here and the filename is escaped.
		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value LIKE '%s'",
				'%' . $wpdb->esc_like( $filename ),
			),
		);

		// phpcs:enable

		return $attachment_id ?? 0;
	}

	/**
	 * Get a post by its unique identifier.
	 *
	 * The identifier was set when the post was created (if it was created by this class), so you probably know what it is.
	 * Make sure you read the docs linked to at the top of the class.
	 *
	 * @param string $unique_identifier The unique identifier to search for.
	 *
	 * @return int|false The post ID if found, false otherwise.
	 */
	public static function get_attachment_by_unique_identifier( string $unique_identifier ): int|false {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				self::UNIQUE_ATTACHMENT_IDENTIFIER_META_KEY,
				$unique_identifier
			)
		);

		return empty( $post_id ) ? false : (int) $post_id;
	}
}
