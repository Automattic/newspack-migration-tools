# Guest Contributors

## Overview

Guest Contributors are a feature of the Newspack Plugin that initializes a new role for WordPress Users for assigning users to posts, but the users themselves do not have access to the WordPress Admin. The `GuestContributorsHelper` class provides standardized ways to create and get these users.

## Prerequisites

* Newspack Plugin `>= 6.2.0` must be installed and activated to use this helper.

## GuestContributorsHelper

The `GuestContributorsHelper` class provides a set of static methods for working with guest contributors.

### Methods

#### create_by_display_name

Create a guest contributor by display name. Duplicate display names are allowed in WordPress, but this function will return an error if a matching display name is found. To bypass this error, set argument `$force = true`. Eitherway, created users will always have a unique `user_login` and unique `user_email`. The function will create a sanitized `user_nicename` (url slug) but WordPress may still add -2, -3, etc, if a matching slug already exists. To set a specific `user_nicename`, use the `$args` parameter.  

Within this function there is an error check for display name `> 250` since this could cause a WordPress bug that returns `int(0)` (instead of `WP_Error`) when calling `wp_insert_user`. Another error could be returned if pre-sanitization causes a blank `user_nicename` which cause WordPress to use `user_login` as the slug which is a security risk.

Example usage:

```php
use Newspack\MigrationTools\Logic\GuestContributorsHelper;

// Create a simple guest contributor
$user_id = GuestContributorsHelper::create_by_display_name( 'John Smith' );
if ( is_wp_error( $user_id ) ) WP_CLI::error( $user_id->get_error_message() );

// Create with force creation, even if a matching display name exists.
$user_id = GuestContributorsHelper::create_by_display_name(
    'John Smith',
    [],
    true
);
if ( is_wp_error( $user_id ) ) WP_CLI::error( $user_id->get_error_message() );

// Create with custom user_nicename (url slug).
$user_id = GuestContributorsHelper::create_by_display_name(
    'John Smith',
    [ 'user_nicename' => 'johnsmith-custom-url' ]
);
if ( is_wp_error( $user_id ) ) WP_CLI::error( $user_id->get_error_message() );
```

#### get_by_display_name

Get an array of guest contributor(s) by display name. Only guest contrubutors with a case-sensitive exact match will be returned. 

Example usage:
```php
use Newspack\MigrationTools\Logic\GuestContributorsHelper;

$users = GuestContributorsHelper::get_by_display_name( 'John Smith' );
if ( is_wp_error( $users ) ) WP_CLI::error( $users->get_error_message() );
```

## Notes

### To assign Guest Contributors to posts, use CoAuthors Plus

CoAuthors Plus is still required for Newspack (plugin and theme) as the way to handle multiple authors per post.  Only the CoAuthors Plus "Guest Authors" features is turned-off by default in Newspack Plugin, but the CoAuthors Plus multiple author taxonomy is still used.

Depending on the site you're working on, the Newspack Migration Tools `CoAuthorsPlusHelper` may not work.  Until that helper is refactored due to "Guest Authors" being defaulted to "off", please use the following code:

```php

// CAP Plugin is required.
if ( ! is_plugin_active( "co-authors-plus/co-authors-plus.php" ) ) {
    WP_CLI::error( 'Co-Authors Plus plugin not found. Install and activate it before using this code.' );
}

global $coauthors_plus;

// Integer array of WP_User IDs.
$author_ids = [ 1, 2, 3 ]; 

// Assign ids to post. 
$success = $coauthors_plus->add_coauthors( $post_id, $author_ids, false, 'id' );
if ( ! $success ) {
    WP_CLI::error( sprintf( 'Failed to set authors - add_coauthors return: %s', wp_json_encode( $success ) ) );
}
```




