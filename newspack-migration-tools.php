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

use Newspack\MigrationTools\Command\WpCliCommands;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';
