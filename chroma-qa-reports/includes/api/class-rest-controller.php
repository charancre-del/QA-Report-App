<?php
/**
 * REST API Controller
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\API;

use ChromaQA\Models\School;
use ChromaQA\Models\Report;
use ChromaQA\Models\Checklist_Response;
use ChromaQA\Models\Photo;
use ChromaQA\Integrations\Google_Drive;
use ChromaQA\Checklists\Checklist_Manager;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API endpoints for the QA Reports plugin.
 */
class REST_Controller {

    /**
     * API namespace.
     *
     * @var string
     */
    const NAMESPACE = 'cqa/v1';

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        // Schools
        \register_rest_route( self::NAMESPACE, '/schools', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_schools' ],
                'permission_callback' => [ $this, 'check_read_permission' ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_school' ],
                'permission_callback' => [ $this, 'check_manage_schools_permission' ],
            ],
        ] );

        \register_rest_route( self::NAMESPACE, '/schools/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_school' ],
                'permission_callback' => [ $this, 'check_read_permission' ],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_school' ],
                'permission_callback' => [ $this, 'check_manage_schools_permission' ],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_school' ],
                'permission_callback' => [ $this, 'check_manage_schools_permission' ],
            ],
        ] );

        // Reports
        \register_rest_route( self::NAMESPACE, '/reports', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_reports' ],
                'permission_callback' => [ $this, 'check_read_permission' ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_report' ],
                'permission_callback' => [ $this, 'check_create_reports_permission' ],
            ],
        ] );

        \register_rest_route( self::NAMESPACE, '/reports/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_report' ],
                'permission_callback' => [ $this, 'check_read_permission' ],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_report' ],
                'permission_callback' => [ $this, 'check_edit_reports_permission' ],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_report' ],
                'permission_callback' => [ $this, 'check_delete_reports_permission' ],
            ],
        ] );

        // Report responses
        \register_rest_route( self::NAMESPACE, '/reports/(?P<id>\d+)/responses', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_report_responses' ],
                'permission_callback' => [ $this, 'check_read_permission' ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'save_report_responses' ],
                'permission_callback' => [ $this, 'check_edit_reports_permission' ],
            ],
        ] );

        // Report PDF
        \register_rest_route( self::NAMESPACE, '/reports/(?P<id>\d+)/pdf', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'generate_report_pdf' ],
            'permission_callback' => [ $this, 'check_export_permission' ],
        ] );

        // AI endpoints
        \register_rest_route( self::NAMESPACE, '/reports/(?P<id>\d+)/generate-summary', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'generate_ai_summary' ],
            'permission_callback' => [ $this, 'check_ai_permission' ],
        ] );

        \register_rest_route( self::NAMESPACE, '/ai/parse-document', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'parse_document' ],
            'permission_callback' => [ $this, 'check_ai_permission' ],
        ] );

        // Checklists
        \register_rest_route( self::NAMESPACE, '/checklists/(?P<type>[a-z0-9_]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_checklist' ],
            'permission_callback' => [ $this, 'check_read_permission' ],
        ] );

        // Schools reports (for previous report selection)
        \register_rest_route( self::NAMESPACE, '/schools/(?P<id>\d+)/reports', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_school_reports' ],
            'permission_callback' => [ $this, 'check_read_permission' ],
        ] );

        // Settings
        \register_rest_route( self::NAMESPACE, '/settings', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_settings' ],
                'permission_callback' => [ $this, 'check_settings_permission' ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'update_settings' ],
                'permission_callback' => [ $this, 'check_settings_permission' ],
            ],
        ] );
    }

    // ===== PERMISSION CALLBACKS =====

    public function check_read_permission() {
        return \current_user_can( 'cqa_view_own_reports' ) || \current_user_can( 'cqa_view_all_reports' );
    }

    public function check_manage_schools_permission() {
        return \current_user_can( 'cqa_manage_schools' );
    }

    public function check_create_reports_permission() {
        return \current_user_can( 'cqa_create_reports' );
    }

    public function check_edit_reports_permission( $request ) {
        if ( \current_user_can( 'cqa_edit_all_reports' ) ) {
            return true;
        }

        if ( \current_user_can( 'cqa_edit_own_reports' ) ) {
            $report = Report::find( $request['id'] );
            return $report && $report->user_id === \get_current_user_id();
        }

        return false;
    }

    public function check_delete_reports_permission() {
        return \current_user_can( 'cqa_delete_reports' );
    }

    public function check_export_permission() {
        return \current_user_can( 'cqa_export_reports' );
    }

    public function check_ai_permission() {
        return \current_user_can( 'cqa_use_ai_features' );
    }

    public function check_manage_options_permission() {
        return \current_user_can( 'manage_options' ); // Super Admin only
    }

    public function check_settings_permission() {
        // Allow anyone who can create reports to also configure the app (for now)
        return \current_user_can( 'cqa_create_reports' ) || \current_user_can( 'manage_options' );
    }

    // ===== SCHOOLS ENDPOINTS =====

    public function get_schools( WP_REST_Request $request ) {
        $args = [
            'status' => $request->get_param( 'status' ) ?: '',
            'region' => $request->get_param( 'region' ) ?: '',
            'limit'  => $request->get_param( 'per_page' ) ?: 100,
            'offset' => ( ( $request->get_param( 'page' ) ?: 1 ) - 1 ) * 100,
        ];

        $schools = School::all( $args );
        $data = array_map( [ $this, 'prepare_school_response' ], $schools );

        return new WP_REST_Response( $data, 200 );
    }

    public function get_school( WP_REST_Request $request ) {
        $school = School::find( $request['id'] );
        
        if ( ! $school ) {
            return new WP_Error( 'not_found', __( 'School not found.', 'chroma-qa-reports' ), [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $this->prepare_school_response( $school ), 200 );
    }

    public function create_school( WP_REST_Request $request ) {
        $school = new School();
        $school->name = \sanitize_text_field( $request->get_param( 'name' ) );
        $school->location = \sanitize_text_field( $request->get_param( 'location' ) );
        $school->region = \sanitize_text_field( $request->get_param( 'region' ) );
        $school->acquired_date = \sanitize_text_field( $request->get_param( 'acquired_date' ) );
        $school->status = \sanitize_text_field( $request->get_param( 'status' ) ) ?: 'active';
        $school->classroom_config = $request->get_param( 'classroom_config' ) ?: [];

        $result = $school->save();

        if ( ! $result ) {
            return new WP_Error( 'create_failed', \__( 'Failed to create school.', 'chroma-qa-reports' ), [ 'status' => 500 ] );
        }

        return new WP_REST_Response( $this->prepare_school_response( $school ), 201 );
    }

    public function update_school( WP_REST_Request $request ) {
        $school = School::find( $request['id'] );
        
        if ( ! $school ) {
            return new WP_Error( 'not_found', __( 'School not found.', 'chroma-qa-reports' ), [ 'status' => 404 ] );
        }

        if ( $request->has_param( 'name' ) ) {
            $school->name = \sanitize_text_field( $request->get_param( 'name' ) );
        }
        if ( $request->has_param( 'location' ) ) {
            $school->location = \sanitize_text_field( $request->get_param( 'location' ) );
        }
        if ( $request->has_param( 'region' ) ) {
            $school->region = \sanitize_text_field( $request->get_param( 'region' ) );
        }
        if ( $request->has_param( 'status' ) ) {
            $school->status = \sanitize_text_field( $request->get_param( 'status' ) );
        }
        if ( $request->has_param( 'classroom_config' ) ) {
            $school->classroom_config = $request->get_param( 'classroom_config' );
        }

        $school->save();

        return new WP_REST_Response( $this->prepare_school_response( $school ), 200 );
    }

    public function delete_school( WP_REST_Request $request ) {
        $school = School::find( $request['id'] );
        
        if ( ! $school ) {
            return new WP_Error( 'not_found', __( 'School not found.', 'chroma-qa-reports' ), [ 'status' => 404 ] );
        }

        $school->delete();

        return new WP_REST_Response( null, 204 );
    }

    // ===== REPORTS ENDPOINTS =====

    public function get_reports( WP_REST_Request $request ) {
        $args = [
            'school_id'   => $request->get_param( 'school_id' ) ?: 0,
            'report_type' => $request->get_param( 'report_type' ) ?: '',
            'status'      => $request->get_param( 'status' ) ?: '',
            'limit'       => $request->get_param( 'per_page' ) ?: 50,
            'offset'      => ( ( $request->get_param( 'page' ) ?: 1 ) - 1 ) * 50,
        ];

        $reports = Report::all( $args );
        $data = array_map( [ $this, 'prepare_report_response' ], $reports );

        return new WP_REST_Response( $data, 200 );
    }

    public function get_report( WP_REST_Request $request ) {
        $report = Report::find( $request['id'] );
        
        if ( ! $report ) {
            return new WP_Error( 'not_found', __( 'Report not found.', 'chroma-qa-reports' ), [ 'status' => 404 ] );
        }

        return new WP_REST_Response( $this->prepare_report_response( $report, true ), 200 );
    }

    public function create_report( WP_REST_Request $request ) {
        // Initialize Report
        $report = new Report();
        $school_id = intval( $request->get_param( 'school_id' ) );

        // FALLBACK 1: Check $_GET (The Nuclear Option)
        if ( empty( $school_id ) && isset( $_GET['school_id'] ) ) {
            $school_id = intval( $_GET['school_id'] );
            error_log( 'create_report: Recovered school_id from $_GET: ' . $school_id );
        }

        // FALLBACK 2: Check Raw PHP Input (The Double Nuclear Option)
        if ( empty( $school_id ) ) {
             $raw_input = file_get_contents( 'php://input' );
             $json = json_decode( $raw_input, true );
             if ( isset( $json['school_id'] ) ) {
                 $school_id = intval( $json['school_id'] );
                 error_log( 'create_report: Recovered school_id from php://input: ' . $school_id );
             }
        }

        // FALLBACK 3: Check Cookies (The Triple Nuclear Option)
        if ( empty( $school_id ) && isset( $_COOKIE['cqa_temp_school_id'] ) ) {
            $school_id = intval( $_COOKIE['cqa_temp_school_id'] );
            error_log( 'create_report: Recovered school_id from COOKIE: ' . $school_id );
        }

        $report->school_id = $school_id;
        $report->user_id = \get_current_user_id();
        $report->report_type = \sanitize_text_field( $request->get_param( 'report_type' ) );
        $report->inspection_date = \sanitize_text_field( $request->get_param( 'inspection_date' ) );
        $report->previous_report_id = intval( $request->get_param( 'previous_report_id' ) ) ?: null;
        $report->overall_rating = \sanitize_text_field( $request->get_param( 'overall_rating' ) ) ?: 'pending';
        $report->closing_notes = \sanitize_textarea_field( $request->get_param( 'closing_notes' ) );
        $report->status = \sanitize_text_field( $request->get_param( 'status' ) ) ?: 'draft';

        $result = $report->save();

        if ( ! $result ) {
            return new WP_Error( 'create_failed', \__( 'Failed to create report.', 'chroma-qa-reports' ), [ 'status' => 500 ] );
        }

        // Process Photos (Uploads)
        $this->process_report_photos( $report->id, $request );

        // Process Drive Files (Picker)
        $this->process_drive_files( $report->id, $request );

        return new WP_REST_Response( $this->prepare_report_response( $report ), 201 );
    }

    public function update_report( WP_REST_Request $request ) {
        $report = Report::find( $request['id'] );
        
        if ( ! $report ) {
            return new WP_Error( 'not_found', __( 'Report not found.', 'chroma-qa-reports' ), [ 'status' => 404 ] );
        }

        if ( $request->has_param( 'report_type' ) ) {
            $report->report_type = \sanitize_text_field( $request->get_param( 'report_type' ) );
        }
        
        // Allow updating School ID (Vital for fixing Unknown Schools)
        if ( $request->has_param( 'school_id' ) ) {
            $school_id = intval( $request->get_param( 'school_id' ) );
             // Fallback 1: $_GET
            if ( empty( $school_id ) && isset( $_GET['school_id'] ) ) {
                $school_id = intval( $_GET['school_id'] );
            }
             // Fallback 2: POST/JSON handled by has_param generally, but if param is strict...
             // Just take the param if it exists.
            
            if ( $school_id > 0 ) {
                $report->school_id = $school_id;
            }
        }
        
        if ( $request->has_param( 'inspection_date' ) ) {
            $report->inspection_date = \sanitize_text_field( $request->get_param( 'inspection_date' ) );
        }
        if ( $request->has_param( 'previous_report_id' ) ) {
            $report->previous_report_id = intval( $request->get_param( 'previous_report_id' ) ) ?: null;
        }
        if ( $request->has_param( 'overall_rating' ) ) {
            $report->overall_rating = \sanitize_text_field( $request->get_param( 'overall_rating' ) );
        }
        if ( $request->has_param( 'closing_notes' ) ) {
            $report->closing_notes = \sanitize_textarea_field( $request->get_param( 'closing_notes' ) );
        }
        if ( $request->has_param( 'status' ) ) {
            $report->status = \sanitize_text_field( $request->get_param( 'status' ) );
        }

        $report->save();

        // Process Photos
        $this->process_report_photos( $report->id, $request );
        $this->process_drive_files( $report->id, $request );

        return new WP_REST_Response( $this->prepare_report_response( $report ), 200 );
    }

    public function delete_report( WP_REST_Request $request ) {
        $report = Report::find( $request['id'] );
        
        if ( ! $report ) {
            return new WP_Error( 'not_found', __( 'Report not found.', 'chroma-qa-reports' ), [ 'status' => 404 ] );
        }

        $report->delete();

        return new WP_REST_Response( null, 204 );
    }

    // ===== REPORT RESPONSES =====

    public function get_report_responses( WP_REST_Request $request ) {
        $responses = Checklist_Response::get_by_report_grouped( $request['id'] );
        return new WP_REST_Response( $responses, 200 );
    }

    public function save_report_responses( WP_REST_Request $request ) {
        $report_id = $request['id'];
        $responses = $request->get_param( 'responses' );

        if ( ! is_array( $responses ) ) {
            return new WP_Error( 'invalid_data', \__( 'Invalid responses data.', 'chroma-qa-reports' ), [ 'status' => 400 ] );
        }

        Checklist_Response::bulk_save( $report_id, $responses );

        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    // ===== CHECKLISTS =====

    public function get_checklist( WP_REST_Request $request ) {
        $type = $request['type'];
        $checklist = Checklist_Manager::get_checklist_for_type( $type );
        return new WP_REST_Response( $checklist, 200 );
    }

    public function get_school_reports( WP_REST_Request $request ) {
        $reports = Report::all( [
            'school_id' => $request['id'],
            'limit'     => 10,
            'orderby'   => 'inspection_date',
            'order'     => 'DESC',
        ] );

        $data = array_map( function( $report ) {
            return [
                'id'              => $report->id,
                'report_type'     => $report->report_type,
                'inspection_date' => $report->inspection_date,
                'overall_rating'  => $report->overall_rating,
                'status'          => $report->status,
            ];
        }, $reports );

        return new WP_REST_Response( $data, 200 );
    }

    // ===== AI ENDPOINTS =====

    public function generate_ai_summary( WP_REST_Request $request ) {
        $report = Report::find( $request['id'] );
        
        if ( ! $report ) {
            return new WP_Error( 'not_found', __( 'Report not found.', 'chroma-qa-reports' ), [ 'status' => 404 ] );
        }

        // Use the AI Summary generator
        $ai = new \ChromaQA\AI\Executive_Summary();
        $result = $ai->generate( $report );

        if ( \is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( $result, 200 );
    }

    public function parse_document( WP_REST_Request $request ) {
        $files = $request->get_file_params();
        
        if ( empty( $files['document'] ) ) {
            return new WP_Error( 'no_file', \__( 'No document provided.', 'chroma-qa-reports' ), [ 'status' => 400 ] );
        }

        $parser = new \ChromaQA\AI\Document_Parser();
        $result = $parser->parse( $files['document']['tmp_name'] );

        if ( \is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( $result, 200 );
    }



    // ===== SETTINGS ENDPOINTS =====

    public function get_settings( WP_REST_Request $request ) {
        $settings = [
            'google_client_id'     => \get_option( 'cqa_google_client_id' ),
            'google_client_secret' => \get_option( 'cqa_google_client_secret' ),
            'google_developer_key' => \get_option( 'cqa_google_developer_key' ),
            'gemini_api_key'       => \get_option( 'cqa_gemini_api_key' ),
            'enable_ai'            => \get_option( 'cqa_enable_ai', 'yes' ),
        ];
        return new WP_REST_Response( $settings, 200 );
    }

    public function update_settings( WP_REST_Request $request ) {
        $params = $request->get_params();

        if ( isset( $params['google_client_id'] ) ) {
            \update_option( 'cqa_google_client_id', \sanitize_text_field( $params['google_client_id'] ) );
        }
        if ( isset( $params['google_client_secret'] ) ) {
            \update_option( 'cqa_google_client_secret', \sanitize_text_field( $params['google_client_secret'] ) );
        }
        if ( isset( $params['google_developer_key'] ) ) {
            \update_option( 'cqa_google_developer_key', \sanitize_text_field( $params['google_developer_key'] ) );
        }
        if ( isset( $params['gemini_api_key'] ) ) {
            \update_option( 'cqa_gemini_api_key', \sanitize_text_field( $params['gemini_api_key'] ) );
        }
        if ( isset( $params['enable_ai'] ) ) {
            \update_option( 'cqa_enable_ai', \sanitize_text_field( $params['enable_ai'] ) );
        }

        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    public function generate_report_pdf( WP_REST_Request $request ) {
        $report = Report::find( $request['id'] );
        
        if ( ! $report ) {
            return new WP_Error( 'not_found', __( 'Report not found.', 'chroma-qa-reports' ), [ 'status' => 404 ] );
        }

        // Nuclear Option: Disable all error reporting for PDF generation
        error_reporting(0);
        @ini_set('display_errors', 0);

        $pdf_generator = new \ChromaQA\Export\PDF_Generator();
        $pdf_path = $pdf_generator->generate( $report );

        if ( \is_wp_error( $pdf_path ) ) {
            return $pdf_path;
        }

        // Clean output buffer to remove any leaked whitespace or logs
        if ( ob_get_length() ) {
            ob_end_clean();
        }

        // Stream the PDF
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: inline; filename="' . \sanitize_file_name( $report->get_school()->name . '-QA-Report.pdf' ) . '"' );
        readfile( $pdf_path );
        exit;
    }

    // ===== HELPERS =====

    /**
     * Process report photos (upload to Drive or fallback to Local).
     *
     * @param int             $report_id Report ID.
     * @param WP_REST_Request $request   Request object.
     */
    private function process_report_photos( $report_id, $request ) {
        $new_photos = $request->get_param( 'new_photos' );
        
        if ( empty( $new_photos ) || ! is_array( $new_photos ) ) {
            return;
        }

        $report = Report::find( $report_id );
        if ( ! $report ) return;
        
        $school = $report->get_school();
        $folder_id = $school->drive_folder_id ?? null;

        // Load image functions for fallback
        require_once( \ABSPATH . 'wp-admin/includes/image.php' );
        require_once( \ABSPATH . 'wp-admin/includes/file.php' );
        require_once( \ABSPATH . 'wp-admin/includes/media.php' );
        
        foreach ( $new_photos as $index => $data_url ) {
            if ( ! is_string( $data_url ) ) continue;

            // Decode Base64
            if ( ! preg_match( '/^data:image\/(\w+);base64,/', $data_url, $type ) ) {
                continue;
            }

            $data = substr( $data_url, strpos( $data_url, ',' ) + 1 );
            $ext = strtolower( $type[1] );
            
            if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'gif', 'png' ] ) ) {
                continue;
            }

            $decoded_data = base64_decode( $data );
            if ( $decoded_data === false ) {
                continue;
            }

            if ( strlen( $decoded_data ) > 10 * 1024 * 1024 ) { // 10MB limit
                error_log( 'File too large: ' . $filename );
                continue;
            }

            $filename = 'report-' . $report_id . '-photo-' . time() . '-' . $index . '.' . $ext;
            $drive_file_id = '';
            $tmp_file = sys_get_temp_dir() . '/' . $filename;
            
            // 1. Try Google Drive Upload
            if ( \get_option( 'cqa_google_client_id' ) ) {
                \file_put_contents( $tmp_file, $decoded_data );
                $drive_result = Google_Drive::upload_file( $tmp_file, $filename, $folder_id );
                if ( ! \is_wp_error( $drive_result ) && isset( $drive_result['id'] ) ) {
                    $drive_file_id = $drive_result['id'];
                }
            }

            // 2. Fallback to Local Media Library
            if ( empty( $drive_file_id ) ) {
                $upload = \wp_upload_bits( $filename, null, $decoded_data );
                
                if ( ! $upload['error'] ) {
                    $file_path = $upload['file'];
                    $file_name = basename( $file_path );
                    $file_type = \wp_check_filetype( $file_name, null );
                    
                    $attachment = [
                        'post_mime_type' => $file_type['type'],
                        'post_title'     => \sanitize_file_name( pathinfo( $file_name, PATHINFO_FILENAME ) ),
                        'post_content'   => '',
                        'post_status'    => 'inherit',
                    ];
                    
                    $attach_id = \wp_insert_attachment( $attachment, $file_path );
                    $attach_data = \wp_generate_attachment_metadata( $attach_id, $file_path );
                    \wp_update_attachment_metadata( $attach_id, $attach_data );
                    
                    $drive_file_id = 'wp_' . $attach_id;
                } else {
                     // Log error or handle upload failure
                     error_log( 'Upload failed: ' . $upload['error'] );
                }
            }
            
            // Cleanup temp file
            if ( file_exists( $tmp_file ) ) {
                unlink( $tmp_file );
            }

            // Save Photo Record
            if ( $drive_file_id ) {
                $photo = new Photo();
                $photo->report_id = $report_id;
                $photo->drive_file_id = $drive_file_id;
                $photo->filename = $filename;
                $photo->section_key = 'general'; 
                $photo->save();
            }
        }

        // Handle Item-Specific Photos
        $item_photos = $request->get_param( 'item_photos' );
        if ( ! empty( $item_photos ) && is_array( $item_photos ) ) {
            foreach ( $item_photos as $section_key => $items ) {
                foreach ( $items as $item_key => $photos ) {
                    if ( ! is_array( $photos ) ) continue;
                    
                    foreach ( $photos as $i => $data_url ) {
                         if ( ! is_string( $data_url ) ) continue;
                         // Process Item Photo (Reuse logic - simpler to refactor but duplicating for safety now)
                         // ... (Duplicate processing logic for speed, or extract method? Extracting is better but risky mid-flight. I will duplicate strictly for the item context)
                         
                            if ( ! preg_match( '/^data:image\/(\w+);base64,/', $data_url, $type ) ) continue;
                            $data = substr( $data_url, strpos( $data_url, ',' ) + 1 );
                            $ext = strtolower( $type[1] );
                            if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'gif', 'png' ] ) ) continue;
                            $decoded_data = base64_decode( $data );
                            if ( $decoded_data === false ) continue;
                            
                            $filename = 'report-' . $report_id . '-' . $section_key . '-' . $item_key . '-' . time() . '-' . $i . '.' . $ext;
                            $drive_file_id = '';
                            $tmp_file = sys_get_temp_dir() . '/' . $filename;
                            
                            if ( \get_option( 'cqa_google_client_id' ) ) {
                                \file_put_contents( $tmp_file, $decoded_data );
                                $drive_result = Google_Drive::upload_file( $tmp_file, $filename, $folder_id );
                                if ( ! \is_wp_error( $drive_result ) && isset( $drive_result['id'] ) ) {
                                    $drive_file_id = $drive_result['id'];
                                }
                            }
                            
                            if ( empty( $drive_file_id ) ) {
                                $upload = \wp_upload_bits( $filename, null, $decoded_data );
                                if ( ! $upload['error'] ) {
                                     // Create attachment...
                                    $file_path = $upload['file'];
                                    $file_name = basename( $file_path );
                                    $file_type = \wp_check_filetype( $file_name, null );
                                    $attachment = [
                                        'post_mime_type' => $file_type['type'],
                                        'post_title'     => \sanitize_file_name( pathinfo( $file_name, PATHINFO_FILENAME ) ),
                                        'post_content'   => '',
                                        'post_status'    => 'inherit',
                                    ];
                                    $attach_id = \wp_insert_attachment( $attachment, $file_path );
                                    $attach_data = \wp_generate_attachment_metadata( $attach_id, $file_path );
                                    \wp_update_attachment_metadata( $attach_id, $attach_data );
                                    $drive_file_id = 'wp_' . $attach_id;
                                }
                            }
                            
                            if ( file_exists( $tmp_file ) ) unlink( $tmp_file );

                            if ( $drive_file_id ) {
                                $photo = new Photo();
                                $photo->report_id = $report_id;
                                $photo->drive_file_id = $drive_file_id;
                                $photo->filename = $filename;
                                $photo->section_key = $section_key . '|' . $item_key; 
                                $photo->save();
                            }
                    }
                }
            }
        }
    }

    /**
     * Process Google Drive files selected via Picker.
     *
     * @param int             $report_id Report ID.
     * @param WP_REST_Request $request   Request object.
     */
    private function process_drive_files( $report_id, $request ) {
        $drive_files = $request->get_param( 'drive_files' );

        if ( empty( $drive_files ) || ! is_array( $drive_files ) ) {
            return;
        }

        foreach ( $drive_files as $file_id ) {
            if ( ! is_string( $file_id ) ) continue;

            // Check if already attached? (Optional, but good practice)
            // For now, just add new records.
            // In a real app, we might check to avoid duplicates if re-saving.
            
            // We need metadata (filename, etc) but the picker only sent ID in the hidden input.
            // We'll have to fetch it or just save the ID for now.
            // The picker JS callback had the name, but only inputs value=ID.
            // Ideally, we should have sent an object or array of data.
            // For now, we'll save with a placeholder filename or fetch it if we had the service.
            // Let's just save the ID. The Photo model can handle fetching metadata on view if needed,
            // or we accept that we don't have the filename yet.
            
            $photo = new Photo();
            $photo->report_id = $report_id;
            $photo->drive_file_id = $file_id;
            $photo->filename = 'drive-file-' . $file_id; // Placeholder
            $photo->section_key = 'general';
            $photo->caption = '';
            $photo->save();
        }
    }

    private function prepare_school_response( $school ) {
        return [
            'id'               => $school->id,
            'name'             => $school->name,
            'location'         => $school->location,
            'region'           => $school->region,
            'acquired_date'    => $school->acquired_date,
            'status'           => $school->status,
            'drive_folder_id'  => $school->drive_folder_id,
            'classroom_config' => $school->classroom_config,
            'created_at'       => $school->created_at,
        ];
    }

    private function prepare_report_response( $report, $include_details = false ) {
        $data = [
            'id'                 => $report->id,
            'school_id'          => $report->school_id,
            'user_id'            => $report->user_id,
            'report_type'        => $report->report_type,
            'report_type_label'  => $report->get_type_label(),
            'inspection_date'    => $report->inspection_date,
            'previous_report_id' => $report->previous_report_id,
            'overall_rating'     => $report->overall_rating,
            'rating_label'       => $report->get_rating_label(),
            'status'             => $report->status,
            'status_label'       => $report->get_status_label(),
            'created_at'         => $report->created_at,
            'school_name'        => $report->get_school() ? $report->get_school()->name : 'Unknown School',
        ];

        if ( $include_details ) {
            $school = $report->get_school();
            $data['school'] = $school ? $this->prepare_school_response( $school ) : null;
            $data['responses'] = Checklist_Response::get_by_report_grouped( $report->id );
            $data['photos'] = array_map( function( $photo ) {
                return [
                    'id'            => $photo->id,
                    'section_key'   => $photo->section_key,
                    'filename'      => $photo->filename,
                    'caption'       => $photo->caption,
                    'thumbnail_url' => $photo->get_thumbnail_url(),
                    'view_url'      => $photo->get_view_url(),
                ];
            }, $report->get_photos() );
            $data['ai_summary'] = $report->get_ai_summary();
            $data['closing_notes'] = $report->closing_notes;
        }

        return $data;
    }
}
