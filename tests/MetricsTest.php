<?php

namespace BangronDB\Tests;

use BangronDB\Collection;
use BangronDB\Database;
use PHPUnit\Framework\TestCase;

class MetricsTest extends TestCase
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

    public function testGetHealthMetrics()
    {
        $metrics = $this->db->getHealthMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('database', $metrics);
        $this->assertArrayHasKey('integrity', $metrics);
        $this->assertArrayHasKey('metrics', $metrics);
        $this->assertArrayHasKey('performance', $metrics);
        $this->assertArrayHasKey('collections', $metrics);

        // Check database info
        $this->assertEquals(':memory:', $metrics['database']['path']);
        $this->assertEquals('memory', $metrics['database']['type']);
        $this->assertFalse($metrics['database']['encryption_enabled']);
    }

    public function testCheckIntegrity()
    {
        $integrity = $this->db->checkIntegrity();

        $this->assertIsArray($integrity);
        $this->assertArrayHasKey('status', $integrity);
        $this->assertArrayHasKey('details', $integrity);
        $this->assertArrayHasKey('checked_at', $integrity);

        // For memory database, integrity should be healthy
        $this->assertEquals('healthy', $integrity['status']);
        $this->assertContains('ok', $integrity['details']);
    }

    public function testGetDataMetrics()
    {
        // Insert some test data
        $this->collection->insert(['name' => 'Test 1', 'value' => 1]);
        $this->collection->insert(['name' => 'Test 2', 'value' => 2]);

        $metrics = $this->db->getDataMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('total_collections', $metrics);
        $this->assertArrayHasKey('total_documents', $metrics);
        $this->assertArrayHasKey('total_size_bytes', $metrics);
        $this->assertArrayHasKey('collections', $metrics);

        $this->assertGreaterThanOrEqual(1, $metrics['total_collections']);
        $this->assertGreaterThanOrEqual(2, $metrics['total_documents']);
        $this->assertGreaterThan(0, $metrics['total_size_bytes']);
        $this->assertArrayHasKey('test_collection', $metrics['collections']);
    }

    public function testPerformanceMetrics()
    {
        $metrics = $this->db->getPerformanceMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('page_count', $metrics);
        $this->assertArrayHasKey('page_size', $metrics);
        $this->assertArrayHasKey('fragmentation_ratio', $metrics);
        $this->assertArrayHasKey('indexes', $metrics);

        // For memory database, page_count might be 0 initially
        $this->assertIsInt($metrics['page_count']);
        $this->assertIsInt($metrics['page_size']);
        $this->assertIsNumeric($metrics['fragmentation_ratio']);
    }

    public function testIndexMetrics()
    {
        // Create an index
        $this->db->createJsonIndex('test_collection', 'field1');

        $metrics = $this->db->getIndexMetrics();

        $this->assertIsArray($metrics);
        $this->assertGreaterThan(0, count($metrics));

        // Check if our index is present
        $found = false;
        foreach ($metrics as $indexName => $indexInfo) {
            if (strpos($indexName, 'idx_test_collection_field1') === 0) {
                $found = true;
                $this->assertEquals('test_collection', $indexInfo['table']);
                $this->assertEquals('json_index', $indexInfo['type']);
                break;
            }
        }
        $this->assertTrue($found, 'Created index not found in metrics');
    }

    public function testCollectionMetrics()
    {
        // Insert data and configure collection
        $this->collection->insert(['name' => 'Item 1', 'category' => 'A']);
        $this->collection->insert(['name' => 'Item 2', 'category' => 'B']);
        $this->collection->setSearchableFields(['category']);

        $metrics = $this->db->getCollectionMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('test_collection', $metrics);

        $collectionMetrics = $metrics['test_collection'];
        $this->assertEquals(2, $collectionMetrics['documents']);
        $this->assertGreaterThan(0, $collectionMetrics['size_bytes']);
        $this->assertIsArray($collectionMetrics['indexes']);
        $this->assertIsArray($collectionMetrics['hooks']);
        $this->assertContains('category', $collectionMetrics['searchable_fields']);
    }

    public function testCollectionMetricsWithHooks()
    {
        // Add hooks to collection
        $this->collection->on('beforeInsert', function ($doc) { return $doc; });
        $this->collection->on('afterInsert', function ($doc, $id) { });
        $this->collection->on('beforeUpdate', function ($criteria, $data) { return [$criteria, $data]; });

        $metrics = $this->db->getCollectionMetrics();

        $collectionMetrics = $metrics['test_collection'];
        $this->assertIsArray($collectionMetrics['hooks']);
        $this->assertEquals(1, $collectionMetrics['hooks']['beforeInsert']);
        $this->assertEquals(1, $collectionMetrics['hooks']['afterInsert']);
        $this->assertEquals(1, $collectionMetrics['hooks']['beforeUpdate']);
        $this->assertEquals(0, $collectionMetrics['hooks']['beforeRemove']);
    }

    public function testMetricsAfterOperations()
    {
        // Initial state
        $initialMetrics = $this->db->getDataMetrics();

        // Insert data
        $this->collection->insert(['test' => 'data']);
        $afterInsert = $this->db->getDataMetrics();

        $this->assertEquals($initialMetrics['total_documents'] + 1, $afterInsert['total_documents']);

        // Update data
        $this->collection->update(['test' => 'data'], ['$set' => ['test' => 'updated']]);
        $afterUpdate = $this->db->getDataMetrics();

        $this->assertEquals($afterInsert['total_documents'], $afterUpdate['total_documents']);

        // Remove data
        $this->collection->remove(['test' => 'updated']);
        $afterRemove = $this->db->getDataMetrics();

        $this->assertEquals($afterUpdate['total_documents'] - 1, $afterRemove['total_documents']);
    }

    public function testHealthMetricsWithEncryption()
    {
        $encryptedDb = new Database(':memory:', ['encryption_key' => 'test_key']);
        $metrics = $encryptedDb->getHealthMetrics();

        $this->assertTrue($metrics['database']['encryption_enabled']);

        $encryptedDb->close();
    }

    public function testIndexMetricsDetails()
    {
        $this->db->createJsonIndex('test_collection', 'test_field', 'custom_index_name');

        $metrics = $this->db->getIndexMetrics();

        $this->assertArrayHasKey('custom_index_name', $metrics);
        $indexInfo = $metrics['custom_index_name'];

        $this->assertEquals('test_collection', $indexInfo['table']);
        $this->assertEquals('custom_index', $indexInfo['type']);
        $this->assertTrue(strpos($indexInfo['definition'] ?? '', 'json_extract') !== false);
    }

    public function testCollectionMetricsWithMultipleCollections()
    {
        $collection2 = $this->db->selectCollection('collection2');

        $this->collection->insert(['type' => 'main']);
        $this->collection->insert(['type' => 'main']);
        $collection2->insert(['type' => 'secondary']);

        $metrics = $this->db->getCollectionMetrics();

        $this->assertArrayHasKey('test_collection', $metrics);
        $this->assertArrayHasKey('collection2', $metrics);

        $this->assertEquals(2, $metrics['test_collection']['documents']);
        $this->assertEquals(1, $metrics['collection2']['documents']);
    }
}
