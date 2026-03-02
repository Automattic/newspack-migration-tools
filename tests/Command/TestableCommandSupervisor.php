<?php

namespace Newspack\MigrationTools\Tests\Command;

use Newspack\MigrationTools\Command\CommandSupervisor;

/**
 * Test double for CommandSupervisor that returns pre-configured process results.
 *
 * Overrides execute_command() to avoid real subprocess execution, allowing
 * the supervision loop in cmd_supervise() to be tested in isolation.
 */
class TestableCommandSupervisor extends CommandSupervisor {

	/**
	 * Sequence of process results to return from execute_command().
	 *
	 * @var object[]
	 */
	public array $process_results = [];

	/**
	 * Number of times execute_command() has been called.
	 *
	 * @var int
	 */
	public int $execute_count = 0;

	/**
	 * Constructor — replaces the private constructor from WpCliCommandTrait.
	 */
	public function __construct() {
		// Intentionally empty.
	}

	/**
	 * Returns the next pre-configured process result instead of running a real command.
	 *
	 * Falls back to a successful result (exit code 0) if the sequence is exhausted.
	 *
	 * @param string $command Ignored.
	 *
	 * @return object Process result with stdout, stderr, and return_code.
	 */
	protected function execute_command( string $command ): object {
		$result = $this->process_results[ $this->execute_count ]
			?? (object) [
				'stdout'      => '',
				'stderr'      => '',
				'return_code' => 0,
			];
		++$this->execute_count;

		return $result;
	}
}
