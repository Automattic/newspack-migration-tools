<?php
/**
 * Newspack Migration Tools
 *
 * This is a library to be included by plugins. See README.md for more information.
 *
 * Version:           1.0.5
 * URL:               https://github.com/Automattic/newspack-migration-tools
 * Requires at least: 8.3
 *
 * @package newspack-migration-tools
 */

use Newspack\MigrationTools\NMT;

defined( 'ABSPATH' ) || exit;

NMT::setup();
