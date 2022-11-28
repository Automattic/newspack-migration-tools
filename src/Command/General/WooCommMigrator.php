<?php

namespace NewspackCustomContentMigrator\Command\General;

use \NewspackCustomContentMigrator\Command\InterfaceCommand;
use \NewspackCustomContentMigrator\Command\General\PostsMigrator;
use \WP_CLI;
use \WP_Error;

class WooCommMigrator implements InterfaceCommand {

	/**
	 * @var null|InterfaceCommand Instance.
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 */
	private function __construct() {
	}

	/**
	 * Singleton get_instance().
	 *
	 * @return InterfaceCommand|null
	 */
	public static function get_instance() {
		$class = get_called_class();
		if ( null === self::$instance ) {
			self::$instance = new $class;
		}

		return self::$instance;
	}

	/**
	 * See InterfaceCommand::register_commands.
	 */
	public function register_commands() {
		WP_CLI::add_command( 'newspack-content-migrator woocomm-setup', array( $this, 'cmd_setup' ), [
			'shortdesc' => 'Updates all the WooCommerce settings.',
		] );
	}

	/**
	 * Callable for woocomm-setup command.
	 *
	 * @param $args
	 * @param $assoc_args
	 */
	public function cmd_setup( $args, $assoc_args ) {
		WP_CLI::success( 'Updating WooComm Pages IDs...' );
		$this->update_woocomm_pages_ids_after_import();

		WP_CLI::success( 'Configuring WooComm for checkout without login...' );
		$this->woocomm_enable_checkout_without_login();

		WP_CLI::success( 'Disabling APM for Checkout page...' );
		$this->disable_amp_for_woocomm_checkout_page();

		WP_CLI::success( 'Done.' );
	}

	/**
	 * Enables checkout for non-logged in users.
	 */
	public function woocomm_enable_checkout_without_login() {
		update_option( 'woocommerce_enable_guest_checkout', 'yes' );
		update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'yes' );
	}

	/**
	 * Disables AMP for the WooCommerce Checkout page.
	 */
	public function disable_amp_for_woocomm_checkout_page() {
		$checkout_page_id = get_option( 'woocommerce_checkout_page_id' );
		if ( ! $checkout_page_id ) {
			return;
		}

		$this->disable_amp_for_post( $checkout_page_id );
	}

	/**
	 * Disables AMP for a specific page.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return null
	 */
	private function disable_amp_for_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		update_post_meta( $post_id, 'amp_status', 'disabled' );
	}

	/**
	 * Updates WoComm's pages' IDs after they've been imported with new IDs.
	 */
	private function update_woocomm_pages_ids_after_import() {
		$option_names = array(
			'woocommerce_shop_page_id',
			'woocommerce_cart_page_id',
			'woocommerce_checkout_page_id',
			'woocommerce_myaccount_page_id',
			'woocommerce_terms_page_id',
		);

		$posts_migrator = PostsMigrator::get_instance();
		foreach ( $option_names as $option_name ) {
			$old_id = get_option( $option_name );
			if ( empty( $old_id ) ) {
				continue;
			}

			$current_id = $posts_migrator->get_current_post_id_from_original_post_id( $old_id );
			if ( null !== $current_id ) {
				update_option( $option_name, $current_id );
			}
		}
	}
}
