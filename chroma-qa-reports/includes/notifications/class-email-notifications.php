<?php
/**
 * Email Notifications
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Notifications;

use ChromaQA\Models\Report;
use ChromaQA\Models\School;

/**
 * Handles email notifications for QA reports.
 */
class Email_Notifications {

    /**
     * Initialize hooks.
     */
    public static function init() {
        \add_action( 'cqa_report_submitted', [ __CLASS__, 'on_report_submitted' ] );
        \add_action( 'cqa_report_approved', [ __CLASS__, 'on_report_approved' ] );
        \add_action( 'cqa_report_needs_revision', [ __CLASS__, 'on_needs_revision' ] );
    }

    /**
     * Send notification when report is submitted.
     *
     * @param int $report_id Report ID.
     */
    public static function on_report_submitted( $report_id ) {
        $report = Report::find( $report_id );
        if ( ! $report ) return;

        $school = $report->get_school();
        $inspector = \get_user_by( 'id', $report->user_id );

        // Notify Regional Director
        $regional_directors = self::get_regional_directors( $school->region ?? '' );
        
        foreach ( $regional_directors as $director ) {
            self::send_email(
                $director->user_email,
                sprintf( 'New QA Report Submitted: %s', $school->name ?? 'Unknown School' ),
                self::get_submitted_template( $report, $school, $inspector )
            );
        }

        // Notify school director if program manager assigned
        $program_managers = self::get_school_managers( $report->school_id );
        
        foreach ( $program_managers as $manager ) {
            self::send_email(
                $manager->user_email,
                sprintf( 'QA Report Submitted for Your Review: %s', $school->name ?? 'Unknown School' ),
                self::get_school_notification_template( $report, $school )
            );
        }
    }

    /**
     * Send notification when report is approved.
     *
     * @param int $report_id Report ID.
     */
    public static function on_report_approved( $report_id ) {
        $report = Report::find( $report_id );
        if ( ! $report ) return;

        $school = $report->get_school();
        $inspector = \get_user_by( 'id', $report->user_id );

        // Notify the inspector
        if ( $inspector ) {
            self::send_email(
                $inspector->user_email,
                sprintf( 'QA Report Approved: %s', $school->name ?? 'Unknown School' ),
                self::get_approved_template( $report, $school )
            );
        }

        // Notify school management
        $program_managers = self::get_school_managers( $report->school_id );
        
        foreach ( $program_managers as $manager ) {
            self::send_email(
                $manager->user_email,
                sprintf( 'QA Report Finalized: %s', $school->name ?? 'Unknown School' ),
                self::get_final_report_template( $report, $school ),
                self::get_pdf_attachment( $report )
            );
        }
    }

    /**
     * Send notification when report needs revision.
     *
     * @param int    $report_id Report ID.
     * @param string $feedback Feedback message.
     */
    public static function on_needs_revision( $report_id, $feedback = '' ) {
        $report = Report::find( $report_id );
        if ( ! $report ) return;

        $school = $report->get_school();
        $inspector = \get_user_by( 'id', $report->user_id );

        if ( $inspector ) {
            self::send_email(
                $inspector->user_email,
                sprintf( 'Revision Requested: %s QA Report', $school->name ?? 'Unknown School' ),
                self::get_revision_template( $report, $school, $feedback )
            );
        }
    }

    /**
     * Send email with HTML template.
     *
     * @param string $to Recipient email.
     * @param string $subject Email subject.
     * @param string $body HTML body.
     * @param string $attachment Optional attachment path.
     */
    private static function send_email( $to, $subject, $body, $attachment = '' ) {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Chroma QA Reports <noreply@' . parse_url( \home_url(), PHP_URL_HOST ) . '>',
        ];

        $attachments = $attachment ? [ $attachment ] : [];

