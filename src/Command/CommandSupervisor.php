<?php

namespace Newspack\MigrationTools\Command;

use Newspack\MigrationTools\Util\Log\MultiLog;
use Psr\Log\LoggerInterface;
use WP_CLI;
use WP_CLI\ExitException;

/**
 * Class CommandSupervisor.
 *
 * Supervises the execution of a shell command with automatic restart on failure.
 * Designed for long-running migration scripts that may crash, timeout, or get OOM-killed.
 */
class CommandSupervisor implements WpCliCommandInterface {

	use WpCliCommandTrait;

	/**
	 * Logger instance that writes to both CLI and file.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Get CLI commands for the class.
	 *
	 * The array should contain an array of arrays. Each entry is a command.
	 * Each command should have 2 or 3 elements that are the same as you would
	 * pass to WP_CLI::add_command().
	 * See https://make.wordpress.org/cli/handbook/references/internal-api/wp-cli-add-command/
	 *
	 * @return array
	 */
	public static function get_cli_commands(): array {
		return [
			[
				'newspack-content-migrator command-supervisor',
				self::get_command_closure( 'cmd_supervise' ),
				[
					'shortdesc' => 'Supervises and manages command execution with automatic restart.',
					'longdesc'  => 'Runs a given shell command and automatically restarts it on failure. Useful for long-running migration scripts that may crash, timeout, or get OOM-killed. The supervisor will keep retrying until the command exits successfully (exit code 0) or the maximum number of retries is reached.',
					'synopsis'  => [
						[
							'type'        => 'assoc',
							'name'        => 'command',
							'description' => 'The WP-CLI command to supervise, without the "wp" prefix (e.g. "newspack-content-migrator some-command --batch-size=100").',
							'optional'    => false,
						],
						[
							'type'        => 'assoc',
							'name'        => 'max-fail-retries',
							'description' => 'Maximum number of failed attempts before giving up. Successful runs do not count toward this limit.',
							'optional'    => true,
							'default'     => 3,
						],
						[
							'type'        => 'assoc',
							'name'        => 'max-consecutive-fail-retries',
							'description' => 'Maximum number of consecutive failed attempts before giving up. Successful runs do not count toward this limit.',
							'optional'    => true,
							'default'     => 2,
						],
						[
							'type'        => 'assoc',
							'name'        => 'retry-delay',
							'description' => 'Seconds to wait between retries.',
							'optional'    => true,
							'default'     => 5,
						],
						[
							'type'        => 'flag',
							'name'        => 'restart-on-success',
							'description' => 'Also restart the command after a successful exit (exit code 0). Useful for batch-processing commands that need to run multiple passes. Requires --completion-criteria or --max-success-retries.',
							'optional'    => true,
						],
						[
							'type'        => 'assoc',
							'name'        => 'completion-criteria',
							'description' => 'A string to look for in the command output that indicates processing is complete and the supervisor should stop restarting. When set, --max-success-retries is ignored.',
							'optional'    => true,
						],
						[
							'type'        => 'assoc',
							'name'        => 'max-success-retries',
							'description' => 'Maximum number of successful runs before stopping. Only used with --restart-on-success when --completion-criteria is not set.',
							'optional'    => true,
							'default'     => 10,
						],
						[
							'type'        => 'assoc',
							'name'        => 'active-plugins',
							'description' => 'Comma-separated list of additional plugin slugs to keep active. newspack-custom-content-migrator is always kept active. All other plugins are skipped via --skip-plugins for memory optimization.',
							'optional'    => true,
						],
						[
							'type'        => 'assoc',
							'name'        => 'notify-email',
							'description' => 'Email address to notify when supervision ends (either final success or max retries exhausted).',
							'optional'    => true,
						],
					],
				],
			],
		];
	}

