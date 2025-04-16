<?php

namespace Newspack\MigrationTools\Scaffold\WordPressData;

use Exception;
use Newspack\MigrationTools\Scaffold\MigrationObjectPropertyWrapper;
use WP_Error;
use WP_Term;

/**
 * Class WordPressTermMetaData.
 *
 * @property int $meta_id
 * @property int $term_id
 * @property string $meta_key
 * @property string $meta_value
 */
class WordPressTermMetaData extends AbstractWordPressData {

	public function __construct() {
		parent::__construct();
		$this->primary_key = 'meta_id';
	}

	public function get_table_name(): string {
		if ( ! isset( $this->table_name ) ) {
			$this->table_name = $this->wpdb->termmeta;
		}

		return parent::get_table_name();
	}

	/**
	 * Sets the meta_id for this data object.
	 *
	 * @param int|MigrationObjectPropertyWrapper $meta_id The meta_id.
	 *
	 * @return WordPressTermMetaData
	 */
	public function set_meta_id( int|MigrationObjectPropertyWrapper $meta_id ): WordPressTermMetaData {
		$this->set_property( 'meta_id', $meta_id );

		return $this;
	}

	/**
	 * Sets the term_id for this data object.
	 *
	 * @param int|WP_Term|MigrationObjectPropertyWrapper $term_id The term_id.
	 *
	 * @return WordPressTermMetaData
	 */
	public function set_term_id( int|WP_Term|MigrationObjectPropertyWrapper $term_id ): WordPressTermMetaData {
		if ( $term_id instanceof WP_Term ) {
			$term_id = $term_id->term_id;
		}

		$this->set_property( 'term_id', $term_id );

		return $this;
	}

	/**
	 * Sets the meta_key for this data object.
	 *
	 * @param string|MigrationObjectPropertyWrapper $meta_key The meta_key.
	 *
	 * @return WordPressTermMetaData
	 */
	public function set_meta_key( string|MigrationObjectPropertyWrapper $meta_key ): WordPressTermMetaData {
		$this->set_property( 'meta_key', $meta_key );

		return $this;
	}

	/**
	 * Sets the meta_value for this data object.
	 *
	 * @param string|MigrationObjectPropertyWrapper $meta_value The meta_value.
	 *
	 * @return WordPressTermMetaData
	 */
	public function set_meta_value( string|MigrationObjectPropertyWrapper $meta_value ): WordPressTermMetaData {
		$this->set_property( 'meta_value', $meta_value );

		return $this;
	}

	/**
	 * Creates the post meta.
	 *
	 * @return WP_Error|int The meta_id on success, WP_Error on failure.
	 * @throws Exception If the term ID does not exist, or if more than on WordPress Object are linked to the same Migration Object, or if the primary ID does not match the WordPress Object ID.
	 */
	public function create(): WP_Error|int {
		if ( isset( $this->term_id ) && ! $this->term_id_exists() ) {
			throw new Exception(
				sprintf(
					'Attempting to create term meta without a valid term ID. Meta Key: %s | Meta Value: %s | Term ID: %d',
					$this->meta_key, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$this->meta_value, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$this->term_id // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				)
			);
		}

		return parent::create();
	}

	/**
	 * Updates the post meta record.
	 *
	 * @return WP_Error|bool True on success, WP_Error on failure.
	 * @throws Exception If the term ID does not exist, or if more than on WordPress Object are linked to the same Migration Object, or if the primary ID does not match the WordPress Object ID.
	 */
	public function update(): WP_Error|bool {
		if ( isset( $this->term_id ) && ! $this->term_id_exists() ) {
			throw new Exception(
				sprintf(
					'Attempting to update term meta without a valid term ID. Meta ID: %d | Meta Key: %s | Meta Value: %s | Term ID: %d',
					$this->meta_id, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$this->meta_key, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$this->meta_value, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$this->term_id // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				)
			);
		}

		return parent::update();
	}

	/**
	 * Checks if the post ID exists in the WordPress posts table.
	 *
	 * @return bool
	 */
	private function term_id_exists(): bool {
		// phpcs:disable -- query properly formatted and escaped.
		return (bool) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT term_id FROM {$this->wpdb->terms} WHERE term_id = %d",
				$this->term_id
			)
		);
		// phpcs:enable
	}
}
