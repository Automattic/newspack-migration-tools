<?php

namespace Newspack\MigrationTools\Scaffold;

use Newspack\MigrationTools\Scaffold\Contracts\MigrationObject as MigrationObjectContract;
use WP_Filesystem_Base;

/**
 * JSON Directory Migration Data Container.
 *
 * This class handles a directory containing multiple JSON files for migration.
 */
class JSONDirectoryMigrationDataChest extends AbstractMigrationDataChest {

	/**
	 * The source type for the data container.
	 *
	 * @var string $source_type The source type for the data container.
	 */
	protected string $source_type = 'JSON_DIRECTORY';

	/**
	 * Constructor.
	 *
	 * @param string $directory_path Path to the directory containing JSON files.
	 * @param string $pointer_to_identifier Pointer to the identifier.
	 * @throws \Exception If the provided path is not a directory or does not exist.
	 */
	public function __construct( string $directory_path, string $pointer_to_identifier ) {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		/* @var $wp_filesystem WP_Filesystem_Base */
		if ( ! $wp_filesystem->is_dir( $directory_path ) ) {
			throw new \Exception( 'The provided path is not a directory or does not exist.' );
		}

		$combined_data = [];
		$files         = $wp_filesystem->dirlist( $directory_path );

		foreach ( $files as $file ) {
			if ( ! preg_match( '/\.json$/i', $file['name'] ) ) {
				continue;
			}

			$file_path    = trailingslashit( $directory_path ) . $file['name'];
			$file_content = $wp_filesystem->get_contents( $file_path );
			$json_data    = json_decode( $file_content, true );

			$combined_data[] = $json_data;
		}

		parent::__construct( $combined_data, $pointer_to_identifier );
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
