<?php
/**
 * Shared unit-test support.
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();

        global $wpdb;
        $wpdb = Mockery::mock( 'wpdb' );
        $wpdb->prefix = 'wp_';
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Configure the WordPress user/capability/meta functions for one user.
     *
     * @param array $roles Role slugs.
     * @param array $capabilities Capability slugs.
     * @param array $meta User meta values.
     * @param int   $user_id User ID.
     * @return object
     */
    protected function setCurrentUser( array $roles, array $capabilities, array $meta = [], $user_id = 42 ) {
        $user = (object) [
            'ID'           => $user_id,
            'roles'        => $roles,
            'capabilities' => array_fill_keys( $capabilities, true ),
        ];

        Functions\when( 'get_current_user_id' )->justReturn( $user_id );
        Functions\when( 'get_userdata' )->alias( static function( $requested_id ) use ( $user ) {
            return (int) $requested_id === (int) $user->ID ? $user : false;
        } );
        Functions\when( 'user_can' )->alias( static function( $subject, $capability ) {
            return ! empty( $subject->capabilities[ $capability ] );
        } );
        Functions\when( 'current_user_can' )->alias( static function( $capability ) use ( $user ) {
            return ! empty( $user->capabilities[ $capability ] );
        } );
        Functions\when( 'get_user_meta' )->alias( static function( $requested_id, $key ) use ( $user, $meta ) {
            if ( (int) $requested_id !== (int) $user->ID ) {
                return '';
            }
            return $meta[ $key ] ?? '';
        } );

        return $user;
    }
}
