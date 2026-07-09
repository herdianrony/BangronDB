<?php

/**
 * Contoh 05: Bulk Operations
 *
 * Demonstrasi insertMany, updateMany, deleteMany
 * untuk operasi massal yang efisien.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sep('Contoh 05: Bulk Operations');

$client = createIsolatedClient('example05');
$db = $client->createDB('bulk_demo');
$items = $db->createCollection('items');

// ── insertMany ───────────────────────────────────────────
sub('insertMany — Insert Banyak Dokumen');

$result = $items->insertMany([
    ['sku' => 'A1', 'status' => 'pending', 'qty' => 10],
    ['sku' => 'A2', 'status' => 'pending', 'qty' => 20],
    ['sku' => 'A3', 'status' => 'pending', 'qty' => 15],
    ['sku' => 'B1', 'status' => 'done',   'qty' => 5],
    ['sku' => 'B2', 'status' => 'done',   'qty' => 8],
]);

p("Inserted: {$result['inserted_count']} documents");
p("IDs: " . implode(', ', array_slice($result['inserted_ids'], 0, 3)) . '...');

// ── updateMany ───────────────────────────────────────────
sub('updateMany — Update Banyak Dokumen');

$result = $items->updateMany(
    ['status' => 'pending'],
    ['$set' => ['status' => 'processed'], '$inc' => ['qty' => 5]]
);

p("Matched: {$result['matched_count']} documents");
p("Modified: {$result['modified_count']} documents");

// Verifikasi
$processed = $items->find(['status' => 'processed'])->toArray();
echo "Processed items:\n";
foreach ($processed as $item) {
    echo "  {$item['sku']}: qty={$item['qty']}\n";
}

// ── deleteMany ───────────────────────────────────────────
sub('deleteMany — Hapus Banyak Dokumen');

$result = $items->deleteMany(['status' => 'done']);
p("Deleted: {$result['deleted_count']} documents");
p("Remaining: " . $items->count());

// ── insertMany + Hook Rejection (Rollback) ──────────────
sub('insertMany + Hook Rejection → Rollback');

$orders = $db->createCollection('orders');
$callCount = 0;
$orders->on('beforeInsert', function ($doc) use (&$callCount) {
    $callCount++;
    if ($callCount === 2) {
        return false; // reject second document
    }
    return $doc;
});

$result = $orders->insertMany([
    ['item' => 'Widget A', 'qty' => 5],
    ['item' => 'Widget B', 'qty' => 10],  // ← ditolak hook
    ['item' => 'Widget C', 'qty' => 15],
]);

p("Inserted before rejection: {$result['inserted_count']}");
p("Total in collection: " . $orders->count() . " (rolled back!)");

// ── Performance Tip ─────────────────────────────────────
sub('Kapan Pakai Bulk vs Satu-per-Satu');

echo "┌──────────────────┬───────────────────────────────────────┐\n";
echo "│ Operasi          │ Gunakan                               │\n";
echo "├──────────────────┼───────────────────────────────────────┤\n";
echo "│ Insert banyak    │ insertMany() — single transaction      │\n";
echo "│ Update banyak    │ updateMany() — single-pass, no requery │\n";
echo "│ Delete banyak    │ deleteMany() — single query             │\n";
echo "│ Perlu hook rollback │ insertMany() (auto-rollback)       │\n";
echo "│ Perlu progress   │ Loop insert() manual                  │\n";
echo "└──────────────────┴───────────────────────────────────────┘\n";

@$client->close();
echo "\nDone!\n";