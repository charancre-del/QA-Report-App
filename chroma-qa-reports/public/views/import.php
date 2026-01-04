<?php
/**
 * Front-End Report Import Tool
 *
 * @package ChromaQAReports
 */

if ( ! current_user_can( 'cqa_create_reports' ) ) {
    wp_die( __( 'You do not have permission to import reports.', 'chroma-qa-reports' ) );
}
?>

<div class="cqa-dashboard">
    <div class="cqa-dashboard-header">
        <div>
            <h1>📄 Import Report</h1>
            <p class="cqa-subtitle">Upload a past QA report (.docx) to automatically extract data.</p>
        </div>
        <a href="<?php echo home_url( '/qa-reports/' ); ?>" class="cqa-btn cqa-btn-secondary">
            Back to Dashboard
        </a>
    </div>

    <div class="cqa-section">
        <div class="cqa-import-container">
            <div class="cqa-upload-area" id="cqa-drop-zone">
                <div class="cqa-upload-icon">📂</div>
                <h3>Drag & Drop your DOCX file here</h3>
                <p>or click to browse</p>
                <input type="file" id="cqa-file-input" accept=".docx" hidden>
            </div>

            <div class="cqa-import-processing" style="display: none;">
                <div class="cqa-spinner"></div>
                <h3>Analyzing Document...</h3>
                <p>Gemini AI is extracting inspection data, ratings, and notes.</p>
            </div>
            
            <div class="cqa-alert cqa-alert-info">
                <strong>ℹ️ How it works:</strong> The AI will read your Word document and attempt to match content to the standard checklist. After import, you will be taken to the New Report wizard to review and finalize the data.
            </div>
        </div>
    </div>
</div>

<style>
.cqa-import-container {
    background: white;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    text-align: center;
    max-width: 600px;
    margin: 0 auto;
}

.cqa-upload-area {
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    padding: 40px;
    cursor: pointer;
    transition: all 0.2s;
}

.cqa-upload-area:hover, .cqa-upload-area.dragover {
    border-color: #4f46e5;
    background: #eef2ff;
}

.cqa-upload-icon {
    font-size: 48px;
    margin-bottom: 16px;
}

.cqa-upload-area h3 {
    margin: 0 0 8px;
    color: #111827;
}

.cqa-upload-area p {
    margin: 0;
    color: #6b7280;
}

.cqa-import-processing {
    padding: 40px;
}

.cqa-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #e5e7eb;
    border-top-color: #4f46e5;
    border-radius: 50%;
    margin: 0 auto 20px;
    animation: cqa-spin 1s linear infinite;
}

@keyframes cqa-spin {
    to { transform: rotate(360deg); }
}

.cqa-alert {
    text-align: left;
    margin-top: 32px;
    padding: 16px;
    border-radius: 8px;
    font-size: 14px;
    line-height: 1.5;
}

.cqa-alert-info {
    background: #eff6ff;
    color: #1e40af;
    border: 1px solid #dbeafe;
}
</style>

<script>
jQuery(document).ready(function($) {
    const $dropZone = $('#cqa-drop-zone');
    const $fileInput = $('#cqa-file-input');
    const $processing = $('.cqa-import-processing');

    $dropZone.on('click', function() {
        $fileInput.click();
    });

    $dropZone.on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });

    $dropZone.on('dragleave drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
    });

    $dropZone.on('drop', function(e) {
        const files = e.originalEvent.dataTransfer.files;
        if (files.length) handleFile(files[0]);
    });

    $fileInput.on('change', function() {
        if (this.files.length) handleFile(this.files[0]);
    });

    function handleFile(file) {
        if (file.name.split('.').pop().toLowerCase() !== 'docx') {
            alert('Please upload a valid .docx file.');
            return;
        }

        $dropZone.hide();
        $processing.show();

        const formData = new FormData();
        formData.append('document', file);

        $.ajax({
            url: cqaFrontend.restUrl + 'ai/parse-document',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', cqaFrontend.nonce);
            }
        }).done(function(response) {
            // Save data to session storage
            sessionStorage.setItem('cqa_imported_data', JSON.stringify(response));
            
            // Redirect to wizard
            window.location.href = cqaFrontend.homeUrl + 'new/?action=import';
        }).fail(function(xhr) {
            const error = xhr.responseJSON?.message || 'Failed to parse document.';
            alert('Error: ' + error);
            $processing.hide();
            $dropZone.show();
        });
    }
});
</script>
