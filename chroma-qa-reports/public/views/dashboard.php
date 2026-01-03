<?php
/**
 * Front-End Dashboard
 *
 * @package ChromaQAReports
 */

use ChromaQA\Models\Report;
use ChromaQA\Models\School;

$current_user = wp_get_current_user();
$recent_reports = Report::all( [ 'user_id' => $current_user->ID, 'limit' => 10 ] );
$schools = School::all();
?>

<div class="cqa-dashboard">
    <div class="cqa-dashboard-header">
        <h1>👋 Welcome, <?php echo esc_html( $current_user->display_name ); ?></h1>
        <a href="<?php echo home_url( '/qa-reports/new/' ); ?>" class="cqa-btn cqa-btn-primary">
            ➕ New Report
        </a>
    </div>

    <div class="cqa-stats-grid">
        <div class="cqa-stat-card">
            <div class="cqa-stat-icon">🏫</div>
            <div class="cqa-stat-content">
                <span class="cqa-stat-value"><?php echo count( $schools ); ?></span>
                <span class="cqa-stat-label">Schools</span>
            </div>
        </div>
        <div class="cqa-stat-card">
            <div class="cqa-stat-icon">📋</div>
            <div class="cqa-stat-content">
                <span class="cqa-stat-value"><?php echo count( $recent_reports ); ?></span>
                <span class="cqa-stat-label">My Reports</span>
            </div>
        </div>
        <div class="cqa-stat-card">
            <div class="cqa-stat-icon">📅</div>
            <div class="cqa-stat-content">
                <span class="cqa-stat-value"><?php echo date( 'M j' ); ?></span>
                <span class="cqa-stat-label">Today</span>
            </div>
        </div>
    </div>

    <div class="cqa-section">
        <h2>📋 Recent Reports</h2>
        
        <?php if ( empty( $recent_reports ) ) : ?>
            <div class="cqa-empty-state">
                <p>No reports yet. Create your first report!</p>
                <a href="<?php echo home_url( '/qa-reports/new/' ); ?>" class="cqa-btn cqa-btn-primary">
                    Create Report
                </a>
            </div>
        <?php else : ?>
            <div class="cqa-reports-list">
                <?php foreach ( $recent_reports as $report ) : 
                    $school = School::find( $report->school_id );
                ?>
                    <div class="cqa-report-card">
                        <div class="cqa-report-info">
                            <h3><?php echo esc_html( $school ? $school->name : 'Unknown School' ); ?></h3>
                            <p class="cqa-report-meta">
                                <?php echo esc_html( ucfirst( str_replace( '_', ' ', $report->report_type ) ) ); ?>
                                • <?php echo esc_html( date( 'M j, Y', strtotime( $report->inspection_date ) ) ); ?>
                            </p>
                        </div>
                        <div class="cqa-report-status">
                            <span class="cqa-badge cqa-badge-<?php echo esc_attr( $report->status ); ?>">
                                <?php echo esc_html( ucfirst( $report->status ) ); ?>
                            </span>
                            <?php if ( $report->overall_rating && $report->overall_rating !== 'pending' ) : ?>
                                <span class="cqa-badge cqa-badge-<?php echo esc_attr( $report->overall_rating ); ?>">
                                    <?php echo esc_html( ucfirst( str_replace( '_', ' ', $report->overall_rating ) ) ); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="cqa-report-actions">
                            <a href="<?php echo home_url( '/qa-reports/report/' . $report->id . '/' ); ?>" class="cqa-btn cqa-btn-sm">
                                View
                            </a>
                            <?php if ( $report->status === 'draft' ) : ?>
                                <a href="<?php echo home_url( '/qa-reports/edit/' . $report->id . '/' ); ?>" class="cqa-btn cqa-btn-sm cqa-btn-primary">
                                    Continue
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="cqa-section">
        <h2>🏫 Schools</h2>
        <div class="cqa-schools-grid">
            <?php foreach ( $schools as $school ) : ?>
                <div class="cqa-school-card">
                    <h3><?php echo esc_html( $school->name ); ?></h3>
                    <p><?php echo esc_html( $school->location ); ?></p>
                    <a href="<?php echo home_url( '/qa-reports/new/?school_id=' . $school->id ); ?>" class="cqa-btn cqa-btn-sm">
                        Start Report
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
