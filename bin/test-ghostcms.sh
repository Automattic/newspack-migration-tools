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

install_wp_config() {

	# portable in-place argument for both GNU sed and Mac OSX sed
	if [[ $(uname -s) == 'Darwin' ]]; then
		local ioption='-i.bak'
	else
		local ioption='-i'
	fi

	# create config if not exists.
	if [ ! -e $WP_CORE_DIR/wp-config.php ]; then

		cp $WP_CORE_DIR/wp-config-sample.php $WP_CORE_DIR/wp-config.php

		sed $ioption "s/database_name_here/$DB_NAME/" "$WP_CORE_DIR"/wp-config.php
		sed $ioption "s/username_here/$DB_USER/" "$WP_CORE_DIR"/wp-config.php
		sed $ioption "s/password_here/$DB_PASS/" "$WP_CORE_DIR"/wp-config.php
		sed $ioption "s/localhost/$DB_HOST/" "$WP_CORE_DIR"/wp-config.php

	fi

}

recreate_db() {
	shopt -s nocasematch
	if [[ $1 =~ ^(y|yes)$ ]]
	then
		CMD_DROP_DB="mysqladmin drop $DB_NAME -f --user="$DB_USER" --password="$DB_PASS"$EXTRA"
		eval $CMD_DROP_DB
		create_db
		echo "Recreated the database ($DB_NAME)."
	else
		echo "Leaving the existing database ($DB_NAME) in place."
	fi
	shopt -u nocasematch
}

create_db() {
	CMD_CREATE_DB="mysqladmin create $DB_NAME --user=$DB_USER --password=$DB_PASS$EXTRA"
	eval $CMD_CREATE_DB
}

install_db() {

	if [ ${SKIP_DB_CREATE} = "true" ]; then
		return 0
	fi

	# parse DB_HOST for port or socket references
	input="value1:some value:another value:final value"
	IFS=':' read -ra PARTS <<< $DB_HOST
	local DB_HOSTNAME=${PARTS[0]};
	local DB_SOCK_OR_PORT=${PARTS[1]};
	local EXTRA=""

	if ! [ -z $DB_HOSTNAME ] ; then
		if [ $(echo $DB_SOCK_OR_PORT | grep -e '^[0-9]\{1,\}$') ]; then
			EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
		elif ! [ -z $DB_SOCK_OR_PORT ] ; then
			EXTRA=" --socket=\"$DB_SOCK_OR_PORT\""
		elif ! [ -z $DB_HOSTNAME ] ; then
			EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
		fi
	fi

	# create database
	CMD_DB_LIST="mysql --user=$DB_USER --password=$DB_PASS$EXTRA --execute='show databases;'"
	DB_LIST=$(eval $CMD_DB_LIST)
	if echo "$DB_LIST" | grep -q ^$DB_NAME$;
	# if [ $(mysql --user="$DB_USER" --password="$DB_PASS"$EXTRA --execute='show databases;' | grep ^$DB_NAME$) ]
	then
		echo "Reinstalling will delete the existing test database ($DB_NAME)"
		read -p 'Are you sure you want to proceed? [y/N]: ' DELETE_EXISTING_DB
		recreate_db $DELETE_EXISTING_DB
	else
		create_db
	fi
}

# install_wp_files
# install_wp_config
# install_db

# wp --path=$WP_CORE_DIR core download --version=$WP_VERSION
wp --path=$WP_CORE_DIR config create --dbname=$DB_NAME --dbuser=$DB_USER --dbpass=$DB_PASS --dbhost=$DB_HOST
wp --path=$WP_CORE_DIR config set DISABLE_WP_CRON true --raw
wp --path=$WP_CORE_DIR config set WP_DEBUG true --raw
wp --path=$WP_CORE_DIR config set WP_DEBUG_LOG true --raw
wp --path=$WP_CORE_DIR config set WP_DEBUG_DISPLAY false --raw

# # db setup
# mysql --user=root --password= -e "CREATE SCHEMA ${DBNAME} DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# # install
# wp --path=$WP_CORE_DIR core install --url=${SITEURL} --title=Test --admin_user=test --admin_password=test --admin_email=localwpinstall@ronchambers.com --skip-email
# wp --path=$WP_CORE_DIR --hard rewrite flush
