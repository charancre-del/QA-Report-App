<?php
/**
 * Front-End School Form
 *
 * @package ChromaQAReports
 */

use ChromaQA\Models\School;

if ( ! current_user_can( 'cqa_manage_schools' ) ) {
    wp_die( __( 'You do not have permission to manage schools.', 'chroma-qa-reports' ) );
}

$action = get_query_var( 'cqa_action' ) ?: 'new';
$school_id = get_query_var( 'cqa_school_id' );
$school = null;

if ( $action === 'edit' && $school_id ) {
    $school = School::find( $school_id );
    if ( ! $school ) {
        wp_die( 'School not found' );
    }
}

$title = $action === 'edit' ? 'Edit School' : 'Add New School';
?>

<div class="cqa-dashboard">
    <div class="cqa-dashboard-header">
        <div>
            <h1><?php echo esc_html( $title ); ?></h1>
            <p class="cqa-subtitle">Complete the details below.</p>
        </div>
        <a href="<?php echo home_url( '/qa-reports/schools/' ); ?>" class="cqa-btn cqa-btn-secondary">
            Back to List
        </a>
    </div>

    <div class="cqa-form-container">
        <form id="cqa-school-form" class="cqa-form">
            <input type="hidden" name="id" value="<?php echo esc_attr( $school ? $school->id : '' ); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
            
            <div class="cqa-form-group">
                <label for="name">School Name *</label>
                <input type="text" id="name" name="name" required value="<?php echo esc_attr( $school ? $school->name : '' ); ?>" class="cqa-input">
            </div>

            <div class="cqa-form-grid">
                <div class="cqa-form-group">
                    <label for="region">Region *</label>
                    <input type="text" id="region" name="region" required value="<?php echo esc_attr( $school ? $school->region : '' ); ?>" class="cqa-input" list="regions-list">
                    <datalist id="regions-list">
                        <?php foreach ( School::get_regions() as $region ) : ?>
                            <option value="<?php echo esc_attr( $region ); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="cqa-form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="cqa-input">
                        <option value="active" <?php selected( $school ? $school->status : '', 'active' ); ?>>Active</option>
                        <option value="inactive" <?php selected( $school ? $school->status : '', 'inactive' ); ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="cqa-form-group">
                <label for="location">Location (City/Address)</label>
                <input type="text" id="location" name="location" value="<?php echo esc_attr( $school ? $school->location : '' ); ?>" class="cqa-input">
            </div>

            <div class="cqa-form-group">
                <label for="acquired_date">Acquired Date</label>
                <input type="date" id="acquired_date" name="acquired_date" value="<?php echo esc_attr( $school ? $school->acquired_date : '' ); ?>" class="cqa-input">
            </div>

            <div class="cqa-form-group">
                <label for="drive_folder_id">Google Drive Folder ID</label>
                <div class="cqa-input-with-hint">
                    <input type="text" id="drive_folder_id" name="drive_folder_id" value="<?php echo esc_attr( $school ? $school->drive_folder_id : '' ); ?>" class="cqa-input" placeholder="e.g. 1A2B3C...">
                    <small>The ID of the folder where reports and photos will be stored.</small>
                </div>
            </div>

            <div class="cqa-form-actions">
                <button type="submit" class="cqa-btn cqa-btn-primary" id="save-school-btn">
                    <?php echo $action === 'edit' ? 'Update School' : 'Create School'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.cqa-form-container {
    background: white;
    padding: 32px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    max-width: 800px;
}

.cqa-form-group {
    margin-bottom: 24px;
}

.cqa-form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #374151;
}

.cqa-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

.cqa-input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 16px;
}

.cqa-input:focus {
    border-color: #4f46e5;
    outline: none;
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
}

.cqa-input-with-hint small {
    display: block;
    margin-top: 6px;
    color: #6b7280;
    font-size: 13px;
}

.cqa-form-actions {
    margin-top: 32px;
    display: flex;
    justify-content: flex-end;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#cqa-school-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $btn = $('#save-school-btn');
        const originalText = $btn.text();
        const formData = {
            name: $('#name').val(),
            region: $('#region').val(),
            location: $('#location').val(),
            status: $('#status').val(),
            acquired_date: $('#acquired_date').val(),
            drive_folder_id: $('#drive_folder_id').val()
        };

        const action = $form.find('input[name="action"]').val();
        const id = $form.find('input[name="id"]').val();
        
        $btn.prop('disabled', true).text('Saving...');

        let method = 'POST';
        let url = cqaFrontend.restUrl + 'schools';
        
        if (action === 'edit') {
            method = 'POST'; // WP REST API update allows POST to /schools/{id}
            url += '/' + id;
        }

        $.ajax({
            url: url,
            method: method,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', cqaFrontend.nonce);
            },
            data: formData
        }).done(function(response) {
            alert('School saved successfully!');
            window.location.href = cqaFrontend.homeUrl + 'schools/';
        }).fail(function(xhr) {
            const error = xhr.responseJSON?.message || 'Failed to save school.';
            alert('Error: ' + error);
        }).always(function() {
            $btn.prop('disabled', false).text(originalText);
        });
    });
});
</script>
