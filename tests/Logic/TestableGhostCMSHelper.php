<?php

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\MigrationTools\Logic\GhostCMSHelper;

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
	 *
	 * @param string $url The image URL.
	 * @return int Attachment ID from the mock map, or 0 if not found.
	 */
	protected function get_attachment_id_from_url( string $url ): int {
		return $this->url_to_attachment_map[ $url ] ?? 0;
	}
}
