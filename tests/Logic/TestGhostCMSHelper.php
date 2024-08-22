<?php

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\MigrationTools\Logic\CoAuthorsPlusHelper;
use Newspack\MigrationTools\Logic\GhostCMSHelper;
use WP_UnitTestCase;

class TestGhostCMSHelper extends WP_UnitTestCase {

	private string $cap_plugin_slug = 'co-authors-plus';

	private string $cap_plugin_slug_with_file = 'co-authors-plus/co-authors-plus.php';
	
	/**
	 * Test importing a JSON file will and create posts, etc.
	 *
	 * @return void
	 */
	public function test_ghostcms_import(): void {

		// update wordpresstest.wptests_options 
		// set option_value = 'a:1:{i:0;s:35:"co-authors-plus/co-authors-plus.php";}'
		// where option_name = 'active_plugins';

		$this->install_plugin( $this->cap_plugin_slug );
		activate_plugins( array( $this->cap_plugin_slug_with_file ) );

		// Load custom taxonomy.
		$cap = new CoAuthorsPlusHelper();
		$cap->coauthors_plus->action_init_late();

		$pos_args = array();

		$assoc_args = array(
			'json-file'       => 'tests/fixtures/ghostcms.json',
			'ghost-url'       => 'https://newspack.com/',
			'default-user-id' => 1,
		);

		$log_file = get_temp_dir() . str_replace( __NAMESPACE__ . '\\', '', __CLASS__ ) . '_' . __FUNCTION__ . '.log';

		// ob_start();

		( new GhostCMSHelper() )->ghostcms_import( $pos_args, $assoc_args, $log_file );
		
		// $results = ob_get_clean();

		// echo $results;

		// temp logs

		// unlink( $log_file );
		// unlink( $log_file . '-skips.log' );
		
		// delete_plugins( array( $this->cap_plugin_slug_with_file ) );

		// $this->assertIsInt( $attachment_id );
		// $file_path = get_attached_file( $attachment_id );
		// $this->assertFileExists( $file_path );

		// $attachment_post = get_post( $attachment_id );
		// $this->assertEquals( $post_title, $attachment_post->post_title );
		// $this->assertEquals( $post_excerpt, $attachment_post->post_excerpt );
		// $this->assertEquals( $post_content, $attachment_post->post_content );
	}

	private function install_plugin( string $plugin_slug ): void {

		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_slug ) ) {
			
			include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

			// Needed to stop output.  Ob_start does not work with Plugin Upgrader. See also: $skin->get_upgrade_messages();
			$skin = new \Automatic_Upgrader_Skin();

			$plugin_uprader = new \Plugin_Upgrader( $skin );
			$plugin_uprader->install( 'https://downloads.wordpress.org/plugin/' . $plugin_slug . '.zip' );
			
		}
	}
}
