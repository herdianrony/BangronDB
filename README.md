# BangronDB

Database NoSQL berbasis SQLite dengan API mirip MongoDB untuk PHP. Mendukung enkripsi, hook, relasi, dan fitur enterprise.

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/herdianrony/BangronDB/releases) [![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE) [![PHP](https://img.shields.io/badge/php-%3E%3D%208.0-blue.svg)](https://www.php.net)

## Fitur Utama

- **API MongoDB-like** - Sintaks familiar seperti MongoDB
- **Enkripsi AES-256** - Enkripsi tingkat kolom dan koleksi
- **Searchable Fields** - Pencarian pada data terenkripsi
- **Hooks System** - Event-driven hooks untuk semua operasi
- **Relationships** - Populate untuk relasi antar koleksi
- **Schema Validation** - Validasi tipe, enum, regex, range
- **Soft Deletes** - Penghapusan logis dengan restore
- **Multiple ID Modes** - UUID, manual, dan prefiks
- **Health Monitoring** - Monitoring dan metrics database

## Instalasi

### Persyaratan

- PHP 8.1+ dengan ekstensi PDO SQLite
- Ekstensi OpenSSL untuk enkripsi
- Composer

### Via Composer

```bash
composer require herdianrony/bangrondb
```

### Manual

```php
require_once __DIR__ . '/src/Client.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Collection.php';
require_once __DIR__ . '/src/Cursor.php';
require_once __DIR__ . '/src/UtilArrayQuery.php';
// ...sertakan traits yang diperlukan
```

## Quick Start

```php
use BangronDB\Client;

// 1. Inisialisasi
$client = new Client(__DIR__ . '/data');

// 2. Pilih database & collection
$users = $client->app->users;

// 3. Simpan data
$userId = $users->insert(['name' => 'John Doe', 'role' => 'admin']);

// 4. Cari data
$user = $users->findOne(['name' => 'John Doe']);
echo "Halo, " . $user['name'];

// 5. Update
$users->update(['_id' => $userId], ['$set' => ['role' => 'superadmin']]);

// 6. Hapus
$users->remove(['_id' => $userId]);
```

## Konsep Dasar

```
Client → Database (file .bangron) → Collection → Document (JSON)
```

- **Client**: Manager utama yang mengatur koneksi ke berbagai database
- **Database**: Satu file fisik `.bangron` di komputer Anda
- **Collection**: "Folder" di dalam database untuk mengelompokkan data sejenis
- **Document**: Satu record data dalam format array/JSON

## Client & Database

```php
use BangronDB\Client;

// File-based
$client = new Client(__DIR__ . '/data');

// In-memory
$client = new Client(':memory:');

// Dengan options
$client = new Client(__DIR__ . '/data', [
    'encryption_key' => 'my-secret-key',
    'timeout' => 30
]);

// Akses database
$db = $client->selectDB('mydatabase');   // Method
$db = $client->mydatabase;               // Magic getter

// Akses collection
$collection = $db->selectCollection('users');
$collection = $db->users;

// List databases
$databases = $client->listDBs();

// Cleanup
$client->close();
```

## Collection

```php
// ID Generation Modes
$collection->setIdModeAuto();        // UUID v4 (default)
$collection->setIdModeManual();      // Manual ID
$collection->setIdModePrefix('USR'); // Prefiks: USR-000001

// Enkripsi per koleksi
$collection->setEncryptionKey('collection-secret-key');

// Searchable fields
$collection->setSearchableFields(['email', 'phone'], true); // true = hashing

// Drop collection
$collection->drop();

// Rename collection
$collection->renameCollection('new_name');
```

## CRUD Operations

### Insert

```php
// Single document
$id = $collection->insert(['name' => 'John', 'email' => 'john@example.com', 'age' => 30]);

// Batch insert
$count = $collection->insert([
    ['name' => 'Alice', 'age' => 25],
    ['name' => 'Bob', 'age' => 35]
]);
```

### Find

```php
// Find all
$users = $collection->find()->toArray();

// Find one
$user = $collection->findOne(['name' => 'John']);

// Dengan criteria
$users = $collection->find(['age' => ['$gt' => 25], 'status' => 'active']);

// Dengan projection
$users = $collection->find(['age' => ['$gte' => 21]], ['name' => 1, 'email' => 1]);

// Count
$total = $collection->count(['status' => 'active']);
```

### Update

```php
// Merge update (default)
$collection->update(['name' => 'John'], ['age' => 31, 'city' => 'NY']);

// Replace update
$collection->update(['name' => 'John'], ['age' => 31], false);

// MongoDB-style operators
$collection->update(['name' => 'John'], [
    '$set' => ['age' => 31, 'city' => 'NY'],
    '$unset' => ['old_field' => '']
]);

// Upsert
$collection->save(['_id' => 'existing-id', 'name' => 'Updated']);
```

### Delete

```php
$deleted = $collection->remove(['status' => 'inactive']);
$collection->remove([]); // Hapus semua
```

### Pagination & Sorting

```php
$users = $collection->find(['status' => 'active'])
    ->skip(10)
    ->limit(5)
    ->sort(['age' => 1])   // Ascending
    ->toArray();

$users = $collection->find()->sort(['age' => -1]); // Descending
```

## Query Operators

```php
// Comparison
$collection->find(['age' => ['$gt' => 18]]);
$collection->find(['age' => ['$gte' => 21]]);
$collection->find(['age' => ['$lt' => 65]]);
$collection->find(['age' => ['$lte' => 60]]);
$collection->find(['age' => ['$ne' => 30]]);

// Array
$collection->find(['role' => ['$in' => ['admin', 'editor']]]);
$collection->find(['role' => ['$nin' => ['guest', 'banned']]]);

// Existence
$collection->find(['email' => ['$exists' => true]]);

// Logical
$collection->find(['$or' => [['age' => ['$lt' => 18]], ['age' => ['$gt' => 65]]]]);
$collection->find(['$and' => [['status' => 'active'], ['age' => ['$gte' => 21]]]]);

// Regex
$collection->find(['name' => ['$regex' => '^John']]);

// Custom function (Closure only - keamanan RCE)
$collection->find(['age' => ['$where' => fn($doc) => $doc['age'] > 18]]);
$collection->find(['name' => ['$func' => fn($val) => strlen($val) > 5]]);

// Fuzzy search
$collection->find(['description' => ['$fuzzy' => ['$search' => 'important', '$minScore' => 0.7]]]);

// Dot notation (nested fields)
$collection->find(['address.city' => 'New York']);
```

## Enkripsi

```php
// Database-wide encryption
$db = new Database('path/to/db.sqlite', [
    'encryption_key' => 'master-secret-key'
]);

// Collection-specific encryption
$collection->setEncryptionKey('collection-specific-key');
$isEncrypted = $collection->isEncrypted(); // true/false

// Searchable encrypted fields
$collection->setSearchableFields(['email', 'phone'], true); // true = SHA-256 hashing
$collection->removeSearchableField('email', true); // true = drop column
```

**Detail teknis**: Algoritma AES-256-CBC, key derivation SHA-256, IV random per enkripsi, storage Base64 dalam JSON.

## Schema Validation

```php
$collection->setSchema([
    'username' => ['required' => true, 'type' => 'string', 'min' => 3, 'max' => 50],
    'email'    => ['required' => true, 'type' => 'string', 'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
    'age'      => ['type' => 'int', 'min' => 13, 'max' => 120],
    'role'     => ['type' => 'string', 'enum' => ['admin', 'user', 'moderator']]
]);

// Validate tanpa insert
$collection->validate(['username' => 'john', 'email' => 'invalid']); // throws ValidationException
```

## Soft Deletes

```php
$collection->useSoftDeletes(true);

$collection->remove(['username' => 'johndoe']);      // Soft delete
$users->find()->withTrashed()->toArray();             // Include deleted
$users->find()->onlyTrashed()->toArray();             // Hanya deleted
$users->restore(['username' => 'johndoe']);           // Restore
$users->forceDelete(['username' => 'johndoe']);       // Permanent delete
```

## Hooks

```php
// Before insert - modifikasi data
$collection->on('beforeInsert', function($document) {
    $document['created_at'] = date('c');
    return $document;
});

// After insert - logging
$collection->on('afterInsert', function($document, $insertId) {
    error_log("Document inserted: " . $insertId);
});

// Before update - auto timestamp
$collection->on('beforeUpdate', function($criteria, $data) {
    $data['updated_at'] = date('c');
    return ['criteria' => $criteria, 'data' => $data];
});

// Veto operation
$collection->on('beforeRemove', function($document) {
    if ($document['protected'] ?? false) return false; // Cancel
});

// Remove hook
$collection->off('beforeInsert');
```

**Events**: `beforeInsert`, `afterInsert`, `beforeUpdate`, `afterUpdate`, `beforeRemove`, `afterRemove`

## Relationships (Populate)

```php
// Basic populate
$postsWithAuthors = $db->posts->find()
    ->populate('author_id', $db->users, ['as' => 'author'])
    ->toArray();

// Nested populate
$posts = $db->posts->find()
    ->populate('author_id', $db->users, ['as' => 'author'])
    ->populate('category_id', $db->categories, ['as' => 'category'])
    ->toArray();

// Array references
$post = $db->posts->populate($post, 'comment_ids', 'db.comments', '_id', 'comments');

// Cross-database populate
$collection->populate($docs, 'foreign_field', 'otherdb.othercollection', '_id', 'relation');
```

## Indexing

```php
$collection->createIndex('email');
$collection->createIndex('address.city');
$collection->createIndex('status', 'idx_status');

$db->dropIndex('idx_email');
```

> **Tip**: Buat index untuk field yang sering di-query. Index memberikan ~20x speedup pada find operations.

## Health & Monitoring

```php
$metrics = $db->getHealthMetrics();   // Metrics detail
$report = $db->getHealthReport();     // Status, issues, warnings, recommendations
$perf   = $db->getPerformanceMetrics();
$collM  = $db->getCollectionMetrics();
$db->vacuum();                        // Optimasi & reclaim space
$db->checkIntegrity();                // Cek integritas
```

## Change Notification

```php
$lastModified = $collection->getLastModified();
// ['version' => 42, 'last_updated' => '2024-01-15 10:30:45']

$collection->notifyChange(); // Manual trigger
```

## Dynamic Configuration

```php
// Set konfigurasi
$users->setIdModePrefix('USR');
$users->setSchema([...]);
$users->useSoftDeletes(true);
$users->saveConfiguration(); // Simpan ke database

// Konfigurasi otomatis dimuat saat inisialisasi collection
$users = $db->users; // encryption, schema, searchable fields otomatis

// Kelola konfigurasi manual
$db->saveCollectionConfig('users', [...]);
$config = $db->loadCollectionConfig('users');
$db->deleteCollectionConfig('users');
```

> **Penting**: Encryption key TIDAK disimpan di config. Selalu sediakan key saat runtime dari `.env` atau secret manager.

## Transactions

```php
$db->connection->beginTransaction();
try {
    $collection->insert($doc1);
    $collection->insert($doc2);
    $db->connection->commit();
} catch (\Exception $e) {
    $db->connection->rollBack();
    throw $e;
}
```

## Keamanan

BangronDB menerapkan validasi ketat untuk mencegah RCE, injection, dan path traversal:

| Fitur | Status |
|-------|--------|
| `$where` / `$func` hanya menerima Closure | ✅ Mencegah RCE |
| Field name whitelist (alfanumerik + `_`, `-`, `.`) | ✅ Mencegah injection |
| Database path validation | ✅ Mencegah path traversal |
| PRAGMA key escaping | ✅ Mencegah SQLite injection |
| Regex delimiter escaping | ✅ Mencegah ReDoS |
| `strict_types=1` di semua file | ✅ Type safety |

> Lihat [SECURITY_USAGE_GUIDE.md](SECURITY_USAGE_GUIDE.md) untuk panduan migrasi detail.

## API Reference

### Client

| Method | Deskripsi |
|--------|-----------|
| `new Client($path, $options)` | Inisialisasi koneksi |
| `listDBs()` | List semua database |
| `selectDB($name)` | Pilih database |
| `selectCollection($db, $col)` | Pilih collection langsung |
| `close()` | Tutup semua koneksi |

### Database

| Method | Deskripsi |
|--------|-----------|
| `selectCollection($name)` | Pilih collection |
| `createCollection($name)` | Buat collection baru |
| `dropCollection($name)` | Hapus collection |
| `getCollectionNames()` | List semua collection |
| `createJsonIndex($col, $field)` | Buat index |
| `attach($path, $alias)` | Attach database lain |
| `detach($alias)` | Detach database |
| `vacuum()` | Optimasi database |
| `getHealthMetrics()` | Metrics kesehatan |
| `getHealthReport()` | Health report |
| `saveCollectionConfig($name, $config)` | Simpan konfigurasi |
| `loadCollectionConfig($name)` | Load konfigurasi |

### Collection

| Method | Deskripsi |
|--------|-----------|
| `insert($doc)` | Insert document |
| `find($criteria, $projection)` | Cari documents |
| `findOne($criteria, $projection)` | Cari satu document |
| `update($criteria, $data, $merge)` | Update documents |
| `remove($criteria)` | Hapus documents |
| `count($criteria)` | Hitung documents |
| `save($document)` | Upsert document |
| `drop()` | Hapus collection |
| `renameCollection($newName)` | Rename collection |
| `setIdModeAuto/Manual/Prefix` | Set ID mode |
| `setEncryptionKey($key)` | Set encryption key |
| `setSearchableFields($fields, $hash)` | Set searchable fields |
| `setSchema($schema)` | Set schema validation |
| `useSoftDeletes($enabled)` | Enable soft deletes |
| `restore($criteria)` | Restore soft-deleted |
| `forceDelete($criteria)` | Permanent delete |
| `on($event, $fn)` | Register hook |
| `off($event)` | Remove hook |
| `createIndex($field)` | Buat index |
| `getLastModified()` | Info perubahan terakhir |
| `saveConfiguration()` | Simpan konfigurasi |

### Cursor

| Method | Deskripsi |
|--------|-----------|
| `limit($n)` | Set limit |
| `skip($n)` | Set skip |
| `sort($fields)` | Set sort order |
| `populate($field, $col, $opts)` | Populate relasi |
| `withTrashed()` | Include soft-deleted |
| `onlyTrashed()` | Hanya soft-deleted |
| `toArray()` | Konversi ke array |
| `each($callable)` | Iterasi tiap document |

## Environment Configuration

Salin `.env.example` ke `.env`:

```env
DB_PATH=                # Path database (kosongkan untuk in-memory)
ENCRYPTION_KEY=         # Key 32 byte untuk AES-256-CBC
QUERY_LOGGING=false     # Log query (true/false)
PERFORMANCE_MONITORING=false  # Monitor performa (true/false)
```

## Contoh Lengkap

Lihat folder `examples/` untuk implementasi lengkap:

- `01-basic-crud.php` - Operasi CRUD dasar
- `02-encryption.php` - Enkripsi data
- `03-schema-validation.php` - Validasi schema
- `04-soft-deletes.php` - Soft deletes
- `05-searchable-fields.php` - Searchable fields
- `06-hooks.php` - Hooks & events
- `07-relationships.php` - Relasi antar collection
- `08-transactions.php` - Transaksi
- `10-advanced.php` - Demo semua fitur

## Performa

Hasil benchmark (PHP 8.3, SSD, 1000 records):

| Operasi | Waktu | Performa |
|---------|-------|----------|
| Insert (1000x) | 215 ms | ~4,600 ops/sec |
| Find One (no index) | 56 ms | ~1,780 ops/sec |
| Find One (with index) | 2.8 ms | ~35,000 ops/sec |
| Update (100x) | 19 ms | ~5,000 ops/sec |
| Pagination (50 pages) | 5 ms | ~0.10 ms/page |
| Query Encrypted (100x) | 15 ms | ~6,350 ops/sec |

> **Tip**: Index memberikan **~20x speedup** pada find operations.

## Contributing

Lihat [CONTRIBUTING.md](CONTRIBUTING.md) untuk panduan berkontribusi.

## License

[MIT](LICENSE) - BangronDB © 2024
