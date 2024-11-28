# Users Helper
This class exists so that we can get and create users in a consistent way. It takes care to enusre that `user_login`, `user_nicename`, and `user_email` are unique and valid. The idea is that _all_ migration code that creates users should use this class for consistency.

## Unique identifier for users
The class will save a meta value on created users and try to use that to get users. A unique identifier that you pass when creating the user will be your "handle" on the user for the rest of the migration.

The unique identifier is stored in user meta with the key that can be found in the `UsersHelper::UNIQUE_IDENTIFIER_META_KEY` constant. Note that it is your job to ensure that the unique identifier is unique in the data you are migrating – the code will help you check that. It can be any string you choose – as long as its unique for the user. See the `create_or_get_user()` or `get_user_by_unique_identifier()` methods for more information.

## Getting users
There are a couple of different methods that can get a user. 
If you don't have a unique identifier for your user from the original migration data (you should, but let's say you don't), then use `get_user()`. 

If you have a unique identifier, use `get_user_by_unique_identifier()`. You should always use this method if you have a unique identifier.

## Creating users
Calling `create_or_get_user( array $user_data, string $unique_identifier )` will create a user with the given data. If the user already exists, it will return that. 

You *must* also pass a unique identifier, which is used to check if the user already exists. This is so that if you for example migrate from data where users can have duplicated user names (just stay with me here and let's pretend that could happen), you can still ensure that if you pass an ID or whatever is unique for the user in the migration data, you can still get the correct user.

When the user is created the meta value for the unique identifier is saved for the user.

You can pass all the same things in the array as you can pass to [wp_insert_user()](https://developer.wordpress.org/reference/functions/wp_insert_user) to the function, so to create a user with some metadata for instance:
    
```php
$user_data = [
    'user_login' => 'my_user_login',
    'role'       => 'editor',
    'meta_input' => [
        'favorite_number' => 7,
    ],
];
// The uniqid just has to be something you know is unique. Here we use the user_login.
$new_user = UsersHelper::create_or_get_user( $user_data, 'my_user_login' );
```

### "Fake data"
The user creation method can create a user from very little data. You *have* to pass at least one of these things in the array though: `user_login`, `user_email`, `user_nicename`, or `display_name`. If you don't, the method will throw an exception. 

Obviously – the more data you pass the better, but the method will create a user with just one of those fields. 

Fake emails will be created that look something like f3e895c937@example.com. 

## Logging
This class logs to file in `UsersHelper.log`.

* When a user is created, it logs the created user's data (including the unique identifier).
* When an email, username, or nicename is shortened, it logs the old and new value.

There is also a bit of CLI logging of more low-level stuff.

## Actions and filters
* `nmt_user_email_default_domain`: If you for some reason want to use another domain than example.com for the fake emails, you can use this filter to change it.
* `nmt_user_user_pre_insert`: Called right before the user is inserted. It is different from WP's own filters because it also passes the unique identifier.
