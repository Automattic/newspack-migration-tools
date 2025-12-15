# GhostCMS Migrator

This migrator will import a [Ghost (CMS)](https://ghost.org/) JSON export file into new posts, featured images, authors, and categories.

### Posts and Content

Public, published posts that contain body content and a title will be migrated. Excerpts are imported too. Already imported posts will be skipped, along with posts that have a matching title on the same date or a matching slug. An optional migration argument allows migrating only posts after a given date. 

### Images

Featured images are fetched from the current Ghost website. Alt and captions are added too.

### Authors

Post authors are imported. Authors must have a visibility of public. If the imported author's user login matches an existing WordPress user with role ('Administrator', 'Editor', 'Author', or 'Contributor') then the WP User will be used, otherwise a Co-Authors Plus Guest Author will be created.

### Categories and Tags

Ghost tags will be imported as WordPress categories.

## How to Migrate

### Step 1: Export JSON from Ghost

A JSON file backup/export of the current Ghost website is needed. 

Options:
- [Export from a self-hosted site using the Admin](https://ghost.org/docs/faq/manual-backup/#export-content). Choose "Export your content".
- [Export from a self-hosted site using the Ghost CLI](https://ghost.org/docs/ghost-cli/#ghost-backup). Run `ghost backup`.
- [Export from a Ghost Pro site](https://ghost.org/help/exports/). See "content" export.

Note: the JSON export file could be very large. In most cases, the GhostCMS Migrator should be able to injest the file as-is. But if smaller chunks are needed, the Linux "jq" command or Ghost's gctools [json-split](https://github.com/TryGhost/gctools?tab=readme-ov-file#json-split) command line utilities could be used to create smaller files.

### Step 2: Verify requirements

To run the migrator, you'll need:

- The free plugin [Co-Authors Plus](https://wordpress.org/plugins/co-authors-plus/) must also be installed and activated.

### Step 3: Review help and arguments

Before running the migrator, please review the help output to understand the required and optional arguments.

Help command: `wp help newspack-migration-tools ghostcms-import` 

Required arguments:
```
--default-user-id=<default-user-id>
  User ID for default "post_author" for wp_insert_post(). Integer.

--ghost-url=<ghost-url>
  Public URL of current/live Ghost Website. Scheme with domain: https://www.mywebsite.com

--json-file=<json-file>
  Path to Ghost JSON export file.
```

Optional arguments:
```
--created-after=<created-after>
Datetime cut-off to only import posts AFTER this date. (Must be parseable by strtotime).
```

### Step 4: Run a test

For testing, you can use these test values (with the included `json` test file):

```
--default-user-id=1
--ghost-url=https://newspack.com
--json-file=wp-content/plugins/newspack-custom-content-migrator/vendor/automattic/newspack-migration-tools/tests/fixtures/ghostcms.json
```

### Step 5: Run a real migration

Command (_be sure to replace your values_):
```
wp newspack-migration-tools ghostcms-import --default-user-id=<default-user-id> --ghost-url=<ghost-url> --json-file=<json-file> [--created-after=<created-after>]
```

If the migrator command is stopped mid-migration, it is OK to simply re-run the command.
- Previously imported content will be skipped.
- Log files will be appended to automatically.

If the command will not run, please view the `wp-content/debug.log` file and/or the output logs listed below. Also see _Errors_ below.

### Step 6: Review output logs 

The following output logs will be created:

* `GhostCMSMigrator_cmd_ghostcms_import.log` - This log file will list all content that was imported along with any warning or errors encountered.
* `GhostCMSMigrator_cmd_ghostcms_import.log-skips.log` - If a post was already imported, it will not be imported again. A list of "skipped" posts will be written to this file.

### Step 7: Download files from external host

The content has now been imported, but the images are still hosted on the external host. You will need to download the images to your local WordPress installation.

Recommended to use the [Newspack Post Image Downloader](https://github.com/Automattic/newspack-post-image-downloader) plugin to download the images. Follow the [README.md](https://github.com/Automattic/newspack-post-image-downloader) file in plugin repo for usage and best workflow.

## Common Errors and Fixes

Error:

`CoAuthorsPlusHelper construct threw exception: CoAuthors Plus is not installed or active.`

1. Check that the Co-Authors Plus plugin is properly activated.
2. If the Newspack Plugin is also active, then the needed Guest Authors feature within Co-Authors Plus may have been removed by the Newspack Plugin.   

Please add a config value to the `wp-config.com` file:

- By hand: `define( 'NEWSPACK_ENABLE_CAP_GUEST_AUTHORS', true );`
- Or by wp-cli: `wp config set NEWSPACK_ENABLE_CAP_GUEST_AUTHORS true --raw --type=constant`
