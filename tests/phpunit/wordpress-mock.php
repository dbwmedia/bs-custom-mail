<?php
/**
 * WordPress Mock Functions for Unit Testing
 */

define('WPINC', 'wp-includes');
define('ABSPATH', dirname(dirname(dirname(__FILE__))) . '/');

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { return true; }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) { return true; }
}

if (!function_exists('has_action')) {
    function has_action($hook, $callback = false) { return false; }
}

if (!function_exists('has_filter')) {
    function has_filter($hook, $callback = false) { return false; }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {}
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value, ...$args) { return $value; }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) { return true; }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback) { return true; }
}

if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('esc_url')) {
    function esc_url($url) { return filter_var($url, FILTER_SANITIZE_URL); }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return trim(strip_tags($str)); }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) { return filter_var($email, FILTER_SANITIZE_EMAIL); }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($content) {
        return strip_tags($content, '<p><br><strong><em><a><ul><ol><li><h1><h2><h3><h4><h5><h6>');
    }
}

if (!function_exists('is_email')) {
    function is_email($email) { return filter_var($email, FILTER_VALIDATE_EMAIL); }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        return $type === 'mysql' ? date('Y-m-d H:i:s') : time();
    }
}

if (!function_exists('get_option')) {
    global $_wp_options;
    $_wp_options = array();
    function get_option($option, $default = false) {
        global $_wp_options;
        return isset($_wp_options[$option]) ? $_wp_options[$option] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        global $_wp_options;
        $_wp_options[$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        global $_wp_options;
        unset($_wp_options[$option]);
        return true;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '', $filter = 'raw') {
        $info = array('name' => 'Test Blog', 'url' => 'http://example.com', 'admin_email' => 'admin@example.com');
        return isset($info[$show]) ? $info[$show] : '';
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') { return 'http://example.com' . $path; }
}

if (!function_exists('site_url')) {
    function site_url($path = '') { return 'http://example.com/wp' . $path; }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') { return 'http://example.com/wp-admin/' . $path; }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) { return dirname($file) . '/'; }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) { return 'http://example.com/wp-content/plugins/test/'; }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) { return 'test-nonce'; }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) { return 1; }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) { return true; }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        return (object) array('user_email' => 'test@example.com', 'ID' => 1);
    }
}

if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
        return true;
    }
}

if (!function_exists('get_attached_file')) {
    function get_attached_file($attachment_id) {
        return '/tmp/test-file.pdf';
    }
}

if (!function_exists('wp_mime_type_icon')) {
    function wp_mime_type_icon($mime) {
        return 'http://example.com/wp-includes/images/media/document.png';
    }
}

if (!function_exists('size_format')) {
    function size_format($bytes, $decimals = 0) {
        $units = array('B', 'KB', 'MB', 'GB');
        $unit = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $unit), $decimals) . ' ' . $units[$unit];
    }
}

if (!function_exists('get_post')) {
    function get_post($post = null, $output = OBJECT, $filter = 'raw') {
        return (object) array('ID' => 1, 'post_title' => 'Test Post');
    }
}

if (!function_exists('get_post_mime_type')) {
    function get_post_mime_type($ID = '') {
        return 'application/pdf';
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = '') {
        if (is_object($args)) {
            $parsed = get_object_vars($args);
        } elseif (is_array($args)) {
            $parsed =& $args;
        } else {
            parse_str($args, $parsed);
        }
        if (is_array($defaults)) {
            return array_merge($defaults, $parsed);
        }
        return $parsed;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') { return $text; }
}

// Define plugin constant
if (!defined('BS_CUSTOM_MAIL_VERSION')) {
    define('BS_CUSTOM_MAIL_VERSION', '2.0.0');
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') { echo $text; }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = 'default') { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('human_time_diff')) {
    function human_time_diff($from, $to = '') {
        if (empty($to)) {
            $to = time();
        }
        $diff = (int) abs($to - $from);
        if ($diff < HOUR_IN_SECONDS) {
            $mins = round($diff / MINUTE_IN_SECONDS);
            return $mins . ' minutes';
        }
        return '1 hour';
    }
}

define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('WEEK_IN_SECONDS', 604800);
define('MONTH_IN_SECONDS', 2592000);
define('YEAR_IN_SECONDS', 31536000);

// Load plugin files
require_once dirname(dirname(dirname(__FILE__))) . '/includes/class-bs-custom-mail.php';
require_once dirname(dirname(dirname(__FILE__))) . '/includes/class-bs-custom-mail-rest-api.php';
require_once dirname(dirname(dirname(__FILE__))) . '/includes/class-bs-custom-mail-email-sender.php';
