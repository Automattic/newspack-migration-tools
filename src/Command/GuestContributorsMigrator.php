<?php
/**
 * GuestContributorsMigrator class.
 * 
 * @package newspack-migration-tools
 */

namespace Newspack\MigrationTools\Command;

use Bramus\Monolog\Formatter\ColoredLineFormatter;
use Monolog\Formatter\LineFormatter;
use Newspack\MigrationTools\Logic\GuestContributorsHelper;
use Newspack\MigrationTools\Util\Log\CliLog;
use Newspack\MigrationTools\Util\Log\FileLog;
use Newspack\MigrationTools\Util\Log\MultiLog;
use WP_CLI;

/**
 * Guest Contributors migration commands.
 */
class GuestContributorsMigrator implements WpCliCommandInterface {

    use WpCliCommandTrait;

    /** @var MultiLog $logger Logger instance. */
    private $logger;

	/**
	 * {@inheritDoc}
	 */
	public static function get_cli_commands(): array {
		return [
			[
				'newspack-content-migrator guest-contributors-create-by-display-name',
				self::get_command_closure( 'cmd_create_by_display_name' ),
				[
					'shortdesc' => 'Create a Guest Contributor by display name.',
					'synopsis'  => [
						[
							'type'        => 'assoc',
							'name'        => 'display_name',
							'description' => 'Display name of the guest contributor to create.',
							'optional'    => false,
							'repeating'   => false,
						],
						[
							'type'        => 'flag',
							'name'        => 'force',
							'description' => 'Force creation even if user(s) with the same display name exists.',
							'optional'    => true,
							'repeating'   => false,
						],
					],
				],
			],
			[
				'newspack-content-migrator guest-contributors-get-by-display-name',
				self::get_command_closure( 'cmd_get_by_display_name' ),
				[
					'shortdesc' => 'Get Guest Contributors by display name - may return multiple.',
					'synopsis'  => [
						[
							'type'        => 'assoc',
							'name'        => 'display_name',
							'description' => 'Display name of the guest contributor.',
							'optional'    => false,
							'repeating'   => false,
						],
					],
				],
			],
            [
                'newspack-content-migrator guest-contributors-tests',
                self::get_command_closure( 'cmd_tests' ),
                [
                    'shortdesc' => 'Test Guest Contributors functions.',
                    'synopsis'  => [],
                ],
            ],
        ];
    }

