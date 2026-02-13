# SoftDeleteTrait API Reference

Trait untuk implementasi soft delete dengan kemampuan restore dan query filtering.

## Trait Overview

```php
trait BangronDB\Traits\SoftDeleteTrait
{
    // Properties
    protected bool $softDeletesEnabled = false;
    protected string $deletedAtField = '_deleted_at';

    // Methods
    public function useSoftDeletes(bool $enabled = true): self
    public function softDeletesEnabled(): bool
    public function getDeletedAtField(): string
    public function setDeletedAtField(string $field): self

    // Soft Delete Operations
    public function remove($criteria): int
    public function forceDelete($criteria): int
    public function restore($criteria): int

    // Query Modifiers
    public function withTrashed(): self
    public function onlyTrashed(): self
}
```

## Properties

### `$softDeletesEnabled`

Flag yang menentukan apakah soft deletes aktif.

**Type:** `bool`
**Default:** `false`

### `$deletedAtField`

Nama field yang digunakan untuk menyimpan timestamp penghapusan.

**Type:** `string`
**Default:** `'_deleted_at'`

## Configuration Methods

### `useSoftDeletes(bool $enabled = true): self`

Mengaktifkan atau menonaktifkan soft deletes.

**Parameters:**

- `$enabled` (bool): True untuk enable, false untuk disable

**Return:** `self` untuk method chaining

**Examples:**

```php
$collection->useSoftDeletes(true);  // Enable soft deletes
$collection->useSoftDeletes(false); // Disable soft deletes
```

### `softDeletesEnabled(): bool`

Memeriksa apakah soft deletes aktif.

**Return:** `bool`

**Examples:**

```php
if ($collection->softDeletesEnabled()) {
    echo "Soft deletes are enabled";
}
```

### `getDeletedAtField(): string`

Mengembalikan nama field yang digunakan untuk timestamp deleted.

**Return:** `string`

**Examples:**

```php
$field = $collection->getDeletedAtField(); // '_deleted_at'
```

### `setDeletedAtField(string $field): self`

Mengatur nama field untuk timestamp deleted.

**Parameters:**

- `$field` (string): Nama field baru

**Return:** `self`

**Examples:**

```php
$collection->setDeletedAtField('deleted_on');
```

## Soft Delete Operations

### `remove($criteria): int`

Menghapus dokumen dengan soft delete (jika aktif) atau hard delete.

**Parameters:**

- `$criteria` (mixed): Kriteria dokumen yang akan dihapus

**Return:** `int` - Jumlah dokumen yang terpengaruh

**Behavior:**

- Jika soft deletes aktif: Set `$deletedAtField` dengan timestamp ISO 8601
- Jika soft deletes tidak aktif: Hard delete dari database

**Examples:**

```php
// Soft delete user
$deleted = $collection->remove(['_id' => 'user-123']);

// Check result
if ($deleted > 0) {
    echo "User soft deleted";
}
```

### `forceDelete($criteria): int`

Menghapus dokumen secara permanen (hard delete), mengabaikan soft deletes setting.

**Parameters:**

- `$criteria` (mixed): Kriteria dokumen yang akan dihapus

**Return:** `int` - Jumlah dokumen yang terhapus

**Examples:**

```php
// Permanently delete user
$deleted = $collection->forceDelete(['_id' => 'user-123']);
```

### `restore($criteria): int`

Merestore dokumen yang telah dihapus (soft delete).

**Parameters:**

- `$criteria` (mixed): Kriteria dokumen yang akan direstore

**Return:** `int` - Jumlah dokumen yang direstore

**Examples:**

```php
// Restore soft deleted user
$restored = $collection->restore(['_id' => 'user-123']);

if ($restored > 0) {
    echo "User restored successfully";
}
```

## Query Modifiers

### `withTrashed(): self`

Memodifikasi query untuk menyertakan dokumen yang telah dihapus.

**Return:** `self` (Cursor instance)

**Examples:**

```php
// Get all users including deleted ones
$allUsers = $collection->find()->withTrashed()->toArray();
```

### `onlyTrashed(): self`

Memodifikasi query untuk hanya mengambil dokumen yang telah dihapus.

**Return:** `self` (Cursor instance)

**Examples:**

