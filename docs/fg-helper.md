# FG Helper
This is a helper class that provides some useful functions for the migration process with the FG plugins from [the great Frédéric Giles](https://www.fredericgilles.net). Thank you for your work, Frédéric!

- [Premium Support / Knowledge Base](https://www.fredericgilles.net/support/)
- [FG Drupal to WordPress (free)](https://wordpress.org/plugins/fg-drupal-to-wp/) 
- [FG Drupal to WordPress Premium](https://www.fredericgilles.net/fg-drupal-to-wordpress/)

## Setup

#### Plugins

Upload and activate the FG (Drupal/Joomla) to WP Premium plugin.
- FgHelper has been tested with FG Drupal to WP Premium version 3.85.2.

#### Database

Import the live database (drupal/joomla) backup into a mysql database (it can be the same database as WordPress).

#### Settings

FG plugin will create a settings page at wp-admin > tools > import > drupal/joomla. You do not need to adjust these settings.  All settings (options) will be set by the migrator when it's run.  Be advised that any FG options set in the wp-admin may be overwritten by the migrator. It's best to not set any settings via wp-admin and just let the migrator set them instead.

If you did set options in wp-admin and want to remove them, you can run:
```
wp db query "delete from wp_options where option_name like 'fg_2wp%';"
```

#### WP Config Constants

```
define( 'NCCM_SOURCE_WEBSITE_URL', '[ live site url ]' );
define( 'NCCM_FG_MIGRATOR_PREFIX', '[ database prefix (can be blank) ]' );
```

You _have to_ define the url of the original site you are migrating away from: `NCCM_SOURCE_WEBSITE_URL`. The code will error if you don't.

You can customize the table prefix for the tables that contain the old data with this constant: `NCCM_FG_MIGRATOR_PREFIX`.

Set environment values (for local Newspack Docker, set the values in .env file):
```
DB_HOST=$MYSQL_HOST (or different host)
DB_USER=$MYSQL_USER (or different user)
DB_PASSWORD=$MYSQL_PASSWORD (or different pass)
DB_NAME=[ your db name ] (can be same database as wordpress)
```

## Running Commands

The wrapper simplifies running the importer from the CLI, so to run it do something like this in your migrator class:

```php
use Newspack\MigrationTools\Util\FgHelper;
public function cmd_run_my_custom_import( array $pos_args, array $assoc_args ): void {
    $fg_helper = new FgHelper( '{drupal|joomla}' );
    add_action( 'fg_helper_pre_import', [ $this, 'add_fg_hooks' ] ); // If you want to add hooks before the import.
    $fg_helper->import( $pos_args, $assoc_args ); // This will run the importer.
}
```

This will ultimately cause the FG CLI command `wp import-{drupal|joomla} import` to be run, but using our filters and options.

#### Logging

FG will log to CLI, `wp-content/debug.log`, and `wp-content/uploads/fg{d|j}2wp-{random}.logs`. FG will also log to `wp-content/uploads/fg{d|j}2wp{p}-progress.json` - this stores the total number of items to migrate and a running count of items imported (example: `{"total":475101,"current":1440}`), it's used for CLI progress bar display.

Note: The "last article node id" is in the options table `fg{d|j}2wp_last_node_article_id`. If this option value is deleted, then the plugin will no longer run. The only way to get the migrator to run again would be to add the option by hand and set it's value to the last article id that was imported (either MAX or MIN value of `_fg{d|j}2wp_old_node_id` from the postmeta table depending on if importing newest or oldest ids first).

#### Re-running

In staging/production, you can just re-run the CLI command. There is no need to do any of the following clean. This is only needed for local testing or if you really need to wipe out previously imported data for some reason.

Typically you'll never need to clean out the uploaded images between each run. FG plugin won't re-fetch already saved images. This is due to `$options['force_media_import'] = 0`. This is what we want so that we don't keep fetching Live images with each run. Once an image is fetched and saved to `wp-content/uploads/` there is no reason to fetch again. So leave images alone between runs. If you realy want to force re-fetching of images then go ahead and run `git clean -fd wp-content/uploads/` to wipe them out.

List of clean up commands that are only needed for local testing:

```
# Clean out all wordpress content. Including content that existed prior to migration!
wp import-{drupal|joomla} empty all

# Clean out logs.
git checkout wp-content/debug.log
git clean -fd wp-content/uploads/fg*2wp*

# Clean out all options and migration counters.
# This includes any "by-hand" settings in wp-admin > tools > import > drupal|joomla - which are not needed anyway.
wp db query "delete from wp_options where option_name like 'fg_2wp%';"
```

## Development

In the FG plugins, do a search for add_filter, add_action, apply_filters, do_action and see if you find something you can use. Files are well organized.

#### Drupal Notes

- `nid` Node id can be used in url `/node/123` to redirect to node's web page.
- `delta` is used for that can be repeated, this is the ordering. For example:
```
Field Images:
    [0] mug.jpg
    [1] horizon.png
    [2] things.jpg
    ... etc
```
- `fid` File id. DB table `file_managed` is the "canonical" place files live in the DB.
- `entity_id` Often a node, but can also be a managed file or other things.
- Fields (applies to Drupal 8 and up) If you have the `config` folder (it's in the code download in backups), you can get a list of all fields on a node type by going to the `config` folder. Let's say you want to see all fields on a node type `book_review` then list files like `ls field.field.node.book_review.*`.  These will be imported into `postmeta`.
- Images: If you have the `config` folder, look at `field_image` for book_review: `cat field.field.node.book_review.field_image.yml`.

#### Helpful Drupal sql

```
# get all roles:
SELECT DISTINCT(roles_target_id) FROM user__roles;

# get all taxonomies:
SELECT DISTINCT(vid) FROM taxonomy_term_data;

# get content types (node types) that have content:
select distinct type from node order by type

# get all node types from config (with/without content): 
select name from config where name like 'node.type.%' order by name;
```
