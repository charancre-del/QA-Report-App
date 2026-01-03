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

    <div class="cqa-section">
        <h2>📋 Checklist Results</h2>
        <!-- Checklist results would be loaded here -->
        <p class="cqa-placeholder">Checklist results display coming soon...</p>
    </div>

    <div class="cqa-report-actions">
        <a href="<?php echo home_url( '/qa-reports/' ); ?>" class="cqa-btn">← Back to Dashboard</a>
        <a href="<?php echo admin_url( 'admin.php?page=chroma-qa-reports-view&id=' . $report_id . '&action=pdf' ); ?>" class="cqa-btn cqa-btn-primary" target="_blank">
            📄 Download PDF
        </a>
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
</style>