```php
// Get only deleted users
$deletedUsers = $collection->find()->onlyTrashed()->toArray();
```

---

## Implementation Details

### Database Schema

Soft delete menggunakan field timestamp di dalam JSON document:

```json
{
  "_id": "user-123",
  "name": "John Doe",
  "email": "john@example.com",
  "_deleted_at": "2024-01-15T10:30:00Z"
}
```

### Query Filtering

Secara default, semua queries difilter untuk mengecualikan dokumen yang dihapus:

```sql
-- Default query (soft delete active)
SELECT * FROM collection WHERE json_extract(document, '$._deleted_at') IS NULL

-- With trashed
SELECT * FROM collection -- No filtering

-- Only trashed
SELECT * FROM collection WHERE json_extract(document, '$._deleted_at') IS NOT NULL
```

### Hook Integration

Soft delete terintegrasi dengan hooks system:

```php
// Before remove hook can prevent soft delete
$collection->on('beforeRemove', function($criteria) {
    // Validation logic
    if (/* some condition */) {
        return false; // Prevent deletion
    }
    return $criteria;
});

// After remove hook for post-delete actions
$collection->on('afterRemove', function($document) {
    // Log deletion, send notifications, etc.
    auditLog('user_deleted', $document);
});
```

---

## Usage Examples

### Basic Setup

```php
$users = $db->selectCollection('users');

// Enable soft deletes
$users->useSoftDeletes(true);

// Normal operations
$userId = $users->insert(['name' => 'John', 'email' => 'john@example.com']);

// Soft delete
$users->remove(['_id' => $userId]);

// User masih ada tapi marked sebagai deleted
$user = $users->findOne(['_id' => $userId]); // null

// Tapi bisa diakses dengan withTrashed
$user = $users->find()->withTrashed()->findOne(['_id' => $userId]); // returns user
```

### Advanced Patterns

#### Cascading Soft Deletes

```php
$users->on('afterRemove', function($user) use ($db) {
    // Soft delete related data
    $db->posts->remove(['author_id' => $user['_id']]);
    $db->comments->remove(['user_id' => $user['_id']]);
    $db->sessions->remove(['user_id' => $user['_id']]);
});
```

#### Data Archiving

```php
$users->on('afterRemove', function($user) use ($db) {
    // Archive to separate collection
    $archive = array_merge($user, [
        'archived_at' => date('c'),
        'archive_reason' => 'user_deletion'
    ]);
    unset($archive['_id']); // New ID for archive

    $db->user_archive->insert($archive);
});
```

#### Temporary Deactivation (Alternative)

```php
// Instead of soft delete, use status
$users->on('beforeRemove', function($user) {
    // Update status instead of delete
    $user['collection']->update(
        ['_id' => $user['_id']],
        ['$set' => [
            'status' => 'deactivated',
            'deactivated_at' => date('c')
        ]]
    );

    return false; // Prevent actual deletion
});
```

### Cleanup Operations

#### Periodic Cleanup

```php
function cleanupExpiredSoftDeletes($collection, $days = 30) {
    $cutoff = date('c', strtotime("-{$days} days"));
    $expired = $collection->find()->onlyTrashed()->toArray();

    $permanentlyDeleted = 0;
    foreach ($expired as $doc) {
        if (($doc['_deleted_at'] ?? '') < $cutoff) {
            $collection->forceDelete(['_id' => $doc['_id']]);
            $permanentlyDeleted++;
        }
    }

    return $permanentlyDeleted;
}

// Usage
$deleted = cleanupExpiredSoftDeletes($db->users, 90);
echo "Cleaned up {$deleted} users older than 90 days";
```

#### Selective Restore

```php
function bulkRestore($collection, $criteria) {
    // Find trashed documents matching criteria
    $trashed = $collection->find($criteria)->onlyTrashed()->toArray();

    $restored = 0;
    foreach ($trashed as $doc) {
        if ($collection->restore(['_id' => $doc['_id']])) {
            $restored++;
        }
    }

    return $restored;
}

// Usage
$restored = bulkRestore($db->users, ['role' => 'premium']);
echo "Restored {$restored} premium users";
```

---

## Integration with Other Features

### With Schema Validation

