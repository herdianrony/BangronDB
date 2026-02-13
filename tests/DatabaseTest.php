<?php

namespace BangronDB\Tests;

use PHPUnit\Framework\TestCase;
use BangronDB\Database;

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
        $collection = $this->db->selectCollection('testcollection');
        $this->assertInstanceOf(\BangronDB\Collection::class, $collection);
        $this->assertEquals('testcollection', $collection->name);
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
        $collection = $this->db->testcollection;
        $this->assertInstanceOf(\BangronDB\Collection::class, $collection);
        $this->assertEquals('testcollection', $collection->name);
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
}
