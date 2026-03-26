<?php
/**
 * Email Sender Unit Tests
 *
 * @package Bs_Custom_Mail
 */

use PHPUnit\Framework\TestCase;

/**
 * Class EmailSenderTest
 */
class EmailSenderTest extends TestCase {

    /**
     * Email sender instance
     */
    protected $email_sender;

    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        $this->email_sender = new Bs_Custom_Mail_Email_Sender('bs-custom-mail', '2.0.0');
    }

    /**
     * Test get file icon
     */
    public function test_get_file_icon() {
        $reflection = new ReflectionClass($this->email_sender);
        $method = $reflection->getMethod('get_file_icon');

        $icons = array(
            'application/pdf' => '📄',
            'application/msword' => '📝',
            'application/zip' => '📦',
            'image/jpeg' => '🖼️',
            'video/mp4' => '🎬',
            'unknown/type' => '📎',
        );

        foreach ($icons as $mime => $expected) {
            $result = $method->invoke($this->email_sender, $mime);
            $this->assertEquals($expected, $result);
        }
    }

    /**
     * Test product to template matching
     */
    public function test_match_product_to_template() {
        $reflection = new ReflectionClass($this->email_sender);
        $method = $reflection->getMethod('match_product_to_template');

        $mappings = array(
            'SBF See Kurs' => 'sbf_see',
            'sbf binnen see kombi' => 'sbf_kombi',
            'UBi SRC Kombi Kurs' => 'ubi_src_kombi',
            'SRC Funkzeugnis' => 'src_funkzeugnis',
            'Gutschein Bootsschule' => 'gutschein',
            'Unbekanntes Produkt' => false,
        );

        foreach ($mappings as $product_name => $expected) {
            $result = $method->invoke($this->email_sender, $product_name);
            $this->assertEquals($expected, $result);
        }
    }
}