```php
// Schema validation works with soft delete
$users->setSchema([
    'name' => ['required' => true],
    'email' => ['required' => true, 'type' => 'string'],
    '_deleted_at' => ['type' => 'string'] // Allow deleted_at field
]);
$users->useSoftDeletes(true);
```

### With Encryption

```php
// Encryption works with soft delete
$users->setEncryptionKey('secret-key');
$users->useSoftDeletes(true);

// Deleted documents remain encrypted
```

### With Searchable Fields

```php
// Searchable fields work with soft delete filtering
$users->setSearchableFields(['email']);
$users->useSoftDeletes(true);

// Queries automatically exclude deleted documents
$user = $users->findOne(['email' => 'john@example.com']); // Excludes deleted
```

### With Hooks

```php
$users->useSoftDeletes(true);

// Pre-delete validation
$users->on('beforeRemove', function($criteria) {
    $user = $this->findOne($criteria);
    if ($user['role'] === 'admin') {
        throw new Exception('Cannot delete admin users');
    }
    return $criteria;
});

// Post-delete cleanup
$users->on('afterRemove', function($user) {
    // Log deletion
    error_log("User deleted: {$user['_id']}");

    // Send notification
    sendDeletionNotification($user['email']);
});
```

---

## Performance Considerations

### Indexing Deleted Field

Untuk performa optimal pada dataset besar:

```php
// Create index on deleted_at field (JSON path)
$collection->createIndex('_deleted_at');
```

### Query Performance

| Operation           | Without Soft Delete | With Soft Delete | Impact  |
| ------------------- | ------------------- | ---------------- | ------- |
| Find (active)       | Fast                | Fast (indexed)   | Minimal |
| Find (with trashed) | N/A                 | Fast             | None    |
| Delete              | Fast                | Fast             | None    |
| Restore             | N/A                 | Fast             | None    |

### Memory Usage

Soft delete tidak menambah memory usage karena hanya menambah field di JSON document.

---

## Migration Guide

### Enabling Soft Delete on Existing Collection

```php
function enableSoftDelete($collection) {
    // Enable soft deletes
    $collection->useSoftDeletes(true);

    // Existing documents remain active (no _deleted_at field)
    // New deletes will be soft deletes

    // Save configuration
    $collection->saveConfiguration();
}

// Usage
enableSoftDelete($db->users);
```

### Disabling Soft Delete

```php
function disableSoftDelete($collection) {
    // Option 1: Keep deleted documents as-is
    $collection->useSoftDeletes(false);

    // Option 2: Permanently delete all soft-deleted documents
    $trashed = $collection->find()->onlyTrashed()->toArray();
    foreach ($trashed as $doc) {
        $collection->forceDelete(['_id' => $doc['_id']]);
    }
    $collection->useSoftDeletes(false);
}
```

---

## Error Handling

### Common Errors

1. **Restore Non-existent Document**: Mengembalikan 0 tanpa error
2. **Force Delete Active Document**: Berhasil (hard delete)
3. **Query on Deleted Field**: Perlu `withTrashed()` untuk mengakses `_deleted_at`

### Exception Types

- `LogicException`: Invalid operation state
- `RuntimeException`: Database operation failures

---

## Best Practices

### When to Use Soft Delete

✅ **Good for:**

- User-generated content
- Audit trails
- Data recovery scenarios
- Regulatory compliance

❌ **Not ideal for:**

- Temporary data
- Session/cache data
- High-frequency inserts/deletes

### Configuration Recommendations

```php
// Production configuration
$collection->useSoftDeletes(true);
$collection->setDeletedAtField('_deleted_at'); // ISO 8601 timestamps
$collection->createIndex('_deleted_at'); // For performance
```

### Monitoring

```php
function getSoftDeleteMetrics($collection) {
    $total = $collection->find()->withTrashed()->count();
    $active = $collection->find()->count();
    $deleted = $collection->find()->onlyTrashed()->count();

    return [
        'total_documents' => $total,
        'active_documents' => $active,
        'deleted_documents' => $deleted,
        'deletion_rate' => $total > 0 ? ($deleted / $total) * 100 : 0
    ];
}

// Usage
$metrics = getSoftDeleteMetrics($db->users);
print_r($metrics);
```
