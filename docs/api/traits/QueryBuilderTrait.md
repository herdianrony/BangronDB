# QueryBuilderTrait API Reference

Trait untuk membangun dan mengeksekusi query MongoDB-like dengan operator advanced.

## Trait Overview

```php
trait BangronDB\Traits\QueryBuilderTrait
{
    // Query Building
    public function find($criteria = null, $projection = null): Cursor
    public function findOne($criteria = null, $projection = null): ?array
    public function count($criteria = null): int

    // Advanced Query Operators
    protected function processCriteria(array $criteria): array
    protected function evaluateCondition($value, $condition): bool
    protected function matchDocument(array $criteria, array $document): bool
}
```

## Query Methods

### `find($criteria = null, $projection = null): Cursor`

Mencari dokumen berdasarkan kriteria dengan optional projection.

**Parameters:**

- `$criteria` (mixed): Kriteria query (null untuk semua dokumen)
- `$projection` (mixed): Fields yang akan di-return (null untuk semua fields)

**Return:** `Cursor` instance

**Examples:**

```php
// Find all documents
$allUsers = $collection->find()->toArray();

// Find with criteria
$activeUsers = $collection->find(['status' => 'active'])->toArray();

// Find with projection
$userNames = $collection->find(['role' => 'admin'], ['name' => 1, 'email' => 1])->toArray();

// Find with MongoDB-like operators
$youngUsers = $collection->find(['age' => ['$gte' => 18, '$lt' => 30]])->toArray();
```

### `findOne($criteria = null, $projection = null): ?array`

Mencari satu dokumen berdasarkan kriteria.

**Parameters:**

- `$criteria` (mixed): Kriteria query
- `$projection` (mixed): Fields yang akan di-return

**Return:** `?array` - Dokumen atau null jika tidak ditemukan

**Examples:**

```php
$user = $collection->findOne(['email' => 'john@example.com']);
if ($user) {
    echo "Found user: " . $user['name'];
}
```

### `count($criteria = null): int`

Menghitung jumlah dokumen yang cocok dengan kriteria.

**Parameters:**

- `$criteria` (mixed): Kriteria query (null untuk menghitung semua)

**Return:** `int` - Jumlah dokumen

**Examples:**

```php
$totalUsers = $collection->count();
$activeUsers = $collection->count(['status' => 'active']);
```

## MongoDB-like Query Operators

### Comparison Operators

#### `$eq` - Equal

```php
$collection->find(['status' => ['$eq' => 'active']]);
```

#### `$ne` - Not Equal

```php
$collection->find(['status' => ['$ne' => 'inactive']]);
```

#### `$gt` - Greater Than

```php
$collection->find(['age' => ['$gt' => 18]]);
```

#### `$gte` - Greater Than or Equal

```php
$collection->find(['score' => ['$gte' => 85]]);
```

#### `$lt` - Less Than

```php
$collection->find(['price' => ['$lt' => 100]]);
```

#### `$lte` - Less Than or Equal

```php
$collection->find(['quantity' => ['$lte' => 10]]);
```

### Logical Operators

#### `$and` - Logical AND

```php
$collection->find([
    '$and' => [
        ['age' => ['$gte' => 18]],
        ['status' => 'active']
    ]
]);
```

#### `$or` - Logical OR

```php
$collection->find([
    '$or' => [
        ['role' => 'admin'],
        ['department' => 'IT']
    ]
]);
```

#### `$nor` - Logical NOR

```php
$collection->find([
    '$nor' => [
        ['status' => 'inactive'],
        ['deleted' => true]
    ]
]);
```

#### `$not` - Logical NOT

```php
$collection->find([
    'age' => ['$not' => ['$gt' => 65]]
]);
```

### Array Operators

#### `$in` - Value in Array

```php
$collection->find(['role' => ['$in' => ['admin', 'moderator']]]);
$collection->find(['tags' => ['$in' => ['php', 'javascript']]]);
```

#### `$nin` - Value not in Array

```php
$collection->find(['status' => ['$nin' => ['banned', 'suspended']]]);
```

#### `$all` - Match all values in array

```php
$collection->find(['tags' => ['$all' => ['php', 'mysql']]]);
```

#### `$size` - Array size

```php
$collection->find(['tags' => ['$size' => 3]]);
$collection->find(['comments' => ['$size' => ['$gte' => 5]]]);
```

### Element Operators

#### `$exists` - Field exists

```php
$collection->find(['phone' => ['$exists' => true]]);
$collection->find(['deleted_at' => ['$exists' => false]]);
```

#### `$type` - Field type (simplified implementation)

```php
$collection->find(['age' => ['$type' => 'integer']]);
```

### Regex Operators

#### `$regex` - Regular expression

```php
$collection->find(['email' => ['$regex' => '@gmail\.com$']]);
$collection->find(['name' => ['$regex' => '^John', '$options' => 'i']]);
```

