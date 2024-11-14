<?php

namespace Newspack\MigrationTools\Scaffold\WordPressData;

use Exception;
use Newspack\MigrationTools\Scaffold\Contracts\RunAwareMigrationObject;
use Newspack\MigrationTools\Scaffold\MigrationObjectPropertyWrapper;
use Newspack\MigrationTools\Scaffold\Singletons\WordPressData;
use WP_Error;

/**
 * AbstractWordPressData.
 */
abstract class AbstractWordPressData {

	/**
	 * The WordPress database object.
	 *
	 * @var \wpdb $wpdb The WordPress database object.
	 */
	protected \wpdb $wpdb;

	/**
	 * The table name.
	 *
	 * @var string $table_name The table name.
	 */
	protected string $table_name;

	/**
	 * The primary key.
	 *
	 * @var string $primary_key The primary key.
	 */
	protected string $primary_key;

	/**
	 * The data to be inserted/updated.
	 *
	 * @var array $data The data.
	 */
	protected array $data = [];

	/**
	 * The data sources.
	 *
	 * @var array $data_sources The data sources.
	 */
	protected array $data_sources = [];

	/**
	 * The migration object.
	 *
	 * @var RunAwareMigrationObject|null $migration_object The migration object.
	 */
	protected ?RunAwareMigrationObject $migration_object;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Gets the table name.
	 *
	 * @return string The table name.
	 */
	public function get_table_name(): string {
		return $this->table_name;
	}

	/**
	 * Gets the primary key.
	 *
	 * @return string The primary key.
	 */
	public function get_primary_key(): string {
		return $this->primary_key;
	}

	/**
	 * Gets the primary ID.
	 *
	 * @return int|null The primary ID.
	 */
	public function get_primary_id(): ?int {
		return $this->data[ $this->get_primary_key() ] ?? null;
	}

	/**
	 * Gets the data.
	 *
	 * @return array The data.
	 */
	public function get_data(): array {
		return $this->data;
	}

	/**
	 * Sets the migration object.
	 *
	 * @param RunAwareMigrationObject $migration_object The migration object.
	 */
	public function set_migration_object( RunAwareMigrationObject $migration_object ): void {
		$this->migration_object = $migration_object;
	}

	/**
	 * Gets the migration object.
	 *
	 * @return RunAwareMigrationObject The migration object.
	 */
	public function get_migration_object(): RunAwareMigrationObject {
		return $this->migration_object;
	}


	/**
	 * Creates a new record.
	 *
	 * @return int|WP_Error The ID of the created record or a WP_Error object.
	 * @throws Exception If the migration object has not been set.
	 */
	public function create(): int|WP_Error {
		if ( ! $this->get_migration_object() ) {
			throw new Exception( 'MigrationObject has not been set.' );
		}

		$maybe_created = $this->wpdb->insert( $this->get_table_name(), $this->get_data() );

		if ( 1 === $maybe_created ) {
			$id = $this->wpdb->insert_id;

			foreach ( $this->data_sources as $key => $source ) {
				$this->wpdb->insert(
					'migration_destination_sources',
					[
						'migration_object_id'       => $this->get_migration_object()->get_id(),
						'wordpress_table_column_id' => WordPressData::get_instance()->get_column_id( $this->get_table_name(), $key ),
						'wordpress_object_id'       => $id,
						'json_path'                 => $source,
					]
				);
			}

			$this->data             = [];
			$this->data_sources     = [];
			$this->migration_object = null;

			return $id;
		}

		return new WP_Error( 'failed_to_create', $this->wpdb->last_error );
	}

