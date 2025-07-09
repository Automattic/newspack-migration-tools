# Collections

## Overview

Collections are an optional module of Newspack Plugin since v6.8.0. The `Newspack\MigrationTools\Logic\CollectionsHelper` class is created to help when migrating content to Collections.

More about the implementation and structure of Collections can be found [here](https://github.com/Automattic/newspack-plugin/blob/trunk/includes/collections/README.md).

## Prerequisites

* Newspack Plugin `>= 6.8.0` must be installed and activated to use this helper.
* The Collections module must be enabled in Newspack Settings.

## CollectionsHelper

### Constructor

```php
$collections_helper = new CollectionsHelper();
```

Instantiates a new CollectionsHelper.

The constructor checks if Newspack Plugin is activated and Collections are enabled. An `Exception` is thrown when either of those checks is falsy.

### Methods

`get_or_create_collection( array $data, string $unique_identifier ): int|WP_Error`

Searches for a Collection Post by its `$unique_identifier`. Otherwise, tries to create a new one with the passed `$data`.

**Parameters:**

* `$data` *(array)* — The data for the Collection Post. Parameters are the same as for `wp_insert_post`.
* `$unique_identifier` *(string)* — The unique identifier for the Collection Post. This is stored as `_nmt_collection_uniqid` post meta.

**Return:**

Returns the `WP_Post` `ID` when the Collection Post is created or fetched, or `WP_Error` on error.