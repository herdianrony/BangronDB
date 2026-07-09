<?php

declare(strict_types=1);

namespace BangronDB\Tests;

use PHPUnit\Framework\TestCase;
use BangronDB\Client;
use BangronDB\Database;
use BangronDB\Exceptions\DatabaseException;
use BangronDB\Exceptions\CollectionException;
use BangronDB\Exceptions\ValidationException;

class ClientTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bangrondb_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            usleep(100000); // Wait for connections to close
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->tempDir);
        }
    }

    public function testConstructor()
    {
        $client = new Client($this->tempDir);
        $this->assertInstanceOf(Client::class, $client);
        $this->assertEquals(realpath($this->tempDir), $client->path);
    }

    public function testListDBsEmpty()
    {
        $client = new Client($this->tempDir);
        $dbs = $client->listDBs();
        $this->assertIsArray($dbs);
        $this->assertEmpty($dbs);
    }

    public function testSelectDBReturnsExistingDatabase()
    {
        $client = new Client($this->tempDir);
        $client->createDB('testdb');

        $db = $client->selectDB('testdb');
        $this->assertInstanceOf(Database::class, $db);
        $this->assertFileExists($this->tempDir . '/testdb.bangron');
    }

    public function testCreateDBCreatesDatabase()
    {
        $client = new Client($this->tempDir);
        $db = $client->createDB('createdb');

        $this->assertInstanceOf(Database::class, $db);
        $this->assertFileExists($this->tempDir . '/createdb.bangron');
        $this->assertTrue($client->dbExists('createdb'));
    }

    public function testListDBsAfterSelection()
    {
        $client = new Client($this->tempDir);
        $client->createDB('testdb');
        $dbs = $client->listDBs();
        $this->assertContains('testdb', $dbs);
    }

    public function testDbExists()
    {
        $client = new Client($this->tempDir);
        $this->assertFalse($client->dbExists('missingdb'));

        $client->createDB('existingdb');
        $this->assertTrue($client->dbExists('existingdb'));
    }

    public function testStrictExtensionPolicy()
    {
        // Manually create legacy files
        touch($this->tempDir . '/legacy.pocket');
        touch($this->tempDir . '/old.sqlite');
        touch($this->tempDir . '/new.bangron');

        $client = new Client($this->tempDir);
        $dbs = $client->listDBs();

        $this->assertContains('new', $dbs);
        $this->assertNotContains('legacy', $dbs);
        $this->assertNotContains('old', $dbs);
    }

    public function testMagicGetAccess()
    {
        $client = new Client($this->tempDir);
        $client->createDB('testdb');

        $db = $client->testdb;
        $this->assertInstanceOf(Database::class, $db);
        $this->assertFileExists($this->tempDir . '/testdb.bangron');
    }

    public function testSelectCollection()
    {
        $client = new Client($this->tempDir);
        $client->createCollection('testdb', 'testcollection');

        $collection = $client->selectCollection('testdb', 'testcollection');
        $this->assertInstanceOf(\BangronDB\Collection::class, $collection);
        $this->assertEquals('testcollection', $collection->name);
    }

    public function testCreateCollection()
    {
        $client = new Client($this->tempDir);
        $collection = $client->createCollection('testdb', 'users');

        $this->assertInstanceOf(\BangronDB\Collection::class, $collection);
        $this->assertTrue($client->collectionExists('testdb', 'users'));
    }

    public function testCollectionExists()
    {
        $client = new Client($this->tempDir);
        $client->createDB('testdb');

        $this->assertFalse($client->collectionExists('testdb', 'users'));

        $client->createCollection('testdb', 'users');
        $this->assertTrue($client->collectionExists('testdb', 'users'));
    }

    public function testRenameCollectionFromClient()
    {
        $client = new Client($this->tempDir);
        $users = $client->createCollection('testdb', 'users');
        $users->insert(['name' => 'Alice']);

        $this->assertTrue($client->renameCollection('testdb', 'users', 'members'));
        $this->assertFalse($client->collectionExists('testdb', 'users'));
        $this->assertTrue($client->collectionExists('testdb', 'members'));
        $this->assertEquals(1, $client->selectCollection('testdb', 'members')->count());
    }

    public function testDropCollectionFromClient()
    {
        $client = new Client($this->tempDir);
        $client->createCollection('testdb', 'users');

        $this->assertTrue($client->dropCollection('testdb', 'users'));
        $this->assertFalse($client->collectionExists('testdb', 'users'));
    }

    public function testInvalidDatabaseName()
    {
        $this->expectException(ValidationException::class);
        $client = new Client($this->tempDir);
        $client->selectDB('invalid name!');
    }

    public function testConstructorRejectsInvalidDirectoryPath()
    {
        $this->expectException(ValidationException::class);
        new Client($this->tempDir . '/missing-subdir');
    }

    public function testConstructorRejectsPathOutsideBaseDirectory()
    {
        $outsideDir = sys_get_temp_dir() . '/bangrondb_outside_' . uniqid();
        mkdir($outsideDir);

        try {
            $this->expectException(ValidationException::class);
            new Client($outsideDir, ['base_path' => $this->tempDir]);
        } finally {
            @rmdir($outsideDir);
        }
    }

    public function testSelectMissingDatabaseThrowsException()
    {
        $this->expectException(DatabaseException::class);

        $client = new Client($this->tempDir);
        $client->selectDB('missingdb');
    }

    public function testSelectMissingCollectionThrowsException()
    {
        $this->expectException(CollectionException::class);

        $client = new Client($this->tempDir);
        $client->createDB('testdb');
        $client->selectCollection('testdb', 'missingcollection');
    }

    public function testMemoryClient()
    {
        $client = new Client(Database::DSN_PATH_MEMORY);
        $db = $client->createDB('memorydb');
        $this->assertInstanceOf(Database::class, $db);
        $dbs = $client->listDBs();
        $this->assertContains('memorydb', $dbs);
    }

    public function testDropDB()
    {
        $client = new Client($this->tempDir);
        $client->createDB('dropme');

        $this->assertTrue($client->dropDB('dropme'));
        $this->assertFalse($client->dbExists('dropme'));
        $this->assertFileDoesNotExist($this->tempDir . '/dropme.bangron');
    }

    public function testDropMemoryDB()
    {
        $client = new Client(Database::DSN_PATH_MEMORY);
        $client->createDB('memorydrop');

        $this->assertTrue($client->dropDB('memorydrop'));
        $this->assertFalse($client->dbExists('memorydrop'));
    }

    public function testRenameDB()
    {
        $client = new Client($this->tempDir);
        $db = $client->createDB('olddb');
        $db->createCollection('users')->insert(['name' => 'Alice']);

        $this->assertTrue($client->renameDB('olddb', 'newdb'));
        $this->assertFalse($client->dbExists('olddb'));
        $this->assertTrue($client->dbExists('newdb'));
        $this->assertFileDoesNotExist($this->tempDir . '/olddb.bangron');
        $this->assertFileExists($this->tempDir . '/newdb.bangron');

        $renamed = $client->selectDB('newdb');
        $this->assertEquals(1, $renamed->selectCollection('users')->count());
    }

    public function testRenameMemoryDB()
    {
        $client = new Client(Database::DSN_PATH_MEMORY);
        $db = $client->createDB('oldmemory');
        $db->createCollection('users')->insert(['name' => 'Alice']);

        $this->assertTrue($client->renameDB('oldmemory', 'newmemory'));
        $this->assertFalse($client->dbExists('oldmemory'));
        $this->assertTrue($client->dbExists('newmemory'));
        $this->assertEquals(1, $client->selectDB('newmemory')->selectCollection('users')->count());
    }

    public function testClose()
    {
        $client = new Client(Database::DSN_PATH_MEMORY);
        $client->createDB('testdb');
        $dbs = $client->listDBs();
        $this->assertContains('testdb', $dbs);

        $client->close();
        // After close, databases should be cleared for memory client
        $dbs = $client->listDBs();
        $this->assertEmpty($dbs);
    }
}
