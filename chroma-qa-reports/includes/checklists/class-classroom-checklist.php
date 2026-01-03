<?php
/**
 * Classroom Checklist Manager
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Checklists;

/**
 * Manages per-classroom checklist items.
 */
class Classroom_Checklist {

    /**
     * Classroom items config.
     *
     * @var array|null
     */
    private static $config = null;

    /**
     * Load the classroom items configuration.
     *
     * @return array
     */
    public static function get_config() {
        if ( self::$config === null ) {
            $file = CQA_PLUGIN_DIR . 'includes/checklists/classroom-items.json';
            if ( file_exists( $file ) ) {
                self::$config = json_decode( file_get_contents( $file ), true );
            } else {
                self::$config = [];
            }
        }
        return self::$config;
    }

    /**
     * Get all classroom types.
     *
     * @return array
     */
    public static function get_classroom_types() {
        $config = self::get_config();
        return $config['classroom_types'] ?? [];
    }

    /**
     * Get active classrooms for a school.
     *
     * @param int $school_id School ID.
     * @return array
     */
    public static function get_school_classrooms( $school_id ) {
        $school = \ChromaQA\Models\School::find( $school_id );
        
        if ( ! $school ) {
            return self::get_classroom_types();
        }

        // Check if school has custom classroom config
        $classroom_config = $school->classroom_config ?? [];
        
        if ( ! empty( $classroom_config['active_classrooms'] ) ) {
            $all_types = self::get_classroom_types();
            $active_keys = $classroom_config['active_classrooms'];
            
            return array_filter( $all_types, function( $type ) use ( $active_keys ) {
                return in_array( $type['key'], $active_keys );
            });
        }

        return self::get_classroom_types();
    }

    /**
     * Get checklist items for a specific classroom type.
     *
     * @param string $classroom_key Classroom type key.
     * @return array
     */
    public static function get_items_for_classroom( $classroom_key ) {
        $config = self::get_config();
        $items = [];

        // Add common observation items
        $common_items = $config['observation_items'] ?? [];
        foreach ( $common_items as $item ) {
            $item['section_key'] = 'classroom_' . $classroom_key;
            $items[] = $item;
        }

        // Add age-specific items based on classroom type
        if ( in_array( $classroom_key, [ 'infant_a', 'infant_b' ] ) ) {
            $infant_items = $config['infant_only_items'] ?? [];
            foreach ( $infant_items as $item ) {
                $item['section_key'] = 'classroom_' . $classroom_key;
                $items[] = $item;
            }
        } elseif ( $classroom_key === 'toddler' ) {
            $toddler_items = $config['toddler_items'] ?? [];
            foreach ( $toddler_items as $item ) {
                $item['section_key'] = 'classroom_' . $classroom_key;
                $items[] = $item;
            }
        } elseif ( in_array( $classroom_key, [ 'twos', 'threes', 'fours', 'ga_prek' ] ) ) {
            $preschool_items = $config['preschool_items'] ?? [];
            foreach ( $preschool_items as $item ) {
                $item['section_key'] = 'classroom_' . $classroom_key;
                $items[] = $item;
            }
        } elseif ( $classroom_key === 'school_age' ) {
            $school_age_items = $config['school_age_items'] ?? [];
            foreach ( $school_age_items as $item ) {
                $item['section_key'] = 'classroom_' . $classroom_key;
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Build full classroom sections for a school.
     *
     * @param int $school_id School ID.
     * @return array Array of section objects.
     */
    public static function build_classroom_sections( $school_id ) {
        $classrooms = self::get_school_classrooms( $school_id );
        $sections = [];

        foreach ( $classrooms as $classroom ) {
            $items = self::get_items_for_classroom( $classroom['key'] );
            
            $sections[] = [
                'key'         => 'classroom_' . $classroom['key'],
                'name'        => $classroom['name'],
                'description' => sprintf(
                    'Ratio: %s, Group Size: %d',
                    $classroom['ratio'],
                    $classroom['group_size']
                ),
                'is_classroom' => true,
                'classroom_type' => $classroom['key'],
                'items'       => $items,
            ];
        }

        return $sections;
    }

    /**
     * Get classroom section by key.
     *
     * @param string $section_key Section key (e.g., 'classroom_infant_a').
     * @return array|null
     */
    public static function get_classroom_section( $section_key ) {
        if ( strpos( $section_key, 'classroom_' ) !== 0 ) {
            return null;
        }

        $classroom_key = str_replace( 'classroom_', '', $section_key );
        $all_types = self::get_classroom_types();
        
        foreach ( $all_types as $type ) {
            if ( $type['key'] === $classroom_key ) {
                return [
                    'key'         => $section_key,
                    'name'        => $type['name'],
                    'description' => sprintf( 'Ratio: %s, Group Size: %d', $type['ratio'], $type['group_size'] ),
                    'is_classroom' => true,
                    'classroom_type' => $classroom_key,
                    'items'       => self::get_items_for_classroom( $classroom_key ),
                ];
            }
        }

        return null;
    }

    /**
     * Get classroom name by key.
     *
     * @param string $key Classroom key.
     * @return string
     */
    public static function get_classroom_name( $key ) {
        $types = self::get_classroom_types();
        foreach ( $types as $type ) {
            if ( $type['key'] === $key ) {
                return $type['name'];
            }
        }
        return ucfirst( str_replace( '_', ' ', $key ) );
    }
}
