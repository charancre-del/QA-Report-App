<?php
/**
 * Report Create View (Wizard)
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Admin;

use ChromaQA\Models\Report;
use ChromaQA\Models\School;
use ChromaQA\Checklists\Checklist_Manager;

// Get existing report if editing
$report_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
$report = $report_id ? Report::find( $report_id ) : new Report();
$is_edit = (bool) $report_id;

// Pre-select school from URL
$selected_school_id = isset( $_GET['school_id'] ) ? intval( $_GET['school_id'] ) : ( $report->school_id ?? 0 );

// Get all active schools
$schools = School::all( [ 'status' => 'active', 'limit' => 100 ] );
?>

<div class="wrap cqa-wrap">
    <div class="cqa-header">
        <div class="cqa-header-content">
            <h1 class="cqa-title">
                <span class="dashicons dashicons-clipboard"></span>
                <?php echo $is_edit ? esc_html__( 'Edit Report', 'chroma-qa-reports' ) : esc_html__( 'Create New Report', 'chroma-qa-reports' ); ?>
            </h1>
        </div>
        <div class="cqa-header-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-import' ) ); ?>" class="button button-secondary" style="margin-right: 10px;">
                <span class="dashicons dashicons-upload"></span>
                <?php esc_html_e( 'Import Doc', 'chroma-qa-reports' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-reports' ) ); ?>" class="button">
                ← <?php esc_html_e( 'Back to Reports', 'chroma-qa-reports' ); ?>
            </a>
        </div>
    </div>

    <!-- Wizard Steps Indicator -->
    <div class="cqa-wizard-steps">
        <div class="cqa-step active" data-step="1">
            <span class="step-number">1</span>
            <span class="step-label"><?php esc_html_e( 'Setup', 'chroma-qa-reports' ); ?></span>
        </div>
        <div class="cqa-step" data-step="2">
            <span class="step-number">2</span>
            <span class="step-label"><?php esc_html_e( 'Checklist', 'chroma-qa-reports' ); ?></span>
        </div>
        <div class="cqa-step" data-step="3">
            <span class="step-number">3</span>
            <span class="step-label"><?php esc_html_e( 'Photos', 'chroma-qa-reports' ); ?></span>
        </div>
        <div class="cqa-step" data-step="4">
            <span class="step-number">4</span>
            <span class="step-label"><?php esc_html_e( 'AI Summary', 'chroma-qa-reports' ); ?></span>
        </div>
        <div class="cqa-step" data-step="5">
            <span class="step-number">5</span>
            <span class="step-label"><?php esc_html_e( 'Review', 'chroma-qa-reports' ); ?></span>
        </div>
    </div>

    <form id="cqa-report-form" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field( 'cqa_save_report', 'cqa_report_nonce' ); ?>
        <input type="hidden" name="report_id" id="report_id" value="<?php echo esc_attr( $report_id ); ?>">

        <!-- Step 1: Setup -->
        <div class="cqa-wizard-panel active" data-step="1">
            <div class="cqa-card">
                <div class="cqa-card-header">
                    <h2><?php esc_html_e( 'Report Setup', 'chroma-qa-reports' ); ?></h2>
                </div>
                <div class="cqa-card-body">
                    <table class="form-table">
                        <tr>
                            <th><label for="school_id"><?php esc_html_e( 'School', 'chroma-qa-reports' ); ?> <span class="required">*</span></label></th>
                            <td>
                                <select id="school_id" name="school_id" class="regular-text" required>
                                    <option value=""><?php esc_html_e( 'Select a school...', 'chroma-qa-reports' ); ?></option>
                                    <?php foreach ( $schools as $school ) : ?>
                                        <option value="<?php echo esc_attr( $school->id ); ?>" <?php selected( $selected_school_id, $school->id ); ?>>
                                            <?php echo esc_html( $school->name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="report_type"><?php esc_html_e( 'Report Type', 'chroma-qa-reports' ); ?> <span class="required">*</span></label></th>
                            <td>
                                <div class="cqa-report-types">
                                    <label class="cqa-type-option">
                                        <input type="radio" name="report_type" value="new_acquisition" <?php checked( $report->report_type ?? '', 'new_acquisition' ); ?>>
                                        <div class="cqa-type-card">
                                            <span class="cqa-type-icon">🏫</span>
                                            <strong><?php esc_html_e( 'New Acquisition', 'chroma-qa-reports' ); ?></strong>
                                            <p><?php esc_html_e( 'Initial QA for newly acquired schools', 'chroma-qa-reports' ); ?></p>
                                        </div>
                                    </label>
                                    <label class="cqa-type-option">
                                        <input type="radio" name="report_type" value="tier1" <?php checked( $report->report_type ?? 'tier1', 'tier1' ); ?>>
                                        <div class="cqa-type-card">
                                            <span class="cqa-type-icon">📋</span>
                                            <strong><?php esc_html_e( 'Tier 1', 'chroma-qa-reports' ); ?></strong>
                                            <p><?php esc_html_e( 'Standard QA inspection checklist', 'chroma-qa-reports' ); ?></p>
                                        </div>
                                    </label>
                                    <label class="cqa-type-option">
                                        <input type="radio" name="report_type" value="tier1_tier2" <?php checked( $report->report_type ?? '', 'tier1_tier2' ); ?>>
                                        <div class="cqa-type-card">
                                            <span class="cqa-type-icon">🌟</span>
                                            <strong><?php esc_html_e( 'Tier 1 + Tier 2', 'chroma-qa-reports' ); ?></strong>
                                            <p><?php esc_html_e( 'Full inspection with CQI add-on', 'chroma-qa-reports' ); ?></p>
                                        </div>
                                    </label>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="inspection_date"><?php esc_html_e( 'Inspection Date', 'chroma-qa-reports' ); ?> <span class="required">*</span></label></th>
                            <td>
                                <input type="date" id="inspection_date" name="inspection_date" 
                                       value="<?php echo esc_attr( $report->inspection_date ?? date( 'Y-m-d' ) ); ?>" required>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="previous_report_id"><?php esc_html_e( 'Compare With', 'chroma-qa-reports' ); ?></label></th>
                            <td>
                                <select id="previous_report_id" name="previous_report_id" class="regular-text">
                                    <option value=""><?php esc_html_e( 'No comparison (first report)', 'chroma-qa-reports' ); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e( 'Select a previous report to enable comparison features.', 'chroma-qa-reports' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Step 2: Checklist -->
        <div class="cqa-wizard-panel" data-step="2">
            <div class="cqa-checklist-container">
                <div class="cqa-section-nav">
                    <h3><?php esc_html_e( 'Sections', 'chroma-qa-reports' ); ?></h3>
                    <ul id="section-nav-list">
                        <!-- Populated by JavaScript -->
                    </ul>
                </div>
                <div class="cqa-section-content" id="checklist-content">
                    <div class="cqa-loading">
                        <span class="spinner is-active"></span>
                        <?php esc_html_e( 'Loading checklist...', 'chroma-qa-reports' ); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 3: Photos -->
        <div class="cqa-wizard-panel" data-step="3">
            <div class="cqa-card">
                <div class="cqa-card-header">
                    <h2><?php esc_html_e( 'Photo Documentation', 'chroma-qa-reports' ); ?></h2>
                </div>
                <div class="cqa-card-body">
                    <div class="cqa-photo-sections">
                        <div class="cqa-photo-upload-area" id="photo-upload-area">
                            <div class="cqa-upload-drop">
                                <span class="dashicons dashicons-cloud-upload"></span>
                                <p><?php esc_html_e( 'Drag and drop photos here', 'chroma-qa-reports' ); ?></p>
                                <p class="description"><?php esc_html_e( 'or', 'chroma-qa-reports' ); ?></p>
                                <button type="button" class="button" id="select-photos-btn">
                                    <span class="dashicons dashicons-format-image"></span>
                                    <?php esc_html_e( 'Select Photos', 'chroma-qa-reports' ); ?>
                                </button>
                                <button type="button" class="button button-primary" id="camera-capture-btn">
                                    <span class="dashicons dashicons-camera"></span>
                                    <?php esc_html_e( 'Take Photo', 'chroma-qa-reports' ); ?>
                                </button>
                                <button type="button" class="button" id="drive-picker-btn">
                                    <span class="dashicons dashicons-portfolio"></span>
                                    <?php esc_html_e( 'Choose from Google Drive', 'chroma-qa-reports' ); ?>
                                </button>
                            </div>
                        </div>
                        <div class="cqa-photo-gallery" id="photo-gallery">
                            <!-- Photos will be displayed here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 4: AI Summary -->
        <div class="cqa-wizard-panel" data-step="4">
            <div class="cqa-card">
                <div class="cqa-card-header">
                    <h2>
                        <span class="dashicons dashicons-superhero-alt"></span>
                        <?php esc_html_e( 'AI Executive Summary', 'chroma-qa-reports' ); ?>
                    </h2>
                </div>
                <div class="cqa-card-body">
                    <div class="cqa-ai-actions">
                        <button type="button" class="button button-primary button-hero" id="generate-summary-btn">
                            <span class="dashicons dashicons-superhero-alt"></span>
                            <?php esc_html_e( 'Generate AI Summary', 'chroma-qa-reports' ); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e( 'AI will analyze the checklist responses and generate an executive summary with issues and recommendations.', 'chroma-qa-reports' ); ?>
                        </p>
                    </div>

                    <div class="cqa-ai-result" id="ai-result" style="display: none;">
                        <h3><?php esc_html_e( 'Executive Summary', 'chroma-qa-reports' ); ?></h3>
                        <div id="executive-summary" class="cqa-summary-text"></div>

                        <h3><?php esc_html_e( 'Identified Issues', 'chroma-qa-reports' ); ?></h3>
                        <div id="issues-list" class="cqa-issues-list"></div>

                        <h3><?php esc_html_e( '📋 Plan of Improvement', 'chroma-qa-reports' ); ?></h3>
                        <div id="poi-list" class="cqa-poi-list"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 5: Review -->
        <div class="cqa-wizard-panel" data-step="5">
            <div class="cqa-card">
                <div class="cqa-card-header">
                    <h2><?php esc_html_e( 'Review & Submit', 'chroma-qa-reports' ); ?></h2>
                </div>
                <div class="cqa-card-body">
                    <div class="cqa-review-section">
                        <h3><?php esc_html_e( 'Report Summary', 'chroma-qa-reports' ); ?></h3>
                        <div id="review-summary"></div>
                    </div>

                    <div class="cqa-form-row">
                        <label for="overall_rating"><?php esc_html_e( 'Overall Rating', 'chroma-qa-reports' ); ?></label>
                        <div class="cqa-rating-options">
                            <label class="cqa-rating-option">
                                <input type="radio" name="overall_rating" value="exceeds" <?php checked( $report->overall_rating ?? '', 'exceeds' ); ?>>
                                <span class="cqa-rating-badge exceeds">✅ <?php esc_html_e( 'Exceeds', 'chroma-qa-reports' ); ?></span>
                            </label>
                            <label class="cqa-rating-option">
                                <input type="radio" name="overall_rating" value="meets" <?php checked( $report->overall_rating ?? '', 'meets' ); ?>>
                                <span class="cqa-rating-badge meets">☑️ <?php esc_html_e( 'Meets', 'chroma-qa-reports' ); ?></span>
                            </label>
                            <label class="cqa-rating-option">
                                <input type="radio" name="overall_rating" value="needs_improvement" <?php checked( $report->overall_rating ?? '', 'needs_improvement' ); ?>>
                                <span class="cqa-rating-badge needs">⚠️ <?php esc_html_e( 'Needs Improvement', 'chroma-qa-reports' ); ?></span>
                            </label>
                        </div>
                    </div>

                    <div class="cqa-form-row">
                        <label for="closing_notes"><?php esc_html_e( 'Closing Notes', 'chroma-qa-reports' ); ?></label>
                        <textarea id="closing_notes" name="closing_notes" rows="5" class="large-text"><?php echo esc_textarea( $report->closing_notes ?? '' ); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Wizard Navigation -->
        <div class="cqa-wizard-nav">
            <button type="button" class="button button-large" id="prev-step" disabled>
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <?php esc_html_e( 'Previous', 'chroma-qa-reports' ); ?>
            </button>
            <div class="cqa-wizard-progress">
                <span id="current-step">1</span> / 5
            </div>
            <button type="button" class="button button-primary button-large" id="next-step">
                <?php esc_html_e( 'Next', 'chroma-qa-reports' ); ?>
                <span class="dashicons dashicons-arrow-right-alt2"></span>
            </button>
            <button type="submit" class="button button-primary button-hero" id="submit-report" style="display: none;">
                <span class="dashicons dashicons-saved"></span>
                <?php esc_html_e( 'Submit Report', 'chroma-qa-reports' ); ?>
            </button>
            <button type="button" class="button" id="save-draft">
                <?php esc_html_e( 'Save Draft', 'chroma-qa-reports' ); ?>
            </button>
        </div>
    </form>
</div>

<style>
/* Wizard Styles */
.cqa-wizard-steps {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-bottom: 30px;
    padding: 20px;
    background: white;
    border-radius: var(--cqa-radius);
    box-shadow: var(--cqa-shadow);
}

