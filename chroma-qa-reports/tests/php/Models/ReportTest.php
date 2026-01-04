<?php
/**
 * Tests for Report Model
 */

namespace ChromaQA\Tests\Models;

use PHPUnit\Framework\TestCase;
use ChromaQA\Models\Report;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

class ReportTest extends TestCase {
    
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        
        global $wpdb;
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
    }
    
    protected function tearDown(): void {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }
    
    /**
     * Test report creation with required fields
     */
    public function test_create_report_with_required_fields() {
        $report = new Report();
        $report->school_id = 1;
        $report->user_id = 1;
        $report->report_type = 'tier1';
        $report->inspection_date = '2026-01-03';
        $report->status = 'draft';
        
        $this->assertEquals(1, $report->school_id);
        $this->assertEquals('tier1', $report->report_type);
        $this->assertEquals('draft', $report->status);
    }
    
    /**
     * Test report type labels
     */
    public function test_get_type_label() {
        $report = new Report();
        
        $report->report_type = 'tier1';
        $this->assertEquals('Tier 1 QA', $report->get_type_label());
        
        $report->report_type = 'tier1_tier2';
        $this->assertEquals('Tier 1 + Tier 2 (CQI)', $report->get_type_label());
        
        $report->report_type = 'new_acquisition';
        $this->assertEquals('New Acquisition', $report->get_type_label());
    }
    
    /**
     * Test rating labels
     */
    public function test_get_rating_label() {
        $report = new Report();
        
        $report->overall_rating = 'exceeds';
        $this->assertEquals('Exceeds Expectations', $report->get_rating_label());
        
        $report->overall_rating = 'meets';
        $this->assertEquals('Meets Expectations', $report->get_rating_label());
        
        $report->overall_rating = 'needs_improvement';
        $this->assertEquals('Needs Improvement', $report->get_rating_label());
        
        $report->overall_rating = 'pending';
        $this->assertEquals('Pending Review', $report->get_rating_label());
    }
    
    /**
     * Test status labels
     */
    public function test_get_status_label() {
        $report = new Report();
        
        $report->status = 'draft';
        $this->assertEquals('Draft', $report->get_status_label());
        
        $report->status = 'submitted';
        $this->assertEquals('Submitted', $report->get_status_label());
        
        $report->status = 'approved';
        $this->assertEquals('Approved', $report->get_status_label());
    }
    
    /**
     * Test previous report linkage
     */
    public function test_previous_report_id() {
        $report = new Report();
        $report->previous_report_id = 5;
        
        $this->assertEquals(5, $report->previous_report_id);
    }
    
    /**
     * Test from_row factory method
     */
    public function test_from_row_factory() {
        $row = [
            'id' => '10',
            'school_id' => '2',
            'user_id' => '3',
            'report_type' => 'tier1',
            'inspection_date' => '2026-01-03',
            'previous_report_id' => null,
            'overall_rating' => 'pending',
            'closing_notes' => 'Test notes',
            'status' => 'draft',
            'created_at' => '2026-01-03 10:00:00',
            'updated_at' => '2026-01-03 10:00:00'
        ];
        
        $report = Report::from_row($row);
        
        $this->assertInstanceOf(Report::class, $report);
        $this->assertEquals(10, $report->id);
        $this->assertEquals(2, $report->school_id);
        $this->assertEquals('tier1', $report->report_type);
        $this->assertEquals('Test notes', $report->closing_notes);
    }
}
