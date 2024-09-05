<?php

// activate_plugin( 'co-authors-plus/co-authors-plus.php' );

// Load this project.
require( __DIR__ . '/../../newspack-migration-tools.php' );

use Newspack\MigrationTools\Logic\CoAuthorsPlusHelper;

// Create a post. Must have title or no post is created.
$post_id = wp_insert_post( array(
    'post_title'   => 'my title',
));

// Create a GA.
$helper = new CoAuthorsPlusHelper();
$ga_id = $helper->create_guest_author( array( 'display_name' => 'Test User' ) );
$ga = $helper->get_guest_author_by_id( $ga_id );

// Try to assign author to post.
// assign_authors_to_post will throw exception on failure.
$helper->assign_authors_to_post( array( $ga ), $post_id );
