<?php
/**
 * School Edit View
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Admin;

use ChromaQA\Models\School;

$school_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
$school = $school_id ? School::find( $school_id ) : new School();
$is_new = ! $school_id;

// Handle form submission
if ( isset( $_POST['cqa_school_nonce'] ) && wp_verify_nonce( $_POST['cqa_school_nonce'], 'cqa_save_school' ) ) {
    $school->name = sanitize_text_field( $_POST['name'] );
    $school->location = sanitize_text_field( $_POST['location'] );
    $school->region = sanitize_text_field( $_POST['region'] );
    $school->acquired_date = sanitize_text_field( $_POST['acquired_date'] );
    $school->status = sanitize_text_field( $_POST['status'] );
    $school->drive_folder_id = sanitize_text_field( $_POST['drive_folder_id'] );
    
    // Parse classroom config
    $classroom_config = [];
    if ( isset( $_POST['classroom'] ) && is_array( $_POST['classroom'] ) ) {
        foreach ( $_POST['classroom'] as $key => $value ) {
            $classroom_config[ $key ] = intval( $value );
        }
    }
    $school->classroom_config = $classroom_config;

    $result = $school->save();
    
    if ( $result ) {
        $redirect_url = admin_url( 'admin.php?page=chroma-qa-reports-school-edit&id=' . $result . '&saved=1' );
        echo '<script>window.location.href = "' . esc_url( $redirect_url ) . '";</script>';
        exit;
    } else {
        global $wpdb;
        $error_message = $wpdb->last_error ?: __( 'Failed to save school. Please check your input and try again.', 'chroma-qa-reports' );
        set_transient( 'cqa_school_error', $error_message, 30 );
    }
}

// Show success/error messages
if ( isset( $_GET['saved'] ) ) {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'School saved successfully.', 'chroma-qa-reports' ) . '</p></div>';
}

if ( $error_message = get_transient( 'cqa_school_error' ) ) {
    delete_transient( 'cqa_school_error' );
    echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Error:', 'chroma-qa-reports' ) . '</strong> ' . esc_html( $error_message ) . '</p></div>';
}

$classroom_types = [
    'infant_a'   => [ 'label' => __( 'Infant A', 'chroma-qa-reports' ), 'ratio' => '1:6', 'group' => 12 ],
    'toddler'    => [ 'label' => __( 'Toddler', 'chroma-qa-reports' ), 'ratio' => '1:8', 'group' => 16 ],
    'twos'       => [ 'label' => __( 'Two-year-olds', 'chroma-qa-reports' ), 'ratio' => '1:10', 'group' => 20 ],
    'threes'     => [ 'label' => __( 'Three-year-olds', 'chroma-qa-reports' ), 'ratio' => '1:15', 'group' => 30 ],
    'fours'      => [ 'label' => __( 'Four-year-olds', 'chroma-qa-reports' ), 'ratio' => '1:18', 'group' => 36 ],
    'ga_prek'    => [ 'label' => __( 'GA Pre-K', 'chroma-qa-reports' ), 'ratio' => '1:10', 'group' => 20 ],
    'school_age' => [ 'label' => __( 'School-age', 'chroma-qa-reports' ), 'ratio' => '1:25', 'group' => 50 ],
];
?>

<div class="wrap cqa-wrap">
    <div class="cqa-header">
        <div class="cqa-header-content">
            <h1 class="cqa-title">
                <span class="dashicons dashicons-building"></span>
                <?php echo $is_new ? esc_html__( 'Add New School', 'chroma-qa-reports' ) : esc_html__( 'Edit School', 'chroma-qa-reports' ); ?>
            </h1>
        </div>
        <div class="cqa-header-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-schools' ) ); ?>" class="button">
                ← <?php esc_html_e( 'Back to Schools', 'chroma-qa-reports' ); ?>
            </a>
        </div>
    </div>

    <form method="post" action="" class="cqa-school-form">
        <?php wp_nonce_field( 'cqa_save_school', 'cqa_school_nonce' ); ?>

        <div class="cqa-form-grid">
            <!-- Basic Info -->
            <div class="cqa-card">
                <div class="cqa-card-header">
                    <h2><?php esc_html_e( 'Basic Information', 'chroma-qa-reports' ); ?></h2>
                </div>
                <div class="cqa-card-body">
                    <table class="form-table">
                        <tr>
                            <th><label for="name"><?php esc_html_e( 'School Name', 'chroma-qa-reports' ); ?> <span class="required">*</span></label></th>
                            <td>
                                <input type="text" id="name" name="name" value="<?php echo esc_attr( $school->name ?? '' ); ?>" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="location"><?php esc_html_e( 'Location/Address', 'chroma-qa-reports' ); ?></label></th>
                            <td>
                                <input type="text" id="location" name="location" value="<?php echo esc_attr( $school->location ?? '' ); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="region"><?php esc_html_e( 'Region', 'chroma-qa-reports' ); ?></label></th>
                            <td>
                                <input type="text" id="region" name="region" value="<?php echo esc_attr( $school->region ?? '' ); ?>" class="regular-text" list="regions-list">
                                <datalist id="regions-list">
                                    <?php foreach ( School::get_regions() as $region ) : ?>
                                        <option value="<?php echo esc_attr( $region ); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                                <p class="description"><?php esc_html_e( 'Enter a region name or select from existing', 'chroma-qa-reports' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="acquired_date"><?php esc_html_e( 'Acquired Date', 'chroma-qa-reports' ); ?></label></th>
                            <td>
                                <input type="date" id="acquired_date" name="acquired_date" value="<?php echo esc_attr( $school->acquired_date ?? '' ); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="status"><?php esc_html_e( 'Status', 'chroma-qa-reports' ); ?></label></th>
                            <td>
                                <select id="status" name="status">
                                    <option value="active" <?php selected( $school->status ?? 'active', 'active' ); ?>><?php esc_html_e( 'Active', 'chroma-qa-reports' ); ?></option>
                                    <option value="inactive" <?php selected( $school->status ?? '', 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'chroma-qa-reports' ); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Classroom Configuration -->
            <div class="cqa-card">
                <div class="cqa-card-header">
                    <h2><?php esc_html_e( 'Classroom Configuration', 'chroma-qa-reports' ); ?></h2>
                </div>
                <div class="cqa-card-body">
                    <p class="description" style="margin-bottom: 15px;">
                        <?php esc_html_e( 'Enter the number of classrooms for each age group at this school.', 'chroma-qa-reports' ); ?>
                    </p>
                    <table class="cqa-classroom-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Age Group', 'chroma-qa-reports' ); ?></th>
                                <th><?php esc_html_e( 'Ratio', 'chroma-qa-reports' ); ?></th>
                                <th><?php esc_html_e( 'Group Size', 'chroma-qa-reports' ); ?></th>
                                <th><?php esc_html_e( '# Classrooms', 'chroma-qa-reports' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $classroom_types as $key => $type ) : 
                                $current_value = $school->classroom_config[ $key ] ?? 0;
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $type['label'] ); ?></strong></td>
                                    <td><?php echo esc_html( $type['ratio'] ); ?></td>
                                    <td><?php echo esc_html( $type['group'] ); ?></td>
                                    <td>
                                        <input type="number" name="classroom[<?php echo esc_attr( $key ); ?>]" 
                                               value="<?php echo esc_attr( $current_value ); ?>" 
                                               min="0" max="20" style="width: 80px;">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Google Drive -->
            <div class="cqa-card">
                <div class="cqa-card-header">
                    <h2><?php esc_html_e( 'Google Drive', 'chroma-qa-reports' ); ?></h2>
                </div>
                <div class="cqa-card-body">
                    <table class="form-table">
                        <tr>
                            <th><label for="drive_folder_id"><?php esc_html_e( 'Folder ID', 'chroma-qa-reports' ); ?></label></th>
                            <td>
                                <input type="text" id="drive_folder_id" name="drive_folder_id" value="<?php echo esc_attr( $school->drive_folder_id ?? '' ); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e( 'The Google Drive folder ID for this school\'s photos and documents.', 'chroma-qa-reports' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary button-hero">
                <span class="dashicons dashicons-saved"></span>
                <?php echo $is_new ? esc_html__( 'Add School', 'chroma-qa-reports' ) : esc_html__( 'Save Changes', 'chroma-qa-reports' ); ?>
            </button>
            
            <?php if ( ! $is_new ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-create&school_id=' . $school->id ) ); ?>" class="button button-secondary button-hero">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php esc_html_e( 'Create Report', 'chroma-qa-reports' ); ?>
                </a>
            <?php endif; ?>
        </p>
    </form>
</div>

<style>
.cqa-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}
.cqa-classroom-table {
    width: 100%;
    border-collapse: collapse;
}
.cqa-classroom-table th,
.cqa-classroom-table td {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid var(--cqa-gray-200);
}
.cqa-classroom-table th {
    font-weight: 600;
    color: var(--cqa-gray-600);
    font-size: 13px;
}
.required {
    color: #dc2626;
}
</style>
