<?php
/**
 * Report Template Model
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Models;

/**
 * Template entity for reusable report templates.
 */
class Template {

    /**
     * Table name.
     */
    const TABLE = 'cqa_templates';

    /**
     * Template properties.
     */
    public $id;
    public $name;
    public $description;
    public $report_type;
    public $responses_json;
    public $notes_json;
    public $created_by;
    public $is_shared;
    public $created_at;
    public $updated_at;

    /**
     * Constructor.
     *
     * @param array $data Template data.
     */
    public function __construct( $data = [] ) {
        foreach ( $data as $key => $value ) {
            if ( property_exists( $this, $key ) ) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Get table name with prefix.
     *
     * @return string
     */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    /**
     * Create the templates table.
     */
    public static function create_table() {
        global $wpdb;
        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            report_type VARCHAR(50) NOT NULL DEFAULT 'tier1',
            responses_json LONGTEXT,
            notes_json LONGTEXT,
            created_by BIGINT(20) UNSIGNED,
            is_shared TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY created_by (created_by),
            KEY report_type (report_type)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Find template by ID.
     *
     * @param int $id Template ID.
     * @return Template|null
     */
    public static function find( $id ) {
        global $wpdb;
        $table = self::table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
            ARRAY_A
        );

        return $row ? new self( $row ) : null;
    }

    /**
     * Get all templates.
     *
     * @param array $args Query arguments.
     * @return array
     */
    public static function all( $args = [] ) {
        global $wpdb;
        $table = self::table_name();

        $defaults = [
            'report_type' => '',
            'created_by'  => 0,
            'is_shared'   => null,
            'limit'       => 50,
            'offset'      => 0,
        ];

        $args = wp_parse_args( $args, $defaults );
        $where = [ '1=1' ];
        $values = [];

        if ( $args['report_type'] ) {
            $where[] = 'report_type = %s';
            $values[] = $args['report_type'];
        }

        if ( $args['created_by'] ) {
            $where[] = 'created_by = %d';
            $values[] = $args['created_by'];
        }

        if ( $args['is_shared'] !== null ) {
            $where[] = 'is_shared = %d';
            $values[] = $args['is_shared'] ? 1 : 0;
        }

        $where_clause = implode( ' AND ', $where );

        $sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY name ASC LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];

        $results = $wpdb->get_results(
            $wpdb->prepare( $sql, $values ),
            ARRAY_A
        );

        return array_map( function( $row ) {
            return new self( $row );
        }, $results );
    }

    /**
     * Get templates available to user.
     *
     * @param int    $user_id User ID.
     * @param string $report_type Report type filter.
     * @return array
     */
    public static function get_for_user( $user_id, $report_type = '' ) {
        global $wpdb;
        $table = self::table_name();

        $where = '(created_by = %d OR is_shared = 1)';
        $values = [ $user_id ];

        if ( $report_type ) {
            $where .= ' AND report_type = %s';
            $values[] = $report_type;
        }

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY name ASC";

        $results = $wpdb->get_results(
            $wpdb->prepare( $sql, $values ),
            ARRAY_A
        );

        return array_map( function( $row ) {
            return new self( $row );
        }, $results );
    }

    /**
     * Save the template.
     *
     * @return int|false Template ID or false on failure.
     */
    public function save() {
        global $wpdb;
        $table = self::table_name();

        $data = [
            'name'           => $this->name,
            'description'    => $this->description,
            'report_type'    => $this->report_type ?: 'tier1',
            'responses_json' => $this->responses_json,
            'notes_json'     => $this->notes_json,
            'created_by'     => $this->created_by ?: get_current_user_id(),
            'is_shared'      => $this->is_shared ? 1 : 0,
        ];

        $format = [ '%s', '%s', '%s', '%s', '%s', '%d', '%d' ];

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
     * Delete the template.
     *
     * @return bool
     */
    public function delete() {
        global $wpdb;
        $table = self::table_name();

        return $wpdb->delete( $table, [ 'id' => $this->id ], [ '%d' ] ) !== false;
    }

    /**
     * Get responses as array.
     *
     * @return array
     */
    public function get_responses() {
        if ( ! $this->responses_json ) {
            return [];
        }
        return json_decode( $this->responses_json, true ) ?: [];
    }

    /**
     * Set responses from array.
     *
     * @param array $responses Responses array.
     */
    public function set_responses( $responses ) {
        $this->responses_json = wp_json_encode( $responses );
    }

    /**
     * Get notes as array.
     *
     * @return array
     */
    public function get_notes() {
        if ( ! $this->notes_json ) {
            return [];
        }
        return json_decode( $this->notes_json, true ) ?: [];
    }

    /**
     * Set notes from array.
     *
     * @param array $notes Notes array.
     */
    public function set_notes( $notes ) {
        $this->notes_json = wp_json_encode( $notes );
    }

    /**
     * Create template from report.
     *
     * @param int    $report_id Source report ID.
     * @param string $name Template name.
     * @return Template
     */
    public static function create_from_report( $report_id, $name ) {
        $report = Report::find( $report_id );
        if ( ! $report ) {
            return null;
        }

        $responses = Checklist_Response::get_by_report( $report_id );

        $template = new self();
        $template->name = $name;
        $template->report_type = $report->report_type;
        $template->description = sprintf(
            'Created from %s report on %s',
            $report->report_type,
            $report->inspection_date
        );

        // Convert responses to template format
        $template_responses = [];
        foreach ( $responses as $resp ) {
            if ( ! isset( $template_responses[ $resp->section_key ] ) ) {
                $template_responses[ $resp->section_key ] = [];
            }
            $template_responses[ $resp->section_key ][ $resp->item_key ] = [
                'rating' => $resp->rating,
                'notes'  => $resp->notes,
            ];
        }
        $template->set_responses( $template_responses );

        $template->save();
        return $template;
    }
}
