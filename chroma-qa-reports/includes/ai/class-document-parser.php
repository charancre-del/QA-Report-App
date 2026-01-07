<?php
/**
 * Document Parser
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\AI;

/**
 * Parses DOCX documents to extract QA report data.
 */
class Document_Parser {

    /**
     * Parse a DOCX document.
     *
     * @param string $file_path Path to the DOCX file.
     * @return array|WP_Error
     */
    public function parse( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return new \WP_Error( 'file_not_found', __( 'Document file not found.', 'chroma-qa-reports' ) );
        }

        // Extract text from DOCX
        $text = $this->extract_docx_text( $file_path );

        if ( \is_wp_error( $text ) ) {
            return $text;
        }

        if ( empty( $text ) ) {
            return new \WP_Error( 'empty_document', __( 'Could not extract text from document.', 'chroma-qa-reports' ) );
        }

        // Use AI to parse the text if configured
        if ( Gemini_Service::is_configured() ) {
            return $this->parse_with_ai( $text );
        }

        // Fallback to basic parsing
        return $this->parse_basic( $text );
    }

    /**
     * Extract text from DOCX file.
     *
     * @param string $file_path Path to DOCX file.
     * @return string|WP_Error
     */
    private function extract_docx_text( $file_path ) {
        $zip = new \ZipArchive();
        
        if ( $zip->open( $file_path ) !== true ) {
            return new \WP_Error( 'invalid_docx', \__( 'Could not open DOCX file.', 'chroma-qa-reports' ) );
        }

        $content = $zip->getFromName( 'word/document.xml' );
        $zip->close();

        if ( $content === false ) {
            return new \WP_Error( 'no_content', __( 'Could not read document content.', 'chroma-qa-reports' ) );
        }

        // Parse XML and extract text
        $xml = \simplexml_load_string( $content, 'SimpleXMLElement', LIBXML_NOWARNING );
        
        if ( $xml === false ) {
            return new \WP_Error( 'xml_error', __( 'Could not parse document XML.', 'chroma-qa-reports' ) );
        }

        // Register namespaces
        $namespaces = $xml->getNamespaces( true );
        $xml->registerXPathNamespace( 'w', $namespaces['w'] ?? 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );

        // Extract all text content
        $text_nodes = $xml->xpath( '//w:t' );
        $text = '';

        foreach ( $text_nodes as $node ) {
            $text .= (string) $node . ' ';
        }

        // Try to extract table content as well
        $tables = $xml->xpath( '//w:tbl' );
        foreach ( $tables as $table ) {
            foreach ( $table->xpath( './/w:tc' ) as $cell ) {
                foreach ( $cell->xpath( './/w:t' ) as $cell_text ) {
                    $text .= (string) $cell_text . "\t";
                }
                $text .= "\n";
            }
        }

        return trim( $text );
    }

    /**
     * Parse document text using AI.
     *
     * @param string $text Document text.
     * @return array|WP_Error
     */
    private function parse_with_ai( $text ) {
        // Truncate if too long
        if ( strlen( $text ) > 30000 ) {
            $text = substr( $text, 0, 30000 ) . '...';
        }

        $prompt = "Parse the following QA inspection report document and extract structured data.\n\n";
        $prompt .= "## Document Content:\n{$text}\n\n";
        $prompt .= "## Instructions:\n";
        $prompt .= "Extract and return a JSON object with the following structure:\n";
        $prompt .= "{\n";
        $prompt .= '  "school_name": "extracted school name",';
        $prompt .= "\n";
        $prompt .= '  "inspection_date": "YYYY-MM-DD format",';
        $prompt .= "\n";
        $prompt .= '  "report_type": "new_acquisition|tier1|tier1_tier2",';
        $prompt .= "\n";
        $prompt .= '  "responses": {';
        $prompt .= "\n";
        $prompt .= '    "section_key": {';
        $prompt .= "\n";
        $prompt .= '      "item_key": { "rating": "yes|sometimes|no|na", "notes": "any notes" }';
        $prompt .= "\n";
        $prompt .= "    }\n";
        $prompt .= "  },\n";
        $prompt .= '  "overall_rating": "exceeds|meets|needs_improvement",';
        $prompt .= "\n";
        $prompt .= '  "closing_notes": "any closing observations"';
        $prompt .= "\n";
        $prompt .= "}\n\n";
        $prompt .= "Match section and item keys to the standard Chroma QA checklist sections:\n";
        $prompt .= "- state_compliance, classroom_ratios, health_safety, classrooms\n";
        $prompt .= "- sleep_nap, kitchen_laundry, playgrounds, vehicles\n";
        $prompt .= "- posted_notices, first_aid_kit, building_maintenance, lobby_office_staff\n";
        $prompt .= "For Tier 2: physical_environment, relationships_interactions, curriculum_teaching, etc.\n";

        return Gemini_Service::generate_json( $prompt, [
            'temperature' => 0.2,
            'maxTokens'   => 4000,
        ] );
    }

    /**
     * Basic text parsing without AI.
     *
     * @param string $text Document text.
     * @return array
     */
    private function parse_basic( $text ) {
        $result = [
            'school_name'     => '',
            'inspection_date' => '',
            'report_type'     => 'tier1',
            'responses'       => [],
            'overall_rating'  => 'pending',
            'closing_notes'   => '',
        ];

        // Try to extract school name
        if ( preg_match( '/(?:School|Location|Center):\s*(.+?)(?:\n|$)/i', $text, $matches ) ) {
            $result['school_name'] = trim( $matches[1] );
        }

        // Try to extract date
        if ( \preg_match( '/(?:Date|Inspection Date):\s*(\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4})/i', $text, $matches ) ) {
            $result['inspection_date'] = \date( 'Y-m-d', \strtotime( $matches[1] ) );
        }

        // Detect report type
        if ( stripos( $text, 'Tier 2' ) !== false || stripos( $text, 'CQI' ) !== false ) {
            $result['report_type'] = 'tier1_tier2';
        } elseif ( stripos( $text, 'New Acquisition' ) !== false || stripos( $text, 'Initial' ) !== false ) {
            $result['report_type'] = 'new_acquisition';
        }

        // Check for overall rating indicators
        if ( stripos( $text, 'Exceeds' ) !== false ) {
            $result['overall_rating'] = 'exceeds';
        } elseif ( stripos( $text, 'Needs Improvement' ) !== false ) {
            $result['overall_rating'] = 'needs_improvement';
        } elseif ( stripos( $text, 'Meets' ) !== false ) {
            $result['overall_rating'] = 'meets';
        }

        return $result;
    }
}
