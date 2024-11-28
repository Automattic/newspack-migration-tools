# User Meta

The `UserMeta` class has functionality related to user meta. 

## Get user ID from metadata key and value
There is no such function in WP's API, so `get_user_id_from_key_and_value()` in the class does that. Note that it if the value is not unique, it will return the first user it finds.