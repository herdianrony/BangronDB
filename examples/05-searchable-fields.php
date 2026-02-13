<?php

/**
 * Contoh 05: Searchable Fields.
 *
 * Demonstrasi searchable fields dengan hashing untuk privasi
 * dan case-insensitive search.
 */

require_once __DIR__ . '/bootstrap.php';

echo "=== Contoh 05: Searchable Fields ===\n\n";

// Buat client dengan database isolated
$client = createIsolatedClient('searchable_demo', ['query_logging' => true]);
$db = $client->selectDB('app');
$users = $db->users;

echo "1. Searchable fields dengan hashing (privasi)\n";
echo "----------------------------------------------\n";

// Hash email untuk privasi - tidak bisa lookup dengan email asli
$users->setSearchableFields(['email'], true);

$userId = $users->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '+1234567890',
]);

echo "Inserted user dengan hashed searchable email\n";

// Query dengan email asli - akan di-hash otomatis
$user = $users->findOne(['email' => 'john@example.com']);
echo "Found by email:\n";
print_r($user);

echo "\n2. Multiple searchable fields\n";
echo "------------------------------\n";

$users->setSearchableFields(['email', 'phone', 'name'], false);

$users->insert([
    'name' => 'Jane Smith',
    'email' => 'jane@test.com',
    'phone' => '+0987654321',
]);

// Search pada searchable fields
$results = $users->find(['email' => 'jane@test.com'])->toArray();
echo "Found by email:\n";
print_r($results);

$results = $users->find(['phone' => '+0987654321'])->toArray();
echo "Found by phone:\n";
print_r($results);

echo "\n3. Case-insensitive search\n";
echo "---------------------------\n";

$users->setSearchableFields(['name'], false);

$users->insert([
    'name' => 'ALICE JOHNSON',
    'email' => 'alice@example.com',
]);

echo "Raw table content for users:\n";
$stmt = $db->connection->query("SELECT * FROM users");
print_r($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : "Table not found\n");

// Search case-insensitive
$results = $users->find(['name' => 'alice johnson'])->toArray();
echo "Search 'alice johnson' (case-insensitive):\n";
print_r($results);

echo "Query Log for Test 3:\n";
print_r($db->queryExecutor->getQueryLog());
$db->queryExecutor->clearLogs();

echo "\n4. Selective hashing\n";
echo "--------------------\n";

// Hash email, tapi simpan name sebagai plain text
$users->setSearchableFields([
    'email' => ['hash' => true],
    'name' => ['hash' => false],
]);

$users->insert([
    'name' => 'Bob Wilson',
    'email' => 'bob@example.com',
]);

echo "Search name (plain text):\n";
$results = $users->find(['name' => 'Bob Wilson'])->toArray();
print_r($results);

echo "\nSearch email (hashed):\n";
$results = $users->find(['email' => 'bob@example.com'])->toArray();
print_r($results);

echo "\n=== Cleanup ===\n";
@$db->drop();
$client->close();
echo "Database dibersihkan.\n";
