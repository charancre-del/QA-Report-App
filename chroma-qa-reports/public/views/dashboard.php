<?php
/**
 * Front-End Dashboard
 *
 * @package ChromaQAReports
 */

use ChromaQA\Models\Report;
use ChromaQA\Models\School;
use ChromaQA\Auth\Access_Policy;

$current_user = wp_get_current_user();

$recent_args = Access_Policy::scope_report_args( [ 'limit' => 5 ] );
$recent_reports = Report::all( $recent_args );

$schools = School::all( Access_Policy::scope_school_args( [ 'limit' => 10000 ] ) );
$overdue_schools = [];
$compliance_stats = [ 'exceeds' => 0, 'meets' => 0, 'needs_improvement' => 0 ];
$overdue_cutoff = strtotime( '-90 days' );

foreach ( $schools as $visible_school ) {
    $latest_report = Report::get_latest_for_school( $visible_school->id );
    if ( $latest_report && isset( $compliance_stats[ $latest_report->overall_rating ] ) ) {
        ++$compliance_stats[ $latest_report->overall_rating ];
    }
    if ( ! $latest_report || strtotime( $latest_report->inspection_date ) < $overdue_cutoff ) {
        $visible_school->last_inspection_date = $latest_report ? $latest_report->inspection_date : null;
        $visible_school->days_since_last_report = $latest_report
            ? (int) floor( ( time() - strtotime( $latest_report->inspection_date ) ) / DAY_IN_SECONDS )
            : null;
        $overdue_schools[] = $visible_school;
    }
}
$overdue_schools = array_slice( $overdue_schools, 0, 5 );

// Calculate my stats using the new count methodology
$my_total_reports = Report::count(['user_id' => $current_user->ID]);
?>

