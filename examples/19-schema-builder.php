<?php

/**
 * Contoh 19: Schema Builder - Headless CMS Style.
 *
 * Demonstrasi BangronDB sebagai Headless CMS dengan:
 * - Dynamic collection creation
 * - Schema definition via API
 * - Field type system
 * - Auto-generated CRUD operations
 * - Schema migration
 */

require_once __DIR__.'/bootstrap.php';

use BangronDB\Client;
use BangronDB\Database;
use BangronDB\Exceptions\ValidationException;

echo "=== Contoh 19: Schema Builder - Headless CMS ===\n\n";

$client = createIsolatedClient('cms_demo');

// ============================================
// 1. Schema Builder Class
// ============================================
class SchemaBuilder
{
    private Client $client;
    private Database $metaDb;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->metaDb = $client->selectDB('_bangron_meta');
    }

    // Field types yang tersedia
    public function getFieldTypes(): array
    {
        return [
            'string' => ['phpType' => 'string', 'sqlType' => 'TEXT'],
            'text' => ['phpType' => 'string', 'sqlType' => 'TEXT', 'long' => true],
            'integer' => ['phpType' => 'integer', 'sqlType' => 'INTEGER'],
            'number' => ['phpType' => 'double', 'sqlType' => 'REAL'],
            'boolean' => ['phpType' => 'boolean', 'sqlType' => 'INTEGER'],
            'email' => ['phpType' => 'string', 'sqlType' => 'TEXT', 'format' => 'email'],
            'date' => ['phpType' => 'string', 'sqlType' => 'TEXT', 'format' => 'date'],
            'datetime' => ['phpType' => 'string', 'sqlType' => 'TEXT', 'format' => 'datetime'],
            'json' => ['phpType' => 'array', 'sqlType' => 'TEXT'],
            'relation' => ['phpType' => 'string', 'sqlType' => 'TEXT', 'rel' => true],
        ];
    }

    // Create collection dengan schema
    public function createCollection(string $name, array $schema): array
    {
        // Validasi schema
        $this->validateSchema($schema);

        // Build schema with defaults
        $fullSchema = $this->buildSchema($schema);

        // Create collection
        $db = $this->client->selectDB($name);
        $collection = $db->{$name};

        // Set schema
        $collection->setSchema($fullSchema);
        $collection->saveConfiguration();

        // Create indexes for indexed fields
        foreach ($fullSchema as $field => $config) {
            if (isset($config['index']) && $config['index']) {
                $collection->createIndex($field);
            }
        }

        return [
            'collection' => $name,
            'fields' => count($schema),
            'status' => 'created',
        ];
    }

    // Validate schema structure
    private function validateSchema(array $schema): void
    {
        foreach ($schema as $field => $config) {
            if (!is_array($config)) {
                throw new ValidationException("Field '$field' must be an array configuration");
            }

            $allowedKeys = ['type', 'required', 'min', 'max', 'enum', 'default', 'index', 'unique', 'description'];
            foreach ($config as $key => $value) {
                if (!in_array($key, $allowedKeys)) {
                    throw new ValidationException("Unknown key '$key' for field '$field'");
                }
            }

            // Validate type
            $validTypes = array_keys($this->getFieldTypes());
            if (isset($config['type']) && !in_array($config['type'], $validTypes)) {
                throw new ValidationException("Invalid type '{$config['type']}'. Valid types: ".implode(', ', $validTypes));
            }
        }
    }

    // Build full schema dengan defaults
    private function buildSchema(array $schema): array
    {
        $fullSchema = [];
        foreach ($schema as $field => $config) {
            $type = $config['type'] ?? 'string';
            $fullSchema[$field] = array_merge([
                'type' => $type,
                'required' => $config['required'] ?? false,
                'index' => $config['index'] ?? false,
            ], $config);
        }

        return $fullSchema;
    }

    // Add field ke existing collection
    public function addField(string $collection, string $field, array $config): array
    {
        $db = $this->client->selectDB($collection);
        $coll = $db->{$collection};
        $currentSchema = $coll->database->loadCollectionConfig($collection)['schema'] ?? [];

        $this->validateSchema([$field => $config]);

        // Add new field
        $fullSchema = $this->buildSchema([$field => $config]);
        $currentSchema = array_merge($currentSchema, $fullSchema);

        // Update schema
        $coll->setSchema($currentSchema);
        $coll->saveConfiguration();

        // Create index if needed
        if (isset($config['index']) && $config['index']) {
            $coll->createIndex($field);
        }

        return [
            'collection' => $collection,
            'field' => $field,
            'status' => 'added',
        ];
    }

    // Get collection schema
    public function getSchema(string $collection): ?array
    {
        try {
            $db = $this->client->selectDB($collection);
            $coll = $db->{$collection};

            return $coll->database->loadCollectionConfig($collection)['schema'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }

    // List all collections
    public function listCollections(): array
    {
        $dbs = $this->client->listDBs();
        $collections = [];

        foreach ($dbs as $dbName) {
            if (str_starts_with($dbName, '_')) {
                continue;
            } // Skip internal

            try {
                $db = $this->client->selectDB($dbName);
                $collList = $db->listCollections();
                foreach ($collList as $collName => $collection) {
                    $schema = $this->getSchema($collName);
                    if ($schema) {
                        $collections[] = [
                            'database' => $dbName,
                            'collection' => $collName,
                            'fields' => count($schema),
                            'schema' => $schema,
                        ];
                    }
                }
            } catch (Exception $e) {
                // Skip inaccessible databases
            }
        }

        return $collections;
    }

    // CRUD Operations - Create
    public function insert(string $collection, array $data): string
    {
        $db = $this->client->selectDB($collection);

        return $db->{$collection}->insert($data);
    }

    // CRUD Operations - Read
    public function find(string $collection, array $criteria = []): array
    {
        $db = $this->client->selectDB($collection);

        return $db->{$collection}->find($criteria)->toArray();
    }

    public function findOne(string $collection, array $criteria = []): ?array
    {
        $db = $this->client->selectDB($collection);

        return $db->{$collection}->findOne($criteria);
    }

    // CRUD Operations - Update
    public function update(string $collection, array $criteria, array $data): int
    {
        $db = $this->client->selectDB($collection);

        return $db->{$collection}->update($criteria, $data);
    }

    // CRUD Operations - Delete
    public function delete(string $collection, array $criteria): int
    {
        $db = $this->client->selectDB($collection);

        return $db->{$collection}->remove($criteria);
    }
}

// ============================================
// 2. Demo: Headless CMS Usage
// ============================================
echo "1. Initialize Schema Builder\n";
echo "-----------------------------\n";

$builder = new SchemaBuilder($client);
echo "Schema builder initialized.\n";
echo 'Available field types: '.implode(', ', array_keys($builder->getFieldTypes()))."\n";

// ============================================
// 3. Create Collections via Schema Builder
// ============================================
echo "\n2. Create Collections\n";
echo "----------------------\n";

// Create 'posts' collection (blog posts)
$result = $builder->createCollection('posts', [
    'title' => ['type' => 'string', 'required' => true, 'index' => true],
    'slug' => ['type' => 'string', 'required' => true, 'unique' => true, 'index' => true],
    'content' => ['type' => 'text', 'required' => true],
    'excerpt' => ['type' => 'string'],
    'status' => ['type' => 'string', 'enum' => ['draft', 'published', 'archived'], 'default' => 'draft'],
    'author_id' => ['type' => 'relation', 'index' => true],
    'published_at' => ['type' => 'datetime'],
    'tags' => ['type' => 'json', 'description' => 'Array of tags'],
    'metadata' => ['type' => 'json', 'description' => 'SEO metadata'],
]);
echo "Created posts: {$result['fields']} fields\n";

// Create 'categories' collection
$result = $builder->createCollection('categories', [
    'name' => ['type' => 'string', 'required' => true, 'index' => true],
    'slug' => ['type' => 'string', 'required' => true, 'unique' => true],
    'description' => ['type' => 'text'],
    'parent_id' => ['type' => 'relation', 'description' => 'Parent category'],
    'order' => ['type' => 'integer', 'default' => 0],
]);
echo "Created categories: {$result['fields']} fields\n";

// ============================================
// 4. CRUD Operations via Schema Builder
// ============================================
echo "\n3. CRUD Operations\n";
echo "------------------\n";

// Insert data
$postId = $builder->insert('posts', [
    'title' => 'Hello World',
    'slug' => 'hello-world',
    'content' => 'This is my first post using BangronDB!',
    'status' => 'published',
    'published_at' => date('c'),
    'tags' => ['welcome', 'first'],
    'metadata' => ['seo_title' => 'Hello World', 'seo_description' => 'First post'],
]);
echo "Inserted post: $postId\n";

$categoryId = $builder->insert('categories', [
    'name' => 'Technology',
    'slug' => 'technology',
    'description' => 'Tech related posts',
    'order' => 1,
]);
echo "Inserted category: $categoryId\n";

// Read data
echo "\nFind posts:\n";
$posts = $builder->find('posts');
foreach ($posts as $post) {
    echo "  - {$post['title']} ({$post['status']})\n";
}

// Update data
$updated = $builder->update('posts', ['_id' => $postId], [
    'title' => 'Hello World (Updated)',
    'status' => 'archived',
]);
echo "\nUpdated $updated document(s)\n";

// ============================================
// 5. Add Field via Schema Builder
// ============================================
echo "\n4. Add Field to Existing Collection\n";
echo "-------------------------------------\n";

$result = $builder->addField('posts', 'featured_image', [
    'type' => 'string',
    'description' => 'URL to featured image',
]);
echo "Added field 'featured_image' to posts\n";

// Verify schema updated
$schema = $builder->getSchema('posts');
echo 'Posts now has '.count($schema)." fields\n";

// ============================================
// 6. List All Collections
// ============================================
echo "\n5. List All Collections\n";
echo "-----------------------\n";

$collections = $builder->listCollections();
foreach ($collections as $coll) {
    echo "{$coll['database']}.{$coll['collection']}: {$coll['fields']} fields\n";
}

// ============================================
// 7. Dynamic Query dengan Criteria
// ============================================
echo "\n6. Dynamic Query\n";
echo "----------------\n";

$builder->insert('posts', [
    'title' => 'Second Post',
    'slug' => 'second-post',
    'content' => 'Another post...',
    'status' => 'draft',
    'tags' => ['draft'],
]);

$draftPosts = $builder->find('posts', ['status' => 'draft']);
echo 'Draft posts: '.count($draftPosts)."\n";

$publishedPosts = $builder->find('posts', ['status' => 'published']);
echo 'Published posts: '.count($publishedPosts)."\n";

// ============================================
// 8. Schema Migration Info
// ============================================
echo "\n7. Schema Information\n";
echo "--------------------\n";

$postsSchema = $builder->getSchema('posts');
echo "Posts schema:\n";
foreach ($postsSchema as $field => $config) {
    if ($field === '_relationships') {
        continue;
    }

    $type = $config['type'];
    $required = $config['required'] ? ' (required)' : '';
    $index = $config['index'] ? ' [index]' : '';
    echo "  $field: $type$required$index\n";
}

if (isset($postsSchema['_relationships'])) {
    echo "\nRelationships:\n";
    foreach ($postsSchema['_relationships'] as $name => $rel) {
        echo "  $name: {$rel['type']} → {$rel['collection']}\n";
    }
}

// ============================================
// Summary
// ============================================
echo "\n=== Summary ===\n";
echo "Schema Builder Features:\n";
echo "  1. createCollection() - Create collection with schema\n";
echo "  2. addField() - Add field to existing collection\n";
echo "  3. getSchema() - Get collection schema\n";
echo "  4. listCollections() - List all collections\n";
echo "  5. CRUD: insert(), find(), update(), delete()\n\n";
echo "Field Types:\n";
echo "  - string, text, integer, number, boolean\n";
echo "  - email, date, datetime, json, relation\n\n";
echo "Field Options:\n";
echo "  - type, required, min, max, enum, default\n";
echo "  - index, unique, description\n";

// Cleanup
foreach ($client->listDBs() as $dbName) {
    if ($dbName !== '_bangron_meta') {
        @$client->selectDB($dbName)->drop();
    }
}
$client->close();
echo "\nCleanup done.\n";
