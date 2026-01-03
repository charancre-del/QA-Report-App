<?php
/**
 * Admin Menu
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Admin;

/**
 * Handles admin menu registration and page rendering.
 */
class Admin_Menu {

    /**
     * Menu slug.
     *
     * @var string
     */
    const MENU_SLUG = 'chroma-qa-reports';

    /**
     * Register admin menu.
     */
    public function register_menu() {
        // Main menu
        add_menu_page(
            __( 'QA Reports', 'chroma-qa-reports' ),
            __( 'QA Reports', 'chroma-qa-reports' ),
            'cqa_view_own_reports',
            self::MENU_SLUG,
            [ $this, 'render_dashboard' ],
            'dashicons-clipboard',
            30
        );

        // Dashboard (same as main)
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Dashboard', 'chroma-qa-reports' ),
            __( 'Dashboard', 'chroma-qa-reports' ),
            'cqa_view_own_reports',
            self::MENU_SLUG,
            [ $this, 'render_dashboard' ]
        );

        // Schools
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Schools', 'chroma-qa-reports' ),
            __( 'Schools', 'chroma-qa-reports' ),
            'cqa_manage_schools',
            self::MENU_SLUG . '-schools',
            [ $this, 'render_schools' ]
        );

        // All Reports
        add_submenu_page(
            self::MENU_SLUG,
            __( 'All Reports', 'chroma-qa-reports' ),
            __( 'All Reports', 'chroma-qa-reports' ),
            'cqa_view_all_reports',
            self::MENU_SLUG . '-reports',
            [ $this, 'render_reports' ]
        );

        // Create New Report
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Create Report', 'chroma-qa-reports' ),
            __( 'Create Report', 'chroma-qa-reports' ),
            'cqa_create_reports',
            self::MENU_SLUG . '-create',
            [ $this, 'render_create_report' ]
        );

        // Settings (admin only)
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Settings', 'chroma-qa-reports' ),
            __( 'Settings', 'chroma-qa-reports' ),
            'cqa_manage_settings',
            self::MENU_SLUG . '-settings',
            [ $this, 'render_settings' ]
        );

        // Help & Guide
        add_submenu_page(
            self::MENU_SLUG,
            __( 'Help & Guide', 'chroma-qa-reports' ),
            __( 'Help & Guide', 'chroma-qa-reports' ),
            'read', // Available to all users
            self::MENU_SLUG . '-docs',
            [ $this, 'render_documentation' ]
        );

        // Hidden pages (edit/view)
        add_submenu_page(
            null, // Hidden
            __( 'View Report', 'chroma-qa-reports' ),
            __( 'View Report', 'chroma-qa-reports' ),
            'cqa_view_own_reports',
            self::MENU_SLUG . '-view',
            [ $this, 'render_view_report' ]
        );

        add_submenu_page(
            null,
            __( 'Edit School', 'chroma-qa-reports' ),
            __( 'Edit School', 'chroma-qa-reports' ),
            'cqa_manage_schools',
            self::MENU_SLUG . '-school-edit',
            [ $this, 'render_school_edit' ]
        );
    }

    /**
     * Enqueue admin styles.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_styles( $hook ) {
        // Only load on our pages
        if ( strpos( $hook, self::MENU_SLUG ) === false ) {
            return;
        }

        wp_enqueue_style(
            'cqa-admin-styles',
            CQA_PLUGIN_URL . 'admin/css/admin-styles.css',
            [],
            CQA_VERSION
        );

        // Mobile styles (Phase 14)
        wp_enqueue_style(
            'cqa-mobile-styles',
            CQA_PLUGIN_URL . 'admin/css/mobile-styles.css',
            [ 'cqa-admin-styles' ],
            CQA_VERSION
        );

        // Google Fonts
        wp_enqueue_style(
            'cqa-google-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
            [],
            null
        );
    }

    /**
     * Enqueue admin scripts.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_scripts( $hook ) {
        if ( strpos( $hook, self::MENU_SLUG ) === false ) {
            return;
        }

        wp_enqueue_script(
            'cqa-admin-scripts',
            CQA_PLUGIN_URL . 'admin/js/admin-scripts.js',
            [ 'jquery' ],
            CQA_VERSION,
            true
        );

        wp_localize_script( 'cqa-admin-scripts', 'cqaAdmin', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'restUrl'   => rest_url( 'cqa/v1/' ),
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'strings'   => [
                'confirm_delete' => __( 'Are you sure you want to delete this?', 'chroma-qa-reports' ),
                'saving'         => __( 'Saving...', 'chroma-qa-reports' ),
                'saved'          => __( 'Saved!', 'chroma-qa-reports' ),
                'error'          => __( 'An error occurred.', 'chroma-qa-reports' ),
            ],
        ] );

        // Report wizard script on create page
        if ( strpos( $hook, 'create' ) !== false ) {
            wp_enqueue_script(
                'cqa-report-wizard',
                CQA_PLUGIN_URL . 'admin/js/report-wizard.js',
                [ 'jquery', 'cqa-admin-scripts' ],
                CQA_VERSION,
                true
            );

            // Phase 12: Duplicate report & keyboard navigation
            wp_enqueue_script(
                'cqa-duplicate-report',
                CQA_PLUGIN_URL . 'admin/js/duplicate-report.js',
                [ 'jquery', 'cqa-admin-scripts' ],
                CQA_VERSION,
                true
            );

            wp_enqueue_script(
                'cqa-keyboard-nav',
                CQA_PLUGIN_URL . 'admin/js/keyboard-nav.js',
                [ 'jquery', 'cqa-admin-scripts' ],
                CQA_VERSION,
                true
            );

            // Phase 14: Voice to text & offline
            wp_enqueue_script(
                'cqa-voice-to-text',
                CQA_PLUGIN_URL . 'admin/js/voice-to-text.js',
                [ 'jquery', 'cqa-admin-scripts' ],
                CQA_VERSION,
                true
            );

            wp_enqueue_script(
                'cqa-offline-manager',
                CQA_PLUGIN_URL . 'admin/js/offline-manager.js',
                [ 'jquery', 'cqa-admin-scripts' ],
                CQA_VERSION,
                true
            );

            // Phase 15: Photo annotator
            wp_enqueue_script(
                'cqa-photo-annotator',
                CQA_PLUGIN_URL . 'admin/js/photo-annotator.js',
                [ 'jquery', 'cqa-admin-scripts' ],
                CQA_VERSION,
                true
            );

            // Item-level photo uploader
            wp_enqueue_script(
                'cqa-item-photo-uploader',
                CQA_PLUGIN_URL . 'admin/js/item-photo-uploader.js',
                [ 'jquery', 'cqa-admin-scripts' ],
                CQA_VERSION,
                true
            );

            // Localize with admin URL for all scripts
            wp_localize_script( 'cqa-report-wizard', 'cqaAdmin', [
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'adminUrl'  => admin_url( 'admin.php' ),
                'restUrl'   => rest_url( 'cqa/v1/' ),
                'nonce'     => wp_create_nonce( 'wp_rest' ),
                'pluginUrl' => CQA_PLUGIN_URL,
                'strings'   => [
                    'confirm_delete' => __( 'Are you sure you want to delete this?', 'chroma-qa-reports' ),
                    'saving'         => __( 'Saving...', 'chroma-qa-reports' ),
                    'saved'          => __( 'Saved!', 'chroma-qa-reports' ),
                    'error'          => __( 'An error occurred.', 'chroma-qa-reports' ),
                    'unsaved_warning' => __( 'You have unsaved changes. Are you sure you want to leave?', 'chroma-qa-reports' ),
                ],
            ] );
        }

        // Phase 15: School map on dashboard
        if ( strpos( $hook, self::MENU_SLUG ) !== false && strpos( $hook, 'create' ) === false ) {
            // Google Maps API (only if key configured)
            $maps_key = get_option( 'cqa_google_maps_api_key', '' );
            if ( $maps_key ) {
                wp_enqueue_script(
                    'google-maps',
                    'https://maps.googleapis.com/maps/api/js?key=' . esc_attr( $maps_key ),
                    [],
                    null,
                    true
                );

                wp_enqueue_script(
                    'cqa-school-map',
                    CQA_PLUGIN_URL . 'admin/js/school-map.js',
                    [ 'jquery', 'google-maps', 'cqa-admin-scripts' ],
                    CQA_VERSION,
                    true
                );
            }
        }
    }

    /**
     * Render dashboard page.
     */
    public function render_dashboard() {
        include CQA_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Render schools list page.
     */
    public function render_schools() {
        include CQA_PLUGIN_DIR . 'admin/views/schools-list.php';
    }

    /**
     * Render school edit page.
     */
    public function render_school_edit() {
        include CQA_PLUGIN_DIR . 'admin/views/school-edit.php';
    }

    /**
     * Render reports list page.
     */
    public function render_reports() {
        include CQA_PLUGIN_DIR . 'admin/views/reports-list.php';
    }

    /**
     * Render create report page.
     */
    public function render_create_report() {
        include CQA_PLUGIN_DIR . 'admin/views/report-create.php';
    }

    /**
     * Render view report page.
     */
    public function render_view_report() {
        include CQA_PLUGIN_DIR . 'admin/views/report-view.php';
    }

    /**
     * Render documentation page.
     */
    public function render_documentation() {
        include CQA_PLUGIN_DIR . 'admin/views/documentation.php';
    }

    /**
     * Render settings page.
     */
    public function render_settings() {
        // Handle form submission
        if ( isset( $_POST['cqa_settings_nonce'] ) && wp_verify_nonce( $_POST['cqa_settings_nonce'], 'cqa_save_settings' ) ) {
            $this->save_settings();
        }

        include CQA_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Save settings.
     */
    private function save_settings() {
        $fields = [
            'google_client_id',
            'google_client_secret',
            'sso_domain',
            'sso_default_role',
            'gemini_api_key',
            'drive_root_folder',
            'company_name',
            'google_maps_api_key',
        ];

        foreach ( $fields as $field ) {
            if ( isset( $_POST[ "cqa_{$field}" ] ) ) {
                $value = sanitize_text_field( $_POST[ "cqa_{$field}" ] );
                
                // Special handling for domain
                if ( $field === 'sso_domain' ) {
                    $value = preg_replace( '#^https?://(?:www\.)?#i', '', $value );
                }
                
                update_option( "cqa_{$field}", $value );
            }
        }

        add_settings_error(
            'cqa_settings',
            'settings_saved',
            __( 'Settings saved successfully.', 'chroma-qa-reports' ),
            'success'
        );
    }
}
