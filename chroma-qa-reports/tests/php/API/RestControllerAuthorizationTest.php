<?php
/**
 * REST authorization and secret-response tests.
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Tests\API;

use Brain\Monkey\Functions;
use ChromaQA\API\REST_Controller;
use ChromaQA\Tests\TestCase;
use Mockery;
use WP_Error;
use WP_REST_Request;

class RestControllerAuthorizationTest extends TestCase {

    public function test_create_only_user_is_denied_settings_access() {
        $this->setCurrentUser( [ 'subscriber' ], [ 'cqa_create_reports' ] );

        $result = ( new REST_Controller() )->check_settings_permission();

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 403, $result->get_error_data()['status'] );
        $this->assertInstanceOf(
            WP_Error::class,
            ( new REST_Controller() )->get_settings( new WP_REST_Request() )
        );
    }

    public function test_settings_response_never_returns_stored_secret_values() {
        $this->setCurrentUser(
            [ 'cqa_super_admin' ],
            [ 'cqa_manage_settings', 'cqa_view_all_reports' ]
        );

        Functions\when( 'get_option' )->alias( static function( $key, $default = '' ) {
            $values = [
                'cqa_google_client_id'     => 'public-client-id',
                'cqa_google_client_secret' => 'oauth-secret',
                'cqa_google_developer_key' => 'browser-key',
                'cqa_gemini_api_key'       => 'gemini-secret',
                'cqa_enable_ai'            => 'yes',
            ];
            return $values[ $key ] ?? $default;
        } );

        $controller = new REST_Controller();
        $this->assertTrue( $controller->check_settings_permission() );

        $data = $controller->get_settings( new WP_REST_Request() )->get_data();

        $this->assertSame( 'public-client-id', $data['google_client_id'] );
        $this->assertTrue( $data['google_client_secret_configured'] );
        $this->assertTrue( $data['google_developer_key_configured'] );
        $this->assertTrue( $data['gemini_api_key_configured'] );
        $this->assertArrayNotHasKey( 'google_client_secret', $data );
        $this->assertArrayNotHasKey( 'google_developer_key', $data );
        $this->assertArrayNotHasKey( 'gemini_api_key', $data );
        $this->assertNotContains( 'oauth-secret', array_values( $data ) );
        $this->assertNotContains( 'gemini-secret', array_values( $data ) );
    }

    public function test_blank_sensitive_setting_values_do_not_erase_existing_credentials() {
        $this->setCurrentUser(
            [ 'cqa_super_admin' ],
            [ 'cqa_manage_settings', 'cqa_view_all_reports' ]
        );

        Functions\expect( 'update_option' )->never();

        $request = new WP_REST_Request( [
            'google_client_secret' => '   ',
            'google_developer_key' => '',
            'gemini_api_key'       => '',
        ] );

        $response = ( new REST_Controller() )->update_settings( $request );

        $this->assertSame( 200, $response->get_status() );
    }

    public function test_sensitive_routes_use_object_aware_permission_callbacks() {
        $this->setCurrentUser( [ 'subscriber' ], [ 'cqa_create_reports' ] );
        $routes = [];

        Functions\when( 'register_rest_route' )->alias( static function( $namespace, $route, $definition ) use ( &$routes ) {
            $routes[ $route ] = $definition;
        } );

        ( new REST_Controller() )->register_routes();

        $this->assertSame( 'check_view_report_permission', $routes['/reports/(?P<id>\d+)'][0]['permission_callback'][1] );
        $this->assertSame( 'check_export_permission', $routes['/reports/(?P<id>\d+)/pdf']['permission_callback'][1] );
        $this->assertSame( 'check_report_ai_permission', $routes['/reports/(?P<id>\d+)/generate-summary']['permission_callback'][1] );
        $this->assertSame( 'check_edit_photo_permission', $routes['/photos/(?P<id>\d+)'][0]['permission_callback'][1] );
        $this->assertSame( 'check_settings_permission', $routes['/settings'][0]['permission_callback'][1] );
    }

    public function test_create_only_user_cannot_create_an_already_approved_report() {
        global $wpdb;

        $this->setCurrentUser(
            [ 'subscriber' ],
            [ 'cqa_create_reports' ],
            [ 'cqa_school_id' => 7 ]
        );

        Functions\when( 'sanitize_sql_orderby' )->justReturn( 'inspection_date DESC' );
        $wpdb->shouldReceive( 'prepare' )->twice()->andReturnUsing( static function( $sql ) {
            return $sql;
        } );
        $wpdb->shouldReceive( 'get_row' )->once()->andReturn( [
            'id'               => 7,
            'name'             => 'Assigned School',
            'location'         => 'Atlanta',
            'region'           => 'North',
            'acquired_date'    => null,
            'status'           => 'active',
            'drive_folder_id'  => '',
            'classroom_config' => '{}',
            'created_at'       => '2026-01-01 00:00:00',
            'updated_at'       => '2026-01-01 00:00:00',
        ] );
        $wpdb->shouldReceive( 'get_results' )->once()->andReturn( [] );

        $request = new WP_REST_Request( [
            'school_id'      => 7,
            'report_type'    => 'tier1',
            'inspection_date'=> '2026-09-19',
            'status'         => 'approved',
        ] );

        $result = ( new REST_Controller() )->create_report( $request );

        $this->assertInstanceOf( WP_Error::class, $result );
        $this->assertSame( 'forbidden', $result->get_error_code() );
    }
}
