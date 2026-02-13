<?php

/**
 * Contoh 01: Operasi CRUD Dasar.
 *
 * Demonstrasi operasi Create, Read, Update, Delete
 * dengan database :memory: untuk isolasi.
 */
require_once __DIR__ . '/bootstrap.php';
echo "=== Contoh 01: Operasi CRUD Dasar ===\n\n";

use BangronDB\Client;

// Gunakan direktori data lokal untuk contoh
$client = new Client(__DIR__ . '/data');
$db = $client->selectDB('app');
$users = $db->users;

echo "1. INSERT - Menambahkan dokumen\n";
echo "-------------------------------\n";

// Insert single document
$userId = $users->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
    'status' => 'active',
    'created_at' => date('c'),
]);
echo "User inserted dengan ID: $userId\n\n";

// Insert multiple documents
$count = $users->insert([
    [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'age' => 28,
        'status' => 'active',
    ],
    [
        'name' => 'Bob Smith',
        'email' => 'bob@example.com',
        'age' => 35,
        'status' => 'inactive',
    ],
    [
        'name' => 'Alice Johnson',
        'email' => 'alice@example.com',
        'age' => 25,
        'status' => 'active',
    ],
]);
echo "Inserted $count users\n\n";

echo "2. READ - Membaca dokumen\n";
echo "--------------------------\n";

// Find one
$user = $users->findOne(['name' => 'John Doe']);
echo "Find one by name:\n";
print_r($user);

// Find all active users
echo "\nFind all active users:\n";
$activeUsers = $users->find(['status' => 'active'])->toArray();
print_r($activeUsers);

// Count documents
$totalUsers = $users->count();
echo "\nTotal users: $totalUsers\n";

echo "\n3. UPDATE - Memperbarui dokumen\n";
echo "--------------------------------\n";

// Update single field
$updated = $users->update(
    ['name' => 'John Doe'],
    ['$set' => ['age' => 31, 'updated_at' => date('c')]]
);
echo "Updated $updated document(s)\n";

// Update multiple documents
$updatedAll = $users->update(
    ['status' => 'inactive'],
    ['$set' => ['status' => 'archived']]
);
echo "Updated $updatedAll inactive users to archived\n";

echo "\n4. DELETE - Menghapus dokumen\n";
echo "-------------------------------\n";

// Remove single document
$removed = $users->remove(['name' => 'Bob Smith']);
echo "Removed $removed document(s)\n";

// Final state
$finalUsers = $users->find()->toArray();
echo "\nFinal state:\n";
print_r($finalUsers);

// Cleanup
echo "\n=== Selesai ===\n";
echo "Database di folder data akan ditutup.\n";
$client->close();
