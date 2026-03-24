<?php

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\MigrationTools\Logic\GhostCMSHelper;
use WP_Error;

/**
 * Testable subclass that allows mocking attachment lookups.
 */
class TestableGhostCMSHelper extends GhostCMSHelper {

	/**
	 * Map of image URLs to attachment IDs for mocking.
	 *
	 * @var array<string, int>
	 */
	private array $url_to_attachment_map = [];

	/**
	 * Set the URL to attachment ID map for mocking.
	 *
	 * @param array<string, int> $map URL => attachment ID mapping.
	 * @return void
	 */
	public function set_attachment_map( array $map ): void {
		$this->url_to_attachment_map = $map;
	}

	/**
	 * Override to return mocked attachment IDs.
	 */
	protected function get_or_import_url( string $path, string $title, ?string $caption = null, ?string $description = null, ?string $alt = null, int $post_id = 0 ): int|WP_Error {
		return $this->url_to_attachment_map[ $path ] ?? 0;
	}
}
