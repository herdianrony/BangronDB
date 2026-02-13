<?php

/**
 * Contoh 03: Schema Validation.
 *
 * Demonstrasi validasi schema untuk integritas data.
 */

require_once __DIR__.'/bootstrap.php';

echo "=== Contoh 03: Schema Validation ===\n\n";

// Buat client dengan database isolated
$client = createIsolatedClient('schema_demo');
$db = $client->selectDB('app');
$users = $db->users;

// Set schema validation
$users->setSchema([
    'name' => [
        'type' => 'string',
        'required' => true,
        'min' => 2,
        'max' => 100,
    ],
    'email' => [
        'type' => 'string',
        'required' => true,
        'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/',
    ],
    'age' => [
        'type' => 'int',
        'min' => 0,
        'max' => 150,
    ],
    'role' => [
        'type' => 'string',
        'enum' => ['admin', 'user', 'guest', 'moderator'],
        'required' => true,
    ],
    'phone' => [
        'type' => 'string',
        'regex' => '/^\+?[0-9]{10,15}$/',
    ],
    'tags' => [
        'type' => 'array',
        'max' => 10,
    ],
    'is_active' => [
        'type' => 'bool',
    ],
]);

// Save configuration to database so schema persists
$users->saveConfiguration();

echo "1. Insert valid\n";
echo "----------------\n";

try {
    $userId = $users->insert([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30,
        'role' => 'admin',
        'phone' => '+1234567890',
        'tags' => ['vip', 'premium'],
        'is_active' => true,
    ]);
    echo "User created: $userId\n";
} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}

echo "\n2. Validasi - Missing required field\n";
echo "--------------------------------------\n";

try {
    $users->insert([
        'name' => 'Jane',
        // Missing email dan role
    ]);
} catch (Exception $e) {
    echo 'Validation Error: '.$e->getMessage()."\n";
}

echo "\n3. Validasi - Invalid email format\n";
echo "------------------------------------\n";

try {
    $users->insert([
        'name' => 'Bob',
        'email' => 'invalid-email',
        'role' => 'user',
    ]);
} catch (Exception $e) {
    echo 'Validation Error: '.$e->getMessage()."\n";
}

echo "\n4. Validasi - Enum invalid\n";
echo "---------------------------\n";

try {
    $users->insert([
        'name' => 'Alice',
        'email' => 'alice@test.com',
        'role' => 'superuser', // Invalid enum
    ]);
} catch (Exception $e) {
    echo 'Validation Error: '.$e->getMessage()."\n";
}

echo "\n5. Validasi - Range out of bounds\n";
echo "-----------------------------------\n";

try {
    $users->insert([
        'name' => 'Too Old',
        'email' => 'old@test.com',
        'age' => 200, // Di atas max
        'role' => 'user',
    ]);
} catch (Exception $e) {
    echo 'Validation Error: '.$e->getMessage()."\n";
}

echo "\n6. Validasi - Array max items\n";
echo "------------------------------\n";

try {
    $users->insert([
        'name' => 'Many Tags',
        'email' => 'tags@test.com',
        'role' => 'user',
        'tags' => ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l'], // 12 items > max 10
    ]);
} catch (Exception $e) {
    echo 'Validation Error: '.$e->getMessage()."\n";
}

echo "\n7. Validasi - Regex phone\n";
echo "--------------------------\n";

try {
    $users->insert([
        'name' => 'Bad Phone',
        'email' => 'phone@test.com',
        'role' => 'user',
        'phone' => 'abc-def-ghij', // Invalid format
    ]);
} catch (Exception $e) {
    echo 'Validation Error: '.$e->getMessage()."\n";
}

echo "\n8. Validasi pada Update (replace)\n";
echo "------------------------------------\n";

try {
    // Full replace memerlukan validasi
    $users->update(
        ['name' => 'John Doe'],
        [
            'name' => 'John Updated',
            'email' => 'john@new.com',
            'role' => 'user',
        ],
        false // merge = false, jadi full replace
    );
    echo "Document replaced successfully\n";
} catch (Exception $e) {
    echo 'Validation Error: '.$e->getMessage()."\n";
}

echo "\n9. Partial Update (\$set) TIDAK divalidasi\n";
echo "-----------------------------------------\n";

// Partial update dengan $set tidak divalidasi (performa)
$users->update(
    ['name' => 'John Doe'],
    ['$set' => ['age' => 25]] // Tidak akan trigger validasi
);
$user = $users->findOne(['name' => 'John Updated']);
echo "Partial update berhasil:\n";
print_r($user);

echo "\n=== Cleanup ===\n";
@$db->drop();
$client->close();
echo "Database dibersihkan.\n";
