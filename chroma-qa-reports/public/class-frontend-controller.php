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
        
        // OAuth Callback
        add_action( 'wp_ajax_cqa_oauth_callback', [ self::class, 'oauth_callback' ] );
        add_action( 'wp_ajax_nopriv_cqa_oauth_callback', [ self::class, 'oauth_callback' ] );

        // Exclude from sitemaps (WordPress core and popular plugins)
        add_filter( 'wp_sitemaps_add_provider', [ self::class, 'exclude_from_sitemap' ], 10, 2 );
        add_filter( 'wpseo_exclude_from_sitemap_by_url', [ self::class, 'yoast_exclude_urls' ] );
        add_filter( 'rank_math/sitemap/exclude_urls', [ self::class, 'rankmath_exclude_urls' ] );
        
        // Add noindex to QA pages
        add_action( 'wp_head', [ self::class, 'add_noindex_meta' ] );

        // Emergency Flush (Self-Healing)
        if ( ! get_option( 'cqa_rewrite_flush_check_v3' ) ) {
            self::register_rewrites();
            flush_rewrite_rules();
            update_option( 'cqa_rewrite_flush_check_v3', true );
        }
    }

    /**
     * Exclude QA pages from WordPress core sitemap.
     */
    public static function exclude_from_sitemap( $provider, $name ) {
        // QA pages use custom routing, so they won't appear in standard sitemaps
        // This is just a safety measure
        return $provider;
    }

    /**
     * Exclude URLs from Yoast sitemap.
     */
    public static function yoast_exclude_urls( $excluded ) {
        $excluded[] = home_url( '/qa-reports/' );
        $excluded[] = home_url( '/qa-reports/login/' );
        $excluded[] = home_url( '/qa-reports/new/' );
        return $excluded;
    }

    /**
     * Exclude URLs from Rank Math sitemap.
     */
    public static function rankmath_exclude_urls( $urls ) {
        $urls[] = home_url( '/qa-reports/' );
        $urls[] = home_url( '/qa-reports/login/' );
        $urls[] = home_url( '/qa-reports/new/' );
        return $urls;
    }

    /**
     * Add noindex meta tag to QA pages.
     */
    public static function add_noindex_meta() {
        if ( get_query_var( 'cqa_page' ) ) {
            echo '<meta name="robots" content="noindex, nofollow">' . "\n";
        }
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
        add_rewrite_rule( 
            '^qa-reports/auth/callback/?$', 
            'index.php?cqa_page=oauth_callback', 
            'top' 
        );
        // School Management Routes
        add_rewrite_rule( 
            '^qa-reports/schools/?$', 
            'index.php?cqa_page=schools-list', 
            'top' 
        );
        add_rewrite_rule( 
            '^qa-reports/schools/new/?$', 
            'index.php?cqa_page=school-form&cqa_action=new', 
            'top' 
        );
        add_rewrite_rule( 
            '^qa-reports/schools/edit/([0-9]+)/?$', 
            'index.php?cqa_page=school-form&cqa_action=edit&cqa_school_id=$matches[1]', 
            'top' 
        );
        add_rewrite_rule( 
            '^qa-reports/settings/?$', 
            'index.php?cqa_page=settings', 
            'top' 
        );
        add_rewrite_rule( 
            '^qa-reports/import/?$', 
            'index.php?cqa_page=import', 
            'top' 
        );
        add_rewrite_rule( 
            '^qa-reports/reports/?$', 
            'index.php?cqa_page=reports-list', 
            'top' 
        );
        add_rewrite_rule( 
            '^qa-reports/analytics/?$', 
            'index.php?cqa_page=analytics', 
            'top' 
        );
    }

    /**
     * Add custom query vars.
     */
    public static function add_query_vars( $vars ) {
        $vars[] = 'cqa_page';
        $vars[] = 'cqa_report_id';
        $vars[] = 'cqa_action';
        $vars[] = 'cqa_school_id';
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
            case 'oauth_callback':
                self::oauth_callback();
                break;
            case 'schools-list':
                include CQA_PLUGIN_DIR . 'public/views/schools-list.php';
                break;
            case 'school-form':
                include CQA_PLUGIN_DIR . 'public/views/school-form.php';
                break;
            case 'settings':
                include CQA_PLUGIN_DIR . 'public/views/settings.php';
                break;
            case 'import':
                include CQA_PLUGIN_DIR . 'public/views/import.php';
                break;
            case 'reports-list':
                include CQA_PLUGIN_DIR . 'public/views/reports-list.php';
                break;
            case 'analytics':
                if ( get_query_var( 'cqa_page' ) === 'analytics' ) { // Ensure script is enqueued
                     // Already handled in loop or script enqueue logic if we check cqa_page
                }
                include CQA_PLUGIN_DIR . 'public/views/analytics.php';
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

        // Enqueue Chart.js for dashboard or analytics
        $cqa_page = get_query_var( 'cqa_page' );
        if ( $cqa_page === 'dashboard' || $cqa_page === 'analytics' ) {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js',
                [],
                '3.9.1',
                true
            );
        }

        wp_enqueue_script(
            'cqa-frontend',
            CQA_PLUGIN_URL . 'public/js/frontend-app.js',
            [ 'jquery' ],
            CQA_VERSION,
            true
        );

        wp_localize_script( 'cqa-frontend', 'cqaFrontend', [
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'restUrl'        => rest_url( 'cqa/v1/' ),
            'nonce'          => wp_create_nonce( 'wp_rest' ),
            'loginUrl'       => home_url( '/qa-reports/login/' ),
            'homeUrl'        => home_url( '/qa-reports/' ),
            'googleClientId' => get_option( 'cqa_google_client_id' ),
            'developerKey'   => get_option( 'cqa_google_developer_key' ), // Needed for Picker API
            'strings'        => [
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
        if ( ! user_can( $user, 'cqa_create_reports' ) 
            && ! user_can( $user, 'cqa_view_all_reports' ) 
            && ! user_can( $user, 'cqa_view_own_reports' ) ) {
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
     * OAuth callback handler.
     */
    public static function oauth_callback() {
        if ( ! isset( $_GET['code'] ) ) {
            wp_redirect( home_url( '/qa-reports/login/?error=missing_code' ) );
            exit;
        }

        // Check state for CSRF
        if ( ! isset( $_GET['state'] ) || ! wp_verify_nonce( $_GET['state'], 'cqa_oauth_state' ) ) {
            wp_redirect( home_url( '/qa-reports/login/?error=invalid_state' ) );
            exit;
        }

        if ( class_exists( 'ChromaQA\Auth\Google_OAuth' ) ) {
            // Include class file if not autoloaded yet (just safety check)
            if ( ! class_exists( 'ChromaQA\Auth\Google_OAuth' ) ) {
                require_once CQA_PLUGIN_DIR . 'includes/auth/class-google-oauth.php';
            }

            $user_id = \ChromaQA\Auth\Google_OAuth::handle_login( $_GET['code'] );

            if ( is_wp_error( $user_id ) ) {
                $error_code = $user_id->get_error_code();
                $error_msg = urlencode( $user_id->get_error_message() );
                wp_redirect( home_url( "/qa-reports/login/?error={$error_code}&message={$error_msg}" ) );
                exit;
            }

            // Verify session was actually created
            if ( ! is_user_logged_in() ) {
                wp_redirect( home_url( '/qa-reports/login/?error=session_failed&message=' . urlencode( 'Login succeeded but session was not created. Please try again.' ) ) );
                exit;
            }

            // Success redirect - use wp_safe_redirect
            wp_safe_redirect( home_url( '/qa-reports/' ) );
            exit;
        }

        wp_redirect( home_url( '/qa-reports/login/?error=oauth_unavailable' ) );
        exit;
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
