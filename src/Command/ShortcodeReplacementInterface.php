<?php

namespace Newspack\MigrationTools\Command;

/**
 * Used by the ShortcodesMigrator, `replace-shortcodes-in-post-body` command's `replace-callback` argument.
 * Create a class method by implementing this interface which will take a shortcode name and a post_id where
 * the shortcode is located, and return a replacement for that shortcode.
 */
interface ShortcodeReplacementInterface {
	/**
	 * Callback which generates a replacement for a shortcode.
	 *
	 * @param string $shortcode_name Shortcode name.
	 * @param int    $post_id        Post ID where the shortcode is being replaced. Use this if needed for more context,
	 *                               e.g. if you wish to get the post author, title, or anything that might be required
	 *                               to generate the replacement.
	 * @return string HTML replacement for the shortcode.
	 */
	public function replace_shortcode( string $shortcode_name, int $post_id ): string;
}
