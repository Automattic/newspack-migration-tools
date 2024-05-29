<?php
/**
 * Plugin Name: Newspack Migration Tools
 * Plugin URI: https://github.com/automattic/newsletter-migration-tools
 * Description: A set of tools to help migration to WordPress.
 * Author: Automattic
 * Author URI: https://newspack.com/
 * Version: 0.0.1
 *
 * @package newspack-migration-tools
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

add_action(
	'init',
	function() {
		// Initialize the migration commands.
		$migrators_classes = [
			Newspack\MigrationTools\Command\AttachmentsMigrator::class,
		];

		foreach ( $migrators_classes as $migrator_class ) {
			$migrator = $migrator_class::get_instance();
			$migrator->register_commands();
		}
	}
);
