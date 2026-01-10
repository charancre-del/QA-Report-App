<?php
/**
 * Front-End Report View
 *
 * @package ChromaQAReports
 */

use ChromaQA\Models\Report;
use ChromaQA\Models\School;
use ChromaQA\Models\Photo;
use ChromaQA\Utils\Photo_Comparison;

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
$ai_summary = $report->get_ai_summary();
?>

<div class="cqa-report-view">
    <div class="cqa-report-header-card">
        <div class="cqa-report-header-info">
            <h1><?php echo esc_html( $school ? $school->name : 'Unknown School' ); ?></h1>
            <p class="cqa-report-meta">
                <?php echo esc_html( ucfirst( str_replace( '_', ' ', $report->report_type ) ) ); ?>
                • <?php echo esc_html( date( 'F j, Y', strtotime( $report->inspection_date ) ) ); ?>
            </p>
        </div>
        <div class="cqa-report-header-rating">
            <?php if ( $report->overall_rating && $report->overall_rating !== 'pending' ) : ?>
                <span class="cqa-badge cqa-badge-lg cqa-badge-<?php echo esc_attr( $report->overall_rating ); ?>">
                    <?php echo esc_html( ucfirst( str_replace( '_', ' ', $report->overall_rating ) ) ); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( $ai_summary ) : ?>
        <div class="cqa-section ai-summary-section">
            <h2>✨ AI Analysis & Plan of Improvement</h2>
            
            <div class="cqa-ai-summary-content">
                <div class="cqa-ai-executive-summary">
                    <h3>📊 Executive Summary</h3>
                    <div class="cqa-ai-text">
                        <?php echo nl2br( esc_html( $ai_summary['executive_summary'] ) ); ?>
                    </div>
                </div>

                <?php if ( ! empty( $ai_summary['issues'] ) ) : ?>
                    <div class="cqa-ai-issues">
                        <h3>⚠️ Identified Issues</h3>
                        <div class="cqa-ai-issues-grid">
                            <?php foreach ( $ai_summary['issues'] as $issue ) : ?>
                                <div class="cqa-ai-issue-card severity-<?php echo esc_attr( $issue['severity'] ?? 'medium' ); ?>">
                                    <div class="cqa-issue-meta">
                                        <span class="cqa-severity-badge"><?php echo esc_html( ucfirst( $issue['severity'] ?? 'Medium' ) ); ?></span>
                                        <span class="cqa-issue-section"><?php echo esc_html( $issue['section'] ?? 'General' ); ?></span>
                                    </div>
                                    <p><?php echo esc_html( $issue['description'] ?? $issue ); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $ai_summary['poi'] ) ) : ?>
                    <div class="cqa-ai-poi">
                        <h3>📋 Plan of Improvement</h3>
                        <div class="cqa-poi-table-wrapper">
                            <table class="cqa-poi-table">
                                <thead>
                                    <tr>
                                        <th>Priority</th>
                                        <th>Area</th>
                                        <th>Action</th>
                                        <th>Timeline</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $ai_summary['poi'] as $poi ) : ?>
                                        <?php 
                                        $action = $poi['action'] ?? $poi['recommendation'] ?? '';
                                        $area = $poi['area'] ?? $poi['section'] ?? 'General';
                                        ?>
                                        <tr>
                                            <td><span class="cqa-priority-label priority-<?php echo esc_attr( $poi['priority'] ?? 'short_term' ); ?>"><?php echo esc_html( str_replace('_', ' ', $poi['priority'] ?? 'Short Term') ); ?></span></td>
                                            <td><?php echo esc_html( $area ); ?></td>
                                            <td><?php echo esc_html( $action ); ?></td>
                                            <td><?php echo esc_html( $poi['timeline'] ?? 'TBD' ); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $photo_comparisons ) ) : ?>
        <div class="cqa-section">
            <h2>📷 Photo Comparison <?php if ( $previous_report ) : ?>(vs. <?php echo esc_html( date( 'M j, Y', strtotime( $previous_report->inspection_date ) ) ); ?>)<?php endif; ?></h2>
            
            <div class="cqa-photo-comparison-grid">
                <?php foreach ( $photo_comparisons as $comparison ) : ?>
                    <div class="cqa-comparison-card">
                        <h3><?php echo esc_html( $comparison['location_label'] ); ?></h3>
                        <div class="cqa-comparison-photos">
                            <div class="cqa-comparison-before">
                                <span class="cqa-comparison-label">Previous</span>
                                <?php if ( $comparison['previous'] ) : ?>
                                    <img src="<?php echo esc_url( $comparison['previous']['thumbnail_url'] ); ?>" alt="Before">
                                <?php else : ?>
                                    <div class="cqa-no-photo">No previous photo</div>
                                <?php endif; ?>
                            </div>
                            <div class="cqa-comparison-arrow">→</div>
                            <div class="cqa-comparison-after">
                                <span class="cqa-comparison-label">Current</span>
                                <img src="<?php echo esc_url( $comparison['current']['thumbnail_url'] ); ?>" alt="After">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

