<?php

namespace BangronDB\Tests;

use BangronDB\Collection;
use BangronDB\Database;
use PHPUnit\Framework\TestCase;

class IndexingTest extends TestCase
{
    private Database $db;
    private Collection $collection;

    protected function setUp(): void
    {
        $this->db = new Database(':memory:');
        $this->collection = $this->db->selectCollection('test_collection');
    }

    protected function tearDown(): void
    {
        $this->db->close();
    }

    public function testCreateJsonIndex()
    {
        $this->collection->insert([
            'name' => 'John',
            'profile' => ['age' => 30, 'city' => 'NYC'],
        ]);

        $this->collection->insert([
            'name' => 'Jane',
            'profile' => ['age' => 25, 'city' => 'LA'],
        ]);

        // Create index on nested field
        $this->db->createJsonIndex('test_collection', 'profile.age');

        // Index should be created successfully
        $this->assertTrue(true); // If no exception thrown, test passes
    }

    public function testCreateIndexOnCollection()
    {
        $this->collection->insert(['name' => 'Test', 'category' => 'A']);
        $this->collection->createIndex('category');

        // Index should be created successfully
        $this->assertTrue(true);
    }

    public function testCreateIndexWithCustomName()
    {
        $this->collection->insert(['priority' => 'high', 'status' => 'open']);
        $this->db->createJsonIndex('test_collection', 'priority', 'custom_priority_idx');

        $this->assertTrue(true);
    }

    public function testDropIndex()
    {
        $this->db->createJsonIndex('test_collection', 'field1', 'test_index');
        $this->db->dropIndex('test_index');

        // Index should be dropped successfully
        $this->assertTrue(true);
    }

    public function testIndexMetrics()
    {
        // Create some data
        $this->collection->insert(['indexed_field' => 'value1', 'other' => 'data1']);
        $this->collection->insert(['indexed_field' => 'value2', 'other' => 'data2']);

        // Create index
        $this->db->createJsonIndex('test_collection', 'indexed_field');

        // Get index metrics
        $metrics = $this->db->getIndexMetrics();

        $this->assertIsArray($metrics);
        $this->assertGreaterThan(0, count($metrics));
    }

    public function testQueryPerformanceWithIndex()
    {
        // Insert many records
        for ($i = 0; $i < 100; ++$i) {
            $this->collection->insert([
                'id' => $i,
                'category' => $i % 10,
                'data' => 'Some data '.$i,
            ]);
        }

        // Create index on category
        $this->db->createJsonIndex('test_collection', 'category');

        // Query using indexed field
        $start = microtime(true);
        $results = $this->collection->find(['category' => 5])->toArray();
        $end = microtime(true);

        $this->assertCount(10, $results); // Should find 10 records with category 5
        $this->assertLessThan(0.1, $end - $start); // Should be fast
    }

    public function testIndexOnArrayField()
    {
        $this->collection->insert([
            'tags' => ['php', 'web', 'database'],
            'name' => 'Project 1',
        ]);

        $this->collection->insert([
            'tags' => ['javascript', 'web', 'frontend'],
            'name' => 'Project 2',
        ]);

        // Create index on array field
        $this->db->createJsonIndex('test_collection', 'tags');

        $this->assertTrue(true);
    }

    public function testMultipleIndexes()
    {
        $this->collection->insert([
            'name' => 'Test',
            'category' => 'A',
            'priority' => 1,
            'metadata' => ['version' => '1.0'],
        ]);

        // Create multiple indexes
        $this->db->createJsonIndex('test_collection', 'category');
        $this->db->createJsonIndex('test_collection', 'priority');
        $this->db->createJsonIndex('test_collection', 'metadata.version');

        $metrics = $this->db->getIndexMetrics();
        $this->assertGreaterThanOrEqual(3, count($metrics));
    }

    public function testIndexWithSpecialCharacters()
    {
        $this->collection->insert([
            'field-name' => 'value1',
            'field.name' => 'value2',
        ]);

        // Index name should be sanitized
        $this->db->createJsonIndex('test_collection', 'field-name');
        $this->db->createJsonIndex('test_collection', 'field.name');

        $this->assertTrue(true);
    }

    public function testIndexQueryOptimization()
    {
        // Create test data
        for ($i = 0; $i < 50; ++$i) {
            $this->collection->insert([
                'user_id' => $i % 5, // Only 5 different user_ids
                'action' => 'login',
                'timestamp' => time() + $i,
            ]);
        }

        // Create index
        $this->db->createJsonIndex('test_collection', 'user_id');

        // Query should use index
        $results = $this->collection->find(['user_id' => 2])->toArray();
        $this->assertCount(10, $results); // Should find 10 records for user_id 2
    }

    public function testIndexIntegrityAfterOperations()
    {
        // Insert data
        $this->collection->insert(['indexed' => 'value1', 'data' => 'test1']);
        $this->collection->insert(['indexed' => 'value2', 'data' => 'test2']);

        // Create index
        $this->db->createJsonIndex('test_collection', 'indexed');

        // Update data
        $this->collection->update(['indexed' => 'value1'], ['$set' => ['data' => 'updated']]);

        // Verify data integrity
        $doc = $this->collection->findOne(['indexed' => 'value1']);
        $this->assertEquals('updated', $doc['data']);

        // Delete some data
        $this->collection->remove(['indexed' => 'value2']);

        // Verify remaining data
        $count = $this->collection->count();
        $this->assertEquals(1, $count);
    }
}
