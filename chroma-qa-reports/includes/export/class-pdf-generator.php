<?php
/**
 * PDF Generator (Enhanced with Comparison Format)
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Export;

use ChromaQA\Models\Report;
use ChromaQA\Models\Checklist_Response;
use ChromaQA\Checklists\Checklist_Manager;

/**
 * Generates PDF reports matching the original QA Report format with comparison notes.
 */
class PDF_Generator {

    /**
     * Generate a PDF for a report.
     *
     * @param Report $report The report to export.
     * @param bool   $include_comparison Whether to include comparison data.
     * @return string|WP_Error Path to generated PDF.
     */
    public function generate( Report $report, $include_comparison = true ) {
        // Check for TCPDF or DOMPDF
        if ( ! class_exists( 'TCPDF' ) && ! class_exists( 'Dompdf\\Dompdf' ) ) {
            // Generate HTML-based PDF using browser print
            return $this->generate_html_pdf( $report, $include_comparison );
        }

        if ( class_exists( 'TCPDF' ) ) {
            return $this->generate_tcpdf( $report, $include_comparison );
        }

        return $this->generate_dompdf( $report, $include_comparison );
    }

    /**
     * Generate HTML for PDF - Matching original document format.
     *
     * @param Report $report Report object.
     * @param bool   $include_comparison Include comparison column.
     * @return string HTML content.
     */
    private function get_report_html( Report $report, $include_comparison = true ) {
        $school = $report->get_school();
        $responses = Checklist_Response::get_by_report_grouped( $report->id );
        $checklist = Checklist_Manager::get_checklist_for_type( $report->report_type );
        $ai_summary = $report->get_ai_summary();
        $previous_report = $include_comparison ? $report->get_previous_report() : null;
        $previous_responses = $previous_report ? Checklist_Response::get_by_report_grouped( $previous_report->id ) : [];
        $company_name = get_option( 'cqa_company_name', 'Chroma Early Learning Academy' );

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html( $school ? $school->name : 'QA Report' ); ?> - QA Report</title>
            <style>
                * {
                    box-sizing: border-box;
                }
                body {
                    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
                    font-size: 10pt;
                    line-height: 1.4;
                    color: #333;
                    margin: 0;
                    padding: 15px;
                }
                .header {
                    text-align: center;
                    padding-bottom: 15px;
                    margin-bottom: 20px;
                    border-bottom: 3px solid #6366f1;
                }
                .header h1 {
                    font-size: 20pt;
                    color: #6366f1;
                    margin: 0 0 5px;
                }
                .header .subtitle {
                    font-size: 14pt;
                    color: #666;
                }
                .meta-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                    background: #f8f9fa;
                }
                .meta-table td {
                    padding: 8px 12px;
                    border: 1px solid #e0e0e0;
                }
                .meta-table .label {
                    font-weight: bold;
                    width: 120px;
                    background: #f0f0f0;
                }
                
                /* Overall Rating Box */
                .rating-box {
                    text-align: center;
                    padding: 15px;
                    margin-bottom: 20px;
                    border-radius: 8px;
                    font-size: 16pt;
                    font-weight: bold;
                }
                .rating-box.exceeds {
                    background: #d1fae5;
                    border: 2px solid #10b981;
                    color: #047857;
                }
                .rating-box.meets {
                    background: #fef3c7;
                    border: 2px solid #f59e0b;
                    color: #b45309;
                }
                .rating-box.needs_improvement {
                    background: #fee2e2;
                    border: 2px solid #ef4444;
                    color: #b91c1c;
                }
                
                /* Section Headers */
                .section {
                    margin-bottom: 25px;
                    page-break-inside: avoid;
                }
                .section h2 {
                    font-size: 12pt;
                    color: #fff;
                    background: #6366f1;
                    padding: 8px 12px;
                    margin: 0 0 0 0;
                    border-radius: 4px 4px 0 0;
                }
                .tier2-badge {
                    display: inline-block;
                    background: #fbbf24;
                    color: #78350f;
                    font-size: 9pt;
                    padding: 2px 8px;
                    border-radius: 10px;
                    margin-left: 8px;
                }
                
