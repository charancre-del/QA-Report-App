<?php
/**
 * Reports List View
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Admin;

use ChromaQA\Models\Report;
use ChromaQA\Models\School;

// Get filter values
$page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = 20;
$offset = ( $page - 1 ) * $per_page;

$args = [
    'limit'  => $per_page,
    'offset' => $offset,
];

if ( isset( $_GET['school_id'] ) && $_GET['school_id'] ) {
    $args['school_id'] = intval( $_GET['school_id'] );
}

if ( isset( $_GET['report_type'] ) && $_GET['report_type'] ) {
    $args['report_type'] = sanitize_text_field( $_GET['report_type'] );
}

if ( isset( $_GET['status'] ) && $_GET['status'] ) {
    $args['status'] = sanitize_text_field( $_GET['status'] );
}

$reports = Report::all( $args );
$total_reports = count( Report::all( array_merge( $args, [ 'limit' => 10000 ] ) ) );
$total_pages = ceil( $total_reports / $per_page );
$schools = School::all( [ 'status' => 'active', 'limit' => 100 ] );
?>

<div class="wrap cqa-wrap">
    <div class="cqa-header">
        <div class="cqa-header-content">
            <h1 class="cqa-title">
                <span class="dashicons dashicons-clipboard"></span>
                <?php esc_html_e( 'All Reports', 'chroma-qa-reports' ); ?>
            </h1>
            <p class="cqa-subtitle"><?php printf( esc_html__( '%d reports total', 'chroma-qa-reports' ), $total_reports ); ?></p>
        </div>
        <div class="cqa-header-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-create' ) ); ?>" class="button button-primary button-hero">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e( 'Create New Report', 'chroma-qa-reports' ); ?>
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="cqa-card" style="margin-bottom: 20px;">
        <div class="cqa-card-body">
            <form method="get" class="cqa-filters">
                <input type="hidden" name="page" value="chroma-qa-reports-reports">
                
                <select name="school_id">
                    <option value=""><?php esc_html_e( 'All Schools', 'chroma-qa-reports' ); ?></option>
                    <?php foreach ( $schools as $school ) : ?>
                        <option value="<?php echo esc_attr( $school->id ); ?>" <?php selected( $_GET['school_id'] ?? '', $school->id ); ?>>
                            <?php echo esc_html( $school->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="report_type">
                    <option value=""><?php esc_html_e( 'All Types', 'chroma-qa-reports' ); ?></option>
                    <option value="new_acquisition" <?php selected( $_GET['report_type'] ?? '', 'new_acquisition' ); ?>><?php esc_html_e( 'New Acquisition', 'chroma-qa-reports' ); ?></option>
                    <option value="tier1" <?php selected( $_GET['report_type'] ?? '', 'tier1' ); ?>><?php esc_html_e( 'Tier 1', 'chroma-qa-reports' ); ?></option>
                    <option value="tier1_tier2" <?php selected( $_GET['report_type'] ?? '', 'tier1_tier2' ); ?>><?php esc_html_e( 'Tier 1 + Tier 2', 'chroma-qa-reports' ); ?></option>
                </select>

                <select name="status">
                    <option value=""><?php esc_html_e( 'All Statuses', 'chroma-qa-reports' ); ?></option>
                    <option value="draft" <?php selected( $_GET['status'] ?? '', 'draft' ); ?>><?php esc_html_e( 'Draft', 'chroma-qa-reports' ); ?></option>
                    <option value="submitted" <?php selected( $_GET['status'] ?? '', 'submitted' ); ?>><?php esc_html_e( 'Submitted', 'chroma-qa-reports' ); ?></option>
                    <option value="approved" <?php selected( $_GET['status'] ?? '', 'approved' ); ?>><?php esc_html_e( 'Approved', 'chroma-qa-reports' ); ?></option>
                </select>

                <button type="submit" class="button"><?php esc_html_e( 'Filter', 'chroma-qa-reports' ); ?></button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-reports' ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'chroma-qa-reports' ); ?></a>
            </form>
        </div>
    </div>

    <!-- Reports Table -->
    <div class="cqa-card">
        <div class="cqa-card-body">
            <?php if ( empty( $reports ) ) : ?>
                <div class="cqa-empty-state">
                    <span class="dashicons dashicons-clipboard"></span>
                    <p><?php esc_html_e( 'No reports found.', 'chroma-qa-reports' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-create' ) ); ?>" class="button button-primary">
                        <?php esc_html_e( 'Create Report', 'chroma-qa-reports' ); ?>
                    </a>
                </div>
            <?php else : ?>
                <table class="cqa-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'School', 'chroma-qa-reports' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'chroma-qa-reports' ); ?></th>
                            <th><?php esc_html_e( 'Inspection Date', 'chroma-qa-reports' ); ?></th>
                            <th><?php esc_html_e( 'Rating', 'chroma-qa-reports' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'chroma-qa-reports' ); ?></th>
                            <th><?php esc_html_e( 'Comparison', 'chroma-qa-reports' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'chroma-qa-reports' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $reports as $report ) : 
                            $school = $report->get_school();
                            $previous = $report->get_previous_report();
                        ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-view&id=' . $report->id ) ); ?>">
                                            <?php echo esc_html( $school ? $school->name : __( 'Unknown', 'chroma-qa-reports' ) ); ?>
                                        </a>
                                    </strong>
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
                                <td>
                                    <?php if ( $previous ) : ?>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-view&id=' . $report->id . '&compare=1' ) ); ?>" class="cqa-compare-link">
                                            <span class="dashicons dashicons-image-flip-horizontal"></span>
                                            <?php esc_html_e( 'Compare', 'chroma-qa-reports' ); ?>
                                        </a>
                                    <?php else : ?>
                                        <span class="cqa-text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="cqa-row-actions">
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-view&id=' . $report->id ) ); ?>" class="button button-small">
                                            <?php esc_html_e( 'View', 'chroma-qa-reports' ); ?>
                                        </a>
                                        <?php if ( $report->status === 'draft' ) : ?>
                                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-create&id=' . $report->id ) ); ?>" class="button button-small button-primary">
                                                <?php esc_html_e( 'Edit', 'chroma-qa-reports' ); ?>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-view&id=' . $report->id . '&export=pdf' ) ); ?>" class="button button-small">
                                            <?php esc_html_e( 'PDF', 'chroma-qa-reports' ); ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ( $total_pages > 1 ) : ?>
                    <div class="cqa-pagination">
                        <?php
                        echo paginate_links( [
                            'base'      => add_query_arg( 'paged', '%#%' ),
                            'format'    => '',
                            'current'   => $page,
                            'total'     => $total_pages,
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                        ] );
                        ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.cqa-filters {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}
.cqa-filters select {
    min-width: 150px;
}
.cqa-row-actions {
    display: flex;
    gap: 8px;
}
.cqa-compare-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: var(--cqa-primary);
    text-decoration: none;
}
.cqa-compare-link:hover {
    color: var(--cqa-primary-dark);
}
.cqa-text-muted {
    color: var(--cqa-gray-400);
}
.cqa-pagination {
    margin-top: 20px;
    text-align: center;
}
</style>
