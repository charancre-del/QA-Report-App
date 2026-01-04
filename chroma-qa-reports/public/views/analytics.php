<?php
/**
 * Front-End Analytics Dashboard
 *
 * @package ChromaQAReports
 */

use ChromaQA\Models\Report;
use ChromaQA\Models\School;

if ( ! current_user_can( 'cqa_view_all_reports' ) ) {
    wp_die( __( 'You do not have permission to view analytics.', 'chroma-qa-reports' ) );
}

// Get Data for Charts
$reports_all = Report::all( [ 'limit' => 200, 'status' => 'approved' ] );
$schools = School::all();

// 1. Compliance Trends (Last 6 Months)
// Group reports by Month and Rating
$trends = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $trends[$month] = [ 'exceeds' => 0, 'meets' => 0, 'needs_improvement' => 0 ];
}

foreach ($reports_all as $report) {
    $month = date('Y-m', strtotime($report->inspection_date));
    if (isset($trends[$month]) && $report->overall_rating) {
        $trends[$month][$report->overall_rating]++;
    }
}

// 2. School Comparison (Counts of ratings per school)
$school_stats = [];
foreach ($schools as $school) {
    if ($school->status !== 'active') continue;
    $school_stats[$school->name] = [ 'exceeds' => 0, 'meets' => 0, 'needs_improvement' => 0 ];
}

foreach ($reports_all as $report) {
    $school = $report->get_school();
    if ($school && isset($school_stats[$school->name]) && $report->overall_rating) {
        $school_stats[$school->name][$report->overall_rating]++;
    }
}

// Filter out empty schools to keep chart clean
$school_stats = array_filter($school_stats, function($stats) {
    return array_sum($stats) > 0;
});
// Sort by 'exceeds' count
uasort($school_stats, function($a, $b) {
    return $b['exceeds'] <=> $a['exceeds'];
});
$school_stats = array_slice($school_stats, 0, 10); // Top 10

?>

<div class="cqa-dashboard">
    <div class="cqa-dashboard-header">
        <div>
            <h1>📊 Analytics Dashboard</h1>
            <p class="cqa-subtitle">Insights and trends across all schools.</p>
        </div>
        <a href="<?php echo home_url( '/qa-reports/' ); ?>" class="cqa-btn cqa-btn-secondary">
            Back to Dashboard
        </a>
    </div>

    <!-- Charts Grid -->
    <div class="cqa-charts-grid">
        
        <!-- Compliance Trends -->
        <div class="cqa-chart-card cqa-full-width">
            <h2>📈 Compliance Trends (6 Months)</h2>
            <div class="cqa-chart-container">
                <canvas id="trendsChart"></canvas>
            </div>
        </div>

        <!-- School Comparison -->
        <div class="cqa-chart-card cqa-full-width">
            <h2>🏫 Top Schools Performance</h2>
            <div class="cqa-chart-container">
                <canvas id="schoolsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<style>
.cqa-charts-grid {
    display: grid;
    gap: 24px;
}

.cqa-chart-card {
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.cqa-chart-container {
    height: 350px;
    position: relative;
}

.cqa-chart-card h2 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 18px;
    color: #111827;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart === 'undefined') return;

    // 1. Trends Chart
    const trendsCtx = document.getElementById('trendsChart').getContext('2d');
    const trendsData = <?php echo json_encode($trends); ?>;
    const months = Object.keys(trendsData);
    
    new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Exceeds',
                    data: months.map(m => trendsData[m].exceeds),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Meets',
                    data: months.map(m => trendsData[m].meets),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.3,
                    fill: true
                },
                {
                    label: 'Needs Improvement',
                    data: months.map(m => trendsData[m].needs_improvement),
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.3,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });

    // 2. Schools Chart
    const schoolsCtx = document.getElementById('schoolsChart').getContext('2d');
    const schoolsStats = <?php echo json_encode($school_stats); ?>;
    const schoolNames = Object.keys(schoolsStats);

    new Chart(schoolsCtx, {
        type: 'bar',
        data: {
            labels: schoolNames,
            datasets: [
                {
                    label: 'Exceeds',
                    data: schoolNames.map(s => schoolsStats[s].exceeds),
                    backgroundColor: '#10b981'
                },
                {
                    label: 'Meets',
                    data: schoolNames.map(s => schoolsStats[s].meets),
                    backgroundColor: '#3b82f6'
                },
                {
                    label: 'Needs Improvement',
                    data: schoolNames.map(s => schoolsStats[s].needs_improvement),
                    backgroundColor: '#ef4444'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true }
            }
        }
    });
});
</script>
