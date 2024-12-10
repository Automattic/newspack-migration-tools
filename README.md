# Newspack Migration Tools

This package is a set of migration tools used to make it easy to migrate content to WordPress.

The repository contains a set of WP commands to migrate different data to WordPress, and helper classes that can be used to develop your own migrators. You can use the code from WP_CLI or from just browser WordPress. Think of this as a library that you can build from.

## Requirements
* WordPress
* Minimum PHP version required is 8.1.
* If you use the JsonIterator class, you must have `jq` installed on your system. See [download instructions](https://jqlang.github.io/jq/download/).

## Documentation for logic and utility classes
* [Logging](./docs/logging.md)
* [UsersHelper](./docs/users-helper.md)
* [UserMeta](./docs/user-meta.md)
* [FG Helper](./docs/fg-helper.md)

## Documentation for Individual Migrators
* Attachments (todo)
* [Newspaper Theme](./docs/newspaper-theme.md)

## Development

You can load this package in your PHP project as follows:

_composer.json_

```json
"repositories": [
    {
        "type": "git",
        "url": "https://github.com/Automattic/newspack-migration-tools.git"
    }
],
"require": {
    "automattic/newspack-migration-tools": "dev-trunk"
}
```

You can either include the `newspack-migration-tools.php` file in your code, use the classes directly, or call `NMT:setup()`.

_my-plugin-file.php_
```php
// Loading the attachments helper class.
use Newspack\MigrationTools\Logic\Attachments;
// Example call.
$attachment_id = Attachments::import_attachment_for_post( ... your arguments here ... );
```

## Registering the WP CLI commands in this package
If you want to use the WP CLI commands in this package, you can do so by adding the following code to your plugin:
```php
use Newspack\MigrationTools\Command\WpCliCommands;
use Newspack\MigrationTools\Command\WpCliCommandInterface;

// Add your command class names in the array.
$cli_commands = [ MyProject\MyCommand::class ];

// Or add all classes:
// $cli_commands = WpCliCommands::get_classes_with_cli_commands();

foreach ( $cli_commands as $command_class ) {
    if ( is_a( $command_class, WpCliCommandInterface::class, true ) ) {
            array_map( function ( $command ) {
            WP_CLI::add_command( ...$command );
        }, $command_class::get_cli_commands() );
    }
}
```

## Tests
To get started with tests, run `./bin/install-wp-tests.sh`. If you are using Local.app, then the args could look something like this: `./bin/install-wp-tests.sh local root root "localhost:/Users/<your-username>/Library/Application Support/Local/run/<some-id>/mysql/mysqld.sock"` You can find the part to put after "socket:" on the Database tab in the local app for the site.

To run the tests, run `composer run phpunit`.

### Code coverage
To get a code coverage report, run `composer run code-coverage`. The report will be generated in the `coverage` directory. Open the [coverage/index.html](coverage/index.html) file in that dir in your browser to see the report.
