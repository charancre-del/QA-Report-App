<?php
/**
 * CQA Doctor - Diagnostic Script
 */

require_once( dirname(__FILE__) . '/wp-load.php' );

if ( ! current_user_can( 'manage_options' ) ) {
    die( 'Access denied.' );
}

global $wpdb;

echo "<h1>CQA Doctor - Diagnostics</h1>";

// 1. Check Tables
$tables = [
    $wpdb->prefix . 'cqa_schools',
    $wpdb->prefix . 'cqa_reports',
    $wpdb->prefix . 'cqa_responses',
    $wpdb->prefix . 'cqa_photos',
    $wpdb->prefix . 'cqa_ai_summaries'
];

echo "<h2>Database Tables</h2>";
foreach ( $tables as $table ) {
    $exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" );
    echo "Table <code>$table</code>: " . ( $exists ? '<span style="color:green">EXISTS</span>' : '<span style="color:red">MISSING</span>' ) . "<br>";
    
    if ( $exists ) {
        $columns = $wpdb->get_results( "SHOW COLUMNS FROM $table" );
        echo "<ul>";
        foreach ( $columns as $col ) {
            echo "<li><code>{$col->Field}</code> ({$col->Type}) - Null: {$col->Null}</li>";
        }
        echo "</ul>";
    }
}

// 2. Check User Capabilities
echo "<h2>User Capabilities</h2>";
$user = wp_get_current_user();
$caps = [
    'cqa_manage_schools',
    'cqa_create_reports',
    'cqa_view_all_reports',
    'manage_options'
];

foreach ( $caps as $cap ) {
    echo "Capability <code>$cap</code>: " . ( current_user_can( $cap ) ? '<span style="color:green">YES</span>' : '<span style="color:red">NO</span>' ) . "<br>";
}

// 3. Test School Save Logic
echo "<h2>Test school Save Logic</h2>";
try {
    $school = new \ChromaQA\Models\School();
    $school->name = "Test " . time();
    $school->region = "Test Region";
    $school->status = "active";
    $result = $school->save();
    
    if ( $result ) {
        echo '<span style="color:green">SUCCESS</span>: Created test school ID: ' . $result . "<br>";
        // Cleanup
        $wpdb->delete( $wpdb->prefix . 'cqa_schools', [ 'id' => $result ] );
    } else {
        echo '<span style="color:red">FAILED</span>: ' . $wpdb->last_error . "<br>";
    }
} catch ( \Exception $e ) {
    echo '<span style="color:red">EXCEPTION</span>: ' . $e->getMessage() . "<br>";
}

echo "<h2>System Info</h2>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "WP Version: " . $GLOBALS['wp_version'] . "<br>";
echo "Time: " . current_time('mysql') . "<br>";

// 4. Test REST API
echo "<h2>Test REST API</h2>";
$rest_url = rest_url( 'cqa/v1/schools' );
echo "REST URL: <code>$rest_url</code><br>";

$response = wp_remote_post( $rest_url, [
    'headers' => [
        'X-WP-Nonce' => wp_create_nonce( 'wp_rest' ),
    ],
    'body' => [
        'name'   => 'REST Test ' . time(),
        'region' => 'REST Region',
        'status' => 'active'
    ]
] );

if ( is_wp_error( $response ) ) {
    echo '<span style="color:red">ERROR</span>: ' . $response->get_error_message() . "<br>";
} else {
    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    echo "Response Code: $code<br>";
    echo "Response Body: <pre>" . esc_html( $body ) . "</pre>";
    
    if ( $code === 201 ) {
        $json = json_decode( $body, true );
        if ( isset( $json['id'] ) ) {
            $wpdb->delete( $wpdb->prefix . 'cqa_schools', [ 'id' => $json['id'] ] );
            echo '<span style="color:green">SUCCESS</span>: REST API is working.<br>';
        }
    }
}
