#!/usr/bin/env bash

WP_VERSION="latest"
DB_NAME="wptemprealdb"
DB_USER="root"
DB_PASS=""
DB_HOST="localhost"

WP_CORE_DIR="bin/temp-real-wp/wp"
mkdir -p $WP_CORE_DIR

# Install files and config.
wp --path=$WP_CORE_DIR core download --version=$WP_VERSION
wp --path=$WP_CORE_DIR config create --dbname=$DB_NAME --dbuser=$DB_USER --dbpass=$DB_PASS --dbhost=$DB_HOST
wp --path=$WP_CORE_DIR config set DISABLE_WP_CRON true --raw
wp --path=$WP_CORE_DIR config set WP_DEBUG true --raw
wp --path=$WP_CORE_DIR config set WP_DEBUG_LOG true --raw
wp --path=$WP_CORE_DIR config set WP_DEBUG_DISPLAY false --raw

# Install db and site.
wp --path=$WP_CORE_DIR db create
wp --path=$WP_CORE_DIR core install --url=localhost --title=test --admin_user=test --admin_email=no-reply-no-user-nope@newspack.com --skip-email

# Install plugin (but do not activate).
wp --path=$WP_CORE_DIR plugin install co-authors-plus