.cqa-step {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 9999px;
    background: var(--cqa-gray-100);
    color: var(--cqa-gray-500);
    transition: all 0.3s ease;
}

.cqa-step.active {
    background: var(--cqa-primary);
    color: white;
}

.cqa-step.completed {
    background: #dcfce7;
    color: #166534;
}

.step-number {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: currentColor;
    color: white;
    font-weight: 600;
    font-size: 14px;
}

.cqa-step.active .step-number {
    background: white;
    color: var(--cqa-primary);
}

.step-label {
    font-weight: 500;
}

/* Wizard Panels */
.cqa-wizard-panel {
    display: none;
}

.cqa-wizard-panel.active {
    display: block;
}

/* Report Types */
.cqa-report-types {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

.cqa-type-option {
    cursor: pointer;
}

.cqa-type-option input {
    display: none;
}

.cqa-type-card {
    padding: 20px;
    border: 2px solid var(--cqa-gray-200);
    border-radius: var(--cqa-radius);
    text-align: center;
    transition: all 0.2s ease;
}

.cqa-type-option input:checked + .cqa-type-card {
    border-color: var(--cqa-primary);
    background: rgba(99, 102, 241, 0.05);
}

.cqa-type-icon {
    font-size: 32px;
    display: block;
    margin-bottom: 8px;
}

.cqa-type-card p {
    margin: 8px 0 0;
    font-size: 13px;
    color: var(--cqa-gray-500);
}

/* Checklist Container */
.cqa-checklist-container {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: 20px;
    min-height: 500px;
}

.cqa-section-nav {
    background: white;
    border-radius: var(--cqa-radius);
    padding: 16px;
    box-shadow: var(--cqa-shadow);
    position: sticky;
    top: 32px;
    max-height: calc(100vh - 200px);
    overflow-y: auto;
}

.cqa-section-nav h3 {
    margin: 0 0 16px;
    font-size: 14px;
    color: var(--cqa-gray-600);
    text-transform: uppercase;
}

.cqa-section-nav ul {
    list-style: none;
    margin: 0;
    padding: 0;
}

.cqa-section-nav li {
    margin-bottom: 4px;
}

.cqa-section-nav a {
    display: block;
    padding: 10px 12px;
    border-radius: var(--cqa-radius-sm);
    color: var(--cqa-gray-700);
    text-decoration: none;
    font-size: 14px;
    transition: all 0.2s;
}

.cqa-section-nav a:hover,
.cqa-section-nav a.active {
    background: var(--cqa-primary);
    color: white;
}

.cqa-section-content {
    background: white;
    border-radius: var(--cqa-radius);
    padding: 20px;
    box-shadow: var(--cqa-shadow);
}

/* Photo Upload */
.cqa-upload-drop {
    border: 2px dashed var(--cqa-gray-300);
    border-radius: var(--cqa-radius);
    padding: 40px;
    text-align: center;
    transition: all 0.2s;
}

.cqa-upload-drop:hover {
    border-color: var(--cqa-primary);
    background: rgba(99, 102, 241, 0.02);
}

.cqa-upload-drop .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: var(--cqa-gray-400);
}