#### `$options` - Regex options

```php
$collection->find([
    'description' => [
        '$regex' => 'web',
        '$options' => 'i' // Case insensitive
    ]
]);
```

### Custom Operators

#### `$where` - Custom function evaluation

```php
$collection->find([
    '$where' => function($doc) {
        return strlen($doc['bio']) > 100 && $doc['verified'] === true;
    }
]);
```

#### `$fuzzy` - Fuzzy text search

```php
$collection->find([
    'title' => ['$fuzzy' => ['search' => 'database', 'distance' => 2]]
]);
```

## Advanced Query Examples

### Complex Queries

```php
// Find users who are active admins under 30 or inactive managers over 40
$complexQuery = $collection->find([
    '$or' => [
        [
            '$and' => [
                ['role' => 'admin'],
                ['status' => 'active'],
                ['age' => ['$lt' => 30]]
            ]
        ],
        [
            '$and' => [
                ['role' => 'manager'],
                ['status' => 'inactive'],
                ['age' => ['$gt' => 40]]
            ]
        ]
    ]
])->toArray();
```

### Nested Object Queries

```php
// Query nested objects
$usersWithITDepartment = $collection->find([
    'profile.department' => 'IT'
])->toArray();

// Query nested arrays
$usersWithPHPTag = $collection->find([
    'skills' => ['$in' => ['PHP', 'MySQL']]
])->toArray();
```

### Date Range Queries

```php
// Find documents created in the last 30 days
$thirtyDaysAgo = date('c', strtotime('-30 days'));
$recentDocuments = $collection->find([
    'created_at' => ['$gte' => $thirtyDaysAgo]
])->toArray();

// Find documents from specific date range
$startDate = '2024-01-01T00:00:00Z';
$endDate = '2024-01-31T23:59:59Z';
$januaryDocuments = $collection->find([
    'created_at' => ['$gte' => $startDate, '$lte' => $endDate]
])->toArray();
```

### Text Search Queries

```php
// Regex search (case insensitive)
$phpDevelopers = $collection->find([
    'bio' => ['$regex' => 'php developer', '$options' => 'i']
])->toArray();

// Fuzzy search
$similarTitles = $collection->find([
    'title' => ['$fuzzy' => ['search' => 'database', 'distance' => 3]]
])->toArray();
```

### Aggregation-like Queries

```php
// Find top 10 highest scoring users
$topUsers = $collection->find([
    'score' => ['$exists' => true]
])
->sort(['score' => -1])
->limit(10)
->toArray();

// Find users with specific tag combinations
$advancedUsers = $collection->find([
    'tags' => ['$all' => ['javascript', 'react', 'nodejs']]
])->toArray();
```

---

## Implementation Details

### Query Processing Pipeline

1. **Criteria Processing**: `processCriteria()` normalizes query criteria
2. **Document Matching**: `matchDocument()` evaluates each document
3. **Condition Evaluation**: `evaluateCondition()` handles individual operators
4. **Result Filtering**: Cursor applies limit, skip, sort

### Performance Considerations

#### Index Utilization

Query builder otomatis menggunakan index untuk:

- Exact matches pada indexed fields
- Range queries pada indexed fields
- Searchable fields untuk encrypted data

#### Query Optimization Tips

```php
// ✅ Good: Use indexed fields first
$collection->createIndex('email');
$collection->createIndex('created_at');

$users = $collection->find([
    'email' => 'john@example.com',
    'created_at' => ['$gte' => '2024-01-01']
])->toArray();

// ✅ Good: Limit result set
$results = $collection->find($criteria)->limit(100)->toArray();

// ❌ Bad: Regex without index
$results = $collection->find([
    'description' => ['$regex' => 'keyword']
])->toArray();
```

### Memory Management

Query builder menggunakan cursor-based iteration untuk large result sets:

```php
// Memory efficient for large datasets
$cursor = $collection->find($criteria);
foreach ($cursor as $document) {
    // Process one document at a time
    processDocument($document);
}
```

---

## Error Handling

### Query Validation Errors

```php
try {
    $results = $collection->find(['invalid' => ['$badop' => 'value']]);
} catch (InvalidArgumentException $e) {
    echo "Invalid query operator: " . $e->getMessage();
}
```

### Common Query Errors

1. **Invalid Operator**: `$badop` tidak dikenali
2. **Type Mismatch**: Operator digunakan pada type yang salah
3. **Nested Query Issues**: Path tidak valid untuk nested objects

### Debugging Queries

```php
// Enable query logging
$db->queryExecutor->setLogging(true);

// Execute query
$results = $collection->find($criteria)->toArray();

// Check logs
$queryLogs = $db->queryExecutor->getQueryLog();
print_r($queryLogs);
```

---

## Integration with Other Traits

### With SoftDeleteTrait

