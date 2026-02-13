<?php

namespace BangronDB\Tests;

use BangronDB\Collection;
use BangronDB\Database;
use PHPUnit\Framework\TestCase;

class ChangeNotificationTest extends TestCase
{
    private Database $db;
    private Collection $collection;

    protected function setUp(): void
    {
        $this->db = new Database(':memory:');
        $this->collection = $this->db->selectCollection('testcollection');
    }

    protected function tearDown(): void
    {
        $this->db->close();
    }

    public function testGetLastModifiedInitial()
    {
        $info = $this->collection->getLastModified();

        $this->assertEquals(0, $info['version']);
        $this->assertNull($info['last_updated']);
    }

    public function testChangeNotificationOnInsert()
    {
        $before = $this->collection->getLastModified();
        $this->assertEquals(0, $before['version']);

        // Insert document
        $this->collection->insert(['name' => 'Test']);

        $after = $this->collection->getLastModified();
        $this->assertEquals(1, $after['version']);
        $this->assertIsString($after['last_updated']);
        $this->assertGreaterThan($before['last_updated'], $after['last_updated']);
    }

    public function testChangeNotificationOnUpdate()
    {
        // Insert initial document
        $id = $this->collection->insert(['name' => 'Test', 'value' => 1]);
        $version1 = $this->collection->getLastModified()['version'];

        // Update document
        $this->collection->update(['_id' => $id], ['value' => 2]);

        $version2 = $this->collection->getLastModified()['version'];
        $this->assertEquals($version1 + 1, $version2);
    }

    public function testChangeNotificationOnRemove()
    {
        // Insert document
        $this->collection->insert(['name' => 'Test']);
        $version1 = $this->collection->getLastModified()['version'];

        // Remove document
        $this->collection->remove(['name' => 'Test']);

        $version2 = $this->collection->getLastModified()['version'];
        $this->assertEquals($version1 + 1, $version2);
    }

    public function testChangeNotificationOnMultipleOperations()
    {
        $initial = $this->collection->getLastModified()['version'];

        // Multiple operations
        $this->collection->insert(['name' => 'Doc1']);
        $this->collection->insert(['name' => 'Doc2']);
        $this->collection->update(['name' => 'Doc1'], ['value' => 1]);
        $this->collection->remove(['name' => 'Doc2']);

        $final = $this->collection->getLastModified()['version'];
        $this->assertEquals($initial + 4, $final); // 4 operations
    }

    public function testChangeNotificationOnBatchInsert()
    {
        $before = $this->collection->getLastModified()['version'];

        // Batch insert multiple documents
        $this->collection->insert([
            ['name' => 'Doc1'],
            ['name' => 'Doc2'],
            ['name' => 'Doc3'],
        ]);

        $after = $this->collection->getLastModified()['version'];
        $this->assertEquals($before + 1, $after); // Batch insert counts as 1 change
    }

    public function testChangeNotificationOnBatchUpdate()
    {
        // Insert multiple documents
        $this->collection->insert([
            ['name' => 'Doc1', 'status' => 'draft'],
            ['name' => 'Doc2', 'status' => 'draft'],
            ['name' => 'Doc3', 'status' => 'draft'],
        ]);

        $beforeUpdate = $this->collection->getLastModified()['version'];

        // Batch update
        $this->collection->update(['status' => 'draft'], ['status' => 'published']);

        $afterUpdate = $this->collection->getLastModified()['version'];
        $this->assertEquals($beforeUpdate + 1, $afterUpdate);
    }

    public function testChangeNotificationWithSoftDeletes()
    {
        $this->collection->useSoftDeletes(true);

        // Insert document
        $this->collection->insert(['name' => 'Test']);
        $version1 = $this->collection->getLastModified()['version'];

        // Soft delete (should trigger notification)
        $this->collection->remove(['name' => 'Test']);
        $version2 = $this->collection->getLastModified()['version'];
        $this->assertEquals($version1 + 1, $version2);

        // Restore (should trigger notification)
        $this->collection->restore(['name' => 'Test']);
        $version3 = $this->collection->getLastModified()['version'];
        $this->assertEquals($version2 + 1, $version3);

        // Force delete (should trigger notification)
        $this->collection->forceDelete(['name' => 'Test']);
        $version4 = $this->collection->getLastModified()['version'];
        $this->assertEquals($version3 + 1, $version4);
    }

    public function testChangeNotificationDifferentCollections()
    {
        $collection1 = $this->db->selectCollection('coll1');
        $collection2 = $this->db->selectCollection('coll2');

        // Initial states
        $this->assertEquals(0, $collection1->getLastModified()['version']);
        $this->assertEquals(0, $collection2->getLastModified()['version']);

        // Modify collection1
        $collection1->insert(['name' => 'Test1']);

        // Check versions
        $this->assertEquals(1, $collection1->getLastModified()['version']);
        $this->assertEquals(0, $collection2->getLastModified()['version']); // Unchanged

        // Modify collection2
        $collection2->insert(['name' => 'Test2']);

        // Check versions again
        $this->assertEquals(1, $collection1->getLastModified()['version']); // Unchanged
        $this->assertEquals(1, $collection2->getLastModified()['version']);
    }

    public function testManualChangeNotification()
    {
        $before = $this->collection->getLastModified()['version'];

        // Manual notification (for custom operations)
        $this->collection->notifyChange();

        $after = $this->collection->getLastModified()['version'];
        $this->assertEquals($before + 1, $after);
    }

    public function testChangeNotificationTimestamp()
    {
        $before = $this->collection->getLastModified();

        // Wait a bit and make change
        sleep(1);
        $this->collection->insert(['name' => 'Test']);

        $after = $this->collection->getLastModified();

        $this->assertGreaterThan($before['last_updated'], $after['last_updated']);
        $this->assertIsString($after['last_updated']);
    }

    public function testChangeNotificationWithSaveUpsert()
    {
        $before = $this->collection->getLastModified()['version'];

        // Insert new document
        $doc = ['name' => 'Test'];
        $this->collection->save($doc);

        $afterInsert = $this->collection->getLastModified()['version'];
        $this->assertEquals($before + 1, $afterInsert);

        // Update existing document
        $doc['name'] = 'Updated Test';
        $this->collection->save($doc);

        $afterUpdate = $this->collection->getLastModified()['version'];
        $this->assertEquals($afterInsert + 1, $afterUpdate);
    }
}
