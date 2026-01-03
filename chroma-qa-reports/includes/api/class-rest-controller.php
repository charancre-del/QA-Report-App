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
        register_rest_route( self::NAMESPACE, '/schools', [
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

        register_rest_route( self::NAMESPACE, '/schools/(?P<id>\d+)', [
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
        register_rest_route( self::NAMESPACE, '/reports', [
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

        register_rest_route( self::NAMESPACE, '/reports/(?P<id>\d+)', [
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
        register_rest_route( self::NAMESPACE, '/reports/(?P<id>\d+)/responses', [
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
        register_rest_route( self::NAMESPACE, '/reports/(?P<id>\d+)/pdf', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'generate_report_pdf' ],
            'permission_callback' => [ $this, 'check_export_permission' ],
        ] );

        // AI endpoints
        register_rest_route( self::NAMESPACE, '/reports/(?P<id>\d+)/generate-summary', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'generate_ai_summary' ],
            'permission_callback' => [ $this, 'check_ai_permission' ],
        ] );

        register_rest_route( self::NAMESPACE, '/ai/parse-document', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'parse_document' ],
            'permission_callback' => [ $this, 'check_ai_permission' ],
        ] );

        // Checklists
        register_rest_route( self::NAMESPACE, '/checklists/(?P<type>[a-z0-9_]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_checklist' ],
            'permission_callback' => [ $this, 'check_read_permission' ],
        ] );

        // Schools reports (for previous report selection)
        register_rest_route( self::NAMESPACE, '/schools/(?P<id>\d+)/reports', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_school_reports' ],
            'permission_callback' => [ $this, 'check_read_permission' ],
        ] );
    }

    // ===== PERMISSION CALLBACKS =====

    public function check_read_permission() {
        return current_user_can( 'cqa_view_own_reports' ) || current_user_can( 'cqa_view_all_reports' );
    }

    public function check_manage_schools_permission() {
        return current_user_can( 'cqa_manage_schools' );
    }

    public function check_create_reports_permission() {
        return current_user_can( 'cqa_create_reports' );
    }

    public function check_edit_reports_permission( $request ) {
        if ( current_user_can( 'cqa_edit_all_reports' ) ) {
            return true;
        }

        if ( current_user_can( 'cqa_edit_own_reports' ) ) {
            $report = Report::find( $request['id'] );
            return $report && $report->user_id === get_current_user_id();
        }

        return false;
    }

    public function check_delete_reports_permission() {
        return current_user_can( 'cqa_delete_reports' );
    }

    public function check_export_permission() {
        return current_user_can( 'cqa_export_reports' );
    }

    public function check_ai_permission() {
        return current_user_can( 'cqa_use_ai_features' );
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
        $school->name = sanitize_text_field( $request->get_param( 'name' ) );
        $school->location = sanitize_text_field( $request->get_param( 'location' ) );
        $school->region = sanitize_text_field( $request->get_param( 'region' ) );
        $school->acquired_date = sanitize_text_field( $request->get_param( 'acquired_date' ) );
        $school->status = sanitize_text_field( $request->get_param( 'status' ) ) ?: 'active';
        $school->classroom_config = $request->get_param( 'classroom_config' ) ?: [];

        $result = $school->save();

        if ( ! $result ) {
            return new WP_Error( 'create_failed', __( 'Failed to create school.', 'chroma-qa-reports' ), [ 'status' => 500 ] );
        }

        return new WP_REST_Response( $this->prepare_school_response( $school ), 201 );
    }

    public function update_school( WP_REST_Request $request ) {
        $school = School::find( $request['id'] );
        
        if ( ! $school ) {
            return new WP_Error( 'not_found', __( 'School not found.', 'chroma-qa-reports' ), [ 'status' => 404 ] );
        }

        if ( $request->has_param( 'name' ) ) {
            $school->name = sanitize_text_field( $request->get_param( 'name' ) );
        }
        if ( $request->has_param( 'location' ) ) {
            $school->location = sanitize_text_field( $request->get_param( 'location' ) );
        }
        if ( $request->has_param( 'region' ) ) {
            $school->region = sanitize_text_field( $request->get_param( 'region' ) );
        }
        if ( $request->has_param( 'status' ) ) {
            $school->status = sanitize_text_field( $request->get_param( 'status' ) );
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
        $report = new Report();
        $report->school_id = intval( $request->get_param( 'school_id' ) );
        $report->user_id = get_current_user_id();
        $report->report_type = sanitize_text_field( $request->get_param( 'report_type' ) );
        $report->inspection_date = sanitize_text_field( $request->get_param( 'inspection_date' ) );
        $report->previous_report_id = intval( $request->get_param( 'previous_report_id' ) ) ?: null;
        $report->overall_rating = sanitize_text_field( $request->get_param( 'overall_rating' ) ) ?: 'pending';
        $report->closing_notes = sanitize_textarea_field( $request->get_param( 'closing_notes' ) );
        $report->status = sanitize_text_field( $request->get_param( 'status' ) ) ?: 'draft';

        $result = $report->save();

        if ( ! $result ) {
            return new WP_Error( 'create_failed', __( 'Failed to create report.', 'chroma-qa-reports' ), [ 'status' => 500 ] );
        }

        return new WP_REST_Response( $this->prepare_report_response( $report ), 201 );
    }

    public function update_report( WP_REST_Request $request ) {
        $report = Report::find( $request['id'] );
        
        if ( ! $report ) {
            return new WP_Error( 'not_found', __( 'Report not found.', 'chroma-qa-reports' ), [ 'status' => 404 ] );
        }

        if ( $request->has_param( 'report_type' ) ) {
            $report->report_type = sanitize_text_field( $request->get_param( 'report_type' ) );
        }
        if ( $request->has_param( 'inspection_date' ) ) {
            $report->inspection_date = sanitize_text_field( $request->get_param( 'inspection_date' ) );
        }
        if ( $request->has_param( 'previous_report_id' ) ) {
            $report->previous_report_id = intval( $request->get_param( 'previous_report_id' ) ) ?: null;
        }
        if ( $request->has_param( 'overall_rating' ) ) {
            $report->overall_rating = sanitize_text_field( $request->get_param( 'overall_rating' ) );
        }
        if ( $request->has_param( 'closing_notes' ) ) {
            $report->closing_notes = sanitize_textarea_field( $request->get_param( 'closing_notes' ) );
        }
        if ( $request->has_param( 'status' ) ) {
            $report->status = sanitize_text_field( $request->get_param( 'status' ) );
        }

        $report->save();

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
            return new WP_Error( 'invalid_data', __( 'Invalid responses data.', 'chroma-qa-reports' ), [ 'status' => 400 ] );
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

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( $result, 200 );
    }

    public function parse_document( WP_REST_Request $request ) {
        $files = $request->get_file_params();
        
        if ( empty( $files['document'] ) ) {
            return new WP_Error( 'no_file', __( 'No document provided.', 'chroma-qa-reports' ), [ 'status' => 400 ] );
        }

        $parser = new \ChromaQA\AI\Document_Parser();
        $result = $parser->parse( $files['document']['tmp_name'] );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( $result, 200 );
    }

    public function generate_report_pdf( WP_REST_Request $request ) {
        $report = Report::find( $request['id'] );
        
        if ( ! $report ) {
            return new WP_Error( 'not_found', __( 'Report not found.', 'chroma-qa-reports' ), [ 'status' => 404 ] );
        }

        $pdf_generator = new \ChromaQA\Export\PDF_Generator();
        $pdf_path = $pdf_generator->generate( $report );

        if ( is_wp_error( $pdf_path ) ) {
            return $pdf_path;
        }

        // Stream the PDF
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $report->get_school()->name . '-QA-Report.pdf' ) . '"' );
        readfile( $pdf_path );
        exit;
    }

    // ===== HELPERS =====

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
