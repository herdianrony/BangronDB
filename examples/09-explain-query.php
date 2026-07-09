<?php

/**
 * Contoh 09: Explain Query
 *
 * Demonstrasi explain() untuk menganalisis dan mengoptimasi query.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sep('Contoh 09: Explain Query');

$client = createIsolatedClient('example09');
$db = $client->createDB('explain_demo');
$users = $db->createCollection('users');

// Seed 100 users
$batch = [];
for ($i = 1; $i <= 100; $i++) {
    $batch[] = [
        'name'  => "User {$i}",
        'email' => "user{$i}@example.com",
        'age'   => 20 + ($i % 50),
        'role'  => ['user', 'admin', 'editor'][$i % 3],
    ];
}
$users->insert($batch);
echo "Seeded 100 users\n";

// ── Explain tanpa filter ──────────────────────────
sub('1. Explain Tanpa Filter');

$explanation = $users->explain();
echo "Strategy: {$explanation['query_plan']['strategy']}\n";
echo "Summary: {$explanation['performance']['criteria_summary']}\n";
echo "Documents matched: {$explanation['performance']['documents_matched']}\n";
echo "Execution time: {$explanation['performance']['execution_time_ms']}ms\n";

// ── Explain dengan filter sederhana ───────────────
sub('2. Explain dengan Filter: age >= 30');

$explanation = $users->explain(['age' => ['$gte' => 30]]);
p([
    'strategy'    => $explanation['query_plan']['strategy'],
    'uses_index'  => $explanation['query_plan']['uses_index'],
    'matched'     => $explanation['performance']['documents_matched'],
    'scan_ratio'  => round($explanation['performance']['scan_ratio'] * 100) . '%',
    'time_ms'     => $explanation['performance']['execution_time_ms'],
]);

// ── Explain dengan index ───────────────────────────
sub('3. Explain SETELAH Buat Index');

$users->createIndex('age');

$explanation = $users->explain(['age' => ['$gte' => 30]]);
p([
    'strategy'    => $explanation['query_plan']['strategy'],
    'uses_index'  => $explanation['query_plan']['uses_index'],
    'index_name'  => $explanation['query_plan']['index_name'],
    'scan_ratio'  => round($explanation['performance']['scan_ratio'] * 100) . '%',
    'time_ms'     => $explanation['performance']['execution_time_ms'],
]);

// ── Suggestions ────────────────────────────────────
sub('4. Suggestions (Sebelum Index)');

$items = $db->createCollection('items');
$items->insert([
    ['name' => 'A', 'category' => 'X', 'price' => 100],
    ['name' => 'B', 'category' => 'Y', 'price' => 200],
]);

$explanation = $items->explain(['price' => ['$gt' => 50]]);
if (!empty($explanation['suggestions'])) {
    echo "Suggestions:\n";
    foreach ($explanation['suggestions'] as $s) {
        echo "  → $s\n";
    }
}

// ── Tips Optimasi ─────────────────────────────────
sub('Tips Optimasi Query');

echo "1. Buat index pada field yang sering di-query\n";
echo "   \$collection->createIndex('field_name');\n\n";
echo "2. Gunakan explain() untuk cek scan_ratio\n";
echo "   < 10% = optimal, > 50% = perlu index\n\n";
echo "3. Gunakan \$in daripada multiple \$or untuk field sama\n\n";
echo "4. Limit hasil dengan ->limit() untuk query besar\n\n";
echo "5. Gunakan stream() untuk dataset sangat besar\n";

@$client->close();
echo "\nDone!\n";