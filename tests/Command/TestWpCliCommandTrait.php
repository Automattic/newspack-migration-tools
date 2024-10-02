<?php

namespace Newspack\MigrationTools\Tests\Command;

use Closure;
use ErrorException;
use Newspack\MigrationTools\Command\WpCliCommandTrait;
use WP_UnitTestCase;

/**
 * Test the WpCliCommandTrait.
 *
 * @package newspack-migration-tools
 */
class TestWpCliCommandTrait extends WP_UnitTestCase {

	/**
	 * Test that the trait can return a closure that we can execute it.
	 */
	public function test_closure() {
		$this->expectOutputString( 'I do stuff!' );
		$closure = ClassThatUsesWpCliCommandTrait::get_non_static_closure();
		$closure( [], [] );
	}

	/**
	 * Test that if a dev adds a static method in the closure, an error is thrown.
	 */
	public function test_static_warn() {
		$this->expectException( \ErrorException::class );
		$closure = ClassThatUsesWpCliCommandTrait::get_static_closure();
		$closure( [], [] );
	}
}

/**
 * Class that uses the WpCliCommandTrait.
 */
// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
class ClassThatUsesWpCliCommandTrait {
	use WpCliCommandTrait;

	/**
	 * Get a closure for a static method.
	 *
	 * @return Closure The closure with the static method.
	 * @throws ErrorException If the closure is wrong (and it should be).
	 */
	public static function get_static_closure(): Closure {
		return self::get_command_closure( 'static_method' );
	}

	/**
	 * Static method that does nothing.
	 */
	public static function static_method() {
		// I'm just hanging out here doing nothing but being static.
	}

	/**
	 * Get a closure with a method that works.
	 *
	 * @return Closure The closure with the method.
	 * @throws ErrorException If the closure is wrong (and it should not be).
	 */
	public static function get_non_static_closure(): Closure {
		return self::get_command_closure( 'non_static_method' );
	}

	/**
	 * Output "I do stuff!".
	 *
	 * @param array $pos_args   Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function non_static_method( array $pos_args, array $assoc_args ): void {
		echo 'I do stuff!';
	}
}
