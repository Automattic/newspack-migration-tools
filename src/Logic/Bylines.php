<?php

namespace Newspack\MigrationTools\Logic;

/**
 * Bylines logic.
 */
class Bylines {

	/**
	 * Parses byline string into author names,
	 * - explodes by multiple separators,
	 * - trims each individual exploded part/author name,
	 * - filters out unsupported characters,
	 * - applies manual substitutions,
	 *     e.g. when you wish to preserve certain unexploded bylines. Let's say you have a separator ',',
	 *     but you wish to preserve 'Arthur Author, Ph.D.' as a single author name, or even remove the 'Ph.D.' suffix.   * 
	 *
	 * @param string $byline               Byline with one or multiple authors.
	 * @param array  $separators           Separators to explode by. E.g. ' and ', '&', ','. Be mindful of spaces needed, depending on the separator.
	 * @param array  $manual_substitutions Will skip parsing these bylines and just return them. Keys are bylines and values are resulting author names.
	 *                                     E.g. [ 'Arthur Author, Ph.D.' => [ 'Arthur Author' ] ].
	 * @param array  $remove_chars         Characters to remove. Unsupported polluting characters found in byline metas.
	 * @param array  $remove_prefixes      Prefixes to remove from beginning of byline, case-insensitive. E.g. 'By ' or 'Byline: '.
	 * @param array  $remove_suffixes      Suffixes to remove from end of byline, case-insensitive. E.g. 'Ph.D.'.
	 * @return string[]                    Exploded author names from byline.
	 */
	public function parse_byline(
		string $byline,
		array $separators,
		array $manual_substitutions = [],
		array $remove_chars = [],
		array $remove_prefixes = [],
		array $remove_suffixes = [],
	): array {
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

		// Check for manual substitutions after cleaning but before processing separators.
		if ( isset( $manual_substitutions[ $byline ] ) ) {
			return $manual_substitutions[ $byline ];
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
					$this->parse_byline( $part, $separators, $manual_substitutions, $remove_chars, $remove_prefixes, $remove_suffixes )
				);
			}
			
			return $result;
		}
		
		// After recursion -- if no more separators are found, return the cleaned byline.
		$byline = trim( $byline );

		return [ $byline ];
	}
}
