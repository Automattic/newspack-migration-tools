<?php

namespace Newspack\MigrationTools\Command;

use Closure;
use ErrorException;

/**
 * Utility trait for ensuring singleton instances of WP CLI commands.
 *
 * If your command class uses static functions for commands – don't use this trait as it will cause a fatal error
 * in WP_CLI because WP_CLI will try to call the private __construct() method before calling your static command.
 */
trait WpCliCommandTrait {

	/**
	 * Constructor.
	 *
	 * I don't do anything at all and that is on purpose.
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
	 * @param string $command_function_name The function name of the command to run when the command is invoked.
	 *
	 * @return Closure The closure wrapping the command function
	 * @throws ErrorException If the command function is static, does not exist, or takes the wrong number of params.
	 */
	protected static function get_command_closure( string $command_function_name ): Closure {

		return function ( array $pos_args, array $assoc_args ) use ( $command_function_name ) {
			$class = get_class( self::get_instance() );

			// If is_callable returns true on the classname string as the first argument, it's a static method.
			// Warn that using the closure is overkill for static and just using the method directly is better.
			if ( is_callable( [ $class, $command_function_name ] ) ) {
				throw new ErrorException(
					sprintf(
						"The command function '%s' in %s is static.\n Instead of using get_command_closure(), just use [__CLASS__, 'command_function_name'] directly.",
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
						$command_function_name,
						// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
						$class
					)
				);
			}

			if ( ! method_exists( self::get_instance(), $command_function_name ) ) {
				throw new ErrorException(
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					sprintf( 'Command "%s" does not exist in %s', $command_function_name, $class )
				);
			}

			return self::get_instance()->{$command_function_name}( $pos_args, $assoc_args );
		};
	}
}
