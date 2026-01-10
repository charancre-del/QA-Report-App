<?php
/**
 * Final Report View for Printing
 *
 * @package ChromaQAReports
 */

use ChromaQA\Models\Report;
use ChromaQA\Models\School;
use ChromaQA\Models\Photo;
use ChromaQA\Utils\Photo_Comparison;
use ChromaQA\Models\Checklist_Response;
use ChromaQA\Checklists\Checklist_Manager;

$report_id = get_query_var( 'cqa_report_id' );
$report = Report::find( $report_id );

if ( ! $report ) {
    include CQA_PLUGIN_DIR . 'public/views/404.php';
    return;
}

$school = School::find( $report->school_id );
$previous_report = $report->previous_report_id ? Report::find( $report->previous_report_id ) : null;
$photos = Photo::get_by_report( $report_id );
$photo_comparisons = $previous_report ? Photo_Comparison::get_comparison_pairs( $report_id, $previous_report->id ) : [];
$responses = Checklist_Response::get_by_report_grouped( $report_id );
$checklist = Checklist_Manager::get_checklist_for_type( $report->report_type );

// Get AI Summary
$ai_summary = $report->get_ai_summary();
?>

<div class="cqa-report-final" id="printable-report">
    <!-- Top Action Bar (Hidden during print) -->
    <div class="cqa-print-actions no-print">
        <a href="<?php echo home_url( '/qa-reports/report/' . $report_id . '/' ); ?>" class="cqa-btn">← Back to Interactive View</a>
        <button type="button" class="cqa-btn cqa-btn-primary" onclick="window.print()">
            🖨️ Print to PDF
        </button>
    </div>

    <div class="cqa-final-report-container">
        <!-- Logo & Header -->
        <div class="cqa-final-header">
            <div class="cqa-header-main">
                <h1 class="cqa-org-name">Chroma Early Learning Academy</h1>
                <h2 class="cqa-report-title">Quality Assurance Inspection Report</h2>
            </div>
            <div class="cqa-header-meta-grid">
                <div class="cqa-meta-row">
                    <span class="cqa-meta-label">School:</span>
                    <span class="cqa-meta-value"><?php echo esc_html( $school ? $school->name : 'Unknown' ); ?></span>
                </div>
                <div class="cqa-meta-row">
                    <span class="cqa-meta-label">Location:</span>
                    <span class="cqa-meta-value"><?php echo esc_html( $school ? $school->address : 'N/A' ); ?></span>
                </div>
                <div class="cqa-meta-row">
                    <span class="cqa-meta-label">Report Type:</span>
                    <span class="cqa-meta-value"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $report->report_type ) ) ); ?></span>
                </div>
                <div class="cqa-meta-row">
                    <span class="cqa-meta-label">Inspection Date:</span>
                    <span class="cqa-meta-value"><?php echo esc_html( date( 'F j, Y', strtotime( $report->inspection_date ) ) ); ?></span>
                </div>
            </div>
        </div>

        <div class="cqa-final-rating-banner cqa-rating-<?php echo esc_attr( $report->overall_rating ); ?>">
            Overall Rating: <?php echo esc_html( ucfirst( str_replace( '_', ' ', $report->overall_rating ) ) ); ?>
        </div>

        <!-- Executive Summary -->
        <?php if ( $ai_summary ) : ?>
            <div class="cqa-final-section">
                <h3>📊 Executive Summary</h3>
                <div class="cqa-final-summary-box">
                    <?php echo nl2br( esc_html( $ai_summary['executive_summary'] ) ); ?>
                </div>
            </div>

            <?php if ( ! empty( $ai_summary['issues'] ) ) : ?>
                <div class="cqa-final-section">
                    <h3>⚠️ Identified Issues</h3>
                    <table class="cqa-final-table">
                        <thead>
                            <tr>
                                <th width="20%">Severity</th>
                                <th width="25%">Section</th>
                                <th width="55%">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $ai_summary['issues'] as $issue ) : ?>
                                <tr>
                                    <td>
                                        <span class="cqa-severity-tag severity-<?php echo esc_attr( $issue['severity'] ?? 'medium' ); ?>">
                                            <?php echo esc_html( strtoupper( $issue['severity'] ?? 'MEDIUM' ) ); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html( $issue['section'] ?? 'General' ); ?></td>
                                    <td><?php echo esc_html( $issue['description'] ?? $issue ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $ai_summary['poi'] ) ) : ?>
                <div class="cqa-final-section">
                    <h3>📋 Plan of Improvement (POI)</h3>
                    <table class="cqa-final-table cqa-poi-table">
                        <thead>
                            <tr>
                                <th width="15%">Priority</th>
                                <th width="25%">Area</th>
                                <th width="40%">Action Plan</th>
                                <th width="20%">Timeline</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $ai_summary['poi'] as $poi ) : ?>
                                <?php 
                                // Support both key mappings
                                $action = $poi['action'] ?? $poi['recommendation'] ?? '';
                                $area = $poi['area'] ?? $poi['section'] ?? 'General';
                                ?>
                                <tr>
                                    <td>
                                        <span class="cqa-priority-tag priority-<?php echo esc_attr( $poi['priority'] ?? 'short_term' ); ?>">
                                            <?php echo esc_html( str_replace('_', ' ', $poi['priority'] ?? 'Short Term') ); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html( $area ); ?></td>
                                    <td><?php echo esc_html( $action ); ?></td>
                                    <td><?php echo esc_html( $poi['timeline'] ?? 'TBD' ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Photo Comparison -->
        <?php if ( ! empty( $photo_comparisons ) ) : ?>
            <div class="cqa-final-section">
                <h3>📷 Photo Comparison <?php if ( $previous_report ) : ?>(vs. <?php echo esc_html( date( 'M j, Y', strtotime( $previous_report->inspection_date ) ) ); ?>)<?php endif; ?></h3>
                <div class="cqa-final-comparison-grid">
                    <?php foreach ( $photo_comparisons as $comparison ) : ?>
                        <div class="cqa-final-comparison-item">
                            <h4><?php echo esc_html( $comparison['location_label'] ); ?></h4>
                            <div class="cqa-comparison-row">
                                <div class="cqa-comp-col">
                                    <span class="cqa-comp-label">Previous</span>
                                    <?php if ( $comparison['previous'] ) : ?>
                                        <img src="<?php echo esc_url( $comparison['previous']['thumbnail_url'] ); ?>" alt="Before">
                                    <?php else : ?>
                                        <div class="cqa-no-photo">No previous photo</div>
                                    <?php endif; ?>
                                </div>
                                <div class="cqa-comp-col">
                                    <span class="cqa-comp-label">Current</span>
                                    <img src="<?php echo esc_url( $comparison['current']['thumbnail_url'] ); ?>" alt="After">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Checklist Results -->
        <div class="cqa-final-section">
            <h3>📋 Checklist Results</h3>
            <?php foreach ( $checklist['sections'] as $section ) : ?>
                <?php 
                $section_responses = $responses[ $section['key'] ] ?? [];
                if ( empty( $section_responses ) ) continue;
                ?>
                <div class="cqa-final-checklist-section">
                    <h4><?php echo esc_html( $section['name'] ); ?></h4>
                    <table class="cqa-final-table">
                        <thead>
                            <tr>
                                <th width="15%">Rating</th>
                                <th width="45%">Item</th>
                                <th width="40%">Notes / Evidence</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $section['items'] as $item ) : ?>
                                <?php 
                                if ( ! isset( $section_responses[ $item['key'] ] ) ) continue;
                                $response = $section_responses[ $item['key'] ];
                                $item_photos = array_filter( $photos, function($photo) use ($section, $item) {
                                    return $photo->section_key === ( $section['key'] . '|' . $item['key'] );
                                });
                                ?>
                                <tr class="rating-<?php echo esc_attr( $response->rating ); ?>">
                                    <td class="cqa-cell-rating">
                                        <?php if ( $response->rating === 'yes' ) : ?>✓ Yes
                                        <?php elseif ( $response->rating === 'sometimes' ) : ?>~ Sometimes
                                        <?php elseif ( $response->rating === 'no' ) : ?>✗ No
                                        <?php else : ?>— N/A<?php endif; ?>
                                    </td>
                                    <td class="cqa-cell-label"><?php echo esc_html( $item['label'] ); ?></td>
                                    <td class="cqa-cell-notes">
                                        <?php echo nl2br( esc_html( $response->notes ) ); ?>
                                        <?php if ( ! empty( $item_photos ) ) : ?>
                                            <div class="cqa-final-item-photos">
                                                <?php foreach ( $item_photos as $photo ) : ?>
                                                    <img src="<?php echo esc_url( $photo->get_thumbnail_url( 200 ) ); ?>" class="cqa-printed-evidence">
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Documentation Photos -->
        <?php
        $general_photos = array_filter( $photos, function($photo) {
            return strpos( $photo->section_key, '|' ) === false;
        });
        if ( ! empty( $general_photos ) ) :
        ?>
        <div class="cqa-final-section page-break-before">
            <h3>📷 Additional Documentation</h3>
            <div class="cqa-final-gallery">
                <?php foreach ( $general_photos as $photo ) : ?>
                    <div class="cqa-final-gallery-item">
                        <img src="<?php echo esc_url( $photo->get_thumbnail_url( 600 ) ); ?>" alt="Documentation">
                        <?php if ( $photo->caption ) : ?>
                            <p class="cqa-gallery-caption"><?php echo esc_html( $photo->caption ); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="cqa-final-footer">
            <p>© <?php echo date('Y'); ?> Chroma Early Learning Academy. Confidential Internal Document.</p>
        </div>
    </div>

    <!-- Bottom Action Bar (Hidden during print) -->
    <div class="cqa-print-actions no-print" style="margin-top: 40px; border-top: 1px solid #e5e7eb; padding-top: 20px;">
        <button type="button" class="cqa-btn cqa-btn-primary" onclick="window.print()">
            🖨️ Print Final Report
        </button>
    </div>
