# ⚡ BangronDB Cheat Sheet

Quick reference untuk perintah paling sering digunakan.

---

## Setup

```php
use BangronDB\Client;

$client = new Client(__DIR__ . '/data');
$db = $client->app;
$users = $db->users;
```

---

## CRUD Operasi

### CREATE - Tambah Data

```php
// Satu dokumen
$id = $users->insert(['name' => 'John', 'age' => 30]);

// Multiple dokumen
$users->insert([
    ['name' => 'Alice', 'age' => 25],
    ['name' => 'Bob', 'age' => 35]
]);
```

### READ - Baca Data

```php
// Semua dokumen
$all = $users->find()->toArray();

// Satu dokumen
$one = $users->findOne(['name' => 'John']);

// Dengan kriteria
$active = $users->find(['status' => 'active'])->toArray();

// Count
$total = $users->count(['status' => 'active']);
```

### UPDATE - Ubah Data

```php
// Merge (default)
$users->update(['_id' => '123'], ['age' => 31]);

// Replace
$users->update(['_id' => '123'], ['age' => 31], false);

// MongoDB-style
$users->update(['_id' => '123'], [
    '$set' => ['age' => 31],
    '$unset' => ['old_field' => '']
]);
```

### DELETE - Hapus Data

```php
// Hard delete
$users->remove(['_id' => '123']);

// Soft delete (jika enabled)
$users->useSoftDeletes(true);
$users->remove(['_id' => '123']); // Tidak benar-benar dihapus

// Query with soft delete
$users->find()->toArray(); // Hanya active
$users->find()->withTrashed()->toArray(); // Include deleted
$users->find()->onlyTrashed()->toArray(); // Hanya deleted

// Restore
$users->restore(['_id' => '123']);

// Force delete
$users->forceDelete(['_id' => '123']);
```

---

## Query Operators

| Operator         | Shortcut | Contoh                                            |
| ---------------- | -------- | ------------------------------------------------- |
| Greater Than     | $gt      | `['age' => ['$gt' => 18]]`                        |
| Greater or Equal | $gte     | `['age' => ['$gte' => 18]]`                       |
| Less Than        | $lt      | `['age' => ['$lt' => 65]]`                        |
| Less or Equal    | $lte     | `['age' => ['$lte' => 60]]`                       |
| Not Equal        | $ne      | `['status' => ['$ne' => 'inactive']]`             |
| In List          | $in      | `['role' => ['$in' => ['admin', 'user']]]`        |
| Not In List      | $nin     | `['role' => ['$nin' => ['guest']]]`               |
| Regex Match      | $regex   | `['email' => ['$regex' => '/^.*@gmail/']]`        |
| Exists           | $exists  | `['email' => ['$exists' => true]]`                |
| Fuzzy Search     | $fuzzy   | `['name' => ['$fuzzy' => ['$search' => 'john']]]` |

---

## Logical Operators

```php
// OR
$users->find([
    '$or' => [
        ['age' => ['$lt' => 18]],
        ['age' => ['$gt' => 65]]
    ]
]);

// AND
$users->find([
    '$and' => [
        ['status' => 'active'],
        ['age' => ['$gte' => 21]]
    ]
]);

// Combined
$users->find([
    'status' => 'active',
    '$or' => [
        ['role' => 'admin'],
        ['role' => 'editor']
    ]
]);
```

---

## Pagination & Sorting

```php
// Limit & Skip
$users->find()
    ->limit(10)    // Ambil 10
    ->skip(0)      // Skip 0 (page 1)
    ->toArray();

// Sorting
$users->find()
    ->sort(['name' => 1])      // Ascending (A-Z)
    ->toArray();

$users->find()
    ->sort(['name' => -1])     // Descending (Z-A)
    ->toArray();

// Multiple sort
$users->find()
    ->sort(['status' => 1, 'created_at' => -1])
    ->toArray();
```

---

## Configuration

### ID Modes

```php
$users->setIdModeAuto();               // UUID v4 (default)
$users->setIdModeManual();             // Manual ID
$users->setIdModePrefix('USR');        // Prefix: USR-000001
```

### Encryption

```php
$users->setEncryptionKey('my-secret-key-min-32-chars');
$users->isEncrypted(); // true/false
```

### Searchable Fields

```php
// Dengan hashing
$users->setSearchableFields(['email', 'phone'], true);

// Tanpa hashing
$users->setSearchableFields(['name', 'city'], false);

// Remove searchable field
$users->removeSearchableField('email', true);
```

### Schema Validation

```php
$users->setSchema([
    'name' => [
        'required' => true,
        'type' => 'string',
        'min' => 3,
        'max' => 100
    ],
    'email' => [
        'required' => true,
        'type' => 'string',
        'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'
    ],
    'age' => [
        'type' => 'int',
        'min' => 0,
        'max' => 150
    ],
    'role' => [
        'type' => 'string',
        'enum' => ['admin', 'user', 'guest']
    ]
]);

// Validate
$errors = $users->validate($data);
```

