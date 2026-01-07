<?php
/**
 * View: Legacy Report Import
 *
 * @package ChromaQAReports
 */

use ChromaQA\Models\School;

$schools = School::all(['orderby' => 'name', 'order' => 'ASC']);
?>

<div class="wrap cqa-import-wrap">
    <h1 class="wp-heading-inline">Import Legacy Report</h1>
    <hr class="wp-header-end">

    <div class="cqa-card cqa-import-card">
        <div class="cqa-import-header">
            <h2>📄 Parse Word Document</h2>
            <p>Upload an old .docx report to convert it into a structured digital report.</p>
        </div>

        <form id="cqa-import-form">
            <div class="cqa-form-group">
                <label for="import-school">School</label>
                <select id="import-school" required>
                    <option value="">Select School...</option>
                    <?php foreach ( $schools as $school ) : ?>
                        <option value="<?php echo esc_attr( $school->id ); ?>"><?php echo esc_html( $school->name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="cqa-form-group">
                <label for="import-date">Original Inspection Date</label>
                <input type="date" id="import-date" required>
            </div>

            <div class="cqa-dropzone" id="cqa-dropzone">
                <div class="cqa-dropzone-content">
                    <span class="dashicons dashicons-upload" style="font-size: 48px; width: 48px; height: 48px;"></span>
                    <p>Drag & Drop <strong>.docx</strong> file here</p>
                    <button type="button" class="button">Or Select File</button>
                    <input type="file" id="import-file" accept=".docx" hidden>
                </div>
            </div>

            <div id="cqa-import-progress" style="display:none; margin-top: 20px;">
                <div class="cqa-progress-bar">
                    <div class="cqa-progress-fill"></div>
                </div>
                <p class="cqa-progress-text">Analyzing document...</p>
            </div>

            <div class="cqa-form-actions">
                <button type="submit" class="button button-primary button-large" id="cqa-start-import" disabled>Start Import</button>
            </div>
        </form>
    </div>
</div>

<style>
.cqa-import-wrap { max-width: 800px; margin: 20px auto; }
.cqa-import-card { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.cqa-import-header { text-align: center; margin-bottom: 30px; }
.cqa-form-group { margin-bottom: 20px; }
.cqa-form-group label { display: block; font-weight: 600; margin-bottom: 8px; }
.cqa-form-group select, .cqa-form-group input { width: 100%; max-width: 100%; }
.cqa-dropzone { border: 2px dashed #ccc; border-radius: 8px; padding: 40px; text-align: center; cursor: pointer; transition: all 0.2s; }
.cqa-dropzone:hover, .cqa-dropzone.dragover { border-color: #2271b1; background: #f0f6fc; }
.cqa-progress-bar { height: 10px; background: #f0f0f1; border-radius: 5px; overflow: hidden; }
.cqa-progress-fill { height: 100%; background: #2271b1; width: 0%; transition: width 0.3s; }
.cqa-progress-text { text-align: center; font-size: 13px; color: #666; margin-top: 8px; }
</style>

<script>
jQuery(document).ready(function($) {
    const $dropzone = $('#cqa-dropzone');
    const $fileInput = $('#import-file');
    const $submitBtn = $('#cqa-start-import');
    let selectedFile = null;

    // Drag & Drop Logic
    $dropzone.on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    }).on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
    }).on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        handleFile(e.originalEvent.dataTransfer.files[0]);
    }).on('click', function() {
        $fileInput.click();
    });

    $fileInput.on('change', function(e) {
        handleFile(e.target.files[0]);
    });

    function handleFile(file) {
        if (!file || !file.name.endsWith('.docx')) {
            alert('Please select a valid .docx file.');
            return;
        }
        selectedFile = file;
        $dropzone.find('p').html(`Selected: <strong>${file.name}</strong>`);
        checkForm();
    }

    function checkForm() {
        const school = $('#import-school').val();
        const date = $('#import-date').val();
        $submitBtn.prop('disabled', !(school && date && selectedFile));
    }

    $('#import-school, #import-date').on('change', checkForm);

    $('#cqa-import-form').on('submit', function(e) {
        e.preventDefault();
        if (!selectedFile) return;

        const formData = new FormData();
        formData.append('file', selectedFile);
        formData.append('school_id', $('#import-school').val());
        formData.append('inspection_date', $('#import-date').val()); // Pass date to backend context if needed?
        // Actually, backend parser just returns JSON. We need to create report after parsing.
        
        // UI Update
        $submitBtn.prop('disabled', true).text('Processing...');
        $('#cqa-import-progress').slideDown();
        $('.cqa-progress-fill').css('width', '30%');

        // 1. Upload & Parse
        $.ajax({
            url: cqaAdmin.restUrl + 'reports/upload-doc',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', cqaAdmin.nonce );
            }
        }).done(function(parsedData) {
            $('.cqa-progress-fill').css('width', '70%');
            $('.cqa-progress-text').text('Creating report record...');

            // 2. Create Report Record
            const reportData = {
                school_id: $('#import-school').val(),
                inspection_date: $('#import-date').val(),
                report_type: parsedData.report_type || 'tier1',
                status: 'approved',
                overall_rating: parsedData.overall_rating || 'pending',
                closing_notes: parsedData.closing_notes || ''
            };

            $.ajax({
                url: cqaAdmin.restUrl + 'reports',
                method: 'POST',
                data: JSON.stringify(reportData),
                contentType: 'application/json',
                beforeSend: function ( xhr ) {
                    xhr.setRequestHeader( 'X-WP-Nonce', cqaAdmin.nonce );
                }
            }).done(function(report) {
                 // 3. Save Responses (if any)
                 if (parsedData.responses) {
                     $('.cqa-progress-fill').css('width', '90%');
                     $('.cqa-progress-text').text('Saving checklist data...');

                     $.ajax({
                        url: cqaAdmin.restUrl + 'reports/' + report.id + '/responses',
                        method: 'POST',
                        data: JSON.stringify({ responses: parsedData.responses }),
                        contentType: 'application/json',
                        beforeSend: function ( xhr ) { xhr.setRequestHeader( 'X-WP-Nonce', cqaAdmin.nonce ); }
                     }).done(function() {
                         $('.cqa-progress-fill').css('width', '100%');
                         alert('Import Successful!');
                         window.location.href = 'admin.php?page=chroma-qa-reports-view&cqa_report_id=' + report.id;
                     });
                 } else {
                     $('.cqa-progress-fill').css('width', '100%');
                     alert('Import Successful (No checklist data found)!');
                     window.location.href = 'admin.php?page=chroma-qa-reports-view&cqa_report_id=' + report.id;
                 }
            });

        }).fail(function(xhr) {
            console.error(xhr);
            alert('Import failed: ' + (xhr.responseJSON ? xhr.responseJSON.message : xhr.statusText));
            $submitBtn.prop('disabled', false).text('Start Import');
            $('#cqa-import-progress').hide();
        });
    });
});
</script>
