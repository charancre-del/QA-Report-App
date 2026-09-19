<?php
/**
 * Authorization policy tests.
 *
 * @package ChromaQAReports
 */

namespace ChromaQA\Tests\Auth;

use ChromaQA\Auth\Access_Policy;
use ChromaQA\Models\Report;
use ChromaQA\Models\School;
use ChromaQA\Tests\TestCase;

class AccessPolicyTest extends TestCase {

    public function test_create_only_user_cannot_manage_settings_or_list_unscoped_schools() {
        $this->setCurrentUser( [ 'subscriber' ], [ 'cqa_create_reports' ] );

        $this->assertFalse( Access_Policy::can_manage_settings() );
        $this->assertFalse( Access_Policy::can_list_schools() );
        $this->assertSame( [], Access_Policy::scope_school_args()['ids'] );
    }

    public function test_regional_director_is_limited_to_assigned_region() {
        $this->setCurrentUser(
            [ 'cqa_regional_director' ],
            [ 'cqa_manage_schools', 'cqa_view_all_reports', 'cqa_create_reports' ],
            [ 'cqa_region' => 'North' ]
        );

        $north = new School();
        $north->id = 1;
        $north->region = 'North';

        $south = new School();
        $south->id = 2;
        $south->region = 'South';

        $this->assertTrue( Access_Policy::can_access_school( $north ) );
        $this->assertFalse( Access_Policy::can_access_school( $south ) );
        $this->assertTrue( Access_Policy::can_create_school( 'North' ) );
        $this->assertFalse( Access_Policy::can_create_school( 'South' ) );
        $this->assertSame( 'North', Access_Policy::scope_school_args()['region'] );
    }

    public function test_program_manager_queries_are_scoped_to_assigned_school() {
        $this->setCurrentUser(
            [ 'cqa_program_manager' ],
            [ 'cqa_view_own_reports', 'cqa_export_reports' ],
            [ 'cqa_school_id' => 7 ]
        );

        $school_args = Access_Policy::scope_school_args();
        $report_args = Access_Policy::scope_report_args();

        $this->assertSame( [ 7 ], $school_args['ids'] );
        $this->assertSame( 7, $report_args['school_id'] );
    }

    public function test_owner_can_edit_only_their_own_report() {
        $this->setCurrentUser( [ 'subscriber' ], [ 'cqa_view_own_reports', 'cqa_edit_own_reports' ], [], 42 );

        $own_report = new Report();
        $own_report->user_id = 42;

        $other_report = new Report();
        $other_report->user_id = 99;

        $this->assertTrue( Access_Policy::can_edit_report( $own_report ) );
        $this->assertFalse( Access_Policy::can_edit_report( $other_report ) );
    }

    public function test_create_report_requires_explicit_matching_school_scope() {
        $this->setCurrentUser(
            [ 'subscriber' ],
            [ 'cqa_create_reports' ],
            [ 'cqa_school_id' => 7 ]
        );

        $assigned = new School();
        $assigned->id = 7;

        $other = new School();
        $other->id = 8;

        $this->assertTrue( Access_Policy::can_create_report_for_school( $assigned ) );
        $this->assertFalse( Access_Policy::can_create_report_for_school( $other ) );
    }
}
