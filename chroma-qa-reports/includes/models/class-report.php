<?php
/**
 * Report Model
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Models;

/**
 * Represents a QA Report.
 */
class Report {

    /**
     * Table name.
     *
     * @var string
     */
    private static $table = 'cqa_reports';

    /**
     * Report type constants.
     */
    const TYPE_NEW_ACQUISITION = 'new_acquisition';
    const TYPE_TIER1 = 'tier1';
    const TYPE_TIER1_TIER2 = 'tier1_tier2';

    /**
     * Rating constants.
     */
    const RATING_EXCEEDS = 'exceeds';
    const RATING_MEETS = 'meets';
    const RATING_NEEDS_IMPROVEMENT = 'needs_improvement';
    const RATING_PENDING = 'pending';

    /**
     * Status constants.
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED = 'approved';

    /**
     * Report properties.
     */
    public $id;
    public $school_id;
    public $user_id;
    public $report_type;
    public $inspection_date;
    public $previous_report_id;
    public $overall_rating;
    public $closing_notes;
    public $status;
    public $created_at;
    public $updated_at;

    /**
     * Cached school object.
     *
     * @var School|null
     */
    private $school_cache = null;

    /**
     * Get the full table name.
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table;
    }

    /**
     * Find a report by ID.
     *
     * @param int $id Report ID.
     * @return Report|null
     */
    public static function find( $id ) {
        global $wpdb;
        $table = self::get_table_name();
        
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
            ARRAY_A
        );

