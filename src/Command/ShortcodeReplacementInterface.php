<?php

namespace Newspack\MigrationTools\Command;

/**
 * Used by the ShortcodesMigrator, `replace-shortcodes-in-post-body` command's `replace-callback` argument.
 * Create a class method by implementing this interface which will take a shortcode string and the post ID where
 * this shortcode is located, and return a replacement for the shortcode.
 */
interface ShortcodeReplacementInterface {
	/**
	 * Return a replacement for a shortcode.
	 *
	 * @param string $shortcode Shortcode string.
	 * @param int    $post_id   Post ID where the shortcode is being replaced. Use for more context if needed,
	 *                          e.g. to get the post author, title, or anything that might be required
	 *                          to generate the replacement.
	 * @return string|false Any HTML replacement for the shortcode. False if no adequate replacement is generated.
	 */
	public function replace_shortcode( string $shortcode, int $post_id ): string|false;
}
