<?php
/**
 * Plugin Activator
 *
 * @package ChromaQAReports
 */

namespace ChromaQA;

/**
 * Handles plugin activation tasks.
 */
class Activator {

    /**
     * Run activation tasks.
     */
    public static function activate() {
        self::create_tables();
        self::create_roles();
        self::set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag for admin notice
        set_transient( 'cqa_activation_notice', true, 30 );
    }

    /**
     * Create custom database tables.
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix . 'cqa_';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Schools table
        $sql_schools = "CREATE TABLE {$prefix}schools (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            location VARCHAR(255) DEFAULT '',
            region VARCHAR(100) DEFAULT '',
            acquired_date DATE DEFAULT NULL,
            status VARCHAR(50) DEFAULT 'active',
            drive_folder_id VARCHAR(100) DEFAULT '',
            classroom_config LONGTEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status),
            KEY region (region)
        ) $charset_collate;";

        // Reports table
        $sql_reports = "CREATE TABLE {$prefix}reports (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            school_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            report_type ENUM('new_acquisition', 'tier1', 'tier1_tier2') NOT NULL DEFAULT 'tier1',
            inspection_date DATE NOT NULL,
            previous_report_id BIGINT(20) UNSIGNED DEFAULT NULL,
            overall_rating ENUM('exceeds', 'meets', 'needs_improvement', 'pending') DEFAULT 'pending',
            closing_notes LONGTEXT DEFAULT '',
            status VARCHAR(50) DEFAULT 'draft',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY school_id (school_id),
            KEY user_id (user_id),
            KEY report_type (report_type),
            KEY status (status),
            KEY inspection_date (inspection_date)
        ) $charset_collate;";

        // Checklist responses table
        $sql_responses = "CREATE TABLE {$prefix}responses (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id BIGINT(20) UNSIGNED NOT NULL,
            section_key VARCHAR(100) NOT NULL,
            item_key VARCHAR(100) NOT NULL,
            rating ENUM('yes', 'sometimes', 'no', 'na') DEFAULT 'na',
            notes LONGTEXT DEFAULT '',
            evidence_type VARCHAR(50) DEFAULT 'observation',
            previous_rating VARCHAR(20) DEFAULT '',
            previous_notes LONGTEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_id (report_id),
            KEY section_key (section_key)
        ) $charset_collate;";

        // Photos table
        $sql_photos = "CREATE TABLE {$prefix}photos (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id BIGINT(20) UNSIGNED NOT NULL,
            section_key VARCHAR(100) DEFAULT '',
            item_key VARCHAR(100) DEFAULT '',
            location_tag VARCHAR(100) DEFAULT '',
            drive_file_id VARCHAR(100) NOT NULL,
            filename VARCHAR(255) DEFAULT '',
            caption TEXT DEFAULT '',
            has_markup TINYINT(1) DEFAULT 0,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_id (report_id),
            KEY section_key (section_key),
            KEY item_key (item_key),
            KEY location_tag (location_tag)
        ) $charset_collate;";

        // AI Summaries table
        $sql_ai_summaries = "CREATE TABLE {$prefix}ai_summaries (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id BIGINT(20) UNSIGNED NOT NULL,
            executive_summary LONGTEXT DEFAULT '',
            issues_json LONGTEXT DEFAULT '',
            poi_json LONGTEXT DEFAULT '',
            comparison_json LONGTEXT DEFAULT '',
            generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY report_id (report_id)
        ) $charset_collate;";

        // Run table creation
        dbDelta( $sql_schools );
        dbDelta( $sql_reports );
        dbDelta( $sql_responses );
        dbDelta( $sql_photos );
        dbDelta( $sql_ai_summaries );

        // Store DB version
        update_option( 'cqa_db_version', CQA_VERSION );
    }

    /**
     * Create custom user roles and capabilities.
     */
    private static function create_roles() {
        // QA Super Admin
        add_role(
            'cqa_super_admin',
            __( 'QA Super Admin', 'chroma-qa-reports' ),
            [
                'read'                    => true,
                'cqa_manage_settings'     => true,
                'cqa_manage_users'        => true,
                'cqa_manage_schools'      => true,
                'cqa_view_all_reports'    => true,
                'cqa_create_reports'      => true,
                'cqa_edit_all_reports'    => true,
                'cqa_delete_reports'      => true,
                'cqa_export_reports'      => true,
                'cqa_use_ai_features'     => true,
            ]
        );

        // Regional Director
        add_role(
            'cqa_regional_director',
            __( 'QA Regional Director', 'chroma-qa-reports' ),
            [
                'read'                    => true,
                'cqa_manage_schools'      => true,
                'cqa_view_all_reports'    => true,
                'cqa_create_reports'      => true,
                'cqa_edit_own_reports'    => true,
                'cqa_export_reports'      => true,
                'cqa_use_ai_features'     => true,
            ]
        );

        // QA Officer
        add_role(
            'cqa_qa_officer',
            __( 'QA Officer', 'chroma-qa-reports' ),
            [
                'read'                    => true,
                'cqa_view_all_reports'    => true,
                'cqa_create_reports'      => true,
                'cqa_edit_own_reports'    => true,
                'cqa_export_reports'      => true,
                'cqa_use_ai_features'     => true,
            ]
        );

        // Program Manager (view only for their school)
        add_role(
            'cqa_program_manager',
            __( 'QA Program Manager', 'chroma-qa-reports' ),
            [
                'read'                    => true,
                'cqa_view_own_reports'    => true,
                'cqa_export_reports'      => true,
            ]
        );

        // Add capabilities to administrator
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $admin->add_cap( 'cqa_manage_settings' );
            $admin->add_cap( 'cqa_manage_users' );
            $admin->add_cap( 'cqa_manage_schools' );
            $admin->add_cap( 'cqa_view_all_reports' );
            $admin->add_cap( 'cqa_create_reports' );
            $admin->add_cap( 'cqa_edit_all_reports' );
            $admin->add_cap( 'cqa_delete_reports' );
            $admin->add_cap( 'cqa_export_reports' );
            $admin->add_cap( 'cqa_use_ai_features' );
        }
    }

    /**
     * Set default plugin options.
     */
    private static function set_default_options() {
        $defaults = [
            'google_client_id'     => '',
            'google_client_secret' => '',
            'gemini_api_key'       => '',
            'drive_root_folder'    => '',
            'company_name'         => 'Chroma Early Learning Academy',
            'reports_per_school'   => 2,
        ];

        foreach ( $defaults as $key => $value ) {
            if ( get_option( "cqa_{$key}" ) === false ) {
                add_option( "cqa_{$key}", $value );
            }
        }
    }
}
