<?php
/**
 * BulkInserter — Transaction-wrapped multi-row INSERTs for WordPress migrations.
 *
 * Buffers rows and flushes them as multi-row INSERT statements, optionally
 * wrapped in a transaction. Follows the WordPress core pattern from
 * wp-includes/taxonomy.php (prepare per tuple, implode into VALUES list).
 *
 * NULL values are NOT supported in v1. Callers should use empty string or 0.
 *
 * @package Newspack\MigrationTools\Logic
 */

namespace Newspack\MigrationTools\Logic;

use RuntimeException;
use wpdb;

class BulkInserter {

	/** @var wpdb */
	private $wpdb;

	/** @var string Fully-qualified table name (e.g. wp_postmeta). */
	private string $table;

	/** @var string[] Column names. */
	private array $columns;

	/** @var string[] Format placeholders (%d, %s, %f) matching $columns order. */
	private array $formats;

	/** @var int Auto-flush when buffer reaches this size. 0 = manual only. */
	private int $flush_threshold;

	/** @var array[] Buffered rows awaiting flush. Each row is an indexed array of values. */
	private array $buffer = [];

	/** @var bool Whether a transaction is active. */
	private bool $in_transaction = false;

	/** @var int Total rows successfully inserted across all flushes. */
	private int $total_inserted = 0;

	/** @var string[] Accumulated error messages. */
	private array $errors = [];

	/** @var int Usable bytes per INSERT query (max_allowed_packet minus safety margin). */
	private int $max_query_bytes;

	/** @var string Pre-built INSERT prefix: "INSERT INTO `table` (`col1`, `col2`) VALUES " */
	private string $insert_prefix;

	/**
	 * @param string    $table           Fully-qualified table name (e.g. $wpdb->postmeta).
	 * @param string[]  $columns         Column names in insert order.
	 * @param string[]  $formats         wpdb format placeholders matching $columns (%d, %s, %f).
	 * @param int       $flush_threshold Auto-flush buffer at N rows. 0 = manual flush only.
	 * @param wpdb|null $wpdb           Optional wpdb instance; defaults to global $wpdb.
	 *
	 * @throws RuntimeException If columns are empty, column/format count mismatch.
	 */
	public function __construct(
		string $table,
		array $columns,
		array $formats,
		int $flush_threshold = 0,
		?wpdb $wpdb = null
	) {
		if ( empty( $columns ) ) {
			throw new RuntimeException( 'BulkInserter: $columns cannot be empty.' );
		}

		if ( count( $columns ) !== count( $formats ) ) {
			throw new RuntimeException( 'BulkInserter: $columns and $formats must have the same count.' );
		}

		$this->wpdb            = $wpdb ?? $GLOBALS['wpdb'];
		$this->table           = $table;
		$this->columns         = array_values( $columns );
		$this->formats         = array_values( $formats );
		$this->flush_threshold = max( 0, $flush_threshold );

		// Build the static INSERT prefix once.
		$escaped_cols        = array_map( fn( $c ) => '`' . $c . '`', $this->columns );
		$this->insert_prefix = 'INSERT INTO `' . $this->table . '` (' . implode( ', ', $escaped_cols ) . ') VALUES ';

		// Determine max query size from MySQL.
		$this->max_query_bytes = $this->resolve_max_query_bytes();
	}

	/**
	 * Destructor — rolls back any uncommitted transaction to prevent silent data loss.
	 */
	public function __destruct() {
		if ( $this->in_transaction ) {
			$this->rollback();
		}
	}

	// -------------------------------------------------------------------------
	// Transaction lifecycle
	// -------------------------------------------------------------------------

	/**
	 * Start a MySQL transaction.
	 *
	 * @return bool True on success.
	 * @throws RuntimeException If already in a transaction.
	 */
	public function begin_transaction(): bool {
		if ( $this->in_transaction ) {
			throw new RuntimeException( 'BulkInserter: Cannot nest transactions. Commit or rollback first.' );
		}

		$result = $this->wpdb->query( 'START TRANSACTION' );
		if ( false === $result ) {
			$this->errors[] = 'Failed to start transaction: ' . $this->wpdb->last_error;
			return false;
		}

		$this->in_transaction = true;
		return true;
	}

