<?php

namespace Newspack\MigrationTools\Scaffold\WordPressData;

use DateTime;
use Exception;
use Newspack\MigrationTools\Scaffold\Contracts\RunAwareMigrationObject;
use Newspack\MigrationTools\Scaffold\MigrationObjectPropertyWrapper;
use Newspack\MigrationTools\Scaffold\RunAwareMigrationObjectWrapper;
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
 * @property string $display_name
 */
class WordPressUsersData extends AbstractWordPressData {

	/**
	 * Class containing logic which validates user properties and ensures they're correct.
	 *
	 * @var Users $users_logic Class containing logic which validates user properties and ensures they're correct.
	 */
	protected Users $users_logic;

	/**
	 * Array containing the user's metadata.
	 *
	 * @var int[]|string[]|WordPressUserMetaData[]|MigrationObjectPropertyWrapper[] $meta_data The metadata associated with the user.
	 */
	protected array $meta_data = [];

	/**
	 * Array containing allowed roles for the user.
	 *
	 * @var string[] $roles List of allowed roles for the user.
	 */
	protected array $allowed_roles = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->primary_key = 'ID';
		$this->users_logic = new Users();
		global $wp_roles;
		$this->allowed_roles = array_keys( $wp_roles->roles );
	}

	/**
	 * This function will return a User's ID based on the Legacy ID, or the Migration Object if available.
	 *
	 * @param int|string $legacy_id Legacy ID.
	 *
	 * @return int|null
	 * @throws Exception If more than one WordPress objects have been found for the same Legacy ID.
	 */
	public function get_user_id_from_legacy_id( int|string $legacy_id = '' ): ?int {
		if ( empty( $legacy_id ) ) { // Allow for ID retrieval if you don't have the Legacy ID in hand.
			if ( $this->get_migration_object() ) { // The Migration Object should have it.
				return $this->get_wordpress_object_id_from_migration_object();
			} else {
				// If you don't have a Legacy ID, and you don't have a Migration Object, you won't get any further.
				return null;
			}
		}

		return $this->get_wordpress_object_id_from_legacy_id( $legacy_id );
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
		if ( $first_name instanceof MigrationObjectPropertyWrapper ) {
			$this->add_user_meta_data(
				( new WordPressUserMetaData() )->set_meta_key( 'first_name' )->set_meta_value( $first_name )
			);
		} else {
			$this->add_meta_data( 'first_name', $first_name );
		}

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
		if ( $last_name instanceof MigrationObjectPropertyWrapper ) {
			$this->add_user_meta_data(
				( new WordPressUserMetaData() )->set_meta_key( 'last_name' )->set_meta_value( $last_name )
			);
		} else {
			$this->add_meta_data( 'last_name', $last_name );
		}

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
		if ( $nickname instanceof MigrationObjectPropertyWrapper ) {
			$this->add_user_meta_data(
				( new WordPressUserMetaData() )->set_meta_key( 'nickname' )->set_meta_value( $nickname )
			);
		} else {
			$this->add_meta_data( 'nickname', $nickname );
		}

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
	 * Sets the role for the user.
	 *
	 * @param string|MigrationObjectPropertyWrapper $role The desired role to set.
	 *
	 * @return WordPressUsersData
	 * @throws Exception If the role is not allowed.
	 */
	public function set_role( string|MigrationObjectPropertyWrapper $role ): WordPressUsersData {
		$copy_role = $role;
		if ( $copy_role instanceof MigrationObjectPropertyWrapper ) {
			$copy_role = $copy_role->get_value();
		}
		$copy_role = strtolower( $copy_role );

		if ( ! in_array( $copy_role, $this->allowed_roles, true ) ) {
			throw new Exception(
				sprintf(
					'Invalid role: %s',
					$copy_role // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				)
			);
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- this is how role data is stored.
		$meta_value = serialize( [ $copy_role => true ] );

		if ( $role instanceof MigrationObjectPropertyWrapper ) {
			$this->add_user_meta_data(
				( new WordPressUserMetaData() )->set_meta_key( 'wp_capabilities' )->set_meta_value(
					new MigrationObjectPropertyWrapper(
						$meta_value,
						explode( '.', $role->get_path() ),
						$role->get_migration_object()
					)
				)
			);
		} else {
			$this->add_meta_data( 'wp_capabilities', $meta_value );
		}

		return $this;
	}

	/**
	 * Sets the metadata for the user.
	 *
	 * @param array $meta_data Array of key-value pairs for the user's metadata.
	 *
	 * @return WordPressUsersData
	 */
	public function set_meta_data( array $meta_data ): WordPressUsersData {
		foreach ( $meta_data as $key => $value ) {
			if ( $value instanceof WordPressUserMetaData ) {
				$this->add_user_meta_data( $value );
			} else {
				$this->add_meta_data( $key, $value );
			}
		}

		return $this;
	}

	/**
	 *  "Simpler" function which adds a metadata item to the term. Adding WordPressUserMetaData turns out to be
	 *  simpler than this function in adding items to the $meta_data array, but WordPressUserMetaData
	 *  has already been set up by the Migrator at this point. For example, a specific
	 *  MigrationObject has been set by the implementing dev which determines
	 *  which value gets stored in `migration_destination_sources`.
	 *
	 * @param string|MigrationObjectPropertyWrapper     $key The metadata key.
	 * @param int|string|MigrationObjectPropertyWrapper $value The metadata value.
	 *
	 * @return WordPressUsersData
	 */
	public function add_meta_data( string|MigrationObjectPropertyWrapper $key, int|string|MigrationObjectPropertyWrapper $value ): WordPressUsersData {
		if ( $value instanceof MigrationObjectPropertyWrapper ) {
			$migration_object = $value->get_migration_object();
			if ( ! ( $migration_object instanceof RunAwareMigrationObject ) ) {
				$migration_object = new RunAwareMigrationObjectWrapper( $migration_object, $this->get_migration_object()->get_run_context() );
			}
			$user_meta_data = new WordPressUserMetaData();
			$user_meta_data->set_migration_object( $migration_object );
			$user_meta_data->set_meta_key( $key )->set_meta_value( $value );

			if ( $key instanceof MigrationObjectPropertyWrapper ) {
				$key = $key->get_value();
			}

			$value = $user_meta_data;
		} elseif ( $key instanceof MigrationObjectPropertyWrapper ) {
			$migration_object = $key->get_migration_object();
			if ( ! ( $migration_object instanceof RunAwareMigrationObject ) ) {
				$migration_object = new RunAwareMigrationObjectWrapper( $migration_object, $this->get_migration_object()->get_run_context() );
			}
			$user_meta_data = new WordPressUserMetaData();
			$user_meta_data->set_migration_object( $migration_object );
			$user_meta_data->set_meta_value( $value )->set_meta_key( $key->get_value() );
			$value = $user_meta_data;
		}

		$this->meta_data[ $key ] = $value;

		return $this;
	}

	/**
	 * Adds a WordPressUserMetaData object to the user's metadata.
	 *
	 * @param WordPressUserMetaData $meta_data The WordPressUserMetaData object to add.
	 *
	 * @return WordPressUsersData
	 */
	public function add_user_meta_data( WordPressUserMetaData $meta_data ): WordPressUsersData {
		$this->meta_data[ $meta_data->meta_key ] = $meta_data;

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
			if ( isset( $this->meta_data['first_name'] ) && isset( $this->meta_data['last_name'] ) ) {
				$first_name = $this->meta_data['first_name'];
				if ( $first_name instanceof WordPressUserMetaData ) {
					$first_name = (string) $first_name->meta_value;
				}
				$first_name = ucwords( strtolower( $first_name ) );

				$last_name = $this->meta_data['last_name'];
				if ( $last_name instanceof WordPressUserMetaData ) {
					$last_name = (string) $last_name->meta_value;
				}
				$last_name = ucwords( strtolower( $last_name ) );

				$this->display_name = "$first_name $last_name";
			} else {
				$this->data         = $original_data;
				$this->data_sources = $original_data_sources;

				throw new Exception( '`display_name` is empty. Attempting to set with `first_name` and `last_name`, but those are empty/missing also.' );
			}
		} elseif ( is_email( $this->display_name ) ) {
			throw new Exception(
				sprintf(
					"`display_name` ('%s') should not be an email.",
					$this->display_name // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				)
			);
		}

		// At this point we know we potentially have `display_name`, `first_name`, and `last_name` set.
		// Or a mix of `display_name` only, or `first_name` and `last_name` only.
		if ( ! isset( $this->user_login ) ) {
			try {
				$this->set_user_login_from_available_data_points();
			} catch ( Exception $e ) {
				$this->data         = $original_data;
				$this->data_sources = $original_data_sources;

				throw $e;
			}
		} else {
			$unique_user_login = $this->users_logic->get_unique_user_login( $this->user_login );

			if ( empty( $unique_user_login ) ) {
				try {
					$this->set_user_login_from_available_data_points();
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
				$this->set_user_nicename_from_available_data_points();
			} catch ( Exception $e ) {
				$this->data         = $original_data;
				$this->data_sources = $original_data_sources;
			}
		} else {
			$unique_user_nicename = $this->users_logic->get_unique_user_nicename( $this->user_nicename );

			if ( empty( $unique_user_nicename ) ) {
				try {
					$this->set_user_nicename_from_available_data_points();
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

		if ( ! isset( $this->meta_data['wp_capabilities'] ) ) {
			$this->set_role( $this->allowed_roles[ array_key_last( $this->allowed_roles ) ] );
		}

		$copy_migration_object = $this->get_migration_object();

		$maybe_user_id = parent::create();

		if ( is_wp_error( $maybe_user_id ) ) {
			return $maybe_user_id;
		}

		if ( ! empty( $this->meta_data ) ) {
			$this->handle_meta_data( $maybe_user_id, $copy_migration_object );
		}

		return $maybe_user_id;
	}

	/**
	 * This function will perform an update operation on the underlying data that has been set, with some validations
	 * to ensure that any of the changes don't conflict with existing User data.
	 *
	 * @return WP_Error|bool
	 * @throws Exception If there is a conflict with existing User data.
	 */
	public function update(): WP_Error|bool {
		// TODO Check that user_login and user_nicename are unique.
		$result = 1 === 0;
		return parent::update();
	}

	/**
	 * This function ensures that `user_login` has been set properly and is unique.
	 *
	 * @return void
	 * @throws Exception If a data property and data property source have not been set, or unable to procure a unique `user_login`.
	 */
	private function set_user_login_from_available_data_points(): void {
		if ( isset( $this->user_email ) ) {
			$user_login_from_email = $this->users_logic->get_unique_user_login( $this->user_email );

			if ( ! empty( $user_login_from_email ) && array_key_exists( 'user_email', $this->data_sources ) ) {
				$this->concatenate_to_set_property( 'user_login', [ 'user_email' ], $user_login_from_email );
			} else {
				$this->set_user_login( $user_login_from_email );
			}
		}

		if ( ! isset( $this->user_login ) && isset( $this->first_name ) && isset( $this->last_name ) ) {
			$user_login_from_names = sanitize_text_field( strtolower( "$this->first_name.$this->last_name" ) );
			$user_login_from_names = preg_replace( '/\s+/', '.', $user_login_from_names );
			$user_login_from_names = $this->users_logic->get_unique_user_login( $user_login_from_names );

			if ( ! empty( $user_login_from_names ) && isset( $this->data_sources['first_name'] ) && isset( $this->data_sources['last_name'] ) ) {
				$this->concatenate_to_set_property( 'user_login', [ 'first_name', 'last_name' ], $user_login_from_names );
			} else {
				$this->set_user_login( $user_login_from_names );
			}
		}

		if ( ! isset( $this->user_login ) && isset( $this->display_name ) ) {
			$user_login_from_display_name = sanitize_text_field( strtolower( $this->display_name ) );
			$user_login_from_display_name = preg_replace( '/\s+/', '.', $user_login_from_display_name );
			$user_login_from_display_name = $this->users_logic->get_unique_user_login( $user_login_from_display_name );

			if ( ! empty( $user_login_from_display_name ) && array_key_exists( 'display_name', $this->data_sources ) ) {
				$this->concatenate_to_set_property( 'user_login', [ 'display_name' ], $user_login_from_display_name );
			} else {
				$this->set_user_login( $user_login_from_display_name );
			}
		}

		// Last resort.
		if ( ! isset( $this->user_login ) ) {
			$user_login = 'mig-scaf-user-' . substr( md5( wp_rand() ), 0, 10 );
			$user_login = $this->users_logic->get_unique_user_login( $user_login );

			if ( ! empty( $user_login ) ) {
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
	private function set_user_nicename_from_available_data_points(): void {
		if ( isset( $this->display_name ) ) {
			$sanitized_display_name = sanitize_title( $this->display_name );
			$unique_user_nicename   = $this->users_logic->get_unique_user_nicename( $sanitized_display_name );

			if ( isset( $this->user_login ) && $this->user_login === $unique_user_nicename ) {
				$unique_user_nicename = $this->users_logic->get_unique_user_nicename( "$sanitized_display_name-1" );
			}

			if ( ! empty( $unique_user_nicename ) && array_key_exists( 'display_name', $this->data_sources ) ) {
				$this->concatenate_to_set_property( 'user_nicename', [ 'display_name' ], $unique_user_nicename );
			} else {
				$this->set_user_nicename( $unique_user_nicename );
			}
		}

		if ( ! isset( $this->user_nicename ) && isset( $this->meta_data['first_name'] ) && isset( $this->meta_data['last_name'] ) ) {
			$first_name = $this->meta_data['first_name'];
			if ( $first_name instanceof WordPressUserMetaData ) {
				$first_name = (string) $first_name->meta_value;
			}
			$last_name = $this->meta_data['last_name'];
			if ( $last_name instanceof WordPressUserMetaData ) {
				$last_name = (string) $last_name->meta_value;
			}
			$unique_user_nicename = $this->users_logic->get_unique_user_nicename( sanitize_title( $first_name . '-' . $last_name ) );

			if ( isset( $this->user_login ) && $this->user_login === $unique_user_nicename ) {
				$unique_user_nicename = $this->users_logic->get_unique_user_nicename( "$sanitized_display_name-1" );
			}

			$this->set_user_nicename( $unique_user_nicename );
		}

		// Last resort.
		if ( ! isset( $this->user_nicename ) ) {
			$user_nicename = $this->users_logic->get_unique_user_nicename( 'user-' . substr( md5( wp_rand() ), 0, 10 ) );

			if ( ! empty( $user_nicename ) ) {
				$this->set_user_nicename( $user_nicename );
			}
		}

		if ( ! isset( $this->user_nicename ) ) {
			throw new Exception( 'Unable to set unique `user_nicename`' );
		}
	}

	/**
	 * Handles creating or updating user metadata.
	 *
	 * @param int                     $user_id The ID of the user.
	 * @param RunAwareMigrationObject $migration_object The migration object.
	 *
	 * @return void
	 * @throws Exception If the user ID is not set, or if the metadata cannot be created or updated.
	 */
	private function handle_meta_data( int $user_id, RunAwareMigrationObject $migration_object ): void {
		// phpcs:disable -- query is properly prepared and escaped.
		$existing_meta_data = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->wpdb->usermeta} WHERE user_id = %d",
				$user_id
			)
		);
		// phpcs:enable

		foreach ( $existing_meta_data as $meta_data ) {
			if ( array_key_exists( $meta_data->meta_key, $this->meta_data ) ) {
				$value = $this->meta_data[ $meta_data->meta_key ];
				// phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- this is intentional, values from the DB will be strings. Letting PHP handle the implicit type conversions.
				if ( $value instanceof WordPressUserMetaData && $value->meta_value != $meta_data->meta_value ) {
					if ( ! array_key_exists( 'migration_object', get_object_vars( $value ) ) ) {
						$value->set_migration_object( $migration_object );
					}
					$value->set_umeta_id( $meta_data->umeta_id )->set_meta_value( $value->meta_value )->update();
					unset( $this->meta_data[ $meta_data->meta_key ] );
					continue;
				}

				// phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- this is intentional, values from the DB will be strings. Letting PHP handle the implicit type conversions.
				if ( $value instanceof MigrationObjectPropertyWrapper && $value->get_value() != $meta_data->meta_value ) {
					$post_meta_data = new WordPressUserMetaData();
					$post_meta_data->set_migration_object( $migration_object );
					$post_meta_data->set_umeta_id( $meta_data->umeta_id )->set_meta_value( $value->get_value() )->update();
					unset( $this->meta_data[ $meta_data->meta_key ] );
					continue;
				}

				$this->wpdb->update(
					$this->wpdb->usermeta,
					[
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- this query targets metadata by meta ID.
						'meta_value' => $value,

					],
					[
						'umeta_id' => $meta_data->umeta_id,
					]
				);
				unset( $this->meta_data[ $meta_data->meta_key ] );
			} else {
				$this->wpdb->delete(
					$this->wpdb->usermeta,
					[
						'umeta_id' => $meta_data->umeta_id,
					]
				);
			}
		}

		foreach ( $this->meta_data as $key => $value ) {
			if ( $value instanceof WordPressUserMetaData ) {
				if ( ! array_key_exists( 'migration_object', get_object_vars( $value ) ) ) {
					$value->set_migration_object( $migration_object );
				}
				$value->set_user_id( $user_id )->create();
				continue;
			}

			if ( $value instanceof MigrationObjectPropertyWrapper ) {
				$post_meta_data         = new WordPressUserMetaData();
				$value_migration_object = $value->get_migration_object();
				if ( ! ( $value_migration_object instanceof RunAwareMigrationObject ) ) {
					$value_migration_object = new RunAwareMigrationObjectWrapper( $value_migration_object, $migration_object->get_run_context() );
				}
				$post_meta_data->set_migration_object( $value_migration_object );
				$post_meta_data->set_user_id( $user_id )->set_meta_value( $value->get_value() )->create();
				continue;
			}

			$this->wpdb->insert(
				$this->wpdb->usermeta,
				[
					'user_id'    => $user_id,
					'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				]
			);
		}

		$this->meta_data = [];
	}
}
