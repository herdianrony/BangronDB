<?php

/**
 * Contoh 09: Indexing, Health & Monitoring
 *
 * Buat index untuk performa query, health metrics,
 * health report, integrity check, dan VACUUM.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sep('Contoh 09: Indexing, Health & Monitoring');

$client = createIsolatedClient('example09');
$db = $client->createDB('monitored_app');
$users = $db->createCollection('users');

// ── Insert Sample Data ────────────────────────────────────
sub('Setup Data');

for ($i = 1; $i <= 100; $i++) {
    $users->insert([
        'name'   => "User {$i}",
        'email'  => "user{$i}@example.com",
        'age'    => rand(18, 65),
        'status' => rand(0, 10) > 2 ? 'active' : 'inactive',
    ]);
}
echo "Inserted 100 users\n";

// ── Create Indexes ─────────────────────────────────────────
sub('Create Indexes');

$users->createIndex('email', 'idx_users_email');
$users->createIndex('status', 'idx_users_status');
echo "Indexes created: email, status\n";

// ── Query Performance Comparison ──────────────────────────
sub('Query Performance (indexed vs non-indexed)');

// Indexed query
$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $users->findOne(['email' => 'user50@example.com']);
}
$indexedTime = microtime(true) - $start;

// Non-indexed query
$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $users->findOne(['age' => 30]);
}
$nonIndexedTime = microtime(true) - $start;

echo "Indexed (email):   " . round($indexedTime * 1000, 2) . " ms (100 queries)\n";
echo "Non-indexed (age): " . round($nonIndexedTime * 1000, 2) . " ms (100 queries)\n";
echo "Speedup: " . round($nonIndexedTime / $indexedTime, 1) . "x\n";

// ── Drop Index ────────────────────────────────────────────
sub('Drop Index');

try {
    $db->dropIndex('idx_users_status');
    echo "Dropped idx_users_status\n";
} catch (Throwable $e) {
    echo "Drop index skipped: {$e->getMessage()}\n";
}

// ── Health Metrics ────────────────────────────────────────
sub('Health Metrics');

$metrics = $db->getHealthMetrics();
echo "Database type: " . ($metrics['database']['type'] ?? 'unknown') . "\n";
echo "Encryption: " . ($metrics['database']['encryption_enabled'] ? 'yes' : 'no') . "\n";
echo "Integrity: " . ($metrics['integrity']['status'] ?? 'unknown') . "\n";
echo "Total collections: " . ($metrics['metrics']['total_collections'] ?? 0) . "\n";
echo "Total documents: " . ($metrics['metrics']['total_documents'] ?? 0) . "\n";

// ── Health Report ─────────────────────────────────────────
sub('Health Report');

$report = $db->getHealthReport();
echo "Status: {$report['status']}\n";
if (!empty($report['warnings'])) {
    echo "Warnings:\n";
    foreach ($report['warnings'] as $w) {
        echo "  - {$w}\n";
    }
}
if (!empty($report['recommendations'])) {
    echo "Recommendations:\n";
    foreach ($report['recommendations'] as $r) {
        echo "  - {$r}\n";
    }
}

// ── Performance Metrics ───────────────────────────────────
sub('Performance Metrics');

$perf = $db->getPerformanceMetrics();
echo "Page count: " . ($perf['page_count'] ?? 'N/A') . "\n";
echo "Page size: " . ($perf['page_size'] ?? 'N/A') . "\n";
echo "Fragmentation: " . round(($perf['fragmentation_ratio'] ?? 0) * 100, 2) . "%\n";

// ── Collection Metrics ────────────────────────────────────
sub('Collection Metrics');

$collMetrics = $db->getCollectionMetrics();
foreach ($collMetrics as $name => $m) {
    echo "Collection '{$name}': {$m['documents']} docs, " . round($m['size_bytes'] / 1024, 1) . " KB\n";
}

// ── VACUUM ────────────────────────────────────────────────
sub('VACUUM - Optimize Database');

try {
    $db->vacuum();
    echo "VACUUM completed - space reclaimed\n";
} catch (Throwable $e) {
    echo "VACUUM skipped: {$e->getMessage()}\n";
}

// ── Change Notification ───────────────────────────────────
sub('Change Notification');

$lastMod = $users->getLastModified();
echo "Collection version: " . ($lastMod['version'] ?? 'N/A') . "\n";
echo "Last updated: " . ($lastMod['last_updated'] ?? 'N/A') . "\n";

@$client->close();
echo "\nDone!\n";
