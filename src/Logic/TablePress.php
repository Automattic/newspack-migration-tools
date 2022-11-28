<?php

namespace NewspackCustomContentMigrator\Logic;

use \TablePress as TablePress_Plugin;
use \TablePress_Import;
use \TablePress_Admin_Controller;

/**
 * TablePress Plugin Migrator Logic.
 */
class TablePress {
	/**
	 * @var null|TablePress_Import
	 */
	public $tablepress_import;

	/**
	 * @var null|TablePress_Admin_Controller
	 */
	public $tablepress_controller_admin;

	/**
	 * TablePress constructor.
	 */
	public function __construct() {
		$plugin_path = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : ABSPATH . 'wp-content/plugins';

		$tablepress                           = $plugin_path . '/tablepress/tablepress.php';
		$tablepress_import                    = $plugin_path . '/tablepress/classes/class-import.php';
		$tablepress_controller                = $plugin_path . '/tablepress/classes/class-controller.php';
		$tablepress_controller_admin          = $plugin_path . '/tablepress/controllers/controller-admin.php';
		$included_tablepress                  = is_file( $tablepress ) && include_once $tablepress;
		$included_tablepress_import           = is_file( $tablepress_import ) && include_once $tablepress_import;
		$included_tablepress_controller       = is_file( $tablepress_controller ) && include_once $tablepress_controller;
		$included_tablepress_controller_admin = is_file( $tablepress_controller_admin ) && include_once $tablepress_controller_admin;

		if ( false === $included_tablepress || false === $included_tablepress_import || false === $included_tablepress_controller_admin || false === $included_tablepress_controller ) {
			// TablePress is a dependency, and will have to be installed before the public functions/commands can be used.
			return;
		}

		TablePress_Plugin::run();
		$this->tablepress_import           = new TablePress_Import();
		$this->tablepress_controller_admin = new TablePress_Admin_Controller();
	}

	/**
	 * Import a table.
	 *
	 * @param string $format Import format.
	 * @param string $data   Data to import.
	 * @return array|false Table array on success, false on error.
	 */
	public function import_table( $format, $data, $filename, $description ) {
		$reflector = new \ReflectionObject( $this->tablepress_controller_admin );
		$importer  = $reflector->getProperty( 'importer' );
		$importer->setAccessible( true );
		$method = $reflector->getMethod( '_import_tablepress_table' );
		$method->setAccessible( true );

		$name = basename( $filename, ".$format" );

		$importer->setValue( $this->tablepress_controller_admin, $this->tablepress_import );
		return $method->invoke( $this->tablepress_controller_admin, $format, $data, $name, $description, false, 'add' );
	}
}
