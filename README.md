# Newspack Migration Tools

This package is a set of migration tools used to make it easy to migrate content to WordPress.

The repository contains a set of WP commands to migrate different data to WordPress, and helper classes that can be used to develop your own migrators.

Minimum PHP version required is 8.1.

### Documentation for Individual Migrators

* Attachments (todo)
* [GhostCMS](docs/GhostCMS.md)


## Development

You can load this package in your PHP project as follows:

_composer.json_

```
"repositories": [
    {
        "type": "git",
        "url": "https://github.com/Automattic/newspack-migration-tools.git"
    }
],
```

_my-plugin-file.php_

```
// Loading the attachments helper class.
use Newspack\MigrationTools\Logic\AttachmentHelper;
// Example call.
$attachment_id = AttachmentHelper::import_attachment_for_post( ... your arguments here ... );
```

## Registering the WP CLI commands in this package
If you want to use the WP CLI commands in this package, you can do so by adding the following code to your plugin:
```php

// All commands in the package can be gotten with:
$cli_commands = WpCliCommands::get_classes_with_cli_commands();
// If you only need a specific command or two, you can also just add them like this:
$cli_commands = [ Newspack\MigrationTools\Commands\MyCommand::class ];

foreach ( $cli_commands as $command_class ) {
    $class = $command_class::get_instance();
    if ( is_a( $class, WpCliCommandInterface::class ) ) {
        array_map( function ( $command ) {
            WP_CLI::add_command( ...$command );
        }, $class->get_cli_commands() );
    }
}
```

## Tests
To get started with tests, run `./bin/install-wp-tests.sh`. If you are using Local.app, then the args could look something like this: `./bin/install-wp-tests.sh local root root "localhost:/Users/<your-username>/Library/Application Support/Local/run/<some-id>/mysql/mysqld.sock"` You can find the part to put after "socket:" on the Database tab in the local app for the site.

To run the tests, run `composer run phpunit`.

### Code coverage
To get a code coverage report, run `composer run code-coverage`. The report will be generated in the `coverage` directory. Open the [coverage/index.html](coverage/index.html) file in that dir in your browser to see the report.

### Integration Tests
To run integration tests, follow these steps:

#### GhostCMS
```
# Run test:

sh bin/test-ghostcms.sh <temp-db-name> <temp-db-user> <temp-db-pass> [temp-db-host(localhost)] [wp-version(latest)]

# Remove temp folder:

rm -rf /tmp/wp-test-ghostcms

# Remove temp db:

DROP DATABASE <temp-db-name>;

```
