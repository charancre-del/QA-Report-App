<?php
/**
 * Schools List View
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Admin;

use ChromaQA\Models\School;

// Handle delete action
if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
    if ( wp_verify_nonce( $_GET['_wpnonce'], 'cqa_delete_school' ) ) {
        $school = School::find( intval( $_GET['id'] ) );
        if ( $school ) {
            $school->delete();
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'School deleted successfully.', 'chroma-qa-reports' ) . '</p></div>';
        }
    }
}

// Get schools with pagination
$page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = 20;
$offset = ( $page - 1 ) * $per_page;

$args = [
    'limit'  => $per_page,
    'offset' => $offset,
];

if ( isset( $_GET['status'] ) && $_GET['status'] ) {
    $args['status'] = sanitize_text_field( $_GET['status'] );
}

if ( isset( $_GET['region'] ) && $_GET['region'] ) {
    $args['region'] = sanitize_text_field( $_GET['region'] );
}

$schools = School::all( $args );
$total_schools = School::count( $args );
$total_pages = ceil( $total_schools / $per_page );
$regions = School::get_regions();
?>

<div class="wrap cqa-wrap">
    <div class="cqa-header">
        <div class="cqa-header-content">
            <h1 class="cqa-title">
                <span class="dashicons dashicons-building"></span>
                <?php esc_html_e( 'Schools', 'chroma-qa-reports' ); ?>
            </h1>
            <p class="cqa-subtitle"><?php printf( esc_html__( '%d schools total', 'chroma-qa-reports' ), $total_schools ); ?></p>
        </div>
        <div class="cqa-header-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-school-edit' ) ); ?>" class="button button-primary button-hero">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e( 'Add New School', 'chroma-qa-reports' ); ?>
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="cqa-card" style="margin-bottom: 20px;">
        <div class="cqa-card-body">
            <form method="get" class="cqa-filters">
                <input type="hidden" name="page" value="chroma-qa-reports-schools">
                
                <select name="status">
                    <option value=""><?php esc_html_e( 'All Statuses', 'chroma-qa-reports' ); ?></option>
                    <option value="active" <?php selected( $_GET['status'] ?? '', 'active' ); ?>><?php esc_html_e( 'Active', 'chroma-qa-reports' ); ?></option>
                    <option value="inactive" <?php selected( $_GET['status'] ?? '', 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'chroma-qa-reports' ); ?></option>
                </select>

                <select name="region">
                    <option value=""><?php esc_html_e( 'All Regions', 'chroma-qa-reports' ); ?></option>
                    <?php foreach ( $regions as $region ) : ?>
                        <option value="<?php echo esc_attr( $region ); ?>" <?php selected( $_GET['region'] ?? '', $region ); ?>>
                            <?php echo esc_html( $region ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="button"><?php esc_html_e( 'Filter', 'chroma-qa-reports' ); ?></button>
            </form>
        </div>
    </div>

    <!-- Schools Table -->
    <div class="cqa-card">
        <div class="cqa-card-body">
            <?php if ( empty( $schools ) ) : ?>
                <div class="cqa-empty-state">
                    <span class="dashicons dashicons-building"></span>
                    <p><?php esc_html_e( 'No schools found. Add your first school to get started.', 'chroma-qa-reports' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-school-edit' ) ); ?>" class="button button-primary">
                        <?php esc_html_e( 'Add School', 'chroma-qa-reports' ); ?>
                    </a>
                </div>
            <?php else : ?>
                <table class="cqa-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name', 'chroma-qa-reports' ); ?></th>
                            <th><?php esc_html_e( 'Location', 'chroma-qa-reports' ); ?></th>
                            <th><?php esc_html_e( 'Region', 'chroma-qa-reports' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'chroma-qa-reports' ); ?></th>
                            <th><?php esc_html_e( 'Last Report', 'chroma-qa-reports' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'chroma-qa-reports' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $schools as $school ) : 
                            $recent_reports = $school->get_recent_reports( 1 );
                            $last_report = ! empty( $recent_reports ) ? $recent_reports[0] : null;
                        ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-school-edit&id=' . $school->id ) ); ?>">
                                            <?php echo esc_html( $school->name ); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo esc_html( $school->location ); ?></td>
                                <td><?php echo esc_html( $school->region ?: '—' ); ?></td>
                                <td>
                                    <span class="cqa-badge status-<?php echo esc_attr( $school->status ); ?>">
                                        <?php echo esc_html( ucfirst( $school->status ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ( $last_report ) : ?>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-view&id=' . $last_report->id ) ); ?>">
                                            <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $last_report->inspection_date ) ) ); ?>
                                        </a>
                                    <?php else : ?>
                                        <span class="cqa-text-muted"><?php esc_html_e( 'No reports', 'chroma-qa-reports' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="cqa-row-actions">
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-school-edit&id=' . $school->id ) ); ?>" class="button button-small">
                                            <?php esc_html_e( 'Edit', 'chroma-qa-reports' ); ?>
                                        </a>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-create&school_id=' . $school->id ) ); ?>" class="button button-small button-primary">
                                            <?php esc_html_e( 'New Report', 'chroma-qa-reports' ); ?>
                                        </a>
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=chroma-qa-reports-schools&action=delete&id=' . $school->id ), 'cqa_delete_school' ) ); ?>" 
                                           class="button button-small cqa-delete-btn" 
                                           onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this school?', 'chroma-qa-reports' ); ?>');">
                                            <?php esc_html_e( 'Delete', 'chroma-qa-reports' ); ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
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
}
.cqa-filters select {
    min-width: 150px;
}
.cqa-row-actions {
    display: flex;
    gap: 8px;
}
.cqa-delete-btn {
    color: #dc2626 !important;
    border-color: #dc2626 !important;
}
.cqa-delete-btn:hover {
    background: #dc2626 !important;
    color: white !important;
}
.cqa-text-muted {
    color: var(--cqa-gray-400);
}
.cqa-pagination {
    margin-top: 20px;
    text-align: center;
}
.cqa-pagination .page-numbers {
    display: inline-block;
    padding: 8px 14px;
    margin: 0 2px;
    border-radius: 6px;
    text-decoration: none;
    background: var(--cqa-gray-100);
    color: var(--cqa-gray-700);
}
.cqa-pagination .page-numbers.current {
    background: var(--cqa-primary);
    color: white;
}
.status-active { background: #dcfce7; color: #166534; }
.status-inactive { background: var(--cqa-gray-200); color: var(--cqa-gray-600); }
</style>
