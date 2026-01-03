<?php
/**
 * Compliance Trends Analytics
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Analytics;

use ChromaQA\Models\Report;
use ChromaQA\Models\School;
use ChromaQA\Models\Checklist_Response;

/**
 * Generates compliance trend data.
 */
class Trends {

    /**
     * Get school compliance trend over time.
     *
     * @param int $school_id School ID.
     * @param int $limit Number of reports to include.
     * @return array Trend data.
     */
    public static function get_school_trend( $school_id, $limit = 10 ) {
        $reports = Report::get_by_school( $school_id, [
            'limit'    => $limit,
            'orderby'  => 'inspection_date',
            'order'    => 'ASC',
            'status'   => 'approved',
        ] );

        $data = [];
        
        foreach ( $reports as $report ) {
            $rating_value = self::rating_to_value( $report->overall_rating );
            $sections = self::get_section_scores( $report->id );
            
            $data[] = [
                'date'       => $report->inspection_date,
                'rating'     => $report->overall_rating,
                'score'      => $rating_value,
                'type'       => $report->report_type,
                'sections'   => $sections,
            ];
        }

        return [
            'school_id'   => $school_id,
            'school_name' => School::find( $school_id )->name ?? 'Unknown',
            'data_points' => $data,
            'trend'       => self::calculate_trend( $data ),
        ];
    }

    /**
     * Get section-level scores for a report.
     *
     * @param int $report_id Report ID.
     * @return array Section scores.
     */
    private static function get_section_scores( $report_id ) {
        $responses = Checklist_Response::get_by_report_grouped( $report_id );
        $scores = [];

        foreach ( $responses as $section_key => $items ) {
            $total = 0;
            $count = 0;

            foreach ( $items as $item ) {
                if ( $item['rating'] === 'na' ) continue;
                
                $count++;
                switch ( $item['rating'] ) {
                    case 'yes':
                        $total += 100;
                        break;
                    case 'sometimes':
                        $total += 50;
                        break;
                    case 'no':
                        $total += 0;
                        break;
                }
            }

            $scores[ $section_key ] = $count > 0 ? round( $total / $count ) : null;
        }

        return $scores;
    }

    /**
     * Convert rating to numeric value.
     *
     * @param string $rating Rating string.
     * @return int Numeric value.
     */
    private static function rating_to_value( $rating ) {
        switch ( $rating ) {
            case 'exceeds':
                return 100;
            case 'meets':
                return 75;
            case 'needs_improvement':
                return 50;
            default:
                return 0;
        }
    }

    /**
     * Calculate trend direction.
     *
     * @param array $data Data points.
     * @return array Trend info.
     */
    private static function calculate_trend( $data ) {
        if ( count( $data ) < 2 ) {
            return [ 'direction' => 'stable', 'change' => 0 ];
        }

        $first = $data[0]['score'];
        $last = end( $data )['score'];
        $change = $last - $first;

        return [
            'direction' => $change > 5 ? 'improving' : ( $change < -5 ? 'declining' : 'stable' ),
            'change'    => $change,
            'first'     => $first,
            'current'   => $last,
        ];
    }

    /**
     * Get regional comparison.
     *
     * @param string $region Region name.
     * @return array Regional comparison data.
     */
    public static function get_regional_comparison( $region = '' ) {
        $schools = School::all( [ 'region' => $region, 'status' => 'active', 'limit' => 100 ] );
        $comparison = [];

        foreach ( $schools as $school ) {
            $latest = Report::get_latest_for_school( $school->id );
            
            if ( ! $latest ) continue;

            $comparison[] = [
                'school_id'   => $school->id,
                'school_name' => $school->name,
                'region'      => $school->region ?? '',
                'rating'      => $latest->overall_rating,
                'score'       => self::rating_to_value( $latest->overall_rating ),
                'last_visit'  => $latest->inspection_date,
            ];
        }

        // Sort by score descending
        usort( $comparison, function( $a, $b ) {
            return $b['score'] <=> $a['score'];
        } );

        return $comparison;
    }

    /**
     * Get company-wide statistics.
     *
     * @return array Company statistics.
     */
    public static function get_company_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'cqa_reports';

        // Rating distribution
        $ratings = $wpdb->get_results( "
            SELECT overall_rating, COUNT(*) as count 
            FROM {$table} 
            WHERE status = 'approved' 
            GROUP BY overall_rating
        ", ARRAY_A );

        $distribution = [];
        foreach ( $ratings as $r ) {
            $distribution[ $r['overall_rating'] ] = (int) $r['count'];
        }

        // Monthly reports
        $monthly = $wpdb->get_results( "
            SELECT DATE_FORMAT(inspection_date, '%Y-%m') as month, COUNT(*) as count
            FROM {$table}
            WHERE status = 'approved'
            GROUP BY month
            ORDER BY month DESC
            LIMIT 12
        ", ARRAY_A );

        // Common issues
        $issues = self::get_common_issues();

        return [
            'rating_distribution' => $distribution,
            'monthly_reports'     => array_reverse( $monthly ),
            'common_issues'       => $issues,
            'total_reports'       => array_sum( $distribution ),
            'total_schools'       => School::count( [ 'status' => 'active' ] ),
        ];
    }

    /**
     * Get most common issues across all reports.
     *
     * @param int $limit Number of issues to return.
     * @return array Common issues.
     */
    public static function get_common_issues( $limit = 10 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'cqa_responses';

        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT section_key, item_key, COUNT(*) as count
            FROM {$table}
            WHERE rating = 'no'
            GROUP BY section_key, item_key
            ORDER BY count DESC
            LIMIT %d
        ", $limit ), ARRAY_A );

        return $results;
    }

    /**
     * Export trend data to CSV.
     *
     * @param int $school_id School ID.
     * @return string CSV content.
     */
    public static function export_csv( $school_id ) {
        $trend = self::get_school_trend( $school_id, 50 );
        
        $csv = "Date,Rating,Score,Type\n";
        
        foreach ( $trend['data_points'] as $point ) {
            $csv .= sprintf(
                "%s,%s,%d,%s\n",
                $point['date'],
                $point['rating'],
                $point['score'],
                $point['type']
            );
        }

        return $csv;
    }

    /**
     * Register REST routes.
     */
    public static function register_routes() {
        register_rest_route( 'cqa/v1', '/analytics/school/(?P<id>\d+)/trend', [
            'methods'             => 'GET',
            'callback'            => function( $request ) {
                return self::get_school_trend( $request['id'], $request['limit'] ?? 10 );
            },
            'permission_callback' => function() {
                return current_user_can( 'cqa_view_all_reports' );
            },
        ] );

        register_rest_route( 'cqa/v1', '/analytics/regional', [
            'methods'             => 'GET',
            'callback'            => function( $request ) {
                return self::get_regional_comparison( $request['region'] ?? '' );
            },
            'permission_callback' => function() {
                return current_user_can( 'cqa_view_all_reports' );
            },
        ] );

        register_rest_route( 'cqa/v1', '/analytics/company', [
            'methods'             => 'GET',
            'callback'            => function() {
                return self::get_company_stats();
            },
            'permission_callback' => function() {
                return current_user_can( 'cqa_view_all_reports' );
            },
        ] );

        register_rest_route( 'cqa/v1', '/analytics/school/(?P<id>\d+)/export', [
            'methods'             => 'GET',
            'callback'            => function( $request ) {
                $csv = self::export_csv( $request['id'] );
                return new \WP_REST_Response( $csv, 200, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="trend-export.csv"',
                ] );
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
