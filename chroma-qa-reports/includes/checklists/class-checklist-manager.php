<?php
/**
 * Checklist Manager
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Checklists;

/**
 * Manages checklist definitions and versions.
 */
class Checklist_Manager {

    /**
     * Cached checklists.
     *
     * @var array
     */
    private static $cache = [];

    /**
     * Get the Tier 1 checklist definition.
     *
     * @return array
     */
    public static function get_tier1_checklist() {
        if ( ! isset( self::$cache['tier1'] ) ) {
            $file = CQA_PLUGIN_DIR . 'includes/checklists/tier-1-checklist.json';
            $json = file_get_contents( $file );
            self::$cache['tier1'] = json_decode( $json, true );
        }
        return self::$cache['tier1'];
    }

    /**
     * Get the Tier 2 checklist definition.
     *
     * @return array
     */
    public static function get_tier2_checklist() {
        if ( ! isset( self::$cache['tier2'] ) ) {
            $file = CQA_PLUGIN_DIR . 'includes/checklists/tier-2-checklist.json';
            $json = file_get_contents( $file );
            self::$cache['tier2'] = json_decode( $json, true );
        }
        return self::$cache['tier2'];
    }

    /**
     * Get the combined Tier 1 + Tier 2 checklist.
     *
     * @return array
     */
    public static function get_combined_checklist() {
        if ( ! isset( self::$cache['combined'] ) ) {
            $tier1 = self::get_tier1_checklist();
            $tier2 = self::get_tier2_checklist();

            $combined = $tier1;
            $combined['name'] = 'Tier 1 + Tier 2 QA & Compliance Checklist';
            $combined['description'] = 'Full QA inspection with Continuous Quality Improvement add-on';
            
            // Append Tier 2 sections
            foreach ( $tier2['sections'] as $section ) {
                $section['tier'] = 2; // Mark as Tier 2 section
                $combined['sections'][] = $section;
            }

            self::$cache['combined'] = $combined;
        }
        return self::$cache['combined'];
    }

    /**
     * Get checklist by report type.
     *
     * @param string $type Report type.
     * @return array
     */
    public static function get_checklist_for_type( $type ) {
        switch ( $type ) {
            case 'tier1_tier2':
                return self::get_combined_checklist();
            case 'tier1':
            case 'new_acquisition':
            default:
                return self::get_tier1_checklist();
        }
    }

    /**
     * Get a flat list of all items for a checklist.
     *
     * @param string $type Report type.
     * @return array
     */
    public static function get_all_items_flat( $type ) {
        $checklist = self::get_checklist_for_type( $type );
        $items = [];

        foreach ( $checklist['sections'] as $section ) {
            foreach ( $section['items'] as $item ) {
                $items[] = [
                    'section_key' => $section['key'],
                    'section_name' => $section['name'],
                    'item_key' => $item['key'],
                    'item_label' => $item['label'],
                    'item_type' => $item['type'],
                    'evidence' => $item['evidence'] ?? 'observation',
                    'tier' => $section['tier'] ?? 1,
                ];
            }
        }

        return $items;
    }

    /**
     * Get section by key.
     *
     * @param string $section_key Section key.
     * @param string $type Report type.
     * @return array|null
     */
    public static function get_section( $section_key, $type = 'tier1' ) {
        $checklist = self::get_checklist_for_type( $type );

        foreach ( $checklist['sections'] as $section ) {
            if ( $section['key'] === $section_key ) {
                return $section;
            }
        }

        return null;
    }

    /**
     * Get item by key.
     *
     * @param string $section_key Section key.
     * @param string $item_key Item key.
     * @param string $type Report type.
     * @return array|null
     */
    public static function get_item( $section_key, $item_key, $type = 'tier1' ) {
        $section = self::get_section( $section_key, $type );

        if ( ! $section ) {
            return null;
        }

        foreach ( $section['items'] as $item ) {
            if ( $item['key'] === $item_key ) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Count total items in a checklist.
     *
     * @param string $type Report type.
     * @return int
     */
    public static function count_items( $type ) {
        $checklist = self::get_checklist_for_type( $type );
        $count = 0;

        foreach ( $checklist['sections'] as $section ) {
            $count += count( $section['items'] );
        }

        return $count;
    }

    /**
     * Get progress stats for a report.
     *
     * @param int    $report_id Report ID.
     * @param string $type Report type.
     * @return array
     */
    public static function get_progress_stats( $report_id, $type ) {
        $total = self::count_items( $type );
        $responses = \ChromaQA\Models\Checklist_Response::get_by_report( $report_id );
        
        $completed = 0;
        $yes_count = 0;
        $sometimes_count = 0;
        $no_count = 0;

        foreach ( $responses as $response ) {
            if ( $response->rating !== 'na' ) {
                $completed++;
                switch ( $response->rating ) {
                    case 'yes':
                        $yes_count++;
                        break;
                    case 'sometimes':
                        $sometimes_count++;
                        break;
                    case 'no':
                        $no_count++;
                        break;
                }
            }
        }

        return [
            'total'      => $total,
            'completed'  => $completed,
            'percentage' => $total > 0 ? round( ( $completed / $total ) * 100 ) : 0,
            'yes'        => $yes_count,
            'sometimes'  => $sometimes_count,
            'no'         => $no_count,
        ];
    }

    /**
     * Get sections list for navigation.
     *
     * @param string $type Report type.
     * @return array
     */
    public static function get_sections_list( $type ) {
        $checklist = self::get_checklist_for_type( $type );
        $sections = [];

        foreach ( $checklist['sections'] as $section ) {
            $sections[] = [
                'key'         => $section['key'],
                'name'        => $section['name'],
                'description' => $section['description'] ?? '',
                'item_count'  => count( $section['items'] ),
                'tier'        => $section['tier'] ?? 1,
            ];
        }

        return $sections;
    }
}
