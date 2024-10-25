<?php

namespace Newspack\MigrationTools\Logic;

use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\Log\MultiLog;
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
		$cli_logger = CliLog::get_logger( 'newspaper-theme-subtitle-migrate' );

		if ( $command_meta->should_skip_post( $post_id ) ) {
			$cli_logger->notice(
				sprintf(
					'Skipping post %d, already at MigrationMeta %s for command %s.',
					$post_id,
					$command_meta->get_command_version(),
					$command_meta->get_command_name()
				)
			);

			return;
		}
		$file_logger  = FileLog::get_logger( $command_meta->get_suggested_log_name(), $command_meta->get_suggested_log_name() );
		$multi_logger = MultiLog::get_logger( 'newspaper-theme-subtitle-multi', [ $cli_logger, $file_logger ] );

		$subtitle = $post_theme_metadata['td_subtitle'] ?? false;

		if ( $subtitle ) {
			update_post_meta( $post_id, 'newspack_post_subtitle', $subtitle );
			$multi_logger->info( sprintf( 'Migrated subtitle for post %d.', $post_id ) );
		}
		$command_meta->set_next_version_on_post( $post_id );
	}
}
