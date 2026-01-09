<?php
require_once 'wp-load.php';
require_once 'chroma-qa-reports/includes/models/class-school.php';
require_once 'chroma-qa-reports/includes/models/class-report.php';

use ChromaQA\Models\School;
use ChromaQA\Models\Report;

echo "Checking Schools...\n";
$schools = School::all();
echo "Count Schools: " . count($schools) . "\n";
foreach($schools as $s) {
    echo "- " . $s->name . " (ID: " . $s->id . ")\n";
}

echo "\nChecking Reports...\n";
$reports = Report::all(['limit' => 10]);
echo "Count Reports: " . count($reports) . "\n";
foreach($reports as $r) {
    $s = School::find($r->school_id);
    echo "- Report #" . $r->id . " for " . ($s ? $s->name : 'Unknown') . " (User: " . $r->user_id . ", Status: " . $r->status . ")\n";
}

$curr_user = wp_get_current_user();
echo "\nCurrent User: " . $curr_user->display_name . " (ID: " . $curr_user->ID . ")\n";
echo "My Reports Count: " . Report::count(['user_id' => $curr_user->ID]) . "\n";
