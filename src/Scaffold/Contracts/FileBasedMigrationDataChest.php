<?php

namespace Newspack\MigrationTools\Scaffold\Contracts;

interface FileBasedMigrationDataChest extends MigrationDataChest {

	/**
	 * Returns the full path to the file.
	 *
	 * @return string
	 */
	public function get_full_path(): string;

	/**
	 * Returns the file name without the extension.
	 *
	 * @return string
	 */
	public function get_file_name(): string;

	/**
	 * Returns the file extension.
	 *
	 * @return string
	 */
	public function get_extension(): string;

	/**
	 * Returns the file name with extension, but without the parent directory.
	 *
	 * @return string
	 */
	public function get_basename(): string;

	/**
	 * Returns the parent directory of the file.
	 *
	 * @return string
	 */
	public function get_parent_directory(): string;
}
