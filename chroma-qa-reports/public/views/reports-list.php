<?php
/**
 * Front-End Reports List
 *
 * @package ChromaQAReports
 */

use ChromaQA\Models\Report;
use ChromaQA\Models\School;

if ( ! is_user_logged_in() ) {
    wp_redirect( home_url( '/qa-reports/login/' ) );
    exit;
}

// Get filter params
$filter_school = isset( $_GET['school_id'] ) ? intval( $_GET['school_id'] ) : '';
$filter_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
$filter_type = isset( $_GET['report_type'] ) ? sanitize_text_field( $_GET['report_type'] ) : '';
$filter_user = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : '';
$page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$limit = 20;

// Build query args
$args = [
    'limit'  => $limit,
    'offset' => ( $page - 1 ) * $limit,
];

if ( $filter_school ) $args['school_id'] = $filter_school;
if ( $filter_status ) $args['status'] = $filter_status;
if ( $filter_type ) $args['report_type'] = $filter_type;
if ( $filter_user ) $args['user_id'] = $filter_user;

// Limit to own reports if not admin/director
if ( ! current_user_can( 'cqa_view_all_reports' ) ) {
    $args['user_id'] = get_current_user_id();
}

$reports = Report::all( $args );
// Count distinct for pagination? Report model doesn't support complex count yet efficiently without duplication. 
// Just showing simple pagination controls.
$has_next = count( $reports ) === $limit; // Approximation

$schools = School::all();
?>

