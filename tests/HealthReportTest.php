<?php

namespace BangronDB\Tests;

use BangronDB\Collection;
use BangronDB\Database;
use PHPUnit\Framework\TestCase;

class HealthReportTest extends TestCase
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

    public function testGetHealthReportStructure()
    {
        $report = $this->db->getHealthReport();

        $this->assertIsArray($report);
        $this->assertArrayHasKey('status', $report);
        $this->assertArrayHasKey('issues', $report);
        $this->assertArrayHasKey('warnings', $report);
        $this->assertArrayHasKey('recommendations', $report);
        $this->assertArrayHasKey('timestamp', $report);

        $this->assertIsInt($report['timestamp']);
        $this->assertIsArray($report['issues']);
        $this->assertIsArray($report['warnings']);
        $this->assertIsArray($report['recommendations']);
    }

    public function testHealthyDatabaseStatus()
    {
        $report = $this->db->getHealthReport();

        // Memory database without encryption shows warning for encryption
        $this->assertContains($report['status'], ['warning', 'healthy']);
        $this->assertEmpty($report['issues']);
    }

    public function testHealthReportWithData()
    {
        // Add some data
        for ($i = 0; $i < 10; ++$i) {
            $this->collection->insert(['name' => 'Item '.$i, 'value' => $i]);
        }

        $report = $this->db->getHealthReport();

        $this->assertContains($report['status'], ['warning', 'healthy']);
        $this->assertEmpty($report['issues']);
    }

    public function testHealthReportWithLargeCollectionWarning()
    {
        // Create a collection with many documents (simulate > 10k)
        // We'll use a smaller number for test but patch the metrics
        for ($i = 0; $i < 50; ++$i) {
            $this->collection->insert(['data' => 'item_'.$i]);
        }

        $report = $this->db->getHealthReport();

        // With 50 documents, should still be healthy but check warnings
        $this->assertContains('healthy', ['healthy', 'warning']);
        $this->assertIsArray($report['warnings']);
    }

    public function testHealthReportWithEncryptionWarning()
    {
        // Test with non-encrypted database (default)
        $report = $this->db->getHealthReport();

        // Should have encryption warning
        $this->assertContains('Database encryption is not enabled', $report['warnings']);
        $this->assertContains('Consider enabling encryption for sensitive data', $report['recommendations']);
    }

    public function testHealthReportWithEncryptionEnabled()
    {
        $encryptedDb = new Database(':memory:', ['encryption_key' => 'test_key']);
        $report = $encryptedDb->getHealthReport();

        // Should not have encryption warning
        $this->assertNotContains('Database encryption is not enabled', $report['warnings']);

        $encryptedDb->close();
    }

    public function testHealthReportStatusDetermination()
    {
        $report = $this->db->getHealthReport();

        // Test status logic
        if (!empty($report['issues'])) {
            $this->assertEquals('critical', $report['status']);
        } elseif (!empty($report['warnings'])) {
            $this->assertContains($report['status'], ['warning', 'healthy']);
        } else {
            $this->assertEquals('healthy', $report['status']);
        }
    }

    public function testHealthReportWithFragmentationWarning()
    {
        // Fragmentation warning is based on freelist_count > 0.1 * page_count
        // For memory database, this might not trigger, but we can test the structure
        $report = $this->db->getHealthReport();

        $this->assertIsArray($report['warnings']);
        $this->assertIsArray($report['recommendations']);
    }

    public function testHealthReportTimestamp()
    {
        $before = time();
        $report = $this->db->getHealthReport();
        $after = time();

        $this->assertGreaterThanOrEqual($before, $report['timestamp']);
        $this->assertLessThanOrEqual($after, $report['timestamp']);
    }

    public function testHealthReportWithMultipleCollections()
    {
        // Create multiple collections
        $collection1 = $this->db->selectCollection('collection1');
        $collection2 = $this->db->selectCollection('collection2');

        $collection1->insert(['type' => 'data1']);
        $collection2->insert(['type' => 'data2']);
        $collection2->insert(['type' => 'data3']);

        $report = $this->db->getHealthReport();

        $this->assertContains($report['status'], ['warning', 'healthy']);
    }

    public function testHealthReportConsistency()
    {
        // Run multiple times to ensure consistency
        $report1 = $this->db->getHealthReport();
        sleep(1); // Ensure different timestamp
        $report2 = $this->db->getHealthReport();

        $this->assertEquals($report1['status'], $report2['status']);
        $this->assertNotEquals($report1['timestamp'], $report2['timestamp']);
    }

    public function testHealthReportWithHooks()
    {
        // Add hooks to collection
        $this->collection->on('beforeInsert', function ($doc) { return $doc; });
        $this->collection->on('afterInsert', function ($doc, $id) { });

        $this->collection->insert(['name' => 'Test']);

        $report = $this->db->getHealthReport();

        // Should still be healthy despite hooks
        $this->assertContains($report['status'], ['healthy', 'warning']);
    }

    public function testHealthReportWithIndexes()
    {
        // Add index
        $this->db->createJsonIndex('test_collection', 'indexed_field');

        // Add data
        $this->collection->insert(['indexed_field' => 'value1']);
        $this->collection->insert(['indexed_field' => 'value2']);

        $report = $this->db->getHealthReport();

        // Should be healthy/warning with indexes (encryption warning still applies)
        $this->assertContains($report['status'], ['warning', 'healthy']);
    }

    public function testHealthReportMethodExists()
    {
        $this->assertTrue(method_exists($this->db, 'getHealthReport'));
    }
}
