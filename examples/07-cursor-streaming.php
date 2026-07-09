<?php

/**
 * Contoh 07: Cursor Streaming
 *
 * Demonstrasi stream() untuk memory-efficient iteration
 * pada dataset besar menggunakan PHP Generator.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sep('Contoh 07: Cursor Streaming');

$client = createIsolatedClient('example07');
$db = $client->createDB('streaming');
$logs = $db->createCollection('logs');

// Seed data — simulasi 1000 log entries
sub('Seed 1000 Log Entries');
$batch = [];
for ($i = 1; $i <= 1000; $i++) {
    $batch[] = [
        'level'   => ['info', 'warning', 'error'][$i % 3],
        'message' => "Log entry #{$i}",
        'ts'      => time() - (1000 - $i),
    ];
}
$logs->insert($batch);
echo "Inserted 1000 log entries\n";

// ── stream() dasar ────────────────────────────────────
sub('stream() — Iterate Tanpa Load Semua ke Memory');

$count = 0;
foreach ($logs->stream() as $doc) {
    $count++;
}
echo "Streamed $count documents\n";

// ── stream() dengan criteria ──────────────────────────
sub('stream() dengan Criteria');

$errorCount = 0;
foreach ($logs->stream(['level' => 'error']) as $doc) {
    $errorCount++;
}
echo "Error logs: $errorCount\n";

// ── stream() dengan sort + limit ─────────────────────
sub('stream() dengan Sort + Limit');

echo "Latest 5 logs:\n";
foreach ($logs->stream([], ['sort' => ['ts' => -1], 'limit' => 5]) as $doc) {
    echo "  [{$doc['level']}] {$doc['message']}\n";
}

// ── Perbandingan: find() vs stream() ──────────────────
sub('Kapan Pakai stream() vs find()');

echo "┌─────────────────────┬──────────────────────────────────┐\n";
echo "│ find()->toArray()   │ Dataset kecil (<1000 dokumen)    │\n";
echo "│                     │ Butuh projection                  │\n";
echo "│                     │ Butuh populate / count            │\n";
echo "├─────────────────────┼──────────────────────────────────┤\n";
echo "│ stream()            │ Dataset besar (1000+)            │\n";
echo "│                     │ Proses baris per baris            │\n";
echo "│                     │ Memory-efficient (Generator)      │\n";
echo "│                     │ Export CSV, batch processing      │\n";
echo "└─────────────────────┴──────────────────────────────────┘\n";

@$client->close();
echo "\nDone!\n";