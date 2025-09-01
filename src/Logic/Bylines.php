<?php

namespace Newspack\MigrationTools\Logic;

/**
 * Bylines logic.
 */
class Bylines {

	/**
	 * Parses byline string into author names:
	 *   - can explode by multiple separators,
	 *   - trims each individual exploded part/author name,
	 *   - can optionally clean unsupported characters,
	 *   - can remove various prefixes and suffixes,
	 *   - can work with manually provided exceptions which override parsing.
	 *
	 * @param string $byline            Byline with one or multiple authors names.
	 * @param array  $separators        Separators by which to explode. Typical separators could be:
	 *                                  [ '&', ', and ', ',', ' and ', ' y ' ].
	 *                                  ⚠️ Important:
	 *                                  - the order of separators matters. If one separator is a substring of
	 *                                    another separator (e.g. ',' and ', and') make sure to provide and
	 *                                    explode by the longer separator first (first ', and', then ',').
	 *                                  - be mindful of spaces used to surround separators, e.g. use ' and '
	 *                                    not 'and'.
	 * @param array  $manual_exceptions If there are some bylines that require special handling, you can
	 *                                  specify the entire byline and resulting authors. These bylines will
	 *                                  be skipped from parsing and just returned as is. Keys are bylines and
	 *                                  values are subarrays with resulting author names. E.g. two exceptions:
	 *                                  [
	 *                                    'John Doe, Jr., Jane Doe' => [ 'John Doe Jr.', 'Jane Doe' ],
	 *                                    'John Doe Jane Doe' => [ 'John Doe', 'Jane Doe' ]
	 *                                  ].
	 * @param array  $remove_prefixes   Prefixes to remove from beginning of byline's author names,
	 *                                  case-insensitive. E.g. 'By ' or 'Byline: '.
	 * @param array  $remove_suffixes   Suffixes to remove from end of byline's author names,
	 *                                  case-insensitive. E.g. ', Daily News', or '| Daily News', etc.
	 * @param array  $remove_chars      Characters to remove. Unsupported polluting characters found in
	 *                                  byline metas.
	 * 
	 * @return string[]                 Exploded author names from byline.
	 */
	public function parse_byline(
		string $byline,
		array $separators,
		array $manual_exceptions = [],
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
		
		// Check for manual exceptions before processing.
		if ( isset( $manual_exceptions[ $byline ] ) ) {
			return $manual_exceptions[ $byline ];
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
					$this->parse_byline( $part, $separators, $manual_exceptions, $remove_prefixes, $remove_suffixes, $remove_chars )
				);
			}
			
			return $result;
		}
		
		// After recursion -- if no more separators are found, return the trimmed byline.
		$byline = trim( $byline );

		return [ $byline ];
	}
}
