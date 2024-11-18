<?php

namespace Newspack\MigrationTools\Scaffold;

use Newspack\MigrationTools\Scaffold\Contracts\MigrationObject as MigrationObjectContract;
use WP_Filesystem_Base;

/**
 * JSON Migration Data Container.
 */
class JSONMigrationDataChest extends AbstractMigrationDataChest {

	/**
	 * The source type for the data container.
	 *
	 * @var string $source_type The source type for the data container.
	 */
	protected string $source_type = 'JSON';

	/**
	 * Constructor.
	 *
	 * @param string $path_or_json Path to the JSON file or JSON string.
	 * @param string $pointer_to_identifier Pointer to the identifier.
	 */
	public function __construct( string $path_or_json, string $pointer_to_identifier ) {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		/* @var $wp_filesystem WP_Filesystem_Base */
		if ( $wp_filesystem->is_file( $path_or_json ) ) {
			$path_or_json = json_decode( $wp_filesystem->get_contents( $path_or_json ), true );
		} else {
			$path_or_json = json_decode( $path_or_json, true );
		}

		parent::__construct( $path_or_json, $pointer_to_identifier );
	}

	/**
	 * Gets all migration objects.
	 *
	 * @return MigrationObjectContract[]
	 */
	public function get_all(): array {
		return array_map(
			fn( $json ) => new MigrationObject( $json, $this->get_pointer_to_identifier(), $this ),
			(array) $this->get_raw_data()
		);
	}
}