    /**
     * Create a guest contributor by display name.
     *
     * ## OPTIONS
     *
     *     --display_name=<display_name>
     *     : The display name for the guest contributor.
     *
     *     [--force]
     *     : Force creation even if user(s) with the same display name exists.
     *
     * ## EXAMPLES
     *
     *     # Create a guest contributor by display name
     *     $ wp newspack-content-migrator guest-contributors-create-by-display-name --display_name="John Smith"
     *
     *     # Force create even if one exists
     *     $ wp newspack-content-migrator guest-contributors-create-by-display-name --display_name="John Smith" --force
     *
     * @param array $pos_args   Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function cmd_create_by_display_name( $pos_args, $assoc_args ) {

        $display_name = $assoc_args['display_name'] ?? '';
        if ( empty( $display_name ) ) {
            WP_CLI::error( '--display_name=<display_name> is required.' );
            exit();
        }

        $force = isset( $assoc_args['force'] );

        $result = GuestContributorsHelper::create_by_display_name( $display_name, $force );
        if ( \is_wp_error( $result ) ) {
            WP_CLI::error( $result->get_error_message() );
            exit();
        }

        WP_CLI::info( $result );
    }

    /**
     * Get guest contributor by display name - may return multiple.
     *
     * ## OPTIONS
     *     
     *     --display_name=<display_name>
     *     : The display name to search for.
     *
     * ## EXAMPLES
     *
     *     # Find guest contributors
     *     $ wp newspack-content-migrator guest-contributors-get-by-display-name --display_name="John Smith"
     *
     * ## Output format
     *
     *     1,2,3
     *
     * @param array $pos_args   Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function cmd_get_by_display_name( $pos_args, $assoc_args ) {

        $display_name = $assoc_args['display_name'] ?? '';
        if ( empty( $display_name ) === 0 ) {
            WP_CLI::error( '--display_name=<display_name> is required.' );
            exit();
        }

        $result = GuestContributorsHelper::get_by_display_name( $display_name );
        if ( \is_wp_error( $result ) ) {
            WP_CLI::error( $result->get_error_message() );
            exit();
        }

        // Output result as list: 1,2,3
        WP_CLI::line( implode( ',', $result ) );

    }

    /**
     * Test Guest Contributors functions.
     *
     * Define WP_ENVIRONMENT_TYPE as 'local' or 'development' to run tests.
     *
     * ## EXAMPLES
     *
     *     # Test functions
     *     $ wp newspack-content-migrator guest-contributors-tests
     * 
     * ## OUTPUT
     *
     *     Log file: GuestContributorsMigrator_cmd_tests.log
     *
     * @param array $pos_args   Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function cmd_tests( $pos_args, $assoc_args ) {

        if ( ! defined( 'WP_ENVIRONMENT_TYPE' ) || ! in_array( WP_ENVIRONMENT_TYPE, [ 'local', 'development' ], true ) ) {
            WP_CLI::error( "Must have wp-config.php: define('WP_ENVIRONMENT_TYPE', 'local|development' )." );
        }

        if( ! GuestContributorsHelper::validate_newspack_plugin() ) {
            WP_CLI::error( GuestContributorsHelper::ERROR_NEWSPACK_PLUGIN );
            exit();
        };

        // Logger.
		$log_slug = str_replace( __NAMESPACE__ . '\\', '', __CLASS__ ) . '_' . __FUNCTION__;
		$this->logger = MultiLog::get_logger( 
			'multi-' . $log_slug,
			[
				CliLog::get_logger( $log_slug, new ColoredLineFormatter( null, "%level_name%: %message%\n", null, true ) ),
				FileLog::get_logger( $log_slug, $log_slug . '.log', new LineFormatter( "%level_name%: %message%\n", null, false, false, true ) ),
			]
		);
		
        // Tests.
		$this->logger->info( 'Starting tests...' );
        
        // Sanitizer tests.
        $this->tester( 'sanitize_for_db', [ '' ], 'preg_match', '/^$/' );
        $this->tester( 'sanitize_for_db', [ '&nbsp; <div>' ], 'preg_match', '/^$/' );
        $this->tester( 'sanitize_for_db', [ 'John Smith' ], 'preg_match', '/^john-smith$/' );
        $this->tester( 'sanitize_for_db', [ 'José ' ], 'preg_match', '/^jose$/' );
        
        // Username tests.
        $this->tester( 'generate_username', [ '' ], 'is_error', 'ERROR_SANITIZE_INPUT' );
        $this->tester( 'generate_username', [ '&nbsp; <div>' ], 'is_error', 'ERROR_SANITIZE_INPUT' );
        $this->tester( 'generate_username', [ 'John Smith' ], 'preg_match', '/^john-smith-[0-9]{5}$/' );
        $this->tester( 'generate_username', [ str_repeat( 'José ', 13 ) ], 'preg_match', '/^' . str_repeat( 'jose-', 11 ). '[0-9]{5}$/' ); // over db length.

        // Email tests.
        $this->tester( 'generate_email', [ '' ], 'is_error', 'ERROR_SANITIZE_INPUT' );
        $this->tester( 'generate_email', [ '&nbsp; <div>' ], 'is_error', 'ERROR_SANITIZE_INPUT' );
        $this->tester( 'generate_email', [ 'John Smith' ], 'preg_match', '/^john-smith-[0-9]{5}@example.com$/' );
        $this->tester( 'generate_email', [ str_repeat( 'José ', 21 ) ], 'preg_match', '/^' . str_repeat( 'jose-', 16 ) . 'jo-[0-9]{5}@example.com$/' ); // over db length.

        // Get tests.
        $this->tester( 'get_by_display_name', [ '' ], 'preg_match', '/^$/' );
        $this->tester( 'get_by_display_name', [ '*' ], 'preg_match', '/^$/' );
        $this->tester( 'get_by_display_name', [ 'John ' . microtime( ) . ' ' . wp_rand( 11111, 99999 ) ], 'preg_match', '/^$/' );

        // Create user tests.
        $this->tester( 'create_by_display_name', [ '' ], 'is_error', 'ERROR_DISPLAY_NAME' );
        $this->tester( 'create_by_display_name', [ '&nbsp; <div>' ], 'is_error', 'ERROR_GENERATE_EMAIL' );
        
        // Mixed tests.
        $unique_display_name = 'John ' . microtime( ) . ' ' . wp_rand( 11111, 99999 );
        $this->tester( 'get_by_display_name', [ $unique_display_name ], 'preg_match', '/^$/' );
        $this->tester( 'create_by_display_name', [ $unique_display_name ], 'preg_match', '/^\d+$/' );
        $this->tester( 'get_by_display_name', [ $unique_display_name ], 'preg_match', '/^\d+$/' );
        $this->tester( 'create_by_display_name', [ $unique_display_name ], 'is_error', 'ERROR_EXISTING_USERS' );
        $this->tester( 'create_by_display_name', [ $unique_display_name, true ], 'preg_match', '/^\d+$/' );
        $this->tester( 'get_by_display_name', [ $unique_display_name ], 'preg_match', '/^[\d,]+$/' );

        
        $this->logger->notice( "Tests completed." );
    }

    /**
     * Test a function with various inputs and expected results.
     *
     * @param string $function Function name.
     * @param array $args Function arguments.
     * @param string $result_type Test type.
     * @param string $result_match Expected match.
     */
    private function tester( $function, $args, $result_type, $result_match ) {


        $this->logger->info( 'Testing ' . $function . '("' . implode( '", "', $args ) . '")' );

        if ( ! is_callable( [ GuestContributorsHelper::class, $function ] ) ) {
            $this->logger->error( 'Failed: not a callable function.' );
            exit();
        }

        $result = call_user_func( [ GuestContributorsHelper::class, $function ], ...$args );

        if ( 'is_error' === $result_type && \is_wp_error( $result ) && $result_match === $result->get_error_code() ) {
            return $this->logger->info( 'Passed.' );
        }

        if ( 'preg_match' === $result_type && ! \is_wp_error( $result ) ) {
            $result = is_array( $result ) ? implode( ',', $result ) : $result;
            if ( preg_match( $result_match, $result ) ) {
                return $this->logger->info( 'Passed.' );
            }
        }

        $this->logger->error( 'Failed: ' . json_encode( $result ) );
        exit();

    }
}