# Original Permalinks

## What are source permalinks?

An original (migration source) permalink is just the original URL from the old site before you migrated it. When you're migrating content from Drupal (or whatever) to WordPress, you might want to keep track of what the old URLs were. That's all this is.

For example:
- Old site: `https://example.com/news/breaking-story`
- New site: `https://example.com/2024/01/breaking-story`

The source permalink here would be `/news/breaking-story` (we store it as a path, not a full URL).

## Why would you use this?

Setting up redirects, debugging migrations, or just keeping a record of where things came from. Once you're done with the migration and don't need them anymore, you can delete them.

## Using it in your migration code

The `OriginalPermalink` class gives you simple methods to save and get these paths.

### Saving source permalinks

```php
use Newspack\MigrationTools\Util\OriginalPermalink;

// For posts
$old_url = 'https://oldsite.com/some/article/path';
OriginalPermalink::save_for_post( $post_id, $old_url );

// For terms
$old_category_url = 'https://oldsite.com/category/news';
OriginalPermalink::save_for_term( $term_id, $old_category_url );
```

You can pass in either a full URL or just a path - it'll strip it down to a path automatically:
- `https://example.com/news/article` → `/news/article`
- `/news/article` → `/news/article`
- `news/article` → `/news/article`

### Getting source permalinks
Just use the `OriginalValueStore` like this:

```php
// For posts
$old_path = OriginalValueStore::get_for_post( $post_id );
// Returns: "/news/article"

// For terms
$old_path = OriginalValueStore::get_for_term( $term_id );
// Returns: "/category/news"
```

## WP-CLI commands

Once you've saved source permalinks during migration, you can view and manage them via WP-CLI.

### Get a single post or term

```bash
# Get source permalink for a specific post
wp newspack-migration-tools original-permalink post get 123

# Get it for a term
wp newspack-migration-tools original-permalink term get 456
```

This shows you a table with the post/term ID, the current WordPress path, and the old source path.

If you want to see full URLs instead of paths, use `--source-domain=`:

```bash
wp newspack-migration-tools original-permalink post get 123 --source-domain=oldsite.com
# or
wp newspack-migration-tools original-permalink post get 123 --source-domain=https://oldsite.com
```

### List all posts or terms with source permalinks

```bash
# List all posts
wp newspack-migration-tools original-permalink post list

# List all terms
wp newspack-migration-tools original-permalink term list
```

Both commands support batching for large datasets:

```bash
# Get the first 50
wp newspack-migration-tools original-permalink post list --num-items=50

# Get items 100-200
wp newspack-migration-tools original-permalink post list --start=100 --end=200

# Same for terms
wp newspack-migration-tools original-permalink term list --num-items=100
```

You can also output as CSV for easy spreadsheet viewing:

```bash
wp newspack-migration-tools original-permalink post list --format=csv > posts.csv
```

### Find URL mismatches

These commands show only posts or terms where the source permalink doesn't match the current WordPress URL. Super useful for debugging URL changes or finding content that needs redirects.

```bash
# List posts with mismatched URLs
wp newspack-migration-tools original-permalink post list-mismatches

# List terms with mismatched URLs
wp newspack-migration-tools original-permalink term list-mismatches
```

The comparison is exact (after normalizing trailing slashes), so `/news/article` and `/blog/article` would show up as a mismatch, but `/news/article` and `/news/article/` are considered the same.

Like the regular list commands, these support all the same options:

```bash
# Batch through mismatches
wp newspack-migration-tools original-permalink post list-mismatches --num-items=50

# Show full URLs instead of paths
wp newspack-migration-tools original-permalink post list-mismatches --source-domain=oldsite.com

# Export to CSV
wp newspack-migration-tools original-permalink post list-mismatches --format=csv > mismatches.csv
```

If you get "No mismatches found in this batch" - great! That means your URLs stayed consistent during migration.

### Delete source permalinks

When you're done with the migration and don't need the source permalinks anymore, use the generic original-value delete command:

```bash
# Delete all permalink data (posts, terms, and users)
wp newspack-migration-tools original-value delete permalink

# Delete only from posts
wp newspack-migration-tools original-value delete permalink --posts

# Delete only from terms
wp newspack-migration-tools original-value delete permalink --terms
```

## Technical details

- Original permalinks are stored as post/term metadata using `OriginalValueStore`
- Meta keys: `_nmt_original_post_permalink` and `_nmt_original_term_permalink`
- They're always stored as paths (with a leading slash), never full URLs
- The storage format is intentionally minimal - just the path
- See [Original Values Documentation](original-values.md) for more details on the underlying storage system
