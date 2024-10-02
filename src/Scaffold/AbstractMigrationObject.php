<?php

namespace Newspack\MigrationTools\Scaffold;

use Exception;
use Symfony\Component\PropertyAccess\PropertyAccess;

abstract class AbstractMigrationObject implements MigrationObject {

	protected MigrationRunKey $run_key;

	protected object|array $data;

	protected string $pointer_to_identifier;

	protected bool $processed;

	private \wpdb $wpdb;

	public function __construct( MigrationRunKey $run_key ) {
		$this->run_key = $run_key;
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * @inheritDoc
	 */
	public function set_run_key( MigrationRunKey $run_key ): void {
		// TODO: Implement set_run_key() method.
	}

	/**
	 * @inheritDoc
	 */
	public function get_run_key(): MigrationRunKey {
		return $this->run_key;
	}

	/**
	 * @inheritDoc
	 *
	 * @throws \InvalidArgumentException If the pointer to the identifier provided does not exist in the data.
	 */
	public function set( object|array $data, string $pointer_to_identifier ): void {
		// Validate the identifier.
		try {
			( PropertyAccess::createPropertyAccessorBuilder()
							->enableExceptionOnInvalidIndex()
							->getPropertyAccessor() )->getValue( $data, $pointer_to_identifier );
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \InvalidArgumentException( 'The pointer to the identifier provided does not exist in the data.', 0, $e );
		}
		$this->data                  = $data;
		$this->pointer_to_identifier = $pointer_to_identifier;
		// TODO setting of $processed could be improved by using a cache
		$this->has_been_processed();
	}

	/**
	 * @inheritDoc
	 */
	public function get( string $dot_path ): mixed {
		return (
			PropertyAccess::createPropertyAccessorBuilder()
							->enableExceptionOnInvalidIndex()
							->getPropertyAccessor()
			)
			->getValue( $this->data, $dot_path );
	}

	/**
	 * @return string
	 */
	public function get_pointer_to_identifier(): string {
		return $this->pointer_to_identifier;
	}

	/**
	 * @inheritDoc
	 */
	public function store(): bool {
		$insert = $this->wpdb->insert(
			$this->wpdb->options,
			[
				'option_name'  => $this->get_run_key()->get() . '_migration_object_' . $this->get( $this->get_pointer_to_identifier() ),
				'option_value' => wp_json_encode( $this->data ),
				'autoload'     => 'no',
			]
		);

		if ( ! is_bool( $insert ) ) {
			return false;
		}

		return $insert;
	}

	/**
	 * @inheritDoc
	 */
	public function store_processed_marker(): bool {
		$insert = $this->wpdb->insert(
			$this->wpdb->options,
			[
				'option_name'  => $this->get_run_key()->get() . '_migration_object_' . $this->get( $this->get_pointer_to_identifier() ) . '_processed',
				'option_value' => '1',
				'autoload'     => 'no',
			]
		);

		if ( ! is_bool( $insert ) ) {
			return false;
		}

		if ( $insert ) {
			$this->processed = true;
		}

		return $insert;
	}

	/**
	 * @inheritDoc
	 */
	public function has_been_processed(): bool {
		if ( ! isset( $this->processed ) ) {
			// phpcs:disable
			$options_table = $this->wpdb->options;
			$this->processed = (bool) $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT option_value FROM {$options_table} WHERE option_name = %s",
					$this->get_run_key()->get() . '_migration_object_' . $this->get($this->get_pointer_to_identifier() ) . '_processed'
				)
			);
			// phpcs:enable
		}

		return $this->processed;
	}

	/**
	 * @inheritDoc
	 */
	public function record_source( string $table, string $column, int $id, string $source ): bool {
		$meta_table = match ( $table ) {
			'wp_users' => $this->wpdb->usermeta,
			'wp_posts' => $this->wpdb->postmeta,
			'wp_terms' => $this->wpdb->termmeta,
			default    => $this->wpdb->options,
		};

		$meta_id_name = match ( $table ) {
			'wp_users' => 'user_id',
			'wp_posts' => 'post_id',
			'wp_terms' => 'term_id',
			default => 'option_id',
		};

		$option_name = $this->get_run_key()->get() . '_migration_object_source_' . $this->get( $this->get_pointer_to_identifier() ) . "_{$table}_{$column}_{$id}";
		$insert      = $this->wpdb->insert(
			$meta_table,
			[
				$meta_id_name => $id,
				'meta_key'    => $option_name,
				'meta_value'  => wp_json_encode(
					[
						'table'  => $table,
						'column' => $column,
						'id'     => $id,
						'source' => $source,
					]
				),
			]
		);

		if ( ! is_bool( $insert ) ) {
			return false;
		}

		return $insert;
	}
}
