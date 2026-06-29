<?php
declare(strict_types=1);
namespace BangronDB\Tests;
use BangronDB\Client;
use BangronDB\Database;
use BangronDB\Collection;
use PHPUnit\Framework\TestCase;

class SecurityValidationTest_v110 extends TestCase
{
    private string $dir;
    private Client $client;
    private Database $db;
    private Collection $collection;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bangrondb_test_v110_' . bin2hex(random_bytes(4));
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
        foreach (scandir($dir) as $f) { if ($f==='.'||$f==='..') continue; $p="$dir/$f"; is_dir($p)?$this->rrmdir($p):unlink($p); }
        rmdir($dir);
    }

    public function testEncryptionV2Uses12ByteIV(): void
    {
        $key='test-encryption-key-32-chars-min!!';
        $this->collection->setEncryptionKey($key,'v2-test');
        $id=$this->collection->insert(['secret'=>'data123']);
        $stmt=$this->db->connection->query("SELECT document FROM users WHERE id = 1");
        $doc=json_decode($stmt->fetchColumn(), true);
        $this->assertEquals(2, $doc['enc_v'] ?? 0);
        $this->assertEquals('v2-test', $doc['key_v'] ?? null);
        $iv=base64_decode($doc['iv']);
        $this->assertEquals(12, strlen($iv));
    }
    public function testKeyVersionIsStoredAndRetrieved(): void
    {
        $key='test-encryption-key-32-chars-min!!';
        $this->collection->setEncryptionKey($key,'my-key-v3');
        $this->assertEquals('my-key-v3', $this->collection->getEncryptionKeyVersion());
        $this->collection->insert(['x'=>1]);
        $this->collection->saveConfiguration();
        $config=$this->db->loadCollectionConfig('users');
        $this->assertTrue($config['encryption_enabled']);
        $this->assertEquals('my-key-v3', $config['encryption_key_version'] ?? null);
    }
    public function testRotateEncryptionKey(): void
    {
        $k1='test-encryption-key-aaaa-32chars!';
        $k2='test-encryption-key-bbbb-32chars!';
        $this->collection->setEncryptionKey($k1,'v1');
        $id1=$this->collection->insert(['name'=>'Alice']);
        $id2=$this->collection->insert(['name'=>'Bob']);
        $rotated=$this->collection->rotateEncryptionKey($k2,'v2');
        $this->assertEquals(2, $rotated);
        $this->collection->setEncryptionKey($k2,'v2');
        $alice=$this->collection->findOne(['_id'=>$id1]);
        $this->assertEquals('Alice', $alice['name']);
    }
    public function testReencryptAll(): void
    {
        $key='test-encryption-key-32-chars-min!!';
        $this->collection->setEncryptionKey($key,'v2');
        $this->collection->insert(['a'=>1]);
        $this->collection->insert(['a'=>2]);
        $this->collection->setEncryptionKey($key,'v2-rotated');
        $count=$this->collection->reencryptAll();
        $this->assertEquals(2, $count);
    }
    public function testCustomConfigBlocksEncryptionKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->collection->setCustomConfig('encryption_key', 'hacked');
    }
    public function testCustomConfigBlocksSensitiveKeys(): void
    {
        foreach (['password','secret','token','api_key','private_key','credential'] as $k) {
            try { $this->collection->setCustomConfig($k,'x'); $this->fail("Key $k should be blocked"); }
            catch (\InvalidArgumentException $e) { $this->assertStringContainsString('forbidden', $e->getMessage()); }
        }
    }
    public function testCustomConfigAllowsSafeKeys(): void
    {
        $this->collection->setCustomConfig('theme','dark');
        $this->assertEquals('dark', $this->collection->getCustomConfig('theme'));
    }
    public function testCollectionManagerRejectsEncryptionKeyInConfig(): void
    {
        $manager = new \BangronDB\CollectionManager($this->db);
        $this->expectException(\InvalidArgumentException::class);
        $manager->saveCollectionConfig('users', ['id_mode'=>'auto','encryption_key'=>'should-fail']);
    }
    public function testDatabaseEncryptionKeyVersion(): void
    {
        $dbPath=$this->dir.'/kvtest.bangron';
        $db = new Database($dbPath, ['encryption_key'=>'test-key-32-chars-minimum-!!!!!','encryption_key_version'=>'test-v1']);
        $this->assertEquals('test-v1', $db->getEncryptionKeyVersion());
        $status=$db->getEncryptionKeyStatus();
        $this->assertTrue($status['enabled']);
        $this->assertEquals('test-v1', $status['key_version']);
        $db->close();
    }
}
