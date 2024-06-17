<?php

namespace Newspack\MigrationTools\Util;

class Logger {

	const WARNING = 'warning';
	const LINE    = 'line';
	const SUCCESS = 'success';
	const ERROR   = 'error';
	const INFO    = 'info';

	/**
	 * Logging wrapper. Fires the `newspack_migration_tools_log` action.
	 *
	 * @param string $filename File name or path.
	 * @param string $message Log message.
	 * @param string $level Log level. See constants in this class.
	 * @param bool   $exit_on_error Whether to exit on error.
	 */
	public static function log( string $filename, string $message, string $level = self::LINE, bool $exit_on_error = false ) {
		do_action( 'newspack_migration_tools_log', $filename, $message, $level, $exit_on_error );
	}
}
