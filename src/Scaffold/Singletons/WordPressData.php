<?php

namespace Newspack\MigrationTools\Scaffold\Singletons;

use Exception;

/**
 * Class WordPressData.
 */
class WordPressData {

	const CACHE_KEY   = 'wordpress_tables_columns';
	const CACHE_GROUP = 'newspack_migration_scaffold';

	/**
	 * Singleton instance.
	 *
	 * @var WordPressData $instance The singleton instance.
	 */
	private static $instance = null;

	/**
	 * The WordPress database prefix.
	 *
	 * @var string $prefix The WordPress database prefix.
	 */
	private static string $prefix;

	/**
	 * 2D array of table names and column names.
	 *
	 * @var array $values
	 *
	 * Structure like:
	 * [
	 *     $table => [
	 *         $column => ID,
	 *         ...,
	 *     ],
	 *     ...,
	 * ]
	 */
	private static array $values = [];

	/**
	 * Constructor. Private to prevent instantiation.
	 */
	private function __construct() {
		global $wpdb;

		self::$prefix = $wpdb->prefix;

		if ( ! wp_cache_get( self::CACHE_KEY, self::CACHE_GROUP ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$values = $wpdb->get_results(
				'SELECT wptc.id, LOWER( wpt.name ) as table_name, LOWER( wptc.name ) as column_name 
				FROM wordpress_tables wpt 
				    INNER JOIN wordpress_table_columns wptc ON wpt.id = wptc.wordpress_table_id'
			);

			foreach ( $values as $row ) {
				if ( array_key_exists( $row->table_name, self::$values ) ) {
					self::$values[ $row->table_name ][ $row->column_name ] = intval( $row->id );
				} else {
					self::$values[ $row->table_name ] = [ $row->column_name => intval( $row->id ) ];
				}
			}

			wp_cache_set( self::CACHE_KEY, self::$values, self::CACHE_GROUP, HOUR_IN_SECONDS );
		}

		self::$values = wp_cache_get( self::CACHE_KEY, self::CACHE_GROUP );
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return WordPressData The singleton instance.
	 */
	public static function get_instance(): WordPressData {
		$class = get_called_class();

		if ( null === self::$instance ) {
			self::$instance = new $class();
		}

		return self::$instance;
	}

	/**
	 * Get all column IDs for a table.
	 *
	 * @param string $table The table name.
	 *
	 * @return array The column IDs.
	 * @throws Exception If the table does not exist.
	 */
	public function get_column_ids_for_table( string $table ): array {
		$table = str_replace( self::$prefix, '', $table );
		$table = strtolower( $table );

		if ( self::$values[ $table ] ) {
			return self::$values[ $table ];
		}

		$exception_message = sprintf( '`%s` does not exist.', $table );
		throw new Exception( $exception_message );
	}

	/**
	 * Get the column ID.
	 *
	 * @param string $table The table name.
	 * @param string $column The column name.
	 *
	 * @return int The column ID.
	 * @throws Exception If the column does not exist.
	 */
	public function get_column_id( string $table, string $column ): int {
		$table_columns = $this->get_column_ids_for_table( $table );
		$column        = strtolower( $column );

		if ( $table_columns[ $column ] ) {
			return $table_columns[ $column ];
		}

		$exception_message = sprintf( '`%s`.`%s` does not exist.', $table, $column );
		throw new Exception( $exception_message );
	}
}
