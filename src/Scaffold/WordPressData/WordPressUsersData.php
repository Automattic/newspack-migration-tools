<?php

namespace Newspack\MigrationTools\Scaffold\WordPressData;

use DateTime;
use Exception;
use Newspack\MigrationTools\Scaffold\MigrationObjectPropertyWrapper;
use NewspackCustomContentMigrator\Logic\Users;
use WP_Error;

/**
 * Class WordPressUsersData.
 *
 * @property int $ID
 * @property string $user_login
 * @property string $user_pass
 * @property string $user_nicename
 * @property string $user_email
 * @property string $user_url
 * @property string $user_registered
 * @property string $user_activation_key
 * @property int $user_status
 * @property string $first_name
 * @property string $last_name
 * @property string $nickname
 * @property string $display_name
 * @property string $role
 */
class WordPressUsersData extends AbstractWordPressData {

	/**
	 * Class containing logic which validates user properties and ensures they're correct.
	 *
	 * @var Users $users_logic Class containing logic which validates user properties and ensures they're correct.
	 */
	protected Users $users_logic;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->primary_key = 'ID';
		$this->users_logic = new Users();
	}

	/**
	 * Returns the table name.
	 *
	 * @return string
	 */
	public function get_table_name(): string {
		if ( ! isset( $this->table_name ) ) {
			$this->table_name = $this->wpdb->users;
		}

		return parent::get_table_name();
	}

	/**
	 * Set the ID field.
	 *
	 * @param int|MigrationObjectPropertyWrapper $id The ID.
	 *
	 * @return $this
	 */
	public function set_id( int|MigrationObjectPropertyWrapper $id ): WordPressUsersData {
		$this->set_property( 'ID', $id );

		return $this;
	}

	/**
	 * Set the user_login field.
	 *
	 * @param string|MigrationObjectPropertyWrapper $user_login The user_login to set.
	 *
	 * @return $this
	 */
	public function set_user_login( string|MigrationObjectPropertyWrapper $user_login ): WordPressUsersData {
		$this->set_property( 'user_login', $user_login );

		return $this;
	}

	/**
	 * Set the user_pass field.
	 *
	 * @param string $user_password The user_pass to set.
	 *
	 * @return $this
	 */
	public function set_user_pass( string $user_password = '' ): WordPressUsersData {
		if ( empty( $user_password ) ) {
			$user_password = wp_generate_password( 24 );
		}

		$this->set_property( 'user_pass', $user_password );

		return $this;
	}

	/**
	 * Set the user_nicename field.
	 *
	 * @param string|MigrationObjectPropertyWrapper $user_nicename The user_nicename to set.
	 *
	 * @return $this
	 */
	public function set_user_nicename( string|MigrationObjectPropertyWrapper $user_nicename ): WordPressUsersData {
		$this->set_property( 'user_nicename', $user_nicename );

		return $this;
	}

	/**
	 * Set the user_email field.
	 *
	 * @param string|MigrationObjectPropertyWrapper $user_email The user_email to set.
	 * @param bool                                  $force_set Whether to bypass any validation and force set user_email value.
	 *
	 * @return $this
	 * @throws Exception If $user_email is not a valid email address.
	 */
	public function set_user_email( string|MigrationObjectPropertyWrapper $user_email, bool $force_set = false ): WordPressUsersData {
		if ( ! $force_set && ! is_email( $user_email ) ) {
			throw new Exception( sprintf( "`user_email` ('%s') is not an email", $user_email ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$this->set_property( 'user_email', $user_email );

		return $this;
	}

	/**
	 * Set the user_url field.
	 *
	 * @param string|MigrationObjectPropertyWrapper $user_url The user_url to set.
	 * @param bool                                  $force_set Whether to bypass any validation and force set user_url value.
	 *
	 * @return $this
	 * @throws Exception If $user_url is not a valid URL.
	 */
	public function set_user_url( string|MigrationObjectPropertyWrapper $user_url, bool $force_set = false ): WordPressUsersData {
		if ( ! $force_set && false === filter_var( $user_url, FILTER_VALIDATE_URL ) ) {
			throw new Exception( sprintf( "`user_url` ('%s') is not a URL.", $user_url ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$this->set_property( 'user_url', $user_url );

		return $this;
	}

	/**
	 * Set the user_registered field.
	 *
	 * @param string|MigrationObjectPropertyWrapper|\DateTimeInterface $user_registered The user_registered value to set.
	 *
	 * @return $this
	 * @throws Exception If the date string is malformed.
	 */
	public function set_user_registered( string|MigrationObjectPropertyWrapper|\DateTimeInterface $user_registered ): WordPressUsersData {
		$this->set_date_property( $user_registered, 'user_registered' );

		return $this;
	}

	/**
	 * Set the user_activation_key field.
	 *
	 * @param string|MigrationObjectPropertyWrapper $user_activation_key The user_activation_key to set.
	 *
	 * @return $this
	 */
	public function set_user_activation_key( string|MigrationObjectPropertyWrapper $user_activation_key ): WordPressUsersData {
		$this->set_property( 'user_activation_key', $user_activation_key );

		return $this;
	}

	/**
	 * Set the user_status field.
	 *
	 * @param int|MigrationObjectPropertyWrapper $user_status The user_status to set.
	 *
	 * @return $this
	 */
	public function set_user_status( int|MigrationObjectPropertyWrapper $user_status ): WordPressUsersData {
		$this->set_property( 'user_status', $user_status );

		return $this;
	}

	/**
	 * Set the first_name field.
	 *
	 * @param string|MigrationObjectPropertyWrapper $first_name The first_name to set.
	 *
	 * @return $this
	 */
	public function set_first_name( string|MigrationObjectPropertyWrapper $first_name ): WordPressUsersData {
		$this->set_property( 'first_name', $first_name );

		return $this;
	}

	/**
	 * Set the last_name field.
	 *
	 * @param string|MigrationObjectPropertyWrapper $last_name The last_name to set.
	 *
	 * @return $this
	 */
	public function set_last_name( string|MigrationObjectPropertyWrapper $last_name ): WordPressUsersData {
		$this->set_property( 'last_name', $last_name );

		return $this;
	}

	/**
	 * Set the nickname field.
	 *
	 * @param string|MigrationObjectPropertyWrapper $nickname The nickname to set.
	 *
	 * @return $this
	 */
	public function set_nickname( string|MigrationObjectPropertyWrapper $nickname ): WordPressUsersData {
		$this->set_property( 'nickname', $nickname );

		return $this;
	}

	/**
	 * Set the display_name field.
	 *
	 * @param string|MigrationObjectPropertyWrapper $display_name The display_name to set.
	 *
	 * @return $this
	 */
	public function set_display_name( string|MigrationObjectPropertyWrapper $display_name ): WordPressUsersData {
		$this->set_property( 'display_name', $display_name );

		return $this;
	}

	/**
	 * Creates a WP_User record with the underlying data that has been set. This will also record the source
	 * of the data used to generate the WP_User record.
	 *
	 * @return WP_Error|int
	 * @throws Exception If display_name, first_name, and last_name have not been set.
	 */
	public function create(): WP_Error|int {
		// It will potentially be necessary to update/set certain properties that may or may not have been set.
		// If they have been set, and we end up updating it, we should set it back to the original value
		// if there is an error when creating the record. This will help with debugging.
		$original_data         = $this->data;
		$original_data_sources = $this->data_sources;

		if ( ! isset( $this->user_pass ) ) {
			$this->set_user_pass();
		}

		// TODO - need to move or consolidate logic that is in here with our User Create Helper.

		// Ensure that user_login and user_nicename are unique.
		if ( ! isset( $this->display_name ) ) {
			try {
				$this->concatenate_to_set_property( 'display_name', [ 'first_name', 'last_name' ] );
			} catch ( Exception $e ) {
				$missing_props = [];

				if ( ! isset( $this->first_name ) ) {
					$missing_props[] = '`first_name`';
				}

				if ( ! isset( $this->last_name ) ) {
					$missing_props[] = '`last_name`';
				}

				$this->data         = $original_data;
				$this->data_sources = $original_data_sources;

				throw new Exception( sprintf( '`display_name` is empty. Attempting to set with %s, but those are empty/missing also.', implode( ' and ', $missing_props ) ) );
			}
		} elseif ( is_email( $this->display_name ) ) {
			throw new Exception( sprintf( "`display_name` ('%s') should not be an email.", $this->display_name ) );
		}

		// At this point we know we potentially have `display_name`, `first_name`, and `last_name` set.
		// Or a mix of `display_name` only, or `first_name` and `last_name` only.
		if ( ! isset( $this->user_login ) ) {
			try {
				$this->confirm_user_login_is_unique();
			} catch ( Exception $e ) {
				$this->data         = $original_data;
				$this->data_sources = $original_data_sources;

				throw $e;
			}
		} else {
			$unique_user_login = $this->users_logic->obtain_unique_user_login( $this->user_login );

			if ( empty( $unique_user_login ) ) {
				try {
					$this->confirm_user_login_is_unique();
				} catch ( Exception $e ) {
					$this->data         = $original_data;
					$this->data_sources = $original_data_sources;

					throw $e;
				}
			} elseif ( $unique_user_login !== $this->user_login ) {
				// Must set directly here instead of using `set_property` to not overwrite `$data_sources[ 'user_login' ]`.
				$this->data['user_login'] = $unique_user_login;
			}
		}

		if ( ! isset( $this->user_nicename ) ) {
			try {
				$this->confirm_user_nicename_is_unique();
			} catch ( Exception $e ) {
				$this->data         = $original_data;
				$this->data_sources = $original_data_sources;
			}
		} else {
			$unique_user_nicename = $this->users_logic->obtain_unique_user_nicename( $this->user_nicename );

			if ( empty( $unique_user_nicename ) ) {
				try {
					$this->confirm_user_nicename_is_unique();
				} catch ( Exception $e ) {
					$this->data         = $original_data;
					$this->data_sources = $original_data_sources;
				}
			} elseif ( $unique_user_nicename !== $this->user_nicename ) {
				// Must set directly here instead of using `set_property` to not overwrite `$data_sources[ 'user_nicename' ]`.
				$this->data['user_nicename'] = $unique_user_nicename;
			}
		}

		if ( ! isset( $this->user_email ) ) {
			// Let's give this user the gift of email. At this point, `user_nicename` should be unique across the entire site.
			$this->set_user_email(
				new MigrationObjectPropertyWrapper(
					$this->user_nicename . '@example.com',
					[ '' ],
					$this->get_migration_object()
				),
				true
			);
		}

		if ( ! isset( $this->user_registered ) ) {
			$this->set_user_registered( new DateTime() );
		}

		return parent::create();
	}

	public function update(): WP_Error|bool {
		// TODO Check that user_login and user_nicename are unique

		return parent::update();
	}

	/**
	 * This function ensures that `user_login` has been set properly and is unique.
	 *
	 * @return void
	 * @throws Exception If a data property and data property source have not been set, or unable to procure a unique `user_login`.
	 */
	private function confirm_user_login_is_unique(): void {
		if ( isset( $this->user_email ) ) {
			$user_login_from_email = $this->users_logic->obtain_unique_user_login( $this->user_email );

			if ( ! empty( $user_login_from_email ) ) {
				$this->concatenate_to_set_property( 'user_login', [ 'user_email' ], $user_login_from_email );
			}
		}

		if ( ! isset( $this->user_login ) && isset( $this->first_name ) && isset( $this->last_name ) ) {
			$user_login_from_names = sanitize_text_field( strtolower( "$this->first_name.$this->last_name" ) );
			$user_login_from_names = preg_replace( '/\s+/', '.', $user_login_from_names );
			$user_login_from_names = $this->users_logic->obtain_unique_user_login( $user_login_from_names );

			if ( ! empty( $user_login_from_names ) ) {
				$this->concatenate_to_set_property( 'user_login', [ 'first_name', 'last_name' ], $user_login_from_names );
			}
		}

		if ( ! isset( $this->user_login ) && isset( $this->display_name ) ) {
			$user_login_from_display_name = sanitize_text_field( strtolower( $this->display_name ) );
			$user_login_from_display_name = preg_replace( '/\s+/', '.', $user_login_from_display_name );
			$user_login_from_display_name = $this->users_logic->obtain_unique_user_login( $user_login_from_display_name );

			if ( ! empty( $user_login_from_display_name ) ) {
				$this->concatenate_to_set_property( 'user_login', [ 'display_name' ], $user_login_from_display_name );
			}
		}

		// Last resort.
		if ( ! isset( $this->user_login ) ) {
			$user_login = 'mig-scaf-user-' . substr( md5( wp_rand() ), 0, 10 );
			$user_login = $this->users_logic->obtain_unique_user_login( $user_login );

			if ( ! empty( $user_login ) ) {
				$user_login = new MigrationObjectPropertyWrapper( $user_login, [] );
				$this->set_user_login( $user_login );
			}
		}

		if ( ! isset( $this->user_login ) ) {
			throw new Exception( 'Unable to set a unique `user_login`' );
		}
	}

	/**
	 * This function ensures that `user_nicename` has been set properly and is unique.
	 *
	 * @return void
	 * @throws Exception If a data property and data property source have not been set, or unabel to procure a unique `user_nicename`.
	 */
	private function confirm_user_nicename_is_unique(): void {
		if ( isset( $this->display_name ) ) {
			$unique_user_nicename = $this->users_logic->obtain_unique_user_nicename( sanitize_title( $this->display_name ) );

			if ( ! empty( $unique_user_nicename ) ) {
				$this->concatenate_to_set_property( 'user_nicename', [ 'display_name' ], $unique_user_nicename );
			}
		}

		if ( ! isset( $this->user_nicename ) && isset( $this->first_name ) && isset( $this->last_name ) ) {
			$unique_user_nicename = $this->users_logic->obtain_unique_user_nicename( sanitize_title( $this->first_name . '-' . $this->last_name ) );

			if ( ! empty( $unique_user_nicename ) ) {
				$this->concatenate_to_set_property( 'user_nicename', [ 'first_name', 'last_name' ], $unique_user_nicename );
			}
		}

		// Last resort.
		if ( ! isset( $this->user_nicename ) ) {
			$user_nicename = $this->users_logic->obtain_unique_user_nicename( 'user-' . substr( md5( wp_rand() ), 0, 10 ) );

			if ( ! empty( $user_nicename ) ) {
				$user_nicename = new MigrationObjectPropertyWrapper( $user_nicename, [] );
				$this->set_user_nicename( $user_nicename );
			}
		}

		if ( ! isset( $this->user_nicename ) ) {
			throw new Exception( 'Unable to set unique `user_nicename`' );
		}
	}
}
