# Original Values

During content migration, you often need to preserve original values from the source site - like author IDs, category names, custom field values, or any other data that might be needed for reference, mapping, or debugging.

The `OriginalValueStore` class provides a consistent way to store and retrieve these values as WordPress metadata.

**Consistent Storage**: All original values use a standardized key format (`_nmt_original_<entity>_<key>`) making them easy to identify and manage.

**Flexible**: Works with posts, terms, and users. Store any type of value (strings, numbers, arrays).

**Discoverable**: Use WP-CLI commands to query and manage stored values without writing custom code.

**Clean Up**: Built-in commands to delete values when migration is complete.

## Code Usage

### Saving Original Values
The below keys used are just examples. You use them however you want :) 

```php
use Newspack\MigrationTools\Logic\OriginalValueStore;

// Save original author ID when creating a post
$new_post_id = wp_insert_post( $post_data );
OriginalValueStore::save_for_post( $new_post_id, 'author_id', $drupal_author_id );

// Save original taxonomy name when creating a term
$new_term = wp_insert_term( 'Technology', 'category' );
OriginalValueStore::save_for_term( $new_term['term_id'], 'taxonomy', 'drupal_vocabulary_tech' );

// Save original username when creating a user
$new_user_id = wp_insert_user( $user_data );
OriginalValueStore::save_for_user( $new_user_id, 'alternative_username', $old_username );
```

### Retrieving Original Values

```php
// Get the original author ID for a post
$original_author_id = OriginalValueStore::get_for_post( $post_id, 'author_id' );

// Get the original taxonomy name for a term
$original_taxonomy = OriginalValueStore::get_for_term( $term_id, 'taxonomy' );

// Get the original username for a user
$original_username = OriginalValueStore::get_for_user( $user_id, 'alternative_username' );
```

### Storage Format

All values are stored with the same meta key prefix:

- `_nmt_original_permalink`
- `_nmt_original_thing`
- `_nmt_original_something_else`

You can also get the full meta key programmatically:

```php
$meta_key = OriginalValueStore::key_for( 'author_id' );
// Returns: _nmt_original_author_id
```

## WP-CLI Commands

Note that when passing the key to CLI command, just use the part after `_nmt_original_`. So for `nmt_original_author_id`, just use `author_id`.

### Get a Single Value

```bash
# Get original author_id for post 123
wp newspack-migration-tools original-value post get 123 author_id

# Get original taxonomy for term 45
wp newspack-migration-tools original-value term get 45 taxonomy

# Get original username for user 1
wp newspack-migration-tools original-value user get 1 username
```

### List All Values for a Key

```bash
# List all posts with an author_id stored
wp newspack-migration-tools original-value post list author_id

# List all terms with a taxonomy stored
wp newspack-migration-tools original-value term list taxonomy

# List all users with a username stored
wp newspack-migration-tools original-value user list username
```

### Batch Processing

All list commands support batching:

```bash
# List posts 1-100
wp newspack-migration-tools original-value post list author_id --start=1 --end=100

# List posts 101-200
wp newspack-migration-tools original-value post list author_id --start=101 --end=200

# List using num-items (default 500)
wp newspack-migration-tools original-value post list author_id --num-items=1000
```

### Output Formats

```bash
# Table output (default)
wp newspack-migration-tools original-value post list author_id

# CSV output
wp newspack-migration-tools original-value post list author_id --format=csv

# JSON output
wp newspack-migration-tools original-value post list author_id --format=json
```

### Delete Values

```bash
# Delete all author_id values (posts, terms, and users)
wp newspack-migration-tools original-value delete author_id

# Delete only from posts
wp newspack-migration-tools original-value delete author_id --posts

# Delete only from terms
wp newspack-migration-tools original-value delete author_id --terms

# Delete only from users
wp newspack-migration-tools original-value delete author_id --users
```

## Specialized Helpers

For specific types of data with special handling requirements, you can create thin wrapper classes like `OriginalPermalink`:

## Best Practices

1. **Use descriptive keys**: Use clear key names like `author_id`, `category_slug`, `custom_field_name` rather than generic names.

2. **Document your keys**: Keep track of what keys you're using in your migration documentation.

3. **Clean up after migration**: Use the delete commands to remove original values once migration is complete and verified.

4. **Use consistent naming**: Stick to a naming convention (snake_case, camelCase, etc.) for your keys.

5. **Batch operations**: When working with large datasets, always use batch arguments to avoid memory issues.

## See Also

- [Original Permalinks Documentation](original-permalinks.md) - Example of a specialized helper using OriginalValueStore
- [BatchLogic Documentation](batch-logic.md) - Details on batch processing
