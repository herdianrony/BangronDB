<?php

/**
 * BangronDB Searchable Fields Benchmark
 * Menguji performa searchable fields dengan/without hashing.
 */

require_once __DIR__.'/vendor/autoload.php';

use BangronDB\Client;

echo "=== Searchable Fields Performance Test ===\n\n";

$client = new Client(__DIR__.'/examples/data/benchmark');
$db = $client->selectDB('test');
$collection = $db->users;

$recordsPerTest = 1000;
$iterations = 5;

// Insert test data
echo "Inserting $recordsPerTest records...\n";
$start = microtime(true);
for ($i = 0; $i < $recordsPerTest; ++$i) {
    $collection->insert([
        'name' => "User $i",
        'email' => "user$i@example.com",
    ]);
}
$insertTime = (microtime(true) - $start) * 1000;
echo 'Insert time: '.round($insertTime, 2)." ms\n\n";

// Test 1: Find WITHOUT searchable fields (json_extract)
echo "=== Test 1: Find WITHOUT searchable fields ===\n";
$totalTime = 0;
for ($i = 0; $i < $iterations; ++$i) {
    $start = microtime(true);
    for ($j = 0; $j < 100; ++$j) {
        $hash = 'user'.rand(0, $recordsPerTest - 1).'@example.com';
        $collection->findOne(['email' => $hash]);
    }
    $totalTime += (microtime(true) - $start);
}
$avgTime1 = ($totalTime / $iterations) * 1000;
$opsPerSec1 = 100 / ($avgTime1 / 1000);
echo 'Average time (100 queries): '.round($avgTime1, 2)." ms\n";
echo 'Ops/sec: '.round($opsPerSec1)."\n\n";

// Test 2: Add searchable fields WITHOUT hashing (hash=false)
echo "=== Test 2: Searchable Fields (hash=false) ===\n";
echo "Adding searchable fields...\n";
$start = microtime(true);
$collection->setSearchableFields(['email'], false);
echo 'Set searchable fields time: '.round((microtime(true) - $start) * 1000, 2)." ms\n";

// Repopulate existing data
echo "Repopulating data...\n";
$start = microtime(true);
$allData = $collection->find()->toArray();
foreach ($allData as $doc) {
    $collection->update(['_id' => $doc['_id']], $doc);
}
$populateTime = (microtime(true) - $start) * 1000;
echo 'Repopulate time: '.round($populateTime, 2)." ms\n";

// Create index
echo "Creating index on si_email...\n";
$collection->createIndex('si_email');

$totalTime = 0;
for ($i = 0; $i < $iterations; ++$i) {
    $start = microtime(true);
    for ($j = 0; $j < 100; ++$j) {
        $hash = 'user'.rand(0, $recordsPerTest - 1).'@example.com';
        $collection->findOne(['email' => $hash]);
    }
    $totalTime += (microtime(true) - $start);
}
$avgTime2 = ($totalTime / $iterations) * 1000;
$opsPerSec2 = 100 / ($avgTime2 / 1000);
echo 'Average time (100 queries): '.round($avgTime2, 2)." ms\n";
echo 'Ops/sec: '.round($opsPerSec2)."\n\n";

// Test 3: Add searchable fields WITH hashing (hash=true)
echo "=== Test 3: Searchable Fields (hash=true) ===\n";
$collection->removeSearchableField('email', true); // Remove old searchable field

echo "Adding searchable fields with hashing...\n";
$start = microtime(true);
$collection->setSearchableFields(['email'], true);
echo 'Set searchable fields time: '.round((microtime(true) - $start) * 1000, 2)." ms\n";

// Repopulate existing data
echo "Repopulating data with hash...\n";
$start = microtime(true);
$allData = $collection->find()->toArray();
foreach ($allData as $doc) {
    $collection->update(['_id' => $doc['_id']], $doc);
}
$populateTime = (microtime(true) - $start) * 1000;
echo 'Repopulate time: '.round($populateTime, 2)." ms\n";

// Create index
echo "Creating index on si_email...\n";
$collection->createIndex('si_email');

$totalTime = 0;
for ($i = 0; $i < $iterations; ++$i) {
    $start = microtime(true);
    for ($j = 0; $j < 100; ++$j) {
        // Pass the PLAIN email, BangronDB will hash it automatically based on config
        $collection->findOne(['email' => 'user'.rand(0, $recordsPerTest - 1).'@example.com']);
    }
    $totalTime += (microtime(true) - $start);
}
$avgTime3 = ($totalTime / $iterations) * 1000;
$opsPerSec3 = 100 / ($avgTime3 / 1000);
echo 'Average time (100 queries): '.round($avgTime3, 2)." ms\n";
echo 'Ops/sec: '.round($opsPerSec3)."\n\n";

// SUMMARY
echo "=== SUMMARY ===\n";
echo str_repeat('-', 50)."\n";
echo sprintf("%-25s | %10s | %10s\n", 'Test', 'Avg (ms)', 'Ops/sec');
echo str_repeat('-', 50)."\n";
echo sprintf("%-25s | %10.2f | %10.0f\n", 'No Searchable (json)', $avgTime1, $opsPerSec1);
echo sprintf("%-25s | %10.2f | %10.0f\n", 'Searchable (hash=false)', $avgTime2, $opsPerSec2);
echo sprintf("%-25s | %10.2f | %10.0f\n", 'Searchable (hash=true)', $avgTime3, $opsPerSec3);
echo str_repeat('-', 50)."\n";

echo "\nSpeed comparison:\n";
echo 'hash=false vs no searchable: '.round($opsPerSec2 / $opsPerSec1 * 100, 1)."%\n";
echo 'hash=true vs hash=false: '.round($opsPerSec3 / $opsPerSec2 * 100, 1)."%\n";

$client->close();
