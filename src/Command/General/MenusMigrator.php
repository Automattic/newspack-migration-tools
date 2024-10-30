<?php

namespace NewspackCustomContentMigrator\Command\General;

use Newspack\MigrationTools\Command\PostsMigrator;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use NewspackCustomContentMigrator\Command\RegisterCommandInterface;
use WP_CLI;

/**
 * Exports and imports menus and associated content.
 */
class MenusMigrator implements RegisterCommandInterface {

	use WpCliCommandTrait;

	/**
	 * @var string Menu file name.
	 */
	const MENU_EXPORT_FILE = 'newspack-menu-export.json';

	/**
	 * {@inheritDoc}
	 */
	public static function register_commands(): void {
		WP_CLI::add_command( 'newspack-content-migrator export-menus', self::get_command_closure( 'cmd_export_menus' ), [
			'shortdesc' => 'Exports menu elements of the staging site and associated pages when needed.',
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'output-dir',
					'description' => 'Output directory full path (no ending slash).',
					'optional'    => false,
					'repeating'   => false,
				],
			],
		] );

		WP_CLI::add_command( 'newspack-content-migrator import-menus', self::get_command_closure( 'cmd_import_menus' ), [
			'shortdesc' => 'Imports custom menus and new pages from the export JSON file.',
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'input-dir',
					'description' => 'Exported Menus JSON directory.',
					'optional'    => false,
					'repeating'   => false,
				],
			],
		] );
	}

	/**
	 * Callable for export-menus command.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function cmd_export_menus( $args, $assoc_args ) {
		$export_dir = isset( $assoc_args[ 'output-dir' ] ) ? $assoc_args[ 'output-dir' ] : null;
		if ( ! is_dir( $export_dir ) ) {
			mkdir( $export_dir );
		}

		WP_CLI::line( sprintf( 'Exporting menus...' ) );

		$this->export_menus( $export_dir );

		WP_CLI::success( 'Done.' );
	}

	/**
	 * Export the site menus into two files: one for the menu info, one for the posts linked in the menu.
	 *
	 * @param string $export_dir
	 */
	public function export_menus( $export_dir ) {

		// Get all menus info gathered together.
		$menu_ids = array();
		foreach ( wp_get_nav_menus() as $nav_menu ) {
			$menu_ids[] = $nav_menu->term_id;
		}
		$locations = array_flip( get_nav_menu_locations() );
		$menus = [];

		// Get menu items' info.
		foreach ( $menu_ids as $menu_id ) {
			$menu = wp_get_nav_menu_object( $menu_id );
			if ( ! $menu ) {
				continue;
			}

			$menu_data = [
				'menu'       => $menu,
				'menu_items' => [],
				'location'   => $locations[ $menu_id ],
			];

			$menu_items = wp_get_nav_menu_items( $menu_id );
			foreach ( $menu_items as $menu_item ) {
				$menu_data['menu_items'][] = wp_setup_nav_menu_item( $menu_item );
			}

			$menus[] = $menu_data;
		}

		// Export menus to a custom JSON file.
		$menu_file = $export_dir . '/' . self::MENU_EXPORT_FILE;
		$open_menu_file = fopen( $menu_file, 'w' );
		if ( ! $open_menu_file ) {
			WP_CLI::error( 'Error creating or opening output file: ' . $menu_file );
		}
		$write = fputs( $open_menu_file, json_encode( $menus ) );
		if ( ! $write ) {
			WP_CLI::error( 'Error writing to output file: ' . $menu_file );
		}
		fclose( $open_menu_file );

		// WP_CLI::line( $output );
		WP_CLI::line( 'Writing to file ' . $menu_file );
	}

	/**
	 * Callable for import-menus command.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function cmd_import_menus( $args, $assoc_args ) {
		$directory = isset( $assoc_args['input-dir'] ) ? $assoc_args['input-dir'] : null;

		$directory = rtrim( $directory, '/' );
		$menu_file = $directory . '/' . self::MENU_EXPORT_FILE;
		if ( ! is_file( $menu_file ) ) {
			WP_CLI::warning( sprintf( 'Menus file not found in input-dir %s', $menu_file ) );
			exit(1);
		}

		WP_CLI::line( sprintf( 'Importing menus from ' . $menu_file . '...' ) );

		$this->delete_all_menus();
		$this->import_menus( $menu_file ) ;
		wp_cache_flush();

		WP_CLI::success( 'Done.' );
	}

	/**
	 * Deletes all existing menus.
	 */
	private function delete_all_menus() {
		$menus = wp_get_nav_menus();
		if ( empty( $menus ) ) {
			return;
		}

		foreach ( $menus as $menu ) {
			wp_delete_nav_menu( $menu->term_id );
		}
	}

	/**
	 * Imports menus.
	 *
	 * @param string $menu_file Full path to menu json file.
	 */
	private function import_menus( $menu_file ) {
		global $wpdb;

		$menu_read = file_get_contents( $menu_file );
		if ( empty( $menu_read ) ) {
			WP_CLI::error( 'Error reading from menu file' );
		}

		// Array with key-value pairs of ORIGINAL_MENU_ITEM_ID => FRESHLY_SAVED_MENU_ITEM_ID.
		$menu_item_parent_mapping = [];
		// Array with key-value pairs of ORIGINAL_POST_ID => CURRENT_POST_ID.
		$menu_item_object_mapping = [];

		$menus = json_decode( $menu_read );
		foreach ( $menus as $menu ) {
			// Create Menu
			$menuname = $menu->menu->name;
			$menu_id = wp_create_nav_menu( $menuname );
			$taxonomy = $menu->menu->taxonomy;
			$term_taxonomy_id = $wpdb->get_var($wpdb->prepare("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id=%d AND taxonomy='%s' LIMIT 1", $menu_id, $taxonomy ) );
			$menu_data = array(
				'menu-name' => $menuname,
				'description' => $menu->menu->description,
				'parent' => $menu->menu->parent,
				'slug' => $menu->menu->slug,
				'term_taxonomy_id' => $term_taxonomy_id,
				'taxonomy' => $taxonomy,
				'term' => $menu->menu->taxonomy,
				'filter' => $menu->menu->filter,
			);
			$updated_id = wp_update_nav_menu_object( $menu_id, $menu_data );

			// Save menu items.
			foreach ( $menu->menu_items as $menu_item ) {

				// For Pages: all migrated pages got the \NewspackCustomContentMigrator\Command\General\PostsMigrator::META_KEY_ORIGINAL_ID meta
				// with their original ID. Based on that meta, get the new ID.
				if ( 'page' === $menu_item->object ) {
					$result_original_id = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT post_id FROM {$wpdb->prefix}postmeta
								WHERE meta_key = '%s'
								AND meta_value = %d ;",
							PostsMigrator::META_KEY_ORIGINAL_ID,
							$menu_item->object_id
						),
						ARRAY_A
					);
					if ( ! isset( $result_original_id[0][ 'post_id' ] ) ) {
						WP_CLI::line(
							sprintf(
								'Error in menu ID %d: (original) item ID %d not found ! Skipping menu item.',
								$menu_id,
								$menu_item->object_id
							)
						);
						continue;
					}

					$current_id = $result_original_id[0][ 'post_id' ];
				} else {
					$current_id = $menu_item->object_id;
				}

				// Get the item's parent's current ID.
				$menu_item_parent = $menu_item->menu_item_parent;
				if ( '0' !== $menu_item_parent && isset( $menu_item_parent_mapping[ $menu_item_parent ] ) ) {
					$menu_item_parent = $menu_item_parent_mapping[ $menu_item_parent ];
				}

				$menu_item_classes = is_array( $menu_item->classes ) && ! empty( $menu_item->classes )
					? implode( ' ', $menu_item->classes )
					: '';

				$item_data =  array(
					'menu-item-object-id'   => $current_id,
					'menu-item-object'      => $menu_item->object,
					'menu-item-position'    => $menu_item->menu_order,
					'menu-item-type'        => $menu_item->type,
					'menu-item-title'       => $menu_item->title,
					'menu-item-url'         => $menu_item->url,
					'menu-item-description' => $menu_item->description,
					'menu-item-attr-title'  => $menu_item->attr_title,
					'menu-item-target'      => $menu_item->target,
					'menu-item-xfn'         => $menu_item->xfn,
					'menu-item-classes'     => $menu_item_classes,
					'menu-item-parent-id'   => $menu_item_parent,
					'menu-item-status'      => 'publish',
				);
				$menu_item_id = wp_update_nav_menu_item( $menu_id, 0, $item_data );

				if ( ! is_wp_error( $menu_item_id ) ) {
					$menu_item_parent_mapping[ $menu_item->ID ] = $menu_item_id;
				}
			}

			// Set menu locations where possible.
			$valid_locations = get_registered_nav_menus();
			$set_menus       = get_theme_mod( 'nav_menu_locations', [] );
			if ( isset( $valid_locations[ $menu->location ] ) ) {
				$set_menus[ $menu->location ] = $menu_id;
				set_theme_mod( 'nav_menu_locations', $set_menus );
			}
		}
	}
}
