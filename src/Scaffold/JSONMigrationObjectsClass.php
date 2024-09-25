<?php

namespace Newspack\MigrationTools\Scaffold;

use Exception;
use NewspackCustomContentMigrator\Utils\CommonDataFileIterator\FileImportFactory;

class JSONMigrationObjectsClass extends AbstractMigrationObjects implements JSONMigrationObjects {

	const MIGRATION_JSON_FILE_KEY                       = 'migration_json_file';
	const MIGRATION_JSON_FILE_PATH_KEY                  = 'migration_json_file_path';
	const MIGRATION_JSON_FILE_POINTER_TO_IDENTIFIER_KEY = 'migration_json_file_pointer_to_identifier';

	protected string $path_to_json_file;

	protected string $pointer_to_identifier;

	/**
	 * @throws Exception
	 */
	public function __construct( MigrationRunKey $run_key, string $path_to_json_file, string $pointer_to_identifier = 'id' ) {
		parent::__construct( [], $run_key );

		$result = $this->save( $path_to_json_file, $pointer_to_identifier );

		if ( ! $result ) {
			throw new Exception( 'Failed to save JSON migration objects.' );
		}
	}

	/**
	 * Saves the migration JSON to DB.
	 *
	 * @param string $full_path The full local path to the migration JSON.
	 * @param string $pointer_to_identifier The JSON attribute pointer that can be used to uniquely identify a JSON object.
	 *
	 * @return bool
	 */
	public function save( string $full_path, string $pointer_to_identifier = 'id' ): bool {
		if ( ! file_exists( $full_path ) ) {
			return false;
		}

		$meta = [
			[
				'option_name'  => $this->get_run_key()->get() . '_' . self::MIGRATION_JSON_FILE_PATH_KEY,
				'option_value' => $full_path,
				'autoload'     => 'no',
			],
			[
				'option_name'  => $this->get_run_key()->get() . '_' . self::MIGRATION_JSON_FILE_POINTER_TO_IDENTIFIER_KEY,
				'option_value' => $pointer_to_identifier,
				'autoload'     => 'no',
			],
		];

		global $wpdb;

		foreach ( $meta as $meta_item ) {
			extract( $meta_item );
			$option_exists = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $wpdb->options WHERE option_name = %s",
					$option_name
				)
			);

			if ( $option_exists ) {
				if ( $option_exists->option_value !== $option_value ) {
					// TODO - trying to reuse migration run with new params. Either new params should be ignored or new migration run is required.
					return false;
				} else {
					continue;
				}
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert( $wpdb->options, $meta_item );
			}
		}

		$this->path_to_json_file     = $full_path;
		$this->pointer_to_identifier = $pointer_to_identifier;

		return true;
	}

	/**
	 * Gets the JSON attribute pointer that can be used to uniquely identify a JSON object.
	 *
	 * @return string
	 */
	public function get_pointer_to_identifier(): string {
		if ( ! isset( $this->pointer_to_identifier ) ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$pointer_to_identifier = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
					$this->get_run_key()->get() . '_' . self::MIGRATION_JSON_FILE_POINTER_TO_IDENTIFIER_KEY
				)
			);

			if ( ! $pointer_to_identifier ) {
				$pointer_to_identifier = 'id';
			}

			$this->pointer_to_identifier = $pointer_to_identifier;
		}

		return $this->pointer_to_identifier;
	}

	/**
	 * Gets all migration objects.
	 *
	 * @return MigrationObject[]
	 */
	public function get_all(): iterable {
		if ( ! isset( $this->path_to_json_file ) ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$path_to_json_file = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
					$this->get_run_key()->get() . '_' . self::MIGRATION_JSON_FILE_PATH_KEY
				)
			);

			if ( ! $path_to_json_file ) {
				return [];
			}

			$this->path_to_json_file = $path_to_json_file;
		}

		foreach ( ( new FileImportFactory() )->get_file( $this->path_to_json_file )->getIterator() as $json_object ) {
			yield new MigrationObjectClass( $this->get_run_key(), $json_object, $this->get_pointer_to_identifier() );
		}
	}
}
