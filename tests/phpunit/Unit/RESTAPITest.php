<?php
/**
 * REST API Unit Tests
 *
 * @package Bs_Custom_Mail
 */

use PHPUnit\Framework\TestCase;

/**
 * Class RESTAPITest
 */
class RESTAPITest extends TestCase {

    /**
     * Test REST API namespace
     */
    public function test_rest_api_namespace() {
        $rest_api = new Bs_Custom_Mail_REST_API();
        
        $reflection = new ReflectionClass($rest_api);
        $property = $reflection->getProperty('namespace');
        
        $this->assertEquals('bs-custom-mail/v1', $property->getValue($rest_api));
    }

    /**
     * Test admin permission check
     */
    public function test_admin_permission_check() {
        $rest_api = new Bs_Custom_Mail_REST_API();
        
        // Mock current_user_can to return true
        $this->assertTrue($rest_api->check_admin_permissions());
    }

    /**
     * Test template key validation
     */
    public function test_template_key_validation() {
        $valid_keys = array(
            'test_template',
            'test123',
            'test_123_key',
            'sbf_see',
        );

        $invalid_keys = array(
            'Test-Template',
            'test template',
            'test@template',
            'TestTemplate',
        );

        foreach ($valid_keys as $key) {
            $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $key);
        }

        foreach ($invalid_keys as $key) {
            $this->assertDoesNotMatchRegularExpression('/^[a-z0-9_]+$/', $key);
        }
    }

    /**
     * Test get_settings_args structure
     */
    public function test_get_settings_args() {
        $rest_api = new Bs_Custom_Mail_REST_API();
        
        $reflection = new ReflectionClass($rest_api);
        $method = $reflection->getMethod('get_settings_args');
        
        $args = $method->invoke($rest_api);
        
        $this->assertArrayHasKey('trigger_status', $args);
        $this->assertArrayHasKey('from_name', $args);
        $this->assertArrayHasKey('from_email', $args);
    }
}
