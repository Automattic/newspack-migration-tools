<?php

namespace Newspack\MigrationTools\Scaffold;

use Exception;
use Newspack\MigrationTools\Scaffold\Contracts\FileBasedMigrationDataChest;
use WP_Filesystem_Base;

/**
 * JSON Migration Data Container.
 */
class JSONFileMigrationDataChest extends JSONMigrationDataChest implements FileBasedMigrationDataChest {

	/**
	 * The full path to the JSON file.
	 *
	 * @var string $full_path The full path to the JSON file.
	 */
	protected string $full_path;

	/**
	 * Constructor.
	 *
	 * @param string $file_path Path to the JSON file.
	 * @param string $pointer_to_identifier Pointer to the identifier.
	 *
	 * @throws Exception If the file does not exist.
	 */
	public function __construct( string $file_path, string $pointer_to_identifier ) {
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		/* @var $wp_filesystem WP_Filesystem_Base */
		if ( ! $wp_filesystem->is_file( $file_path ) ) {
			throw new Exception(
				sprintf(
					'File `%s` does not exist.',
					$file_path // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				)
			);
		}

		$this->full_path = $file_path;
		$this->byte_size = filesize( $file_path );

		parent::__construct( $wp_filesystem->get_contents( $file_path ), $pointer_to_identifier );
	}

	/**
	 * Returns the full path to the file.
	 *
	 * @return string
	 */
	public function get_full_path(): string {
		return $this->full_path;
	}

	/**
	 * Returns the file name without the extension.
	 *
	 * @return string
	 */
	public function get_file_name(): string {
		return pathinfo( $this->get_full_path(), PATHINFO_FILENAME );
	}

	/**
	 * Returns the file extension.
	 *
	 * @return string
	 */
	public function get_extension(): string {
		return pathinfo( $this->get_full_path(), PATHINFO_EXTENSION );
	}

	/**
	 * Returns the file name with extension, but without the parent directory.
	 *
	 * @return string
	 */
	public function get_basename(): string {
		return pathinfo( $this->get_full_path(), PATHINFO_BASENAME );
	}

	/**
	 * Returns the parent directory of the file.
	 *
	 * @return string
	 */
	public function get_parent_directory(): string {
		return pathinfo( $this->get_full_path(), PATHINFO_DIRNAME );
	}
}