---

## Hooks & Events

```php
// Before Insert
$users->on('beforeInsert', function($doc) {
    $doc['created_at'] = date('c');
    return $doc;
});

// After Insert
$users->on('afterInsert', function($doc, $id) {
    echo "Dihapus: " . $id;
});

// Before Update
$users->on('beforeUpdate', function($criteria, $data) {
    $data['updated_at'] = date('c');
    return ['criteria' => $criteria, 'data' => $data];
});

// After Update
$users->on('afterUpdate', function($old, $new) {
    // Log changes
});

// Before Remove
$users->on('beforeRemove', function($doc) {
    if ($doc['protected'] ?? false) {
        return false; // Cancel
    }
});

// After Remove
$users->on('afterRemove', function($doc) {
    echo "Dihapus: " . $doc['_id'];
});

// Remove hook
$users->off('beforeInsert');
```

---

## Relationships (Populate)

```php
// Setup
$posts = $db->posts;
$users = $db->users;

// Join data dari collection lain
$postsWithAuthor = $posts->find()
    ->populate('author_id', $users, ['as' => 'author'])
    ->toArray();

// Result:
// {
//     '_id' => 'post1',
//     'title' => 'Hello',
//     'author_id' => 'user1',
//     'author' => { '_id' => 'user1', 'name' => 'John' }
// }

// Multiple populate
$posts->find()
    ->populate('author_id', $users, ['as' => 'author'])
    ->populate('category_id', $db->categories, ['as' => 'category'])
    ->toArray();

// Cross-database populate
$posts->find()
    ->populate('author_id', 'otherdb.users', ['as' => 'author'])
    ->toArray();
```

---

## Indexing

```php
// Create index
$users->createIndex('email');
$users->createIndex('created_at');
$users->createIndex('status');

// Multiple columns
$users->createIndex(['status', 'created_at']);

// Drop index
$db->dropIndex('idx_users_email');
```

---

## Transactions

```php
// Manual transaction
$db->connection->beginTransaction();

try {
    $users->insert(['name' => 'John']);
    $users->insert(['name' => 'Jane']);
    $db->connection->commit();
} catch (Exception $e) {
    $db->connection->rollBack();
    throw $e;
}
```

---

## Health & Monitoring

```php
// Metrics
$metrics = $db->getHealthMetrics();

// Health report
$report = $db->getHealthReport();

// Performance metrics
$perf = $db->getPerformanceMetrics();

// Collection metrics
$collMetrics = $db->getCollectionMetrics();

// Check integrity
$integrity = $db->checkIntegrity();

// Vacuum (optimize)
$db->vacuum();
```

---

## Change Notification

```php
$lastModified = $users->getLastModified();
// { 'version' => 42, 'last_updated' => '2024-01-15 10:30:00' }

// Notify manually
$users->notifyChange();
```

---

## Dynamic Configuration

```php
// Set custom config
$users->setCustomConfig('settings', ['theme' => 'dark']);

// Get custom config
$settings = $users->getCustomConfig('settings');

// Set multiple
$users->setCustomConfigArray([
    'theme' => 'dark',
    'lang' => 'id'
]);

// Save to database
$users->saveConfiguration();

// Load from database
$users->loadConfiguration();
```

---

## Collection Operations

```php
// List collections
$collections = $db->getCollectionNames();

// Drop collection
$users->drop();

// Rename collection
$users->renameCollection('users_new');

// Count documents
$count = $users->count();

// Count with criteria
$active = $users->count(['status' => 'active']);
```

---

## Error Handling

```php
try {
    $id = $users->insert($data);
} catch (InvalidArgumentException $e) {
    echo "Validation error: " . $e->getMessage();
} catch (RuntimeException $e) {
    echo "Runtime error: " . $e->getMessage();
} catch (Exception $e) {
    echo "General error: " . $e->getMessage();
}
```

---

## Utilities

```php
use BangronDB\UtilArrayQuery;

// Match document
$matches = UtilArrayQuery::match(['age' => ['$gt' => 18]], $doc);

// Generate UUID
$uuid = UtilArrayQuery::generateId();

// Fuzzy search
$score = UtilArrayQuery::fuzzy_search('search', 'text', 3);
```

---

## Dot Notation (Nested Fields)

```php
// Query nested fields
$users->find(['address.city' => 'New York']);

// Update nested fields
$users->update(
    ['_id' => '123'],
    ['address.city' => 'Los Angeles']
);

// With projection
$users->find([], ['address.city' => 1])->toArray();
```

---

**Tip**: Bookmark page ini untuk referensi cepat! 📌
