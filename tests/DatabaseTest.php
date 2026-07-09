<?php

declare(strict_types=1);

namespace BangronDB\Tests;

use PHPUnit\Framework\TestCase;
use BangronDB\Database;
use BangronDB\Exceptions\CollectionException;

class DatabaseTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        $this->db = new Database(':memory:');
    }

    protected function tearDown(): void
    {
        $this->db->close();
    }

    public function testConstructor()
    {
        $this->assertInstanceOf(Database::class, $this->db);
        $this->assertEquals(':memory:', $this->db->path);
    }

    public function testCreateCollection()
    {
        $this->db->createCollection('testcollection');
        $collections = $this->db->getCollectionNames();
        $this->assertContains('testcollection', $collections);
    }

    public function testDropCollection()
    {
        $this->db->createCollection('testcollection');
        $this->assertContains('testcollection', $this->db->getCollectionNames());

        $this->db->dropCollection('testcollection');
        $this->assertNotContains('testcollection', $this->db->getCollectionNames());
    }

    public function testSelectCollection()
    {
        $this->db->createCollection('testcollection');

        $collection = $this->db->selectCollection('testcollection');
        $this->assertInstanceOf(\BangronDB\Collection::class, $collection);
        $this->assertEquals('testcollection', $collection->name);
    }

    public function testCollectionExists()
    {
        $this->assertFalse($this->db->collectionExists('missing'));

        $this->db->createCollection('existing');
        $this->assertTrue($this->db->collectionExists('existing'));
    }

    public function testSelectMissingCollectionThrowsException()
    {
        $this->expectException(CollectionException::class);
        $this->db->selectCollection('missing');
    }

    public function testListCollections()
    {
        $this->db->createCollection('col1');
        $this->db->createCollection('col2');
        $collections = $this->db->listCollections();
        $this->assertCount(2, $collections);
        $this->assertArrayHasKey('col1', $collections);
        $this->assertArrayHasKey('col2', $collections);
    }

    public function testMagicGetCollection()
    {
        $this->db->createCollection('testcollection');

        $collection = $this->db->testcollection;
        $this->assertInstanceOf(\BangronDB\Collection::class, $collection);
        $this->assertEquals('testcollection', $collection->name);
    }

    public function testRenameCollectionViaDatabase()
    {
        $this->db->createCollection('users')->insert(['name' => 'Alice']);

        $this->assertTrue($this->db->renameCollection('users', 'members'));
        $this->assertFalse($this->db->collectionExists('users'));
        $this->assertTrue($this->db->collectionExists('members'));
        $this->assertEquals(1, $this->db->selectCollection('members')->count());
    }

    public function testVacuum()
    {
        // Vacuum on memory database should not throw
        $this->db->vacuum();
        $this->assertTrue(true);
    }

    public function testHealthMetrics()
    {
        $metrics = $this->db->getHealthMetrics();
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('database', $metrics);
        $this->assertArrayHasKey('integrity', $metrics);
        $this->assertArrayHasKey('metrics', $metrics);
        $this->assertArrayHasKey('performance', $metrics);
        $this->assertArrayHasKey('collections', $metrics);
    }

    public function testSetEncryptionKey()
    {
        $result = $this->db->setEncryptionKey('12345678901234567890123456789012');

        $this->assertSame($this->db, $result);
        $this->assertTrue($this->db->isEncryptionEnabled());
        $this->assertSame([
            'enabled' => true,
            'key_length' => 32,
            'key_version' => null,
        ], $this->db->getEncryptionKeyStatus());
    }

    public function testMemoryEncryptionSaltIsStablePerInstance()
    {
        $salt1 = $this->db->getEncryptionSalt();
        $salt2 = $this->db->getEncryptionSalt();

        $this->assertNotEmpty($salt1);
        $this->assertSame($salt1, $salt2);
    }

    public function testFileEncryptionSaltPersistsPerDatabase()
    {
        $path = sys_get_temp_dir() . '/bangrondb_salt_' . uniqid() . '.bangron';

        try {
            $db1 = new Database($path);
            $salt1 = $db1->getEncryptionSalt();
            $db1->close();

            $db2 = new Database($path);
            $salt2 = $db2->getEncryptionSalt();
            $db2->close();

            $this->assertNotEmpty($salt1);
            $this->assertSame($salt1, $salt2);
        } finally {
            @unlink($path);
            @unlink($path . '-wal');
            @unlink($path . '-shm');
        }
    }

    public function testDropRejectsNonBangronExtension()
    {
        $path = sys_get_temp_dir() . '/bangrondb_invalid_' . uniqid() . '.sqlite';

        try {
            $db = new Database($path);
            $this->expectException(\RuntimeException::class);
            $db->drop();
        } finally {
            @unlink($path);
            @unlink($path . '-wal');
            @unlink($path . '-shm');
        }
    }
}
