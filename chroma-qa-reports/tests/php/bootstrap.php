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
define('ARRAY_A', 'ARRAY_A');

// Load Composer autoloader
require_once CQA_PLUGIN_DIR . 'vendor/autoload.php';

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        private $data;

        public function __construct($code = '', $message = '', $data = []) {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code() { return $this->code; }
        public function get_error_message() { return $this->message; }
        public function get_error_data() { return $this->data; }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        private $data;
        private $status;

        public function __construct($data = null, $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }

        public function get_data() { return $this->data; }
        public function get_status() { return $this->status; }
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request implements ArrayAccess {
        private $params;
        private $files;

        public function __construct($params = [], $files = []) {
            $this->params = $params;
            $this->files = $files;
        }

        public function get_param($key) { return $this->params[$key] ?? null; }
        public function get_params() { return $this->params; }
        public function has_param($key) { return array_key_exists($key, $this->params); }
        public function get_file_params() { return $this->files; }
        public function offsetExists($offset): bool { return array_key_exists($offset, $this->params); }
        public function offsetGet($offset): mixed { return $this->params[$offset] ?? null; }
        public function offsetSet($offset, $value): void { $this->params[$offset] = $value; }
        public function offsetUnset($offset): void { unset($this->params[$offset]); }
    }
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server {
        public const READABLE = 'GET';
        public const CREATABLE = 'POST';
        public const EDITABLE = 'PUT,PATCH';
        public const DELETABLE = 'DELETE';
    }
}

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

if (!function_exists('absint')) {
    function absint($value) {
        return abs((int) $value);
    }
}

if (!function_exists('wp_list_pluck')) {
    function wp_list_pluck($list, $field) {
        return array_map(static function($item) use ($field) {
            return is_object($item) ? $item->{$field} : $item[$field];
        }, $list);
    }
}

require_once CQA_PLUGIN_DIR . 'includes/models/class-school.php';
require_once CQA_PLUGIN_DIR . 'includes/models/class-report.php';
require_once CQA_PLUGIN_DIR . 'includes/models/class-checklist-response.php';
require_once CQA_PLUGIN_DIR . 'includes/models/class-photo.php';
require_once CQA_PLUGIN_DIR . 'includes/auth/class-access-policy.php';
require_once CQA_PLUGIN_DIR . 'includes/api/class-rest-controller.php';
