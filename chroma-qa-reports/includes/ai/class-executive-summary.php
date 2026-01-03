<?php
/**
 * AI Executive Summary Generator
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\AI;

use ChromaQA\Models\Report;
use ChromaQA\Models\Checklist_Response;
use ChromaQA\Checklists\Checklist_Manager;

/**
 * Generates AI-powered executive summaries for QA reports.
 */
class Executive_Summary {

    /**
     * Generate executive summary for a report.
     *
     * @param Report $report The report to summarize.
     * @return array|WP_Error
     */
    public function generate( Report $report ) {
        if ( ! Gemini_Client::is_configured() ) {
            return new \WP_Error( 'not_configured', __( 'AI features are not configured.', 'chroma-qa-reports' ) );
        }

        $school = $report->get_school();
        $responses = Checklist_Response::get_by_report_grouped( $report->id );
        $previous_report = $report->get_previous_report();
        $checklist = Checklist_Manager::get_checklist_for_type( $report->report_type );
        $stats = Checklist_Manager::get_progress_stats( $report->id, $report->report_type );

        // Build the prompt
        $prompt = $this->build_prompt( $school, $report, $responses, $previous_report, $checklist, $stats );

        // Generate summary using Gemini
        $result = Gemini_Client::generate_json( $prompt, [
            'temperature' => 0.3,
            'maxTokens'   => 3000,
        ] );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Save the summary to database
        $this->save_summary( $report->id, $result );

        return $result;
    }

    /**
     * Build the AI prompt.
     *
     * @param object $school School object.
     * @param Report $report Report object.
     * @param array  $responses Checklist responses.
     * @param Report|null $previous_report Previous report for comparison.
     * @param array  $checklist Checklist definition.
     * @param array  $stats Stats summary.
     * @return string
     */
    private function build_prompt( $school, $report, $responses, $previous_report, $checklist, $stats ) {
        $school_name = $school ? $school->name : 'Unknown School';
        $report_type = $report->get_type_label();
        $date = date_i18n( 'F j, Y', strtotime( $report->inspection_date ) );

        $prompt = "You are a QA analyst for Chroma Early Learning Academy, a childcare organization. ";
        $prompt .= "Analyze the following QA inspection report and generate an executive summary.\n\n";

        $prompt .= "## Report Information\n";
        $prompt .= "- School: {$school_name}\n";
        $prompt .= "- Report Type: {$report_type}\n";
        $prompt .= "- Inspection Date: {$date}\n\n";

        $prompt .= "## Summary Statistics\n";
        $prompt .= "- Total Items: {$stats['total']}\n";
        $prompt .= "- Completed: {$stats['completed']} ({$stats['percentage']}%)\n";
        $prompt .= "- Yes/Compliant: {$stats['yes']}\n";
        $prompt .= "- Needs Work: {$stats['sometimes']}\n";
        $prompt .= "- Non-Compliant: {$stats['no']}\n\n";

        $prompt .= "## Checklist Responses\n\n";

        foreach ( $checklist['sections'] as $section ) {
            $section_responses = $responses[ $section['key'] ] ?? [];
            
            if ( empty( $section_responses ) ) {
                continue;
            }

            $prompt .= "### {$section['name']}\n";

            foreach ( $section['items'] as $item ) {
                if ( isset( $section_responses[ $item['key'] ] ) ) {
                    $response = $section_responses[ $item['key'] ];
                    $rating = strtoupper( $response->rating );
                    $notes = $response->notes ? " - Notes: {$response->notes}" : '';
                    $prompt .= "- [{$rating}] {$item['label']}{$notes}\n";
                }
            }

            $prompt .= "\n";
        }

        if ( $previous_report ) {
            $prompt .= "## Comparison with Previous Report\n";
            $prompt .= "This report is being compared to a previous inspection from {$previous_report->inspection_date}.\n";
            $prompt .= "Highlight any improvements or regressions.\n\n";
        }

        $prompt .= "## Instructions\n";
        $prompt .= "Generate a structured JSON response with the following format:\n";
        $prompt .= "{\n";
        $prompt .= '  "executive_summary": "A 2-3 paragraph professional summary of the inspection findings...",';
        $prompt .= "\n";
        $prompt .= '  "issues": [';
        $prompt .= "\n";
        $prompt .= '    { "severity": "high|medium|low", "section": "section name", "description": "issue description" }';
        $prompt .= "\n";
        $prompt .= "  ],\n";
        $prompt .= '  "poi": [';
        $prompt .= "\n";
        $prompt .= '    { "section": "section name", "recommendation": "actionable recommendation" }';
        $prompt .= "\n";
        $prompt .= "  ],\n";
        $prompt .= '  "comparison": {';
        $prompt .= "\n";
        $prompt .= '    "improvements": ["list of improved items"],';
        $prompt .= "\n";
        $prompt .= '    "regressions": ["list of regressed items"]';
        $prompt .= "\n";
        $prompt .= "  },\n";
        $prompt .= '  "suggested_rating": "exceeds|meets|needs_improvement"';
        $prompt .= "\n";
        $prompt .= "}\n\n";

        $prompt .= "Focus on:\n";
        $prompt .= "1. Critical safety and compliance issues (mark as HIGH severity)\n";
        $prompt .= "2. Areas that need immediate attention\n";
        $prompt .= "3. Positive observations and strengths\n";
        $prompt .= "4. Specific, actionable recommendations for the Points of Interest (POI)\n";
        $prompt .= "5. Overall assessment and suggested rating based on the responses\n";

        return $prompt;
    }

