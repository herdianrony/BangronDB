<?php

/**
 * Contoh 02: Enkripsi Data.
 *
 * Demonstrasi enkripsi per-collection dengan AES-256-CBC
 * untuk data sensitif.
 */

require_once __DIR__ . '/bootstrap.php';

echo "=== Contoh 02: Enkripsi Data ===\n\n";

// Buat client dengan database isolated
$client = createIsolatedClient('encryption_demo');
$db = $client->selectDB('app');
$users = $db->users;

// Set encryption key (must be at least 32 characters)
$encryptionKey = 'this-is-a-32-char-secret-key-too-short-no-more!';
$users->setEncryptionKey($encryptionKey);

echo "1. Insert data terenkripsi\n";
echo "---------------------------\n";

$userId = $users->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'ssn' => '123-45-6789',       // Akan dienkripsi
    'credit_card' => '4111111111111111', // Akan dienkripsi
    'password_hash' => password_hash('secret123', PASSWORD_DEFAULT),
]);

echo "User inserted dengan ID: $userId\n\n";

echo "2. Verifikasi enkripsi di database\n";
echo "-----------------------------------\n";

// Langsung query ke database untuk lihat data terenkripsi
$stmt = $db->connection->query('SELECT document FROM users WHERE id = 1');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Data terenkripsi di database:\n";
echo $row['document'] . "\n\n";

// Decode manual (tanpa decryption) untuk lihat format
$decoded = json_decode($row['document'], true);
echo 'Field SSN di database (terenkripsi): ' .
    (isset($decoded['ssn']) ? substr($decoded['ssn'], 0, 20) . '...' : 'N/A') . "\n\n";

echo "3. Read dengan auto-decryption\n";
echo "-------------------------------\n";

$user = $users->findOne(['_id' => $userId]);
echo "Data setelah di-decrypt otomatis:\n";
print_r($user);

echo "\n4. Update data terenkripsi\n";
echo "---------------------------\n";

$users->update(
    ['_id' => $userId],
    [
        '$set' => [
            'ssn' => '987-65-4321',
            'credit_card' => '5500000000000004',
        ],
    ]
);

$user = $users->findOne(['_id' => $userId]);
echo "Setelah update:\n";
print_r($user);

echo "\n5. Multiple collections dengan encryption berbeda\n";
echo "--------------------------------------------------\n";

$orders = $db->orders;
$orders->setEncryptionKey('order-encryption-key-at-least-32-chars!!!');

$orders->insert([
    'user_id' => $userId,
    'total' => 150.00,
    'card_last4' => '0004',
    'encrypted_payment_data' => 'sensitive-payment-info',
]);

echo "Order inserted dengan encryption berbeda:\n";
$order = $orders->findOne(['user_id' => $userId]);
print_r($order);

echo "\n=== Cleanup ===\n";
@$db->drop();
$client->close();
echo "Database dibersihkan.\n";