<div class="cqa-dashboard">
    <div class="cqa-dashboard-header">
        <div>
            <h1>👋 Welcome, <?php echo esc_html( $current_user->display_name ); ?></h1>
            <p class="cqa-subtitle">Here's what's happening today.</p>
        </div>
        <?php if ( current_user_can( 'cqa_create_reports' ) ) : ?>
            <a href="<?php echo home_url( '/qa-reports/new/' ); ?>" class="cqa-btn cqa-btn-primary">
                ➕ New Report
            </a>
        <?php endif; ?>
    </div>

    <!-- Top Stats Row -->
    <div class="cqa-stats-grid">
        <a href="<?php echo home_url( '/qa-reports/schools/' ); ?>" class="cqa-stat-card-link">
            <div class="cqa-stat-card">
                <div class="cqa-stat-icon" style="background: #e0e7ff; color: #4338ca;">🏫</div>
                <div class="cqa-stat-content">
                    <span class="cqa-stat-value"><?php echo count( $schools ); ?></span>
                    <span class="cqa-stat-label">Total Schools</span>
                </div>
            </div>
        </a>
        <a href="<?php echo home_url( '/qa-reports/schools/?compliance=compliant' ); ?>" class="cqa-stat-card-link">
            <div class="cqa-stat-card">
                <div class="cqa-stat-icon" style="background: #dcfce7; color: #15803d;">✅</div>
                <div class="cqa-stat-content">
                    <span class="cqa-stat-value"><?php echo $compliance_stats['meets'] + $compliance_stats['exceeds']; ?></span>
                    <span class="cqa-stat-label">Compliant Schools</span>
                </div>
            </div>
        </a>
        <a href="<?php echo home_url( '/qa-reports/schools/?status=overdue' ); ?>" class="cqa-stat-card-link">
            <div class="cqa-stat-card">
                <div class="cqa-stat-icon" style="background: #fee2e2; color: #b91c1c;">⚠️</div>
                <div class="cqa-stat-content">
                    <span class="cqa-stat-value"><?php echo count( $overdue_schools ); ?></span>
                    <span class="cqa-stat-label">Overdue Visits</span>
                </div>
            </div>
        </a>
        <a href="<?php echo home_url( '/qa-reports/reports/?user_id=' . $current_user->ID ); ?>" class="cqa-stat-card-link">
            <div class="cqa-stat-card">
                <div class="cqa-stat-icon" style="background: #f3f4f6; color: #4b5563;">📊</div>
                <div class="cqa-stat-content">
                    <span class="cqa-stat-value"><?php echo $my_total_reports; ?></span>
                    <span class="cqa-stat-label">My Total Reports</span>
                </div>
            </div>
        </a>
    </div>

    <div class="cqa-dashboard-columns">
        <!-- Main Column -->
        <div class="cqa-main-column">
            
            <!-- Compliance Overview Chart -->
            <div class="cqa-section">
                <h2>📊 Compliance Overview</h2>
                <div class="cqa-chart-container" style="background: white; padding: 20px; border-radius: 12px; height: 300px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <canvas id="complianceChart"></canvas>
                </div>
            </div>

            <div class="cqa-section">
                <div class="cqa-section-header">
                    <h2>📋 Recent Reports</h2>
                    <a href="<?php echo home_url('/qa-reports/reports/'); ?>" class="cqa-link">View All</a>
                </div>
                
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
                                    <h3>
                                        <?php echo esc_html( $school ? $school->name : 'Unknown School' ); ?> 
                                        <span style="font-size:0.8em; color:#888;">(#<?php echo esc_html( $report->school_id ); ?>)</span>
                                    </h3>
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
                                    <?php if ( Access_Policy::can_edit_report( $report ) ) : ?>
                                        <a href="<?php echo home_url( '/qa-reports/edit/' . $report->id . '/' ); ?>" class="cqa-btn cqa-btn-sm cqa-btn-primary">
                                            <?php echo $report->status === 'draft' ? 'Continue' : 'Edit'; ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ( Access_Policy::can_delete_report( $report ) ) : ?>
                                        <button type="button" class="cqa-btn cqa-btn-sm cqa-btn-danger cqa-delete-report" data-id="<?php echo esc_attr( $report->id ); ?>" onclick="if(window.CQA) CQA.deleteReport(<?php echo esc_attr( $report->id ); ?>)">
                                            🗑️
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar Column -->
        <div class="cqa-sidebar-column">
            
            <!-- Due Schools Widget -->
            <div class="cqa-section">
                <h2>⚠️ Needs Attention</h2>
                <div class="cqa-card-list">
                    <?php if ( empty( $overdue_schools ) ) : ?>
                        <div class="cqa-empty-text">🎉 No schools explicitly overdue!</div>
                    <?php else : ?>
                        <?php foreach ( $overdue_schools as $school ) : ?>
                            <div class="cqa-mini-card">
                                <div class="cqa-mini-Icon">🏫</div>
                                <div class="cqa-mini-content">
                                    <h4><?php echo esc_html( $school->name ); ?></h4>
                                    <p class="cqa-text-danger">
                                        Last visit: <?php echo $school->last_inspection_date ? date('M Y', strtotime($school->last_inspection_date)) : 'Never'; ?>
                                        (<?php echo $school->days_since_last_report ? $school->days_since_last_report . ' days ago' : 'No reports'; ?>)
                                    </p>
                                </div>
                                <a href="<?php echo home_url( '/qa-reports/new/?school_id=' . $school->id ); ?>" class="cqa-btn-icon" title="New Report">➕</a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="cqa-section">
                <h2>🚀 Quick Actions</h2>
                <div class="cqa-quick-actions">
                    <a href="<?php echo home_url( '/qa-reports/new/' ); ?>" class="cqa-action-btn">
                        <span>📝</span> Start New Report
                    </a>
                    <a href="<?php echo home_url( '/qa-reports/schools/' ); ?>" class="cqa-action-btn">
                        <span>🏫</span> Manage Schools
                    </a>
                    <a href="<?php echo home_url( '/qa-reports/settings/' ); ?>" class="cqa-action-btn">
                        <span>⚙️</span> Settings
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
.cqa-dashboard-columns {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
}

@media (max-width: 900px) {
    .cqa-dashboard-columns {
        grid-template-columns: 1fr;
    }
}

.cqa-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.cqa-link {
    color: #4f46e5;
    text-decoration: none;
    font-weight: 500;
    font-size: 14px;
}

.cqa-card-list {
    background: white;
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.cqa-mini-card {
    display: flex;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;
}

.cqa-mini-card:last-child {
    border-bottom: none;
}

.cqa-mini-Icon {
    font-size: 20px;
    margin-right: 12px;
    background: #f3f4f6;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
}

.cqa-mini-content {
    flex: 1;
}

.cqa-mini-content h4 {
    margin: 0;
    font-size: 14px;
    color: #111827;
}

.cqa-mini-content p {
    margin: 2px 0 0;
    font-size: 12px;
    color: #6b7280;
}

.cqa-text-danger {
    color: #ef4444 !important;
}

.cqa-btn-icon {
    text-decoration: none;
    color: #4b5563;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: background 0.2s;
}

.cqa-btn-icon:hover {
    background: #f3f4f6;
}

.cqa-quick-actions {
    display: grid;
    gap: 12px;
}

.cqa-action-btn {
    display: flex;
    align-items: center;
    gap: 12px;
    background: white;
    padding: 16px;
    border-radius: 12px;
    text-decoration: none;
    color: #374151;
    font-weight: 500;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
}

.cqa-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    color: #4f46e5;
}

.cqa-stat-card-link {
    text-decoration: none;
    color: inherit;
    display: block;
    transition: transform 0.2s;
}

.cqa-stat-card-link:hover {
    transform: translateY(-4px);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Only init if Chart.js is loaded
    if (typeof Chart !== 'undefined') {
        const ctx = document.getElementById('complianceChart').getContext('2d');
        const stats = <?php echo json_encode($compliance_stats); ?>;
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: [
                    'Exceeds (' + stats.exceeds + ')', 
                    'Meets (' + stats.meets + ')', 
                    'Needs Improvement (' + stats.needs_improvement + ')'
                ],
                datasets: [{
                    data: [stats.exceeds, stats.meets, stats.needs_improvement],
                    backgroundColor: [
                        '#10b981', // Emerald 500
                        '#3b82f6', // Blue 500
                        '#ef4444'  // Red 500
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 10,
                            font: {
                                size: 12,
                                weight: '600'
                            }
                        }
                    }
                },
                cutout: '70%',
                onClick: (evt, elements) => {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        const statuses = ['exceeds', 'meets', 'needs_improvement'];
                        const targetStatus = statuses[index];
                        window.location.href = '<?php echo home_url("/qa-reports/schools/?compliance="); ?>' + targetStatus;
                    }
                }
            }
        });
    }
});
</script>