                /* Checklist Table - Matching original format */
                .checklist-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 0;
                }
                .checklist-table th,
                .checklist-table td {
                    padding: 8px 10px;
                    text-align: left;
                    border: 1px solid #d0d0d0;
                    vertical-align: top;
                }
                .checklist-table th {
                    background: #e8e8e8;
                    font-weight: bold;
                    font-size: 9pt;
                }
                .checklist-table th.col-item { width: 40%; }
                .checklist-table th.col-rating { width: 12%; text-align: center; }
                .checklist-table th.col-previous { width: 12%; text-align: center; background: #e8f5e9; }
                .checklist-table th.col-notes { width: 24%; }
                .checklist-table th.col-comparison { width: 12%; background: #e8f5e9; }
                
                .checklist-table td.rating-cell {
                    text-align: center;
                    font-weight: bold;
                }
                .checklist-table .rating-yes { color: #16a34a; }
                .checklist-table .rating-sometimes { color: #f59e0b; }
                .checklist-table .rating-no { color: #dc2626; font-weight: bold; }
                .checklist-table .rating-na { color: #9ca3af; }
                
                /* Comparison column - GREEN highlighting like sample docs */
                .checklist-table .comparison-cell {
                    background: #f0fdf4;
                    font-size: 9pt;
                }
                .checklist-table .comparison-cell.improved {
                    color: #16a34a;
                }
                .checklist-table .comparison-cell.regressed {
                    color: #dc2626;
                }
                
                /* Follow-up notes in GREEN like original docs */
                .followup-note {
                    background: #dcfce7;
                    color: #166534;
                    padding: 4px 8px;
                    border-radius: 3px;
                    font-size: 9pt;
                    margin-top: 4px;
                    display: inline-block;
                }
                
                /* Row highlighting for issues */
                .row-issue {
                    background: #fef2f2;
                }
                .row-sometimes {
                    background: #fffbeb;
                }
                
                /* AI Summary Section */
                .ai-summary {
                    background: #f0f9ff;
                    border: 1px solid #0ea5e9;
                    border-radius: 8px;
                    padding: 15px;
                    margin-bottom: 20px;
                }
                .ai-summary h3 {
                    color: #0369a1;
                    margin: 0 0 10px 0;
                    font-size: 12pt;
                }
                .ai-summary p {
                    margin: 0 0 10px 0;
                    line-height: 1.6;
                }
                
                /* Issues List */
                .issues-list {
                    margin: 15px 0;
                }
                .issue-item {
                    padding: 8px 12px;
                    margin-bottom: 6px;
                    border-radius: 4px;
                    border-left: 4px solid;
                }
                .issue-item.high {
                    background: #fee2e2;
                    border-left-color: #dc2626;
                }
                .issue-item.medium {
                    background: #fef3c7;
                    border-left-color: #f59e0b;
                }
                .issue-item.low {
                    background: #dbeafe;
                    border-left-color: #3b82f6;
                }
                .issue-severity {
                    display: inline-block;
                    padding: 2px 6px;
                    border-radius: 3px;
                    font-size: 8pt;
                    font-weight: bold;
                    text-transform: uppercase;
                    margin-right: 8px;
                }
                .issue-severity.high { background: #dc2626; color: white; }
                .issue-severity.medium { background: #f59e0b; color: white; }
                .issue-severity.low { background: #3b82f6; color: white; }
                
                /* POI Section */
                .poi-list {
                    margin: 15px 0;
                }
                .poi-item {
                    padding: 8px 12px;
                    margin-bottom: 6px;
                    background: #f0fdf4;
                    border-left: 4px solid #22c55e;
                    border-radius: 4px;
                }
                
                /* Photo Grid */
                .photo-section {
                    margin: 20px 0;
                    page-break-inside: avoid;
                }
                .photo-grid {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 10px;
                }
                .photo-item {
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    overflow: hidden;
                }
                .photo-item img {
                    width: 100%;
                    height: 120px;
                    object-fit: cover;
                }
                .photo-caption {
                    padding: 6px 8px;
                    background: #f5f5f5;
                    font-size: 9pt;
                    color: #666;
                }
                
                /* Footer */
                .footer {
                    margin-top: 40px;
                    padding-top: 15px;
                    border-top: 1px solid #ddd;
                    text-align: center;
                    font-size: 9pt;
                    color: #888;
                }
                
                @media print {
                    body { padding: 0; }
                    .section { page-break-inside: avoid; }
                }
            </style>
        </head>
        <body>
            <!-- Header -->
            <div class="header">
                <h1><?php echo esc_html( $company_name ); ?></h1>
                <div class="subtitle">Quality Assurance Inspection Report</div>
            </div>

            <!-- Report Metadata -->
            <table class="meta-table">
                <tr>
                    <td class="label">School:</td>
                    <td><?php echo esc_html( $school ? $school->name : 'Unknown' ); ?></td>
                    <td class="label">Location:</td>
                    <td><?php echo esc_html( $school ? $school->location : '' ); ?></td>
                </tr>
                <tr>
                    <td class="label">Report Type:</td>
                    <td><?php echo esc_html( $report->get_type_label() ); ?></td>
                    <td class="label">Inspection Date:</td>
                    <td><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $report->inspection_date ) ) ); ?></td>
                </tr>
                <?php if ( $previous_report ) : ?>
                <tr>
                    <td class="label">Compared To:</td>
                    <td colspan="3"><?php echo esc_html( date_i18n( 'F j, Y', strtotime( $previous_report->inspection_date ) ) ); ?> Report</td>
                </tr>
                <?php endif; ?>
            </table>

            <!-- Overall Rating -->
            <div class="rating-box <?php echo esc_attr( $report->overall_rating ); ?>">
                Overall Rating: <?php echo esc_html( $report->get_rating_label() ); ?>
            </div>

            <!-- AI Executive Summary -->
            <?php if ( $ai_summary && ! empty( $ai_summary['executive_summary'] ) ) : ?>
            <div class="ai-summary">
                <h3>📊 Executive Summary</h3>
                <p><?php echo wp_kses_post( nl2br( $ai_summary['executive_summary'] ) ); ?></p>

                <?php if ( ! empty( $ai_summary['issues'] ) ) : ?>
                    <h4 style="margin: 15px 0 10px;">Identified Issues:</h4>
                    <div class="issues-list">
                        <?php foreach ( $ai_summary['issues'] as $issue ) : ?>
                            <div class="issue-item <?php echo esc_attr( $issue['severity'] ?? 'medium' ); ?>">
                                <span class="issue-severity <?php echo esc_attr( $issue['severity'] ?? 'medium' ); ?>">
                                    <?php echo esc_html( $issue['severity'] ?? 'Medium' ); ?>
                                </span>
                                <?php echo esc_html( is_array( $issue ) ? $issue['description'] : $issue ); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $ai_summary['poi'] ) ) : ?>
                    <h4 style="margin: 15px 0 10px;">Points of Interest (POI):</h4>
                    <div class="poi-list">
                        <?php foreach ( $ai_summary['poi'] as $poi ) : ?>
                            <div class="poi-item">
                                ✓ <?php echo esc_html( is_array( $poi ) ? $poi['recommendation'] : $poi ); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Checklist Sections -->
            <?php foreach ( $checklist['sections'] as $section ) : 
                $section_responses = $responses[ $section['key'] ] ?? [];
                $section_previous = $previous_responses[ $section['key'] ] ?? [];
            ?>
            <div class="section">
                <h2>
                    <?php echo esc_html( $section['name'] ); ?>
                    <?php if ( isset( $section['tier'] ) && $section['tier'] === 2 ) : ?>
                        <span class="tier2-badge">Tier 2</span>
                    <?php endif; ?>
                </h2>
                <table class="checklist-table">
                    <thead>
                        <tr>
                            <th class="col-item">Criteria</th>
                            <th class="col-rating">Current</th>
                            <?php if ( $previous_report ) : ?>
                                <th class="col-previous">Previous</th>
                                <th class="col-comparison">Change</th>
                            <?php endif; ?>
                            <th class="col-notes">Notes / Follow-up</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $section['items'] as $item ) : 
                            $response = $section_responses[ $item['key'] ] ?? null;
                            $prev_response = $section_previous[ $item['key'] ] ?? null;
                            $rating = $response ? $response->rating : 'na';
                            $prev_rating = $prev_response ? $prev_response->rating : null;
                            $notes = $response ? $response->notes : '';
                            
                            // Determine row class
                            $row_class = '';
                            if ( $rating === 'no' ) {
                                $row_class = 'row-issue';
                            } elseif ( $rating === 'sometimes' ) {
                                $row_class = 'row-sometimes';
                            }
                            
                            // Determine comparison
                            $change = '';
                            $change_class = '';
                            if ( $prev_rating && $prev_rating !== $rating ) {
                                $rating_values = [ 'no' => 0, 'sometimes' => 1, 'na' => 2, 'yes' => 3 ];
                                $current_val = $rating_values[ $rating ] ?? 2;
                                $prev_val = $rating_values[ $prev_rating ] ?? 2;
                                
                                if ( $current_val > $prev_val ) {
                                    $change = '↑ Improved';
                                    $change_class = 'improved';
                                } else {
                                    $change = '↓ Regressed';
                                    $change_class = 'regressed';
                                }
                            } elseif ( $prev_rating ) {
                                $change = '— Same';
                            }
                        ?>
                        <tr class="<?php echo esc_attr( $row_class ); ?>">
                            <td><?php echo esc_html( $item['label'] ); ?></td>
                            <td class="rating-cell rating-<?php echo esc_attr( $rating ); ?>">
                                <?php
                                $rating_display = [
                                    'yes'       => '✓ Yes',
                                    'sometimes' => '~ Sometimes',
                                    'no'        => '✗ No',
                                    'na'        => '— N/A',
                                ];
                                echo esc_html( $rating_display[ $rating ] ?? $rating );
                                ?>
                            </td>
                            <?php if ( $previous_report ) : ?>
                                <td class="rating-cell comparison-cell rating-<?php echo esc_attr( $prev_rating ?? 'na' ); ?>">
                                    <?php echo esc_html( $rating_display[ $prev_rating ?? 'na' ] ?? '—' ); ?>
                                </td>
                                <td class="comparison-cell <?php echo esc_attr( $change_class ); ?>">
                                    <?php echo esc_html( $change ); ?>
                                </td>
                            <?php endif; ?>
                            <td>
                                <?php if ( $notes ) : ?>
                                    <?php echo esc_html( $notes ); ?>
                                    <?php if ( $rating === 'no' || $rating === 'sometimes' ) : ?>
                                        <div class="followup-note">📌 Follow-up required</div>
                                    <?php endif; ?>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>

            <!-- Photos Section -->
            <?php 
            $photos = $report->get_photos();
            if ( ! empty( $photos ) ) : 
            ?>
            <div class="section photo-section">
                <h2>Photo Documentation</h2>
                <div class="photo-grid">
                    <?php foreach ( $photos as $photo ) : ?>
                        <div class="photo-item">
                            <img src="<?php echo esc_url( $photo->get_thumbnail_url( 300 ) ); ?>" alt="">
                            <?php if ( $photo->caption ) : ?>
                                <div class="photo-caption"><?php echo esc_html( $photo->caption ); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Closing Notes -->
            <?php if ( $report->closing_notes ) : ?>
            <div class="section">
                <h2>Closing Notes & Observations</h2>
                <div style="padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                    <?php echo wp_kses_post( nl2br( $report->closing_notes ) ); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Footer -->
            <div class="footer">
                <p><?php echo esc_html( $company_name ); ?> Quality Assurance Report</p>
                <p>Report ID: <?php echo esc_html( $report->id ); ?> | Generated: <?php echo esc_html( date_i18n( 'F j, Y \a\t g:i A' ) ); ?></p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate HTML PDF (print-based).
     *
     * @param Report $report Report object.
     * @param bool   $include_comparison Include comparison.
     * @return string Path to temp file.
     */
    private function generate_html_pdf( Report $report, $include_comparison = true ) {
        $html = $this->get_report_html( $report, $include_comparison );
        
        // Save HTML to temp file
        $temp_dir = wp_upload_dir()['basedir'] . '/cqa-temp';
        wp_mkdir_p( $temp_dir );
        
        $filename = 'report-' . $report->id . '-' . time() . '.html';
        $filepath = $temp_dir . '/' . $filename;
        
        file_put_contents( $filepath, $html );
        
        return $filepath;
    }

    /**
     * Generate PDF using TCPDF.
     *
     * @param Report $report Report object.
     * @param bool   $include_comparison Include comparison.
     * @return string Path to PDF file.
     */
    private function generate_tcpdf( Report $report, $include_comparison = true ) {
        $html = $this->get_report_html( $report, $include_comparison );
        
        $pdf = new \TCPDF( 'P', 'mm', 'A4', true, 'UTF-8' );
        $pdf->SetCreator( 'Chroma QA Reports' );
        $pdf->SetAuthor( 'Chroma Early Learning Academy' );
        $pdf->SetTitle( 'QA Report - ' . $report->id );
        
        $pdf->SetMargins( 12, 12, 12 );
        $pdf->SetAutoPageBreak( true, 12 );
        $pdf->SetFont( 'helvetica', '', 10 );
        
        $pdf->AddPage();
        $pdf->writeHTML( $html, true, false, true, false, '' );
        
        $temp_dir = wp_upload_dir()['basedir'] . '/cqa-temp';
        wp_mkdir_p( $temp_dir );
        
        $filepath = $temp_dir . '/report-' . $report->id . '-' . time() . '.pdf';
        $pdf->Output( $filepath, 'F' );
        
        return $filepath;
    }

    /**
     * Generate PDF using DOMPDF.
     *
     * @param Report $report Report object.
     * @param bool   $include_comparison Include comparison.
     * @return string Path to PDF file.
     */
    private function generate_dompdf( Report $report, $include_comparison = true ) {
        $html = $this->get_report_html( $report, $include_comparison );
        
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();
        
        $temp_dir = wp_upload_dir()['basedir'] . '/cqa-temp';
        wp_mkdir_p( $temp_dir );
        
        $filepath = $temp_dir . '/report-' . $report->id . '-' . time() . '.pdf';
        file_put_contents( $filepath, $dompdf->output() );
        
        return $filepath;
    }
}
