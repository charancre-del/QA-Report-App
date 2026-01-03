<?php
/**
 * Uninstall Script
 *
 * Runs when the plugin is deleted via WordPress admin.
 *
 * @package ChromaQAReports
 */

// Exit if accessed directly or not uninstalling
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Delete custom tables
$tables = [
    $wpdb->prefix . 'cqa_schools',
    $wpdb->prefix . 'cqa_reports',
    $wpdb->prefix . 'cqa_responses',
    $wpdb->prefix . 'cqa_photos',
    $wpdb->prefix . 'cqa_ai_summaries',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Delete options
$options = [
    'cqa_google_client_id',
    'cqa_google_client_secret',
    'cqa_gemini_api_key',
    'cqa_drive_root_folder',
    'cqa_company_name',
    'cqa_reports_per_school',
    'cqa_db_version',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove custom roles
remove_role( 'cqa_super_admin' );
remove_role( 'cqa_regional_director' );
remove_role( 'cqa_qa_officer' );
remove_role( 'cqa_program_manager' );

// Remove capabilities from administrator
$admin = get_role( 'administrator' );
if ( $admin ) {
    $caps = [
        'cqa_manage_settings',
        'cqa_manage_users',
        'cqa_manage_schools',
        'cqa_view_all_reports',
        'cqa_create_reports',
        'cqa_edit_all_reports',
        'cqa_delete_reports',
        'cqa_export_reports',
        'cqa_use_ai_features',
    ];
    
    foreach ( $caps as $cap ) {
        $admin->remove_cap( $cap );
    }
}

// Clean up user meta
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'cqa_%'" );

// Delete temp files
$upload_dir = wp_upload_dir();
$temp_dir = $upload_dir['basedir'] . '/cqa-temp';

if ( is_dir( $temp_dir ) ) {
    $files = glob( $temp_dir . '/*' );
    foreach ( $files as $file ) {
        if ( is_file( $file ) ) {
            unlink( $file );
        }
    }
    rmdir( $temp_dir );
}

// Flush rewrite rules
flush_rewrite_rules();
