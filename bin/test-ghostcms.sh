#!/usr/bin/env bash

# Usage.
if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]"
	exit 1
fi

# Console logging.
set -ex

# Arguments and defaults.
DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}

# Get temp dir without trailing slash.
TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e "s/\/$//")

# Create wp folder for this ghostcms test.
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wp-test-ghostcms}
if [ ! -d $WP_CORE_DIR ]; then
	mkdir -p $WP_CORE_DIR
fi

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

# Install plugin:
wp --path=$WP_CORE_DIR plugin install --activate co-authors-plus