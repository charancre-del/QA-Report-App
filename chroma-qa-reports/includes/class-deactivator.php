<?php
/**
 * Plugin Deactivator
 *
 * @package ChromaQAReports
 */

namespace ChromaQA;

/**
 * Handles plugin deactivation tasks.
 */
class Deactivator {

    /**
     * Run deactivation tasks.
     */
    public static function deactivate() {
        // Clear scheduled hooks
        wp_clear_scheduled_hook( 'cqa_daily_cleanup' );
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