	/**
	 * Commit the current transaction. Auto-flushes the buffer first.
	 * If errors have accumulated, rolls back instead and throws.
	 *
	 * @return bool True on successful commit.
	 * @throws RuntimeException If no transaction is active, or if errors force a rollback.
	 */
	public function commit(): bool {
		if ( ! $this->in_transaction ) {
			throw new RuntimeException( 'BulkInserter: No transaction to commit.' );
		}

		// Flush remaining buffered rows.
		if ( ! empty( $this->buffer ) ) {
			$this->flush();
		}

		// If errors accumulated during the transaction, rollback.
		if ( ! empty( $this->errors ) ) {
			$this->rollback();
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not HTML output.
			throw new RuntimeException(
				'BulkInserter: Transaction rolled back due to errors: ' . implode( '; ', $this->errors )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$result               = $this->wpdb->query( 'COMMIT' );
		$this->in_transaction = false;

		if ( false === $result ) {
			$this->errors[] = 'COMMIT failed: ' . $this->wpdb->last_error;
			return false;
		}

		return true;
	}

	/**
	 * Rollback the current transaction and discard the buffer.
	 *
	 * @return bool True on successful rollback.
	 * @throws RuntimeException If no transaction is active.
	 */
	public function rollback(): bool {
		if ( ! $this->in_transaction ) {
			throw new RuntimeException( 'BulkInserter: No transaction to rollback.' );
		}

		$this->buffer         = [];
		$result               = $this->wpdb->query( 'ROLLBACK' );
		$this->in_transaction = false;

		if ( false === $result ) {
			$this->errors[] = 'ROLLBACK failed: ' . $this->wpdb->last_error;
			return false;
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// Row insertion
	// -------------------------------------------------------------------------

	/**
	 * Buffer a single row for later insertion.
	 *
	 * @param array $row Associative (keyed by column name) or indexed array of values.
	 * @return self For chaining.
	 * @throws RuntimeException If column count doesn't match.
	 */
	public function add_row( array $row ): self {
		$row            = $this->normalize_row( $row );
		$this->buffer[] = $row;

		if ( $this->flush_threshold > 0 && count( $this->buffer ) >= $this->flush_threshold ) {
			$this->flush();
		}

		return $this;
	}

	/**
	 * Buffer multiple rows.
	 *
	 * @param array[] $rows Array of rows (each associative or indexed).
	 * @return self For chaining.
	 */
	public function add_rows( array $rows ): self {
		foreach ( $rows as $row ) {
			$this->add_row( $row );
		}
		return $this;
	}

	/**
	 * Flush the buffer: build multi-row INSERT statements, chunk by max_allowed_packet,
	 * and execute them.
	 *
	 * @return int Number of rows inserted in this flush.
	 * @throws RuntimeException If an INSERT fails inside a transaction.
	 */
	public function flush(): int {
		if ( empty( $this->buffer ) ) {
			return 0;
		}

		$rows_to_flush = $this->buffer;
		$this->buffer  = [];

		// Prepare each row into a sanitized tuple string.
		$tuples = [];
		foreach ( $rows_to_flush as $row ) {
			$tuples[] = $this->prepare_tuple( $row );
		}

		// Chunk tuples so each INSERT fits within max_allowed_packet.
		$chunks       = $this->chunk_tuples( $tuples );
		$rows_flushed = 0;

		foreach ( $chunks as $chunk_tuples ) {
			$sql = $this->insert_prefix . implode( ', ', $chunk_tuples );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Each tuple is already prepared via wpdb::prepare() in prepare_tuple().
			$result = $this->wpdb->query( $sql );

			if ( false === $result ) {
				$error          = 'INSERT failed: ' . $this->wpdb->last_error;
				$this->errors[] = $error;

				// Inside a transaction: fail fast — rollback and throw.
				if ( $this->in_transaction ) {
					$this->rollback();
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not HTML output.
					throw new RuntimeException( 'BulkInserter: ' . $error . ' — transaction rolled back.' );
				}
				// Outside transaction: log error, continue with remaining chunks.
				continue;
			}

			$rows_flushed += $result;
		}

		$this->total_inserted += $rows_flushed;
		return $rows_flushed;
	}

	// -------------------------------------------------------------------------
	// Convenience
	// -------------------------------------------------------------------------

	/**
	 * All-in-one: begin transaction → add rows → flush → commit.
	 *
	 * @param array[] $rows Rows to insert.
	 * @return array{inserted: int, errors: string[]}
	 */
	public function insert_rows( array $rows ): array {
		$this->reset_results();

		try {
			$this->begin_transaction();
			$this->add_rows( $rows );
			$this->flush();
			$this->commit();
		} catch ( RuntimeException $e ) {
			$this->errors[] = $e->getMessage();
		}

		return $this->get_results();
	}

	// -------------------------------------------------------------------------
	// State
	// -------------------------------------------------------------------------

	/**
	 * @return array{inserted: int, errors: string[]}
	 */
	public function get_results(): array {
		return [
			'inserted' => $this->total_inserted,
			'errors'   => $this->errors,
		];
	}

	/**
	 * Reset counters and error log.
	 */
	public function reset_results(): void {
		$this->total_inserted = 0;
		$this->errors         = [];
	}

	/**
	 * @return int Number of rows currently in the buffer.
	 */
	public function get_buffer_count(): int {
		return count( $this->buffer );
	}

	/**
	 * @return bool Whether a transaction is currently active.
	 */
	public function is_in_transaction(): bool {
		return $this->in_transaction;
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Normalize associative or indexed row to indexed array matching $this->columns order.
	 *
	 * @param array $row Associative or indexed array of values.
	 * @return array Indexed values in column order.
	 * @throws RuntimeException On column count mismatch.
	 */
	private function normalize_row( array $row ): array {
		// Associative array: reorder by column names.
		if ( array_keys( $row ) !== range( 0, count( $row ) - 1 ) ) {
			$ordered = [];
			foreach ( $this->columns as $col ) {
				if ( ! array_key_exists( $col, $row ) ) {
					throw new RuntimeException( "BulkInserter: Missing column '$col' in row." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not HTML output.
				}
				$ordered[] = $row[ $col ];
			}
			return $ordered;
		}

		// Indexed array: validate count.
		if ( count( $row ) !== count( $this->columns ) ) {
			throw new RuntimeException(
				'BulkInserter: Row has ' . count( $row ) . ' values, expected ' . count( $this->columns ) . '.'
			);
		}

		return $row;
	}

	/**
	 * Build a prepared tuple string for one row: "(%d, %s, %s)" with values substituted.
	 *
	 * Uses $wpdb->prepare() for safe escaping, following the WordPress core pattern.
	 *
	 * @param array $row Indexed values.
	 * @return string e.g. "(42, 'some_key', 'some_value')"
	 */
	private function prepare_tuple( array $row ): string {
		$format_string = '(' . implode( ', ', $this->formats ) . ')';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- formats are constructor-controlled placeholders.
		return $this->wpdb->prepare( $format_string, $row );
	}

	/**
	 * Split an array of tuple strings into chunks that each fit within max_query_bytes
	 * when combined with the INSERT prefix.
	 *
	 * @param string[] $tuples Prepared tuple strings.
	 * @return array[] Array of chunks, each chunk an array of tuple strings.
	 */
	private function chunk_tuples( array $tuples ): array {
		$prefix_bytes = strlen( $this->insert_prefix );
		$chunks       = [];
		$current      = [];
		$current_size = $prefix_bytes;

		foreach ( $tuples as $tuple ) {
			$tuple_bytes = strlen( $tuple ) + 2; // +2 for ", " separator.

			// If adding this tuple would exceed the limit, start a new chunk.
			// Exception: if the current chunk is empty, include it anyway (oversized single row).
			if ( ! empty( $current ) && ( $current_size + $tuple_bytes ) > $this->max_query_bytes ) {
				$chunks[]     = $current;
				$current      = [];
				$current_size = $prefix_bytes;
			}

			$current[]     = $tuple;
			$current_size += $tuple_bytes;
		}

		if ( ! empty( $current ) ) {
			$chunks[] = $current;
		}

		return $chunks;
	}

	/**
	 * Determine the maximum query size in bytes from MySQL's max_allowed_packet.
	 *
	 * @return int Usable bytes (max_allowed_packet minus 64KB safety margin).
	 */
	private function resolve_max_query_bytes(): int {
		$fallback     = 4 * 1024 * 1024; // 4 MB.
		$safety       = 64 * 1024;        // 64 KB margin.
		$result       = $this->wpdb->get_row( "SHOW VARIABLES LIKE 'max_allowed_packet'", ARRAY_A );
		$packet_bytes = $result ? (int) $result['Value'] : $fallback;

		return max( $packet_bytes - $safety, $safety );
	}
}
