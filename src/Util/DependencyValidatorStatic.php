<?php

namespace Newspack\MigrationTools\Util;

use WP_Error;

/**
 * Static only idea...
 */
class DependencyValidatorStatic {
    
    public static function is_active( $plugin_name ): bool|WP_Error {
        
        // Only run this function once, as long as argument(s) are the same.
        static $validated = [];

        if ( isset( $validated[ $plugin_name ] ) && true === $validated[ $plugin_name ] ) {
            return $validated[ $plugin_name ];
        }
        
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ( ! is_plugin_active(  $plugin_name ) ) {
            return new WP_Error( 'ERROR_PLUGIN_MISSING', 'Plugin not found. Install and activate it before using this function.' );
        } 

        $validated[ $plugin_name ] = true;

        return $validated[ $plugin_name ];

    }

}