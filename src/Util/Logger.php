<?php

namespace Newspack\MigrationTools\Util;

class Logger {
	public static function log(  ) {
		do_action('newspack_migration_tools_log', func_get_args() ); // TODO. Implement handling of args better.
	}
}