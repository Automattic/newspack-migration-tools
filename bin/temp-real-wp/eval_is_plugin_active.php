<?php

// Load this project.
require( __DIR__ . '/../../newspack-migration-tools.php' );

use Newspack\MigrationTools\Logic\CoAuthorsPlusHelper;

$helper = new CoAuthorsPlusHelper();

// Verify the CAP plugin is not activated.
WP_CLI::line( "validate_co_authors_plus_dependencies: " . $helper->validate_co_authors_plus_dependencies() );

// Verify if CPT and Tax are loaded.
WP_CLI::line( "validate_co_authors_plus_cpt_tax_loaded: " . $helper->validate_co_authors_plus_cpt_tax_loaded() );
