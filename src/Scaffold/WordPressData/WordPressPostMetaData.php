<?php

namespace Newspack\MigrationTools\Scaffold\WordPressData;

use Exception;
use Newspack\MigrationTools\Scaffold\MigrationObjectPropertyWrapper;
use WP_Error;
use WP_Post;

/**
 * Class WordPressPostMetaData.
 *
 * @property int $meta_id
 * @property int $post_id
 * @property string $meta_key
 * @property string $meta_value
 */
class WordPressPostMetaData extends AbstractWordPressData {

	/**
	 * WordPressPostMetaData constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->primary_key = 'meta_id';
	}

	/**
	 * Returns the table name for this data object.
	 *
	 * @return string
	 */
	public function get_table_name(): string {
		if ( ! isset( $this->table_name ) ) {
			$this->table_name = $this->wpdb->postmeta;
		}

		return parent::get_table_name();
	}

	/**
	 * Sets the meta_id for this data object.
	 *
	 * @param int|MigrationObjectPropertyWrapper $meta_id The meta_id.
	 *
	 * @return WordPressPostMetaData
	 */
	public function set_meta_id( int|MigrationObjectPropertyWrapper $meta_id ): WordPressPostMetaData {
		$this->set_property( 'meta_id', $meta_id );

		return $this;
	}

	/**
	 * Sets the post_id for this data object.
	 *
	 * @param int|WP_Post|MigrationObjectPropertyWrapper $post_id The post_id.
	 *
	 * @return WordPressPostMetaData
	 */
	public function set_post_id( int|WP_Post|MigrationObjectPropertyWrapper $post_id ): WordPressPostMetaData {
		if ( $post_id instanceof WP_Post ) {
			$post_id = $post_id->ID;
		}

		$this->set_property( 'post_id', $post_id );

		return $this;
	}

	/**
	 * Sets the meta_key for this data object.
	 *
	 * @param string|MigrationObjectPropertyWrapper $meta_key The meta_key.
	 *
	 * @return WordPressPostMetaData
	 */
	public function set_meta_key( string|MigrationObjectPropertyWrapper $meta_key ): WordPressPostMetaData {
		$this->set_property( 'meta_key', $meta_key );

		return $this;
	}

	/**
	 * Sets the meta_value for this data object.
	 *
	 * @param string|MigrationObjectPropertyWrapper $meta_value The meta_value.
	 *
	 * @return WordPressPostMetaData
	 */
	public function set_meta_value( string|MigrationObjectPropertyWrapper $meta_value ): WordPressPostMetaData {
		$this->set_property( 'meta_value', $meta_value );

		return $this;
	}

	/**
	 * Creates the post meta.
	 *
	 * @return WP_Error|int The meta_id on success, WP_Error on failure.
	 * @throws Exception If the post ID does not exist, or if more than on WordPress Object are linked to the same Migration Object, or if the primary ID does not match the WordPress Object ID.
	 */
	public function create(): WP_Error|int {
		if ( isset( $this->post_id ) && ! $this->post_id_exists() ) {
			throw new Exception(
				sprintf(
					'Attempting to create post meta without a valid post ID. Meta Key: %s | Meta Value: %s | Post ID: %d',
					$this->meta_key, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$this->meta_value, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$this->post_id // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				)
			);
		}

		return parent::create();
	}

	/**
	 * Updates the post meta record.
	 *
	 * @return WP_Error|bool True on success, WP_Error on failure.
	 * @throws Exception If the post ID does not exist, or if more than on WordPress Object are linked to the same Migration Object, or if the primary ID does not match the WordPress Object ID.
	 */
	public function update(): WP_Error|bool {
		if ( isset( $this->post_id ) && ! $this->post_id_exists() ) {
			throw new Exception(
				sprintf(
					'Attempting to update post meta without a valid post ID. Meta ID: %d | Meta Key: %s | Meta Value: %s | Post ID: %d',
					$this->meta_id, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$this->meta_key, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$this->meta_value, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$this->post_id // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
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
	private function post_id_exists(): bool {
		// phpcs:disable -- query properly formatted and escaped.
		return (bool) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT ID FROM {$this->wpdb->posts} WHERE ID = %d",
                $this->post_id
			)
		);
		// phpcs:enable
	}
}
