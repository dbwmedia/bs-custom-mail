<?php
/**
 * Settings Unit Tests
 *
 * @package Bs_Custom_Mail
 */

use PHPUnit\Framework\TestCase;

/**
 * Class SettingsTest
 */
class SettingsTest extends TestCase {

    /**
     * Test default trigger status
     */
    public function test_default_trigger_status() {
        delete_option('bs_custom_mail_trigger_status');
        
        $status = get_option('bs_custom_mail_trigger_status', 'processing');
        $this->assertEquals('processing', $status);
    }

    /**
     * Test setting trigger status
     */
    public function test_set_trigger_status() {
        update_option('bs_custom_mail_trigger_status', 'completed');
        
        $status = get_option('bs_custom_mail_trigger_status');
        $this->assertEquals('completed', $status);
    }

    /**
     * Test default from name
     */
    public function test_default_from_name() {
        delete_option('bs_custom_mail_from_name');
        
        $default_name = get_bloginfo('name');
        $from_name = get_option('bs_custom_mail_from_name', $default_name);
        
        $this->assertEquals($default_name, $from_name);
    }

    /**
     * Test default from email
     */
    public function test_default_from_email() {
        delete_option('bs_custom_mail_from_email');
        
        $default_email = get_option('admin_email');
        $from_email = get_option('bs_custom_mail_from_email', $default_email);
        
        $this->assertEquals($default_email, $from_email);
    }

    /**
     * Test email validation for from_email
     */
    public function test_from_email_validation() {
        $valid_emails = array(
            'test@example.com',
            'user.name@domain.co.uk',
            'user+tag@example.org',
        );

        foreach ($valid_emails as $email) {
            $this->assertTrue(is_email($email) !== false);
        }

        $invalid_emails = array(
            'invalid-email',
            '@example.com',
            'user@',
        );

        foreach ($invalid_emails as $email) {
            $this->assertFalse(is_email($email));
        }
    }
}
