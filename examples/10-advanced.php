<?php

/**
 * Contoh 10: Advanced - Full Features Combined.
 *
 * Demonstrasi kombinasi semua fitur: encryption, validation,
 * hooks, soft deletes, searchable fields, dan indexing.
 */

require_once __DIR__ . '/bootstrap.php';

echo "=== Contoh 10: Advanced - Full Features Combined ===\n\n";

// Buat client dengan database isolated
$client = createIsolatedClient('advanced_demo');
$db = $client->selectDB('app');
$users = $db->users;

// Setup full features
echo "1. Setup collection dengan semua fitur\n";
echo "--------------------------------------\n";

// Encryption
$users->setEncryptionKey('super-secure-key-at-least-32-chars-long!!');

// Searchable fields (hashed untuk email)
$users->setSearchableFields(['email'], true);

// Schema validation
$users->setSchema([
    'name' => ['type' => 'string', 'required' => true, 'min' => 2],
    'email' => ['type' => 'string', 'required' => true, 'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
    'password' => ['type' => 'string', 'required' => true, 'min' => 8],
    'age' => ['type' => 'int', 'min' => 13, 'max' => 150],
    'role' => ['enum' => ['admin', 'user', 'guest']],
    'is_active' => ['type' => 'bool'],
]);

// Soft deletes
$users->useSoftDeletes(true);

// Hooks
$users->on('beforeInsert', function ($doc) {
    $doc['created_at'] = date('c');
    $doc['is_active'] = $doc['is_active'] ?? true;

    return $doc;
});

$users->on('beforeUpdate', function ($criteria, $data) {
    if (!isset($data['$set'])) {
        $data['$set'] = [];
    }
    $data['$set']['updated_at'] = date('c');

    return [$criteria, $data];
});

// Save configuration to database so all settings persist
$users->saveConfiguration();

echo "Features configured:\n";
echo "- Encryption: enabled\n";
echo "- Searchable fields: email (hashed)\n";
echo "- Schema validation: enabled\n";
echo "- Soft deletes: enabled\n";
echo "- Hooks: beforeInsert, beforeUpdate\n\n";

echo "2. Insert dengan semua validasi\n";
echo "-------------------------------\n";

try {
    $user1 = $users->insert([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'secure123',
        'age' => 30,
        'role' => 'admin',
    ]);
    echo "User 1 created: $user1\n";

    $user2 = $users->insert([
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
        'password' => 'secure456',
        'age' => 25,
        'role' => 'user',
    ]);
    echo "User 2 created: $user2\n";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}

echo "\n3. Update dengan hooks\n";
echo "------------------------\n";

try {
    $users->update(
        ['name' => 'John Doe'],
        ['$set' => ['age' => 31]]
    );
    echo "Updated user 1\n";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}

$user = $users->findOne(['name' => 'John Doe']);
echo "Updated user:\n";
print_r($user);

echo "\n4. Create indexes\n";
echo "-----------------\n";

$users->createIndex('email', 'idx_users_email');
$users->createIndex('role', 'idx_users_role');
$users->createIndex('created_at', 'idx_users_created_at');
echo "Indexes created for email, role, and created_at fields\n";

echo "\n5. Metrics dan monitoring\n";
echo "------------------------\n";

$metrics = $db->getHealthMetrics();
echo 'Database status: ' . ($metrics['status'] ?? 'unknown') . "\n";
echo 'Total documents: ' . $users->count() . "\n";

echo "\n=== Cleanup ===\n";
@$db->drop();
$client->close();
echo "Database dibersihkan.\n";
