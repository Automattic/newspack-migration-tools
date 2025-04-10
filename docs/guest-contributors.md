# Guest Contributors

## Overview

The Guest Contributors feature of the Newspack Plugin initializes a new role that can be used to create WordPress Users for assigning authorship to posts, but the users themselves will not have access to the WordPress Admin.

## Prerequisites

- The Newspack Plugin `>= 6.2.0` must be installed and activated to use this helper.  If Newspack Plugin is less than `6.2.0` then this helper will only work for code that is run in wp-admin and will fail in both CLI and PHPUNIT contexts.

## GuestContributorsHelper

The `GuestContributorsHelper` class provides a set of static methods for working with guest contributors.

### Methods

#### create_by_display_name

Create a guest contributor by display name.

### Display Name Sanitization
Display names are sanitized following WordPress core standards:
- Trimmed of whitespace
- Limited to 250 characters (WordPress core limitation)
- Must result in a non-empty string after sanitization



```php
public static function create_by_display_name( $display_name, $args = array(), $force = false ): int|\WP_Error
```

Parameters:

- `$display_name`: The display name of the guest contributor.
- `$args`: Optional. Additional arguments.
- `$force`: Optional. Whether to force user creation even if display name matches existing user(s).

Returns:

- `int`: The ID of the created user.
- `\WP_Error`: An error object if an error occurred. Possible error codes:
  - `ERROR_NEWSPACK_PLUGIN`: Newspack plugin's Guest Contributors feature is not available
  - `ERROR_DISPLAY_NAME`: Display name is invalid (empty or > 250 characters)
  - `ERROR_EXISTING_USERS`: Users with this display name already exist (when `$force = false`)
  - `ERROR_SANITIZE_INPUT`: Display name sanitization resulted in an empty string
  - `ERROR_USER_NICENAME`: User nicename cannot be blank
  - `ERROR_CREATE_USER`: WordPress failed to create the user
  - `ERROR_ATTEMPTS`: Too many attempts to generate unique email/username

Example Usage:

```php
use Newspack\MigrationTools\Logic\GuestContributorsHelper;

// Create a simple guest contributor
$user_id = GuestContributorsHelper::create_by_display_name( 'John Smith' );

// Create with custom user_nicename (url)
$user_id = GuestContributorsHelper::create_by_display_name(
    'John Smith',
    [ 'user_nicename' => 'johnsmith-guest' ],
);

// Create with force creation even if matching display name exists.
$user_id = GuestContributorsHelper::create_by_display_name(
    'John Smith',
    [],
    true
);

```

#### get_by_display_name

Get guest contributors by display name.

```php
public static function get_by_display_name( $display_name ): array|\WP_Error
```

Parameters:

- `$display_name`: The display name of the guest contributor to find.

Returns:

- `array`: An array of user IDs.
- `\WP_Error`: An error object if an error occurred.


#### generate_email

Generate a unique dummy email address with a random suffix.

Guest contributor email addresses are automatically generated using a dummy domain provided by the Newspack plugin. The format is:
```
{sanitized-display-name}-{random-suffix}@{dummy-domain}
```


```php
public static function generate_email( $display_name ): string|\WP_Error
```

Parameters:

- `$display_name`: The display name of the guest contributor.

Returns:

- `string`: The generated email address.
- `\WP_Error`: An error object if an error occurred.



#### generate_username

Generate a unique username (user_login) with a random suffix.

### Username Generation
Usernames (user_login) are automatically generated from the display name. The format is:
```
{sanitized-display-name}-{random-suffix}
```


```php
public static function generate_username( $display_name ): string|\WP_Error
```

Parameters:

- `$display_name`: The display name of the guest contributor.

Returns:

- `string`: The generated username.
- `\WP_Error`: An error object if an error occurred.


## Notes
### To assign Guest Contributors to posts, use CoAuthors Plus

CoAuthors Plus is still required for Newspack (plugin and theme) as the way to handle multiple authors per post.  Only the CoAuthors Plus "Guest Authors" features is turned-off by default in Newspack Plugin, but the CoAuthors Plus multiple author taxonomy is still used.

Dependent on the site you're working on, the NMT [CoAuthorsPlusHelper](https://github.com/Automattic/newspack-migration-tools/blob/trunk/src/Logic/CoAuthorsPlusHelper.php) may not work.  Until it is refactored due to "Guest Authors" being defaulted to "off", please use this code:

```
// CAP Plugin is required.
if ( ! is_plugin_active( "co-authors-plus/co-authors-plus.php" ) ) {
    WP_CLI::error( 'Co-Authors Plus plugin not found. Install and activate it before using this command.', true );
}

global $coauthors_plus;

// Integer array of WP_User IDs.
$author_ids = [ 1, 2, 3 ]; 

// Assign ids to post. 
// False means do not append; replace existing authors if exists. 
// 'id' since we're using IDs in the $author_ids array.
$success = $coauthors_plus->add_coauthors( $post_id, $author_ids, false, 'id' );
if ( ! $success ) {
    WP_CLI::error( sprintf( 'Failed to set authors - add_coauthors return: %s', wp_json_encode( $success ) ), true );
}
```




