<?php

/**
 * Contoh 03: Enkripsi & Searchable Fields
 *
 * Enkripsi AES-256-CBC per collection, searchable fields
 * dengan hashing, dan enkripsi dengan key dari .env.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sep('Contoh 03: Enkripsi & Searchable Fields');

// ═══════════════════════════════════════════════════════════
// BAGIAN 1: Enkripsi Dasar
// ═══════════════════════════════════════════════════════════

sub('BAGIAN 1: Enkripsi Per-Collection');

$examplePath = __DIR__ . '/data/example03_' . uniqid();
@mkdir($examplePath, 0755, true);

$client = new Client($examplePath);
$db = $client->createDB('secure_app');
$patients = $db->createCollection('patients');

// Set encryption key (min 32 karakter)
$encKey = $_ENV['DB_ENCRYPTION_KEY'] ?? 'this-is-a-32-char-secret-key-secure!!'; // v1.2.0: use $_ENV in production
$patients->setEncryptionKey($encKey, $_ENV['DB_ENCRYPTION_KEY_VERSION'] ?? 'v2-2026');

// Insert data sensitif
$patientId = $patients->insert([
    'name'        => 'John Patient',
    'email'       => 'john@hospital.com',
    'ssn'         => '123-45-6789',
    'diagnosis'   => 'Hypertension Stage 2',
    'credit_card' => '4111111111111111',
]);
echo "Patient inserted: {$patientId}\n";

// Verifikasi: data di database terenkripsi
$stmt = $db->connection->query("SELECT document FROM patients WHERE id = 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$raw = json_decode($row['document'], true);
echo "SSN di DB (terenkripsi): " . substr($raw['ssn'] ?? 'N/A', 0, 20) . "...\n";

// Read auto-decrypt
$patient = $patients->findOne(['_id' => $patientId]);
echo "SSN setelah decrypt: {$patient['ssn']}\n";
echo "Diagnosis: {$patient['diagnosis']}\n";

// Update data terenkripsi
$patients->update(['_id' => $patientId], ['$set' => ['ssn' => '987-65-4321']]);
$updated = $patients->findOne(['_id' => $patientId]);
echo "SSN setelah update: {$updated['ssn']}\n";

// ═══════════════════════════════════════════════════════════
// BAGIAN 2: Searchable Fields
// ═══════════════════════════════════════════════════════════

sub('BAGIAN 2: Searchable Fields dengan Hashing');

$users = $db->createCollection('users');

// Set searchable fields + hashing untuk email
$users->setSearchableFields(['email'], true); // true = SHA-256 hash
$users->setEncryptionKey($encKey, $_ENV['DB_ENCRYPTION_KEY_VERSION'] ?? 'v2-2026');

$users->insert([
    ['name' => 'Alice',   'email' => 'alice@example.com',   'role' => 'admin'],
    ['name' => 'Bob',     'email' => 'bob@example.com',     'role' => 'user'],
    ['name' => 'Charlie', 'email' => 'charlie@example.com', 'role' => 'user'],
]);

// Query email → otomatis di-hash untuk pencarian
$found = $users->findOne(['email' => 'bob@example.com']);
echo "Found by hashed email: {$found['name']}\n";

// Searchable fields TANPA hashing (plain text)
$users->setSearchableFields(['email' => ['hash' => true], 'name' => ['hash' => false]]);

$users->insert(['name' => 'Diana', 'email' => 'diana@example.com', 'role' => 'editor']);

// Cari by name (plain text)
$byName = $users->find(['name' => 'Diana'])->toArray();
echo "Found by plain name: " . count($byName) . " result(s)\n";

// ═══════════════════════════════════════════════════════════
// BAGIAN 3: Enkripsi dengan Key dari Environment
// ═══════════════════════════════════════════════════════════

sub('BAGIAN 3: Enkripsi dengan Key dari .env');

// Simulasi key dari .env (di production: $_ENV['DB_ENCRYPTION_KEY'])
$envKey = $_ENV['DB_ENCRYPTION_KEY'] ?? 'env-secret-key-at-least-32-chars!!!'; // v1.2.0: use $_ENV

$secretDb = $client->createDB('secrets');
$vault = $secretDb->createCollection('vault');
$vault->setEncryptionKey($envKey, $_ENV['DB_ENCRYPTION_KEY_VERSION'] ?? 'v2-2026');

$vaultId = $vault->insert([
    'label'     => 'API Key Production',
    'api_key'   => 'sk-prod-abc123xyz456',
    'api_secret'=> 'super-secret-token-here',
]);
echo "Secret inserted: {$vaultId}\n";

// Baca dengan key yang benar
$secret = $vault->findOne(['_id' => $vaultId]);
echo "API Key: {$secret['api_key']}\n";

// Reconnect TANPA key → data tidak bisa dibaca
$client->close();
$client2 = new Client($examplePath);
$db2 = $client2->selectDB('secrets');
$vault2 = $db2->selectCollection('vault');
$unreadable = $vault2->findOne(['_id' => $vaultId]);
echo "Tanpa key: " . ($unreadable === null ? 'null (tidak bisa decrypt)' : 'terbaca') . "\n";

// Reconnect DENGAN key → data bisa dibaca
$client2->close();
$client3 = new Client($examplePath, ['encryption_key' => $envKey, 'encryption_key_version' => $_ENV['DB_ENCRYPTION_KEY_VERSION'] ?? 'v2-2026']);  // v1.2.0
$db3 = $client3->selectDB('secrets');
$vault3 = $db3->selectCollection('vault');
$readable = $vault3->findOne(['_id' => $vaultId]);
echo "Dengan key: API Key = {$readable['api_key']}\n";

echo "\nPENTING: Encryption key TIDAK disimpan di database!\n";
echo "Selalu sediakan key dari .env atau secret manager.\n";

@$client3->close();
echo "\nDone!\n";
