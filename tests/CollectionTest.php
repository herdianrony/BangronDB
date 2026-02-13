<?php

namespace BangronDB\Tests;

use PHPUnit\Framework\TestCase;
use BangronDB\Collection;
use BangronDB\Database;

class CollectionTest extends TestCase
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

    public function testInsertSingleDocument()
    {
        $doc = ['name' => 'John', 'age' => 30];
        $id = $this->collection->insert($doc);
        $this->assertIsString($id);
        // Check that a document was inserted
        $found = $this->collection->findOne(['_id' => $id]);
        $this->assertEquals('John', $found['name']);
    }

    public function testInsertManyDocuments()
    {
        $docs = [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
        ];
        $count = $this->collection->insert($docs);
        $this->assertEquals(2, $count);
    }

    public function testFindAll()
    {
        $this->collection->insert(['name' => 'John']);
        $this->collection->insert(['name' => 'Jane']);

        $cursor = $this->collection->find();
        $results = $cursor->toArray();
        $this->assertCount(2, $results);
    }

    public function testFindWithCriteria()
    {
        $this->collection->insert(['name' => 'John', 'age' => 30]);
        $this->collection->insert(['name' => 'Jane', 'age' => 25]);

        $cursor = $this->collection->find(['age' => ['$gt' => 26]]);
        $results = $cursor->toArray();
        $this->assertCount(1, $results);
        $this->assertEquals('John', $results[0]['name']);
    }

    public function testFindOne()
    {
        $this->collection->insert(['name' => 'John']);
        $doc = $this->collection->findOne(['name' => 'John']);
        $this->assertIsArray($doc);
        $this->assertEquals('John', $doc['name']);
    }

    public function testUpdate()
    {
        $this->collection->insert(['name' => 'John', 'age' => 30]);
        $updated = $this->collection->update(['name' => 'John'], ['age' => 31]);
        $this->assertEquals(1, $updated);

        $doc = $this->collection->findOne(['name' => 'John']);
        $this->assertEquals(31, $doc['age']);
    }

    public function testRemove()
    {
        $this->collection->insert(['name' => 'John']);
        $this->collection->insert(['name' => 'Jane']);

        $removed = $this->collection->remove(['name' => 'John']);
        $this->assertEquals(1, $removed);

        $remaining = $this->collection->find()->toArray();
        $this->assertCount(1, $remaining);
        $this->assertEquals('Jane', $remaining[0]['name']);
    }

    public function testCount()
    {
        $this->collection->insert(['name' => 'John']);
        $this->collection->insert(['name' => 'Jane']);
        $count = $this->collection->count();
        $this->assertEquals(2, $count);
    }

    public function testSaveUpsert()
    {
        $doc = ['name' => 'John', 'age' => 30];
        $id = $this->collection->save($doc);
        $this->assertIsString($id);

        $doc['_id'] = $id;
        $doc['age'] = 31;
        $updatedId = $this->collection->save($doc);
        // Check that save returns the same ID (upsert behavior)
        $this->assertEquals($id, $updatedId);
        // Count should still be 1 (no new document inserted)
        $this->assertEquals(1, $this->collection->count());
        // Document should still exist
        $found = $this->collection->findOne(['_id' => $id]);
        $this->assertIsArray($found);
        $this->assertEquals('John', $found['name']);
    }

    public function testIdModes()
    {
        $collection = $this->db->selectCollection('auto');
        $doc = ['name' => 'Auto'];
        $id = $collection->insert($doc);
        $this->assertIsString($id);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $id); // UUID v4

        $collection2 = $this->db->selectCollection('manual');
        $collection2->setIdModeManual();
        $doc2 = ['_id' => 'custom-id', 'name' => 'Manual'];
        $id2 = $collection2->insert($doc2);
        $this->assertEquals('custom-id', $id2);
    }

    public function testEncryption()
    {
        $this->collection->setEncryptionKey('secretkey-with-32-chars-minimum!');
        $this->assertTrue($this->collection->isEncrypted());

        $doc = ['name' => 'Secret', 'data' => 'hidden'];
        $id = $this->collection->insert($doc);
        $found = $this->collection->findOne(['_id' => $id]);
        $this->assertEquals('Secret', $found['name']);
        $this->assertEquals('hidden', $found['data']);
    }

    public function testIdDecorations()
    {
        $collection = $this->db->selectCollection('decorated');
        $collection->setPrefix('PRE-');
        $collection->setSuffix('-SUF');

        $id = $collection->insert(['name' => 'test']);
        $this->assertStringStartsWith('PRE-', $id);
        $this->assertStringEndsWith('-SUF', $id);

        $collection2 = $this->db->selectCollection('decorated_prefix_mode');
        $collection2->setIdModePrefix('USR');
        $collection2->setSuffix('-SUF');

        $id2 = $collection2->insert(['name' => 'test2']);
        $this->assertEquals('USR-000001-SUF', $id2);
    }
}
