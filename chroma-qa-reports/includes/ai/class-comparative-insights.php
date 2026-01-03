<?php
/**
 * Comparative Insights
 *
 * AI-powered cross-school analysis
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\AI;

use ChromaQA\Models\Report;
use ChromaQA\Models\School;
use ChromaQA\Models\Checklist_Response;
use ChromaQA\Analytics\Trends;

/**
 * Generates comparative insights across all schools.
 */
class Comparative_Insights {

    /**
     * Generate company-wide insights.
     *
     * @return array Insights data.
     */
    public static function generate() {
        $data = self::collect_data();
        
        if ( empty( $data['reports'] ) ) {
            return [ 'error' => 'Not enough data for analysis.' ];
        }

        // Use AI to analyze patterns
        $api_key = get_option( 'cqa_gemini_api_key', '' );
        
        if ( $api_key ) {
            return self::ai_analyze( $api_key, $data );
        }

        // Fallback to rule-based analysis
        return self::rule_based_analysis( $data );
    }

    /**
     * Collect data for analysis.
     *
     * @return array Collected data.
     */
    private static function collect_data() {
        global $wpdb;
        
        // Get recent approved reports
        $reports_table = $wpdb->prefix . 'cqa_reports';
        $schools_table = $wpdb->prefix . 'cqa_schools';
        $responses_table = $wpdb->prefix . 'cqa_responses';

        // Last 90 days of reports
        $reports = $wpdb->get_results( "
            SELECT r.*, s.name as school_name, s.region
            FROM {$reports_table} r
            LEFT JOIN {$schools_table} s ON r.school_id = s.id
            WHERE r.status = 'approved'
            AND r.inspection_date > DATE_SUB(NOW(), INTERVAL 90 DAY)
            ORDER BY r.inspection_date DESC
        ", ARRAY_A );

        // Common issues
        $issues = $wpdb->get_results( "
            SELECT resp.section_key, resp.item_key, COUNT(*) as count,
                   GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as schools
            FROM {$responses_table} resp
            JOIN {$reports_table} r ON resp.report_id = r.id
            JOIN {$schools_table} s ON r.school_id = s.id
            WHERE resp.rating = 'no'
            AND r.inspection_date > DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY resp.section_key, resp.item_key
            HAVING count >= 2
            ORDER BY count DESC
            LIMIT 20
        ", ARRAY_A );

        // Best performers
        $best = $wpdb->get_results( "
            SELECT s.id, s.name, s.region, r.overall_rating, r.inspection_date
            FROM {$schools_table} s
            JOIN {$reports_table} r ON s.id = r.school_id
            WHERE r.status = 'approved'
            AND r.overall_rating = 'exceeds'
            AND r.inspection_date = (
                SELECT MAX(inspection_date) FROM {$reports_table} 
                WHERE school_id = s.id AND status = 'approved'
            )
        ", ARRAY_A );

        // Needs attention
        $needs_attention = $wpdb->get_results( "
            SELECT s.id, s.name, s.region, r.overall_rating, r.inspection_date
            FROM {$schools_table} s
            JOIN {$reports_table} r ON s.id = r.school_id
            WHERE r.status = 'approved'
            AND r.overall_rating = 'needs_improvement'
            AND r.inspection_date = (
                SELECT MAX(inspection_date) FROM {$reports_table} 
                WHERE school_id = s.id AND status = 'approved'
            )
        ", ARRAY_A );

        // Regional breakdown
        $by_region = $wpdb->get_results( "
            SELECT s.region, r.overall_rating, COUNT(*) as count
            FROM {$reports_table} r
            JOIN {$schools_table} s ON r.school_id = s.id
            WHERE r.status = 'approved'
            AND r.inspection_date > DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY s.region, r.overall_rating
        ", ARRAY_A );

        return [
            'reports'         => $reports,
            'common_issues'   => $issues,
            'best_performers' => $best,
            'needs_attention' => $needs_attention,
            'by_region'       => $by_region,
            'period'          => '90 days',
        ];
    }

    /**
     * AI-powered analysis.
     *
     * @param string $api_key Gemini API key.
     * @param array  $data Collected data.
     * @return array Analysis results.
     */
    private static function ai_analyze( $api_key, $data ) {
        $prompt = self::build_prompt( $data );

        $client = new Gemini_Client();
        $response = $client->generate( $prompt );

        if ( isset( $response['error'] ) ) {
            // Fallback to rule-based
            return self::rule_based_analysis( $data );
        }

        // Parse structured response
        preg_match( '/\{[\s\S]*\}/', $response['text'] ?? '', $matches );
        
        if ( ! empty( $matches[0] ) ) {
            $parsed = json_decode( $matches[0], true );
            if ( $parsed ) {
                $parsed['data_summary'] = [
                    'total_reports'    => count( $data['reports'] ),
                    'schools_analyzed' => count( array_unique( array_column( $data['reports'], 'school_id' ) ) ),
                    'period'           => $data['period'],
                ];
                return $parsed;
            }
        }

        return self::rule_based_analysis( $data );
    }

    /**
     * Build AI prompt.
     *
     * @param array $data Collected data.
     * @return string Prompt.
     */
    private static function build_prompt( $data ) {
        $issues_list = implode( "\n", array_map( function( $i ) {
            return "- {$i['section_key']}/{$i['item_key']}: {$i['count']} occurrences at schools: {$i['schools']}";
        }, array_slice( $data['common_issues'], 0, 10 ) ) );

        $best_list = implode( ', ', array_column( array_slice( $data['best_performers'], 0, 5 ), 'name' ) );
        $attention_list = implode( ', ', array_column( array_slice( $data['needs_attention'], 0, 5 ), 'name' ) );

        return <<<PROMPT
You are a QA Director analyzing patterns across multiple childcare facilities. Based on the following data from the last 90 days:

**Common Issues (items marked 'No' across multiple schools):**
{$issues_list}

**Best Performing Schools (Exceeds rating):** {$best_list}
**Schools Needing Attention (Needs Improvement):** {$attention_list}

**Total Reports Analyzed:** {$data['reports'][0]['inspection_date']} to present

Generate executive insights for leadership. Respond in JSON format:

{
    "executive_summary": "2-3 sentence overview of company-wide QA status",
    "key_trends": [
        {
            "trend": "Trend description",
            "impact": "high|medium|low",
            "recommendation": "Action to take"
        }
    ],
    "systemic_issues": [
        {
            "issue": "Issue appearing across multiple schools",
            "affected_schools": "X schools",
            "root_cause_hypothesis": "Possible cause",
            "recommended_action": "What to do"
        }
    ],
    "best_practices": [
        {
            "practice": "What top schools are doing well",
            "schools": "Schools demonstrating this",
            "recommendation": "How to replicate"
        }
    ],
    "priority_actions": [
        "Top 3 actions leadership should take"
    ],
    "regional_insights": [
        {
            "region": "Region name",
            "status": "Summary of regional performance"
        }
    ]
}
PROMPT;
    }

    /**
     * Rule-based analysis fallback.
     *
     * @param array $data Collected data.
     * @return array Analysis results.
     */
    private static function rule_based_analysis( $data ) {
        $insights = [
            'executive_summary' => '',
            'key_trends'        => [],
            'systemic_issues'   => [],
            'best_practices'    => [],
            'priority_actions'  => [],
            'data_summary'      => [
                'total_reports'    => count( $data['reports'] ),
                'schools_analyzed' => count( array_unique( array_column( $data['reports'], 'school_id' ) ) ),
                'period'           => $data['period'],
            ],
        ];

        // Calculate overall health
        $ratings = array_column( $data['reports'], 'overall_rating' );
        $exceeds = count( array_filter( $ratings, fn($r) => $r === 'exceeds' ) );
        $meets = count( array_filter( $ratings, fn($r) => $r === 'meets' ) );
        $needs = count( array_filter( $ratings, fn($r) => $r === 'needs_improvement' ) );
        $total = count( $ratings );

        if ( $total > 0 ) {
            $health_pct = round( ( ( $exceeds * 100 + $meets * 75 + $needs * 50 ) / $total ) );
            $insights['executive_summary'] = sprintf(
                'Over the past %s, %d QA reports were conducted across %d schools. Overall compliance health is at %d%%. %d schools exceed standards, %d meet standards, and %d need improvement.',
                $data['period'],
                $total,
                $insights['data_summary']['schools_analyzed'],
                $health_pct,
                $exceeds,
                $meets,
                $needs
            );
        }

        // Systemic issues (appearing in 3+ schools)
        foreach ( array_slice( $data['common_issues'], 0, 5 ) as $issue ) {
            $insights['systemic_issues'][] = [
                'issue'              => "{$issue['section_key']}: {$issue['item_key']}",
                'affected_schools'   => "{$issue['count']} schools",
                'schools_list'       => $issue['schools'],
                'recommended_action' => 'Review and address across all affected locations.',
            ];
        }

        // Best performers
        foreach ( array_slice( $data['best_performers'], 0, 3 ) as $school ) {
            $insights['best_practices'][] = [
                'school' => $school['name'],
                'region' => $school['region'] ?? 'N/A',
                'rating' => 'Exceeds Standards',
            ];
        }

        // Priority actions
        if ( ! empty( $data['needs_attention'] ) ) {
            $insights['priority_actions'][] = sprintf(
                'Schedule follow-up visits for %d schools needing improvement.',
                count( $data['needs_attention'] )
            );
        }

        if ( count( $data['common_issues'] ) > 3 ) {
            $insights['priority_actions'][] = 'Address systemic issues appearing across multiple schools through company-wide training.';
        }

        $insights['priority_actions'][] = 'Recognize and learn from best-performing schools.';

        return $insights;
    }

    /**
     * Register REST route.
     */
    public static function register_routes() {
        register_rest_route( 'cqa/v1', '/insights/company', [
            'methods'             => 'GET',
            'callback'            => function() {
                return self::generate();
            },
            'permission_callback' => function() {
                return current_user_can( 'cqa_view_all_reports' );
            },
        ] );
    }

    /**
     * Initialize.
     */
    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }
}