        \wp_mail( $to, $subject, self::wrap_template( $body ), $headers, $attachments );
    }

    /**
     * Wrap body in email template.
     *
     * @param string $content Body content.
     * @return string
     */
    private static function wrap_template( $content ) {
        $company = \get_option( 'cqa_company_name', 'Chroma Early Learning Academy' );
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #6366f1, #4f46e5); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #ffffff; padding: 30px; border: 1px solid #e5e7eb; }
                .footer { background: #f9fafb; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-radius: 0 0 8px 8px; }
                .button { display: inline-block; padding: 12px 24px; background: #6366f1; color: white; text-decoration: none; border-radius: 6px; margin: 10px 0; }
                .rating { display: inline-block; padding: 6px 12px; border-radius: 4px; font-weight: bold; }
                .rating.exceeds { background: #dcfce7; color: #166534; }
                .rating.meets { background: #fef3c7; color: #92400e; }
                .rating.needs_improvement { background: #fee2e2; color: #991b1b; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>' . esc_html( $company ) . '</h1>
                    <p>Quality Assurance Reports</p>
                </div>
                <div class="content">
                    ' . $content . '
                </div>
                <div class="footer">
                    <p>This is an automated message from the QA Reports system.</p>
                    <p>© ' . date( 'Y' ) . ' ' . \esc_html( $company ) . '</p>
                </div>
            </div>
        </body>
        </html>';
    }

    /**
     * Get submitted notification template.
     */
    private static function get_submitted_template( $report, $school, $inspector ) {
        $view_url = admin_url( 'admin.php?page=chroma-qa-reports-view&id=' . $report->id );
        $date = date_i18n( 'F j, Y', strtotime( $report->inspection_date ) );
        
        return '
        <h2>New QA Report Submitted</h2>
        <p>A new QA report has been submitted and is awaiting your review.</p>
        <table style="width:100%; margin: 20px 0;">
            <tr><td><strong>School:</strong></td><td>' . esc_html( $school->name ?? 'Unknown' ) . '</td></tr>
            <tr><td><strong>Report Type:</strong></td><td>' . esc_html( $report->get_type_label() ) . '</td></tr>
            <tr><td><strong>Inspection Date:</strong></td><td>' . esc_html( $date ) . '</td></tr>
            <tr><td><strong>Inspector:</strong></td><td>' . esc_html( $inspector->display_name ?? 'Unknown' ) . '</td></tr>
            <tr><td><strong>Overall Rating:</strong></td><td><span class="rating ' . esc_attr( $report->overall_rating ) . '">' . esc_html( $report->get_rating_label() ) . '</span></td></tr>
        </table>
        <p><a href="' . esc_url( $view_url ) . '" class="button">Review Report</a></p>';
    }

    /**
     * Get school notification template.
     */
    private static function get_school_notification_template( $report, $school ) {
        $date = date_i18n( 'F j, Y', strtotime( $report->inspection_date ) );
        
        return '
        <h2>QA Inspection Report Available</h2>
        <p>A QA inspection has been completed for your school. The report is now available for your review.</p>
        <table style="width:100%; margin: 20px 0;">
            <tr><td><strong>School:</strong></td><td>' . esc_html( $school->name ?? 'Unknown' ) . '</td></tr>
            <tr><td><strong>Inspection Date:</strong></td><td>' . esc_html( $date ) . '</td></tr>
            <tr><td><strong>Overall Rating:</strong></td><td><span class="rating ' . esc_attr( $report->overall_rating ) . '">' . esc_html( $report->get_rating_label() ) . '</span></td></tr>
        </table>
        <p>Your Regional Director will contact you shortly to discuss the findings and any required action items.</p>';
    }

    /**
     * Get approved template.
     */
    private static function get_approved_template( $report, $school ) {
        return '
        <h2>✅ Report Approved</h2>
        <p>Your QA report for <strong>' . esc_html( $school->name ?? 'Unknown' ) . '</strong> has been reviewed and approved.</p>
        <p>Thank you for your thorough inspection work!</p>';
    }

    /**
     * Get final report template.
     */
    private static function get_final_report_template( $report, $school ) {
        $date = date_i18n( 'F j, Y', strtotime( $report->inspection_date ) );
        
        return '
        <h2>Final QA Report</h2>
        <p>The QA report for <strong>' . esc_html( $school->name ?? 'Unknown' ) . '</strong> has been finalized.</p>
        <table style="width:100%; margin: 20px 0;">
            <tr><td><strong>Inspection Date:</strong></td><td>' . esc_html( $date ) . '</td></tr>
            <tr><td><strong>Overall Rating:</strong></td><td><span class="rating ' . esc_attr( $report->overall_rating ) . '">' . esc_html( $report->get_rating_label() ) . '</span></td></tr>
        </table>
        <p>The full report is attached to this email as a PDF.</p>
        <p>Please review the findings and work with your Regional Director on any required action items.</p>';
    }

    /**
     * Get revision template.
     */
    private static function get_revision_template( $report, $school, $feedback ) {
        $edit_url = admin_url( 'admin.php?page=chroma-qa-reports-create&id=' . $report->id );
        
        return '
        <h2>⚠️ Revision Requested</h2>
        <p>Your QA report for <strong>' . esc_html( $school->name ?? 'Unknown' ) . '</strong> requires revision before it can be approved.</p>
        ' . ( $feedback ? '<div style="background:#fef3c7; padding:15px; border-radius:6px; margin:20px 0;"><strong>Feedback:</strong><br>' . esc_html( $feedback ) . '</div>' : '' ) . '
        <p><a href="' . esc_url( $edit_url ) . '" class="button">Edit Report</a></p>';
    }

    /**
     * Get regional directors for a region.
     */
    private static function get_regional_directors( $region ) {
        $users = \get_users( [
            'role'       => 'cqa_regional_director',
            'meta_key'   => 'cqa_region',
            'meta_value' => $region,
        ] );

        // If no regional match, get all regional directors
        if ( empty( $users ) ) {
            $users = \get_users( [ 'role' => 'cqa_regional_director' ] );
        }

        return $users;
    }

    /**
     * Get school managers.
     */
    private static function get_school_managers( $school_id ) {
        return \get_users( [
            'role'       => 'cqa_program_manager',
            'meta_key'   => 'cqa_school_id',
            'meta_value' => $school_id,
        ] );
    }

    /**
     * Get PDF attachment path.
     */
    private static function get_pdf_attachment( $report ) {
        $generator = new \ChromaQA\Export\PDF_Generator();
        return $generator->generate( $report );
    }
}
