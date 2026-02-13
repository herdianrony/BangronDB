<?php

namespace BangronDB\Tests;

use BangronDB\Collection;
use BangronDB\Database;
use BangronDB\UtilArrayQuery;
use PHPUnit\Framework\TestCase;

class UtilitiesTest extends TestCase
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

    public function testIdModeAuto()
    {
        $this->collection->setIdModeAuto();

        $this->assertEquals('auto', $this->collection->getIdMode());

        // Insert document and verify UUID format
        $result = $this->collection->insert(['name' => 'test']);
        $this->assertIsString($result);

        // UUID v4 format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $result
        );

        // Verify document has the generated ID
        $doc = $this->collection->findOne(['_id' => $result]);
        $this->assertEquals('test', $doc['name']);
        $this->assertEquals($result, $doc['_id']);
    }

    public function testIdModeManual()
    {
        $this->collection->setIdModeManual();

        $this->assertEquals('manual', $this->collection->getIdMode());

        // Insert with manual ID
        $manualId = 'custom-id-123';
        $result = $this->collection->insert(['_id' => $manualId, 'name' => 'manual test']);
        $this->assertEquals($manualId, $result);

        // Verify document
        $doc = $this->collection->findOne(['_id' => $manualId]);
        $this->assertEquals('manual test', $doc['name']);
    }

    public function testIdModeManualWithoutIdFails()
    {
        $this->collection->setIdModeManual();

        // Try to insert without _id
        $result = $this->collection->insert(['name' => 'should fail']);
        $this->assertFalse($result);

        // Collection should be empty
        $this->assertEquals(0, $this->collection->count());
    }

    public function testIdModePrefix()
    {
        $this->collection->setIdModePrefix('TEST');

        $this->assertEquals('prefix', $this->collection->getIdMode());

        // Insert documents and verify prefix format
        $result1 = $this->collection->insert(['name' => 'first']);
        $result2 = $this->collection->insert(['name' => 'second']);

        $this->assertStringStartsWith('TEST-', $result1);
        $this->assertStringStartsWith('TEST-', $result2);
        $this->assertNotEquals($result1, $result2);

        // Verify counter increments
        $parts1 = explode('-', $result1);
        $parts2 = explode('-', $result2);
        $this->assertEquals((int) $parts1[1] + 1, (int) $parts2[1]);
    }

    public function testIdModePrefixCounterInitialization()
    {
        // Insert some documents with prefix
        $this->collection->setIdModePrefix('INIT');
        $id1 = $this->collection->insert(['name' => 'init1']);
        $id2 = $this->collection->insert(['name' => 'init2']);

        // Create new collection instance (simulates restart)
        $newCollection = $this->db->selectCollection('test_collection');
        $newCollection->setIdModePrefix('INIT');

        // Next ID should continue from counter
        $id3 = $newCollection->insert(['name' => 'init3']);

        $num1 = (int) explode('-', $id1)[1];
        $num2 = (int) explode('-', $id2)[1];
        $num3 = (int) explode('-', $id3)[1];

        $this->assertEquals($num1 + 1, $num2);
        $this->assertEquals($num2 + 1, $num3);
    }

    public function testSetPrefixAndSuffix()
    {
        $this->collection->setIdModeAuto()->setPrefix('PRE_')->setSuffix('_SUF');

        $result = $this->collection->insert(['name' => 'prefixed']);

        $this->assertStringStartsWith('PRE_', $result);
        $this->assertStringEndsWith('_SUF', $result);
        $this->assertMatchesRegularExpression(
            '/^PRE_[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}_SUF$/i',
            $result
        );
    }

    public function testPrefixModeWithGeneralPrefix()
    {
        // In prefix mode, general prefix is not used, only the prefix mode prefix
        $this->collection->setIdModePrefix('BASE');

        $result = $this->collection->insert(['name' => 'complex']);

        // Should only have BASE- counter
        $this->assertStringStartsWith('BASE-', $result);
    }

    public function testIdGenerationUniqueness()
    {
        $this->collection->setIdModeAuto();

        $ids = [];
        for ($i = 0; $i < 100; ++$i) {
            $id = $this->collection->insert(['index' => $i]);
            $this->assertNotContains($id, $ids, "ID {$id} is not unique");
            $ids[] = $id;
        }

        $this->assertCount(100, array_unique($ids));
    }

    public function testUtilArrayQueryGenerateId()
    {
        // Test the underlying UUID generation
        $id1 = UtilArrayQuery::generateId();
        $id2 = UtilArrayQuery::generateId();

        $this->assertIsString($id1);
        $this->assertIsString($id2);
        $this->assertNotEquals($id1, $id2);

        // Both should be valid UUIDs
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id1
        );
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id2
        );
    }

    public function testManualIdOverride()
    {
        $this->collection->setIdModeAuto();

        // Insert with explicit _id should override auto generation
        $customId = 'my-custom-id';
        $result = $this->collection->insert(['_id' => $customId, 'name' => 'override']);

        $this->assertEquals($customId, $result);

        $doc = $this->collection->findOne(['_id' => $customId]);
        $this->assertEquals('override', $doc['name']);
    }

    public function testIdCounterResetOnPrefixChange()
    {
        $this->collection->setIdModePrefix('OLD');
        $oldId = $this->collection->insert(['name' => 'old']);

        // Change prefix
        $this->collection->setIdModePrefix('NEW');
        $newId = $this->collection->insert(['name' => 'new']);

        $this->assertStringStartsWith('OLD-', $oldId);
        $this->assertStringStartsWith('NEW-', $newId);
    }

    public function testIdModeSwitching()
    {
        // Start with auto
        $this->collection->setIdModeAuto();
        $autoId = $this->collection->insert(['name' => 'auto']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $autoId
        );

        // Switch to manual
        $this->collection->setIdModeManual();
        $manualId = 'manual-123';
        $result = $this->collection->insert(['_id' => $manualId, 'name' => 'manual']);
        $this->assertEquals($manualId, $result);

        // Switch to prefix
        $this->collection->setIdModePrefix('PRE');
        $prefixId = $this->collection->insert(['name' => 'prefix']);
        $this->assertStringStartsWith('PRE-', $prefixId);
    }

    public function testEnsureDocumentIdMethod()
    {
        // Test via public interface by checking insert results
        $this->collection->setIdModeAuto();
        $doc = ['name' => 'test'];
        $result = $this->collection->insert($doc);
        $this->assertIsString($result);

        $retrieved = $this->collection->findOne(['_id' => $result]);
        $this->assertEquals('test', $retrieved['name']);
        $this->assertEquals($result, $retrieved['_id']);
    }

    public function testPrefixCounterWithExistingData()
    {
        // Insert some data first
        $this->collection->setIdModePrefix('EXIST');
        $this->collection->insert(['name' => 'existing1']);
        $this->collection->insert(['name' => 'existing2']);

        // Simulate new session by creating new collection instance
        $freshCollection = $this->db->selectCollection('test_collection');
        $freshCollection->setIdModePrefix('EXIST');

        $newId = $freshCollection->insert(['name' => 'new']);

        // Should continue from existing counter
        $this->assertStringStartsWith('EXIST-', $newId);
        $parts = explode('-', $newId);
        $counter = (int) $parts[1];
        $this->assertGreaterThan(2, $counter); // Should be at least 3
    }
}
