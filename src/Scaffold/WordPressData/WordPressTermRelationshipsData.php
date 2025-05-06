<?php

namespace Newspack\MigrationTools\Scaffold\WordPressData;

use Exception;
use JetBrains\PhpStorm\ArrayShape;
use Newspack\MigrationTools\Scaffold\MigrationObjectPropertyWrapper;
use Newspack\MigrationTools\Scaffold\Singletons\WordPressData;
use WP_Error;

/**
 * Class WordPressTermRelationshipsData.
 *
 * @readonly int $virtual_primary_key
 * @property int $object_id
 * @property int $term_taxonomy_id
 * @property int $term_order
 */
class WordPressTermRelationshipsData extends AbstractWordPressData {

	/**
	 * Special view table created to hold virtual primary keys for the term_relationships table.
	 *
	 * @var string $view_table_name The name of the view table created to hold virtual primary keys for the term_relationships table.
	 */
	private string $view_table_name;

	/**
	 * WordPressTermRelationshipsData constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->primary_key = 'virtual_primary_key';
	}


	/**
	 * Sets the object_id.
	 *
	 * @param int|MigrationObjectPropertyWrapper $object_id The object_id.
	 *
	 * @return WordPressTermRelationshipsData
	 */
	public function set_object_id( int|MigrationObjectPropertyWrapper $object_id ): WordPressTermRelationshipsData {
		$this->set_property( 'object_id', $object_id );

		return $this;
	}

	/**
	 * Sets the term_taxonomy_id.
	 *
	 * @param int|MigrationObjectPropertyWrapper $term_taxonomy_id The term_taxonomy_id.
	 *
	 * @return WordPressTermRelationshipsData
	 */
	public function set_term_taxonomy_id( int|MigrationObjectPropertyWrapper $term_taxonomy_id ): WordPressTermRelationshipsData {
		$this->set_property( 'term_taxonomy_id', $term_taxonomy_id );

		return $this;
	}

	/**
	 * Sets the term_order.
	 *
	 * @param int|MigrationObjectPropertyWrapper $term_order The term_order.
	 *
	 * return WordPressTermRelationshipsData
	 */
	public function set_term_order( int|MigrationObjectPropertyWrapper $term_order ): WordPressTermRelationshipsData {
		$this->set_property( 'term_order', $term_order );

		return $this;
	}

	/**
	 * Returns the virtual primary key for the given object_id and term_taxonomy_id.
	 *
	 * @return int|null
	 */
	public function get_primary_id(): ?int {
		if ( array_key_exists( 'object_id', $this->data ) && array_key_exists( 'term_taxonomy_id', $this->data ) ) {
			return static::get_virtual_primary_key( $this->data['object_id'], $this->data['term_taxonomy_id'] );
		}

		return null;
	}

	/**
	 * Returns the name for the view table created to hold virtual primary keys for the term_relationships table.
	 *
	 * @return string
	 */
	public function get_view_table_name(): string {
		if ( ! isset( $this->view_table_name ) ) {
			$this->view_table_name = "{$this->wpdb->prefix}term_relationships_view";
		}

		return $this->view_table_name;
	}

