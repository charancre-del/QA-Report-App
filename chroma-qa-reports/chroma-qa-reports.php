<?php
/**
 * Plugin Name: Chroma QA Reports
 * Plugin URI: https://chromaearlylearning.com/qa-reports
 * Description: Quality Assurance Report Management System for Chroma Early Learning Academy schools.
 * Version: 1.0.0
 * Author: Chroma Early Learning Academy
 * Author URI: https://chromaearlylearning.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: chroma-qa-reports
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package ChromaQAReports
 */

namespace ChromaQA;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'CQA_VERSION', '1.0.0' );
define( 'CQA_PLUGIN_FILE', __FILE__ );
define( 'CQA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CQA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CQA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes.
 *
 * @param string $class The fully-qualified class name.
 */
spl_autoload_register( function ( $class ) {
    // Project-specific namespace prefix
    $prefix = 'ChromaQA\\';

    // Base directory for the namespace prefix
    $base_dir = CQA_PLUGIN_DIR . 'includes/';

    // Does the class use the namespace prefix?
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    // Get the relative class name
    $relative_class = substr( $class, $len );

    // Convert namespace separators to directory separators
    // and convert to lowercase with class- prefix
    $path_parts = explode( '\\', $relative_class );
    $class_file = 'class-' . strtolower( str_replace( '_', '-', array_pop( $path_parts ) ) ) . '.php';
    
    // Build the file path
    $file = $base_dir;
    if ( ! empty( $path_parts ) ) {
        $file .= strtolower( implode( '/', $path_parts ) ) . '/';
    }
    $file .= $class_file;

    // If the file exists, require it
    if ( file_exists( $file ) ) {
        require $file;
    }
});

/**
 * Plugin activation hook.
 */
function activate_chroma_qa_reports() {
    require_once CQA_PLUGIN_DIR . 'includes/class-activator.php';
    Activator::activate();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate_chroma_qa_reports' );

/**
 * Plugin deactivation hook.
 */
function deactivate_chroma_qa_reports() {
    require_once CQA_PLUGIN_DIR . 'includes/class-deactivator.php';
    Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate_chroma_qa_reports' );

/**
 * Initialize the plugin.
 */
function run_chroma_qa_reports() {
    $plugin = new Plugin();
    $plugin->run();
}

// Start the plugin
add_action( 'plugins_loaded', __NAMESPACE__ . '\\run_chroma_qa_reports' );
