<?php

namespace Newspack\MigrationTools\Logic;

use Newspack\MigrationTools\NMT;
use NinjaTables\Classes\ArrayHelper;
use NinjaTablesAdmin;

/**
 * NinjaTables Plugin Migrator Logic.
 *
 * NOTE: That this might be outdated.
 */
class NinjaTablesHelper {
	/**
	 * @var NinjaTablesAdmin
	 */
	public $ninja_tables_admin;

	/**
	 * NinjaTables constructor.
	 */
	public function __construct() {
		if ( ! is_plugin_active( 'ninja-tables/ninja-tables.php' ) ) {
			NMT::exit_with_message( 'Ninja Tables is a dependency, and will have to be installed and activated before this command can be used.' );
		}

		$this->ninja_tables_admin = new NinjaTablesAdmin();
	}

	/**
	 * Export data function, based on exportData method from the NinjaTables plugin.
	 *
	 * @param int    $table_id Table to export ID.
	 * @param string $file_path File path where to export the data.
	 * @param string $format Data format to export to. It can be CSV or JSON.
	 */
	public function export_data( $table_id, $file_path, $format ) {
		$table_columns  = \ninja_table_get_table_columns( $table_id, 'admin' );
		$table_settings = \ninja_table_get_table_settings( $table_id, 'admin' );

		$header = array();
		foreach ( $table_columns as $item ) {
			$header[ $item['key'] ] = $item['name'];
		}

		if ( 'csv' === $format ) {
			$sorting_type  = ArrayHelper::get( $table_settings, 'sorting_type', 'by_created_at' );
			$table_columns = \ninja_table_get_table_columns( $table_id, 'admin' );
			$data          = \ninjaTablesGetTablesDataByID( $table_id, $table_columns, $sorting_type, true );

			$export_data = array();

			foreach ( $data as $item ) {
				$temp = array();
				foreach ( $header as $accessor => $name ) {
					$value = ArrayHelper::get( $item, $accessor );
					if ( is_array( $value ) ) {
						$value = implode( ', ', $value );
					}
					$temp[] = $value;
				}
				array_push( $export_data, $temp );
			}
			$this->export_as_csv( array_values( $header ), $export_data, $file_path );
		} elseif ( 'json' === $format ) {
			$table = get_post( $table_id );

			$data_provider = \ninja_table_get_data_provider( $table_id );
			$rows          = array( array_values( $header ) );
			if ( 'default' === $data_provider ) {
				$raw_rows = \ninja_tables_DbTable()
					->select( array( 'position', 'owner_id', 'attribute', 'value', 'settings', 'created_at', 'updated_at' ) )
					->where( 'table_id', $table_id )
					->get();
				foreach ( $raw_rows as $row ) {
					$rows[] = array_values( json_decode( $row->value, true ) );
				}
			}

			$data = array(
				'name' => $table->post_title,
				'data' => $rows,
			);

			file_put_contents( $file_path, wp_json_encode( $data ) );
		} else {
			NMT::exit_with_message( sprintf( 'The %s export format is not supported, please choose either csv or json.', $format ) );
		}
	}

	/**
	 * Fill file with CSV data from given array.
	 *
	 * @param mixed[] $header Data columns titles.
	 * @param mixed[] $data Data to save in the file as CSV.
	 * @param string  $file_path File path where to save the CSV file.
	 */
	private function export_as_csv( $header, $data, $file_path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$f = fopen( $file_path, 'w' );

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
		fputcsv( $f, $header );
		foreach ( $data as $row ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
			fputcsv( $f, $row );
		}

		fclose( $f );
	}
}
