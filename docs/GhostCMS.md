# GhostCMS Migrator

The following CLI migrator can be used to import a Ghost JSON export file into new posts, featured images, categories, and authors.

### Required Plugin

[CoAuthorsPlus](https://wordpress.org/plugins/co-authors-plus/) (free plugin) must be installed and activated.

## Usage:

Command: `wp newspack-migration-tools ghostcms-import`

### Required arguments:

* `--default-user-id=` Default user id for `post_author`.  Ex: 1
* `--ghost-url=` This is the current LIVE site url. Ex: https://www.my-site.com/
* `--json-file=` Path to Ghost JSON export file.

### Optional arguments:

* `--created-after=` Cut off date to only import newer posts.  Ex: "2024-01-01 10:50:30"

### Output log files: 

* `GhostCMSMigrator_cmd_ghostcms_import.log` - Be sure to review for warning and error lines.
* `GhostCMSMigrator_cmd_ghostcms_import.log-skips.log` - Be sure to review for any posts that should have been added but were skipped.
