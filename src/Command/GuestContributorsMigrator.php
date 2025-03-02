<?php
/**
 * GuestContributorsMigrator class.
 * 
 * @package newspack-migration-tools
 */

namespace Newspack\MigrationTools\Command;

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
}
