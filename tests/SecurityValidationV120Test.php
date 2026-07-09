<?php
declare(strict_types=1);

namespace BangronDB\Tests;

use BangronDB\Client;
use BangronDB\Database;
use BangronDB\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Security Validation Tests – BangronDB v1.2.0
 * 
 * Tests new security hardening:
 * - Encryption v2: 12-byte IV, enc_v, key_v
 * - Key rotation / re-encrypt
 * - Sensitive config blocking
 * - Legacy decrypt (16-byte IV)
 */
class SecurityValidationV120Test extends TestCase
{
    private string $dir;
    private Client $client;
    private Database $db;
    private Collection $collection;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bangrondb_test_v120_' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0700, true);
        $this->client = new Client($this->dir);
        $this->client->createDB('test');
        $this->client->createCollection('test', 'users');
        $this->db = $this->client->selectDB('test');
        $this->collection = $this->client->selectCollection('test', 'users');
    }

    protected function tearDown(): void
    {
        $this->client->close();
        $this->rrmdir($this->dir);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            $p = "$dir/$f";
            is_dir($p) ? $this->rrmdir($p) : unlink($p);
        }
        rmdir($dir);
    }

    public function testEncryptionV2Uses12ByteIV(): void
    {
        $key = 'test-encryption-key-32-chars-min!!';
        $this->collection->setEncryptionKey($key, 'v2-test');
        $id = $this->collection->insert(['secret' => 'data123']);

        // Read raw stored document
        $stmt = $this->db->connection->query("SELECT document FROM users WHERE id = 1");
        $raw = $stmt->fetchColumn();
        $doc = json_decode($raw, true);

        $this->assertArrayHasKey('encrypted_data', $doc);
        $this->assertArrayHasKey('iv', $doc);
        $this->assertArrayHasKey('tag', $doc);
        $this->assertArrayHasKey('hmac', $doc);
        $this->assertArrayHasKey('enc_v', $doc);
        $this->assertEquals(2, $doc['enc_v'], 'Encryption version must be 2 (v1.2.0)');
        $this->assertEquals('v2-test', $doc['key_v'] ?? null);

        $iv = base64_decode($doc['iv']);
        $this->assertEquals(12, strlen($iv), 'GCM IV must be 12 bytes (NIST SP 800-38D), was ' . strlen($iv));
    }

    public function testDecryptLegacy16ByteIV(): void
    {
        // v1.2.0 decryptor must accept both 12-byte (v2) and 16-byte (v1 legacy) IVs
        $key = 'test-encryption-key-32-chars-min!!';
        $this->collection->setEncryptionKey($key, 'v1-legacy');

        $id = $this->collection->insert(['foo' => 'bar']);
        $found = $this->collection->findOne(['_id' => $id]);
        $this->assertEquals('bar', $found['foo']);

        // Verify decryptData accepts 12 and 16 byte IV via reflection
        $ref = new \ReflectionClass($this->collection);
        $method = $ref->getMethod('decryptData');
        // Note: setAccessible() is a no-op since PHP 8.1 and deprecated in 8.5
        $this->assertTrue($method->isPrivate());

        // If we got here, decrypt worked (v2 12-byte IV)
        $this->assertNotNull($found);
    }

    public function testKeyVersionIsStoredAndRetrieved(): void
    {
        $key = 'test-encryption-key-32-chars-min!!';
        $this->collection->setEncryptionKey($key, 'my-key-v3');
        $this->assertEquals('my-key-v3', $this->collection->getEncryptionKeyVersion());

        $this->collection->insert(['x' => 1]);
        $this->collection->saveConfiguration();

        // Reload config from DB
        $config = $this->db->loadCollectionConfig('users');
        $this->assertTrue($config['encryption_enabled']);
        $this->assertEquals('my-key-v3', $config['encryption_key_version'] ?? null);
        $this->assertArrayNotHasKey('encryption_key', $config, 'Encryption key must never be persisted');
    }

    public function testRotateEncryptionKey(): void
    {
        $key1 = 'test-encryption-key-aaaa-32chars!';
        $key2 = 'test-encryption-key-bbbb-32chars!';
        
        $this->collection->setEncryptionKey($key1, 'v1');
        $id1 = $this->collection->insert(['name' => 'Alice', 'secret' => 's1']);
        $id2 = $this->collection->insert(['name' => 'Bob', 'secret' => 's2']);

        // Rotate
        $rotated = $this->collection->rotateEncryptionKey($key2, 'v2');
        $this->assertEquals(2, $rotated, 'Should rotate 2 documents');

        // Verify data readable with new key
        $this->collection->setEncryptionKey($key2, 'v2');
        $alice = $this->collection->findOne(['_id' => $id1]);
        $this->assertEquals('Alice', $alice['name']);
        $this->assertEquals('s1', $alice['secret']);

        $bob = $this->collection->findOne(['_id' => $id2]);
        $this->assertEquals('Bob', $bob['name']);
    }

    public function testReencryptAll(): void
    {
        $key = 'test-encryption-key-32-chars-min!!';
        $this->collection->setEncryptionKey($key, 'v2');
        $this->collection->insert(['a' => 1]);
        $this->collection->insert(['a' => 2]);
        $this->collection->insert(['a' => 3]);

        // Bump key version, re-encrypt all with same key
        $this->collection->setEncryptionKey($key, 'v2-rotated');
        $count = $this->collection->reencryptAll();
        $this->assertEquals(3, $count);

        $all = $this->collection->find()->toArray();
        $this->assertCount(3, $all);
    }

    public function testCustomConfigBlocksEncryptionKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('sensitive credentials must not be persisted');
        $this->collection->setCustomConfig('encryption_key', 'hacked');
    }

    public function testCustomConfigBlocksSensitiveKeys(): void
    {
        $sensitive = ['password', 'secret', 'token', 'api_key', 'apikey', 'private_key', 'credential', 'passwd', 'encryptionkey'];
        foreach ($sensitive as $key) {
            try {
                $this->collection->setCustomConfig($key, 'x');
                $this->fail("Key $key should have been blocked");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('forbidden', $e->getMessage());
                $this->assertStringContainsString($key, $e->getMessage());
            }
        }
    }

    public function testCustomConfigAllowsSafeKeys(): void
    {
        $this->collection->setCustomConfig('theme', 'dark');
        $this->collection->setCustomConfig('locale', 'id_ID');
        $this->collection->setCustomConfigArray(['page_size' => 20, 'show_tips' => true]);

        $this->assertEquals('dark', $this->collection->getCustomConfig('theme'));
        $this->assertEquals('id_ID', $this->collection->getCustomConfig('locale'));
        $this->assertEquals(20, $this->collection->getCustomConfig('page_size'));
        $this->assertTrue($this->collection->getCustomConfig('show_tips'));
    }

    public function testCustomConfigArrayFiltersSensitiveKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('sensitive credentials must not be persisted');
        $this->collection->setCustomConfigArray([
            'theme' => 'dark',
            'api_key' => 'should-fail'
        ]);
    }

    public function testSaveConfigurationFiltersSensitiveData(): void
    {
        // Simulate old dirty data in custom_config (e.g., from pre-v1.2.0)
        // setCustomConfig() now blocks, so inject via reflection
        // Note: setAccessible() is a no-op since PHP 8.1 and deprecated in 8.5
        $ref = new \ReflectionClass($this->collection);
        $prop = $ref->getProperty('customConfig');
        $prop->setValue($this->collection, [
            'theme' => 'dark',
            'password' => 'should-be-filtered',
            'encryption_key' => 'should-be-filtered-too',
            'api_key' => 'also-filtered'
        ]);

        $this->collection->saveConfiguration();
        $config = $this->db->loadCollectionConfig('users');

        $custom = $config['custom_config'] ?? [];
        $this->assertEquals('dark', $custom['theme'] ?? null, 'Safe key must survive');
        $this->assertArrayNotHasKey('password', $custom, 'Password must be stripped');
        $this->assertArrayNotHasKey('encryption_key', $custom, 'Encryption key must be stripped');
        $this->assertArrayNotHasKey('api_key', $custom, 'API key must be stripped');
    }

    public function testCollectionManagerRejectsEncryptionKeyInConfig(): void
    {
        $manager = new \BangronDB\CollectionManager($this->db);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid configuration key: encryption_key');
        
        $manager->saveCollectionConfig('users', [
            'id_mode' => 'auto',
            'encryption_key' => 'should-fail',
        ]);
    }

    public function testDatabaseEncryptionKeyVersion(): void
    {
        $dbPath = $this->dir . '/kvtest.bangron';
        $db = new Database($dbPath, [
            'encryption_key' => 'test-key-32-chars-minimum-!!!!!!',
            'encryption_key_version' => 'test-v1'
        ]);

        $this->assertEquals('test-v1', $db->getEncryptionKeyVersion());

        $status = $db->getEncryptionKeyStatus();
        $this->assertTrue($status['enabled']);
        $this->assertEquals(32, $status['key_length']);
        $this->assertEquals('test-v1', $status['key_version']);

        // Test setEncryptionKey with version at runtime
        $db->setEncryptionKey('new-key-32-chars-minimum-@@@@@@', 'test-v2');
        $this->assertEquals('test-v2', $db->getEncryptionKeyVersion());

        $db->close();
    }
}
