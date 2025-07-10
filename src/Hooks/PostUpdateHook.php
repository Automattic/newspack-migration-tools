<?php

namespace Newspack\MigrationTools\Hooks;

class PostUpdateHook {
    /**
     * Attaches a callback to prevent updating post_modified and post_modified_gmt
     * when a post is being updated.
     * 
     * @return void
     */
    public static function attach_hook_to_prevent_modified_date_update(): void {
        add_filter( 'wp_insert_post_data', [ __CLASS__, 'fallback_to_current_post_modified_datetime' ], 10, 4 );
    }

    /**
     * Detaches a callback to prevent updating post_modified and post_modified_gmt
     * when a post is being updated.
     * 
     * @return void
     */
    public static function detach_hook_to_prevent_modified_date_update(): void {
        remove_filter( 'wp_insert_post_data', [ __CLASS__, 'fallback_to_current_post_modified_datetime' ], 10 );
    }

    /**
     * Prevents updating the `post_modifed` and `post_modified_gmt` fields when updating a post.
     * 
     * The `$postarr` parameter contains the post object prior to being updated. The method
     * below uses the original `post_modified` and `post_modified_gmt` values from there.
     * 
     * @see https://developer.wordpress.org/reference/hooks/wp_insert_post_data/
     * 
     * @param $data array An array of slashed, sanitized, and processed post data.
     * @param $postarr array An array of sanitized (and slashed) but otherwise unmodified post data.
     * @param $unsanitized_postarr array An array of slashed yet *unsanitized* and unprocessed post data as originally passed to wp_insert_post().
     * @param $update bool Whether this is an existing post being updated.
     * @return array The post data to update.
     */
    public static function fallback_to_current_post_modified_datetime( $data, $postarr, $unsanitized_postarr, $update ) {
        if ( ! $update ) {
            return $data;
        }

        $data['post_modified']     = $postarr['post_modified'];
        $data['post_modified_gmt'] = $postarr['post_modified_gmt'];

        return $data;
    }
}