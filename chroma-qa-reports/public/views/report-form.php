<?php
/**
 * Front-End Report Form
 *
 * @package ChromaQAReports
 */

use ChromaQA\Models\School;
use ChromaQA\Models\Report;
use ChromaQA\Checklists\Checklist_Manager;
use ChromaQA\Checklists\Classroom_Checklist;

$report_id = get_query_var( 'cqa_report_id' );
$school_id = isset( $_GET['school_id'] ) ? intval( $_GET['school_id'] ) : 0;
$report = null;
$schools = School::all();

if ( $report_id ) {
    $report = Report::find( $report_id );
    if ( $report ) {
        $school_id = $report->school_id;
    }
}

$school = $school_id ? School::find( $school_id ) : null;
?>

<div class="cqa-report-wizard" id="cqa-report-wizard" data-report-id="<?php echo esc_attr( $report_id ?: '' ); ?>">
    <!-- Step Progress -->
    <div class="cqa-wizard-progress">
        <div class="cqa-wizard-step active" data-step="1">
            <span class="cqa-step-number">1</span>
            <span class="cqa-step-label">Setup</span>
        </div>
        <div class="cqa-wizard-step" data-step="2">
            <span class="cqa-step-number">2</span>
            <span class="cqa-step-label">Checklist</span>
        </div>
        <div class="cqa-wizard-step" data-step="3">
            <span class="cqa-step-number">3</span>
            <span class="cqa-step-label">Photos</span>
        </div>
        <div class="cqa-wizard-step" data-step="4">
            <span class="cqa-step-number">4</span>
            <span class="cqa-step-label">Review</span>
        </div>
    </div>

    <!-- Step 1: Setup -->
    <div class="cqa-wizard-panel active" data-step="1">
        <h2>📋 Report Setup</h2>
        
        <div class="cqa-form-group">
            <label>School *</label>
            <select id="cqa-school-select" required>
                <option value="">Select a school...</option>
                <?php foreach ( $schools as $s ) : ?>
                    <option value="<?php echo esc_attr( $s->id ); ?>" <?php selected( $school_id, $s->id ); ?>>
                        <?php echo esc_html( $s->name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="cqa-form-group">
            <label>Report Type *</label>
            <select id="cqa-report-type" required>
                <option value="tier1" <?php echo $report && $report->report_type === 'tier1' ? 'selected' : ''; ?>>Tier 1</option>
                <option value="tier1_tier2" <?php echo $report && $report->report_type === 'tier1_tier2' ? 'selected' : ''; ?>>Tier 1 + Tier 2 (CQI)</option>
                <option value="new_acquisition" <?php echo $report && $report->report_type === 'new_acquisition' ? 'selected' : ''; ?>>New Acquisition</option>
            </select>
        </div>

        <div class="cqa-form-group">
            <label>Inspection Date *</label>
            <input type="date" id="cqa-inspection-date" value="<?php echo esc_attr( $report ? $report->inspection_date : date('Y-m-d') ); ?>" required>
        </div>

        <div class="cqa-form-actions">
            <button type="button" class="cqa-btn cqa-btn-primary cqa-wizard-next">
                Continue to Checklist →
            </button>
        </div>
    </div>

    <!-- Step 2: Checklist -->
    <div class="cqa-wizard-panel" data-step="2">
        <h2>✅ Checklist</h2>
        <p class="cqa-checklist-tip">💡 Use keyboard: 1=Yes, 2=Sometimes, 3=No, 4=N/A, ↑↓ to navigate</p>
        
        <div id="cqa-checklist-container">
            <!-- Loaded dynamically -->
            <div class="cqa-loading">Loading checklist...</div>
        </div>

        <div class="cqa-form-actions">
            <button type="button" class="cqa-btn cqa-wizard-prev">← Back</button>
            <button type="button" class="cqa-btn cqa-btn-primary cqa-wizard-next">Continue to Photos →</button>
        </div>
    </div>

    <!-- Step 3: Photos -->
    <div class="cqa-wizard-panel" data-step="3">
        <h2>📷 Photo Evidence</h2>
        
        <div class="cqa-photo-upload-area" id="cqa-photo-dropzone">
            <div class="cqa-dropzone-content">
                <span class="cqa-dropzone-icon">📷</span>
                <p>Drag photos here or tap to upload</p>
                <input type="file" id="cqa-photo-input" accept="image/*" multiple style="display:none;">
                <input type="file" id="cqa-camera-input" accept="image/*" capture="environment" style="display:none;">
                <div class="cqa-upload-buttons">
                    <button type="button" class="cqa-btn" onclick="document.getElementById('cqa-photo-input').click()">
                        📁 Select Files
                    </button>
                    <button type="button" class="cqa-btn" onclick="document.getElementById('cqa-camera-input').click()">
                        📸 Take Photo
                    </button>
                </div>
            </div>
        </div>

        <div id="cqa-photo-gallery" class="cqa-photo-gallery">
            <!-- Photos will be added here -->
        </div>

        <div class="cqa-form-actions">
            <button type="button" class="cqa-btn cqa-wizard-prev">← Back</button>
            <button type="button" class="cqa-btn cqa-btn-primary cqa-wizard-next">Continue to Review →</button>
        </div>
    </div>

    <!-- Step 4: Review -->
    <div class="cqa-wizard-panel" data-step="4">
        <h2>🔍 Review & Submit</h2>
        
        <div id="cqa-review-summary">
            <!-- Populated dynamically -->
        </div>

        <div class="cqa-form-group">
            <label>Overall Rating *</label>
            <div class="cqa-rating-buttons">
                <button type="button" class="cqa-rating-btn cqa-rating-exceeds" data-rating="exceeds">
                    ⭐ Exceeds
                </button>
                <button type="button" class="cqa-rating-btn cqa-rating-meets" data-rating="meets">
                    ✅ Meets
                </button>
                <button type="button" class="cqa-rating-btn cqa-rating-needs" data-rating="needs_improvement">
                    ⚠️ Needs Improvement
                </button>
            </div>
            <input type="hidden" id="cqa-overall-rating" required>
        </div>

        <div class="cqa-form-group">
            <label>Closing Notes</label>
            <textarea id="cqa-closing-notes" rows="4" placeholder="Any additional notes or observations..."></textarea>
        </div>

        <div class="cqa-ai-summary" id="cqa-ai-summary">
            <button type="button" class="cqa-btn cqa-btn-secondary" id="cqa-generate-summary">
                🤖 Generate AI Summary
            </button>
        </div>

        <div class="cqa-form-actions">
            <button type="button" class="cqa-btn cqa-wizard-prev">← Back</button>
            <button type="button" class="cqa-btn cqa-btn-secondary" id="cqa-save-draft">
                💾 Save Draft
            </button>
            <button type="button" class="cqa-btn cqa-btn-primary" id="cqa-submit-report">
                ✅ Submit Report
            </button>
        </div>
    </div>
</div>
