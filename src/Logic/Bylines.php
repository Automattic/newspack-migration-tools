<?php

namespace Newspack\MigrationTools\Logic;

/**
 * Bylines logic.
 */
class Bylines {

	/**
	 * Parse byline string into author names,
	 * - explodes by multiple separators,
	 * - trims each individual part/name,
	 * - filters out unsupported characters,
	 * - applies manual substitutions without parsing
	 *     e.g. when you wish to preserve certain unexploded bylines. Let's say you have a separator ',',
	 *     but you wish to preserve 'Arthur Author, Ph.D.' as a single author name, or remove the 'Ph.D.' suffix manually.
	 * 
	 * @param string $byline               Byline.
	 * @param array  $separators           Separators to explode by. E.g. ' and ', '&', ','. Be mindful of spaces needed, depending on the separator.
	 * @param array  $manual_substitutions Will not parse these bylines, just return them as given in this array. Keys are bylines, values are resulting author names.
	 *                                     E.g. [ 'Arthur Author, Ph.D.' => 'Arthur Author' ]
	 * @param array  $remove_chars         Characters to remove. Unsupported polluting characters found in byline metas.
	 * @param array  $remove_prefixes      Prefixes to remove from beginning of byline, case-insensitive. E.g. 'By ' or 'Byline: '.
	 * @param array  $remove_suffixes      Suffixes to remove from end of byline, case-insensitive. E.g. 'Ph.D.'.
	 * @return string[]                    Exploded and trimmed author names from byline.
	 */
	public function get_author_names_from_byline(
		string $byline,
		array $separators,
		array $manual_substitutions = [],
		array $remove_chars = [],
		array $remove_prefixes = [],
		array $remove_suffixes = [],
	): array {
		// If empty return empty array.
		if ( empty( $byline ) ) {
			return [];
		}
		
		// Remove unsupported characters.
		$byline = str_replace( $remove_chars, '', $byline );

		// Trim.
		$byline = trim( $byline );

		// Remove prefixes and suffixes case-insensitively.
		foreach ( $remove_prefixes as $prefix ) {
			$byline = preg_replace( '/^' . $prefix . ' /i', '', $byline );
		}
		foreach ( $remove_suffixes as $suffix ) {
			$byline = preg_replace( '/ ' . $suffix . '$/i', '', $byline );
		}

		// Explode by multiple separators.
		foreach ( $separators as $separator ) {
			if ( false === stripos( $byline, $separator ) ) {
				continue;
			}
			
			// Explode and trim each exploded part.
			$byline_exploded_parts = array_map( 'trim', explode( $separator, $byline ) );
			
			// Recursively process each exploded part to explode by remaining separators.
			$result = [];
			foreach ( $byline_exploded_parts as $part ) {
				$result = array_merge(
					$result,
					$this->get_author_names_from_byline( $part, $separators, $remove_chars, $remove_prefixes )
				);
			}
			
			return $result;
		}
		
		// After recursion -- if no more separators are found, return the cleaned byline.
		$byline = trim( $byline );

		// Finally, apply manual substitutions.
		if ( isset( $manual_substitutions[ $byline ] ) ) {
			return [ $manual_substitutions[ $byline ] ];
		}

		return [ $byline ];
	}
}
