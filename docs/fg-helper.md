# FG Helper
This is a helper class that provides some useful functions for the migration process with the FG plugins from [the great Frédéric Giles](https://www.fredericgilles.net). Thank you for your work, Frédéric!

- [Premium Support / Knowledge Base](https://www.fredericgilles.net/support/)

Drupal:

- [FG Drupal to WordPress (free)](https://wordpress.org/plugins/fg-drupal-to-wp/) 
- [FG Drupal to WordPress Premium](https://www.fredericgilles.net/fg-drupal-to-wordpress/)
- NMT has been tested with FG Drupal to WP Premium 3.85.2.

## How to use

### Database:

Import the live database (drupal/joomla) backup into a mysql database (it can be the same database as wordpress if desired).

### Constants / WP Config:

```
define( 'NCCM_SOURCE_WEBSITE_URL', '[ live site url ]' );
define( 'NCCM_FG_MIGRATOR_PREFIX', '[ database prefix (can be blank) ]' );
```

You _have to_ define the url of the original site you are migrating away from: `NCCM_SOURCE_WEBSITE_URL`. The code will error if you don't.

You can customize the table prefix for the tables that contain the old data with this constant: `NCCM_FG_MIGRATOR_PREFIX`.

Set environment values.  For local Newspack Docker, set the values in .env file:
```
DB_HOST=$MYSQL_HOST
DB_USER=$MYSQL_USER
DB_PASSWORD=$MYSQL_PASSWORD
DB_NAME=[ your db name ]
```

### Commands

Only for testing:
```
wp import-drupal empty all
```

If you use wp-admin > tools > import > drupal settings, then you can run the command directly:
```
wp import-drupal import
```

Otherwise without the admin settings you'll need to set some filters and run via NCCM or NMT.

Assuming all config values are set in CLI, be sure to clear out any wp-admin > tools > import > Drupal settings:
```
select * from wp_options where option_name like 'fgd2wp%';
delete from wp_options where option_name like 'fgd2wp%';
```

To clear out the logs:
```
rm wp-content/uploads/fgd2wp*
```

All in one command to clear test - LOCAL/TESTING ONLY:
```
wp import-drupal empty all ; git checkout wp-content/debug.log ; git clean -fd wp-content/uploads/fgd2wp* ; wp db query "delete from wp_options where option_name like 'fgd2wp%';" ; git status ;
```

_don't clean out the uploaded images as FG plugin won't re-fetch already saved images (`'force_media_import' => 0`)_

Set options via:
```
add_filter( 'option_fgd2wp_options', ....
```

`wp newspack-migration-tools drupal-import [name]`

- name is unique identifier.


The wrapper simplifies running the importer from the CLI, so to run it do something like this:
```php
public function cmd_run_my_custom_import( array $pos_args, array $assoc_args ): void {
    add_action( 'fg_helper_pre_import', [ $this, 'add_fg_hooks' ] ); // If you want to add hooks before the import.
    $this->fg_helper->import( $pos_args, $assoc_args ); // This will run the importer.
}
```


### Logging

Keep track of FG Drupal plugin's output file `wp-content/uploads/fgd2wpp-progress.json` - this stores the current progress ~~which will be needed for Content Refresh~~.  This actually stores the total number of "items" and a running count of "items" imported: `{"total":475101,"current":1440}` used for progress bar display.

The "last article node id" is in the options table: `fgd2wp_last_node_article_id`. If this option value is deleted, then the plugin will no longer run.  The only way to get it to run again would be to add the option by hand and set it's value to the last article id that was imported (either MAX or MIN value of `_fgd2wp_old_node_id` from the postmeta table depending on if importing newest or oldest first).


## Development

In the FG plugins, do a search for add_filter, add_action, apply_filters, do_action and see if you find something you can use. Files are well organized.


### Drupal Notes:

- `nid` Node ID (url: `/node/123` ) (like post id in WP)
- Delta: For fields that can be repeated, this is the ordering. For example:
```
Field Images:
    [0] mug.jpg
    [1] horizon.png
    [2] things.jpg
    ... etc
```
- `fid` File id. See (table file_managed)
- `entity_id` Often a node, but can also be a managed file or other things.
- Fields (applies to Drupal 8 and up) If you have the `config` folder (it's in the code download in backups), you can get a list of all fields on a node type by going to the `config` folder. Let's say you want to see all fields on a node type `book_review` then list al files like `ls field.field.node.book_review.*`
- Images: If you have the `config` folder, look at `field_image` for book_review: `cat field.field.node.book_review.field_image.yml`.
- DB table `file_managed` - The "canonical" place files live in the DB
- sql get all roles: `SELECT DISTINCT(roles_target_id) FROM user__roles;`
- sql get all taxonomies`SELECT DISTINCT(vid) FROM taxonomy_term_data;`
- sql get content types (node types) `SELECT DISTINCT(type) FROM node;`
