<?php
/**
 * Due Date Reminders
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Notifications;

use ChromaQA\Models\School;
use ChromaQA\Models\Report;

/**
 * Handles due date tracking and reminders.
 */
class Reminders {

    /**
     * Default visit interval in days.
     */
    const DEFAULT_INTERVAL = 90; // 3 months

    /**
     * Initialize hooks.
     */
    public static function init() {
        // Register cron event
        \add_action( 'cqa_daily_reminder_check', [ __CLASS__, 'check_due_dates' ] );
        
        // Schedule if not scheduled
        if ( ! \wp_next_scheduled( 'cqa_daily_reminder_check' ) ) {
            \wp_schedule_event( time(), 'daily', 'cqa_daily_reminder_check' );
        }

        // Dashboard widget
        \add_action( 'wp_dashboard_setup', [ __CLASS__, 'add_dashboard_widget' ] );
    }

    /**
     * Check due dates and send reminders.
     */
    public static function check_due_dates() {
        $schools = self::get_schools_due_for_visit();

        foreach ( $schools as $school_data ) {
            self::send_reminder( $school_data );
        }
    }

    /**
     * Get schools due for visit.
     *
     * @param int $days_threshold Days threshold for "due soon".
     * @return array
     */
    public static function get_schools_due_for_visit( $days_threshold = 14 ) {
        $schools = School::all( [ 'status' => 'active', 'limit' => 100 ] );
        $interval = \get_option( 'cqa_visit_interval', self::DEFAULT_INTERVAL );
        $due_schools = [];

        foreach ( $schools as $school ) {
            $last_report = Report::get_latest_for_school( $school->id );
            
            if ( $last_report ) {
                $last_date = strtotime( $last_report->inspection_date );
                $next_due = $last_date + ( $interval * DAY_IN_SECONDS );
                $days_until_due = ( $next_due - time() ) / DAY_IN_SECONDS;
            } else {
                // Never visited - due now
                $days_until_due = -999;
                $next_due = time();
            }

            if ( $days_until_due <= $days_threshold ) {
                $due_schools[] = [
                    'school'         => $school,
                    'last_visit'     => $last_report ? $last_report->inspection_date : null,
                    'next_due'       => date( 'Y-m-d', $next_due ),
                    'days_until_due' => round( $days_until_due ),
                    'is_overdue'     => $days_until_due < 0,
                    'last_rating'    => $last_report ? $last_report->overall_rating : null,
                ];
            }
        }

        // Sort by days until due (most overdue first)
        usort( $due_schools, function( $a, $b ) {
            return $a['days_until_due'] <=> $b['days_until_due'];
        } );

        return $due_schools;
    }

    /**
     * Get overdue schools only.
     *
     * @return array
     */
    public static function get_overdue_schools() {
        return array_filter( self::get_schools_due_for_visit( 0 ), function( $item ) {
            return $item['is_overdue'];
        } );
    }

    /**
     * Send reminder email.
     *
     * @param array $school_data School data with due info.
     */
    private static function send_reminder( $school_data ) {
        $school = $school_data['school'];
        
        // Get QA officers and regional directors
        $recipients = array_merge(
            \get_users( [ 'role' => 'cqa_qa_officer' ] ),
            \get_users( [ 'role' => 'cqa_regional_director' ] )
        );

        if ( empty( $recipients ) ) return;

        $emails = array_map( function( $user ) {
            return $user->user_email;
        }, $recipients );

        $subject = $school_data['is_overdue'] 
            ? '⚠️ OVERDUE: QA Visit Required for ' . $school->name
            : '📅 Upcoming: QA Visit Due Soon for ' . $school->name;

        $body = self::get_reminder_template( $school_data );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Chroma QA Reports <noreply@' . parse_url( \home_url(), PHP_URL_HOST ) . '>',
        ];

