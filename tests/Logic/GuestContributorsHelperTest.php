<?php

namespace Newspack\MigrationTools\Tests\Logic;

use Newspack\MigrationTools\Logic\GuestContributorsHelper;
use WP_Error;
use WP_UnitTestCase;

class GuestContributorsHelperTest extends WP_UnitTestCase {

    /**
	 * Test that the Newspack Plugin is installed and activated.
	 */
	public function test_newspack_plugin_activated() {
		$helper = new GuestContributorsHelper();
		$this->assertTrue( $helper->validate_newspack_plugin() );
	}

    /**
     * @dataProvider sanitizeForDbProvider
     */
    public function testSanitizeForDb($input, $expected) {
        $result = GuestContributorsHelper::sanitize_for_db($input);
        $this->assertMatchesRegularExpression($expected, $result);
    }

    public function sanitizeForDbProvider() {
        return [
            'empty string' => ['', '/^$/'],
            'html content' => ['&nbsp; <div>', '/^$/'],
            'normal name' => ['John Smith', '/^john-smith$/'],
            'accented chars' => ['José ', '/^jose$/'],
        ];
    }

    /**
     * @dataProvider usernameGenerationProvider
     */
    public function testUsernameGeneration($input, $expectedPattern, $expectError = false) {
        $result = GuestContributorsHelper::generate_username($input);
        
        if ($expectError) {
            $this->assertInstanceOf(WP_Error::class, $result);
            $this->assertEquals('ERROR_SANITIZE_INPUT', $result->get_error_code());
        } else {
            $this->assertMatchesRegularExpression($expectedPattern, $result);
        }
    }

    public function usernameGenerationProvider() {
        return [
            'empty string' => ['', '', true],
            'html content' => ['&nbsp; <div>', '', true],
            'normal name' => ['John Smith', '/^john-smith-[0-9]{5}$/'],
            'long name' => [str_repeat('José ', 13), '/^' . str_repeat('jose-', 11) . '[0-9]{5}$/'],
        ];
    }

    /**
     * @dataProvider emailGenerationProvider
     */
    public function testEmailGeneration($input, $expectedPattern, $expectError = false) {
        $result = GuestContributorsHelper::generate_email($input);
        
        if ($expectError) {
            $this->assertInstanceOf(WP_Error::class, $result);
            $this->assertEquals('ERROR_SANITIZE_INPUT', $result->get_error_code());
        } else {
            $this->assertMatchesRegularExpression($expectedPattern, $result);
        }
    }

    public function emailGenerationProvider() {
        return [
            'empty string' => ['', '', true],
            'html content' => ['&nbsp; <div>', '', true],
            'normal name' => ['John Smith', '/^john-smith-[0-9]{5}@example.com$/'],
            'long name' => [str_repeat('José ', 21), '/^' . str_repeat('jose-', 16) . 'jo-[0-9]{5}@example.com$/'],
        ];
    }

    /**
     * @dataProvider getByDisplayNameProvider
     */
    public function testGetByDisplayName($input, $expectedPattern) {
        $result = GuestContributorsHelper::get_by_display_name($input);
        if (is_array($result)) {
            $result = implode(',', $result);
        }
        $this->assertMatchesRegularExpression($expectedPattern, $result);
    }

    public function getByDisplayNameProvider() {
        return [
            'empty string' => ['', '/^$/'],
            'wildcard' => ['*', '/^$/'],
            'non-existent name' => ['John ' . microtime() . ' ' . wp_rand(11111, 99999), '/^$/'],
        ];
    }

    /**
     * @dataProvider createByDisplayNameProvider
     */
    public function testCreateByDisplayName($input, $force, $expectError = false) {
        $result = GuestContributorsHelper::create_by_display_name($input, $force);
        
        if ($expectError) {
            $this->assertInstanceOf(WP_Error::class, $result);
        } else {
            $this->assertMatchesRegularExpression('/^\d+$/', $result);
        }
    }

    public function createByDisplayNameProvider() {
        return [
            'empty string' => ['', false, true], // 'ERROR_DISPLAY_NAME' 
            'html content' => ['&nbsp; <div>', false, true], // 'ERROR_GENERATE_EMAIL'
        ];
    }

    public function testCompleteGuestContributorFlow() {
        $uniqueName = 'John ' . microtime() . ' ' . wp_rand(11111, 99999);
        
        // Initial get should return empty
        $result = GuestContributorsHelper::get_by_display_name($uniqueName);
        $this->assertMatchesRegularExpression('/^$/', implode(',', $result));
        
        // Create should succeed
        $result = GuestContributorsHelper::create_by_display_name($uniqueName);
        $this->assertMatchesRegularExpression('/^\d+$/', $result);
        
        // Get should now return the ID
        $result = GuestContributorsHelper::get_by_display_name($uniqueName);
        $this->assertMatchesRegularExpression('/^\d+$/', implode(',', $result));
        
        // Create without force should fail
        $result = GuestContributorsHelper::create_by_display_name($uniqueName);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('ERROR_EXISTING_USERS', $result->get_error_code());
        
        // Create with force should succeed
        $result = GuestContributorsHelper::create_by_display_name($uniqueName, true);
        $this->assertMatchesRegularExpression('/^\d+$/', $result);
        
        // Get should now return multiple IDs
        $result = GuestContributorsHelper::get_by_display_name($uniqueName);
        $this->assertMatchesRegularExpression('/^[\d,]+$/', implode(',', $result));
    }
}
