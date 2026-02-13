<?php

namespace BangronDB\Tests;

use BangronDB\Collection;
use BangronDB\Database;
use PHPUnit\Framework\TestCase;

class RenameTest extends TestCase
{
    private Database $db;
    private Collection $collection;

    protected function setUp(): void
    {
        $this->db = new Database(':memory:');
        $this->collection = $this->db->selectCollection('original_collection');
    }

    protected function tearDown(): void
    {
        $this->db->close();
    }

    public function testRenameCollectionSuccess()
    {
        // Insert some data
        $this->collection->insert(['name' => 'Test Item 1']);
        $this->collection->insert(['name' => 'Test Item 2']);

        // Verify original collection exists and has data
        $this->assertContains('original_collection', $this->db->getCollectionNames());
        $this->assertEquals(2, $this->collection->count());

        // Rename collection
        $result = $this->collection->renameCollection('renamed_collection');
        $this->assertTrue($result);

        // Verify new collection exists
        $this->assertContains('renamed_collection', $this->db->getCollectionNames());
        $this->assertNotContains('original_collection', $this->db->getCollectionNames());

        // Verify data was preserved
        $newCollection = $this->db->selectCollection('renamed_collection');
        $this->assertEquals(2, $newCollection->count());

        $items = $newCollection->find()->toArray();
        $this->assertCount(2, $items);
        $this->assertEquals('Test Item 1', $items[0]['name']);
        $this->assertEquals('Test Item 2', $items[1]['name']);
    }

    public function testRenameToExistingCollectionFails()
    {
        // Create two collections
        $this->collection->insert(['data' => 'original']);
        $existingCollection = $this->db->selectCollection('existing_collection');
        $existingCollection->insert(['data' => 'existing']);

        // Try to rename to existing collection
        $result = $this->collection->renameCollection('existing_collection');
        $this->assertFalse($result);

        // Verify both collections still exist
        $this->assertContains('original_collection', $this->db->getCollectionNames());
        $this->assertContains('existing_collection', $this->db->getCollectionNames());

        // Verify data is preserved in both
        $this->assertEquals(1, $this->collection->count());
        $this->assertEquals(1, $existingCollection->count());
    }

    public function testRenameEmptyCollection()
    {
        // Rename empty collection
        $result = $this->collection->renameCollection('empty_renamed');
        $this->assertTrue($result);

        // Verify rename worked
        $this->assertNotContains('original_collection', $this->db->getCollectionNames());
        $this->assertContains('empty_renamed', $this->db->getCollectionNames());

        // Verify new collection is accessible
        $newCollection = $this->db->selectCollection('empty_renamed');
        $this->assertEquals(0, $newCollection->count());
    }

    public function testRenameCollectionWithIndexes()
    {
        // Insert data and create index
        $this->collection->insert(['indexed_field' => 'value1']);
        $this->collection->insert(['indexed_field' => 'value2']);
        $this->db->createJsonIndex('original_collection', 'indexed_field');

        // Rename collection
        $result = $this->collection->renameCollection('indexed_renamed');
        $this->assertTrue($result);

        // Verify index is gone from old table (SQLite behavior)
        // and data is preserved
        $newCollection = $this->db->selectCollection('indexed_renamed');
        $this->assertEquals(2, $newCollection->count());

        // Should be able to query by indexed field
        $item = $newCollection->findOne(['indexed_field' => 'value1']);
        $this->assertEquals('value1', $item['indexed_field']);
    }

    public function testRenameCollectionWithSearchableFields()
    {
        // Configure searchable fields
        $this->collection->setSearchableFields(['search_field']);

        // Insert data
        $this->collection->insert(['search_field' => 'searchable_value']);

        // Rename collection
        $result = $this->collection->renameCollection('searchable_renamed');
        $this->assertTrue($result);

        // Verify new collection works
        $newCollection = $this->db->selectCollection('searchable_renamed');
        $item = $newCollection->findOne(['search_field' => 'searchable_value']);
        $this->assertNotNull($item);
    }

    public function testRenameCollectionUpdatesCollectionReference()
    {
        $originalName = $this->collection->name;
        $this->assertEquals('original_collection', $originalName);

        // Rename
        $this->collection->renameCollection('updated_collection');

        // Verify collection object was updated
        $this->assertEquals('updated_collection', $this->collection->name);
    }

    public function testRenameCollectionWithManyDocuments()
    {
        // Insert many documents
        for ($i = 0; $i < 100; ++$i) {
            $this->collection->insert(['index' => $i, 'data' => 'item_'.$i]);
        }

        $this->assertEquals(100, $this->collection->count());

        // Rename
        $result = $this->collection->renameCollection('large_renamed');
        $this->assertTrue($result);

        // Verify all data preserved
        $newCollection = $this->db->selectCollection('large_renamed');
        $this->assertEquals(100, $newCollection->count());

        // Verify specific items
        $item50 = $newCollection->findOne(['index' => 50]);
        $this->assertEquals('item_50', $item50['data']);
    }

    public function testRenameSameNameFails()
    {
        $this->collection->insert(['data' => 'test']);

        // Try to rename to same name
        $result = $this->collection->renameCollection('original_collection');
        $this->assertFalse($result);

        // Verify collection still exists with data
        $this->assertContains('original_collection', $this->db->getCollectionNames());
        $this->assertEquals(1, $this->collection->count());
    }

    public function testRenameCollectionWithSpecialCharacters()
    {
        $this->collection->insert(['data' => 'special']);

        // Rename with underscores and numbers
        $result = $this->collection->renameCollection('renamed_123_collection');
        $this->assertTrue($result);

        // Verify rename worked
        $this->assertContains('renamed_123_collection', $this->db->getCollectionNames());
        $this->assertNotContains('original_collection', $this->db->getCollectionNames());

        // Verify data preserved
        $newCollection = $this->db->selectCollection('renamed_123_collection');
        $this->assertEquals(1, $newCollection->count());
    }

    public function testRenameCollectionAfterOperations()
    {
        // Perform various operations
        $this->collection->insert(['data' => 'insert']);
        $this->collection->update(['data' => 'insert'], ['$set' => ['data' => 'updated']]);
        $this->assertEquals(1, $this->collection->count());

        // Rename
        $result = $this->collection->renameCollection('after_ops');
        $this->assertTrue($result);

        // Verify final state
        $newCollection = $this->db->selectCollection('after_ops');
        $this->assertEquals(1, $newCollection->count());
        $item = $newCollection->findOne();
        $this->assertEquals('updated', $item['data']);
    }
}