<div class="cqa-dashboard">
    <div class="cqa-dashboard-header">
        <div>
            <h1>📊 All Reports</h1>
            <p class="cqa-subtitle">View and manage all QA reports.</p>
        </div>
        <a href="<?php echo home_url( '/qa-reports/new/' ); ?>" class="cqa-btn cqa-btn-primary">
            ➕ New Report
        </a>
    </div>

    <!-- Filters -->
    <div class="cqa-section cqa-filter-bar">
        <form method="GET" class="cqa-filter-form">
            <div class="cqa-filter-group">
                <select name="school_id" class="cqa-select">
                    <option value="">All Schools</option>
                    <?php foreach ( $schools as $school ) : ?>
                        <option value="<?php echo $school->id; ?>" <?php selected( $filter_school, $school->id ); ?>>
                            <?php echo esc_html( $school->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="cqa-filter-group">
                <select name="status" class="cqa-select">
                    <option value="">All Statuses</option>
                    <option value="draft" <?php selected( $filter_status, 'draft' ); ?>>Draft</option>
                    <option value="submitted" <?php selected( $filter_status, 'submitted' ); ?>>Submitted</option>
                    <option value="approved" <?php selected( $filter_status, 'approved' ); ?>>Approved</option>
                </select>
            </div>

            <div class="cqa-filter-group">
                <select name="report_type" class="cqa-select">
                    <option value="">All Types</option>
                    <option value="tier1" <?php selected( $filter_type, 'tier1' ); ?>>Tier 1</option>
                    <option value="tier1_tier2" <?php selected( $filter_type, 'tier1_tier2' ); ?>>Tier 1 + Tier 2</option>
                    <option value="new_acquisition" <?php selected( $filter_type, 'new_acquisition' ); ?>>New Acquisition</option>
                </select>
            </div>

            <button type="submit" class="cqa-btn cqa-btn-secondary">Filter</button>
            <?php if ( $filter_school || $filter_status || $filter_type || $filter_user ) : ?>
                <a href="<?php echo home_url( '/qa-reports/reports/' ); ?>" class="cqa-btn-text">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Reports List -->
    <div class="cqa-section">
        <?php if ( empty( $reports ) ) : ?>
            <div class="cqa-empty-state">
                <p>No reports found matching your criteria.</p>
                <?php if ( $filter_school || $filter_status || $filter_type ) : ?>
                    <a href="<?php echo home_url( '/qa-reports/reports/' ); ?>" class="cqa-btn cqa-btn-secondary">Clear Filters</a>
                <?php else : ?>
                    <a href="<?php echo home_url( '/qa-reports/new/' ); ?>" class="cqa-btn cqa-btn-primary">Create Report</a>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div class="cqa-table-responsive">
                <table class="cqa-data-table">
                    <thead>
                        <tr>
                            <th>School</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Rating</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Map schools for efficient lookup
                        $school_map = [];
                        foreach ( $schools as $s ) {
                            $school_map[ $s->id ] = $s;
                        }

                        // Attach school data and Sort
                        // Sort by Region DESC (so meaningful regions come first), then School Name ASC, then Report Date DESC
                        usort( $reports, function( $a, $b ) use ( $school_map ) {
                            $school_a = $school_map[ $a->school_id ] ?? null;
                            $school_b = $school_map[ $b->school_id ] ?? null;

                            $region_a = $school_a ? ( $school_a->region ?: 'Unassigned' ) : 'Z_Unknown';
                            $region_b = $school_b ? ( $school_b->region ?: 'Unassigned' ) : 'Z_Unknown';

                            // 1. Sort by Region
                            $region_compare = strcmp( $region_a, $region_b );
                            if ( $region_compare !== 0 ) return $region_compare;

                            // 2. Sort by School Name
                            $name_a = $school_a ? $school_a->name : '';
                            $name_b = $school_b ? $school_b->name : '';
                            $name_compare = strcmp( $name_a, $name_b );
                            if ( $name_compare !== 0 ) return $name_compare;

                            // 3. Sort by Date (Newest First)
                            return strtotime( $b->inspection_date ) - strtotime( $a->inspection_date );
                        });

                        $current_region = null;
                        $current_school_id = null;

                        foreach ( $reports as $report ) : 
                            $school = $school_map[ $report->school_id ] ?? null;
                            $region = $school ? ( $school->region ?: 'Unassigned' ) : 'Unknown';
                            
                            // Region Header
                            if ( $region !== $current_region ) {
                                $current_region = $region;
                                $current_school_id = null; // Reset school on region change
                                echo '<tr class="cqa-region-header"><td colspan="6">' . esc_html( $region ) . '</td></tr>';
                            }

                            // School Header
                            if ( $school && $school->id !== $current_school_id ) {
                                $current_school_id = $school->id;
                                echo '<tr class="cqa-school-header"><td colspan="6">🏫 ' . esc_html( $school->name ) . ' <span class="cqa-text-muted" style="font-weight:normal;font-size:12px;margin-left:8px;">' . esc_html( $school->location ) . '</span></td></tr>';
                            }
                        ?>
                            <tr class="cqa-report-row">
                                <td class="cqa-primary-col" style="padding-left: 32px;">
                                    <?php echo esc_html( $school ? $school->name : 'Unknown School' ); ?>
                                </td>
                                <td>
                                    <?php echo esc_html( date( 'M j, Y', strtotime( $report->inspection_date ) ) ); ?>
                                </td>
                                <td>
                                    <?php echo esc_html( $report->get_type_label() ); ?>
                                </td>
                                <td>
                                    <span class="cqa-badge cqa-badge-<?php echo esc_attr( $report->status ); ?>">
                                        <?php echo esc_html( ucfirst( $report->status ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ( $report->overall_rating && $report->overall_rating !== 'pending' ) : ?>
                                        <span class="cqa-badge cqa-badge-<?php echo esc_attr( $report->overall_rating ); ?>">
                                            <?php echo esc_html( $report->get_rating_label() ); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="cqa-text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="cqa-table-actions">
                                        <a href="<?php echo home_url( '/qa-reports/report/' . $report->id . '/' ); ?>" class="cqa-btn-icon" title="View">👁️</a>
                                        <?php if ( current_user_can( 'cqa_edit_all_reports' ) || $report->user_id === get_current_user_id() ) : ?>
                                            <a href="<?php echo home_url( '/qa-reports/edit/' . $report->id . '/' ); ?>" class="cqa-btn-icon" title="Edit">✏️</a>
                                        <?php endif; ?>
                                        <a href="<?php echo home_url( '/qa-reports/new/?action=duplicate&id=' . $report->id ); ?>" class="cqa-btn-icon" title="Duplicate">📋</a>
                                        <?php if ( current_user_can( 'cqa_delete_reports' ) || $report->user_id === get_current_user_id() ) : ?>
                                            <button type="button" class="cqa-btn-icon cqa-delete-report" style="background:none;border:none;cursor:pointer;" data-id="<?php echo esc_attr( $report->id ); ?>" onclick="if(window.CQA) CQA.deleteReport(<?php echo esc_attr( $report->id ); ?>)" title="Delete">🗑️</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="cqa-pagination">
                <?php if ( $page > 1 ) : ?>
                    <a href="<?php echo add_query_arg( 'paged', $page - 1 ); ?>" class="cqa-btn cqa-btn-secondary">Previous</a>
                <?php endif; ?>
                
                <span class="cqa-page-info">Page <?php echo $page; ?></span>

                <?php if ( $has_next ) : ?>
                     <a href="<?php echo add_query_arg( 'paged', $page + 1 ); ?>" class="cqa-btn cqa-btn-secondary">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.cqa-filter-bar {
    padding: 16px;
    background: white;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
}

.cqa-filter-form {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
    width: 100%;
}

.cqa-select {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    min-width: 160px;
}

.cqa-btn-text {
    color: #6b7280;
    text-decoration: none;
    font-size: 14px;
    padding: 0 8px;
}

.cqa-btn-text:hover {
    color: #1f2937;
}

.cqa-table-responsive {
    overflow-x: auto;
}

.cqa-data-table {
    width: 100%;
    border-collapse: collapse;
}

.cqa-data-table th, .cqa-data-table td {
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid #f3f4f6;
}

.cqa-data-table th {
    background: #f9fafb;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    color: #6b7280;
}

.cqa-data-table tr:last-child td {
    border-bottom: none;
}

.cqa-text-muted {
    color: #9ca3af;
}

.cqa-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 16px;
    margin-top: 24px;
}

.cqa-page-info {
    font-size: 14px;

.cqa-region-header td {
    background: #e0e7ff;
    color: #3730a3;
    font-weight: 700;
    font-size: 16px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 2px solid #c7d2fe;
}

.cqa-school-header td {
    background: #f1f5f9;
    color: #1f2937;
    font-weight: 600;
    padding-left: 24px;
    font-size: 14px;
    border-bottom: 1px solid #e2e8f0;
}
</style>
