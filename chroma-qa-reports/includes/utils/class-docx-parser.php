<?php
/**
 * DOCX Parser Utility
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Utils;

use ZipArchive;

/**
 * Parses DOCX files to extract text.
 */
class Docx_Parser {

    /**
     * Extract text from a DOCX file.
     *
     * @param string $file_path Absolute path to the .docx file.
     * @return string|WP_Error Extracted text or error.
     */
    public static function extract_text( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return new \WP_Error( 'file_not_found', 'DOCX file not found.' );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $file_path ) !== true ) {
            return new \WP_Error( 'invalid_zip', 'Failed to open DOCX file (invalid format).' );
        }

        // Read document.xml which contains the main text content
        $content = $zip->getFromName( 'word/document.xml' );
        $zip->close();

        if ( ! $content ) {
            return new \WP_Error( 'invalid_content', 'Could not read document content from DOCX.' );
        }

        // Remove XML tags to get raw text
        // Note: Simple strip_tags removes formatting but keeps text. 
        // We might want to preserve some structure (paragraphs) but for now raw text is sufficient for AI.
        // Let's add newlines for paragraph breaks usually marked by <w:p>
        $content = str_replace( '</w:p>', "\n", $content );
        $text = strip_tags( $content );

        return trim( $text );
    }
}
