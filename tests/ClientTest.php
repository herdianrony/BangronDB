<?php

namespace BangronDB\Tests;

use PHPUnit\Framework\TestCase;
use BangronDB\Client;
use BangronDB\Database;
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
        $this->assertEquals($this->tempDir, $client->path);
    }

    public function testListDBsEmpty()
    {
        $client = new Client($this->tempDir);
        $dbs = $client->listDBs();
        $this->assertIsArray($dbs);
        $this->assertEmpty($dbs);
    }

    public function testSelectDBCreatesDatabase()
    {
        $client = new Client($this->tempDir);
        $db = $client->selectDB('testdb');
        $this->assertInstanceOf(Database::class, $db);
        $this->assertFileExists($this->tempDir . '/testdb.bangron');
    }

    public function testListDBsAfterSelection()
    {
        $client = new Client($this->tempDir);
        $client->selectDB('testdb');
        $dbs = $client->listDBs();
        $this->assertContains('testdb', $dbs);
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
        $db = $client->testdb;
        $this->assertInstanceOf(Database::class, $db);
        $this->assertFileExists($this->tempDir . '/testdb.bangron');
    }

    public function testSelectCollection()
    {
        $client = new Client($this->tempDir);
        $collection = $client->selectCollection('testdb', 'testcollection');
        $this->assertInstanceOf(\BangronDB\Collection::class, $collection);
        $this->assertEquals('testcollection', $collection->name);
    }

    public function testInvalidDatabaseName()
    {
        $this->expectException(ValidationException::class);
        $client = new Client($this->tempDir);
        $client->selectDB('invalid name!');
    }

    public function testMemoryClient()
    {
        $client = new Client(Database::DSN_PATH_MEMORY);
        $db = $client->selectDB('memorydb');
        $this->assertInstanceOf(Database::class, $db);
        $dbs = $client->listDBs();
        $this->assertContains('memorydb', $dbs);
    }

    public function testClose()
    {
        $client = new Client(Database::DSN_PATH_MEMORY);
        $client->selectDB('testdb');
        $dbs = $client->listDBs();
        $this->assertContains('testdb', $dbs);

        $client->close();
        // After close, databases should be cleared for memory client
        $dbs = $client->listDBs();
        $this->assertEmpty($dbs);
    }
}
