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
	 *     but you wish to preserve 'Arthur Author, Ph.D.' as a single author name, or even remove the
	 *     'Ph.D.' suffix.   * 
	 *
	 * @param string $byline          Byline with one or multiple authors.
	 * @param array  $separators      Separators to explode. Typical separators could be:
	 *                                  [ '&', ', and ', ',', ' and ', ' y ' ].
	 *                                ⚠️ Important:
	 *                                  - the order of separators matters. One separator is a substring of
	 *                                    another separator (e.g. ',' and ', and'), make sure to explode by
	 *                                    the longer separator first to avoid incorrect splitting.
	 *                                  - be mindful of spaces used to surround separators (e.g. ' and '
	 *                                    vs 'and').
	 * @param array  $manual_bylines  Keys are bylines and values are resulting author names.
	 *                                If there are some bylines that require special handling, you can
	 *                                specify the entire byline as a key, and the final author names as
	 *                                subarray with one or more values.
	 *                                This will skip parsing these bylines and just return them,
	 *                                e.g. [ 'Arthur Author, Ph.D.' => [ 'Arthur Author' ] ].
	 * @param array  $remove_prefixes Prefixes to remove from beginning of byline's author names,
	 *                                case-insensitive. E.g. 'By ' or 'Byline: '.
	 * @param array  $remove_suffixes Suffixes to remove from end of byline's author names,
	 *                                case-insensitive. E.g. ', Daily News', or '| Daily News', etc.
	 * @param array  $remove_chars    Characters to remove. Unsupported polluting characters found in
	 *                                byline metas.
	 * 
	 * @return string[]               Exploded author names from byline.
	 */
	public function parse_byline(
		string $byline,
		array $separators,
		array $manual_bylines = [],
		array $remove_prefixes = [],
		array $remove_suffixes = [],
		array $remove_chars = [],
	): array {
		if ( empty( $byline ) ) {
			return [];
		}
		
		// Remove unsupported characters.
		$byline = str_replace( $remove_chars, '', $byline );

		// Trim.
		$byline = trim( $byline );
		
		// Check for manual substitutions before processing.
		if ( isset( $manual_bylines[ $byline ] ) ) {
			return $manual_bylines[ $byline ];
		}

		// Remove prefixes and suffixes case-insensitively.
		foreach ( $remove_prefixes as $prefix ) {
			$byline = preg_replace( '/^' . preg_quote( $prefix, '/' ) . '/i', '', $byline );
		}
		foreach ( $remove_suffixes as $suffix ) {
			$byline = preg_replace( '/' . preg_quote( $suffix, '/' ) . '$/i', '', $byline );
		}
		
		// Trim again after removing prefixes and suffixes.
		$byline = trim( $byline );

		// Explode by multiple separators.
		foreach ( $separators as $separator ) {
			if ( false === stripos( $byline, $separator ) ) {
				continue;
			}

			// Explode and trim each exploded part.
			$byline_exploded_parts = array_map( 'trim', explode( $separator, $byline ) );
			
			// Recursively parse each exploded part to explode everything by all separators.
			$result = [];
			foreach ( $byline_exploded_parts as $part ) {
				$result = array_merge(
					$result,
					$this->parse_byline( $part, $separators, $manual_bylines, $remove_chars, $remove_prefixes, $remove_suffixes )
				);
			}
			
			return $result;
		}
		
		// After recursion -- if no more separators are found, return the trimmed byline.
		$byline = trim( $byline );

		return [ $byline ];
	}
}
