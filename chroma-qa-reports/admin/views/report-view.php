<?php
/**
 * Report View
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Admin;

use ChromaQA\Models\Report;
use ChromaQA\Models\Checklist_Response;
use ChromaQA\Checklists\Checklist_Manager;

$report_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
$compare_mode = isset( $_GET['compare'] ) && $_GET['compare'];
$export_pdf = isset( $_GET['export'] ) && $_GET['export'] === 'pdf';

if ( ! $report_id ) {
    wp_die( __( 'Report not found.', 'chroma-qa-reports' ) );
}

$report = Report::find( $report_id );
if ( ! $report ) {
    wp_die( __( 'Report not found.', 'chroma-qa-reports' ) );
}

$school = $report->get_school();
$previous_report = $report->get_previous_report();
$responses = Checklist_Response::get_by_report_grouped( $report_id );
$photos = $report->get_photos();
$ai_summary = $report->get_ai_summary();
$checklist = Checklist_Manager::get_checklist_for_type( $report->report_type );
$stats = Checklist_Manager::get_progress_stats( $report_id, $report->report_type );

// Handle PDF export
if ( $export_pdf ) {
    // Redirect to PDF generation endpoint
    wp_redirect( rest_url( 'cqa/v1/reports/' . $report_id . '/pdf' ) );
    exit;
}
?>

<div class="wrap cqa-wrap">
    <div class="cqa-header">
        <div class="cqa-header-content">
            <h1 class="cqa-title">
                <span class="dashicons dashicons-clipboard"></span>
                <?php echo esc_html( $school ? $school->name : __( 'Report', 'chroma-qa-reports' ) ); ?>
            </h1>
            <p class="cqa-subtitle">
                <?php echo esc_html( $report->get_type_label() ); ?> • 
                <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $report->inspection_date ) ) ); ?>
            </p>
        </div>
        <div class="cqa-header-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-reports' ) ); ?>" class="button">
                ← <?php esc_html_e( 'Back', 'chroma-qa-reports' ); ?>
            </a>
            <?php if ( $previous_report && ! $compare_mode ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-view&id=' . $report_id . '&compare=1' ) ); ?>" class="button">
                    <span class="dashicons dashicons-image-flip-horizontal"></span>
                    <?php esc_html_e( 'Compare', 'chroma-qa-reports' ); ?>
                </a>
            <?php endif; ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-view&id=' . $report_id . '&export=pdf' ) ); ?>" class="button button-primary">
                <span class="dashicons dashicons-pdf"></span>
                <?php esc_html_e( 'Export PDF', 'chroma-qa-reports' ); ?>
            </a>
        </div>
    </div>

    <!-- Report Stats -->
    <div class="cqa-stats-grid" style="margin-bottom: 20px;">
        <div class="cqa-stat-card">
            <div class="cqa-stat-icon reports">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="cqa-stat-content">
                <span class="cqa-stat-number"><?php echo esc_html( $stats['yes'] ); ?></span>
                <span class="cqa-stat-label"><?php esc_html_e( 'Yes / Compliant', 'chroma-qa-reports' ); ?></span>
            </div>
        </div>
        <div class="cqa-stat-card">
            <div class="cqa-stat-icon pending">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="cqa-stat-content">
                <span class="cqa-stat-number"><?php echo esc_html( $stats['sometimes'] ); ?></span>
                <span class="cqa-stat-label"><?php esc_html_e( 'Needs Work', 'chroma-qa-reports' ); ?></span>
            </div>
        </div>
        <div class="cqa-stat-card">
            <div class="cqa-stat-icon" style="background: linear-gradient(135deg, #ef4444, #dc2626);">
                <span class="dashicons dashicons-dismiss"></span>
            </div>
            <div class="cqa-stat-content">
                <span class="cqa-stat-number"><?php echo esc_html( $stats['no'] ); ?></span>
                <span class="cqa-stat-label"><?php esc_html_e( 'Non-Compliant', 'chroma-qa-reports' ); ?></span>
            </div>
        </div>
        <div class="cqa-stat-card">
            <div class="cqa-stat-icon ai">
                <span class="dashicons dashicons-chart-pie"></span>
            </div>
            <div class="cqa-stat-content">
                <span class="cqa-stat-number"><?php echo esc_html( $stats['percentage'] ); ?>%</span>
                <span class="cqa-stat-label"><?php esc_html_e( 'Completed', 'chroma-qa-reports' ); ?></span>
            </div>
        </div>
    </div>

    <div class="cqa-view-grid">
        <!-- Main Content -->
        <div class="cqa-main-content">
            <!-- Overall Rating -->
            <div class="cqa-card cqa-rating-card">
                <div class="cqa-card-body" style="text-align: center; padding: 30px;">
                    <h3 style="margin: 0 0 10px;"><?php esc_html_e( 'Overall Rating', 'chroma-qa-reports' ); ?></h3>
                    <span class="cqa-badge rating-<?php echo esc_attr( $report->overall_rating ); ?>" style="font-size: 18px; padding: 12px 24px;">
                        <?php
                        $rating_icons = [
                            'exceeds' => '✅',
                            'meets' => '☑️',
                            'needs_improvement' => '⚠️',
                            'pending' => '⏳',
                        ];
                        echo esc_html( ( $rating_icons[ $report->overall_rating ] ?? '' ) . ' ' . $report->get_rating_label() ); 
                        ?>
                    </span>
                </div>
            </div>

            <!-- AI Executive Summary -->
            <?php if ( $ai_summary ) : ?>
            <div class="cqa-card">
                <div class="cqa-card-header">
                    <h2>
                        <span class="dashicons dashicons-superhero-alt"></span>
                        <?php esc_html_e( 'AI Executive Summary', 'chroma-qa-reports' ); ?>
                    </h2>
                </div>
                <div class="cqa-card-body">
                    <div class="cqa-ai-summary">
                        <?php echo wp_kses_post( nl2br( $ai_summary['executive_summary'] ) ); ?>
                    </div>

                    <?php if ( ! empty( $ai_summary['issues'] ) ) : ?>
                        <h4><?php esc_html_e( 'Identified Issues', 'chroma-qa-reports' ); ?></h4>
                        <ul class="cqa-issues-list">
                            <?php foreach ( $ai_summary['issues'] as $issue ) : ?>
                                <li class="cqa-issue-item">
                                    <span class="cqa-issue-severity <?php echo esc_attr( $issue['severity'] ?? 'medium' ); ?>">
                                        <?php echo esc_html( $issue['severity'] ?? 'Medium' ); ?>
                                    </span>
                                    <?php echo esc_html( $issue['description'] ?? $issue ); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ( ! empty( $ai_summary['poi'] ) ) : ?>
                        <h4><?php esc_html_e( '📋 Plan of Improvement', 'chroma-qa-reports' ); ?></h4>
                        <ul class="cqa-poi-list">
                            <?php foreach ( $ai_summary['poi'] as $poi ) : ?>
                                <?php 
                                if ( is_array( $poi ) ) {
                                    $action = $poi['action'] ?? $poi['recommendation'] ?? '';
                                    $area = $poi['area'] ?? $poi['section'] ?? '';
                                    $priority = $poi['priority'] ?? '';
                                    echo '<li>';
                                    if ( $priority ) echo '<strong>[' . esc_html( strtoupper( str_replace('_', ' ', $priority ) ) ) . ']</strong> ';
                                    if ( $area ) echo esc_html( $area ) . ': ';
                                    echo esc_html( $action );
                                    echo '</li>';
                                } else {
                                    echo '<li>' . esc_html( $poi ) . '</li>';
                                }
                                ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Checklist Sections -->
            <?php foreach ( $checklist['sections'] as $section ) : 
                $section_responses = $responses[ $section['key'] ] ?? [];
            ?>
            <div class="cqa-card cqa-section-card">
                <div class="cqa-card-header">
                    <h2>
                        <?php if ( isset( $section['tier'] ) && $section['tier'] === 2 ) : ?>
                            <span class="cqa-badge type-tier1_tier2" style="font-size: 11px; margin-right: 8px;">Tier 2</span>
                        <?php endif; ?>
                        <?php echo esc_html( $section['name'] ); ?>
                    </h2>
                </div>
                <div class="cqa-card-body">
                    <table class="cqa-checklist-table">
                        <thead>
                            <tr>
                                <th style="width: 50%;"><?php esc_html_e( 'Item', 'chroma-qa-reports' ); ?></th>
                                <th><?php esc_html_e( 'Rating', 'chroma-qa-reports' ); ?></th>
                                <?php if ( $compare_mode && $previous_report ) : ?>
                                    <th><?php esc_html_e( 'Previous', 'chroma-qa-reports' ); ?></th>
                                <?php endif; ?>
                                <th><?php esc_html_e( 'Notes', 'chroma-qa-reports' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $section['items'] as $item ) : 
                                $response = $section_responses[ $item['key'] ] ?? null;
                                $rating = $response ? $response->rating : 'na';
                                $notes = $response ? $response->notes : '';
                            ?>
                            <tr class="rating-row rating-<?php echo esc_attr( $rating ); ?>">
                                <td><?php echo esc_html( $item['label'] ); ?></td>
                                <td>
                                    <?php
                                    $rating_display = [
                                        'yes' => '✅ Yes',
                                        'sometimes' => '⚠️ Sometimes',
                                        'no' => '❌ No',
                                        'na' => '➖ N/A',
                                    ];
                                    echo esc_html( $rating_display[ $rating ] ?? $rating );
                                    ?>
                                </td>
                                <?php if ( $compare_mode && $previous_report ) : ?>
                                    <td>
                                        <?php
                                        $prev_rating = $response && $response->previous_rating ? $response->previous_rating : 'na';
                                        echo esc_html( $rating_display[ $prev_rating ] ?? $prev_rating );
                                        
                                        if ( $response && $response->has_changed() ) {
                                            echo $response->is_improvement() ? ' <span style="color: green;">↑</span>' : ' <span style="color: red;">↓</span>';
                                        }
                                        ?>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <?php if ( $notes ) : ?>
                                        <span class="cqa-note-text"><?php echo esc_html( $notes ); ?></span>
                                    <?php else : ?>
                                        <span class="cqa-text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Closing Notes -->
            <?php if ( $report->closing_notes ) : ?>
            <div class="cqa-card">
                <div class="cqa-card-header">
                    <h2><?php esc_html_e( 'Closing Notes', 'chroma-qa-reports' ); ?></h2>
                </div>
                <div class="cqa-card-body">
                    <?php echo wp_kses_post( nl2br( $report->closing_notes ) ); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="cqa-sidebar">
            <!-- Report Info -->
            <div class="cqa-card">
                <div class="cqa-card-header">
                    <h2><?php esc_html_e( 'Report Info', 'chroma-qa-reports' ); ?></h2>
                </div>
                <div class="cqa-card-body">
                    <dl class="cqa-info-list">
                        <dt><?php esc_html_e( 'School', 'chroma-qa-reports' ); ?></dt>
                        <dd><?php echo esc_html( $school ? $school->name : 'Unknown' ); ?></dd>

                        <dt><?php esc_html_e( 'Type', 'chroma-qa-reports' ); ?></dt>
                        <dd><span class="cqa-badge type-<?php echo esc_attr( $report->report_type ); ?>"><?php echo esc_html( $report->get_type_label() ); ?></span></dd>

                        <dt><?php esc_html_e( 'Inspection Date', 'chroma-qa-reports' ); ?></dt>
                        <dd><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $report->inspection_date ) ) ); ?></dd>

                        <dt><?php esc_html_e( 'Status', 'chroma-qa-reports' ); ?></dt>
                        <dd><span class="cqa-badge status-<?php echo esc_attr( $report->status ); ?>"><?php echo esc_html( $report->get_status_label() ); ?></span></dd>

                        <dt><?php esc_html_e( 'Created', 'chroma-qa-reports' ); ?></dt>
                        <dd><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $report->created_at ) ) ); ?></dd>
                    </dl>
                </div>
            </div>

            <!-- Photos -->
            <?php if ( ! empty( $photos ) ) : ?>
            <div class="cqa-card">
                <div class="cqa-card-header">
                    <h2><?php esc_html_e( 'Photos', 'chroma-qa-reports' ); ?> (<?php echo count( $photos ); ?>)</h2>
                </div>
                <div class="cqa-card-body">
                    <div class="cqa-photo-grid">
                        <?php foreach ( array_slice( $photos, 0, 6 ) as $photo ) : ?>
                            <a href="<?php echo esc_url( $photo->get_view_url() ); ?>" target="_blank" class="cqa-photo-thumb">
                                <img src="<?php echo esc_url( $photo->get_thumbnail_url( 150 ) ); ?>" alt="<?php echo esc_attr( $photo->caption ); ?>">
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php if ( count( $photos ) > 6 ) : ?>
                        <p style="text-align: center; margin-top: 12px;">
                            <a href="#"><?php printf( esc_html__( 'View all %d photos', 'chroma-qa-reports' ), count( $photos ) ); ?></a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.cqa-view-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 20px;
}

.cqa-checklist-table {
    width: 100%;
    border-collapse: collapse;
}

.cqa-checklist-table th,
.cqa-checklist-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--cqa-gray-100);
    vertical-align: top;
}

.cqa-checklist-table th {
    font-weight: 600;
    color: var(--cqa-gray-600);
    font-size: 13px;
    background: var(--cqa-gray-50);
}

.rating-row.rating-no {
    background: #fef2f2;
}

.rating-row.rating-sometimes {
    background: #fffbeb;
}

.cqa-info-list {
    margin: 0;
}

.cqa-info-list dt {
    font-weight: 600;
    color: var(--cqa-gray-600);
    font-size: 13px;
    margin-top: 12px;
}

.cqa-info-list dt:first-child {
    margin-top: 0;
}

.cqa-info-list dd {
    margin: 4px 0 0 0;
}

.cqa-photo-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}

.cqa-photo-thumb {
    aspect-ratio: 1;
    overflow: hidden;
    border-radius: 8px;
}

.cqa-photo-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cqa-ai-summary {
    padding: 16px;
    background: var(--cqa-gray-50);
    border-radius: var(--cqa-radius-sm);
    line-height: 1.7;
}

.cqa-issues-list {
    list-style: none;
    padding: 0;
}

.cqa-issue-item {
    padding: 12px;
    margin-bottom: 8px;
    background: var(--cqa-gray-50);
    border-radius: var(--cqa-radius-sm);
    border-left: 4px solid var(--cqa-gray-300);
}

.cqa-issue-severity {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    margin-right: 8px;
}

.cqa-issue-severity.high { background: #fee2e2; color: #991b1b; }
.cqa-issue-severity.medium { background: #fef3c7; color: #92400e; }
.cqa-issue-severity.low { background: #dbeafe; color: #1e40af; }

.cqa-note-text {
    font-size: 13px;
    color: var(--cqa-gray-600);
}

.cqa-text-muted {
    color: var(--cqa-gray-400);
}

@media (max-width: 1024px) {
    .cqa-view-grid {
        grid-template-columns: 1fr;
    }
}
</style>
