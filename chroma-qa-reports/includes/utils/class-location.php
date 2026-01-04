<?php
/**
 * Location Verification
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Utils;

use ChromaQA\Models\School;

/**
 * Handles GPS location verification.
 */
class Location {

    /**
     * Distance threshold in meters.
     */
    const DISTANCE_THRESHOLD = 500; // 500 meters

    /**
     * Verify location matches school.
     *
     * @param int   $school_id School ID.
     * @param float $latitude User's latitude.
     * @param float $longitude User's longitude.
     * @return array Verification result.
     */
    public static function verify_location( $school_id, $latitude, $longitude ) {
        $school = School::find( $school_id );
        
        if ( ! $school ) {
            return [
                'verified' => false,
                'error'    => 'School not found.',
            ];
        }

        // Check if school has coordinates
        if ( empty( $school->latitude ) || empty( $school->longitude ) ) {
            // Try to geocode the address
            $coords = self::geocode_address( $school->address ?? '' );
            
            if ( $coords ) {
                $school->latitude = $coords['lat'];
                $school->longitude = $coords['lng'];
                $school->save();
            } else {
                return [
                    'verified'    => true,
                    'warning'     => 'School location not set. Verification skipped.',
                    'can_proceed' => true,
                ];
            }
        }

        // Calculate distance
        $distance = self::calculate_distance(
            $latitude,
            $longitude,
            (float) $school->latitude,
            (float) $school->longitude
        );

        $is_nearby = $distance <= self::DISTANCE_THRESHOLD;

        return [
            'verified'       => $is_nearby,
            'distance'       => round( $distance ),
            'threshold'      => self::DISTANCE_THRESHOLD,
            'school_name'    => $school->name,
            'school_address' => $school->address ?? '',
            'message'        => $is_nearby 
                ? 'Location verified! You are at ' . $school->name
                : 'You appear to be ' . round( $distance ) . 'm away from ' . $school->name,
            'can_proceed'    => true, // Allow override
        ];
    }

    /**
     * Calculate distance between two coordinates using Haversine formula.
     *
     * @param float $lat1 Latitude 1.
     * @param float $lon1 Longitude 1.
     * @param float $lat2 Latitude 2.
     * @param float $lon2 Longitude 2.
     * @return float Distance in meters.
     */
    public static function calculate_distance( $lat1, $lon1, $lat2, $lon2 ) {
        $earth_radius = 6371000; // Earth's radius in meters

        $lat1_rad = deg2rad( $lat1 );
        $lat2_rad = deg2rad( $lat2 );
        $delta_lat = deg2rad( $lat2 - $lat1 );
        $delta_lon = deg2rad( $lon2 - $lon1 );

        $a = sin( $delta_lat / 2 ) * sin( $delta_lat / 2 ) +
             cos( $lat1_rad ) * cos( $lat2_rad ) *
             sin( $delta_lon / 2 ) * sin( $delta_lon / 2 );

        $c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

        return $earth_radius * $c;
    }

    /**
     * Geocode an address to coordinates.
     *
     * @param string $address Address to geocode.
     * @return array|null Coordinates or null.
     */
    public static function geocode_address( $address ) {
        if ( empty( $address ) ) {
            return null;
        }

        $api_key = \get_option( 'cqa_google_maps_api_key', '' );
        
        if ( empty( $api_key ) ) {
            // Try without API key (limited usage)
            $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode( $address );
        } else {
            $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode( $address ) . '&key=' . $api_key;
        }

        $response = \wp_remote_get( $url );
        
        if ( \is_wp_error( $response ) ) {
            return null;
        }

        $body = json_decode( \wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['results'][0]['geometry']['location'] ) ) {
            return $body['results'][0]['geometry']['location'];
        }

        return null;
    }

    /**
     * Register REST endpoint for location verification.
     */
    public static function register_routes() {
        \register_rest_route( 'cqa/v1', '/location/verify', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_verify' ],
            'permission_callback' => function() {
                return \current_user_can( 'cqa_create_reports' );
            },
            'args'                => [
                'school_id' => [ 'type' => 'integer', 'required' => true ],
                'latitude'  => [ 'type' => 'number', 'required' => true ],
                'longitude' => [ 'type' => 'number', 'required' => true ],
            ],
        ] );

        \register_rest_route( 'cqa/v1', '/location/log-override', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'log_override' ],
            'permission_callback' => function() {
                return \current_user_can( 'cqa_create_reports' );
            },
            'args'                => [
                'school_id' => [ 'type' => 'integer', 'required' => true ],
                'reason'    => [ 'type' => 'string', 'required' => true ],
                'latitude'  => [ 'type' => 'number' ],
                'longitude' => [ 'type' => 'number' ],
            ],
        ] );
    }

    /**
     * Handle verify request.
     */
    public static function handle_verify( $request ) {
        return self::verify_location(
            $request['school_id'],
            $request['latitude'],
            $request['longitude']
        );
    }

    /**
     * Log location override.
     */
    public static function log_override( $request ) {
        $log = [
            'user_id'    => \get_current_user_id(),
            'school_id'  => $request['school_id'],
            'reason'     => \sanitize_text_field( $request['reason'] ),
            'latitude'   => $request['latitude'] ?? null,
            'longitude'  => $request['longitude'] ?? null,
            'timestamp'  => \current_time( 'mysql' ),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        ];

        // Store in options or custom table
        $overrides = \get_option( 'cqa_location_overrides', [] );
        $overrides[] = $log;
        
        // Keep last 100 overrides
        if ( count( $overrides ) > 100 ) {
            $overrides = array_slice( $overrides, -100 );
        }
        
        \update_option( 'cqa_location_overrides', $overrides );

        return [
            'success' => true,
            'message' => 'Override logged. You may proceed.',
        ];
    }

    /**
     * Initialize hooks.
     */
    public static function init() {
        \add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }
}
