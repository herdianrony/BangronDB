<?php

/**
 * Contoh 01: Quick Start & CRUD Dasar
 *
 * Panduan cepat 5 menit untuk mulai menggunakan BangronDB.
 * Mencakup operasi Create, Read, Update, Delete.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sep('Contoh 01: Quick Start & CRUD Dasar');

// ── Inisialisasi ──────────────────────────────────────────
$client = createIsolatedClient('example01');
$db = $client->createDB('myapp');
$users = $db->createCollection('users');

// ── INSERT ────────────────────────────────────────────────
sub('INSERT - Menambahkan Dokumen');

// Single insert → return ID
$userId = $users->insert([
    'name'  => 'John Doe',
    'email' => 'john@example.com',
    'age'   => 30,
]);
echo "Inserted user ID: {$userId}\n";

// Batch insert → return jumlah dokumen
$count = $users->insert([
    ['name' => 'Jane Doe',   'email' => 'jane@example.com',   'age' => 28, 'status' => 'active'],
    ['name' => 'Bob Smith',  'email' => 'bob@example.com',    'age' => 35, 'status' => 'inactive'],
    ['name' => 'Alice Wong', 'email' => 'alice@example.com',  'age' => 25, 'status' => 'active'],
    ['name' => 'Charlie',    'email' => 'charlie@example.com','age' => 42, 'status' => 'active'],
]);
echo "Batch inserted: {$count} users\n";

// ── FIND (Read) ───────────────────────────────────────────
sub('FIND - Membaca Dokumen');

// Find one
$user = $users->findOne(['name' => 'John Doe']);
echo "findOne: {$user['name']} (age: {$user['age']})\n";

// Find with criteria
$activeUsers = $users->find(['status' => 'active'])->toArray();
echo "Active users: " . count($activeUsers) . "\n";

// Count
$total = $users->count();
echo "Total users: {$total}\n";

// Projection (hanya ambil field tertentu)
$names = $users->find([], ['name' => 1, 'email' => 1])->toArray();
echo "Names only: " . implode(', ', array_column($names, 'name')) . "\n";

// ── UPDATE ────────────────────────────────────────────────
sub('UPDATE - Memperbarui Dokumen');

// Merge update (default)
$updated = $users->update(['name' => 'John Doe'], ['age' => 31, 'city' => 'Jakarta']);
echo "Merge update: {$updated} doc(s)\n";

// MongoDB-style $set dan $unset
$updated = $users->update(
    ['name' => 'John Doe'],
    ['$set' => ['status' => 'active'], '$unset' => ['city' => '']]
);
echo "\$set/\$unset update: {$updated} doc(s)\n";

// Replace update (non-merge)
$updated = $users->update(['name' => 'Bob Smith'], ['name' => 'Bobby', 'age' => 36], false);
echo "Replace update: {$updated} doc(s)\n";

// Upsert (save)
$users->save(['_id' => 'custom-id-001', 'name' => 'Upserted User', 'age' => 20]);
$upserted = $users->findOne(['_id' => 'custom-id-001']);
echo "Upsert: {$upserted['name']}\n";

// ── DELETE ────────────────────────────────────────────────
sub('DELETE - Menghapus Dokumen');

$removed = $users->remove(['name' => 'Charlie']);
echo "Removed: {$removed} doc(s)\n";

// ── PAGINATION & SORTING ──────────────────────────────────
sub('PAGINATION & SORTING');

$page = $users->find(['status' => 'active'])
    ->sort(['age' => -1])    // DESC
    ->skip(0)
    ->limit(2)
    ->toArray();

echo "Page 1 (sorted by age DESC, limit 2):\n";
foreach ($page as $u) {
    echo "  - {$u['name']}: age {$u['age']}\n";
}

// ── CLEANUP ───────────────────────────────────────────────
echo "\nTotal final: {$users->count()} users\n";
$client->close();
echo "Done!\n";
