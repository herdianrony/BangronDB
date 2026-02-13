<?php

namespace BangronDB\Tests;

use BangronDB\Collection;
use BangronDB\Database;
use PHPUnit\Framework\TestCase;

class SchemaValidationTest extends TestCase
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

    public function testSchemaValidationSuccess()
    {
        $this->collection->setSchema([
            'name' => ['required' => true, 'type' => 'string', 'min' => 2, 'max' => 50],
            'email' => ['required' => true, 'type' => 'string', 'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
            'age' => ['type' => 'int', 'min' => 0, 'max' => 150],
        ]);

        $doc = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ];

        $id = $this->collection->insert($doc);
        $this->assertIsString($id);

        $found = $this->collection->findOne(['_id' => $id]);
        $this->assertEquals('John Doe', $found['name']);
    }

    public function testSchemaValidationRequiredField()
    {
        $this->collection->setSchema([
            'name' => ['required' => true, 'type' => 'string'],
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Field \'name\' is required');

        $this->collection->insert(['email' => 'john@example.com']);
    }

    public function testSchemaValidationTypeMismatch()
    {
        $this->collection->setSchema([
            'age' => ['type' => 'int'],
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Field \'age\' must be of type \'int\'');

        $this->collection->insert(['age' => 'thirty']);
    }

    public function testSchemaValidationMinMax()
    {
        $this->collection->setSchema([
            'age' => ['type' => 'int', 'min' => 0, 'max' => 150],
        ]);

        // Test min
        $this->expectException(\Exception::class);
        $this->collection->insert(['age' => -5]);

        // Test max
        $this->expectException(\Exception::class);
        $this->collection->insert(['age' => 200]);
    }

    public function testSchemaValidationRegex()
    {
        $this->collection->setSchema([
            'email' => ['regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('does not match pattern');

        $this->collection->insert(['email' => 'invalid-email']);
    }

    public function testSchemaValidationEnum()
    {
        $this->collection->setSchema([
            'role' => ['enum' => ['admin', 'user', 'moderator']],
        ]);

        // Valid enum value
        $id = $this->collection->insert(['role' => 'admin']);
        $this->assertIsString($id);

        // Invalid enum value
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('must be one of: admin, user, moderator');

        $this->collection->insert(['role' => 'guest']);
    }

    public function testSchemaValidationArrayType()
    {
        $this->collection->setSchema([
            'tags' => ['type' => 'array', 'max' => 5],
        ]);

        // Valid array
        $id = $this->collection->insert(['tags' => ['php', 'mysql', 'javascript']]);
        $this->assertIsString($id);

        // Array too large
        $this->expectException(\Exception::class);
        $this->collection->insert(['tags' => range(1, 10)]); // 10 items
    }

    public function testSchemaValidationStringLength()
    {
        $this->collection->setSchema([
            'name' => ['type' => 'string', 'min' => 2, 'max' => 10],
        ]);

        // Valid length
        $id = $this->collection->insert(['name' => 'John']);
        $this->assertIsString($id);

        // Too short
        $this->expectException(\Exception::class);
        $this->collection->insert(['name' => 'A']);

        // Too long
        $this->expectException(\Exception::class);
        $this->collection->insert(['name' => 'ThisNameIsTooLong']);
    }

    public function testGetSchema()
    {
        $schema = [
            'name' => ['required' => true, 'type' => 'string'],
            'email' => ['type' => 'string'],
        ];

        $this->collection->setSchema($schema);
        $retrievedSchema = $this->collection->getSchema();

        $this->assertEquals($schema, $retrievedSchema);
    }
}
