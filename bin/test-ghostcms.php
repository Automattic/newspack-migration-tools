<?php

// Load this project.
require( __DIR__ . '/../newspack-migration-tools.php' );

// Run test.
$testGhostCMSHelper = new \Newspack\MigrationTools\Logic\GhostCMSHelper();
$testGhostCMSHelper->ghostcms_import( 
    [], 
    [
        'json-file'       => 'tests/fixtures/ghostcms.json',
        'ghost-url'       => 'https://newspack.com/',
        'default-user-id' => 1,
    ],
    WP_CONTENT_DIR . '/test-ghostcms.log'
);

