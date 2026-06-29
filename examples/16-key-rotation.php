<?php
/**
 * Example 16: Encryption Key Rotation – BangronDB v1.1.0
 */
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
use BangronDB\Client;

sub('BangronDB v1.1.0 – Key Rotation Demo');
$examplePath = __DIR__ . '/data_example_16';
@mkdir($examplePath, 0700, true);

$keyV1 = $_ENV['DB_ENCRYPTION_KEY'] ?? 'test-key-v1-32-chars-minimum-!!!!';
$keyV2 = $_ENV['DB_ENCRYPTION_KEY_V2'] ?? 'test-key-v2-32-chars-minimum-@@@@';
$keyVersion1 = 'v2-2026-06';
$keyVersion2 = 'v3-2026-12';

$client = new Client($examplePath, ['encryption_key' => $keyV1, 'encryption_key_version' => $keyVersion1]);
$client->createDB('secure_app');
$client->createCollection('secure_app', 'users');
$users = $client->selectCollection('secure_app', 'users');
$users->setEncryptionKey($keyV1, $keyVersion1);
$users->setSearchableFields(['email' => ['hash' => true]]);

sub('1. Insert encrypted documents – v2');
$id1 = $users->insert(['name' => 'Alice', 'email' => 'alice@example.com']);
$id2 = $users->insert(['name' => 'Bob', 'email' => 'bob@example.com']);
p("Inserted 2 users with key_version = $keyVersion1");

$db = $client->selectDB('secure_app');
$stmt = $db->connection->query("SELECT document FROM users LIMIT 1");
$decoded = json_decode($stmt->fetchColumn(), true);
p(['enc_v' => $decoded['enc_v'] ?? null, 'key_v' => $decoded['key_v'] ?? null, 'iv_bytes' => strlen(base64_decode($decoded['iv'] ?? ''))]);

sub('2. Read – decrypt transparently');
$user = $users->findOne(['email' => 'alice@example.com']);
p($user);

sub('3. Key Rotation');
$rotated = $users->rotateEncryptionKey($keyV2, $keyVersion2);
p("Rotated documents: $rotated");
$users->setEncryptionKey($keyV2, $keyVersion2);
$user = $users->findOne(['email' => 'bob@example.com']);
p($user);

sub('4. reencryptAll()');
$users->setEncryptionKey($keyV2, 'v3-2027-01');
$count = $users->reencryptAll();
p("Re-encrypted: $count");

sub('5. Sensitive config blocking v1.1.0');
try { $users->setCustomConfig('encryption_key', 'hacked'); p('ERROR'); }
catch (InvalidArgumentException $e) { p("✓ Blocked: " . $e->getMessage()); }

$users->setCustomConfig('theme', 'dark');
$users->saveConfiguration();
p("✓ Safe custom_config saved");

sub('Done – Key Rotation v1.1.0');
$client->close();
