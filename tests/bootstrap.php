<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Newspack_Migration_Tools
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Include and activate the 3rd-party plugins we use.
 *
 * See install_contrib_plugins in bin/install-wp-tests.sh for the list of plugins we download.
 */
function set_up_contrib_plugins(): void {
	// Add plugins to this array to have them loaded by the test suite.
	// Note that it would load and activate the plugin in all tests, so use sparingly.
	$plugins_active_in_tests = [
		// The CAP plugin is used so much in our code that it is hard to test without it.
		'co-authors-plus' => 'co-authors-plus/co-authors-plus.php',
	];

	$wordpress_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress';
	foreach ( $plugins_active_in_tests as $plugin ) {
		$plugin_file = "$wordpress_dir/wp-content/plugins/$plugin";

		if ( ! file_exists( $plugin_file ) ) {
			// Be very, very specific about what is wrong to save hours of error finding.
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.HeredocOutputNotEscaped
			echo <<<MESSAGE
			----------------------------------------------
			👋
			This is an error message from your friendly function set_up_contrib_plugins() in bootstrap.php:
			Could not find the plugin file: $plugin_file 
			Make sure the plugin gets downloaded in the install_contrib_plugins() function in bin/install-wp-tests.sh.
			Make sure you have run ./bin/install-wp-tests.sh.
			Sometimes deleting the WordPress test directory $wordpress_dir and then running ./bin/install-wp-tests.sh again helps.\n
			----------------------------------------------
			MESSAGE;
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Security.EscapeOutput.HeredocOutputNotEscaped
			exit( 1 );
		}
		tests_add_filter( 'muplugins_loaded', fn() => require $plugin_file );
	}

	// This will activate the plugins in the test suite.
	$GLOBALS['wp_tests_options']['active_plugins'] = $plugins_active_in_tests;
}

// Include Newspack Migration Tools.
tests_add_filter( 'muplugins_loaded', fn() => require dirname( __DIR__ ) . '/newspack-migration-tools.php' );

// Include and "activate" plugins needed.
set_up_contrib_plugins();

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
