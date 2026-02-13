<?php

/**
 * Contoh 15: Encryption dengan Key dari .env.
 *
 * Demonstrasi enkripsi dengan key dari environment variable,
 * bukan dari database.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

echo "=== Contoh 15: Encryption dengan .env ===\n\n";

// Simulasi: Load dari .env (dalam aplikasi nyata gunakan vlucas/phpdotenv)
$_ENV['DB_ENCRYPTION_KEY'] = 'ini-key-rahasia-dari-env-32char!';

// Setup
$path = __DIR__ . '/data/encryption_demo_env';
if (!is_dir($path)) {
    mkdir($path, 0755, true);
}

// Key dari .env - TIDAK disimpan di database
$encryptionKey = $_ENV['DB_ENCRYPTION_KEY'] ?? null;

$client = new Client($path, ['encryption_key' => $encryptionKey]);
$db = $client->selectDB('app');
$users = $db->users;

echo "1. Insert Data Terenkripsi\n";
echo "---------------------------\n";

$userId = $users->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'ssn' => '123-45-6789',
    'credit_card' => '4111111111111111',
]);
echo "User inserted dengan ID: $userId\n\n";

echo "2. Verifikasi Enkripsi\n";
echo "----------------------\n";

// Lihat data di database (terenkripsi)
$stmt = $db->connection->query('SELECT document FROM users WHERE id = 1');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Data terenkripsi di database:\n";
echo substr($row['document'], 0, 100) . "...\n\n";

echo "3. Read Data (Auto-Decrypt)\n";
echo "----------------------------\n";

$user = $users->findOne(['_id' => $userId]);
echo "Data setelah di-decrypt:\n";
print_r($user);

echo "\n4. Reconnect dengan Key Salah\n";
echo "-----------------------------\n";

$client->close();

// Coba reconnect dengan key SALAH
$client2 = new Client($path); // Tanpa key
$db2 = $client2->selectDB('app');
$users2 = $db2->users;

$user2 = $users2->findOne(['_id' => $userId]);
echo "Dengan key salah/null:\n";
print_r($user2); // Akan null karena tidak bisa decrypt

echo "\n5. Reconnect dengan Key BENAR\n";
echo "-----------------------------\n";

$client3 = new Client($path, ['encryption_key' => $encryptionKey]);
$db3 = $client3->selectDB('app');
$users3 = $db3->users;

$user3 = $users3->findOne(['_id' => $userId]);
echo "Dengan key benar:\n";
print_r($user3);

echo "\n=== Catatan ===\n";
echo "- Key disimpan di .env (di luar database)\n";
echo "- Database TIDAK menyimpan key\n";
echo "- Tanpa key yang benar, data tidak bisa dibaca\n";
echo "- Sangat aman untuk production!\n";

echo "\n=== Cleanup ===\n";
@$db3->drop();
@$client3->close();
echo "Database dibersihkan.\n";
