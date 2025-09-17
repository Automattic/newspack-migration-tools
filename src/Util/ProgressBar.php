<?php

namespace Newspack\MigrationTools\Util;

use cli\progress\Bar as WP_CLI_ProgressBar;

/**
 * Wrapper for WP_CLI\Utils\make_progress_bar.
 */
class ProgressBar {
	/**
	 * @var WP_CLI_ProgressBar
	 */
	private WP_CLI_ProgressBar $progress_bar;

	/**
	 * Constructor.
	 */
	public function __construct(
		private string $label,
		private int $total_count
	) {
		$this->progress_bar = \WP_CLI\Utils\make_progress_bar( $this->label, $this->total_count, 1 );
	}

	/**
	 * Advance the progress bar by one step.
	 * 
	 * @return void
	 */
	public function advance(): void {
		static $index = 0;

		$this->progress_bar->tick( 1, $this->get_label( ++$index ) );

		usleep( 100000 ); // sleep for 0.1 seconds

		\WP_CLI::line( '' );
	}

	/**
	 * Finish the progress bar.
	 * 
	 * @return void
	 */
	public function finish(): void {
		$this->progress_bar->finish();
	}

	/**
	 * Constructs the progress bar label.
	 * 
	 * @param  int $index  The current index of the progress.
	 * @return string      The formatted label for the progress bar.
	 */
	private function get_label( int $index ): string {
		return sprintf(
			'[Memory Usage: %s] %s (%d/%d)',
			size_format( memory_get_usage( true ) ),
			$this->label,
			$index,
			$this->total_count
		);
	}
}