</div>

<style>
/* Base styles for the Final Report View */
.cqa-report-final {
    background: #f3f4f6;
    min-height: 100vh;
    padding: 40px 20px;
}

.cqa-final-report-container {
    background: white;
    max-width: 1000px;
    margin: 0 auto;
    padding: 60px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
}

.cqa-final-header {
    border-bottom: 2px solid #6366f1;
    padding-bottom: 20px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
}

.cqa-org-name {
    color: #6366f1;
    font-size: 28px;
    margin: 0;
    font-weight: 800;
}

.cqa-report-title {
    font-size: 18px;
    color: #4b5563;
    margin: 5px 0 0;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.cqa-header-meta-grid {
    text-align: right;
}

.cqa-meta-row {
    margin-bottom: 4px;
    font-size: 14px;
}

.cqa-meta-label {
    font-weight: 700;
    color: #374151;
    margin-right: 8px;
}

.cqa-meta-value {
    color: #4b5563;
}

.cqa-final-rating-banner {
    padding: 15px;
    text-align: center;
    font-weight: 800;
    font-size: 20px;
    border-radius: 6px;
    margin-bottom: 40px;
}

.cqa-rating-exceeds { background: #dcfce7; color: #166534; border: 1px solid #166534; }
.cqa-rating-meets { background: #eff6ff; color: #1e40af; border: 1px solid #1e40af; }
.cqa-rating-needs_improvement { background: #fef2f2; color: #991b1b; border: 1px solid #991b1b; }

.cqa-severity-tag {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight : 800;
}
.severity-high { background: #fee2e2; color: #991b1b; }
.severity-medium { background: #fef3c7; color: #92400e; }
.severity-low { background: #dbeafe; color: #1e40af; }

.cqa-priority-tag {
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
}
.priority-immediate { color: #dc2626; }
.priority-short_term { color: #d97706; }
.priority-ongoing { color: #2563eb; }

.cqa-final-section {
    margin-bottom: 40px;
}

.cqa-final-section h3 {
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 10px;
    margin-bottom: 20px;
    color: #1f2937;
    font-size: 18px;
}

.cqa-final-summary-box {
    background: #f9fafb;
    padding: 20px;
    border-radius: 8px;
    line-height: 1.6;
    color: #374151;
    border-left: 4px solid #6366f1;
}

.cqa-final-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.cqa-final-table th {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    padding: 12px;
    text-align: left;
    font-size: 12px;
    text-transform: uppercase;
    color: #6b7280;
}

.cqa-final-table td {
    border: 1px solid #e5e7eb;
    padding: 12px;
    vertical-align: top;
    font-size: 14px;
}

.cqa-cell-rating { font-weight: 700; }

tr.rating-no td.cqa-cell-rating { color: #dc2626; }
tr.rating-sometimes td.cqa-cell-rating { color: #d97706; }
tr.rating-yes td.cqa-cell-rating { color: #16a34a; }

.cqa-final-item-photos {
    display: flex;
    gap: 8px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.cqa-printed-evidence {
    width: 100px;
    height: 75px;
    object-fit: cover;
    border-radius: 4px;
    border: 1px solid #e5e7eb;
}

.cqa-final-comparison-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.cqa-final-comparison-item {
    border: 1px solid #e5e7eb;
    padding: 15px;
    border-radius: 8px;
}

.cqa-final-comparison-item h4 {
    margin: 0 0 10px;
    font-size: 14px;
}

.cqa-comparison-row {
    display: flex;
    gap: 10px;
}

.cqa-comp-col { flex: 1; text-align: center; }
.cqa-comp-label { font-size: 10px; font-weight: 700; color: #9ca3af; display: block; margin-bottom: 5px; }
.cqa-comp-col img { width: 100%; height: 120px; object-fit: cover; border-radius: 4px; }

.cqa-final-gallery {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.cqa-final-gallery-item img {
    width: 100%;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.cqa-gallery-caption {
    font-size: 12px;
    color: #6b7280;
    margin-top: 8px;
    font-style: italic;
}

.cqa-final-footer {
    margin-top: 60px;
    border-top: 1px solid #e5e7eb;
    padding-top: 20px;
    text-align: center;
    font-size: 12px;
    color: #9ca3af;
}

.cqa-print-actions {
    max-width: 1000px;
    margin: 0 auto 20px;
    display: flex;
    justify-content: space-between;
}

/* PRINT OVERRIDES */
@media print {
    body { background: white !important; }
    .no-print { display: none !important; }
    .cqa-report-final { padding: 0; background: white; }
    .cqa-final-report-container { 
        box-shadow: none !important; 
        margin: 0 !important; 
        padding: 0 !important;
        max-width: none !important;
    }
    .page-break-before { page-break-before: always; }
    
    /* Ensure colors print */
    .cqa-final-rating-banner { 
        -webkit-print-color-adjust: exact; 
        print-color-adjust: exact; 
    }
    
    .cqa-final-header { border-bottom-color: #6366f1 !important; }
}
</style>