	/**
	 * Creates a new row in the term_relationships table based on the set properties.
	 *
	 * @return int|WP_Error Returns the virtual primary key for the new row.
	 * @throws Exception If MigrationObject is not set, other WordPress objects were created already, or if we failed to insert the row.
	 */
	public function create(): int|WP_Error {
		if ( ! $this->get_migration_object() ) {
			throw new Exception( 'MigrationObject has not been set.' );
		}

		$pre_existing_object_id = $this->get_wordpress_object_id_from_migration_object();
		if ( ! empty( $pre_existing_object_id ) ) {
			$column_values = static::get_column_values_from_virtual_primary_key( $pre_existing_object_id );
			throw new Exception(
				sprintf(
					'A `%s` (`%s`:%d, `%s`:%d) relationship already exists based off of the set Migration Object (Legacy ID: %s)',
					$this->get_table_name(), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					'object_id', // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$column_values['object_id'], // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					'term_taxonomy_id', // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$column_values['term_taxonomy_id'], // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$this->get_migration_object()->get_data_id() // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				)
			);
		}

		// phpcs:disable -- properly formatted and escaped.
		$maybe_exists  = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->get_table_name()} 
         			WHERE object_id = %d 
         			  AND term_taxonomy_id = %d",
				$this->data['object_id'],
				$this->data['term_taxonomy_id']
			)
		);
		// phpcs:enable
		$maybe_created = empty( $maybe_exists ) ? $this->wpdb->insert( $this->get_table_name(), $this->get_data() ) : 1;

		if ( 1 === $maybe_created ) {
			$virtual_primary_key = static::get_virtual_primary_key( $this->data['object_id'], $this->data['term_taxonomy_id'] );

			foreach ( $this->data_sources as $key => $source ) {
				if ( ! is_array( $source ) ) {
					$source = [ $source ];
				}

				foreach ( $source as $path ) {
					$this->wpdb->insert(
						'migration_destination_sources',
						[
							'migration_object_id'       => $this->get_migration_object()->get_id(),
							'wordpress_table_column_id' => WordPressData::get_instance()->get_column_id( $this->get_table_name(), $key ),
							'wordpress_object_id'       => $virtual_primary_key,
							'json_path'                 => $path,
						]
					);
				}
			}

			$this->data             = [];
			$this->data_sources     = [];
			$this->migration_object = null;

			return $virtual_primary_key;
		}

		throw new Exception(
			sprintf(
				'Failed to create %s data: %s',
				$this->get_table_name(), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				$this->wpdb->last_error // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			)
		);
	}

	/**
	 * Updates are not supported for WordPressTermRelationshipsData. Use `delete` to clear old data, then `create`.
	 *
	 * @return bool|WP_Error Will not return anything.
	 * @throws Exception Updates are not supported for WordPressTermRelationshipsData. Use `delete` to clear old data, then `create`.
	 */
	public function update(): bool|WP_Error {
		throw new Exception( 'Update is not supported for WordPressTermRelationshipsData. Use `delete` to clear old data, then `create`.' );

		return false; // phpcs:ignore Squiz.PHP.NonExecutableCode.Unreachable
	}

	/**
	 * Deletes the row in the term_relationships table based on the set properties.
	 *
	 * @return bool|WP_Error Returns true if the row was deleted, false otherwise.
	 * @throws Exception If either `object_id` or `term_taxonomy_id` are missing, or if we failed to delete the row.
	 */
	public function delete(): bool|WP_Error {
		if ( null === $this->get_primary_id() ) {
			throw new Exception( 'Either `object_id` or `term_taxonomy_id` are missing' );
		}

		$maybe_deleted = $this->wpdb->delete(
			$this->get_table_name(),
			[
				'object_id'        => $this->data['object_id'],
				'term_taxonomy_id' => $this->data['term_taxonomy_id'],
			]
		);

		if ( false === $maybe_deleted ) {
			throw new Exception( 
				sprintf(
					'Failed to delete %s data: %s',
					$this->get_table_name(), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$this->wpdb->last_error // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				)
			);
		}

		return true;
	}

	/**
	 * Returns the computed virtual primary key for the given object_id and term_taxonomy_id.
	 *
	 * @param int $object_id The object_id.
	 * @param int $term_taxonomy_id The term_taxonomy_id.
	 *
	 * @return int
	 */
	public static function get_virtual_primary_key( int $object_id, int $term_taxonomy_id ): int {
		return bcadd(
			bcmul(
				$object_id,
				bcpow(
					'2',
					'32'
				)
			),
			$term_taxonomy_id
		);
	}

	/**
	 * Returns the composite values used to form a virtual primary key.
	 *
	 * @param int $virtual_primary_key The virtual primary key.
	 *
	 * @return array
	 */
	#[ArrayShape(
		[
			'object_id'        => 'int',
			'term_taxonomy_id' => 'int',
		]
	) ]
	public static function get_column_values_from_virtual_primary_key( int $virtual_primary_key ): array {
		return [
			'object_id'        => bcdiv(
				$virtual_primary_key,
				bcpow(
					'2',
					'32'
				),
				0
			),
			'term_taxonomy_id' => bcmod(
				$virtual_primary_key,
				bcpow(
					'2',
					'32'
				)
			),
		];
	}
}
