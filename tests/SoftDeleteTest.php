<?php

namespace BangronDB\Tests;

use BangronDB\Collection;
use BangronDB\Database;
use PHPUnit\Framework\TestCase;

class SoftDeleteTest extends TestCase
{
    private Database $db;
    private Collection $collection;

    protected function setUp(): void
    {
        $this->db = new Database(':memory:');
        $this->collection = $this->db->selectCollection('testcollection');
        $this->collection->useSoftDeletes(true);
    }

    protected function tearDown(): void
    {
        $this->db->close();
    }

    public function testSoftDeleteEnabled()
    {
        $this->assertTrue($this->collection->softDeletesEnabled());
        $this->assertEquals('_deleted_at', $this->collection->getDeletedAtField());
    }

    public function testSoftDeleteBasic()
    {
        // Insert documents
        $id1 = $this->collection->insert(['name' => 'John', 'status' => 'active']);
        $id2 = $this->collection->insert(['name' => 'Jane', 'status' => 'active']);

        // Verify both documents exist
        $this->assertEquals(2, $this->collection->count());

        // Soft delete one document
        $deleted = $this->collection->remove(['name' => 'John']);
        $this->assertEquals(1, $deleted);

        // Active documents should be 1
        $activeDocs = $this->collection->find()->toArray();
        $this->assertCount(1, $activeDocs);
        $this->assertEquals('Jane', $activeDocs[0]['name']);

        // Including soft-deleted should be 2
        $allDocs = $this->collection->find()->withTrashed()->toArray();
        $this->assertCount(2, $allDocs);

        // Only soft-deleted should be 1
        $trashedDocs = $this->collection->find()->onlyTrashed()->toArray();
        $this->assertCount(1, $trashedDocs);
        $this->assertEquals('John', $trashedDocs[0]['name']);
        $this->assertArrayHasKey('_deleted_at', $trashedDocs[0]);
    }

    public function testRestoreSoftDeleted()
    {
        // Insert and soft delete
        $id = $this->collection->insert(['name' => 'John']);
        $this->collection->remove(['name' => 'John']);

        // Verify it's deleted
        $this->assertEquals(0, $this->collection->count());

        // Restore
        $restored = $this->collection->restore(['name' => 'John']);
        $this->assertEquals(1, $restored);

        // Verify it's restored
        $this->assertEquals(1, $this->collection->count());
        $doc = $this->collection->findOne(['name' => 'John']);
        $this->assertArrayNotHasKey('_deleted_at', $doc);
    }

    public function testForceDelete()
    {
        // Insert and soft delete
        $id = $this->collection->insert(['name' => 'John']);
        $this->collection->remove(['name' => 'John']);

        // Verify soft deleted
        $this->assertEquals(0, $this->collection->count());
        $this->assertEquals(1, $this->collection->find()->withTrashed()->count());

        // Force delete
        $forceDeleted = $this->collection->forceDelete(['name' => 'John']);
        $this->assertEquals(1, $forceDeleted);

        // Verify completely gone
        $this->assertEquals(0, $this->collection->find()->withTrashed()->count());
    }

    public function testSoftDeleteMultiple()
    {
        // Insert multiple documents
        $this->collection->insert([
            ['name' => 'John', 'status' => 'active'],
            ['name' => 'Jane', 'status' => 'active'],
            ['name' => 'Bob', 'status' => 'inactive'],
        ]);

        // Soft delete by criteria
        $deleted = $this->collection->remove(['status' => 'active']);
        $this->assertEquals(2, $deleted);

        // Check remaining
        $active = $this->collection->find()->toArray();
        $this->assertCount(1, $active);
        $this->assertEquals('Bob', $active[0]['name']);

        $all = $this->collection->find()->withTrashed()->toArray();
        $this->assertCount(3, $all);
    }

    public function testSoftDeleteWithCursorModifiers()
    {
        // Insert documents
        $this->collection->insert([
            ['name' => 'Active1', 'status' => 'active'],
            ['name' => 'Active2', 'status' => 'active'],
            ['name' => 'Deleted1', 'status' => 'deleted'],
        ]);

        // Soft delete some
        $this->collection->remove(['name' => 'Active1']);

        // Test cursor methods
        $cursor = $this->collection->find();

        // Default (without trashed)
        $this->assertEquals(2, $cursor->count());

        // With trashed
        $this->assertEquals(3, $cursor->withTrashed()->count());

        // Only trashed
        $this->assertEquals(1, $cursor->onlyTrashed()->count());
    }

    public function testSoftDeleteFieldCustomization()
    {
        $collection = $this->db->selectCollection('custom_soft_delete');
        $collection->useSoftDeletes(true);

        // Check default field
        $this->assertEquals('_deleted_at', $collection->getDeletedAtField());

        // Insert and delete
        $id = $collection->insert(['name' => 'Test']);
        $collection->remove(['name' => 'Test']);

        // Check deleted document has the field
        $deleted = $collection->find()->onlyTrashed()->toArray();
        $this->assertCount(1, $deleted);
        $this->assertArrayHasKey('_deleted_at', $deleted[0]);
    }

    public function testSoftDeletesDisabledByDefault()
    {
        $collection = $this->db->selectCollection('hard_delete_collection');
        $this->assertFalse($collection->softDeletesEnabled());

        // Insert and remove (should be hard delete)
        $id = $collection->insert(['name' => 'Test']);
        $collection->remove(['name' => 'Test']);

        // Should be completely gone
        $this->assertEquals(0, $collection->count());
    }
}
