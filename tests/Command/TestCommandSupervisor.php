<?php

namespace Newspack\MigrationTools\Tests\Command;

use Newspack\MigrationTools\Command\CommandSupervisor;
use Newspack\MigrationTools\NMT;
use Newspack\MigrationTools\Util\Log\MultiLog;
use ReflectionClass;
use WP_UnitTestCase;

/**
 * Class TestCommandSupervisor
 *
 * @package newspack-migration-tools
 */
class TestCommandSupervisor extends WP_UnitTestCase {

	/**
	 * The CommandSupervisor instance under test.
	 *
	 * @var CommandSupervisor
	 */
	private CommandSupervisor $supervisor;

	/**
	 * Reflection class for accessing private methods.
	 *
	 * @var ReflectionClass
	 */
	private ReflectionClass $reflection;

	/**
	 * Temporary plugin directories created during tests.
	 *
	 * @var string[]
	 */
	private array $temp_plugin_dirs = [];

	/**
	 * Filters registered during tests that need to be removed in tearDown.
	 *
	 * Each entry is [ hook, callback, priority ].
	 *
	 * @var array[]
	 */
	private array $filters_to_remove = [];

	/**
	 * {@inheritDoc}
	 */
	public function setUp(): void {
		parent::setUp();

		add_filter( 'newspack_migration_tools_log_file_logger_disable', '__return_true' );
		add_filter( 'newspack_migration_tools_log_clilog_disable', '__return_true' );

		$this->reflection = new ReflectionClass( CommandSupervisor::class );
		$this->supervisor = $this->reflection->newInstanceWithoutConstructor();

		// Initialize logger since private methods depend on it.
		$logger_prop = $this->reflection->getProperty( 'logger' );
		$logger_prop->setAccessible( true );
		$logger_prop->setValue( $this->supervisor, MultiLog::get_cli_and_file_logger( 'CommandSupervisorTest' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function tearDown(): void {
		foreach ( $this->temp_plugin_dirs as $dir ) {
			if ( file_exists( $dir . '/composer.json' ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $dir . '/composer.json' );
			}
			if ( is_dir( $dir ) ) {
				// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir
				rmdir( $dir );
			}
		}
		$this->temp_plugin_dirs = [];

		foreach ( $this->filters_to_remove as list( $hook, $callback, $priority ) ) {
			remove_filter( $hook, $callback, $priority );
		}
		$this->filters_to_remove = [];

		parent::tearDown();
	}

	/**
	 * Invoke a private method on the supervisor instance via reflection.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Method arguments.
	 *
	 * @return mixed The method's return value.
	 */
	private function invoke_private_method( string $method, array $args = [] ) {
		$method_ref = $this->reflection->getMethod( $method );
		$method_ref->setAccessible( true );

		return $method_ref->invokeArgs( $this->supervisor, $args );
	}

	/**
	 * Override the active_plugins option for the duration of a test.
	 *
	 * The test bootstrap sets $GLOBALS['wp_tests_options']['active_plugins'] which
	 * gets returned by get_option() via a pre_option filter, bypassing the database.
	 * This helper overrides that filter so our test value is used instead.
	 *
	 * @param array $plugins The active plugins array to use.
	 */
	private function set_active_plugins( array $plugins ): void {
		$callback = function () use ( $plugins ) {
			return $plugins;
		};
		add_filter( 'pre_option_active_plugins', $callback );
		$this->filters_to_remove[] = [ 'pre_option_active_plugins', $callback, 10 ];
	}

	/**
	 * Create a temporary plugin directory with an optional composer.json.
	 *
	 * @param string     $slug          Plugin slug (directory name).
	 * @param array|null $composer_data Composer data to write as JSON, or null for no composer.json.
	 */
	private function create_temp_plugin( string $slug, ?array $composer_data = null ): void {
		$plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
		if ( ! is_dir( $plugin_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir,WordPressVIPMinimum.Functions.RestrictedFunctions.directory_mkdir
			mkdir( $plugin_dir, 0755, true );
		}
		if ( null !== $composer_data ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $plugin_dir . '/composer.json', wp_json_encode( $composer_data ) );
		}
		$this->temp_plugin_dirs[] = $plugin_dir;
	}

	// ==========================================================================
	// Tests for EXIT_DONE constant.
	// ==========================================================================

	/**
	 * Test that EXIT_DONE constant is defined on NMT and equals 2.
	 */
	public function test_exit_done_constant_is_defined(): void {
		$this->assertSame( 2, NMT::EXIT_DONE );
	}

	// ==========================================================================
	// Tests for get_cli_commands().
	// ==========================================================================

	/**
	 * Test that get_cli_commands returns a valid structure.
	 */
	public function test_get_cli_commands_returns_valid_structure(): void {
		$commands = CommandSupervisor::get_cli_commands();

		$this->assertIsArray( $commands );
		$this->assertCount( 1, $commands );

		$command = $commands[0];
		$this->assertSame( 'newspack-content-migrator command-supervisor', $command[0] );
		$this->assertIsCallable( $command[1] );
		$this->assertArrayHasKey( 'shortdesc', $command[2] );
		$this->assertArrayHasKey( 'synopsis', $command[2] );
	}

	/**
	 * Test that all expected synopsis parameters are present.
	 */
	public function test_get_cli_commands_has_all_synopsis_params(): void {
		$commands    = CommandSupervisor::get_cli_commands();
		$synopsis    = $commands[0][2]['synopsis'];
		$param_names = array_column( $synopsis, 'name' );

		$expected = [
			'command',
			'max-fail-retries',
			'max-consecutive-fail-retries',
			'retry-delay',
			'restart-on-success',
			'completion-criteria',
			'max-success-retries',
			'active-plugins',
			'notify-email',
		];

		foreach ( $expected as $param ) {
			$this->assertContains( $param, $param_names, sprintf( 'Missing synopsis param: %s', $param ) );
		}
	}

	/**
	 * Test that --command is the only required parameter.
	 */
	public function test_command_param_is_required(): void {
		$commands = CommandSupervisor::get_cli_commands();
		$synopsis = $commands[0][2]['synopsis'];

		foreach ( $synopsis as $param ) {
			if ( 'command' === $param['name'] ) {
				$this->assertFalse( $param['optional'] );
			} else {
				$this->assertTrue(
					$param['optional'] ?? true,
					sprintf( 'Param %s should be optional', $param['name'] )
				);
			}
		}
	}

	// ==========================================================================
	// Tests for inject_wp_cli_global_flags().
	// ==========================================================================

	/**
	 * Test that --skip-themes and --skip-plugins are injected.
	 */
	public function test_inject_flags_adds_skip_themes_and_plugins(): void {
		$this->set_active_plugins( [ 'some-plugin/some-plugin.php' ] );

		$result = $this->invoke_private_method(
			'inject_wp_cli_global_flags',
			[ 'newspack-content-migrator some-command', '' ]
		);

		$this->assertStringContainsString( '--skip-themes', $result );
		$this->assertStringContainsString( '--skip-plugins=', $result );
		$this->assertStringEndsWith( 'newspack-content-migrator some-command', $result );
	}

	/**
	 * Test that existing --skip-themes is not duplicated.
	 */
	public function test_inject_flags_respects_existing_skip_themes(): void {
		$result = $this->invoke_private_method(
			'inject_wp_cli_global_flags',
			[ '--skip-themes newspack-content-migrator some-command', '' ]
		);

		$this->assertSame( 1, substr_count( $result, '--skip-themes' ) );
	}

	/**
	 * Test that existing --skip-plugins is not duplicated.
	 */
	public function test_inject_flags_respects_existing_skip_plugins(): void {
		$result = $this->invoke_private_method(
			'inject_wp_cli_global_flags',
			[ '--skip-plugins=foo newspack-content-migrator some-command', '' ]
		);

		$this->assertSame( 1, substr_count( $result, '--skip-plugins' ) );
	}

	/**
	 * Test that the command is returned unchanged when both flags are already present.
	 */
	public function test_inject_flags_returns_unchanged_when_both_present(): void {
		$command = '--skip-themes --skip-plugins=foo newspack-content-migrator some-command';
		$result  = $this->invoke_private_method( 'inject_wp_cli_global_flags', [ $command, '' ] );

		$this->assertSame( $command, $result );
	}

	/**
	 * Test that --skip-plugins is omitted when there are no plugins to skip.
	 */
	public function test_inject_flags_omits_skip_plugins_when_none_to_skip(): void {
		$this->set_active_plugins( [] );

		$result = $this->invoke_private_method(
			'inject_wp_cli_global_flags',
			[ 'newspack-content-migrator some-command', '' ]
		);

		$this->assertStringContainsString( '--skip-themes', $result );
		$this->assertStringNotContainsString( '--skip-plugins', $result );
	}

	/**
	 * Test that flags are prepended before the subcommand.
	 */
	public function test_inject_flags_prepends_before_subcommand(): void {
		$this->set_active_plugins( [ 'some-plugin/some-plugin.php' ] );

		$result = $this->invoke_private_method(
			'inject_wp_cli_global_flags',
			[ 'newspack-content-migrator some-command --batch-size=100', '' ]
		);

		// Flags should come before the subcommand.
		$skip_pos    = strpos( $result, '--skip-themes' );
		$command_pos = strpos( $result, 'newspack-content-migrator' );
		$this->assertLessThan( $command_pos, $skip_pos );
	}

	// ==========================================================================
	// Tests for get_plugins_to_skip().
	// ==========================================================================

	/**
	 * Test that all non-whitelisted active plugins are skipped.
	 */
	public function test_get_plugins_to_skip_skips_non_whitelisted(): void {
		$this->set_active_plugins(
			[
				'plugin-a/plugin-a.php',
				'plugin-b/plugin-b.php',
			]
		);

		$result = $this->invoke_private_method( 'get_plugins_to_skip', [ '' ] );

		$this->assertContains( 'plugin-a', $result );
		$this->assertContains( 'plugin-b', $result );
	}

	/**
	 * Test that caller-specified plugins are kept active (not skipped).
	 */
	public function test_get_plugins_to_skip_keeps_whitelisted_plugins(): void {
		$this->set_active_plugins(
			[
				'plugin-a/plugin-a.php',
				'plugin-b/plugin-b.php',
			]
		);

		$result = $this->invoke_private_method( 'get_plugins_to_skip', [ 'plugin-a' ] );

		$this->assertNotContains( 'plugin-a', $result );
		$this->assertContains( 'plugin-b', $result );
	}

	/**
	 * Test that multiple comma-separated whitelist plugins are all kept.
	 */
	public function test_get_plugins_to_skip_handles_multiple_whitelisted(): void {
		$this->set_active_plugins(
			[
				'plugin-a/plugin-a.php',
				'plugin-b/plugin-b.php',
				'plugin-c/plugin-c.php',
			]
		);

		$result = $this->invoke_private_method( 'get_plugins_to_skip', [ 'plugin-a, plugin-c' ] );

		$this->assertNotContains( 'plugin-a', $result );
		$this->assertNotContains( 'plugin-c', $result );
		$this->assertContains( 'plugin-b', $result );
	}

	/**
	 * Test that single-file plugins use the basename (minus .php) as their slug.
	 */
	public function test_get_plugins_to_skip_handles_single_file_plugins(): void {
		$this->set_active_plugins(
			[
				'single-file-plugin.php',
				'plugin-a/plugin-a.php',
			]
		);

		$result = $this->invoke_private_method( 'get_plugins_to_skip', [ '' ] );

		$this->assertContains( 'single-file-plugin', $result );
		$this->assertContains( 'plugin-a', $result );
	}

	/**
	 * Test that an empty skip list is returned when there are no active plugins.
	 */
	public function test_get_plugins_to_skip_returns_empty_when_no_active_plugins(): void {
		$this->set_active_plugins( [] );

		$result = $this->invoke_private_method( 'get_plugins_to_skip', [ '' ] );

		$this->assertEmpty( $result );
	}

	/**
	 * Test that default migration plugins (Co-Authors Plus, Simple Local Avatars, Yoast, Newspack) are whitelisted.
	 */
	public function test_get_plugins_to_skip_whitelists_default_migration_plugins(): void {
		$this->set_active_plugins(
			[
				'co-authors-plus/co-authors-plus.php',
				'simple-local-avatars/simple-local-avatars.php',
				'wordpress-seo/wp-seo.php',
				'newspack-plugin/newspack.php',
				'some-other-plugin/some-other-plugin.php',
			]
		);

		$result = $this->invoke_private_method( 'get_plugins_to_skip', [ '' ] );

		$this->assertNotContains( 'co-authors-plus', $result );
		$this->assertNotContains( 'simple-local-avatars', $result );
		$this->assertNotContains( 'wordpress-seo', $result );
		$this->assertNotContains( 'newspack-plugin', $result );
		$this->assertContains( 'some-other-plugin', $result );
	}

	/**
	 * Test that detected host plugins are whitelisted automatically.
	 */
	public function test_get_plugins_to_skip_whitelists_host_plugin(): void {
		$this->create_temp_plugin(
			'test-nmt-host',
			[
				'require' => [
					'automattic/newspack-migration-tools' => 'dev-trunk',
				],
			]
		);

		$this->set_active_plugins(
			[
				'test-nmt-host/test-nmt-host.php',
				'some-other-plugin/some-other-plugin.php',
			]
		);

		$result = $this->invoke_private_method( 'get_plugins_to_skip', [ '' ] );

		$this->assertNotContains( 'test-nmt-host', $result );
		$this->assertContains( 'some-other-plugin', $result );
	}

	// ==========================================================================
	// Tests for get_host_plugin_slugs().
	// ==========================================================================

	/**
	 * Test that a plugin requiring newspack-migration-tools is detected.
	 */
	public function test_get_host_plugin_slugs_detects_dependency(): void {
		$this->create_temp_plugin(
			'test-host-plugin',
			[
				'require' => [
					'automattic/newspack-migration-tools' => 'dev-trunk',
				],
			]
		);

		$result = $this->invoke_private_method(
			'get_host_plugin_slugs',
			[ [ 'test-host-plugin/test-host-plugin.php' ] ]
		);

		$this->assertContains( 'test-host-plugin', $result );
	}

	/**
	 * Test that a plugin without the dependency is not detected.
	 */
	public function test_get_host_plugin_slugs_ignores_non_dependent_plugins(): void {
		$this->create_temp_plugin(
			'not-a-host',
			[
				'require' => [
					'some/other-package' => '^1.0',
				],
			]
		);

		$result = $this->invoke_private_method(
			'get_host_plugin_slugs',
			[ [ 'not-a-host/not-a-host.php' ] ]
		);

		$this->assertNotContains( 'not-a-host', $result );
	}

	/**
	 * Test that plugins without a composer.json are skipped.
	 */
	public function test_get_host_plugin_slugs_skips_plugins_without_composer_json(): void {
		$this->create_temp_plugin( 'no-composer', null );

		$result = $this->invoke_private_method(
			'get_host_plugin_slugs',
			[ [ 'no-composer/no-composer.php' ] ]
		);

		$this->assertNotContains( 'no-composer', $result );
	}

	/**
	 * Test that single-file plugins (no directory) are skipped.
	 */
	public function test_get_host_plugin_slugs_skips_single_file_plugins(): void {
		$result = $this->invoke_private_method(
			'get_host_plugin_slugs',
			[ [ 'single-file.php' ] ]
		);

		$this->assertEmpty( $result );
	}

	/**
	 * Test that a plugin with invalid JSON in composer.json is skipped.
	 */
	public function test_get_host_plugin_slugs_handles_invalid_json(): void {
		$plugin_dir = WP_PLUGIN_DIR . '/invalid-json-plugin';
		if ( ! is_dir( $plugin_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir,WordPressVIPMinimum.Functions.RestrictedFunctions.directory_mkdir
			mkdir( $plugin_dir, 0755, true );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $plugin_dir . '/composer.json', 'not valid json{{{' );
		$this->temp_plugin_dirs[] = $plugin_dir;

		$result = $this->invoke_private_method(
			'get_host_plugin_slugs',
			[ [ 'invalid-json-plugin/invalid-json-plugin.php' ] ]
		);

		$this->assertNotContains( 'invalid-json-plugin', $result );
	}

	/**
	 * Test that multiple host plugins are detected.
	 */
	public function test_get_host_plugin_slugs_returns_multiple_hosts(): void {
		$this->create_temp_plugin(
			'host-one',
			[
				'require' => [
					'automattic/newspack-migration-tools' => 'dev-trunk',
				],
			]
		);
		$this->create_temp_plugin(
			'host-two',
			[
				'require' => [
					'automattic/newspack-migration-tools' => '^1.0',
				],
			]
		);

		$result = $this->invoke_private_method(
			'get_host_plugin_slugs',
			[
				[
					'host-one/host-one.php',
					'host-two/host-two.php',
				],
			]
		);

		$this->assertContains( 'host-one', $result );
		$this->assertContains( 'host-two', $result );
		$this->assertCount( 2, $result );
	}

	/**
	 * Test that an empty list is returned when no active plugins exist.
	 */
	public function test_get_host_plugin_slugs_returns_empty_for_no_plugins(): void {
		$result = $this->invoke_private_method( 'get_host_plugin_slugs', [ [] ] );

		$this->assertEmpty( $result );
	}

	// ==========================================================================
	// Tests for wp prefix stripping (regex used in cmd_supervise).
	// ==========================================================================

	/**
	 * Test that the wp prefix is correctly stripped from various command formats.
	 *
	 * @dataProvider data_wp_prefix_stripping
	 *
	 * @param string $input    The input command string.
	 * @param string $expected The expected result after stripping.
	 */
	public function test_wp_prefix_is_stripped( string $input, string $expected ): void {
		$result = preg_replace( '/^\S*wp\s+/', '', $input );
		$this->assertSame( $expected, $result );
	}

	/**
	 * Data provider for wp prefix stripping tests.
	 *
	 * @return array[] Test cases.
	 */
	public function data_wp_prefix_stripping(): array {
		return [
			'simple wp prefix'         => [
				'wp some-command --flag',
				'some-command --flag',
			],
			'full path wp prefix'      => [
				'/usr/local/bin/wp some-command --flag',
				'some-command --flag',
			],
			'no wp prefix'             => [
				'some-command --flag',
				'some-command --flag',
			],
			'wp in middle of word'     => [
				'newspack-wp-migrator some-command',
				'newspack-wp-migrator some-command',
			],
			'wp with extra whitespace' => [
				'wp   some-command',
				'some-command',
			],
		];
	}

	// ==========================================================================
	// Tests for send_notification().
	// ==========================================================================

	/**
	 * Test that a success notification is sent with the correct content.
	 */
	public function test_send_notification_sends_success_email(): void {
		$captured_atts = null;
		$callback      = function ( $no_value, $atts ) use ( &$captured_atts ) {
			$captured_atts = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $callback, 10, 2 );
		$this->filters_to_remove[] = [ 'pre_wp_mail', $callback, 10 ];

		$this->invoke_private_method(
			'send_notification',
			[ 'test@example.com', 0, 'newspack-content-migrator some-command', 3 ]
		);

		$this->assertNotNull( $captured_atts );
		$this->assertSame( 'test@example.com', $captured_atts['to'] );
		$this->assertStringContainsString( 'succeeded', $captured_atts['subject'] );
		$this->assertStringContainsString( '3 attempt(s)', $captured_atts['subject'] );
		$this->assertStringContainsString( 'newspack-content-migrator some-command', $captured_atts['message'] );
	}

	/**
	 * Test that a failure notification is sent with the correct status.
	 */
	public function test_send_notification_sends_failure_email(): void {
		$captured_atts = null;
		$callback      = function ( $no_value, $atts ) use ( &$captured_atts ) {
			$captured_atts = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $callback, 10, 2 );
		$this->filters_to_remove[] = [ 'pre_wp_mail', $callback, 10 ];

		$this->invoke_private_method(
			'send_notification',
			[ 'test@example.com', 1, 'some-command', 5 ]
		);

		$this->assertNotNull( $captured_atts );
		$this->assertStringContainsString( 'failed', $captured_atts['subject'] );
		$this->assertStringContainsString( '5 attempt(s)', $captured_atts['subject'] );
	}

	/**
	 * Test that the email body contains all expected fields.
	 */
	public function test_send_notification_email_body_content(): void {
		$captured_atts = null;
		$callback      = function ( $no_value, $atts ) use ( &$captured_atts ) {
			$captured_atts = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $callback, 10, 2 );
		$this->filters_to_remove[] = [ 'pre_wp_mail', $callback, 10 ];

		$this->invoke_private_method(
			'send_notification',
			[ 'test@example.com', 0, 'my-command --flag', 2 ]
		);

		$body = $captured_atts['message'];
		$this->assertStringContainsString( 'my-command --flag', $body );
		$this->assertStringContainsString( 'succeeded', $body );
		$this->assertStringContainsString( 'Exit code: 0', $body );
		$this->assertStringContainsString( 'Attempts:  2', $body );
	}

	// ==========================================================================
	// Tests for cmd_supervise() supervision loop.
	// ==========================================================================

	/**
	 * Create a process result object for use with TestableCommandSupervisor.
	 *
	 * @param int    $return_code Exit code.
	 * @param string $stdout      Standard output.
	 * @param string $stderr      Standard error.
	 *
	 * @return object Process result.
	 */
	private function make_process_result( int $return_code, string $stdout = '', string $stderr = '' ): object {
		return (object) [
			'stdout'      => $stdout,
			'stderr'      => $stderr,
			'return_code' => $return_code,
		];
	}

	/**
	 * Create a TestableCommandSupervisor with pre-configured process results.
	 *
	 * @param object[] $process_results Sequence of process results to return.
	 *
	 * @return TestableCommandSupervisor
	 */
	private function create_testable_supervisor( array $process_results ): TestableCommandSupervisor {
		$supervisor                  = new TestableCommandSupervisor();
		$supervisor->process_results = $process_results;

		return $supervisor;
	}

	/**
	 * Build assoc_args for cmd_supervise() with sensible test defaults.
	 *
	 * @param array $overrides Key-value pairs to merge into defaults.
	 *
	 * @return array
	 */
	private function get_default_supervise_args( array $overrides = [] ): array {
		return array_merge(
			[
				'command'     => 'some-command --batch-size=100',
				'retry-delay' => 0,
			],
			$overrides
		);
	}

	/**
	 * Test that EXIT_DONE stops the loop even when --restart-on-success is set.
	 */
	public function test_cmd_supervise_stops_on_exit_done_with_restart_on_success(): void {
		$this->set_active_plugins( [] );
		$supervisor = $this->create_testable_supervisor(
			[
				$this->make_process_result( 0 ),
				$this->make_process_result( NMT::EXIT_DONE ),
			]
		);

		$supervisor->cmd_supervise(
			[],
			$this->get_default_supervise_args( [ 'restart-on-success' => true ] )
		);

		$this->assertSame( 2, $supervisor->execute_count );
	}

	/**
	 * Test that EXIT_DONE stops the loop when --restart-on-success is not set.
	 */
	public function test_cmd_supervise_stops_on_exit_done_without_restart_on_success(): void {
		$this->set_active_plugins( [] );
		$supervisor = $this->create_testable_supervisor(
			[
				$this->make_process_result( NMT::EXIT_DONE ),
			]
		);

		$supervisor->cmd_supervise(
			[],
			$this->get_default_supervise_args()
		);

		$this->assertSame( 1, $supervisor->execute_count );
	}

	/**
	 * Test that exit 0 continues the loop with --restart-on-success until EXIT_DONE.
	 */
	public function test_cmd_supervise_continues_on_exit_0_with_restart_on_success(): void {
		$this->set_active_plugins( [] );
		$supervisor = $this->create_testable_supervisor(
			[
				$this->make_process_result( 0 ),
				$this->make_process_result( 0 ),
				$this->make_process_result( NMT::EXIT_DONE ),
			]
		);

		$supervisor->cmd_supervise(
			[],
			$this->get_default_supervise_args( [ 'restart-on-success' => true ] )
		);

		$this->assertSame( 3, $supervisor->execute_count );
	}

	/**
	 * Test that exit 0 stops the loop when --restart-on-success is not set.
	 */
	public function test_cmd_supervise_stops_on_exit_0_without_restart_on_success(): void {
		$this->set_active_plugins( [] );
		$supervisor = $this->create_testable_supervisor(
			[
				$this->make_process_result( 0 ),
			]
		);

		$supervisor->cmd_supervise(
			[],
			$this->get_default_supervise_args()
		);

		$this->assertSame( 1, $supervisor->execute_count );
	}

	/**
	 * Test that EXIT_DONE normalizes the final exit code to 0 (notification says "succeeded").
	 */
	public function test_cmd_supervise_exit_done_sends_success_notification(): void {
		$this->set_active_plugins( [] );
		$captured_atts = null;
		$callback      = function ( $no_value, $atts ) use ( &$captured_atts ) {
			$captured_atts = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $callback, 10, 2 );
		$this->filters_to_remove[] = [ 'pre_wp_mail', $callback, 10 ];

		$supervisor = $this->create_testable_supervisor(
			[
				$this->make_process_result( NMT::EXIT_DONE ),
			]
		);

		$supervisor->cmd_supervise(
			[],
			$this->get_default_supervise_args( [ 'notify-email' => 'test@example.com' ] )
		);

		$this->assertNotNull( $captured_atts );
		$this->assertStringContainsString( 'succeeded', $captured_atts['subject'] );
	}

	/**
	 * Test that --restart-on-success stops at max-success-retries when no EXIT_DONE is received.
	 */
	public function test_cmd_supervise_stops_at_max_success_retries(): void {
		$this->set_active_plugins( [] );
		$supervisor = $this->create_testable_supervisor(
			[
				$this->make_process_result( 0 ),
				$this->make_process_result( 0 ),
				$this->make_process_result( 0 ),
			]
		);

		$supervisor->cmd_supervise(
			[],
			$this->get_default_supervise_args(
				[
					'restart-on-success'  => true,
					'max-success-retries' => 3,
				]
			)
		);

		$this->assertSame( 3, $supervisor->execute_count );
	}
}

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
