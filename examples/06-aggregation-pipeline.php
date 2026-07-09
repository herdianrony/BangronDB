<?php

/**
 * Contoh 06: Aggregation Pipeline
 *
 * Demonstrasi $match, $group, $sort, $limit, $skip,
 * $project, $count, $unset untuk analisis data.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sep('Contoh 06: Aggregation Pipeline');

$client = createIsolatedClient('example06');
$db = $client->createDB('analytics');
$sales = $db->createCollection('sales');

// Seed data
$sales->insert([
    ['category' => 'Elektronik', 'product' => 'Laptop',  'amount' => 15000000, 'qty' => 1, 'status' => 'completed'],
    ['category' => 'Elektronik', 'product' => 'Mouse',   'amount' => 250000,   'qty' => 2, 'status' => 'completed'],
    ['category' => 'Pakaian',    'product' => 'Kaos',    'amount' => 150000,   'qty' => 3, 'status' => 'completed'],
    ['category' => 'Pakaian',    'product' => 'Celana',  'amount' => 300000,   'qty' => 1, 'status' => 'pending'],
    ['category' => 'Elektronik', 'product' => 'Keyboard','amount' => 500000,   'qty' => 1, 'status' => 'cancelled'],
    ['category' => 'Pakaian',    'product' => 'Jaket',   'amount' => 450000,   'qty' => 1, 'status' => 'completed'],
    ['category' => 'Makanan',    'product' => 'Kopi',    'amount' => 50000,    'qty' => 10,'status' => 'completed'],
    ['category' => 'Makanan',    'product' => 'Teh',     'amount' => 30000,    'qty' => 5, 'status' => 'completed'],
]);

// ── $match ─────────────────────────────────────────────
sub('$match — Filter Dokumen');

$completed = $sales->aggregate([
    ['$match' => ['status' => 'completed']],
]);

echo "Completed sales: " . count($completed) . "\n";
foreach ($completed as $s) {
    echo "  {$s['product']} — Rp " . number_format($s['amount']) . "\n";
}

// ── $group + $sum ──────────────────────────────────────
sub('$group + $sum — Total per Kategori');

$byCategory = $sales->aggregate([
    ['$group' => [
        '_id'   => '$category',
        'total' => ['$sum' => '$amount'],
    ]],
]);

foreach ($byCategory as $row) {
    echo "  {$row['_id']}: Rp " . number_format($row['total']) . "\n";
}

// ── $group + $count ────────────────────────────────────
sub('$group + $count — Jumlah Transaksi per Kategori');

$counts = $sales->aggregate([
    ['$group' => [
        '_id'   => '$category',
        'count' => ['$count' => null],
    ]],
]);

foreach ($counts as $row) {
    echo "  {$row['_id']}: {$row['count']} transaksi\n";
}

// ── $group + $avg ──────────────────────────────────────
sub('$group + $avg — Rata-rata per Kategori');

$averages = $sales->aggregate([
    ['$match' => ['status' => 'completed']],
    ['$group' => [
        '_id'       => '$category',
        'avg_amount' => ['$avg' => '$amount'],
    ]],
]);

foreach ($averages as $row) {
    $avg = $row['avg_amount'] ?? 'N/A (empty group)';
    if (is_numeric($avg)) {
        $avg = 'Rp ' . number_format($avg);
    }
    echo "  {$row['_id']}: $avg\n";
}

// ── $group + $min / $max ───────────────────────────────
sub('$group + $min / $max — Range per Kategori');

$ranges = $sales->aggregate([
    ['$group' => [
        '_id'      => '$category',
        'cheapest' => ['$min' => '$amount'],
        'priciest' => ['$max' => '$amount'],
    ]],
]);

foreach ($ranges as $row) {
    echo "  {$row['_id']}: Rp " . number_format($row['cheapest']) . " — Rp " . number_format($row['priciest']) . "\n";
}

// ── $sort + $limit + $skip ────────────────────────────
sub('$sort + $limit + $skip — Pagination');

$top2 = $sales->aggregate([
    ['$match' => ['status' => 'completed']],
    ['$sort' => ['amount' => -1]],
    ['$limit' => 2],
]);

echo "Top 2 completed by amount:\n";
foreach ($top2 as $i => $s) {
    echo "  #" . ($i + 1) . " {$s['product']} — Rp " . number_format($s['amount']) . "\n";
}

// ── $project ───────────────────────────────────────────
sub('$project — Pilih & Rename Field');

$projected = $sales->aggregate([
    ['$match' => ['status' => 'completed']],
    ['$project' => ['produk' => '$product', 'harga' => '$amount']],
    ['$limit' => 3],
]);

foreach ($projected as $s) {
    echo "  {$s['produk']}: Rp " . number_format($s['harga']) . "\n";
}

// ── $unset ────────────────────────────────────────────
sub('$unset — Hapus Field');

$unsetted = $sales->aggregate([
    ['$match' => ['status' => 'completed']],
    ['$unset' => ['qty', 'status']],
    ['$limit' => 2],
]);

p($unsetted[0]);

// ── $count ────────────────────────────────────────────
sub('$count — Hitung Hasil Pipeline');

$result = $sales->aggregate([
    ['$match' => ['status' => 'completed']],
    ['$count' => 'completed_total'],
]);

p("Completed transactions: {$result[0]['completed_total']}");

// ── Pipeline Kompleks ─────────────────────────────────
sub('Pipeline Kompleks — Top Category by Revenue');

$topCategory = $sales->aggregate([
    ['$match' => ['status' => 'completed']],
    ['$group' => [
        '_id'   => '$category',
        'revenue' => ['$sum' => '$amount'],
        'transactions' => ['$count' => null],
    ]],
    ['$sort' => ['revenue' => -1]],
    ['$limit' => 1],
]);

echo "  🏆 {$topCategory[0]['_id']}\n";
echo "     Revenue: Rp " . number_format($topCategory[0]['revenue']) . "\n";
echo "     Transactions: {$topCategory[0]['transactions']}\n";

@$client->close();
echo "\nDone!\n";