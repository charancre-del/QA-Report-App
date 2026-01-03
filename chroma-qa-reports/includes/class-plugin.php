<?php
/**
 * Core Plugin Class
 *
 * @package ChromaQAReports
 */

namespace ChromaQA;

/**
 * Main plugin class that initializes all components.
 */
class Plugin {

    /**
     * Plugin version.
     *
     * @var string
     */
    protected $version;

    /**
     * Admin instance.
     *
     * @var Admin\Admin_Menu
     */
    protected $admin;

    /**
     * REST API controller.
     *
     * @var API\REST_Controller
     */
    protected $api;

    /**
     * Initialize the plugin.
     */
    public function __construct() {
        $this->version = CQA_VERSION;
    }

    /**
     * Run the plugin.
     */
    public function run() {
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_api_hooks();
        $this->init_enhancements();
    }

    /**
     * Load required dependencies.
     */
    private function load_dependencies() {
        // Models
        require_once CQA_PLUGIN_DIR . 'includes/models/class-school.php';
        require_once CQA_PLUGIN_DIR . 'includes/models/class-report.php';
        require_once CQA_PLUGIN_DIR . 'includes/models/class-checklist-response.php';
        require_once CQA_PLUGIN_DIR . 'includes/models/class-photo.php';

        // Template model (Phase 12)
        if ( file_exists( CQA_PLUGIN_DIR . 'includes/models/class-template.php' ) ) {
            require_once CQA_PLUGIN_DIR . 'includes/models/class-template.php';
        }

        // Checklists
        require_once CQA_PLUGIN_DIR . 'includes/checklists/class-checklist-manager.php';
        require_once CQA_PLUGIN_DIR . 'includes/checklists/class-classroom-checklist.php';

        // Auth
        require_once CQA_PLUGIN_DIR . 'includes/auth/class-google-oauth.php';
        require_once CQA_PLUGIN_DIR . 'includes/auth/class-user-roles.php';

        // Integrations
        require_once CQA_PLUGIN_DIR . 'includes/integrations/class-google-drive.php';

        // AI
        require_once CQA_PLUGIN_DIR . 'includes/ai/class-gemini-client.php';
        require_once CQA_PLUGIN_DIR . 'includes/ai/class-document-parser.php';
        require_once CQA_PLUGIN_DIR . 'includes/ai/class-executive-summary.php';

        // AI Enhancements (Phase 16)
        if ( file_exists( CQA_PLUGIN_DIR . 'includes/ai/class-photo-analyzer.php' ) ) {
            require_once CQA_PLUGIN_DIR . 'includes/ai/class-photo-analyzer.php';
        }
        if ( file_exists( CQA_PLUGIN_DIR . 'includes/ai/class-comparative-insights.php' ) ) {
            require_once CQA_PLUGIN_DIR . 'includes/ai/class-comparative-insights.php';
        }

        // Export
        require_once CQA_PLUGIN_DIR . 'includes/export/class-pdf-generator.php';

        // Notifications (Phase 13)
        if ( file_exists( CQA_PLUGIN_DIR . 'includes/notifications/class-email-notifications.php' ) ) {
            require_once CQA_PLUGIN_DIR . 'includes/notifications/class-email-notifications.php';
        }
        if ( file_exists( CQA_PLUGIN_DIR . 'includes/notifications/class-reminders.php' ) ) {
            require_once CQA_PLUGIN_DIR . 'includes/notifications/class-reminders.php';
        }

        // Workflow (Phase 13)
        if ( file_exists( CQA_PLUGIN_DIR . 'includes/workflow/class-approval-workflow.php' ) ) {
            require_once CQA_PLUGIN_DIR . 'includes/workflow/class-approval-workflow.php';
        }

        // Analytics (Phase 15)
        if ( file_exists( CQA_PLUGIN_DIR . 'includes/analytics/class-trends.php' ) ) {
            require_once CQA_PLUGIN_DIR . 'includes/analytics/class-trends.php';
        }

        // Utils (Phase 14)
        if ( file_exists( CQA_PLUGIN_DIR . 'includes/utils/class-location.php' ) ) {
            require_once CQA_PLUGIN_DIR . 'includes/utils/class-location.php';
        }

        // Admin
        require_once CQA_PLUGIN_DIR . 'admin/class-admin-menu.php';

        // API
        require_once CQA_PLUGIN_DIR . 'includes/api/class-rest-controller.php';
    }

    /**
     * Set plugin text domain for internationalization.
     */
    private function set_locale() {
        add_action( 'init', function() {
            load_plugin_textdomain(
                'chroma-qa-reports',
                false,
                dirname( CQA_PLUGIN_BASENAME ) . '/languages/'
            );
        });
    }

    /**
     * Register admin hooks.
     */
    private function define_admin_hooks() {
        if ( is_admin() ) {
            $this->admin = new Admin\Admin_Menu();
            
            add_action( 'admin_menu', [ $this->admin, 'register_menu' ] );
            add_action( 'admin_enqueue_scripts', [ $this->admin, 'enqueue_styles' ] );
            add_action( 'admin_enqueue_scripts', [ $this->admin, 'enqueue_scripts' ] );
        }

        // Add PWA manifest to head
        add_action( 'admin_head', [ $this, 'add_pwa_manifest' ] );
    }

    /**
     * Register REST API hooks.
     */
    private function define_api_hooks() {
        $this->api = new API\REST_Controller();
        add_action( 'rest_api_init', [ $this->api, 'register_routes' ] );
    }

    /**
     * Initialize enhancement modules.
     */
    private function init_enhancements() {
        // Phase 13: Notifications
        if ( class_exists( 'ChromaQA\\Notifications\\Email_Notifications' ) ) {
            Notifications\Email_Notifications::init();
        }
        if ( class_exists( 'ChromaQA\\Notifications\\Reminders' ) ) {
            Notifications\Reminders::init();
        }

        // Phase 13: Workflow
        if ( class_exists( 'ChromaQA\\Workflow\\Approval_Workflow' ) ) {
            Workflow\Approval_Workflow::init();
        }

        // Phase 14: Location
        if ( class_exists( 'ChromaQA\\Utils\\Location' ) ) {
            Utils\Location::init();
        }

        // Phase 15: Analytics
        if ( class_exists( 'ChromaQA\\Analytics\\Trends' ) ) {
            Analytics\Trends::init();
        }

        // Phase 16: AI Enhancements
        if ( class_exists( 'ChromaQA\\AI\\Photo_Analyzer' ) ) {
            AI\Photo_Analyzer::init();
        }
        if ( class_exists( 'ChromaQA\\AI\\Comparative_Insights' ) ) {
            AI\Comparative_Insights::init();
        }
    }

    /**
     * Add PWA manifest link to admin head.
     */
    public function add_pwa_manifest() {
        $manifest_url = CQA_PLUGIN_URL . 'manifest.json';
        echo '<link rel="manifest" href="' . esc_url( $manifest_url ) . '">';
        echo '<meta name="theme-color" content="#6366f1">';
        echo '<meta name="apple-mobile-web-app-capable" content="yes">';
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">';
    }

    /**
     * Get the plugin version.
     *
     * @return string
     */
    public function get_version() {
        return $this->version;
    }
}
