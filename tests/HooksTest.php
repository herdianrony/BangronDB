<?php

namespace BangronDB\Tests;

use BangronDB\Collection;
use BangronDB\Database;
use PHPUnit\Framework\TestCase;

class HooksTest extends TestCase
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

    public function testOnRegistersHook()
    {
        $called = false;
        $this->collection->on('beforeInsert', function ($doc) use (&$called) {
            $called = true;

            return $doc;
        });

        $hooks = $this->collection->getHooks();
        $this->assertArrayHasKey('beforeInsert', $hooks);
        $this->assertCount(1, $hooks['beforeInsert']);
        $this->assertIsCallable($hooks['beforeInsert'][0]);
    }

    public function testOffRemovesSpecificHook()
    {
        $hook1 = function ($doc) { return $doc; };
        $hook2 = function ($doc) { return $doc; };

        $this->collection->on('beforeInsert', $hook1);
        $this->collection->on('beforeInsert', $hook2);

        $this->assertCount(2, $this->collection->getHooks()['beforeInsert']);

        $this->collection->off('beforeInsert', $hook1);

        $this->assertCount(1, $this->collection->getHooks()['beforeInsert']);
    }

    public function testOffRemovesAllHooksForEvent()
    {
        $this->collection->on('beforeInsert', function ($doc) { return $doc; });
        $this->collection->on('beforeInsert', function ($doc) { return $doc; });

        $this->assertCount(2, $this->collection->getHooks()['beforeInsert']);

        $this->collection->off('beforeInsert');

        $this->assertArrayNotHasKey('beforeInsert', $this->collection->getHooks());
    }

    public function testBeforeInsertHookModifiesDocument()
    {
        $this->collection->on('beforeInsert', function ($doc) {
            $doc['modified'] = true;

            return $doc;
        });

        $result = $this->collection->insert(['name' => 'test']);
        $this->assertIsString($result);

        $doc = $this->collection->findOne(['name' => 'test']);
        $this->assertTrue($doc['modified']);
    }

    public function testBeforeInsertHookCancelsInsert()
    {
        $this->collection->on('beforeInsert', function ($doc) {
            return false; // Cancel insertion
        });

        $result = $this->collection->insert(['name' => 'test']);
        $this->assertFalse($result);

        $count = $this->collection->count();
        $this->assertEquals(0, $count);
    }

    public function testAfterInsertHookReceivesDocumentAndId()
    {
        $receivedDoc = null;
        $receivedId = null;

        $this->collection->on('afterInsert', function ($doc, $id) use (&$receivedDoc, &$receivedId) {
            $receivedDoc = $doc;
            $receivedId = $id;
        });

        $result = $this->collection->insert(['name' => 'test']);
        $this->assertIsString($result);

        $this->assertIsArray($receivedDoc);
        $this->assertEquals('test', $receivedDoc['name']);
        $this->assertEquals($result, $receivedId);
    }

    public function testBeforeUpdateHookModifiesCriteriaAndData()
    {
        $this->collection->insert(['name' => 'original', 'value' => 1]);

        $this->collection->on('beforeUpdate', function ($criteria, $data) {
            return [
                ['name' => 'modified'], // New criteria
                ['$set' => ['value' => 999]], // New data
            ];
        });

        $this->collection->update(['name' => 'original'], ['$set' => ['value' => 2]]);

        $doc = $this->collection->findOne();
        $this->assertEquals('original', $doc['name']); // Criteria didn't match modified document
        $this->assertEquals(1, $doc['value']); // Data wasn't updated
    }

    public function testAfterUpdateHookReceivesOriginalAndUpdatedDocument()
    {
        $this->collection->insert(['name' => 'test', 'value' => 1]);

        $originalDoc = null;
        $updatedDoc = null;

        $this->collection->on('afterUpdate', function ($orig, $updated) use (&$originalDoc, &$updatedDoc) {
            $originalDoc = $orig;
            $updatedDoc = $updated;
        });

        $this->collection->update(['name' => 'test'], ['$set' => ['value' => 2]]);

        $this->assertIsArray($originalDoc);
        $this->assertIsArray($updatedDoc);
        $this->assertEquals(1, $originalDoc['value']);
        $this->assertEquals(2, $updatedDoc['value']);
    }

    public function testBeforeRemoveHookCancelsRemoval()
    {
        $this->collection->insert(['name' => 'test']);

        $this->collection->on('beforeRemove', function ($doc) {
            return false; // Cancel removal
        });

        $deleted = $this->collection->remove(['name' => 'test']);
        $this->assertEquals(0, $deleted);

        $count = $this->collection->count();
        $this->assertEquals(1, $count);
    }

    public function testAfterRemoveHookReceivesRemovedDocument()
    {
        $this->collection->insert(['name' => 'test']);

        $removedDoc = null;
        $this->collection->on('afterRemove', function ($doc) use (&$removedDoc) {
            $removedDoc = $doc;
        });

        $deleted = $this->collection->remove(['name' => 'test']);
        $this->assertEquals(1, $deleted);

        $this->assertIsArray($removedDoc);
        $this->assertEquals('test', $removedDoc['name']);
    }

    public function testHookExceptionDoesNotPreventOperation()
    {
        $this->collection->on('beforeInsert', function ($doc) {
            throw new \Exception('Hook error');
        });

        $this->collection->on('beforeInsert', function ($doc) {
            $doc['processed'] = true;

            return $doc;
        });

        $result = $this->collection->insert(['name' => 'test']);
        $this->assertIsString($result);

        $doc = $this->collection->findOne();
        $this->assertEquals('test', $doc['name']);
        $this->assertTrue($doc['processed']);
    }

    public function testMultipleHooksExecuteInOrder()
    {
        $order = [];

        $this->collection->on('beforeInsert', function ($doc) use (&$order) {
            $order[] = 1;
            $doc['step1'] = true;

            return $doc;
        });

        $this->collection->on('beforeInsert', function ($doc) use (&$order) {
            $order[] = 2;
            $doc['step2'] = true;

            return $doc;
        });

        $this->collection->insert(['name' => 'test']);

        $this->assertEquals([1, 2], $order);

        $doc = $this->collection->findOne();
        $this->assertTrue($doc['step1']);
        $this->assertTrue($doc['step2']);
    }
}
