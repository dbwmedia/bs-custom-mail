<?php
/**
 * PHPUnit bootstrap file
 *
 * @package Bs_Custom_Mail
 */

// Load WordPress mocks
require_once __DIR__ . '/wordpress-mock.php';

// Composer autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Load WP PHPUnit Polyfills
require_once __DIR__ . '/../../vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

// Set test mode
define('WP_TESTS', true);