	/**
	 * Supervises a command, restarting it on failure until it succeeds or max retries is hit.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @throws ExitException Thrown if the command fails beyond or equal to the maximum number of retries.
	 */
	public function cmd_supervise( array $args, array $assoc_args ): void {
		$command              = $assoc_args['command'];
		$max_fail_retries     = abs( intval( $assoc_args['max-fail-retries'] ?? 3 ) );
		$max_consecutive_fail_retries = abs( intval( $assoc_args['max-consecutive-fail-retries'] ?? 2 ) );
		$max_success_retries  = abs( intval( $assoc_args['max-success-retries'] ?? 10 ) );
		$retry_delay          = abs( intval( $assoc_args['retry-delay'] ?? 5 ) );
		$restart_on_success   = isset( $assoc_args['restart-on-success'] );
		$completion_criteria  = $assoc_args['completion-criteria'] ?? null;
		$active_plugins_arg   = $assoc_args['active-plugins'] ?? '';
		$notify_email         = $assoc_args['notify-email'] ?? null;

		$this->logger = MultiLog::get_cli_and_file_logger( 'CommandSupervisor' );

		// Strip the "wp" prefix if the caller included it.
		$command = preg_replace( '/^\S*wp\s+/', '', $command );
		$command = $this->inject_wp_cli_global_flags( $command, $active_plugins_arg );

		$this->logger->info( "Starting supervision of: {$command}" );
		$this->logger->info(
			sprintf(
				'Config: max-fail-retries=%d, retry-delay=%ds, restart-on-success=%s',
				$max_fail_retries,
				$retry_delay,
				$restart_on_success ? 'yes' : 'no'
			)
		);

		$attempt         = 0;
		$total_fail_count = 0;
		$consecutive_fail_count = 0;
		$total_success_count = 0;
		$final_exit_code = null;
		$operation_status_stack = [ null, null ];

		while ( true ) {
			++$attempt;

			$this->logger->info( "========== Attempt #{$attempt} ==========" );

			$process         = $this->execute_command( $command );
			$final_exit_code = $process->return_code;
			$success         = ( 0 === $process->return_code );
			if ( $operation_status_stack[0] !== null ) {
				$operation_status_stack[1] = $operation_status_stack[0];
			}
			$operation_status_stack[0] = $success;

			if ( ! empty( $process->stdout ) ) {
				$this->logger->info( $process->stdout );
			}

			if ( $success ) {
				++$total_success_count;
				$this->logger->info( "Command succeeded on attempt #{$attempt} (exit code 0)." );

				if ( ! $restart_on_success ) {
					break;
				}

				if ( ! empty( $completion_criteria ) ) {
					// Check if the output contains the completion criteria string.
					$output = ( $process->stdout ?? '' ) . ( $process->stderr ?? '' );
					if ( false !== strpos( $output, $completion_criteria ) ) {
						$this->logger->info( "Completion criteria \"{$completion_criteria}\" found in output. Stopping." );
						break;
					}
				} elseif ( $total_success_count >= $max_success_retries ) {
					$this->logger->info( "Max success retries ({$max_success_retries}) reached. Stopping." );
					break;
				}

				$this->logger->info( "--restart-on-success is set; will restart after delay (success {$total_success_count}" . ( empty( $completion_criteria ) ? "/{$max_success_retries})." : ').' ) );
			} else {
				++$total_fail_count;

				if ( false === $operation_status_stack[1] ) {
					++$consecutive_fail_count;
				} else {
					$consecutive_fail_count = 1;
				}

				if ( ! empty( $process->stderr ) ) {
					$this->logger->error( $process->stderr );
				}
				$this->logger->warning( "Command failed with exit code {$process->return_code} on attempt #{$attempt} (consecutive failures {$consecutive_fail_count}/{$max_consecutive_fail_retries} failure {$total_fail_count}/{$max_fail_retries})." );

				// Check whether we've exhausted failure retries.
				if ( $total_fail_count >= $max_fail_retries ) {
					$this->logger->warning( "Max fail retries ({$max_fail_retries}) reached. Stopping." );
					break;
				}
			}

			$this->logger->info( "Retrying in {$retry_delay} seconds..." );
			sleep( $retry_delay );
		}

		// Final summary.
		$this->logger->info( '========== Supervision Complete ==========' );
		$this->logger->info( sprintf( 'Finished after %d attempt(s), %d failure(s). Final exit code: %d.', $attempt, $total_fail_count, $final_exit_code ) );

		if ( $notify_email ) {
			if ( ! is_email( $notify_email ) ) {
				$this->logger->error( sprintf( 'Invalid notify-email address provided: %s', $notify_email ) );
			} else {
				$this->send_notification( $notify_email, $final_exit_code, $command, $attempt );
			}
		}

		if ( $total_success_count >= $max_success_retries ) {
			$this->logger->info( 'Command succeeded after max success retries.' );
		} elseif ( $consecutive_fail_count >= $max_consecutive_fail_retries || $total_fail_count >= $max_fail_retries ) {
			$this->logger->error( 'Command failed after max consecutive failures or max fail retries.' );
			WP_CLI::halt( 1 );
		}

		$this->logger->info( 'Command supervision completed successfully.' );
	}

