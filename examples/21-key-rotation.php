<?php
/**
 * Example 16: Encryption Key Rotation – BangronDB v1.2.0
 * 
 * Demonstrates:
 * - Encryption v2: AES-256-GCM with 12-byte IV (NIST SP 800-38D)
 * - Key versioning (enc_v, key_v)
 * - rotateEncryptionKey()
 * - reencryptAll()
 * - Sensitive config blocking
 * - Legacy decrypt (IV 16-byte) backward compatibility
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sub('BangronDB v1.2.0 – Key Rotation Demo');

$examplePath = __DIR__ . '/data_example_16';
@mkdir($examplePath, 0700, true);

// v1.2.0: Always load encryption key from ENV / vault, never hardcode in production
$keyV1 = $_ENV['DB_ENCRYPTION_KEY'] ?? 'test-key-v1-32-chars-minimum-!!!!';
$keyV2 = $_ENV['DB_ENCRYPTION_KEY_V2'] ?? 'test-key-v2-32-chars-minimum-@@@@';
$keyVersion1 = $_ENV['DB_ENCRYPTION_KEY_VERSION'] ?? 'v2-2026-06';
$keyVersion2 = 'v3-2026-12';

$client = new Client($examplePath, [
    'encryption_key' => $keyV1,
    'encryption_key_version' => $keyVersion1,
]);

$client->createDB('secure_app');
$client->createCollection('secure_app', 'users');
$users = $client->selectCollection('secure_app', 'users');

// Enable encryption with key version – v1.2.0
$users->setEncryptionKey($keyV1, $keyVersion1);
$users->setSearchableFields(['email' => ['hash' => true]]);

sub('1. Insert encrypted documents – Encryption v2');
$id1 = $users->insert(['name' => 'Alice', 'email' => 'alice@example.com', 'ssn' => '123-45-6789']);
$id2 = $users->insert(['name' => 'Bob',   'email' => 'bob@example.com',   'ssn' => '987-65-4321']);
p("Inserted 2 users with encryption_key_version = $keyVersion1");

// Inspect raw stored document – see enc_v / key_v / IV length
$db = $client->selectDB('secure_app');
$stmt = $db->connection->query("SELECT document FROM users LIMIT 1");
$raw = $stmt->fetchColumn();
$decoded = json_decode($raw, true);
p("Encrypted document format – BangronDB v1.2.0:");
p([
    'enc_v' => $decoded['enc_v'] ?? 'missing (v1 legacy)',
    'key_v' => $decoded['key_v'] ?? null,
    'iv_base64_len' => strlen($decoded['iv'] ?? ''),
    'iv_bytes' => strlen(base64_decode($decoded['iv'] ?? '')) . ' bytes',
    'iv_standard' => 'GCM standard = 12 bytes (NIST SP 800-38D)',
    'has_hmac' => isset($decoded['hmac']) ? 'yes' : 'no',
]);

sub('2. Read – decrypt transparently (AES-256-GCM + HMAC verify)');
$user = $users->findOne(['email' => 'alice@example.com']);
p("Found user via searchable blind index:");
p($user);

sub('3. Key Rotation – rotateEncryptionKey() – v1.2.0');
p("Rotating encryption key:");
p("  From: key_version = $keyVersion1");
p("  To:   key_version = $keyVersion2");
$rotated = $users->rotateEncryptionKey($keyV2, $keyVersion2);
p("Result: $rotated documents rotated successfully");

// Verify read still works with new key
$users->setEncryptionKey($keyV2, $keyVersion2);
$user = $users->findOne(['email' => 'bob@example.com']);
p("After rotation – decrypt with new key – OK:");
p($user);

sub('4. reencryptAll() – bump key version without changing key material');
$users->setEncryptionKey($keyV2, 'v3-2027-01');
$reencrypted = $users->reencryptAll();
p("Re-encrypted documents: $reencrypted");
p("Use case: key version bump, metadata refresh, post-migration cleanup");

sub('5. Sensitive config blocking – v1.2.0 security hardening');
p("Trying to save encryption_key via setCustomConfig() …");
try {
    $users->setCustomConfig('encryption_key', 'hacked!!!');
    p("ERROR: should have thrown InvalidArgumentException!");
} catch (InvalidArgumentException $e) {
    p("✓ Blocked correctly:");
    p("  " . $e->getMessage());
}

p("\nTrying api_key …");
try {
    $users->setCustomConfigArray(['api_key' => 'secret', 'theme' => 'dark']);
    p("ERROR: should have thrown!");
} catch (InvalidArgumentException $e) {
    p("✓ Blocked api_key correctly");
}

// Valid custom config still works
$users->setCustomConfig('theme', 'dark');
$users->setCustomConfig('locale', 'id_ID');
$users->saveConfiguration();
p("\n✓ Safe custom_config saved successfully:");
p($users->getAllCustomConfig());

sub('6. Legacy decrypt – v1.0 IV 16-byte still readable – backward compatible');
p("EncryptionTrait v1.2.0 decryptData() accepts:");
p("- v2 current: IV 12-byte, enc_v = 2, key_v set");
p("- v1 legacy: IV 16-byte, enc_v missing, no key_v");
p("Tested in: tests/SecurityValidationV120Test.php – testDecryptLegacy16ByteIV()");

sub('Summary – BangronDB v1.2.0 Security Features');
p([
    'encryption' => 'AES-256-GCM, PBKDF2-SHA256 100k iter',
    'iv' => '12-byte random (NIST), legacy 16-byte still decryptable',
    'integrity' => 'GCM auth tag + HMAC-SHA256, hash_equals()',
    'key_versioning' => 'enc_v + key_v stored per document',
    'rotation' => 'rotateEncryptionKey() / reencryptAll()',
    'blind_index' => 'HMAC-SHA256 keyed, anti rainbow-table',
    'config_security' => 'setCustomConfig() blocks: encryption_key, password, secret, token, api_key, private_key, credential',
]);

sub('Done – Key Rotation Demo v1.2.0');
$client->close();

// Cleanup hint:
// rm -rf examples/data_example_16
