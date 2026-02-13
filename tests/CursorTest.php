<?php

namespace BangronDB\Tests;

use PHPUnit\Framework\TestCase;
use BangronDB\Collection;
use BangronDB\Database;

class CursorTest extends TestCase
{
    private Database $db;
    private Collection $collection;

    protected function setUp(): void
    {
        $this->db = new Database(':memory:');
        $this->collection = $this->db->selectCollection('testcollection');

        // Insert test data
        $this->collection->insert(['name' => 'John', 'age' => 30]);
        $this->collection->insert(['name' => 'Jane', 'age' => 25]);
        $this->collection->insert(['name' => 'Bob', 'age' => 35]);
    }

    protected function tearDown(): void
    {
        $this->db->close();
    }

    public function testCount()
    {
        $cursor = $this->collection->find();
        $this->assertEquals(3, $cursor->count());
    }

    public function testLimit()
    {
        $cursor = $this->collection->find()->limit(2);
        $results = $cursor->toArray();
        $this->assertCount(2, $results);
    }

    public function testSkip()
    {
        $cursor = $this->collection->find()->skip(1);
        $results = $cursor->toArray();
        $this->assertCount(2, $results);
    }

    public function testLimitAndSkip()
    {
        $cursor = $this->collection->find()->skip(1)->limit(1);
        $results = $cursor->toArray();
        $this->assertCount(1, $results);
    }

    public function testSortAsc()
    {
        $cursor = $this->collection->find()->sort(['age' => 1]);
        $results = $cursor->toArray();
        $this->assertEquals(25, $results[0]['age']);
        $this->assertEquals(30, $results[1]['age']);
        $this->assertEquals(35, $results[2]['age']);
    }

    public function testSortDesc()
    {
        $cursor = $this->collection->find()->sort(['age' => -1]);
        $results = $cursor->toArray();
        $this->assertEquals(35, $results[0]['age']);
        $this->assertEquals(30, $results[1]['age']);
        $this->assertEquals(25, $results[2]['age']);
    }

    public function testToArray()
    {
        $cursor = $this->collection->find();
        $results = $cursor->toArray();
        $this->assertIsArray($results);
        $this->assertCount(3, $results);
        foreach ($results as $doc) {
            $this->assertArrayHasKey('name', $doc);
            $this->assertArrayHasKey('age', $doc);
        }
    }

    public function testIterator()
    {
        $cursor = $this->collection->find();
        $count = 0;
        foreach ($cursor as $doc) {
            $this->assertIsArray($doc);
            ++$count;
        }
        $this->assertEquals(3, $count);
    }

    public function testCurrentAndNext()
    {
        $cursor = $this->collection->find()->limit(2);
        $cursor->rewind();
        $first = $cursor->current();
        $this->assertIsArray($first);

        $cursor->next();
        $second = $cursor->current();
        $this->assertIsArray($second);
        $this->assertNotEquals($first['_id'], $second['_id']);
    }

    public function testValid()
    {
        $cursor = $this->collection->find()->limit(1);
        $cursor->rewind();
        $this->assertTrue($cursor->valid());
        $cursor->next();
        $this->assertFalse($cursor->valid());
    }
}
