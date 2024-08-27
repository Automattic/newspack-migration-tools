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

# Setup a temp folder.
TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e "s/\/$//")
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wp-test-ghostcms}

install_wp() {

	# Check if already installed.
	if [ -d $WP_CORE_DIR ]; then
		return;
	fi

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

	# Install plugin:
	wp --path=$WP_CORE_DIR plugin install --activate co-authors-plus

}

install_wp

# Run test:
wp --path=$WP_CORE_DIR eval-file bin/test-ghostcms.php
