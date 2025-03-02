<?php

namespace Newspack\MigrationTools\Command;

use Newspack\MigrationTools\Logic\GuestContributorsHelper;
use WP_CLI;

/**
 * Class for migrating guest contributors.
 */
class GuestContributorsMigrator implements WpCliCommandInterface {

    use WpCliCommandTrait;

    /**
     * Create a guest contributor from a display name.
     *
     * ## OPTIONS
     *
     * &lt;display_name&gt;
     * : The display name for the guest contributor.
     *
     * [--force]
     * : Force creation even if a user with the same display name exists.
     *
     * ## EXAMPLES
     *
     *     # Create a guest contributor
     *     $ wp newspack-content-migrator guest-contributors create "John Smith"
     *
     *     # Force create a guest contributor even if one exists
     *     $ wp newspack-content-migrator guest-contributors create "John Smith" --force
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function create( $args, $assoc_args ) {
        if ( empty( $args[0] ) ) {
            WP_CLI::error( 'Display name is required.' );
        }

        $display_name = $args[0];
        $force = isset( $assoc_args['force'] );

        try {
            $result = GuestContributorsHelper::create_from_display_name( $display_name, $force );
            
            if ( is_wp_error( $result ) ) {
                WP_CLI::error( $result->get_error_message() );
            }

            WP_CLI::success( sprintf( 'Created guest contributor with ID: %d', $result ) );
        } catch ( \Exception $e ) {
            WP_CLI::error( $e->getMessage() );
        }
    }

    /**
     * Get guest contributor(s) by display name.
     *
     * ## OPTIONS
     *
     * &lt;display_name&gt;
     * : The display name to search for.
     *
     * ## EXAMPLES
     *
     *     # Find guest contributors
     *     $ wp newspack-content-migrator guest-contributors get "John Smith"
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function get( $args, $assoc_args ) {
        if ( empty( $args[0] ) ) {
            WP_CLI::error( 'Display name is required.' );
        }

        $display_name = $args[0];

        try {
            $result = GuestContributorsHelper::get_by_display_name( $display_name );
            
            if ( is_wp_error( $result ) ) {
                WP_CLI::error( $result->get_error_message() );
            }

            if ( empty( $result ) ) {
                WP_CLI::warning( 'No guest contributors found with that display name.' );
                return;
            }

            WP_CLI::success( sprintf( 'Found %d guest contributor(s):', count( $result ) ) );
            foreach ( $result as $user_id ) {
                $user = get_userdata( $user_id );
                WP_CLI::line( sprintf( 'ID: %d, Display Name: %s', $user_id, $user->display_name ) );
            }
        } catch ( \Exception $e ) {
            WP_CLI::error( $e->getMessage() );
        }
    }
}
