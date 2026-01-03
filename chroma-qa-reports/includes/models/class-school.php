<?php
/**
 * School Model
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Models;

/**
 * Represents a school in the QA system.
 */
class School {

    /**
     * Table name.
     *
     * @var string
     */
    private static $table = 'cqa_schools';

    /**
     * School ID.
     *
     * @var int
     */
    public $id;

    /**
     * School name.
     *
     * @var string
     */
    public $name;

    /**
     * School location/address.
     *
     * @var string
     */
    public $location;

    /**
     * Regional grouping.
     *
     * @var string
     */
    public $region;

    /**
     * Date school was acquired.
     *
     * @var string
     */
    public $acquired_date;

    /**
     * School status (active/inactive).
     *
     * @var string
     */
    public $status;

    /**
     * Google Drive folder ID.
     *
     * @var string
     */
    public $drive_folder_id;

    /**
     * Classroom configuration JSON.
     *
     * @var array
     */
    public $classroom_config;

    /**
     * Created timestamp.
     *
     * @var string
     */
    public $created_at;

    /**
     * Updated timestamp.
     *
     * @var string
     */
    public $updated_at;

    /**
     * Get the full table name with prefix.
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::$table;
    }

    /**
     * Find a school by ID.
     *
     * @param int $id School ID.
     * @return School|null
     */
    public static function find( $id ) {
        global $wpdb;
        $table = self::get_table_name();
        
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
            ARRAY_A
        );

        if ( ! $row ) {
            return null;
        }

        return self::from_row( $row );
    }

    /**
     * Get all schools.
     *
     * @param array $args Query arguments.
     * @return School[]
     */
    public static function all( $args = [] ) {
        global $wpdb;
        $table = self::get_table_name();

        $defaults = [
            'status'   => '',
            'region'   => '',
            'orderby'  => 'name',
            'order'    => 'ASC',
            'limit'    => 100,
            'offset'   => 0,
        ];

        $args = wp_parse_args( $args, $defaults );

        $where = [];
        $values = [];

        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if ( ! empty( $args['region'] ) ) {
            $where[] = 'region = %s';
            $values[] = $args['region'];
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
     * Count total schools.
     *
     * @param array $args Query arguments.
     * @return int
     */
    public static function count( $args = [] ) {
        global $wpdb;
        $table = self::get_table_name();

        $where = [];
        $values = [];

        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        $where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

        if ( ! empty( $values ) ) {
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} {$where_clause}",
                $values
            ) );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    /**
     * Create a school from a database row.
     *
     * @param array $row Database row.
     * @return School
     */
    public static function from_row( $row ) {
        $school = new self();
        $school->id = (int) $row['id'];
        $school->name = $row['name'];
        $school->location = $row['location'];
        $school->region = $row['region'];
        $school->acquired_date = $row['acquired_date'];
        $school->status = $row['status'];
        $school->drive_folder_id = $row['drive_folder_id'];
        $school->classroom_config = json_decode( $row['classroom_config'] ?: '{}', true );
        $school->created_at = $row['created_at'];
        $school->updated_at = $row['updated_at'];
        return $school;
    }

    /**
     * Save the school to the database.
     *
     * @return bool|int False on failure, ID on success.
     */
    public function save() {
        global $wpdb;
        $table = self::get_table_name();

        $data = [
            'name'             => $this->name,
            'location'         => $this->location,
            'region'           => $this->region,
            'acquired_date'    => $this->acquired_date,
            'status'           => $this->status ?: 'active',
            'drive_folder_id'  => $this->drive_folder_id,
            'classroom_config' => wp_json_encode( $this->classroom_config ?: [] ),
        ];

        $format = [ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ];

        if ( $this->id ) {
            // Update existing
            $result = $wpdb->update( $table, $data, [ 'id' => $this->id ], $format, [ '%d' ] );
            return $result !== false ? $this->id : false;
        } else {
            // Insert new
            $result = $wpdb->insert( $table, $data, $format );
            if ( $result ) {
                $this->id = $wpdb->insert_id;
                return $this->id;
            }
            return false;
        }
    }

    /**
     * Delete the school.
     *
     * @return bool
     */
    public function delete() {
        if ( ! $this->id ) {
            return false;
        }

        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->delete( $table, [ 'id' => $this->id ], [ '%d' ] ) !== false;
    }

    /**
     * Get the most recent reports for this school.
     *
     * @param int $limit Number of reports to retrieve.
     * @return array
     */
    public function get_recent_reports( $limit = 2 ) {
        return Report::all( [
            'school_id' => $this->id,
            'limit'     => $limit,
            'orderby'   => 'inspection_date',
            'order'     => 'DESC',
        ] );
    }

    /**
     * Get available regions.
     *
     * @return array
     */
    public static function get_regions() {
        global $wpdb;
        $table = self::get_table_name();
        
        return $wpdb->get_col( "SELECT DISTINCT region FROM {$table} WHERE region != '' ORDER BY region" );
    }
}
