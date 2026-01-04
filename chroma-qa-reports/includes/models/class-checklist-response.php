<?php
/**
 * Checklist Response Model
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Models;

/**
 * Represents a single checklist item response.
 */
class Checklist_Response {

    /**
     * Table name.
     *
     * @var string
     */
    private static $table = 'cqa_responses';

    /**
     * Rating constants.
     */
    const RATING_YES = 'yes';
    const RATING_SOMETIMES = 'sometimes';
    const RATING_NO = 'no';
    const RATING_NA = 'na';

    /**
     * Response properties.
     */
    public $id;
    public $report_id;
    public $section_key;
    public $item_key;
    public $rating;
    public $notes;
    public $evidence_type;
    public $previous_rating;
    public $previous_notes;
    public $created_at;

    /**
     * Get table name.
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table;
    }

    /**
     * Get all responses for a report.
     *
     * @param int $report_id Report ID.
     * @return Checklist_Response[]
     */
    public static function get_by_report( $report_id ) {
        global $wpdb;
        $table = self::get_table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE report_id = %d ORDER BY section_key, item_key",
                $report_id
            ),
            \ARRAY_A
        );

        return array_map( [ self::class, 'from_row' ], $rows );
    }

    /**
     * Get responses grouped by section.
     *
     * @param int $report_id Report ID.
     * @return array Associative array keyed by section_key.
     */
    public static function get_by_report_grouped( $report_id ) {
        $responses = self::get_by_report( $report_id );
        $grouped = [];

        foreach ( $responses as $response ) {
            if ( ! isset( $grouped[ $response->section_key ] ) ) {
                $grouped[ $response->section_key ] = [];
            }
            $grouped[ $response->section_key ][ $response->item_key ] = $response;
        }

        return $grouped;
    }

    /**
     * Create from database row.
     *
     * @param array $row Database row.
     * @return Checklist_Response
     */
    public static function from_row( $row ) {
        $response = new self();
        $response->id = (int) $row['id'];
        $response->report_id = (int) $row['report_id'];
        $response->section_key = $row['section_key'];
        $response->item_key = $row['item_key'];
        $response->rating = $row['rating'];
        $response->notes = $row['notes'];
        $response->evidence_type = $row['evidence_type'];
        $response->previous_rating = $row['previous_rating'];
        $response->previous_notes = $row['previous_notes'];
        $response->created_at = $row['created_at'];
        return $response;
    }

    /**
     * Save the response.
     *
     * @return bool|int
     */
    public function save() {
        global $wpdb;
        $table = self::get_table_name();

        $data = [
            'report_id'       => $this->report_id,
            'section_key'     => $this->section_key,
            'item_key'        => $this->item_key,
            'rating'          => $this->rating ?: self::RATING_NA,
            'notes'           => $this->notes,
            'evidence_type'   => $this->evidence_type ?: 'observation',
            'previous_rating' => $this->previous_rating,
            'previous_notes'  => $this->previous_notes,
        ];

        $format = [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ];

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
     * Bulk save responses for a report.
     *
     * @param int   $report_id Report ID.
     * @param array $responses Array of response data.
     * @return bool
     */
    public static function bulk_save( $report_id, $responses ) {
        global $wpdb;
        $table = self::get_table_name();

        // Delete existing responses
        $wpdb->delete( $table, [ 'report_id' => $report_id ], [ '%d' ] );

        // Insert new responses
        foreach ( $responses as $section_key => $items ) {
            foreach ( $items as $item_key => $data ) {
                $wpdb->insert(
                    $table,
                    [
                        'report_id'       => $report_id,
                        'section_key'     => $section_key,
                        'item_key'        => $item_key,
                        'rating'          => $data['rating'] ?? self::RATING_NA,
                        'notes'           => $data['notes'] ?? '',
                        'evidence_type'   => $data['evidence_type'] ?? 'observation',
                        'previous_rating' => $data['previous_rating'] ?? '',
                        'previous_notes'  => $data['previous_notes'] ?? '',
                    ],
                    [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
                );
            }
        }

        return true;
    }

    /**
     * Get rating icon/emoji.
     *
     * @return string
     */
    public function get_rating_icon() {
        $icons = [
            self::RATING_YES       => '✅',
            self::RATING_SOMETIMES => '⚠️',
            self::RATING_NO        => '❌',
            self::RATING_NA        => '➖',
        ];
        return $icons[ $this->rating ] ?? '➖';
    }

    /**
     * Check if this response has changed from previous.
     *
     * @return bool
     */
    public function has_changed() {
        return $this->previous_rating && $this->previous_rating !== $this->rating;
    }

    /**
     * Check if this is an improvement from previous.
     *
     * @return bool
     */
    public function is_improvement() {
        if ( ! $this->has_changed() ) {
            return false;
        }

        $rating_order = [
            self::RATING_NO        => 0,
            self::RATING_SOMETIMES => 1,
            self::RATING_YES       => 2,
            self::RATING_NA        => -1,
        ];

        $prev = $rating_order[ $this->previous_rating ] ?? -1;
        $curr = $rating_order[ $this->rating ] ?? -1;

        return $curr > $prev;
    }
}