        return $row ? self::from_row( $row ) : null;
    }

    /**
     * Get all reports.
     *
     * @param array $args Query arguments.
     * @return Report[]
     */
    public static function all( $args = [] ) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = [
            'school_id'    => 0,
            'user_id'      => 0,
            'report_type'  => '',
            'status'       => '',
            'orderby'      => 'inspection_date',
            'order'        => 'DESC',
            'limit'        => 50,
            'offset'       => 0,
        ];

        $args = wp_parse_args( $args, $defaults );

        $where = [];
        $values = [];

        if ( $args['school_id'] ) {
            $where[] = 'school_id = %d';
            $values[] = $args['school_id'];
        }

        if ( $args['user_id'] ) {
            $where[] = 'user_id = %d';
            $values[] = $args['user_id'];
        }

        if ( ! empty( $args['report_type'] ) ) {
            $where[] = 'report_type = %s';
            $values[] = $args['report_type'];
        }

        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        $where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
        $orderby = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );

        $sql = "SELECT * FROM {$table} {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];

        $rows = $wpdb->get_results(
            $wpdb->prepare( $sql, $values ),
            ARRAY_A
        );

        return array_map( [ self::class, 'from_row' ], $rows );
    }

    /**
     * Create a report from a database row.
     *
     * @param array $row Database row.
     * @return Report
     */
    public static function from_row( $row ) {
        $report = new self();
        $report->id = (int) $row['id'];
        $report->school_id = (int) $row['school_id'];
        $report->user_id = (int) $row['user_id'];
        $report->report_type = $row['report_type'];
        $report->inspection_date = $row['inspection_date'];
        $report->previous_report_id = $row['previous_report_id'] ? (int) $row['previous_report_id'] : null;
        $report->overall_rating = $row['overall_rating'];
        $report->closing_notes = $row['closing_notes'];
        $report->status = $row['status'];
        $report->created_at = $row['created_at'];
        $report->updated_at = $row['updated_at'];
        return $report;
    }

    /**
     * Save the report.
     *
     * @return bool|int
     */
    public function save() {
        global $wpdb;
        $table = self::get_table_name();

        $data = [
            'school_id'          => $this->school_id,
            'user_id'            => $this->user_id,
            'report_type'        => $this->report_type,
            'inspection_date'    => $this->inspection_date,
            'previous_report_id' => $this->previous_report_id,
            'overall_rating'     => $this->overall_rating ?: self::RATING_PENDING,
            'closing_notes'      => $this->closing_notes,
            'status'             => $this->status ?: self::STATUS_DRAFT,
        ];

        $format = [ '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s' ];

        if ( $this->id ) {
            $result = $wpdb->update( $table, $data, [ 'id' => $this->id ], $format, [ '%d' ] );
            return $result !== false ? $this->id : false;
        } else {
            $result = $wpdb->insert( $table, $data, $format );
            if ( $result ) {
                $this->id = $wpdb->insert_id;
                return $this->id;
            }
            return false;
        }
    }

    /**
     * Delete the report.
     *
     * @return bool
     */
    public function delete() {
        if ( ! $this->id ) {
            return false;
        }

        global $wpdb;
        
        // Delete related responses
        $wpdb->delete( 
            $wpdb->prefix . 'cqa_responses', 
            [ 'report_id' => $this->id ], 
            [ '%d' ] 
        );

        // Delete related photos
        $wpdb->delete( 
            $wpdb->prefix . 'cqa_photos', 
            [ 'report_id' => $this->id ], 
            [ '%d' ] 
        );

        // Delete AI summary
        $wpdb->delete( 
            $wpdb->prefix . 'cqa_ai_summaries', 
            [ 'report_id' => $this->id ], 
            [ '%d' ] 
        );

        // Delete the report
        return $wpdb->delete( self::get_table_name(), [ 'id' => $this->id ], [ '%d' ] ) !== false;
    }

    /**
     * Get the school for this report.
     *
     * @return School|null
     */
    public function get_school() {
        if ( $this->school_cache === null ) {
            $this->school_cache = School::find( $this->school_id );
        }
        return $this->school_cache;
    }

    /**
     * Get the previous report for comparison.
     *
     * @return Report|null
     */
    public function get_previous_report() {
        if ( $this->previous_report_id ) {
            return self::find( $this->previous_report_id );
        }
        return null;
    }

    /**
     * Get all responses for this report.
     *
     * @return Checklist_Response[]
     */
    public function get_responses() {
        return Checklist_Response::get_by_report( $this->id );
    }

    /**
     * Get all photos for this report.
     *
     * @return Photo[]
     */
    public function get_photos() {
        return Photo::get_by_report( $this->id );
    }

    /**
     * Get the AI summary for this report.
     *
     * @return array|null
     */
    public function get_ai_summary() {
        global $wpdb;
        $table = $wpdb->prefix . 'cqa_ai_summaries';

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE report_id = %d", $this->id ),
            ARRAY_A
        );

        if ( ! $row ) {
            return null;
        }

        return [
            'executive_summary' => $row['executive_summary'],
            'issues'           => json_decode( $row['issues_json'] ?: '[]', true ),
            'poi'              => json_decode( $row['poi_json'] ?: '[]', true ),
            'comparison'       => json_decode( $row['comparison_json'] ?: '[]', true ),
            'generated_at'     => $row['generated_at'],
        ];
    }

    /**
     * Get report type label.
     *
     * @return string
     */
    public function get_type_label() {
        $labels = [
            self::TYPE_NEW_ACQUISITION => __( 'New Acquisition', 'chroma-qa-reports' ),
            self::TYPE_TIER1           => __( 'Tier 1', 'chroma-qa-reports' ),
            self::TYPE_TIER1_TIER2     => __( 'Tier 1 + Tier 2', 'chroma-qa-reports' ),
        ];
        return $labels[ $this->report_type ] ?? $this->report_type;
    }

    /**
     * Get rating label.
     *
     * @return string
     */
    public function get_rating_label() {
        $labels = [
            self::RATING_EXCEEDS           => __( 'Exceeds', 'chroma-qa-reports' ),
            self::RATING_MEETS             => __( 'Meets', 'chroma-qa-reports' ),
            self::RATING_NEEDS_IMPROVEMENT => __( 'Needs Improvement', 'chroma-qa-reports' ),
            self::RATING_PENDING           => __( 'Pending', 'chroma-qa-reports' ),
        ];
        return $labels[ $this->overall_rating ] ?? $this->overall_rating;
    }

    /**
     * Get status label.
     *
     * @return string
     */
    public function get_status_label() {
        $labels = [
            self::STATUS_DRAFT     => __( 'Draft', 'chroma-qa-reports' ),
            self::STATUS_SUBMITTED => __( 'Submitted', 'chroma-qa-reports' ),
            self::STATUS_APPROVED  => __( 'Approved', 'chroma-qa-reports' ),
        ];
        return $labels[ $this->status ] ?? $this->status;
    }

    /**
     * Get the latest report for a school.
     *
     * @param int $school_id School ID.
     * @return Report|null
     */
    public static function get_latest_for_school( $school_id ) {
        $reports = self::all( [
            'school_id' => $school_id,
            'status'    => 'approved',
            'orderby'   => 'inspection_date',
            'order'     => 'DESC',
            'limit'     => 1,
        ] );

        return ! empty( $reports ) ? $reports[0] : null;
    }

    /**
     * Get reports by school with additional filtering.
     *
     * @param int   $school_id School ID.
     * @param array $args Additional query arguments.
     * @return Report[]
     */
    public static function get_by_school( $school_id, $args = [] ) {
        $args['school_id'] = $school_id;
        return self::all( $args );
    }
}

