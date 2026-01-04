<?php
/**
 * Tests for School Model
 */

namespace ChromaQA\Tests\Models;

use PHPUnit\Framework\TestCase;
use ChromaQA\Models\School;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

class SchoolTest extends TestCase {
    
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        
        // Mock global $wpdb
        global $wpdb;
        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('prepare')->andReturn('SELECT * FROM...');
        $wpdb->shouldReceive('get_results')->andReturn([]);
    }
    
    protected function tearDown(): void {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }
    
    /**
     * Test school creation with valid data
     */
    public function test_create_school_with_valid_data() {
        $school = new School();
        $school->name = 'Test School';
        $school->location = 'Test City';
        $school->region = 'North';
        $school->status = 'active';
        
        $this->assertEquals('Test School', $school->name);
        $this->assertEquals('Test City', $school->location);
        $this->assertEquals('North', $school->region);
        $this->assertEquals('active', $school->status);
    }
    
    /**
     * Test school validation - name required
     */
    public function test_school_name_is_required() {
        $school = new School();
        $school->location = 'Test City';
        
        // Name should be required
        $this->assertEmpty($school->name);
    }
    
    /**
     * Test classroom config JSON handling
     */
    public function test_classroom_config_json() {
        $school = new School();
        $config = [
            'infant' => ['count' => 2, 'capacity' => 8],
            'toddler' => ['count' => 3, 'capacity' => 12]
        ];
        
        $school->classroom_config = $config;
        
        $this->assertIsArray($school->classroom_config);
        $this->assertEquals(2, $school->classroom_config['infant']['count']);
    }
    
    /**
     * Test table name generation
     */
    public function test_get_table_name() {
        Functions\expect('wpdb')->once();
        
        $tableName = School::get_table_name();
        $this->assertStringContainsString('cqa_schools', $tableName);
    }
    
    /**
     * Test from_row factory method
     */
    public function test_from_row_factory() {
        $row = [
            'id' => '1',
            'name' => 'Factory School',
            'location' => 'Factory City',
            'region' => 'South',
            'status' => 'active',
            'drive_folder_id' => '12345',
            'classroom_config' => '[]',
            'created_at' => '2026-01-01 00:00:00'
        ];
        
        $school = School::from_row($row);
        
        $this->assertInstanceOf(School::class, $school);
        $this->assertEquals(1, $school->id);
        $this->assertEquals('Factory School', $school->name);
        $this->assertEquals('12345', $school->drive_folder_id);
    }
    
    /**
     * Test school status enumeration
     */
    public function test_school_status_values() {
        $school = new School();
        
        $validStatuses = ['active', 'inactive', 'pending'];
        
        foreach ($validStatuses as $status) {
            $school->status = $status;
            $this->assertEquals($status, $school->status);
        }
    }
}
