<?php
/**
 * Front-End Report Controller
 * 
 * Handles public-facing report submission without wp-admin
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Frontend;

/**
 * Front-End Report Controller
 */
class Frontend_Controller {

    /**
     * Initialize front-end functionality.
     */
    public static function init() {
        add_action( 'init', [ self::class, 'register_rewrites' ] );
        add_filter( 'query_vars', [ self::class, 'add_query_vars' ] );
        add_action( 'template_redirect', [ self::class, 'handle_routes' ] );
        add_shortcode( 'cqa_report_form', [ self::class, 'shortcode_report_form' ] );
        add_shortcode( 'cqa_login', [ self::class, 'shortcode_login' ] );
        
        // AJAX handlers
        add_action( 'wp_ajax_cqa_frontend_login', [ self::class, 'ajax_login' ] );
        add_action( 'wp_ajax_nopriv_cqa_frontend_login', [ self::class, 'ajax_login' ] );
    }

    /**
     * Register custom URL rewrites.
     */
    public static function register_rewrites() {
        add_rewrite_rule( 
            '^qa-reports/?$', 
            'index.php?cqa_page=dashboard', 
            'top' 
        );
        add_rewrite_rule( 
            '^qa-reports/login/?$', 
            'index.php?cqa_page=login', 
            'top' 
        );
        add_rewrite_rule( 
            '^qa-reports/new/?$', 
            'index.php?cqa_page=new-report', 
            'top' 
        );
        add_rewrite_rule( 
            '^qa-reports/report/([0-9]+)/?$', 
            'index.php?cqa_page=view-report&cqa_report_id=$matches[1]', 
            'top' 
        );
        add_rewrite_rule( 
            '^qa-reports/edit/([0-9]+)/?$', 
            'index.php?cqa_page=edit-report&cqa_report_id=$matches[1]', 
            'top' 
        );
    }

    /**
     * Add custom query vars.
     */
    public static function add_query_vars( $vars ) {
        $vars[] = 'cqa_page';
        $vars[] = 'cqa_report_id';
        return $vars;
    }

    /**
     * Handle front-end routes.
     */
    public static function handle_routes() {
        $page = get_query_var( 'cqa_page' );
        
        if ( empty( $page ) ) {
            return;
        }

        // Check authentication for protected pages
        $public_pages = [ 'login' ];
        if ( ! in_array( $page, $public_pages ) && ! is_user_logged_in() ) {
            wp_redirect( home_url( '/qa-reports/login/' ) );
            exit;
        }

        // Check capabilities
        if ( ! in_array( $page, $public_pages ) && ! current_user_can( 'cqa_create_reports' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'chroma-qa-reports' ) );
        }

        // Load the appropriate template
        self::load_template( $page );
        exit;
    }

    /**
     * Load front-end template.
     */
    private static function load_template( $page ) {
        // Enqueue front-end styles and scripts
        self::enqueue_assets();

        $report_id = get_query_var( 'cqa_report_id' );

        include CQA_PLUGIN_DIR . 'public/views/header.php';

        switch ( $page ) {
            case 'login':
                include CQA_PLUGIN_DIR . 'public/views/login.php';
                break;
            case 'dashboard':
                include CQA_PLUGIN_DIR . 'public/views/dashboard.php';
                break;
            case 'new-report':
                include CQA_PLUGIN_DIR . 'public/views/report-form.php';
                break;
            case 'edit-report':
                include CQA_PLUGIN_DIR . 'public/views/report-form.php';
                break;
            case 'view-report':
                include CQA_PLUGIN_DIR . 'public/views/report-view.php';
                break;
            default:
                include CQA_PLUGIN_DIR . 'public/views/404.php';
        }

        include CQA_PLUGIN_DIR . 'public/views/footer.php';
    }

    /**
     * Enqueue front-end assets.
     */
    private static function enqueue_assets() {
        wp_enqueue_style(
            'cqa-frontend',
            CQA_PLUGIN_URL . 'public/css/frontend-styles.css',
            [],
            CQA_VERSION
        );

        wp_enqueue_script(
            'cqa-frontend',
            CQA_PLUGIN_URL . 'public/js/frontend-app.js',
            [ 'jquery' ],
            CQA_VERSION,
            true
        );

        wp_localize_script( 'cqa-frontend', 'cqaFrontend', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'restUrl'  => rest_url( 'cqa/v1/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'loginUrl' => home_url( '/qa-reports/login/' ),
            'homeUrl'  => home_url( '/qa-reports/' ),
            'strings'  => [
                'loading'     => __( 'Loading...', 'chroma-qa-reports' ),
                'saving'      => __( 'Saving...', 'chroma-qa-reports' ),
                'error'       => __( 'An error occurred', 'chroma-qa-reports' ),
                'loginFailed' => __( 'Invalid username or password', 'chroma-qa-reports' ),
            ],
        ]);
    }

    /**
     * AJAX login handler.
     */
    public static function ajax_login() {
        check_ajax_referer( 'cqa_frontend_login', 'nonce' );

        $username = sanitize_user( $_POST['username'] ?? '' );
        $password = $_POST['password'] ?? '';
        $remember = ! empty( $_POST['remember'] );

        if ( empty( $username ) || empty( $password ) ) {
            wp_send_json_error( [ 'message' => __( 'Please enter username and password.', 'chroma-qa-reports' ) ] );
        }

        $user = wp_signon( [
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember,
        ] );

        if ( is_wp_error( $user ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid username or password.', 'chroma-qa-reports' ) ] );
        }

        // Check if user has QA capabilities
        if ( ! user_can( $user, 'cqa_create_reports' ) && ! user_can( $user, 'cqa_view_all_reports' ) ) {
            wp_logout();
            wp_send_json_error( [ 'message' => __( 'You do not have access to QA Reports.', 'chroma-qa-reports' ) ] );
        }

        wp_send_json_success( [
            'redirect' => home_url( '/qa-reports/' ),
            'user'     => [
                'name'   => $user->display_name,
                'avatar' => get_avatar_url( $user->ID ),
            ],
        ]);
    }

    /**
     * Shortcode for login form.
     */
    public static function shortcode_login( $atts ) {
        if ( is_user_logged_in() ) {
            return '<p>' . __( 'You are already logged in.', 'chroma-qa-reports' ) . ' <a href="' . home_url( '/qa-reports/' ) . '">' . __( 'Go to Dashboard', 'chroma-qa-reports' ) . '</a></p>';
        }

        ob_start();
        include CQA_PLUGIN_DIR . 'public/views/partials/login-form.php';
        return ob_get_clean();
    }

    /**
     * Shortcode for report form.
     */
    public static function shortcode_report_form( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>' . __( 'Please log in to create a report.', 'chroma-qa-reports' ) . '</p>';
        }

        if ( ! current_user_can( 'cqa_create_reports' ) ) {
            return '<p>' . __( 'You do not have permission to create reports.', 'chroma-qa-reports' ) . '</p>';
        }

        ob_start();
        include CQA_PLUGIN_DIR . 'public/views/partials/report-wizard.php';
        return ob_get_clean();
    }

    /**
     * Flush rewrite rules on activation.
     */
    public static function flush_rules() {
        self::register_rewrites();
        flush_rewrite_rules();
    }
}
