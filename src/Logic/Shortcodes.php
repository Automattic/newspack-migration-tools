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

	/**
	 * Parse shortcode attributes using `shortcode_parse_atts`. A memo helper wrapper method.
	 * 
	 * @param string $shortcode Shortcode string.
	 * 
	 * @return array Response from shortcode_parse_atts(). Here's how `shortcode_parse_atts` will parse
	 *               attributes which do have and do not have values:
	 *               e.g. for following shortcode:
	 *               ```
	 *               [customshort attr1="value1" attr2 attr3="value3" attr4 attr5]
	 *               ```
	 *               the resulting array will be --  ❗️  NOTE that the ending `]` is included in final argument's value:
	 *               ```
	 *               array(6) {
	 *                   [0]     => "[customshort"
	 *                   'attr1' => "value1"
	 *                   [1]     => "attr2"
	 *                   'attr3' => "value3"
	 *                   [2]     => "attr4"
	 *                   [3]     => "attr5]"
	 *               }
	 *               ```
	 */
	public function get_shortcode_attributes( string $shortcode ): array {
		$attrs = shortcode_parse_atts( $shortcode );
		return $attrs;
	}
}