<?php
    use ChromaQA\Models\Checklist_Response;
    use ChromaQA\Checklists\Checklist_Manager;

    $responses = Checklist_Response::get_by_report_grouped( $report_id );
    $checklist = Checklist_Manager::get_checklist_for_type( $report->report_type );
    ?>

    <div class="cqa-section">
        <h2>📋 Checklist Results</h2>
        
        <div class="cqa-checklist-results">
            <?php foreach ( $checklist['sections'] as $section ) : ?>
                <?php 
                $section_responses = $responses[ $section['key'] ] ?? [];
                if ( empty( $section_responses ) ) continue;
                ?>
                <div class="cqa-checklist-section-result">
                    <h3><?php echo esc_html( $section['name'] ); ?></h3>
                    
                    <div class="cqa-checklist-items">
                        <?php foreach ( $section['items'] as $item ) : ?>
                            <?php 
                            if ( ! isset( $section_responses[ $item['key'] ] ) ) continue;
                            $response = $section_responses[ $item['key'] ];
                            
                            // Get photos for this item (handling composite key)
                            $item_photos = array_filter( $photos, function($photo) use ($section, $item) {
                                return $photo->section_key === ( $section['key'] . '|' . $item['key'] );
                            });
                            ?>
                            <div class="cqa-result-item rating-<?php echo esc_attr( $response->rating ); ?>">
                                <div class="cqa-result-header">
                                    <span class="cqa-result-rating">
                                        <?php if ( $response->rating === 'yes' ) : ?>✓ Yes
                                        <?php elseif ( $response->rating === 'sometimes' ) : ?>~ Sometimes
                                        <?php elseif ( $response->rating === 'no' ) : ?>✗ No
                                        <?php else : ?>— N/A<?php endif; ?>
                                    </span>
                                    <span class="cqa-result-label"><?php echo esc_html( $item['label'] ); ?></span>
                                </div>
                                
                                <?php if ( ! empty( $response->notes ) ) : ?>
                                    <div class="cqa-result-notes">
                                        <?php echo nl2br( esc_html( $response->notes ) ); ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ( ! empty( $item_photos ) ) : ?>
                                    <div class="cqa-result-photos">
                                        <?php foreach ( $item_photos as $photo ) : ?>
                                            <div class="cqa-result-photo">
                                                <a href="javascript:void(0)" onclick="CQAPhotoModal.open('<?php echo esc_url( $photo->get_view_url() ); ?>')" class="cqa-photo-link">
                                                    <img src="<?php echo esc_url( $photo->get_thumbnail_url( 300 ) ); ?>" alt="Evidence">
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- General Photos Section -->
    <?php
    $general_photos = array_filter( $photos, function($photo) {
        return strpos( $photo->section_key, '|' ) === false;
    });
    
    if ( ! empty( $general_photos ) ) :
    ?>
    <div class="cqa-section">
        <h2>📷 Photo Documentation</h2>
        <div class="cqa-photo-comparison-grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
            <?php foreach ( $general_photos as $photo ) : ?>
                <div class="cqa-comparison-card">
                    <div class="cqa-comparison-photos">
                        <div class="cqa-comparison-after">
                             <a href="javascript:void(0)" onclick="CQAPhotoModal.open('<?php echo esc_url( $photo->get_view_url() ); ?>')" class="cqa-photo-link">
                                <img src="<?php echo esc_url( $photo->get_thumbnail_url( 400 ) ); ?>" alt="Photo">
                            </a>
                            <?php if ( $photo->caption ) : ?>
                                <p style="margin-top:8px; font-size:12px; color:#666;"><?php echo esc_html( $photo->caption ); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="cqa-report-actions">
        <a href="<?php echo home_url( '/qa-reports/' ); ?>" class="cqa-btn">← Back to Dashboard</a>
        <a href="<?php echo home_url( '/qa-reports/report/' . $report_id . '/final/' ); ?>" class="cqa-btn cqa-btn-primary">
            📄 View Final Report
        </a>

        <?php if ( current_user_can( 'cqa_edit_reports' ) ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=chroma-qa-reports-create&id=' . $report_id ) ); ?>" class="cqa-btn">
                ✏️ Edit
            </a>
        <?php endif; ?>

        <?php if ( current_user_can( 'cqa_delete_reports' ) ) : ?>
            <button type="button" class="cqa-btn cqa-delete-report" data-id="<?php echo esc_attr( $report_id ); ?>" style="color: #ef4444; border-color: #ef4444;">
                🗑️ Delete
            </button>
        <?php endif; ?>

        <?php if ( current_user_can( 'cqa_manage_settings' ) ) : ?>
             <button type="button" class="cqa-btn" id="cqa-regenerate-ai-btn" data-id="<?php echo esc_attr( $report_id ); ?>">
                🤖 Regenerate AI
            </button>
        <?php endif; ?>

        <?php if ( $report->status === 'submitted' && current_user_can('cqa_edit_all_reports') ) : ?>
            <button type="button" class="cqa-btn cqa-btn-success" id="cqa-approve-report-btn" data-id="<?php echo esc_attr($report->id); ?>" style="background-color: #10b981; color: white;">
                ✅ Approve Report
            </button>
        <?php endif; ?>
    </div>
</div>

<style>
.cqa-report-header-card {
    background: white;
    border-radius: var(--cqa-radius);
    padding: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: var(--cqa-shadow);
    margin-bottom: 24px;
}

/* AI Summary Section Styles */
.ai-summary-section {
    background: white;
    padding: 24px;
    border-radius: var(--cqa-radius);
    box-shadow: var(--cqa-shadow);
}

.cqa-ai-summary-content h3 {
    font-size: 16px;
    margin: 24px 0 12px;
    color: var(--cqa-gray-800);
}

.cqa-ai-text {
    line-height: 1.6;
    color: var(--cqa-gray-700);
    background: #f8fafc;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid var(--cqa-primary);
}

.cqa-ai-issues-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.cqa-ai-issue-card {
    padding: 16px;
    border-radius: 8px;
    border-left: 4px solid #cbd5e1;
    background: #f8fafc;
}

.cqa-ai-issue-card.severity-high { border-left-color: #ef4444; background: #fef2f2; }
.cqa-ai-issue-card.severity-medium { border-left-color: #f59e0b; background: #fffbeb; }
.cqa-ai-issue-card.severity-low { border-left-color: #3b82f6; background: #eff6ff; }

.cqa-issue-meta {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 12px;
    font-weight: 700;
}

.cqa-severity-badge { text-transform: uppercase; }
.cqa-issue-section { color: var(--cqa-gray-500); }

.cqa-poi-table-wrapper {
    overflow-x: auto;
    margin-top: 8px;
}

.cqa-poi-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.cqa-poi-table th {
    text-align: left;
    padding: 12px;
    background: #f1f5f9;
    color: var(--cqa-gray-600);
    font-weight: 600;
}

.cqa-poi-table td {
    padding: 12px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: top;
}

.cqa-priority-label {
    font-weight: 700;
    font-size: 11px;
    text-transform: uppercase;
}
.priority-immediate { color: #dc2626; }
.priority-short_term { color: #d97706; }
.priority-ongoing { color: #2563eb; }

.cqa-badge-lg {
    font-size: 16px;
    padding: 8px 16px;
}

.cqa-photo-comparison-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.cqa-comparison-card {
    background: white;
    border-radius: var(--cqa-radius);
    padding: 16px;
    box-shadow: var(--cqa-shadow);
}

.cqa-comparison-card h3 {
    margin: 0 0 12px;
    font-size: 14px;
    color: var(--cqa-gray-700);
}

.cqa-comparison-photos {
    display: flex;
    align-items: center;
    gap: 12px;
}

.cqa-comparison-before,
.cqa-comparison-after {
    flex: 1;
    text-align: center;
}

.cqa-comparison-label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--cqa-gray-500);
    margin-bottom: 8px;
}

.cqa-comparison-photos img {
    width: 100%;
    border-radius: 8px;
    aspect-ratio: 4/3;
    object-fit: cover;
}

.cqa-comparison-arrow {
    font-size: 24px;
    color: var(--cqa-gray-400);
}

.cqa-no-photo {
    background: var(--cqa-gray-100);
    padding: 30px;
    border-radius: 8px;
    color: var(--cqa-gray-500);
    font-size: 12px;
}

.cqa-placeholder {
    text-align: center;
    color: var(--cqa-gray-500);
    padding: 40px;
    background: var(--cqa-gray-50);
    border-radius: var(--cqa-radius);
}

.cqa-report-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}

/* Photo Lightbox Modal */
.cqa-photo-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.9);
    z-index: 10000;
    justify-content: center;
    align-items: center;
}

.cqa-photo-modal.active {
    display: flex;
}

.cqa-modal-content {
    position: relative;
    max-width: 90vw;
    max-height: 90vh;
    background: white;
    border-radius: 8px;
    overflow: hidden;
}

.cqa-modal-content iframe {
    width: 80vw;
    height: 80vh;
    border: none;
}

.cqa-modal-close {
    position: absolute;
    top: -40px;
    right: 0;
    background: white;
    color: #333;
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    font-size: 24px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.cqa-modal-close:hover {
    background: #f0f0f0;
}

.cqa-photo-link {
    cursor: pointer;
}
</style>

<!-- Photo Lightbox Modal -->
<div id="cqa-photo-modal" class="cqa-photo-modal">
    <div class="cqa-modal-content">
        <button class="cqa-modal-close" onclick="CQAPhotoModal.close()">×</button>
        <iframe id="cqa-modal-iframe" src=""></iframe>
    </div>
</div>

<script>
const CQAPhotoModal = {
    open: function(url) {
        document.getElementById('cqa-modal-iframe').src = url;
        document.getElementById('cqa-photo-modal').classList.add('active');
        document.body.style.overflow = 'hidden';
    },
    close: function() {
        document.getElementById('cqa-photo-modal').classList.remove('active');
        document.getElementById('cqa-modal-iframe').src = '';
        document.body.style.overflow = '';
    }
};

// Close on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        CQAPhotoModal.close();
    }
});

// Close on background click
document.getElementById('cqa-photo-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        CQAPhotoModal.close();
    }
});
</script>
