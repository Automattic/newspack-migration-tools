# Newspack Migration Tools

This package is a set of migration tools used to make it easy to migrate content to WordPress.

The repository contains a set of WP commands to migrate different data to WordPress, and a set of "Logic" classes that can be used to develop your own migrators.

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
// Loading the attachments logic class.
use Newspack\MigrationTools\Logic\Attachments as AttachmentsLogic;
new Attachments_Logic();
```

To use the WP commands that from this repository, you can load it as a plugin.
