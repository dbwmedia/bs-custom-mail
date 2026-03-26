<?php
/**
 * Template Unit Tests
 *
 * @package Bs_Custom_Mail
 */

use PHPUnit\Framework\TestCase;

/**
 * Class TemplateTest
 */
class TemplateTest extends TestCase {

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
     * Test template data structure
     */
    public function test_template_data_structure() {
        $template = array(
            'template_key' => 'sbf_see',
            'template_name' => 'SBF See',
            'subject' => 'Ihre Kursbuchung',
            'header_text' => '<h1>Willkommen</h1>',
            'content' => '<p>Ihr Kurs wurde gebucht.</p>',
            'footer_text' => '<p>Mit freundlichen Grüßen</p>',
            'attachments' => array(),
            'is_active' => true,
        );

        $this->assertArrayHasKey('template_key', $template);
        $this->assertArrayHasKey('template_name', $template);
        $this->assertArrayHasKey('subject', $template);
        $this->assertArrayHasKey('is_active', $template);
        
        $this->assertIsString($template['template_key']);
        $this->assertIsBool($template['is_active']);
    }

    /**
     * Test template attachments
     */
    public function test_template_attachments() {
        $attachments = array(1, 2, 3);
        $attachments_string = implode(',', $attachments);
        
        $this->assertEquals('1,2,3', $attachments_string);
        
        // Test parsing back
        $parsed = array_map('intval', explode(',', $attachments_string));
        $this->assertEquals($attachments, $parsed);
    }
}