```php
$collection->useSoftDeletes(true);

// Queries automatically exclude deleted documents
$activeUsers = $collection->find(['status' => 'active'])->toArray();

// Include deleted documents
$allUsers = $collection->find(['status' => 'active'])->withTrashed()->toArray();
```

### With SearchableFieldsTrait

```php
$collection->setSearchableFields(['email' => ['hash' => true]]);

// Queries use indexed searchable fields for performance
$user = $collection->findOne(['email' => 'john@example.com']);
```

### With SchemaValidationTrait

```php
$collection->setSchema([
    'email' => ['required' => true, 'type' => 'string'],
    'age' => ['type' => 'integer', 'min' => 0]
]);

// Query validation works with schema
$youngUsers = $collection->find([
    'age' => ['$gte' => 18, '$lte' => 30]
])->toArray();
```

---

## Custom Query Operators

### Extending QueryBuilderTrait

```php
trait CustomQueryTrait {
    use QueryBuilderTrait;

    protected function evaluateCondition($value, $condition) {
        // Handle custom operators
        if (isset($condition['$custom'])) {
            return $this->evaluateCustomCondition($value, $condition['$custom']);
        }

        // Call parent for standard operators
        return parent::evaluateCondition($value, $condition);
    }

    private function evaluateCustomCondition($value, $customCondition) {
        // Implement custom logic
        switch ($customCondition['type']) {
            case 'contains':
                return strpos($value, $customCondition['substring']) !== false;
            case 'distance':
                return $this->calculateDistance($value, $customCondition['point']);
        }
        return false;
    }
}
```

### Usage

```php
$results = $collection->find([
    'bio' => ['$custom' => ['type' => 'contains', 'substring' => 'developer']],
    'location' => ['$custom' => ['type' => 'distance', 'point' => [40.7128, -74.0060]]]
])->toArray();
```

---

## Best Practices

### Query Design

1. **Use Indexed Fields**: Prioritize queries pada field yang di-index
2. **Limit Result Sets**: Selalu gunakan `limit()` untuk queries besar
3. **Use Projections**: Select hanya field yang diperlukan
4. **Avoid Regex on Large Datasets**: Gunakan searchable fields sebagai alternatif

### Performance Optimization

```php
// Optimal query pattern
$results = $collection->find([
    'status' => 'active',           // Indexed field
    'created_at' => ['$gte' => $date] // Indexed field
])
->sort(['priority' => -1])          // Indexed sort
->limit(50)                         // Limit results
->toArray();
```

### Query Testing

```php
function testQueryPerformance($collection, $criteria, $iterations = 100) {
    $times = [];

    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);
        $result = $collection->find($criteria)->toArray();
        $times[] = microtime(true) - $start;
    }

    $avgTime = array_sum($times) / count($times);
    $maxTime = max($times);
    $minTime = min($times);

    return [
        'avg_time' => $avgTime,
        'max_time' => $maxTime,
        'min_time' => $minTime,
        'count' => count($result)
    ];
}

// Usage
$perf = testQueryPerformance($collection, ['status' => 'active']);
echo "Average query time: " . round($perf['avg_time'] * 1000, 2) . "ms\n";
```

---

## Migration from Other Databases

### From SQL Queries

```php
// SQL: SELECT * FROM users WHERE age > 18 AND status = 'active'
// BangronDB:
$users = $collection->find([
    'age' => ['$gt' => 18],
    'status' => 'active'
])->toArray();
```

### From MongoDB Queries

```php
// MongoDB: db.users.find({age: {$gte: 18, $lte: 65}})
// BangronDB: Same syntax
$users = $collection->find([
    'age' => ['$gte' => 18, '$lte' => 65]
])->toArray();
```

### From ElasticSearch Queries

```php
// ElasticSearch: match query
// BangronDB: Use regex or fuzzy search
$results = $collection->find([
    'content' => ['$regex' => 'searchterm', '$options' => 'i']
])->toArray();
```

---

## Troubleshooting

### Slow Queries

**Symptoms:** Queries take longer than expected

**Solutions:**

1. Check if fields are indexed: `$collection->createIndex('field_name')`
2. Use searchable fields for text search
3. Limit result set with `limit()`
4. Use projections to select only needed fields

### Memory Issues

**Symptoms:** Out of memory errors

**Solutions:**

1. Process results in batches
2. Use cursors instead of `toArray()`
3. Implement pagination
4. Use projections to reduce data size

### Unexpected Results

**Debug Steps:**

1. Enable query logging: `$db->queryExecutor->setLogging(true)`
2. Check query logs after execution
3. Verify data types and operators
4. Test with simpler criteria first

### Index Not Used

**Symptoms:** Query still slow despite having index

**Check:**

1. Verify index exists: Check database schema
2. Ensure query uses indexed field exactly
3. Check for type mismatches
4. Consider composite indexes for multiple conditions
