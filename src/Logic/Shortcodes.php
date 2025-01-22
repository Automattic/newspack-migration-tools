<?php

namespace Newspack\MigrationTools\Logic;

/**
 * Shortcodes logic class.
 */
class Shortcodes {

	/**
	 * Checks if a post content has a specific shortcode, without the need for the shortcode to be registered.
	 * 
	 * @param string $shortcode_name Shortcode name.
	 * @param string $post_content   HTML content.
	 * @return bool                  True if the shortcode is found in the content, false otherwise.
	 */
	public function has_shortcode( string $shortcode_name, string $post_content ): bool {
		$pattern       = get_shortcode_regex( [ $shortcode_name ] );
		$res           = preg_match_all( '/' . $pattern . '/s', $post_content, $matches );
		$has_shortcode = is_int( $res ) && $res > 0;
		
		return $has_shortcode;
	}

	/**
	 * Gets all shortcodes from a post content.
	 * 
	 * @param string $shortcode_name Shortcode name.
	 * @param string $post_content   HTML content.
	 * 
	 * @return array Array containing all found shortcodes.
	 */
	public function get_all_shortcodes_from_content( string $shortcode_name, string $post_content ): array {
		$shortcodes_found = [];
		
		$pattern = get_shortcode_regex( [ $shortcode_name ] );
		$matches = [];
		$res     = preg_match_all( '/' . $pattern . '/s', $post_content, $matches );
		if ( is_int( $res ) && $res > 0 ) {
			$shortcodes_found = $matches[0];
		}

		return $shortcodes_found;
	}
}
