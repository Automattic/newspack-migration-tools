# Guest Contributors

## Overview

Guest Contributors are a feature of the Newspack Plugin that initializes a new role for WordPress Users for assigning users to posts, but the users themselves do not have access to the WordPress Admin. The `GuestContributorsHelper` class provides standardized ways to create and get these users.

## Prerequisites

-   Newspack Plugin `>= 6.2.0` must be installed and activated to use this helper.
-   Co-Authors Plus plugin must be installed and activated for assigning contributors to posts.

## GuestContributorsHelper

The `GuestContributorsHelper` class provides a set of static methods for working with guest contributors.

### Methods

#### create_or_get_contributor

Create or get a contributor user. This is a wrapper around `UsersHelper::create_or_get_user` that sets the role to the guest contributor role.

**Parameters:**

-   `$data` (array): The data to create the user with. The array is the same as `wp_insert_user` accepts. You must provide one of the following fields: 'user_email', 'user_login', 'user_nicename', 'display_name'.
-   `$unique_identifier` (string): A unique identifier for the user.

**Returns:** `WP_User|WP_Error` - The user object if created or found, or a WP_Error if the user cannot be created.

Example usage:

```php
use Newspack\MigrationTools\Logic\GuestContributorsHelper;

$user_data = [
    'display_name' => 'John Smith',
    'user_email'   => 'john.smith@example.com',
    'user_login'   => 'johnsmith',
];

$user = GuestContributorsHelper::create_or_get_contributor( $user_data, 'john-smith-unique' );
if ( is_wp_error( $user ) ) {
    WP_CLI::error( $user->get_error_message() );
}
```

#### create_by_display_name

Create a guest contributor by display name. Duplicate display names are allowed in WordPress, but this function will return an error if a matching display name is found. To bypass this error, set argument `$force = true`. Eitherway, created users will always have a unique `user_login` and unique `user_email`. The function will create a sanitized `user_nicename` (url slug) but WordPress may still add -2, -3, etc, if a matching slug already exists. To set a specific `user_nicename`, use the `$args` parameter.

Within this function there is an error check for display name `> 250` since this could cause a WordPress bug that returns `int(0)` (instead of `WP_Error`) when calling `wp_insert_user`. Another error could be returned if pre-sanitization causes a blank `user_nicename` which cause WordPress to use `user_login` as the slug which is a security risk.

**Parameters:**

-   `$display_name` (string): The Display Name of the new user. Duplicates are allowed with `$force = true`.
-   `$args` (array): Optional. Array of additional arguments.
    -   `user_nicename` (string): URL slug for user. Duplicates will be appended by WordPress with -2, -3, ...
-   `$force` (bool): Force user creation even if display name matches existing user(s).

**Returns:** `int|WP_Error` - Inserted user ID or WP_Error.

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

Get an array of guest contributor(s) by display name. Only guest contributors with a case-sensitive exact match will be returned.

**Parameters:**

-   `$display_name` (string): Display name to find.

**Returns:** `array|WP_Error` - Array of user ID(s) or WP_Error.

Example usage:

```php
use Newspack\MigrationTools\Logic\GuestContributorsHelper;

$users = GuestContributorsHelper::get_by_display_name( 'John Smith' );
if ( is_wp_error( $users ) ) WP_CLI::error( $users->get_error_message() );
```

#### assign_contributors_to_post

Assigns Guest Contributors to a Post using Co-Authors Plus.

**Parameters:**

-   `$post_id` (int): Post ID.
-   `$contributor_ids` (array): Array of contributor IDs.

**Returns:** `true|WP_Error` - True if successful, WP_Error if not.

Example usage:

```php
use Newspack\MigrationTools\Logic\GuestContributorsHelper;

$post_id = 123;
$contributor_ids = [ 1, 2, 3 ];

$result = GuestContributorsHelper::assign_contributors_to_post( $post_id, $contributor_ids );
if ( is_wp_error( $result ) ) {
    WP_CLI::error( $result->get_error_message() );
}
```

### Error Handling

All methods in the `GuestContributorsHelper` class return either the expected result or a `WP_Error` object. Always check for errors:

```php
$result = GuestContributorsHelper::create_by_display_name( 'John Smith' );
if ( is_wp_error( $result ) ) {
    // Handle error
    WP_CLI::error( $result->get_error_message() );
} else {
    // Use the result (user ID)
    $user_id = $result;
}
```

### Common Error Messages

-   `ERROR_NEWSPACK_PLUGIN`: Newspack Plugin's Guest Contributors feature is required
-   `ERROR_DISPLAY_NAME`: Display Name must be between 1 and 250 characters
-   `ERROR_EXISTING_USERS`: Existing user(s) found. Use `$force = true` to skip this check
-   `ERROR_COAUTHORS_PLUS`: Co-Authors Plus plugin not found
-   `ERROR_USER_NICENAME`: User nicename cannot be blank
