<?php
/**
 * Photo Model
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Models;

/**
 * Represents a photo attached to a report.
 */
class Photo {

    /**
     * Table name.
     *
     * @var string
     */
    private static $table = 'cqa_photos';

    /**
     * Photo properties.
     */
    public $id;
    public $report_id;
    public $section_key;
    public $item_key;
    public $location_tag;
    public $drive_file_id;
    public $filename;
    public $caption;
    public $has_markup;
    public $sort_order;
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
     * Find a photo by ID.
     *
     * @param int $id Photo ID.
     * @return Photo|null
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
     * Get all photos for a report.
     *
     * @param int $report_id Report ID.
     * @return Photo[]
     */
    public static function get_by_report( $report_id ) {
        global $wpdb;
        $table = self::get_table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE report_id = %d ORDER BY section_key, sort_order",
                $report_id
            ),
            ARRAY_A
        );

        return array_map( [ self::class, 'from_row' ], $rows );
    }

    /**
     * Get photos grouped by section.
     *
     * @param int $report_id Report ID.
     * @return array
     */
    public static function get_by_report_grouped( $report_id ) {
        $photos = self::get_by_report( $report_id );
        $grouped = [];

        foreach ( $photos as $photo ) {
            $key = $photo->section_key ?: 'general';
            if ( ! isset( $grouped[ $key ] ) ) {
                $grouped[ $key ] = [];
            }
            $grouped[ $key ][] = $photo;
        }

        return $grouped;
    }

    /**
     * Create from database row.
     *
     * @param array $row Database row.
     * @return Photo
     */
    public static function from_row( $row ) {
        $photo = new self();
        $photo->id = (int) $row['id'];
        $photo->report_id = (int) $row['report_id'];
        $photo->section_key = $row['section_key'];
        $photo->item_key = $row['item_key'] ?? null;
        $photo->location_tag = $row['location_tag'] ?? null;
        $photo->drive_file_id = $row['drive_file_id'];
        $photo->filename = $row['filename'];
        $photo->caption = $row['caption'];
        $photo->has_markup = (bool) $row['has_markup'];
        $photo->sort_order = (int) $row['sort_order'];
        $photo->created_at = $row['created_at'];
        return $photo;
    }

    /**
     * Get photos for a specific checklist item.
     *
     * @param int    $report_id Report ID.
     * @param string $item_key  Checklist item key.
     * @return Photo[]
     */
    public static function get_by_item( $report_id, $item_key ) {
        global $wpdb;
        $table = self::get_table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE report_id = %d AND item_key = %s ORDER BY sort_order",
                $report_id,
                $item_key
            ),
            ARRAY_A
        );

        return array_map( [ self::class, 'from_row' ], $rows );
    }

    /**
     * Save the photo.
     *
     * @return bool|int
     */
    public function save() {
        global $wpdb;
        $table = self::get_table_name();

        $data = [
            'report_id'     => $this->report_id,
            'section_key'   => $this->section_key,
            'item_key'      => $this->item_key,
            'location_tag'  => $this->location_tag,
            'drive_file_id' => $this->drive_file_id,
            'filename'      => $this->filename,
            'caption'       => $this->caption,
            'has_markup'    => $this->has_markup ? 1 : 0,
            'sort_order'    => $this->sort_order ?: 0,
        ];

        $format = [ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' ];

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
     * Delete the photo.
     *
     * @return bool
     */
    public function delete() {
        if ( ! $this->id ) {
            return false;
        }

        global $wpdb;
        return $wpdb->delete( self::get_table_name(), [ 'id' => $this->id ], [ '%d' ] ) !== false;
    }

    /**
     * Get Google Drive thumbnail URL.
     *
     * @param int $size Thumbnail size in pixels.
     * @return string
     */
    public function get_thumbnail_url( $size = 200 ) {
        if ( empty( $this->drive_file_id ) ) {
            return '';
        }
        return "https://drive.google.com/thumbnail?id={$this->drive_file_id}&sz=w{$size}";
    }

    /**
     * Get Google Drive view URL.
     *
     * @return string
     */
    public function get_view_url() {
        if ( empty( $this->drive_file_id ) ) {
            return '';
        }
        return "https://drive.google.com/file/d/{$this->drive_file_id}/view";
    }

    /**
     * Get section label.
     *
     * @return string
     */
    public function get_section_label() {
        $labels = [
            'general'       => __( 'General', 'chroma-qa-reports' ),
            'classrooms'    => __( 'Classrooms', 'chroma-qa-reports' ),
            'playgrounds'   => __( 'Playgrounds', 'chroma-qa-reports' ),
            'vehicles'      => __( 'Vehicles', 'chroma-qa-reports' ),
            'kitchen'       => __( 'Kitchen/Laundry', 'chroma-qa-reports' ),
            'lobby'         => __( 'Lobby/Office', 'chroma-qa-reports' ),
            'maintenance'   => __( 'Building/Maintenance', 'chroma-qa-reports' ),
            'sleep_nap'     => __( 'Sleep/Nap', 'chroma-qa-reports' ),
            'curb_appeal'   => __( 'Curb Appeal', 'chroma-qa-reports' ),
        ];
        return $labels[ $this->section_key ] ?? ucfirst( str_replace( '_', ' ', $this->section_key ) );
    }
}
