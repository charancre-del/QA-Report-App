<?php
/**
 * Gemini API Client
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\AI;

/**
 * Client for Google Gemini API.
 */
class Gemini_Service {

    /**
     * API endpoint.
     */
    const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent';

    /**
     * Get the API key.
     *
     * @return string
     */
    public static function get_api_key() {
        return \get_option( 'cqa_gemini_api_key', '' );
    }

    /**
     * Check if Gemini is configured.
     *
     * @return bool
     */
    public static function is_configured() {
        return ! empty( self::get_api_key() );
    }

    /**
     * Send a request to Gemini.
     *
     * @param string $prompt The prompt to send.
     * @param array  $options Additional options.
     * @return string|WP_Error
     */
    public static function generate( $prompt, $options = [] ) {
        if ( ! self::is_configured() ) {
            return new \WP_Error( 'not_configured', __( 'Gemini API key not configured.', 'chroma-qa-reports' ) );
        }

        $url = self::API_URL . '?key=' . self::get_api_key();

        $body = [
            'contents' => [
                [
                    'parts' => [
                        [ 'text' => $prompt ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'     => $options['temperature'] ?? 0.7,
                'topK'            => $options['topK'] ?? 40,
                'topP'            => $options['topP'] ?? 0.95,
                'maxOutputTokens' => $options['maxTokens'] ?? 2048,
            ],
        ];

        $response = \wp_remote_post( $url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => \wp_json_encode( $body ),
            'timeout' => 60,
        ] );

        if ( \is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = \wp_remote_retrieve_response_code( $response );
        $body = \json_decode( \wp_remote_retrieve_body( $response ), true );

        if ( $status_code !== 200 ) {
            $error_message = $body['error']['message'] ?? __( 'Unknown API error', 'chroma-qa-reports' );
            return new \WP_Error( 'api_error', $error_message );
        }

        if ( empty( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
            return new \WP_Error( 'no_response', __( 'No response from Gemini.', 'chroma-qa-reports' ) );
        }

        // Return array format for consistency
        return [
            'text' => $body['candidates'][0]['content']['parts'][0]['text'],
        ];
    }

    /**
     * Generate JSON response from Gemini.
     *
     * @param string $prompt The prompt.
     * @param array  $options Options.
     * @return array|WP_Error
     */
    public static function generate_json( $prompt, $options = [] ) {
        // Add JSON instruction to prompt
        $json_prompt = $prompt . "\n\nRespond ONLY with valid JSON. Do not include any other text or markdown formatting. Ensure all strings are properly escaped.";
        
        $response = self::generate( $json_prompt, $options );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        // Extract text from array response
        $text = is_array( $response ) ? ( $response['text'] ?? '' ) : $response;
        $text = trim( $text );

        // 1. Strip Markdown Code Blocks explicitly
        $text = preg_replace( '/^```json\s*/i', '', $text );
        $text = preg_replace( '/^```\s*/', '', $text );
        $text = preg_replace( '/\s*```$/', '', $text );

        // 2. Locate JSON object (find first { and last })
        $start = strpos( $text, '{' );
        $end = strrpos( $text, '}' );
        
        if ( $start !== false && $end !== false && $end > $start ) {
            $text = substr( $text, $start, $end - $start + 1 );
        }

        // 3. Attempt Decode
        $data = json_decode( $text, true );

        // 4. Fallback: Try cleaning control characters if decode failed
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            error_log( 'CQA Gemini JSON Error: ' . json_last_error_msg() );
            // Log a snippet for safety
            error_log( 'CQA Gemini Failed Text: ' . substr( $text, 0, 500 ) . '...' ); 
            return new \WP_Error( 'json_parse_error', '[DEBUG-NEW-SERVICE] JSON Error: ' . json_last_error_msg() . ' | Content: ' . substr( $text, 0, 200 ) );
        }

        return $data;
    }

    /**
     * Parse text from a document into structured report data.
     *
     * @param string $text The raw text from the document.
     * @return array|WP_Error Parsed data or error.
     */
    public static function parse_document( $text ) {
        // We need to map the text to our schema
        $prompt = "You are a QA Data Extraction AI.\n\n" .
                  "I will provide text from a childcare QA inspection report. " .
                  "Extract the ratings and notes for the following checklist items and return a JSON object.\n\n" .
                  "STRUCTURE:\n" .
                  "{\n" .
                  "  \"responses\": {\n" .
                  "     \"section_key\": {\n" .
                  "        \"item_key\": { \"rating\": \"yes|no|sometimes|n/a\", \"notes\": \"...\" }\n" .
                  "     }\n" .
                  "  },\n" .
                  "  \"report_type\": \"tier1\",\n" .
                  "  \"overall_rating\": \"exceeds|meets|needs_improvement|pending\",\n" .
                  "  \"closing_notes\": \"Summary text...\"\n" .
                  "}\n\n" .
                  "RULES:\n" .
                  "1. Map loose terms: 'Consistently'->'yes', 'Needs Improvement'->'sometimes', 'Not Met'->'no'.\n" .
                  "2. If an item is not mentioned, ignore it (do not include in JSON).\n" .
                  "3. Extract section/item structure based on standard Chroma QA Tier 1 checklist.\n" .
                  "4. Respond ONLY with valid JSON.\n\n" .
                  "REPORT TEXT:\n" . $text;

        return self::generate_json( $prompt, [ 'maxTokens' => 4000 ] );
    }
}
