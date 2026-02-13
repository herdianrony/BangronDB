<?php
/**
 * Simple BangronDB Performance Test
 */

require_once __DIR__.'/../vendor/autoload.php';

use BangronDB\Client;

$dataDir = 'C:\\temp\\bangrondb_benchmark';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

echo "=== BangronDB Performance Test ===\n";
echo "Data directory: $dataDir\n\n";

try {
    // Initialize
    $client = new Client($dataDir);
    $db = $client->selectDB('benchmark');
    $users = $db->users;
    
    echo "✓ Database initialized\n\n";
    
    // Test 1: Insert
    echo "=== Test 1: Single Insert (1000 records) ===\n";
    $start = microtime(true);
    for ($i = 0; $i < 1000; $i++) {
        $users->insert([
            'name' => "User $i",
            'email' => "user$i@example.com",
            'age' => rand(18, 80),
            'status' => 'active'
        ]);
    }
    $insertTime = (microtime(true) - $start) * 1000;
    $insertOps = 1000000 / ($insertTime * 1000); // ops per second
    printf("Time: %.2f ms | Ops/sec: %.0f\n\n", $insertTime, $insertOps);
    
    // Test 2: Find (no index)
    echo "=== Test 2: Find One (no index) - 100 queries ===\n";
    $start = microtime(true);
    for ($i = 0; $i < 100; $i++) {
        $users->findOne(['email' => 'user' . rand(0, 999) . '@example.com']);
    }
    $findTime = (microtime(true) - $start) * 1000;
    $findOps = 100000 / ($findTime * 1000);
    printf("Time: %.2f ms | Ops/sec: %.0f\n\n", $findTime, $findOps);
    
    // Test 3: Create index
    echo "=== Test 3: Create Index on email ===\n";
    $start = microtime(true);
    $users->createIndex('email');
    $indexTime = (microtime(true) - $start) * 1000;
    printf("Time: %.2f ms\n\n", $indexTime);
    
    // Test 4: Find (with index)
    echo "=== Test 4: Find One (with index) - 100 queries ===\n";
    $start = microtime(true);
    for ($i = 0; $i < 100; $i++) {
        $users->findOne(['email' => 'user' . rand(0, 999) . '@example.com']);
    }
    $findIndexedTime = (microtime(true) - $start) * 1000;
    $findIndexedOps = 100000 / ($findIndexedTime * 1000);
    printf("Time: %.2f ms | Ops/sec: %.0f\n", $findIndexedTime, $findIndexedOps);
    printf("Speedup: %.1fx\n\n", $findTime / $findIndexedTime);
    
    // Test 5: Update
    echo "=== Test 5: Update (100 records) ===\n";
    $start = microtime(true);
    for ($i = 0; $i < 100; $i++) {
        $users->update(
            ['email' => 'user' . rand(0, 999) . '@example.com'],
            ['status' => 'updated', 'updated_at' => date('c')]
        );
    }
    $updateTime = (microtime(true) - $start) * 1000;
    $updateOps = 100000 / ($updateTime * 1000);
    printf("Time: %.2f ms | Ops/sec: %.0f\n\n", $updateTime, $updateOps);
    
    // Test 6: Count
    echo "=== Test 6: Count with query ===\n";
    $start = microtime(true);
    $count = $users->count(['status' => 'active']);
    $countTime = (microtime(true) - $start) * 1000;
    printf("Count: %d | Time: %.2f ms\n\n", $count, $countTime);
    
    // Test 7: Pagination
    echo "=== Test 7: Pagination (limit 20, skip varies) ===\n";
    $start = microtime(true);
    for ($page = 0; $page < 50; $page++) {
        $results = $users->find()
            ->skip($page * 20)
            ->limit(20)
            ->toArray();
    }
    $paginationTime = (microtime(true) - $start) * 1000;
    printf("50 pages × 20 items | Time: %.2f ms | Avg: %.2f ms/page\n\n", $paginationTime, $paginationTime / 50);
    
    // Test 8: Searchable fields
    echo "=== Test 8: Searchable Fields (email hashed) ===\n";
    $users->remove([]);
    
    $start = microtime(true);
    for ($i = 0; $i < 1000; $i++) {
        $users->insert([
            'name' => "User $i",
            'email' => "user$i@example.com"
        ]);
    }
    
    $users->setSearchableFields(['email'], true);
    $users->createIndex('si_email');
    
    $searchTime = (microtime(true) - $start) * 1000;
    printf("Setup searchable fields: %.2f ms\n\n", $searchTime);
    
    // Test 9: Query searchable fields
    echo "=== Test 9: Query via Searchable Fields - 100 queries ===\n";
    $start = microtime(true);
    for ($i = 0; $i < 100; $i++) {
        $users->findOne(['email' => 'user' . rand(0, 999) . '@example.com']);
    }
    $searchQueryTime = (microtime(true) - $start) * 1000;
    $searchQueryOps = 100000 / ($searchQueryTime * 1000);
    printf("Time: %.2f ms | Ops/sec: %.0f\n\n", $searchQueryTime, $searchQueryOps);
    
    // Summary
    echo "=== SUMMARY ===\n";
    echo str_repeat('=', 70) . "\n";
    printf("%-35s | %10s | %12s\n", 'Operation', 'Time (ms)', 'Ops/Sec');
    echo str_repeat('-', 70) . "\n";
    printf("%-35s | %10.2f | %12.0f\n", 'Single Insert', $insertTime / 1000, $insertOps);
    printf("%-35s | %10.2f | %12.0f\n", 'Find One (no index)', $findTime, $findOps);
    printf("%-35s | %10.2f | %12.0f\n", 'Find One (indexed)', $findIndexedTime, $findIndexedOps);
    printf("%-35s | %10.2f | %12.0f\n", 'Update', $updateTime, $updateOps);
    printf("%-35s | %10.2f | N/A\n", 'Create Index (email)', $indexTime);
    printf("%-35s | %10.2f | N/A\n", 'Pagination (50 pages)', $paginationTime);
    printf("%-35s | %10.2f | %12.0f\n", 'Query Searchable (indexed)', $searchQueryTime, $searchQueryOps);
    echo str_repeat('=', 70) . "\n";
    
    // Health check
    echo "\n=== Database Health ===\n";
    $health = $db->getHealthReport();
    printf("Status: %s\n", $health['status']);
    printf("Issues: %s\n", count($health['issues']) > 0 ? implode(', ', $health['issues']) : 'None');
    
    // Cleanup
    $db->drop();
    $client->close();
    
    echo "\n✓ All tests completed successfully!\n";
    
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}
