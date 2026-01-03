<?php
/**
 * Photo Comparison
 * 
 * Compares photos across reports by location tag
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Utils;

use ChromaQA\Models\Photo;
use ChromaQA\Models\Report;

/**
 * Photo Comparison Service
 */
class Photo_Comparison {

    /**
     * Location tag presets for consistent tagging.
     *
     * @var array
     */
    private static $location_presets = [
        'lobby_entrance'     => 'Lobby / Entrance',
        'front_desk'         => 'Front Desk',
        'director_office'    => 'Director\'s Office',
        'kitchen'            => 'Kitchen',
        'kitchen_storage'    => 'Kitchen Storage',
        'laundry'            => 'Laundry Room',
        'playground_main'    => 'Main Playground',
        'playground_infant'  => 'Infant Playground',
        'playground_toddler' => 'Toddler Playground',
        'parking_lot'        => 'Parking Lot',
        'building_exterior'  => 'Building Exterior',
        'dumpster_area'      => 'Dumpster Area',
        'hallway_main'       => 'Main Hallway',
        'infant_a_room'      => 'Infant A Room',
        'infant_b_room'      => 'Infant B Room',
        'toddler_room'       => 'Toddler Room',
        'twos_room'          => 'Two\'s Room',
        'threes_room'        => 'Three\'s Room',
        'fours_room'         => 'Four\'s Room',
        'prek_room'          => 'Pre-K Room',
        'school_age_room'    => 'School-Age Room',
        'restroom_child'     => 'Child Restroom',
        'restroom_adult'     => 'Adult Restroom',
        'fire_extinguisher'  => 'Fire Extinguisher',
        'emergency_exit'     => 'Emergency Exit',
        'bulletin_board'     => 'Bulletin Board',
        'bus_exterior'       => 'Bus/Van Exterior',
        'bus_interior'       => 'Bus/Van Interior',
    ];

    /**
     * Get location presets.
     *
     * @return array
     */
    public static function get_location_presets() {
        return self::$location_presets;
    }

    /**
     * Get comparison photos for a report.
     * 
     * Matches photos from current report with photos from previous report
     * based on location_tag.
     *
     * @param int $current_report_id  Current report ID.
     * @param int $previous_report_id Previous report ID.
     * @return array Array of comparison pairs.
     */
    public static function get_comparison_pairs( $current_report_id, $previous_report_id ) {
        $current_photos = Photo::get_by_report( $current_report_id );
        $previous_photos = Photo::get_by_report( $previous_report_id );

        // Index previous photos by location_tag
        $previous_by_location = [];
        foreach ( $previous_photos as $photo ) {
            if ( ! empty( $photo->location_tag ) ) {
                if ( ! isset( $previous_by_location[ $photo->location_tag ] ) ) {
                    $previous_by_location[ $photo->location_tag ] = [];
                }
                $previous_by_location[ $photo->location_tag ][] = $photo;
            }
        }

        $comparisons = [];

        foreach ( $current_photos as $current_photo ) {
            if ( empty( $current_photo->location_tag ) ) {
                continue;
            }

            $location = $current_photo->location_tag;
            $previous_photo = null;

            if ( isset( $previous_by_location[ $location ] ) && ! empty( $previous_by_location[ $location ] ) ) {
                // Get the first matching previous photo
                $previous_photo = array_shift( $previous_by_location[ $location ] );
            }

            $comparisons[] = [
                'location_tag'   => $location,
                'location_label' => self::$location_presets[ $location ] ?? ucwords( str_replace( '_', ' ', $location ) ),
                'current'        => [
                    'id'            => $current_photo->id,
                    'thumbnail_url' => $current_photo->get_thumbnail_url( 400 ),
                    'view_url'      => $current_photo->get_view_url(),
                    'caption'       => $current_photo->caption,
                    'date'          => $current_photo->created_at,
                ],
                'previous'       => $previous_photo ? [
                    'id'            => $previous_photo->id,
                    'thumbnail_url' => $previous_photo->get_thumbnail_url( 400 ),
                    'view_url'      => $previous_photo->get_view_url(),
                    'caption'       => $previous_photo->caption,
                    'date'          => $previous_photo->created_at,
                ] : null,
            ];
        }

        // Sort by location label
        usort( $comparisons, function( $a, $b ) {
            return strcmp( $a['location_label'], $b['location_label'] );
        });

        return $comparisons;
    }

    /**
     * Get photos that only exist in the previous report (removed/not retaken).
     *
     * @param int $current_report_id  Current report ID.
     * @param int $previous_report_id Previous report ID.
     * @return array Array of orphaned previous photos.
     */
    public static function get_orphaned_previous_photos( $current_report_id, $previous_report_id ) {
        $current_photos = Photo::get_by_report( $current_report_id );
        $previous_photos = Photo::get_by_report( $previous_report_id );

        // Get current location tags
        $current_locations = [];
        foreach ( $current_photos as $photo ) {
            if ( ! empty( $photo->location_tag ) ) {
                $current_locations[] = $photo->location_tag;
            }
        }

        // Find previous photos not in current
        $orphaned = [];
        foreach ( $previous_photos as $photo ) {
            if ( ! empty( $photo->location_tag ) && ! in_array( $photo->location_tag, $current_locations ) ) {
                $orphaned[] = [
                    'location_tag'   => $photo->location_tag,
                    'location_label' => self::$location_presets[ $photo->location_tag ] ?? ucwords( str_replace( '_', ' ', $photo->location_tag ) ),
                    'photo'          => [
                        'id'            => $photo->id,
                        'thumbnail_url' => $photo->get_thumbnail_url( 400 ),
                        'view_url'      => $photo->get_view_url(),
                        'caption'       => $photo->caption,
                    ],
                ];
            }
        }

        return $orphaned;
    }

    /**
     * Generate comparison summary.
     *
     * @param int $current_report_id  Current report ID.
     * @param int $previous_report_id Previous report ID.
     * @return array Summary statistics.
     */
    public static function get_comparison_summary( $current_report_id, $previous_report_id ) {
        $comparisons = self::get_comparison_pairs( $current_report_id, $previous_report_id );
        $orphaned = self::get_orphaned_previous_photos( $current_report_id, $previous_report_id );

        $matched = 0;
        $new_only = 0;

        foreach ( $comparisons as $c ) {
            if ( $c['previous'] ) {
                $matched++;
            } else {
                $new_only++;
            }
        }

        return [
            'total_current'   => count( $comparisons ),
            'matched_pairs'   => $matched,
            'new_locations'   => $new_only,
            'missing_in_new'  => count( $orphaned ),
        ];
    }
}
