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

**Example:**

```php
$collections_helper = new CollectionsHelper();

$collection_post_id = $collections_helper
    ->get_or_create_collection(
        [
            'post_title'   => 'January 1970',
            'post_content' => 'Lorem Ipsum Dolor Sit Amet',
        ],
        'a1s2d3f4g5h6'
    );
```

---

`update_collection_metadata( int $collection_id, array $data ): void`

Updates the post meta related to Collections.

**Parameters:**

* `$collection_id` *(int)* — The Collection Post ID.
* `$data` *(array)* — An array of metadata to update for the Collection. The `CollectionsHelper::COLLECTION_META_MAP` constant contains a reference of all available meta keys.

**Return:**

Void.

**Example:**

```php
$collections_helper = new CollectionsHelper();

$collection_id = 123;

$collection_post_id = $collections_helper
    ->update_collection_metadata(
        $collection_id,
        [
            'thumbnail'      => 1234,                            // Attachment ID
            'volume'         => 1,                               // Collection Volume
            'number'         => 1,                               // Collection Number
            'period'         => 'January 1970',                  // Collection Period
            'subscribe_link' => 'https://example.com/subscribe', // Subscribe URL
            'order_link'     => 'https://example.com/order',     // Order URL
            'ctas'           => [
                [
                    'type'  => 'attachment',
                    'label' => 'View Digital Edition (PDF)',
                    'id'    => 12345,
                ]
            ]
        ],
    );
```

---

`get_or_create_collection_section( array $data, string $unique_identifier ): WP_Term|WP_Error`

Searches for a Collection Section WP Term by its `$unique_identifier`. Otherwise, tries to create a new one with the passed `$data`.

**Parameters:**

* `$data` *(array)* — The data for the Collection Section WP Term. Parameters are the same as for `wp_insert_term`.
* `$unique_identifier` *(string)* — The unique identifier for the Collection Section. This is stored as `_nmt_collection_section_uniqid` term meta.

**Return:**

Returns the `WP_Term` when the Collection Section is created or fetched, or `WP_Error` on error.

**Example:**

```php
$collections_helper = new CollectionsHelper();

$collection_post_id = $collections_helper
    ->get_or_create_collection_section(
        [
            'name' => 'Collection Section #1',
            'slug' => 'collection-section-1',
        ],
        'a1s2d3f4g5h6'
    );
```

---

`update_collection_section_metadata( int $collection_section_id, array $data ): void`

Updates the term meta related to Collection Sections.

**Parameters:**

* `$collection_section_id` *(int)* — The Collection Section WP_Term ID.
* `$data` *(array)* — An array of metadata to update for the Collection Section. The `CollectionsHelper::COLLECTION_SECTION_META_MAP` constant contains a reference of all available meta keys.

**Return:**

Void.

**Example:**

```php
$collections_helper = new CollectionsHelper();

$collection_section_id = 123;

$collection_post_id = $collections_helper
    ->update_collection_section_metadata(
        $collection_section_id,
        [
            'order' => 10, // Collection Section Order
        ],
    );
```

---

`get_or_create_collection_category( array $data, string $unique_identifier ): WP_Term|WP_Error`

Searches for a Collection Category WP Term by its `$unique_identifier`. Otherwise, tries to create a new one with the passed `$data`.

**Parameters:**

* `$data` *(array)* — The data for the Collection Category WP Term. Parameters are the same as for `wp_insert_term`.
* `$unique_identifier` *(string)* — The unique identifier for the Collection Category. This is stored as `_nmt_collection_category_uniqid` term meta.

**Return:**

Returns the `WP_Term` when the Collection Category is created or fetched, or `WP_Error` on error.

**Example:**

```php
$collections_helper = new CollectionsHelper();

$collection_post_id = $collections_helper
    ->get_or_create_collection_category(
        [
            'name' => 'Collection Category #1',
            'slug' => 'collection-category-1',
        ],
        'a1s2d3f4g5h6'
    );
```

---

`update_collection_category_metadata( int $collection_category_id, array $data ): void`

Updates the term meta related to Collection Category.

**Parameters:**

* `$collection_category_id` *(int)* — The Collection Category WP_Term ID.
* `$data` *(array)* — An array of metadata to update for the Collection Category. The `CollectionsHelper::COLLECTION_CATEGORY_META_MAP` constant contains a reference of all available meta keys.

**Return:**

Void.

**Example:**

```php
$collections_helper = new CollectionsHelper();

$collection_category_id = 123;

$collection_post_id = $collections_helper
    ->update_collection_category_metadata(
        $collection_category_id,
        [
            'subscribe_link' => 'https://example.com/subscribe', // Collection Category Subscribe Link
            'order_link'     => 'https://example.com/order', // Collection Category Order Link
        ],
    );
```