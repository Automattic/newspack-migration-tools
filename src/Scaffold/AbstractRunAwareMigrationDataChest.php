<?php

namespace Newspack\MigrationTools\Scaffold;

use Exception;
use Newspack\MigrationTools\Scaffold\Contracts\RunAwareMigrationDataChest;
use Newspack\MigrationTools\Scaffold\Contracts\MigrationRunKey;

/**
 * AbstractRunAwareMigrationDataContainer.
 */
abstract class AbstractRunAwareMigrationDataChest extends AbstractMigrationDataChest implements RunAwareMigrationDataChest {

	/**
	 * Database connection.
	 *
	 * @var \wpdb $wpdb Database connection.
	 */
	private \wpdb $wpdb;

	/**
	 * The migration run key.
	 *
	 * @var MigrationRunKey The migration run key.
	 */
	private MigrationRunKey $run_key;

	/**
	 * The Database ID for this migration data container.
	 *
	 * @var int $id The Database ID for this migration data container.
	 */
	private int $id;

	/**
	 * Whether the underlying data has been stored in the database.
	 *
	 * @var bool $stored Whether the underlying data has been stored in the database.
	 */
	private bool $stored;

		/**
		 * Constructor.
		 *
		 * @param iterable        $data The data set that needs to be migrated.
		 * @param string          $pointer_to_identifier Pointer to the data attribute which uniquely identifies individual objects with the data set.
		 * @param MigrationRunKey $run_key The migration run key.
		 * @param int|null        $id The Database ID for the migration data set.
		 * @param bool|null       $stored Whether the data set has been successfully stored or not.
		 *
		 * @throws Exception If $id does not exist in `migration_data_chests` table.
		 */
	public function __construct( iterable $data, string $pointer_to_identifier, MigrationRunKey $run_key, ?int $id = null, ?bool $stored = null ) {
		parent::__construct( $data, $pointer_to_identifier );
		$this->run_key = $run_key;

		global $wpdb;
		$this->wpdb = $wpdb;

		if ( null !== $id ) {
			// phpcs:disable
			$id_exists = $this->wpdb->get_var(
				$this->wpdb->prepare(
					'SELECT ID FROM migration_data_chests WHERE ID = %d',
					$id
				)
			);
			// phpcs:enable

			if ( ! $id_exists ) {
				$exception_message = sprintf( 'Migration Data Container Database ID (%d) does not exist', $id );
				throw new Exception( wp_kses( $exception_message, wp_kses_allowed_html( 'post' ) ) );
			}

			$this->id     = $id;
			$this->stored = true;
		}

		// This allows someone to set $stored, but not $id. Don't know if we need this capability, can leave like this for now, but this can likely be removed at some point.
		if ( null !== $stored && ! isset( $this->stored ) ) {
			$this->stored = $stored;
		}
	}

	/**
	 * Returns the migration run key.
	 *
	 * @return MigrationRunKey
	 */
	public function get_run_key(): MigrationRunKey {
		return $this->run_key;
	}

	/**
	 * Saves the migration objects container data.
	 *
	 * @return bool
	 */
	public function store(): bool {
		if ( $this->has_been_stored() ) {
			return true;
		}

		$store_data = [
			'json_data'            => wp_json_encode( $this->get_raw_data() ),
			'pointer_to_object_id' => $this->get_pointer_to_identifier(),
			'source_type'          => $this->get_source_type(),
		];

		$maybe_stored = false;
		if ( isset( $this->id ) ) {
			$maybe_stored = $this->wpdb->update(
				'migration_data_chests',
				$store_data,
				[
					'id' => $this->get_id(), // At this point, this should not throw an Exception.
				]
			);

			if ( ! is_bool( $maybe_stored ) ) {
				$this->stored = false;
			} else {
				$this->stored = $maybe_stored;
			}
		} else {
			$store_data['migration_id'] = $this->get_run_key()->get_migration_id();

			$maybe_stored = $this->wpdb->insert(
				'migration_data_chests',
				$store_data
			);

			if ( ! is_bool( $maybe_stored ) ) {
				$this->stored = false;
			} else {
				if ( true === $maybe_stored ) {
					$this->id = $this->wpdb->insert_id;
				}

				$this->stored = $maybe_stored;
			}
		}

		return $this->stored;
	}

	/**
	 * Whether a row for the migration objects container exists in the Database.
	 *
	 * @return bool
	 */
	public function has_been_stored(): bool {
		if ( ! isset( $this->stored ) ) {
			$data = $this->wpdb->get_var(
				$this->wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					'SELECT json_data FROM migration_data_chests WHERE migration_id = %d',
					$this->get_run_key()->get_migration_id() // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				)
			);

			$this->stored = ! empty( $data ) && json_decode( $data ) === $this->data;
		}

		return $this->stored;
	}

	/**
	 * Returns the Database ID of this Migration Data Set Container.
	 *
	 * @return int
	 * @throws Exception If Migration Data Container does not exist in the Database.
	 */
	public function get_id(): int {
		if ( ! isset( $this->id ) ) {
			if ( ! $this->has_been_stored() ) {
				$this->store();
			} else {
				// phpcs:disable
				$row = $this->wpdb->get_row(
					$this->wpdb->prepare(
						'SELECT id FROM migration_data_chests WHERE migration_id = %d ORDER BY created_at DESC',
						$this->get_run_key()->get_migration_id()
					)
				);
				// phpcs:enable

				if ( ! $row ) {
					throw new Exception( 'Supposedly stored Migration Data Container does not exist.' );
				}

				$this->id = $row->id;
			}
		}

		return $this->id;
	}
}
