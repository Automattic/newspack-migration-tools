<?php

namespace Newspack\MigrationTools\Command;

use Closure;
use Newspack\MigrationTools\Log\CliLogger;

/**
 * Utility trait for ensuring singleton instances of WP CLI commands.
 */
trait WpCliCommandTrait {

	/**
	 * Constructor.
	 *
	 * I don't do anything at all and that is on purpose. You probably don't want to override this.
	 */
	private function __construct() {
		// Nothing.
	}

	/**
	 * Singleton instance getter.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Get a closure wrapping the command function.
	 *
	 * We do this to make sure we instantiate the command class as late as possible.
	 *
	 * @param string $command_name The function name of the command to run when the command is invoked.
	 *
	 * @return Closure
	 */
	protected static function get_command_closure( string $command_name ): Closure {
		if ( ! method_exists( self::get_instance(), $command_name ) ) {
			CliLogger::error(
				sprintf( 'Command "%s" does not exist in %s', $command_name, get_class( self::get_instance() ) ),
				true
			);
		}

		return function ( array $pos_args, array $assoc_args ) use ( $command_name ) {
			return self::get_instance()->{$command_name}( $pos_args, $assoc_args );
		};
	}
}
