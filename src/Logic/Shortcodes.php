<?php

namespace Newspack\MigrationTools\Logic;

use UnexpectedValueException;

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
	 * @throws UnexpectedValueException If the shortcode format is invalid.
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
		 * Validate shortcode a bit.
		 */
		$shortcode_trimmed   = trim( $shortcode );
		$starts_with_bracket = '[' === substr( $shortcode_trimmed, 0, 1 );
		$ends_in_bracket     = ']' === substr( $shortcode_trimmed, -1 );
		if ( ! $starts_with_bracket || ! $ends_in_bracket ) {
			// Shouldn't happen, but better safe than sorry.
			throw new UnexpectedValueException( sprintf( 'Invalid shortcode format `%s`', esc_html( $shortcode ) ) );
		}

		/**
		 * If the shortcode has no attributes, return an empty array.
		 */
		if ( strpos( $shortcode_trimmed, ' ' ) === false ) {
			return [];
		}

		/**
		 * Get the shortcode arguments list needed for `shortcode_parse_atts( $text )`.
		 */
		$text = $shortcode_trimmed;
		// Remove evertything before and including the first space.
		$text = substr( $text, strpos( $text, ' ' ) + 1 );
		// Remove the ending `]`.
		$text = substr( $text, 0, -1 );
		// Parse.
		$text       = trim( $text );
		$attributes = shortcode_parse_atts( $text );

		/**
		 * For attributes without value, make the array key equal to the attribute name,
		 * and the array value equal to null.
		 */
		foreach ( $attributes as $key => $value ) {
			// If the key is integer, that's an attribute without value.
			if ( is_int( $key ) ) {
				$attributes[ $value ] = null;
				unset( $attributes[ $key ] );
			}
		}

		// Sort by keys, keeping values.
		ksort( $attributes );

		return $attributes;
	}

	/**
	 * Get a specific shortcode attribute.
	 * Uses the built in WP `shortcode_parse_atts` and automatically strips the ending `]` from the last key-value pair.
	 *
	 * @param string $attribute_name Name of the attribute.
	 * @param string $shortcode Shortcode string.
	 *
	 * @return mixed string attribute value if it's a key-value pair.
	 *               true if it's an attribute without a value.
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
