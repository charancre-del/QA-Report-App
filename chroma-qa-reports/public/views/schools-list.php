<?php
/**
 * Front-End Schools List
 *
 * @package ChromaQAReports
 */

use ChromaQA\Models\School;

if ( ! current_user_can( 'cqa_manage_schools' ) && ! current_user_can( 'cqa_regional_director' ) && ! current_user_can( 'cqa_create_reports' ) ) {
    wp_die( __( 'You do not have permission to manage schools.', 'chroma-qa-reports' ) );
}

$schools = School::all(['orderby' => 'name']);

// Debug: Check direct DB count if empty
if ( empty( $schools ) ) {
    global $wpdb;
    $table_name = School::get_table_name();
    $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
    $last_error = $wpdb->last_error;
    
    echo '<div class="cqa-notice cqa-notice-warning" style="margin: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffeeba; border-radius: 6px;">';
    echo '<strong>Debug Info:</strong><br>';
    echo "Query Result Count: DB says $count schools exist.<br>";
    echo "Table Name: $table_name<br>";
    if ( $last_error ) echo "DB Error: $last_error<br>";
    echo 'If count is > 0 but list is empty, there implies a permission or object mapping issue.';
    echo '</div>';
}
?>

<div class="cqa-dashboard">
    <div class="cqa-dashboard-header">
        <div>
            <h1>🏫 School Management</h1>
            <p class="cqa-subtitle">Manage school profiles and configurations.</p>
        </div>
        <a href="<?php echo home_url( '/qa-reports/schools/new/' ); ?>" class="cqa-btn cqa-btn-primary">
            ➕ Add School
        </a>
    </div>

    <div class="cqa-section">
        <div class="cqa-card-list-container">
            <table class="cqa-data-table">
                <thead>
                    <tr>
                        <th>School Name</th>
                        <th>Region</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Last Visit</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $schools as $school ) : 
                        $latest_report = Report::get_latest_for_school( $school->id );
                    ?>
                        <tr>
                            <td class="cqa-primary-col">
                                <strong><?php echo esc_html( $school->name ); ?></strong>
                            </td>
                            <td>
                                <span class="cqa-chip"><?php echo esc_html( $school->region ); ?></span>
                            </td>
                            <td><?php echo esc_html( $school->location ); ?></td>
                            <td>
                                <span class="cqa-status-dot <?php echo $school->status === 'active' ? 'active' : 'inactive'; ?>"></span>
                                <?php echo ucfirst( $school->status ); ?>
                            </td>
                            <td>
                                <?php echo $latest_report ? date('M j, Y', strtotime($latest_report->inspection_date)) : 'Never'; ?>
                            </td>
                            <td>
                                <div class="cqa-table-actions">
                                    <a href="<?php echo home_url( '/qa-reports/schools/edit/' . $school->id ); ?>" class="cqa-btn-icon" title="Edit">✏️</a>
                                    <a href="<?php echo home_url( '/qa-reports/new/?school_id=' . $school->id ); ?>" class="cqa-btn-icon" title="New Report">📝</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.cqa-card-list-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

.cqa-data-table {
    width: 100%;
    border-collapse: collapse;
}

.cqa-data-table th {
    background: #f9fafb;
    padding: 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    color: #6b7280;
    border-bottom: 1px solid #e5e7eb;
}

.cqa-data-table td {
    padding: 16px;
    border-bottom: 1px solid #f3f4f6;
    color: #374151;
    font-size: 14px;
}

.cqa-data-table tr:last-child td {
    border-bottom: none;
}

.cqa-primary-col {
    color: #111827;
}

.cqa-chip {
    background: #eef2ff;
    color: #4f46e5;
    padding: 4px 8px;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 500;
}

.cqa-status-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 6px;
}

.cqa-status-dot.active {
    background: #10b981;
}

.cqa-status-dot.inactive {
    background: #9ca3af;
}

.cqa-table-actions {
    display: flex;
    gap: 8px;
}
</style>
