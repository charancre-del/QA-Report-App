<?php
/**
 * PHPUnit Bootstrap File
 * 
 * Sets up the WordPress testing environment for PHPUnit tests.
 */

// Define test environment constants
define('CQA_TESTS_DIR', __DIR__);
define('CQA_PLUGIN_DIR', dirname(dirname(__DIR__)) . '/');
define('CQA_VERSION', '1.0.1');

// Load Composer autoloader
require_once CQA_PLUGIN_DIR . 'vendor/autoload.php';

// Bootstrap Brain Monkey for WordPress function mocking
\Brain\Monkey\setUp();

// Mock WordPress functions that are commonly used
if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return htmlspecialchars($text);
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return strip_tags($str);
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        return strip_tags($str);
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []) {
        if (is_object($args)) {
            $parsed_args = get_object_vars($args);
        } elseif (is_array($args)) {
            $parsed_args = &$args;
        } else {
            parse_str($args, $parsed_args);
        }
        
        if (is_array($defaults)) {
            return array_merge($defaults, $parsed_args);
        }
        return $parsed_args;
    }
}

// Register shutdown function to tear down Brain Monkey
register_shutdown_function(function() {
    \Brain\Monkey\tearDown();
});
