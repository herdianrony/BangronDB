<?php

namespace BangronDB\Tests;

use BangronDB\Collection;
use BangronDB\Database;
use PHPUnit\Framework\TestCase;

class SearchTest extends TestCase
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

    public function testSetSearchableFieldsCreatesColumns()
    {
        $this->collection->setSearchableFields(['name', 'email']);

        $searchable = $this->collection->getSearchableFields();
        $this->assertArrayHasKey('name', $searchable);
        $this->assertArrayHasKey('email', $searchable);
        $this->assertFalse($searchable['name']['hash']);
        $this->assertFalse($searchable['email']['hash']);
    }

    public function testSetSearchableFieldsWithHash()
    {
        $this->collection->setSearchableFields(['password'], true);

        $searchable = $this->collection->getSearchableFields();
        $this->assertArrayHasKey('password', $searchable);
        $this->assertTrue($searchable['password']['hash']);
    }

    public function testInsertWithSearchableFields()
    {
        $this->collection->setSearchableFields(['name', 'age']);

        $result = $this->collection->insert([
            'name' => 'John Doe',
            'age' => 30,
            'email' => 'john@example.com',
        ]);

        $this->assertIsString($result);

        // Verify document was inserted correctly
        $doc = $this->collection->findOne(['name' => 'John Doe']);
        $this->assertEquals('John Doe', $doc['name']);
        $this->assertEquals(30, $doc['age']);
    }

    public function testSearchBySearchableField()
    {
        $this->collection->setSearchableFields(['category', 'status']);

        $this->collection->insert([
            'name' => 'Product A',
            'category' => 'electronics',
            'status' => 'active',
        ]);

        $this->collection->insert([
            'name' => 'Product B',
            'category' => 'books',
            'status' => 'inactive',
        ]);

        // Search by category
        $results = $this->collection->find(['category' => 'electronics'])->toArray();
        $this->assertCount(1, $results);
        $this->assertEquals('Product A', $results[0]['name']);

        // Search by status
        $results = $this->collection->find(['status' => 'inactive'])->toArray();
        $this->assertCount(1, $results);
        $this->assertEquals('Product B', $results[0]['name']);
    }

    public function testSearchWithHashedField()
    {
        $this->collection->setSearchableFields(['password'], true);

        $this->collection->insert([
            'username' => 'user1',
            'password' => 'secret123',
        ]);

        // Search still works by plain text since find() uses criteria function on document
        $results = $this->collection->find(['password' => 'secret123'])->toArray();
        $this->assertCount(1, $results);

        $doc = $results[0];
        $this->assertEquals('user1', $doc['username']);
        $this->assertEquals('secret123', $doc['password']);
    }

    public function testSearchNestedFieldsWithDotNotation()
    {
        $this->collection->setSearchableFields(['user.name', 'user.email']);

        $this->collection->insert([
            'title' => 'Post 1',
            'user' => [
                'name' => 'Alice',
                'email' => 'alice@example.com',
            ],
        ]);

        $this->collection->insert([
            'title' => 'Post 2',
            'user' => [
                'name' => 'Bob',
                'email' => 'bob@example.com',
            ],
        ]);

        // Search by nested field
        $results = $this->collection->find(['user.name' => 'Alice'])->toArray();
        $this->assertCount(1, $results);
        $this->assertEquals('Post 1', $results[0]['title']);
    }

    public function testSearchArrayField()
    {
        $this->collection->setSearchableFields(['tags']);

        $this->collection->insert([
            'title' => 'Article 1',
            'tags' => ['php', 'testing', 'database'],
        ]);

        $this->collection->insert([
            'title' => 'Article 2',
            'tags' => ['javascript', 'frontend'],
        ]);

        // Search by array field using criteria function
        $results = $this->collection->find(['tags' => ['$in' => ['php']]])->toArray();
        $this->assertCount(1, $results);
        $this->assertEquals('Article 1', $results[0]['title']);
    }

    public function testRemoveSearchableField()
    {
        $this->collection->setSearchableFields(['name', 'email', 'age']);
        $this->assertCount(3, $this->collection->getSearchableFields());

        $this->collection->removeSearchableField('email');
        $this->assertCount(2, $this->collection->getSearchableFields());
        $this->assertArrayNotHasKey('email', $this->collection->getSearchableFields());
    }

    public function testSearchFieldsPersistAfterReopeningCollection()
    {
        $this->collection->setSearchableFields(['priority', 'type']);
        $this->collection->saveConfiguration();

        // Simulate reopening collection
        $freshCollection = $this->db->selectCollection('test_collection');

        $searchable = $freshCollection->getSearchableFields();
        $this->assertArrayHasKey('priority', $searchable);
        $this->assertArrayHasKey('type', $searchable);
    }

    public function testSearchComplexQuery()
    {
        $this->collection->setSearchableFields(['category', 'price', 'active']);

        $this->collection->insert([
            'name' => 'Item 1',
            'category' => 'electronics',
            'price' => 100,
            'active' => true,
        ]);

        $this->collection->insert([
            'name' => 'Item 2',
            'category' => 'electronics',
            'price' => 200,
            'active' => false,
        ]);

        $this->collection->insert([
            'name' => 'Item 3',
            'category' => 'books',
            'price' => 50,
            'active' => true,
        ]);

        // Complex query using multiple searchable fields
        $results = $this->collection->find([
            'category' => 'electronics',
            'active' => true,
        ])->toArray();

        $this->assertCount(1, $results);
        $this->assertEquals('Item 1', $results[0]['name']);
    }

    public function testSearchNonExistentField()
    {
        $this->collection->setSearchableFields(['name']);

        $this->collection->insert(['name' => 'Test', 'other' => 'value']);

        // Should find by searchable field
        $results = $this->collection->find(['name' => 'Test'])->toArray();
        $this->assertCount(1, $results);

        // Should not find by non-searchable field (falls back to criteria function)
        $results = $this->collection->find(['other' => 'value'])->toArray();
        $this->assertCount(1, $results);
    }
}
