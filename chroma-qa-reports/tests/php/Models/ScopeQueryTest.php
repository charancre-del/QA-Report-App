<?php
/**
 * Fail-closed model query tests.
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Tests\Models;

use Brain\Monkey\Functions;
use ChromaQA\Models\Report;
use ChromaQA\Models\School;
use ChromaQA\Tests\TestCase;
use Mockery;

class ScopeQueryTest extends TestCase {

    public function test_empty_school_id_scope_cannot_fall_back_to_all_reports() {
        global $wpdb;

        Functions\when( 'sanitize_sql_orderby' )->justReturn( 'inspection_date DESC' );
        $wpdb->shouldReceive( 'prepare' )
            ->once()
            ->with( Mockery::on( static function( $sql ) {
                return false !== strpos( $sql, '1 = 0' );
            } ), Mockery::type( 'array' ) )
            ->andReturn( 'scoped-report-query' );
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( 'scoped-report-query', ARRAY_A )
            ->andReturn( [] );

        $this->assertSame( [], Report::all( [ 'school_ids' => [] ] ) );
    }

    public function test_empty_school_id_scope_cannot_fall_back_to_all_schools() {
        global $wpdb;

        Functions\when( 'sanitize_sql_orderby' )->justReturn( 'name ASC' );
        $wpdb->shouldReceive( 'prepare' )
            ->once()
            ->with( Mockery::on( static function( $sql ) {
                return false !== strpos( $sql, '1 = 0' );
            } ), Mockery::type( 'array' ) )
            ->andReturn( 'scoped-school-query' );
        $wpdb->shouldReceive( 'get_results' )
            ->once()
            ->with( 'scoped-school-query', ARRAY_A )
            ->andReturn( [] );

        $this->assertSame( [], School::all( [ 'ids' => [] ] ) );
    }

    public function test_empty_report_count_scope_returns_zero_instead_of_global_count() {
        global $wpdb;

        $wpdb->shouldReceive( 'get_var' )
            ->once()
            ->with( Mockery::on( static function( $sql ) {
                return false !== strpos( $sql, '1 = 0' );
            } ) )
            ->andReturn( 0 );

        $this->assertSame( 0, Report::count( [ 'school_ids' => [] ] ) );
    }

    public function test_empty_school_count_scope_returns_zero_instead_of_global_count() {
        global $wpdb;

        $wpdb->shouldReceive( 'get_var' )
            ->once()
            ->with( Mockery::on( static function( $sql ) {
                return false !== strpos( $sql, '1 = 0' );
            } ) )
            ->andReturn( 0 );

        $this->assertSame( 0, School::count( [ 'ids' => [] ] ) );
    }
}
