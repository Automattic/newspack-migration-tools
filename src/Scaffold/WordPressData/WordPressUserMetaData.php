<?php

namespace Newspack\MigrationTools\Scaffold\WordPressData;

use Exception;
use Newspack\MigrationTools\Scaffold\MigrationObjectPropertyWrapper;
use WP_Error;
use WP_User;

/**
 * Class WordPressUserMetaData.
 *
 * @property int $umeta_id
 * @property int $user_id
 * @property string $meta_key
 * @property string $meta_value
 */
class WordPressUserMetaData extends AbstractWordPressData {

	/**
	 * WordPressUserMetaData constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->primary_key = 'umeta_id';
	}

	/**
	 * Returns the table name for this data object.
	 *
	 * @return string
	 */
	public function get_table_name(): string {
		if ( ! isset( $this->table_name ) ) {
			$this->table_name = $this->wpdb->usermeta;
		}

		return parent::get_table_name();
	}

	/**
	 * Sets the umeta_id for this data object.
	 *
	 * @param int|MigrationObjectPropertyWrapper $umeta_id The umeta_id.
	 *
	 * @return WordPressUserMetaData
	 */
	public function set_umeta_id( int|MigrationObjectPropertyWrapper $umeta_id ): WordPressUserMetaData {
		$this->set_property( 'umeta_id', $umeta_id );

		return $this;
	}

	/**
	 * Sets the user_id for this data object.
	 *
	 * @param int|WP_User|MigrationObjectPropertyWrapper $user_id The user_id.
	 *
	 * @return WordPressUserMetaData
	 */
	public function set_user_id( int|WP_User|MigrationObjectPropertyWrapper $user_id ): WordPressUserMetaData {
		if ( $user_id instanceof WP_User ) {
			$user_id = $user_id->ID;
		}

		$this->set_property( 'user_id', $user_id );

		return $this;
	}

	/**
	 * Sets the meta_key for this data object.
	 *
	 * @param string|MigrationObjectPropertyWrapper $meta_key The meta_key.
	 *
	 * @return WordPressUserMetaData
	 */
	public function set_meta_key( string|MigrationObjectPropertyWrapper $meta_key ): WordPressUserMetaData {
		$this->set_property( 'meta_key', $meta_key );

		return $this;
	}

	/**
	 * Sets the meta_value for this data object.
	 *
	 * @param string|MigrationObjectPropertyWrapper $meta_value The meta_value.
	 *
	 * @return WordPressUserMetaData
	 */
	public function set_meta_value( string|MigrationObjectPropertyWrapper $meta_value ): WordPressUserMetaData {
		$this->set_property( 'meta_value', $meta_value );

		return $this;
	}

	/**
	 * Creates a user meta record.
	 *
	 * @return WP_Error|int The umeta_id on success, WP_Error on failure.
	 * @throws Exception If the user ID does not exist, or if more than on WordPress Object are linked to the same Migration Object, or if the primary ID does not match the WordPress Object ID.
	 */
	public function create(): WP_Error|int {
		if ( isset( $this->user_id ) && ! $this->user_id_exists() ) {
			throw new Exception(
				sprintf(
					'Attempting to create user meta without a valid user ID. Meta Key: %s | Meta Value: %s | User ID: %d',
					$this->meta_key, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$this->meta_value, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$this->user_id // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				)
			);
		}

		return parent::create();
	}

	/**
	 * Updates a user meta record.
	 *
	 * @return WP_Error|bool True on success, WP_Error on failure.
	 * @throws Exception If the user ID does not exist, or if more than one WordPress Object are linked to the same Migration Object, or if the primary ID does not match the WordPress Object ID.
	 */
	public function update(): WP_Error|bool {
		if ( isset( $this->user_id ) && ! $this->user_id_exists() ) {
			throw new Exception(
				sprintf(
					'Attempting to update user meta without a valid user ID. Meta ID: %d | Meta Key: %s | Meta Value: %s | User ID: %d',
					$this->umeta_id, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$this->meta_key, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$this->meta_value, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$this->user_id // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				)
			);
		}

		return parent::update();
	}

	/**
	 * Checks if the user ID exists in the WordPress users table.
	 *
	 * @return bool
	 */
	private function user_id_exists(): bool {
		// phpcs:disable -- query properly formatted and escaped.
		return (bool) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT user_id FROM {$this->wpdb->users} WHERE ID = %d",
                $this->user_id
			)
		);
		// phpcs:enable
	}
}
