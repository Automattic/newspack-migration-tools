<?php

namespace Newspack\MigrationTools\Util;

use Newspack\MigrationTools\Log\CliLogger;

class MigrationMetaForCommand {
	private string $command_name;
	private int $command_version;

	private bool $refresh_existing = false;

	public function __construct( string $command_name, int $command_version ) {
		$this->command_name    = $command_name;
		$this->command_version = $command_version;
	}

	public function set_refresh_existing( bool $refresh_existing ): void {
		$this->refresh_existing = $refresh_existing;
	}

	public function get_suggested_logfile_name(): string {
		$logfile_name = $this->command_name . '-' . $this->command_version;
		if ( $this->refresh_existing ) {
			$logfile_name .= '-refresh-existing';
		}

		return str_replace( '_', '-', sanitize_title( $logfile_name ) ) . '.log';
	}

	public function should_skip_post( int $post_id ): bool {
		if ( $this->refresh_existing ) {
			return false;
		}
		$post_version = MigrationMeta::get( $post_id, $this->command_name, 'post' );
		if ( empty( $post_version ) ) {
			// No metadata set for that command at all – we should not skip.
			return false;
		}

		// If the post version is higher than the command version, we should skip.
		return $post_version > $this->command_version;
	}

	public function log_skip_post_id( int $post_id ): void {
		CliLogger::line(
			sprintf(
				'Skipping post %d, already at MigrationMeta %s for command %s.',
				$post_id,
				$this->command_version,
				$this->command_name
			)
		);
	}

	/**
	 * Set the next version numnber on a post.
	 *
	 * Please note that this will always simply add 1 to the instance of this class's command version.
	 * That means if you call this over and over you will still update to the same version.
	 *
	 * @param int $post_id The post ID.
	 */
	public function set_next_version_on_post( int $post_id ): void {
		MigrationMeta::update( $post_id, $this->command_name, 'post', ( $this->command_version + 1 ) );
	}
}