    /**
     * Save the summary to database.
     *
     * @param int   $report_id Report ID.
     * @param array $summary Summary data.
     */
    private function save_summary( $report_id, $summary ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cqa_ai_summaries';

        // Delete existing summary if any
        $wpdb->delete( $table, [ 'report_id' => $report_id ], [ '%d' ] );

        // Insert new summary
        $wpdb->insert(
            $table,
            [
                'report_id'         => $report_id,
                'executive_summary' => $summary['executive_summary'] ?? '',
                'issues_json'       => wp_json_encode( $summary['issues'] ?? [] ),
                'poi_json'          => wp_json_encode( $summary['poi'] ?? [] ),
                'comparison_json'   => wp_json_encode( $summary['comparison'] ?? [] ),
                'generated_at'      => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    /**
     * Generate smart note suggestion based on rating.
     *
     * @param string $section_key Section key.
     * @param string $item_key Item key.
     * @param string $item_label Item human-readable label.
     * @param string $rating Selected rating (no, sometimes).
     * @return array Suggestion data.
     */
    public static function get_note_suggestion( $section_key, $item_key, $item_label, $rating ) {
        // Common suggestions based on patterns
        $suggestions = self::get_predefined_suggestions();

        // Check for exact match
        $key = $section_key . '/' . $item_key;
        if ( isset( $suggestions[ $key ] ) && isset( $suggestions[ $key ][ $rating ] ) ) {
            return [
                'suggestion' => $suggestions[ $key ][ $rating ],
                'source'     => 'predefined',
            ];
        }

        // Try AI-powered suggestion
        if ( Gemini_Client::is_configured() ) {
            return self::generate_ai_suggestion( $item_label, $rating, $section_key );
        }

        // Fallback generic suggestions
        return self::get_generic_suggestion( $item_label, $rating );
    }

    /**
     * Get predefined suggestions for common items.
     *
     * @return array Suggestions map.
     */
    private static function get_predefined_suggestions() {
        return [
            'health_safety/fire_extinguisher' => [
                'no' => 'Fire extinguisher needs to be serviced/replaced. Schedule inspection by [DATE]. Ensure annual maintenance tag is current.',
                'sometimes' => 'Fire extinguisher present but needs attention. Check expiration date and ensure monthly visual inspection log is maintained.',
            ],
            'health_safety/fire_drills' => [
                'no' => 'Fire drills not conducted as required. Schedule monthly fire drills and ensure one nap time drill per year. Maintain drill log with dates and times.',
                'sometimes' => 'Fire drills being conducted but not consistently documented. Ensure drill log captures all required information.',
            ],
            'health_safety/allergy_list' => [
                'no' => 'Allergy list not posted or not current. Update immediately and post in all classrooms and kitchen. Ensure all staff are aware.',
                'sometimes' => 'Allergy list present but needs updating. Verify all enrolled children with allergies are listed with current information.',
            ],
            'classroom/handwashing' => [
                'no' => 'Proper handwashing procedures not being followed. Retrain staff on proper technique. Post handwashing posters at all sinks.',
                'sometimes' => 'Handwashing procedures inconsistent. Observe and coach staff on key times (before meals, after diaper changes, etc.).',
            ],
            'classroom/diapering' => [
                'no' => 'Diapering procedures not being followed correctly. Review proper steps with staff. Ensure gloves and sanitizer are accessible at changing tables.',
                'sometimes' => 'Diapering procedure needs improvement. Observe staff and provide coaching on proper sequence and sanitization.',
            ],
            'playground/fall_zone' => [
                'no' => 'Fall zone does not meet requirements. Add mulch to achieve required depth (9" wood mulch or 6" rubber). Extend fall zone perimeter.',
                'sometimes' => 'Fall zone needs attention in some areas. Check depth and coverage, particularly under high-use equipment.',
            ],
            'playground/equipment_condition' => [
                'no' => 'Equipment has safety concerns (rust, sharp edges, loose parts). Remove from use until repaired or replaced.',
                'sometimes' => 'Some equipment needs maintenance. Schedule repairs and document completion.',
            ],
            'kitchen/food_storage' => [
                'no' => 'Food storage not meeting standards. Label all items with date and contents. Ensure FIFO rotation. Check refrigerator temperatures daily.',
                'sometimes' => 'Food storage mostly compliant but some items need proper labeling or dating.',
            ],
            'building/cleanliness' => [
                'no' => 'Facility cleanliness below standards. Deep clean required. Review and strengthen cleaning checklists and routines.',
                'sometimes' => 'Some areas need additional cleaning attention. Focus on high-touch surfaces and bathroom areas.',
            ],
            'lobby/front_desk' => [
                'no' => 'Front desk not consistently staffed. Ensure coverage during all operating hours for security and customer service.',
                'sometimes' => 'Front desk coverage intermittent. Review schedule and provide backup coverage plan.',
            ],
        ];
    }

    /**
     * Generate AI-powered suggestion.
     *
     * @param string $item_label Item label.
     * @param string $rating Rating.
     * @param string $section_key Section key.
     * @return array Suggestion.
     */
    private static function generate_ai_suggestion( $item_label, $rating, $section_key ) {
        $prompt = sprintf(
            'You are a QA officer for a childcare facility. A checklist item "%s" in the "%s" section was marked as "%s". ' .
            'Generate a brief, professional note (1-2 sentences) that could be added to the inspection report explaining the issue and recommending action. ' .
            'Be specific and actionable. Return only the note text, no other formatting.',
            $item_label,
            str_replace( '_', ' ', $section_key ),
            $rating === 'no' ? 'NOT compliant/No' : 'partially compliant/Sometimes'
        );

        $result = Gemini_Client::generate( $prompt, [ 'temperature' => 0.3, 'maxTokens' => 200 ] );

        if ( is_wp_error( $result ) ) {
            return self::get_generic_suggestion( $item_label, $rating );
        }

        return [
            'suggestion' => trim( $result['text'] ?? '' ),
            'source'     => 'ai',
        ];
    }

    /**
     * Get generic suggestion.
     *
     * @param string $item_label Item label.
     * @param string $rating Rating.
     * @return array Suggestion.
     */
    private static function get_generic_suggestion( $item_label, $rating ) {
        if ( $rating === 'no' ) {
            $template = 'Item "%s" is not meeting standards. Immediate action required to bring into compliance. Follow up within [X] days.';
        } else {
            $template = 'Item "%s" needs improvement. While partially meeting standards, additional attention is recommended.';
        }

        return [
            'suggestion' => sprintf( $template, $item_label ),
            'source'     => 'generic',
        ];
    }
}
