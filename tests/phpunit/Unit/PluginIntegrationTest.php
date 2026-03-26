<?php
/**
 * Plugin Integration Tests
 *
 * @package Bs_Custom_Mail
 */

use PHPUnit\Framework\TestCase;

/**
 * Class PluginIntegrationTest
 */
class PluginIntegrationTest extends TestCase {

    /**
     * Test plugin constants are defined
     */
    public function test_plugin_constants_defined() {
        $this->assertTrue(defined('BS_CUSTOM_MAIL_VERSION'));
        $this->assertEquals('2.0.0', BS_CUSTOM_MAIL_VERSION);
    }

    /**
     * Test main plugin class exists
     */
    public function test_main_class_exists() {
        $this->assertTrue(class_exists('Bs_Custom_Mail'));
        $this->assertTrue(class_exists('Bs_Custom_Mail_REST_API'));
        $this->assertTrue(class_exists('Bs_Custom_Mail_Email_Sender'));
    }

    /**
     * Test REST API namespace
     */
    public function test_rest_api_namespace() {
        $rest_api = new Bs_Custom_Mail_REST_API();
        
        $reflection = new ReflectionClass($rest_api);
        $property = $reflection->getProperty('namespace');
        
        $this->assertEquals('bs-custom-mail/v1', $property->getValue($rest_api));
    }
}
