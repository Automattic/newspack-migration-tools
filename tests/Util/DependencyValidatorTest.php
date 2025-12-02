<?php

namespace Newspack\MigrationTools\Tests\Util;

use Newspack\MigrationTools\Util\DependencyValidatorStatic;
use WP_UnitTestCase;

class DependencyValidatorTest extends WP_UnitTestCase {

	protected function setUp(): void {
		// parent::setUp();
		// No need for logging.
		// add_filter( 'newspack_migration_tools_log_file_logger_disable', '__return_true' );
		// add_filter( 'newspack_migration_tools_log_clilog_disable', '__return_true' );
	}

	public function test_static_idea(): void {

        $is_active = DependencyValidatorStatic::is_active( 'newspack-plugin/newspack.php' );
		$this->assertEquals( true, $is_active );
		
        $is_active = DependencyValidatorStatic::is_active( 'nothing/nothing.php' );
		$this->assertInstanceOf( \WP_Error::class, $is_active );
		$this->assertEquals( 'ERROR_PLUGIN_MISSING', $is_active->get_error_code() );

	}
}
