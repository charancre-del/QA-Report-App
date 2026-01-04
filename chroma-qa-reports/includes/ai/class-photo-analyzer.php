<?php
/**
 * Photo Analyzer
 *
 * AI-powered photo analysis using Gemini Vision
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\AI;

/**
 * Analyzes photos for QA issues using Gemini Vision.
 */
class Photo_Analyzer {

    /**
     * Gemini API endpoint for vision.
     */
    const API_ENDPOINT = 'https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent';

    /**
     * Analyze a photo for QA issues.
     *
     * @param string $image_url URL or path to image.
     * @param string $context Optional context (e.g., section name).
     * @return array Analysis results.
     */
    public static function analyze( $image_url, $context = '' ) {
        $api_key = \get_option( 'cqa_gemini_api_key', '' );
        
        if ( empty( $api_key ) ) {
            return [ 'error' => 'Gemini API key not configured.' ];
        }

        // Get image data
        $image_data = self::get_image_data( $image_url );
        
        if ( is_wp_error( $image_data ) ) {
            return [ 'error' => $image_data->get_error_message() ];
        }

        // Build prompt
        $prompt = self::build_analysis_prompt( $context );

        // Call Gemini Vision API
        $response = self::call_gemini_vision( $api_key, $image_data, $prompt );

        return $response;
    }

    /**
     * Get image data as base64.
     *
     * @param string $image_url Image URL or path.
     * @return array|WP_Error Image data array or error.
     */
    private static function get_image_data( $image_url ) {
        // Check if it's a local file
        if ( file_exists( $image_url ) ) {
            $content = file_get_contents( $image_url );
            $mime = mime_content_type( $image_url );
        } else {
            // Fetch remote image
            $response = \wp_remote_get( $image_url );
            
            if ( \is_wp_error( $response ) ) {
                return $response;
            }

            $content = \wp_remote_retrieve_body( $response );
            $content_type = \wp_remote_retrieve_header( $response, 'content-type' );
            $mime = explode( ';', $content_type )[0];
        }

        if ( empty( $content ) ) {
            return new \WP_Error( 'empty_image', 'Could not retrieve image data.' );
        }

        return [
            'base64'    => base64_encode( $content ),
            'mime_type' => $mime,
        ];
    }

    /**
     * Build analysis prompt.
     *
     * @param string $context Context hint.
     * @return string Prompt text.
     */
    private static function build_analysis_prompt( $context = '' ) {
        $context_hint = $context ? "This photo is from the '{$context}' section of a childcare facility." : "This photo is from a childcare facility QA inspection.";

        return <<<PROMPT
You are a Quality Assurance inspector for a childcare facility. Analyze this image and identify:

1. **Safety Concerns**: Any hazards, tripping risks, sharp objects, unsecured items, blocked exits, or fire safety issues.

2. **Cleanliness Issues**: Visible dirt, clutter, disorganization, stains, or maintenance problems.

3. **Compliance Items**: Missing safety equipment, improper storage, posting requirements not met, age-inappropriate materials.

4. **Positive Observations**: Well-organized areas, good practices, safety measures in place.

5. **Section Assignment**: Suggest which QA checklist section this photo most likely belongs to:
   - Classroom
   - Kitchen/Laundry
   - Playground
   - Lobby/Office
   - Vehicle
   - Posted Notices
   - Health & Safety
   - Building/Maintenance

{$context_hint}

Respond in JSON format:
{
    "issues": [
        {
            "type": "safety|cleanliness|compliance",
            "severity": "high|medium|low",
            "description": "Brief description of the issue",
            "recommendation": "Suggested action"
        }
    ],
    "positives": ["List of positive observations"],
    "suggested_section": "Most appropriate section name",
    "needs_annotation": true/false,
    "annotation_suggestions": ["Where to draw circles/arrows"],
    "overall_assessment": "Brief overall assessment",
    "confidence": 0.0-1.0
}
PROMPT;
    }

    /**
     * Call Gemini Vision API.
     *
     * @param string $api_key API key.
     * @param array  $image_data Image data with base64 and mime_type.
     * @param string $prompt Analysis prompt.
     * @return array Analysis results.
     */
    private static function call_gemini_vision( $api_key, $image_data, $prompt ) {
        $url = self::API_ENDPOINT . '?key=' . $api_key;

        $body = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt,
                        ],
                        [
                            'inline_data' => [
                                'mime_type' => $image_data['mime_type'],
                                'data'      => $image_data['base64'],
                            ],
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'     => 0.2,
                'topP'            => 0.95,
                'maxOutputTokens' => 1024,
            ],
        ];

        $response = \wp_remote_post( $url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => \wp_json_encode( $body ),
            'timeout' => 60,
        ] );

        if ( \is_wp_error( $response ) ) {
            return [ 'error' => $response->get_error_message() ];
        }

        $body = json_decode( \wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            return [ 'error' => $body['error']['message'] ?? 'API error' ];
        }

        // Extract text response
        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Parse JSON from response
        preg_match( '/\{[\s\S]*\}/', $text, $matches );
        
        if ( ! empty( $matches[0] ) ) {
            $parsed = json_decode( $matches[0], true );
            if ( $parsed ) {
                return $parsed;
            }
        }

        return [
            'raw_response' => $text,
            'error'        => 'Could not parse structured response.',
        ];
    }

    /**
     * Batch analyze photos.
     *
     * @param array $photos Array of photo URLs.
     * @return array Analysis results for each photo.
     */
    public static function batch_analyze( $photos ) {
        $results = [];

        foreach ( $photos as $photo ) {
            $url = is_array( $photo ) ? ( $photo['url'] ?? '' ) : $photo;
            $context = is_array( $photo ) ? ( $photo['section'] ?? '' ) : '';

            if ( $url ) {
                $results[] = [
                    'url'      => $url,
                    'analysis' => self::analyze( $url, $context ),
                ];
            }
        }

        return $results;
    }

    /**
     * Register REST routes.
     */
    public static function register_routes() {
        \register_rest_route( 'cqa/v1', '/photos/analyze', [
            'methods'             => 'POST',
            'callback'            => function( $request ) {
                $url = $request['url'] ?? '';
                $context = $request['context'] ?? '';

                if ( empty( $url ) ) {
                    return new \WP_Error( 'missing_url', 'Photo URL is required.' );
                }

                return self::analyze( $url, $context );
            },
            'permission_callback' => function() {
                return \current_user_can( 'cqa_create_reports' );
            },
        ] );

        \register_rest_route( 'cqa/v1', '/photos/batch-analyze', [
            'methods'             => 'POST',
            'callback'            => function( $request ) {
                $photos = $request['photos'] ?? [];
                return self::batch_analyze( $photos );
            },
            'permission_callback' => function() {
                return \current_user_can( 'cqa_create_reports' );
            },
        ] );
    }

    /**
     * Initialize.
     */
    public static function init() {
        \add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }
}