/* Wizard Navigation */
.cqa-wizard-nav {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 16px;
    margin-top: 30px;
    padding: 20px;
    background: white;
    border-radius: var(--cqa-radius);
    box-shadow: var(--cqa-shadow);
}

.cqa-wizard-progress {
    font-weight: 600;
    color: var(--cqa-gray-600);
}

/* Rating Options */
.cqa-rating-options {
    display: flex;
    gap: 16px;
}

.cqa-rating-option input {
    display: none;
}

.cqa-rating-badge {
    display: inline-block;
    padding: 12px 24px;
    border: 2px solid var(--cqa-gray-200);
    border-radius: var(--cqa-radius-sm);
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 500;
}

.cqa-rating-option input:checked + .cqa-rating-badge.exceeds {
    border-color: #10b981;
    background: #dcfce7;
}

.cqa-rating-option input:checked + .cqa-rating-badge.meets {
    border-color: #f59e0b;
    background: #fef3c7;
}

.cqa-rating-option input:checked + .cqa-rating-badge.needs {
    border-color: #ef4444;
    background: #fee2e2;
}

.required { color: #dc2626; }
.cqa-form-row { margin-bottom: 20px; }
.cqa-form-row label { display: block; font-weight: 600; margin-bottom: 8px; }

.cqa-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 40px;
    color: var(--cqa-gray-500);
}

/* Photo Cards */
.cqa-photo-thumb {
    transition: transform 0.2s, box-shadow 0.2s;
}
.cqa-photo-thumb:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
}
.cqa-photo-thumb select:focus, .cqa-photo-thumb input:focus {
    border-color: var(--cqa-primary) !important;
    outline: none;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.1);
}

/* POI List */
.cqa-poi-box {
    transition: border-color 0.2s, box-shadow 0.2s;
}
.cqa-poi-box:hover {
    border-color: var(--cqa-primary) !important;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.cqa-poi-box textarea:focus {
    outline: none;
    border-color: var(--cqa-primary) !important;
}
.cqa-delete-poi-btn:hover {
    color: #ef4444 !important;
}

@media (max-width: 1024px) {
    .cqa-checklist-container {
        grid-template-columns: 1fr;
    }
    .cqa-section-nav {
        position: static;
    }
    .cqa-report-types {
        grid-template-columns: 1fr;
    }
}
</style>
