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
                                                <a href="<?php echo esc_url( $photo->get_view_url() ); ?>" target="_blank">
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
                             <a href="<?php echo esc_url( $photo->get_view_url() ); ?>" target="_blank">
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
        <a href="<?php echo esc_url( rest_url( 'cqa/v1/reports/' . $report_id . '/pdf?_wpnonce=' . wp_create_nonce( 'wp_rest' ) ) ); ?>" class="cqa-btn cqa-btn-primary" target="_blank">
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