	/**
	 * Executes a WP-CLI command as a subprocess and returns the process result.
	 *
	 * Uses WP_CLI::runcommand() with launch=true so it spawns a new process using the
	 * same PHP binary, avoiding "env: php: No such file or directory" issues.
	 *
	 * @param string $command WP-CLI command without the "wp" prefix.
	 * @return object{stdout: string, stderr: string, return_code: int} Process result.
	 */
	private function execute_command( string $command ): object {
		return WP_CLI::runcommand(
			$command,
			[
				'launch'     => true,
				'return'     => 'all',
				'exit_error' => false,
			]
		);
	}

	/**
	 * Injects --skip-themes and --skip-plugins global flags into a WP-CLI command for memory optimization.
	 *
	 * Flags are prepended to the command string so they appear before the subcommand, e.g.:
	 *   --skip-themes --skip-plugins=foo,bar newspack-content-migrator some-command
	 *
	 * If the caller already included --skip-themes or --skip-plugins in the command,
	 * those flags are respected and not overridden.
	 *
	 * @param string $command            The WP-CLI command (without "wp" prefix) to modify.
	 * @param string $active_plugins_arg Comma-separated list of additional plugin slugs to keep active.
	 *
	 * @return string The command with WP-CLI global flags prepended.
	 */
	private function inject_wp_cli_global_flags( string $command, string $active_plugins_arg ): string {
		// Check if the caller already included --skip-* flags in the command.
		$has_skip_themes  = (bool) preg_match( '/--skip-themes\b/', $command );
		$has_skip_plugins = (bool) preg_match( '/--skip-plugins\b/', $command );

		$flags = [];

		if ( $has_skip_themes ) {
			$this->logger->info( '--skip-themes already present in command; skipping.' );
		} else {
			$flags[] = '--skip-themes';
		}

		if ( $has_skip_plugins ) {
			$this->logger->info( '--skip-plugins already present in command; skipping.' );
		} else {
			$skip_plugins_list = $this->get_plugins_to_skip( $active_plugins_arg );
			if ( ! empty( $skip_plugins_list ) ) {
				$flags[] = '--skip-plugins=' . implode( ',', $skip_plugins_list );
			}
		}

		if ( empty( $flags ) ) {
			return $command;
		}

		$this->logger->info( 'Injected WP-CLI global flags: ' . implode( ' ', $flags ) );

		return implode( ' ', $flags ) . ' ' . $command;
	}

	/**
	 * Builds the list of plugin slugs to skip.
	 *
	 * All active plugins are included except newspack-custom-content-migrator and any additional
	 * plugins specified by the caller.
	 *
	 * @param string $active_plugins_arg Comma-separated list of additional plugin slugs to keep active.
	 *
	 * @return string[] Plugin slugs to pass to --skip-plugins.
	 */
	private function get_plugins_to_skip( string $active_plugins_arg ): array {
		// Build the whitelist of plugins that should remain active.
		$whitelist = [ 'newspack-custom-content-migrator' ];
		if ( ! empty( $active_plugins_arg ) ) {
			$whitelist = array_merge(
				$whitelist,
				array_map( 'trim', explode( ',', $active_plugins_arg ) )
			);
		}
		$whitelist = array_unique( $whitelist );

		// Determine which active plugins to skip.
		$all_active   = get_option( 'active_plugins', [] );
		$skip_plugins = [];

		foreach ( $all_active as $plugin_path ) {
			$plugin_slug = dirname( $plugin_path );

			// Single-file plugins have no directory, so dirname returns '.'.
			if ( '.' === $plugin_slug ) {
				$plugin_slug = basename( $plugin_path, '.php' );
			}

			if ( ! in_array( $plugin_slug, $whitelist, true ) ) {
				$skip_plugins[] = $plugin_slug;
			}
		}

		return $skip_plugins;
	}

	/**
	 * Sends an email notification about the supervision outcome.
	 *
	 * @param string $email     Recipient email address.
	 * @param int    $exit_code Final exit code of the supervised command.
	 * @param string $command   The supervised command string.
	 * @param int    $attempts  Total number of attempts made.
	 */
	private function send_notification( string $email, int $exit_code, string $command, int $attempts ): void {
		$status  = ( 0 === $exit_code ) ? 'succeeded' : 'failed';
		$subject = sprintf( '[CommandSupervisor] Command %s after %d attempt(s)', $status, $attempts );
		$body    = implode(
			"\n",
			[
				"Command:  {$command}",
				"Status:   {$status}",
				"Exit code: {$exit_code}",
				"Attempts: {$attempts}",
				'Time:     ' . gmdate( 'Y-m-d H:i:s' ),
			]
		);

		$sent = wp_mail( $email, $subject, $body );

		if ( $sent ) {
			$this->logger->info( "Notification sent to {$email}." );
		} else {
			$this->logger->warning( "Failed to send notification to {$email}." );
		}
	}
}