        \wp_mail( $emails, $subject, $body, $headers );
    }

    /**
     * Get reminder email template.
     */
    private static function get_reminder_template( $school_data ) {
        $school = $school_data['school'];
        $create_url = \admin_url( 'admin.php?page=chroma-qa-reports-create&school_id=' . $school->id );
        
        $status_color = $school_data['is_overdue'] ? '#fee2e2' : '#fef3c7';
        $status_text = $school_data['is_overdue'] 
            ? 'OVERDUE by ' . abs( $school_data['days_until_due'] ) . ' days'
            : 'Due in ' . $school_data['days_until_due'] . ' days';

        return '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <div style="background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
                    <h1 style="margin: 0;">QA Visit Reminder</h1>
                </div>
                <div style="background: #ffffff; padding: 30px; border: 1px solid #e5e7eb;">
                    <h2>' . \esc_html( $school->name ) . '</h2>
                    <div style="background: ' . $status_color . '; padding: 15px; border-radius: 6px; margin: 20px 0; text-align: center;">
                        <strong style="font-size: 18px;">' . \esc_html( $status_text ) . '</strong>
                    </div>
                    <table style="width:100%; margin: 20px 0;">
                        <tr><td><strong>Location:</strong></td><td>' . \esc_html( $school->location ?? 'N/A' ) . '</td></tr>
                        <tr><td><strong>Last Visit:</strong></td><td>' . ( $school_data['last_visit'] ? \esc_html( \date_i18n( 'F j, Y', strtotime( $school_data['last_visit'] ) ) ) : 'Never' ) . '</td></tr>
                        <tr><td><strong>Last Rating:</strong></td><td>' . ( $school_data['last_rating'] ? \esc_html( ucwords( str_replace( '_', ' ', $school_data['last_rating'] ) ) ) : 'N/A' ) . '</td></tr>
                    </table>
                    <p style="text-align: center;">
                        <a href="' . \esc_url( $create_url ) . '" style="display: inline-block; padding: 12px 24px; background: #6366f1; color: white; text-decoration: none; border-radius: 6px;">Create QA Report</a>
                    </p>
                </div>
                <div style="background: #f9fafb; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 8px 8px;">
                    <p>This is an automated reminder from the QA Reports system.</p>
                </div>
            </div>
        </body>
        </html>';
    }

    /**
     * Add dashboard widget.
     */
    public static function add_dashboard_widget() {
        if ( ! \current_user_can( 'cqa_view_all_reports' ) ) return;

        \wp_add_dashboard_widget(
            'cqa_due_visits',
            '📅 Upcoming QA Visits',
            [ __CLASS__, 'render_dashboard_widget' ]
        );
    }

    /**
     * Render dashboard widget.
     */
    public static function render_dashboard_widget() {
        $due_schools = self::get_schools_due_for_visit( 30 ); // Next 30 days
        
        if ( empty( $due_schools ) ) {
            echo '<p style="color:#6b7280;">✓ All schools are up to date!</p>';
            return;
        }

        echo '<style>
            .cqa-due-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
            .cqa-due-item:last-child { border: none; }
            .cqa-overdue { color: #dc2626; font-weight: bold; }
            .cqa-due-soon { color: #f59e0b; }
        </style>';

        echo '<div class="cqa-due-list">';
        
        $count = 0;
        foreach ( $due_schools as $item ) {
            if ( $count >= 5 ) break; // Show top 5
            
            $class = $item['is_overdue'] ? 'cqa-overdue' : 'cqa-due-soon';
            $status = $item['is_overdue'] 
                ? 'Overdue ' . abs( $item['days_until_due'] ) . 'd'
                : 'Due in ' . $item['days_until_due'] . 'd';
            
            $create_url = admin_url( 'admin.php?page=chroma-qa-reports-create&school_id=' . $item['school']->id );
            
            echo '<div class="cqa-due-item">';
            echo '<div><strong>' . esc_html( $item['school']->name ) . '</strong></div>';
            echo '<div><span class="' . $class . '">' . esc_html( $status ) . '</span> ';
            echo '<a href="' . esc_url( $create_url ) . '">+ Report</a></div>';
            echo '</div>';
            
            $count++;
        }
        
        echo '</div>';

        if ( count( $due_schools ) > 5 ) {
            echo '<p style="text-align:center;margin-top:10px;">';
            echo '<a href="' . admin_url( 'admin.php?page=chroma-qa-reports' ) . '">View all ' . count( $due_schools ) . ' due →</a>';
            echo '</p>';
        }
    }

    /**
     * Unschedule cron on deactivation.
     */
    public static function deactivate() {
        \wp_clear_scheduled_hook( 'cqa_daily_reminder_check' );
    }
}
