<?php
/**
 * Tests for Checklist Response Model
 */

namespace ChromaQA\Tests\Models;

use PHPUnit\Framework\TestCase;
use ChromaQA\Models\Checklist_Response;
use Brain\Monkey;
use Mockery;

class ChecklistResponseTest extends TestCase {
    
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
     * Test response creation
     */
    public function test_create_response() {
        $response = new Checklist_Response();
        $response->report_id = 1;
        $response->section_key = 'classroom_infant';
        $response->item_key = 'ratios';
        $response->rating = 'yes';
        $response->notes = 'Looking good';
        
        $this->assertEquals(1, $response->report_id);
        $this->assertEquals('yes', $response->rating);
        $this->assertEquals('Looking good', $response->notes);
    }
    
    /**
     * Test rating constants
     */
    public function test_rating_constants() {
        $this->assertEquals('yes', Checklist_Response::RATING_YES);
        $this->assertEquals('sometimes', Checklist_Response::RATING_SOMETIMES);
        $this->assertEquals('no', Checklist_Response::RATING_NO);
        $this->assertEquals('na', Checklist_Response::RATING_NA);
    }
    
    /**
     * Test get_rating_icon method
     */
    public function test_get_rating_icon() {
        $response = new Checklist_Response();
        
        $response->rating = 'yes';
        $this->assertEquals('✅', $response->get_rating_icon());
        
        $response->rating = 'sometimes';
        $this->assertEquals('⚠️', $response->get_rating_icon());
        
        $response->rating = 'no';
        $this->assertEquals('❌', $response->get_rating_icon());
        
        $response->rating = 'na';
        $this->assertEquals('➖', $response->get_rating_icon());
    }
    
    /**
     * Test has_changed method
     */
    public function test_has_changed() {
        $response = new Checklist_Response();
        $response->rating = 'yes';
        $response->previous_rating = 'no';
        
        $this->assertTrue($response->has_changed());
        
        $response->previous_rating = 'yes';
        $this->assertFalse($response->has_changed());
    }
    
    /**
     * Test is_improvement method
     */
    public function test_is_improvement() {
        $response = new Checklist_Response();
        
        // No -> Yes (improvement)
        $response->previous_rating = 'no';
        $response->rating = 'yes';
        $this->assertTrue($response->is_improvement());
        
        // Sometimes -> Yes (improvement)
        $response->previous_rating = 'sometimes';
        $response->rating = 'yes';
        $this->assertTrue($response->is_improvement());
        
        // Yes -> No (not improvement)
        $response->previous_rating = 'yes';
        $response->rating = 'no';
        $this->assertFalse($response->is_improvement());
        
        // No -> Sometimes (improvement)
        $response->previous_rating = 'no';
        $response->rating = 'sometimes';
        $this->assertTrue($response->is_improvement());
    }
    
    /**
     * Test from_row factory
     */
    public function test_from_row() {
        $row = [
            'id' => '5',
            'report_id' => '10',
            'section_key' => 'admin',
            'item_key' => 'licensing',
            'rating' => 'yes',
            'notes' => 'All up to date',
            'evidence_type' => 'document',
            'previous_rating' => 'sometimes',
            'previous_notes' => 'Was pending',
            'created_at' => '2026-01-03 10:00:00'
        ];
        
        $response = Checklist_Response::from_row($row);
        
        $this->assertInstanceOf(Checklist_Response::class, $response);
        $this->assertEquals(5, $response->id);
        $this->assertEquals(10, $response->report_id);
        $this->assertEquals('All up to date', $response->notes);
    }
}
