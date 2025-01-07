# FG Helper
This is a helper class that provides some useful functions for the migration process with the FG pluings from [the great Frédéric Giles](https://www.fredericgilles.net). Thank you for your work, Frédéric!

## How to use

### Commands
The wrapper simplifies running the importer from the CLI, so to run it do something like this:
```php
public function cmd_run_my_custom_import( array $pos_args, array $assoc_args ): void {
    add_action( 'fg_helper_pre_import', [ $this, 'add_fg_hooks' ] ); // If you want to add hooks before the import.
    $this->fg_helper->import( $pos_args, $assoc_args ); // This will run the importer.
}
```

### Constants
You _have to_ define the url of the original site you are migrating away from: `NCCM_SOURCE_WEBSITE_URL`. The code will error if you don't.

You can customize the table prefix for the tables that contain the old data with this constant: `NCCM_FG_MIGRATOR_PREFIX`.

If you want to filter the db creds, you can use these environment variables instead of filtering:
```
DB_HOST
DB_NAME
DB_USER
DB_PASSWORD
```
