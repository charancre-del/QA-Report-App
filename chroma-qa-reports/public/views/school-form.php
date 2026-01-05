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
                <input type="text" id="location" name="location" value="<?php echo esc_attr( $school ? $school->location : '' ); ?>" class="cqa-input" list="locations-list">
                <datalist id="locations-list">
                    <?php foreach ( School::get_locations() as $loc ) : ?>
                        <option value="<?php echo esc_attr( $loc ); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="cqa-form-group">
                <label for="acquired_date">Acquired Date</label>
                <input type="date" id="acquired_date" name="acquired_date" value="<?php echo esc_attr( $school ? $school->acquired_date : '' ); ?>" class="cqa-input">
            </div>

            <div class="cqa-form-group">
                <label>Classroom Configuration (Max Capacities)</label>
                <div class="cqa-classroom-grid">
                    <?php
                    $classroom_types = [
                        'infant_a'   => 'Infant A (1:6)',
                        'toddler'    => 'Toddler (1:8)',
                        'preschool'  => 'Preschool (1:10)',
                        'pre_k'      => 'Pre-K (1:11)',
                        'school_age' => 'School Age (1:15)',
                    ];
                    $config = $school ? $school->classroom_config : [];
                    foreach ( $classroom_types as $key => $label ) :
                        $val = $config[ $key ] ?? '';
                    ?>
                        <div class="cqa-classroom-item">
                            <label for="classroom_<?php echo $key; ?>"><?php echo esc_html( $label ); ?></label>
                            <input type="number" id="classroom_<?php echo $key; ?>" name="classroom[<?php echo $key; ?>]" value="<?php echo esc_attr( $val ); ?>" class="cqa-input">
                        </div>
                    <?php endforeach; ?>
                </div>
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

.cqa-classroom-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 16px;
    background: #f9fafb;
    padding: 16px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.cqa-classroom-item label {
    font-size: 13px !important;
    margin-bottom: 4px !important;
}

.cqa-form-actions {
    margin-top: 32px;
    display: flex;
    justify-content: flex-end;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // TIMESTAMP: <?php echo time(); ?>
    console.log('CQA: School Form Loaded (Timestamp: <?php echo time(); ?>)');

    // Wait for jQuery to be available
    var waitForJQuery = setInterval(function() {
        if (window.jQuery) {
            clearInterval(waitForJQuery);
            initSchoolForm(window.jQuery);
        }
    }, 100);

    // Timeout safety
    setTimeout(function() {
        if (!window.jQuery) {
             console.error('CQA: jQuery failed to load after 5 seconds.');
             alert('System Error: Required libraries failed to load. Please refresh the page.');
        }
    }, 5000);

    function initSchoolForm($) {
        console.log('CQA: jQuery Found, initializing form logic.');
        
        $('#cqa-school-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $btn = $('#save-school-btn');
            const originalText = $btn.text();
            
            // Console logging data
            console.log('CQA: Attempting Submit');
            
            const classroom = {};
            $form.find('input[name^="classroom"]').each(function() {
                const name = $(this).attr('name').match(/\[(.*?)\]/)[1];
                classroom[name] = $(this).val();
            });

            const formData = {
                name: $('#name').val(),
                region: $('#region').val(),
                location: $('#location').val(),
                status: $('#status').val(),
                acquired_date: $('#acquired_date').val(),
                drive_folder_id: $('#drive_folder_id').val(),
                classroom_config: classroom
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

            console.log('CQA Debug: Submitting to', url, 'method', method);
            console.log('CQA Debug: Data:', formData);

            $.ajax({
                url: url,
                method: method,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', cqaFrontend.nonce);
                },
                data: formData
            }).done(function(response) {
                console.log('CQA Debug: Save Success', response);
                alert('School saved successfully!');
                window.location.href = cqaFrontend.homeUrl + 'schools/';
            }).fail(function(xhr) {
                console.error('CQA Debug: Save Failed', xhr);
                const error = xhr.responseJSON?.message || 'Failed to save school.';
                const status = xhr.status;
                const statusText = xhr.statusText;
                alert('Error (' + status + ' ' + statusText + '): ' + error + '\n\nPlease check the browser console for details.');
            }).always(function() {
                $btn.prop('disabled', false).text(originalText);
            });
        });
    }
});
</script>
