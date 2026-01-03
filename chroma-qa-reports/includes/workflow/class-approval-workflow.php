<?php
/**
 * Approval Workflow
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Workflow;

use ChromaQA\Models\Report;

/**
 * Handles report approval workflow.
 */
class Approval_Workflow {

    /**
     * Workflow statuses.
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_NEEDS_REVISION = 'needs_revision';
    const STATUS_APPROVED = 'approved';

    /**
     * Initialize hooks.
     */
    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    /**
     * Register REST routes for workflow.
     */
    public static function register_routes() {
        register_rest_route( 'cqa/v1', '/reports/(?P<id>\d+)/workflow', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_workflow_action' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
            'args'                => [
                'id'      => [ 'type' => 'integer', 'required' => true ],
                'action'  => [ 'type' => 'string', 'required' => true ],
                'comment' => [ 'type' => 'string' ],
            ],
        ] );

        register_rest_route( 'cqa/v1', '/reports/(?P<id>\d+)/comments', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_comments' ],
            'permission_callback' => [ __CLASS__, 'check_view_permission' ],
        ] );
    }

    /**
     * Check workflow permission.
     */
    public static function check_permission( $request ) {
        $report_id = $request['id'];
        $action = $request['action'];
        $report = Report::find( $report_id );

        if ( ! $report ) {
            return false;
        }

        switch ( $action ) {
            case 'submit':
                // Author can submit
                return $report->user_id == get_current_user_id() || current_user_can( 'cqa_edit_all_reports' );

            case 'start_review':
            case 'approve':
            case 'request_revision':
                // Regional Director or Super Admin
                return current_user_can( 'cqa_edit_all_reports' );

            default:
                return false;
        }
    }

    /**
     * Check view permission.
     */
    public static function check_view_permission( $request ) {
        return current_user_can( 'cqa_view_all_reports' );
    }

    /**
     * Handle workflow action.
     */
    public static function handle_workflow_action( $request ) {
        $report_id = $request['id'];
        $action = $request['action'];
        $comment = $request['comment'] ?? '';

        $report = Report::find( $report_id );
        if ( ! $report ) {
            return new \WP_Error( 'not_found', 'Report not found', [ 'status' => 404 ] );
        }

        $result = false;

        switch ( $action ) {
            case 'submit':
                $result = self::submit_for_review( $report, $comment );
                break;

            case 'start_review':
                $result = self::start_review( $report, $comment );
                break;

            case 'approve':
                $result = self::approve( $report, $comment );
                break;

            case 'request_revision':
                $result = self::request_revision( $report, $comment );
                break;

            default:
                return new \WP_Error( 'invalid_action', 'Invalid workflow action', [ 'status' => 400 ] );
        }

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return [
            'success' => true,
            'status'  => $report->status,
            'message' => self::get_status_message( $action ),
        ];
    }

    /**
     * Submit report for review.
     *
     * @param Report $report Report object.
     * @param string $comment Optional comment.
     * @return bool|WP_Error
     */
    public static function submit_for_review( Report $report, $comment = '' ) {
        if ( $report->status !== self::STATUS_DRAFT && $report->status !== self::STATUS_NEEDS_REVISION ) {
            return new \WP_Error( 'invalid_status', 'Report cannot be submitted from current status.' );
        }

        $report->status = self::STATUS_SUBMITTED;
        $result = $report->save();

        if ( $result ) {
            self::add_workflow_comment( $report->id, 'submitted', $comment );
            do_action( 'cqa_report_submitted', $report->id );
        }

        return $result;
    }

    /**
     * Start reviewing a report.
     *
     * @param Report $report Report object.
     * @param string $comment Optional comment.
     * @return bool|WP_Error
     */
    public static function start_review( Report $report, $comment = '' ) {
        if ( $report->status !== self::STATUS_SUBMITTED ) {
            return new \WP_Error( 'invalid_status', 'Report is not pending review.' );
        }

        $report->status = self::STATUS_UNDER_REVIEW;
        $result = $report->save();

        if ( $result ) {
            self::add_workflow_comment( $report->id, 'review_started', $comment );
        }

        return $result;
    }

    /**
     * Approve a report.
     *
     * @param Report $report Report object.
     * @param string $comment Optional comment.
     * @return bool|WP_Error
     */
    public static function approve( Report $report, $comment = '' ) {
        if ( ! in_array( $report->status, [ self::STATUS_SUBMITTED, self::STATUS_UNDER_REVIEW ] ) ) {
            return new \WP_Error( 'invalid_status', 'Report cannot be approved from current status.' );
        }

        $report->status = self::STATUS_APPROVED;
        $result = $report->save();

        if ( $result ) {
            self::add_workflow_comment( $report->id, 'approved', $comment );
            do_action( 'cqa_report_approved', $report->id );
        }

        return $result;
    }

    /**
     * Request revision.
     *
     * @param Report $report Report object.
     * @param string $comment Revision feedback (required).
     * @return bool|WP_Error
     */
    public static function request_revision( Report $report, $comment = '' ) {
        if ( ! in_array( $report->status, [ self::STATUS_SUBMITTED, self::STATUS_UNDER_REVIEW ] ) ) {
            return new \WP_Error( 'invalid_status', 'Report cannot request revision from current status.' );
        }

        if ( empty( $comment ) ) {
            return new \WP_Error( 'comment_required', 'Please provide feedback for the revision.' );
        }

        $report->status = self::STATUS_NEEDS_REVISION;
        $result = $report->save();

        if ( $result ) {
            self::add_workflow_comment( $report->id, 'revision_requested', $comment );
            do_action( 'cqa_report_needs_revision', $report->id, $comment );
        }

        return $result;
    }

    /**
     * Add workflow comment.
     *
     * @param int    $report_id Report ID.
     * @param string $action Action taken.
     * @param string $comment Comment text.
     */
    private static function add_workflow_comment( $report_id, $action, $comment = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cqa_workflow_comments';

        // Create table if not exists
        self::ensure_comments_table();

        $wpdb->insert(
            $table,
            [
                'report_id'  => $report_id,
                'user_id'    => get_current_user_id(),
                'action'     => $action,
                'comment'    => $comment,
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%s' ]
        );
    }

    /**
     * Get workflow comments for a report.
     */
    public static function get_comments( $request ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cqa_workflow_comments';
        $report_id = $request['id'];

        self::ensure_comments_table();

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT c.*, u.display_name as user_name 
             FROM {$table} c 
             LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID 
             WHERE c.report_id = %d 
             ORDER BY c.created_at DESC",
            $report_id
        ), ARRAY_A );

        return array_map( function( $row ) {
            return [
                'id'         => (int) $row['id'],
                'action'     => $row['action'],
                'comment'    => $row['comment'],
                'user_id'    => (int) $row['user_id'],
                'user_name'  => $row['user_name'],
                'created_at' => $row['created_at'],
            ];
        }, $results );
    }

    /**
     * Ensure comments table exists.
     */
    private static function ensure_comments_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'cqa_workflow_comments';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            action VARCHAR(50) NOT NULL,
            comment TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_id (report_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Get status message for action.
     */
    private static function get_status_message( $action ) {
        $messages = [
            'submit'           => 'Report submitted for review.',
            'start_review'     => 'Review started.',
            'approve'          => 'Report approved!',
            'request_revision' => 'Revision requested.',
        ];
        return $messages[ $action ] ?? 'Action completed.';
    }

    /**
     * Get workflow status info.
     *
     * @param string $status Current status.
     * @return array
     */
    public static function get_status_info( $status ) {
        $statuses = [
            self::STATUS_DRAFT => [
                'label' => 'Draft',
                'color' => '#9ca3af',
                'icon'  => '📝',
                'next'  => [ 'submit' ],
            ],
            self::STATUS_SUBMITTED => [
                'label' => 'Submitted',
                'color' => '#3b82f6',
                'icon'  => '📤',
                'next'  => [ 'start_review', 'approve', 'request_revision' ],
            ],
            self::STATUS_UNDER_REVIEW => [
                'label' => 'Under Review',
                'color' => '#f59e0b',
                'icon'  => '🔍',
                'next'  => [ 'approve', 'request_revision' ],
            ],
            self::STATUS_NEEDS_REVISION => [
                'label' => 'Needs Revision',
                'color' => '#ef4444',
                'icon'  => '⚠️',
                'next'  => [ 'submit' ],
            ],
            self::STATUS_APPROVED => [
                'label' => 'Approved',
                'color' => '#10b981',
                'icon'  => '✅',
                'next'  => [],
            ],
        ];

        return $statuses[ $status ] ?? $statuses[ self::STATUS_DRAFT ];
    }
}