	/**
	 * Updates an existing record.
	 *
	 * @return bool|WP_Error True if the record was updated, a WP_Error object otherwise.
	 */
	public function update(): bool|WP_Error {
		if ( null === $this->get_primary_id() ) {
			// TODO perhaps we use $migration_object to try and obtain a primary ID (via `migration_destination_sources`)?
			return new WP_Error( 'missing_primary_key', 'Primary key not found in data.' );
		}

		$update_data = $this->get_data();
		$primary_id  = $this->get_primary_id();
		unset( $update_data[ $this->get_primary_key() ] );

		$maybe_updated = $this->wpdb->update(
			$this->get_table_name(),
			$update_data,
			[ $this->get_primary_key() => $primary_id ]
		);

		if ( false === $maybe_updated ) {
			return new WP_Error( 'failed_to_update', $this->wpdb->last_error );
		}

		foreach ( $this->data_sources as $key => $source ) {
			// phpcs:disable
			$existing_source = $this->wpdb->get_row(
				$this->wpdb->prepare(
					'SELECT * FROM migration_destination_sources WHERE migration_object_id = %d AND wordpress_table_column_id = %d AND wordpress_object_id = %d',
					$this->get_migration_object()->get_id(),
					WordPressData::get_instance()->get_column_id( $this->get_table_name(), $key ),
					$primary_id
				)
			);
			// phpcs:enable

			if ( $existing_source ) {
				$this->wpdb->delete(
					'migration_destination_sources',
					[ 'id' => $existing_source->id ]
				);
			}

			$this->wpdb->insert(
				'migration_destination_sources',
				[
					'migration_object_id'       => $this->get_migration_object()->get_id(),
					'wordpress_table_column_id' => WordPressData::get_instance()->get_column_id( $this->get_table_name(), $key ),
					'wordpress_object_id'       => $primary_id,
					'json_path'                 => $source,
				]
			);
		}

		$this->data             = [];
		$this->data_sources     = [];
		$this->migration_object = null;

		return true;
	}

	/**
	 * Deletes a record.
	 *
	 * @return bool|WP_Error True if the record was deleted, a WP_Error object otherwise.
	 */
	public function delete(): bool|WP_Error {
		if ( null === $this->get_primary_id() ) {
			return new WP_Error( 'missing_primary_key', 'Primary key not found in data.' );
			// TODO perhaps we use $migration_object to try and obtain a primary ID (via `migration_destination_sources`)?
		}

		$maybe_deleted = $this->wpdb->delete(
			$this->get_table_name(),
			[ $this->get_primary_key() => $this->get_primary_id() ]
		);

		if ( false === $maybe_deleted ) {
			return new WP_Error( 'failed_to_delete', $this->wpdb->last_error );
		}

		return true;
	}

	/**
	 * Magic method to check if a property is set.
	 *
	 * @param string $name Property name.
	 *
	 * @return bool
	 */
	public function __isset( string $name ) {
		return $this->is_property_set( $name );
	}

	/**
	 * Magic method to get properties.
	 *
	 * @param string $name Property name.
	 *
	 * @return mixed
	 */
	public function __get( string $name ) {
		if ( $this->__isset( $name ) ) {
			return $this->data[ $name ];
		}

		return null;
	}

	/**
	 * Magic method to set properties.
	 *
	 * @param string $name Property name.
	 * @param mixed  $value Property value.
	 *
	 * @return void
	 */
	public function __set( string $name, mixed $value ) {
		$this->set_property( $name, $value );
	}

	/**
	 * Magic method to unset properties.
	 *
	 * @param string $name Property name.
	 *
	 * @return void
	 */
	public function __unset( string $name ) {
		$this->unset_property( $name );
	}

	/**
	 * Sets a property.
	 *
	 * @param string $name The property name.
	 * @param mixed  $value The property value.
	 */
	protected function set_property( string $name, mixed $value ): void {
		if ( $value instanceof MigrationObjectPropertyWrapper ) {
			$this->data_sources[ $name ] = $value->get_path();

			$value = $value->get_value();
		}

		if ( is_string( $value ) && str_contains( '<mig_scaf>', $value ) ) {
			preg_match_all( '/<mig_scaf>(.*?)<\/mig_scaf>/', $value, $matches );

			// TODO finish implementing this. This should add support for concatenated values. We will have to check for the custom
			// tag <mig_scaf> and <mig_scaf><property> and then parse the string in between. It could be a JSON value,
			// it could be a regular string. We should be able to obtain the path and the value after parsing.
		}

		$this->data[ $name ] = $value;
	}

	/**
	 * Checks if a property is set.
	 *
	 * @param string $name The property name.
	 *
	 * @return bool True if the property is set, false otherwise.
	 */
	protected function is_property_set( string $name ): bool {
		return isset( $this->data[ $name ] );
	}

	/**
	 * Unsets a property.
	 *
	 * @param string $name The property name.
	 */
	protected function unset_property( string $name ): void {
		unset( $this->data[ $name ] );
		unset( $this->data_sources[ $name ] );
	}
}
