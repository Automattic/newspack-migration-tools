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
	 * Get all shortcode attributes.
	 * 
	 * @param string $shortcode Shortcode string.
	 * 
	 * @return array Keys are shortcode attribute names.
	 *               Values are null where a shortcode attribute has no value, e.g. [customshort myattribute]
	 *               or strings if they do have values, e.g. [customshort myattribute="somevalue"].
	 *               E.g. shortcode [customshort attr1="value1" attr2 attr3="value3" attr4 attr5] will return:
	 *               ```
	 *               array(5) {
	 *                   'attr1' => "value1"
	 *                   'attr2' => null
	 *                   'attr3' => "value3"
	 *                   'attr4' => null
	 *                   'attr5' => null
	 *               }
	 *               ```
	 */
	public function get_all_shortcode_attributes( string $shortcode ): array {
		
		/**
		 * Uses the built in shortcode_parse_atts() function, however at the time of writing this
		 * the shortcode_parse_atts() seems to return incorrect results for the final attribute with a value.
		 * For example, `shortcode_parse_atts( '[customshort attr1="val1" attr2="val2" "attr3="val3"]' )` will return:
		 * ```
		 * array(4) {
		 *   [0] => "[customshort"
		 *   'attr1' => "val1"
		 *   'attr2' => "val2"
		 *   [1]     => "attr3="val3"]"
		 * }
		 * ```
		 * Note that the attr3 is not parsed correctly.
		 *
		 * By adding a space just before the ending `]` character and then running shortcode_parse_atts(),
		 * the final element can be returned correctly, however an empty parameter will be added at the end.
		 * For example, and note the space before ending `]`, `shortcode_parse_atts( '[customshort attr1="val1" attr2="val2" attr3="val3" ]' )`
		 * will return:
		 * ```
		 * array(5) {
		 *   [0] => "[customshort"
		 *   'attr1' => "val1"
		 *   'attr2' => "val2"
		 *   'attr3' => "val3"
		 *   [1] => "]"
		 * }
		 * ```
		 *
		 * Conclusion -- we'll add a space before the ending `]` character, then run the parse method,
		 * then remove both starting and the ending element from the resulting array, and we'll end up with the correct result.
		 *
		 * Also confirming that the same works for attributes with no values, e.g. `shortcode_parse_atts( '[customshort attr1 attr2]' )`:
		 * ```
		 * array(3) {
		 *   [0] => "[customshort"
		 *   [1] => "attr1"
		 *   [2] => "attr2]"
		 * }
		 * ```
		 * and with added space before ending `]`, e.g. `[customshort attr1 attr2 ]`, result is:
		 * ```
		 * array(4) {
		 *   [0] => "[customshort"
		 *   [1] => "attr1"
		 *   [2] => "attr2"
		 *   [3] => "]"
		 * }
		 * ```
		 *
		 * Therefore, adding a space before the ending `]`, and cleaning up the trailing element, will give
		 * the correct list of parameters.
		 */

		// Add a space character just before the ending `]`.
		if ( ' ' !== substr( $shortcode, -2, 1 ) ) {
			$shortcode = substr( $shortcode, 0, -1 ) . ' ]'; 
		}
		
		// Parse.
		$attributes = shortcode_parse_atts( $shortcode );

		// Remove 0th element, shortcode name.
		unset( $attributes[0] );

		// Remove the final element from array -- the trailing element from when we added the space.
		$last_key = array_key_last( $attributes );
		unset( $attributes[ $last_key ] );

		// For attributes without value, make the key attribute name, and value null.
		foreach ( $attributes as $key => $value ) {
			// If the key is integer, that's an attribute without value.
			if ( is_int( $key ) ) {
				$attributes[ $value ] = null;
				unset( $attributes[ $key ] );
			}
		}

		return $attributes;
	}

	/**
	 * Get a specific shortcode attribute.
	 * Uses the built in WP `shortcode_parse_atts` and automatically strips the ending `]` from the last key-value pair.
	 *
	 * @param string $attribute_name Name of the attribute.
	 * @param string $shortcode Shortcode string.
	 *
	 * @return mixed The attribute value if it's a key-value pair, 
	 *               true if it's a boolean attribute (no value), 
	 *               false if the attribute is not found.
	 */
	public function get_shortcode_attribute( string $attribute_name, string $shortcode ): mixed {
		$attributes = $this->get_all_shortcode_attributes( $shortcode );
		
		// Attribute not found.
		if ( ! array_key_exists( $attribute_name, $attributes ) ) {
			return false;
		}

		// If the value is null, that means it's an attribute without value, so return true.
		if ( is_null( $attributes[ $attribute_name ] ) ) {
			return true;
		}

		// Return the attribute value.
		return $attributes[ $attribute_name ];
	}
}
