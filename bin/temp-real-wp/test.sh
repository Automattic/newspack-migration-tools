#!/usr/bin/env bash

WP_CORE_DIR="bin/temp-real-wp/wp"

# Reset db.
wp --path=$WP_CORE_DIR db reset --yes
wp --path=$WP_CORE_DIR core install --url=localhost --title=test --admin_user=test --admin_email=no-reply-no-user-nope@newspack.com --skip-email

# Run test:
wp --path=$WP_CORE_DIR eval-file bin/temp-real-wp/eval.php
