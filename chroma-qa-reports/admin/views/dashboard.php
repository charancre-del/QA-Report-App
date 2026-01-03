<?php
/**
 * Dashboard View
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Admin;

use ChromaQA\Models\School;
use ChromaQA\Models\Report;

// Get stats
$total_schools = School::count( [ 'status' => 'active' ] );
$total_reports = count( Report::all( [ 'limit' => 1000 ] ) );
$recent_reports = Report::all( [ 'limit' => 10, 'orderby' => 'created_at', 'order' => 'DESC' ] );
$pending_reports = Report::all( [ 'status' => 'draft', 'limit' => 5 ] );
?>

<div class="wrap cqa-wrap">
    <div class="cqa-header">
        <div class="cqa-header-content">
            <h1 class="cqa-title">
                <span class="cqa-logo">🎨</span>
                <?php esc_html_e( 'Chroma QA Reports', 'chroma-qa-reports' ); ?>
            </h1>
            <p class="cqa-subtitle"><?php esc_html_e( 'Quality Assurance Report Management System', 'chroma-qa-reports' ); ?></p>
        </div>
        <div class="cqa-header-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-create' ) ); ?>" class="button button-primary button-hero">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e( 'Create New Report', 'chroma-qa-reports' ); ?>
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="cqa-stats-grid">
        <div class="cqa-stat-card">
            <div class="cqa-stat-icon schools">
                <span class="dashicons dashicons-building"></span>
            </div>
            <div class="cqa-stat-content">
                <span class="cqa-stat-number"><?php echo esc_html( $total_schools ); ?></span>
                <span class="cqa-stat-label"><?php esc_html_e( 'Active Schools', 'chroma-qa-reports' ); ?></span>
            </div>
        </div>

        <div class="cqa-stat-card">
            <div class="cqa-stat-icon reports">
                <span class="dashicons dashicons-clipboard"></span>
            </div>
            <div class="cqa-stat-content">
                <span class="cqa-stat-number"><?php echo esc_html( $total_reports ); ?></span>
                <span class="cqa-stat-label"><?php esc_html_e( 'Total Reports', 'chroma-qa-reports' ); ?></span>
            </div>
        </div>

        <div class="cqa-stat-card">
            <div class="cqa-stat-icon pending">
                <span class="dashicons dashicons-edit"></span>
            </div>
            <div class="cqa-stat-content">
                <span class="cqa-stat-number"><?php echo esc_html( count( $pending_reports ) ); ?></span>
                <span class="cqa-stat-label"><?php esc_html_e( 'Draft Reports', 'chroma-qa-reports' ); ?></span>
            </div>
        </div>

        <div class="cqa-stat-card">
            <div class="cqa-stat-icon ai">
                <span class="dashicons dashicons-superhero-alt"></span>
            </div>
            <div class="cqa-stat-content">
                <span class="cqa-stat-number">AI</span>
                <span class="cqa-stat-label"><?php esc_html_e( 'Powered Insights', 'chroma-qa-reports' ); ?></span>
            </div>
        </div>
    </div>

    <div class="cqa-dashboard-grid">
        <!-- Recent Reports -->
        <div class="cqa-card cqa-recent-reports">
            <div class="cqa-card-header">
                <h2><?php esc_html_e( 'Recent Reports', 'chroma-qa-reports' ); ?></h2>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-reports' ) ); ?>" class="cqa-link">
                    <?php esc_html_e( 'View All', 'chroma-qa-reports' ); ?> →
                </a>
            </div>
            <div class="cqa-card-body">
                <?php if ( empty( $recent_reports ) ) : ?>
                    <div class="cqa-empty-state">
                        <span class="dashicons dashicons-clipboard"></span>
                        <p><?php esc_html_e( 'No reports yet. Create your first QA report!', 'chroma-qa-reports' ); ?></p>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-create' ) ); ?>" class="button">
                            <?php esc_html_e( 'Create Report', 'chroma-qa-reports' ); ?>
                        </a>
                    </div>
                <?php else : ?>
                    <table class="cqa-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'School', 'chroma-qa-reports' ); ?></th>
                                <th><?php esc_html_e( 'Type', 'chroma-qa-reports' ); ?></th>
                                <th><?php esc_html_e( 'Date', 'chroma-qa-reports' ); ?></th>
                                <th><?php esc_html_e( 'Rating', 'chroma-qa-reports' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'chroma-qa-reports' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $recent_reports as $report ) : 
                                $school = $report->get_school();
                            ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-view&id=' . $report->id ) ); ?>">
                                            <?php echo esc_html( $school ? $school->name : __( 'Unknown', 'chroma-qa-reports' ) ); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="cqa-badge type-<?php echo esc_attr( $report->report_type ); ?>">
                                            <?php echo esc_html( $report->get_type_label() ); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $report->inspection_date ) ) ); ?></td>
                                    <td>
                                        <span class="cqa-badge rating-<?php echo esc_attr( $report->overall_rating ); ?>">
                                            <?php echo esc_html( $report->get_rating_label() ); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="cqa-badge status-<?php echo esc_attr( $report->status ); ?>">
                                            <?php echo esc_html( $report->get_status_label() ); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="cqa-card cqa-quick-actions">
            <div class="cqa-card-header">
                <h2><?php esc_html_e( 'Quick Actions', 'chroma-qa-reports' ); ?></h2>
            </div>
            <div class="cqa-card-body">
                <div class="cqa-action-buttons">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-create' ) ); ?>" class="cqa-action-button">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <span><?php esc_html_e( 'New Report', 'chroma-qa-reports' ); ?></span>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-schools' ) ); ?>" class="cqa-action-button">
                        <span class="dashicons dashicons-building"></span>
                        <span><?php esc_html_e( 'Manage Schools', 'chroma-qa-reports' ); ?></span>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-school-edit' ) ); ?>" class="cqa-action-button">
                        <span class="dashicons dashicons-plus"></span>
                        <span><?php esc_html_e( 'Add School', 'chroma-qa-reports' ); ?></span>
                    </a>
                    <?php if ( current_user_can( 'cqa_manage_settings' ) ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-settings' ) ); ?>" class="cqa-action-button">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <span><?php esc_html_e( 'Settings', 'chroma-qa-reports' ); ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Draft Reports -->
    <?php if ( ! empty( $pending_reports ) ) : ?>
    <div class="cqa-card cqa-drafts">
        <div class="cqa-card-header">
            <h2><?php esc_html_e( 'Continue Working On', 'chroma-qa-reports' ); ?></h2>
        </div>
        <div class="cqa-card-body">
            <div class="cqa-draft-list">
                <?php foreach ( $pending_reports as $report ) : 
                    $school = $report->get_school();
                ?>
                    <div class="cqa-draft-item">
                        <div class="cqa-draft-info">
                            <strong><?php echo esc_html( $school ? $school->name : __( 'Unknown School', 'chroma-qa-reports' ) ); ?></strong>
                            <span class="cqa-draft-meta">
                                <?php echo esc_html( $report->get_type_label() ); ?> • 
                                <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $report->created_at ) ) ); ?>
                            </span>
                        </div>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-create&id=' . $report->id ) ); ?>" class="button">
                            <?php esc_html_e( 'Continue', 'chroma-qa-reports' ); ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
