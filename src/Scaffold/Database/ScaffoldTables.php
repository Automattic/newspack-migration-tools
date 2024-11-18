<?php

namespace Newspack\MigrationTools\Scaffold\Database;

/**
 * Class ScaffoldTables
 */
class ScaffoldTables {

	/**
	 * Creates the custom database tables for use with the Migration Scaffold tool.
	 *
	 * @return void
	 */
	public static function create() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$migration_table = "CREATE TABLE IF NOT EXISTS migrations (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			version integer NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY name_version (name, version)
		) $charset_collate;";

		$migration_status_enum_table = "CREATE TABLE IF NOT EXISTS migration_status_enum (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY name (name)
		) $charset_collate;";

		$migration_status_table = "CREATE TABLE IF NOT EXISTS migration_status (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			migration_id bigint(20) NOT NULL,
			status_id bigint(20) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			FOREIGN KEY (migration_id) REFERENCES migrations(id),
			FOREIGN KEY (status_id) REFERENCES migration_status_enum(id)
		) $charset_collate;";

		$migration_data_chests_table = "CREATE TABLE IF NOT EXISTS migration_data_chests (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			migration_id bigint(20) NOT NULL,
			pointer_to_object_id varchar(255) NOT NULL,
			json_data longtext NOT NULL,
			source_type varchar(255) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			FOREIGN KEY (migration_id) REFERENCES migrations(id)
		) $charset_collate;";

		$migration_objects_table = "CREATE TABLE IF NOT EXISTS migration_objects (
    		id bigint(20) NOT NULL AUTO_INCREMENT,
    		migration_data_chest_id bigint(20) NOT NULL,
    		original_object_id varchar(255) NOT NULL,
    		json_data longtext NOT NULL,
    		processed boolean NOT NULL DEFAULT 0,
    		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
    		PRIMARY KEY  (id),
    		FOREIGN KEY (migration_data_chest_id) REFERENCES migration_data_chests(id)
		) $charset_collate;";

		$migration_object_mutation = "CREATE TABLE IF NOT EXISTS migration_object_mutation (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			migration_object_id bigint(20) NOT NULL,
			json_data longtext NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			FOREIGN KEY (migration_object_id) REFERENCES migration_objects(id)
		) $charset_collate;";

		$migration_object_meta = "CREATE TABLE IF NOT EXISTS migration_object_meta (
    		id bigint(20) NOT NULL AUTO_INCREMENT,
    		migration_data_chest_id bigint(20) NOT NULL,
    		migration_object_id bigint(20) NULL DEFAULT NULL,
    		meta_key varchar(255) NOT NULL,
    		meta_value longtext NOT NULL,
    		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
    		PRIMARY KEY  (id),
    		FOREIGN KEY (migration_data_chest_id) REFERENCES migration_data_chests(id),
    		FOREIGN KEY (migration_object_id) REFERENCES migration_objects(id)
		) $charset_collate;";

		$wordpress_tables = "CREATE TABLE IF NOT EXISTS wordpress_tables (
    		id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY name (name)
		) $charset_collate;";

		$wordpress_table_columns = "CREATE TABLE IF NOT EXISTS wordpress_table_columns (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			wordpress_table_id bigint(20) NOT NULL,
			name varchar(255) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			FOREIGN KEY (wordpress_table_id) REFERENCES wordpress_tables(id),
			UNIQUE KEY wordpress_table_id_name (wordpress_table_id, name)
		) $charset_collate;";

		$migration_destination_sources = "CREATE TABLE IF NOT EXISTS migration_destination_sources (
    		id bigint(20) NOT NULL AUTO_INCREMENT,
    		migration_object_id bigint(20) NOT NULL,
    		wordpress_table_column_id bigint(20) NOT NULL,
    		wordpress_object_id bigint(20) NOT NULL,
    		json_path varchar(255) NOT NULL,
    		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
    		PRIMARY KEY  (id),
    		FOREIGN KEY (migration_object_id) REFERENCES migration_objects(id),
    		FOREIGN KEY (wordpress_table_column_id) REFERENCES wordpress_table_columns(id)
		) $charset_collate;";

		dbDelta( $migration_table );
		dbDelta( $migration_status_enum_table );
		dbDelta( $migration_status_table );
		dbDelta( $migration_data_chests_table );
		dbDelta( $migration_objects_table );
		dbDelta( $migration_object_mutation );
		dbDelta( $migration_object_meta );
		dbDelta( $wordpress_tables );
		dbDelta( $wordpress_table_columns );
		dbDelta( $migration_destination_sources );

		$migration_status_names = [
			'STARTED',
			'RUNNING',
			'COMPLETED',
			'FAILED',
			'CANCELLED',
		];

		foreach ( $migration_status_names as $status_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				'migration_status_enum',
				[ 'name' => $status_name ]
			);
		}

		$wordpress_tables_names = [
			'posts',
			'postmeta',
			'terms',
			'term_taxonomy',
			'term_relationships',
			'users',
			'usermeta',
			'comments',
			'commentmeta',
		];

		foreach ( $wordpress_tables_names as $table_name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				'wordpress_tables',
				[ 'name' => $table_name ]
			);
		}

		$wordpress_table_columns_names = [
			'posts'              => [
				'ID',
				'post_author',
				'post_date',
				'post_date_gmt',
				'post_content',
				'post_title',
				'post_excerpt',
				'post_status',
				'comment_status',
				'ping_status',
				'post_password',
				'post_name',
				'to_ping',
				'pinged',
				'post_modified',
				'post_modified_gmt',
				'post_content_filtered',
				'post_parent',
				'guid',
				'menu_order',
				'post_type',
				'post_mime_type',
				'comment_count',
			],
			'postmeta'           => [
				'meta_id',
				'post_id',
				'meta_key',
				'meta_value',
			],
			'terms'              => [
				'term_id',
				'name',
				'slug',
				'term_group',
			],
			'term_taxonomy'      => [
				'term_taxonomy_id',
				'term_id',
				'taxonomy',
				'description',
				'parent',
				'count',
			],
			'term_relationships' => [
				'object_id',
				'term_taxonomy_id',
				'term_order',
			],
			'users'              => [
				'ID',
				'user_login',
				'user_pass',
				'user_nicename',
				'user_email',
				'user_url',
				'user_registered',
				'user_activation_key',
				'user_status',
				'display_name',
			],
			'usermeta'           => [
				'umeta_id',
				'user_id',
				'meta_key',
				'meta_value',
			],
			'comments'           => [
				'comment_ID',
				'comment_post_ID',
				'comment_author',
				'comment_author_email',
				'comment_author_url',
				'comment_author_IP',
				'comment_date',
				'comment_date_gmt',
				'comment_content',
				'comment_karma',
				'comment_approved',
				'comment_agent',
				'comment_type',
				'comment_parent',
				'user_id',
			],
			'commentmeta'        => [
				'meta_id',
				'comment_id',
				'meta_key',
				'meta_value',
			],
		];

		foreach ( $wordpress_table_columns_names as $table_name => $columns ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$table_id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM wordpress_tables WHERE name = %s', $table_name ) );
			foreach ( $columns as $column_name ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert(
					'wordpress_table_columns',
					[
						'wordpress_table_id' => $table_id,
						'name'               => $column_name,
					]
				);
			}
		}
	}
}
