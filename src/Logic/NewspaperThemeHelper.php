<?php

namespace Newspack\MigrationTools\Logic;

use Exception;
use Newspack\MigrationTools\Log\CliLogger;
use Newspack\MigrationTools\Log\FileLogger;
use Newspack\MigrationTools\Util\MigrationMetaForCommand;

/**
 * Helper class for migrating data from the Newspaper theme to Newspack.
 */
class NewspaperThemeHelper {

	/**
	 * Migrates the subtitle from the Newspaper theme to the Newspack subtitle on a post
	 *
	 * @param int                     $post_id             The post ID.
	 * @param array                   $post_theme_metadata The value of the `td_post_theme_settings` meta for the post.
	 * @param MigrationMetaForCommand $command_meta        An instance of MigrationMetaForCommand with the version and command name values.
	 *
	 * @return void
	 */
	public function migrate_subtitle_to_newspack_subtitle( int $post_id, array $post_theme_metadata, MigrationMetaForCommand $command_meta ): void {

		if ( $command_meta->should_skip_post( $post_id ) ) {
			$command_meta->log_skip_post_id( $post_id );

			return;
		}

		$subtitle = $post_theme_metadata['td_subtitle'] ?? false;

		if ( $subtitle ) {
			update_post_meta( $post_id, 'newspack_post_subtitle', $subtitle );
			FileLogger::log( $command_meta->get_suggested_logfile_name(), sprintf( 'Migrated subtitle for post %d.', $post_id ) );
		}
		$command_meta->set_next_version_on_post( $post_id );
	}
}
