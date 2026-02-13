# BangronDB

BangronDB adalah library PHP yang menyediakan antarmuka database dokumen seperti MongoDB, namun menggunakan SQLite sebagai penyimpan data. Library ini dirancang untuk memberikan keseimbangan antara kemudahan penggunaan database dokumen dengan kecepatan dan keandalan SQLite.

## Fitur Utama

- **Database Dokumen**: Penyimpanan data dalam format JSON yang fleksibel
- **Query MongoDB-like**: Mendukung operator query seperti `$gt`, `$in`, `$regex`, `$or`, `$and`, dll.
- **Schema Validation**: Validasi dokumen dengan aturan schema yang ketat
- **Encryption**: Enkripsi per-collection dengan AES-256-CBC
- **Hooks**: Event hooks untuk before/after operations (insert, update, remove)
- **Soft Delete**: Penghapusan lunak dengan restore capability
- **Searchable Fields**: Field pencarian dengan dukungan hashing untuk privasi
- **Population**: Pengisian relasi antar dokumen (populate)
- **Indexing**: Index untuk field JSON menggunakan `json_extract`
- **ID Generation**: Auto UUID v4 atau manual dengan prefix
- **Cross-Database Relations**: Relasi antar database dengan populate
- **Performance**: Optimasi untuk concurrency menggunakan WAL mode
- **Health Monitoring**: Metrik kesehatan database dan collection
- **Configuration**: Dynamic configuration save/load per collection
- **Query Executor**: Prepared statements, logging, dan performance monitoring

## Instalasi

### Persyaratan

- PHP 8.0 atau lebih tinggi
- PDO SQLite (biasanya sudah tersedia)
- Ekstensi OpenSSL untuk fitur enkripsi

### Via Composer

```bash
composer require bangrondb/bangrondb
```

### Manual

Download file-file sumber dan include secara manual:

```php
require_once 'src/Database.php';
require_once 'src/Client.php';
require_once 'src/Collection.php';
require_once 'src/Cursor.php';
require_once 'src/UtilArrayQuery.php';
require_once 'src/QueryExecutor.php';
require_once 'src/Factory.php';
require_once 'src/Config.php';
require_once 'src/DatabaseMetrics.php';
require_once 'src/CollectionManager.php';
```

## Penggunaan Dasar

### Membuat Database

```php
use BangronDB\Client;

// Membuat client database
$client = new BangronDB\Client('/path/to/database/directory');

// Pilih database (otomatis dibuat jika tidak ada)
$db = $client->mydb;
```

### Operasi CRUD

```php
// Pilih collection
$users = $db->users;

// Insert dokumen
$userId = $users->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
]);

// Find dokumen
$user = $users->findOne(['name' => 'John Doe']);

// Update dokumen
$users->update(
    ['_id' => $userId],
    ['$set' => ['age' => 31]]
);

// Remove dokumen
$users->remove(['name' => 'John Doe']);
```

### Query Lanjutan

```php
// Query dengan operator komparasi
$youngUsers = $users->find([
    'age' => ['$lt' => 25, '$gte' => 18]
])->toArray();

// Query dengan regex dan case-insensitive
$usersWithGmail = $users->find([
    'email' => ['$regex' => '@gmail\.com$', '$options' => 'i']
])->toArray();

// Query dengan $in dan $nin
$activeAdmins = $users->find([
    'status' => 'active',
    'role' => ['$in' => ['admin', 'moderator']]
])->toArray();

// Query dengan $or dan $and
$complexQuery = $users->find([
    '$or' => [
        ['age' => ['$lt' => 20]],
        ['role' => 'admin']
    ],
    'status' => 'active'
])->toArray();

// Query fuzzy search (text similarity)
$searchResults = $users->find([
    'name' => ['$fuzzy' => ['search' => 'john', 'distance' => 2]]
])->toArray();

// Query dengan exists
$usersWithPhone = $users->find([
    'phone' => ['$exists' => true]
])->toArray();

// Sorting dan pagination
$users = $users->find()
    ->sort(['name' => 1, 'created_at' => -1])
    ->limit(10)
    ->skip(20)
    ->toArray();
```

## Enkripsi

```php
// Set encryption untuk collection
$users->setEncryptionKey('your-secret-key');

// Dokumen akan dienkripsi secara otomatis
$userId = $users->insert([
    'name' => 'John Doe',
    'ssn' => 'sensitive-data' // Akan dienkripsi
]);
```

## Schema Validation

```php
// Set schema validation untuk collection
$users->setSchema([
    'name' => ['required' => true, 'type' => 'string', 'min' => 2],
    'email' => ['required' => true, 'type' => 'string', 'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
    'age' => ['type' => 'int', 'min' => 0, 'max' => 150],
    'role' => ['enum' => ['user', 'admin', 'moderator']]
]);

// Validasi otomatis saat insert/update
$userId = $users->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
    'role' => 'user'
]);
```

## Soft Delete

```php
// Enable soft delete
$users->useSoftDeletes(true);

// Delete akan menandai field _deleted_at
$users->remove(['name' => 'John Doe']); // Soft delete

// Query hanya data aktif (default)
$activeUsers = $users->find()->toArray();

// Query termasuk yang dihapus
$allUsers = $users->withTrashed()->find()->toArray();

// Query hanya yang dihapus
$deletedUsers = $users->onlyTrashed()->find()->toArray();

// Restore data yang dihapus
$users->restore(['name' => 'John Doe']);

// Force delete permanen
$users->forceDelete(['name' => 'John Doe']);
```

## Searchable Fields

```php
// Konfigurasi field pencarian dengan hashing
$users->setSearchableFields(['email'], true); // Hash email untuk privasi

// Sekarang bisa query dengan email yang di-hash
$user = $users->findOne(['email' => 'john@example.com']);

// Konfigurasi dengan multiple fields
$products->setSearchableFields([
    'name' => ['hash' => false],    // Plain text
    'category' => ['hash' => true], // Hashed
    'tags' => false                 // Array handling
]);
```

## Hooks

```php
// Hook before insert
$users->on(Collection::HOOK_BEFORE_INSERT, function($doc) {
    $doc['created_at'] = date('c');
    return $doc;
});

// Hook after insert
$users->on(Collection::HOOK_AFTER_INSERT, function($doc, $id) {
    // Log insert operation
    error_log("User inserted: {$id}");
});

// Hook before update
$users->on(Collection::HOOK_BEFORE_UPDATE, function($criteria, $data) {
    $data['$set']['updated_at'] = date('c');
    return ['criteria' => $criteria, 'data' => $data];
});

// Hook before remove (dapat membatalkan penghapusan)
$users->on(Collection::HOOK_BEFORE_REMOVE, function($doc) {
    if ($doc['role'] === 'admin') {
        return false; // Batalkan penghapusan admin
    }
    return true;
});
```

## Configuration Management

```php
// Konfigurasi collection tersimpan dalam database
$users->setEncryptionKey('user-encryption-key');
$users->setSearchableFields(['email'], true);
$users->setSchema([...]);
$users->useSoftDeletes(true);

// Simpan konfigurasi ke database
$users->saveConfiguration();

// Konfigurasi akan dimuat otomatis saat collection diakses
// Atau muat manual
$users->loadConfiguration();
```

## Population (Relasi)

```php
// Misalkan ada collection posts yang referensi users
$posts->populate($postArray, 'author_id', 'users', '_id', 'author');
```

## Health Monitoring

```php
$metrics = $db->getHealthMetrics();
print_r($metrics);

$report = $db->getHealthReport();
echo $report['status']; // 'healthy', 'warning', atau 'critical'
```

## Factory Pattern

```php
use BangronDB\Factory;
use BangronDB\Config;

// Konfigurasi default
Config::set('default_path', '/var/data/bangrondb');
Config::set('encryption_key', 'default-key');

// Membuat instances dengan factory
$client = Factory::createClient();
$database = Factory::createDatabase('/path', 'mydb');
$collection = Factory::createCollection('/path', 'mydb', 'users');
```

## Query Executor

```php
use BangronDB\QueryExecutor;

$executor = new QueryExecutor($pdo);

// Enable logging
$executor->setLogging(true);

// Execute dengan prepared statement
$stmt = $executor->executeQuery(
    'SELECT * FROM users WHERE email = ?',
    ['john@example.com']
);

// Get statistics
$stats = $executor->getQueryStats();
```

## Traits Overview

BangronDB menggunakan sistem traits untuk memberikan fleksibilitas dalam konfigurasi collection:

### EncryptionTrait

- Enkripsi AES-256-CBC per-collection
- Key management yang aman
- Otomatis encrypt/decrypt saat penyimpanan/pembacaan

### SchemaValidationTrait

- Validasi dokumen dengan aturan schema
- Support required, type, enum, min/max, regex validation
- Exception-based error reporting

### HooksTrait

- Event hooks: beforeInsert, afterInsert, beforeUpdate, afterUpdate, beforeRemove, afterRemove
- Hook chaining dan error handling
- Fleksibel untuk business logic injection

### SoftDeleteTrait

- Soft delete dengan field timestamp
- Query modifiers: withTrashed(), onlyTrashed()
- Restore functionality
- Compatible dengan hooks

### SearchableFieldsTrait

- Field pencarian dengan optional hashing
- Index otomatis untuk performa
- Privacy protection untuk sensitive data

### IdGeneratorTrait

- Auto UUID v4 generation
- Manual ID dengan prefix
- Flexible ID management

### QueryBuilderTrait

- MongoDB-like query operators
- JSON path querying
- Advanced filtering dan sorting

## API Reference

Untuk dokumentasi API lengkap, lihat folder [api/](api/) yang mencakup:

- [Client API](api/Client.md)
- [Database API](api/Database.md)
- [Collection API](api/Collection.md)
- [Cursor API](api/Cursor.md)
- [Config API](api/Config.md)
- [Factory API](api/Factory.md)
- [CollectionManager API](api/CollectionManager.md)
- [DatabaseMetrics API](api/DatabaseMetrics.md)
- [QueryExecutor API](api/QueryExecutor.md)
- [UtilArrayQuery API](api/UtilArrayQuery.md)
- [Exceptions API](api/Exceptions.md)
- [Traits API](api/traits/README.md)

Dokumentasi API lengkap menyediakan informasi detail tentang setiap class, metode, parameter, dan contoh penggunaan.

```php
class Client {
    // Constructor
    public function __construct(string $path, array $options = [])

    // Database management
    public function listDBs(): array
    public function selectDB(string $name, array $options = []): Database
    public function __get(string $database): Database

    // Collection management
    public function selectCollection(string $database, string $collection): Collection

    // Cleanup
    public function close(): void
    public function __destruct()
}
```

### Database API

```php
class Database {
    // Properties
    public string $path
    public ?Client $client
    public ?string $encryptionKey

    // Connection management
    public $connection // PDO instance
    public QueryExecutor $queryExecutor

    // Collection management
    public function getCollectionNames(): array
    public function selectCollection(string $name): Collection
    public function createCollection(string $name): void
    public function dropCollection(string $name): void
    public function __get(string $collection): Collection

    // Configuration
    public function saveCollectionConfig(string $name, array $config): void
    public function loadCollectionConfig(string $name): array
    public function getAllCollectionConfigs(): array

    // Monitoring & Health
    public function getHealthMetrics(): array
    public function getHealthReport(): array
    public function getDataMetrics(): array
    public function getPerformanceMetrics(): array
    public function getIndexMetrics(): array
    public function getCollectionMetrics(): array

    // Indexing
    public function createJsonIndex(string $collection, string $field, ?string $indexName = null): void
    public function dropIndex(string $indexName): void

    // Maintenance
    public function vacuum(): void
    public function drop(): void
    public function checkIntegrity(): array
}
```

### Collection API

```php
class Collection {
    // Properties
    public Database $database
    public string $name

    // Constants
    const ID_MODE_AUTO = 'auto'
    const ID_MODE_MANUAL = 'manual'
    const ID_MODE_PREFIX = 'prefix'
    const HOOK_BEFORE_INSERT = 'beforeInsert'
    const HOOK_AFTER_INSERT = 'afterInsert'
    const HOOK_BEFORE_UPDATE = 'beforeUpdate'
    const HOOK_AFTER_UPDATE = 'afterUpdate'
    const HOOK_BEFORE_REMOVE = 'beforeRemove'
    const HOOK_AFTER_REMOVE = 'afterRemove'

    // CRUD Operations
    public function insert(array $document = []): mixed
    public function save(array $document, bool $create = false): mixed
    public function update($criteria, array $data, bool $merge = true): int
    public function remove($criteria): int
    public function forceDelete($criteria): int

    // Query Operations
    public function find($criteria = null, $projection = null): Cursor
    public function findOne($criteria = null, $projection = null): ?array
    public function count($criteria = null): int

    // Population & Relations
    public function populate(array $documents, string $localField, string $foreign, string $foreignField = '_id', ?string $as = null): mixed

    // Schema & Validation
    public function setSchema(array $schema): self
    public function getSchema(): array
    public function validate(array $document): bool

    // Encryption
    public function setEncryptionKey(?string $key): self
    public function isEncrypted(): bool

    // Hooks
    public function on(string $event, callable $fn): void
    public function off(string $event, ?callable $fn = null): void
    public function getHooks(): array

    // Soft Delete
    public function useSoftDeletes(bool $enabled = true): self
    public function softDeletesEnabled(): bool
    public function getDeletedAtField(): string
    public function restore($criteria): int

    // Searchable Fields
    public function setSearchableFields(array $fields, bool $hash = false): self
    public function getSearchableFields(): array

    // ID Management
    public function setIdMode(string $mode): self
    public function getIdMode(): string

    // Configuration
    public function saveConfiguration(): bool
    public function loadConfiguration(): bool

    // Utility
    public function drop(): void
    public function renameCollection(string $newname): bool
    public function createIndex(string $field, ?string $indexName = null): void
    public function getLastModified(): array
}
```

### Cursor API

```php
class Cursor implements \IteratorAggregate {
    // Properties
    public Collection $collection
    public $criteria

    // Query Modifiers
    public function limit(int $limit): self
    public function skip(int $skip): self
    public function sort($sort): self

    // Population
    public function populate(string $path, Collection $collection, array $options = []): self
    public function populateMany(array $defs): self
    public function with(string|array $path, ?Collection $collection = null, array $options = []): self

    // Soft Delete Modifiers
    public function withTrashed(): self
    public function onlyTrashed(): self

    // Output
    public function toArray(): array
    public function toArraySafe(?int $maxResults = null): array
    public function count(): int
    public function each($callable): self

    // Iterator Interface
    public function getIterator(): \Traversable
    public function current(): ?array
    public function key(): int
    public function next(): void
    public function valid(): bool
    public function rewind(): void
}
```

### UtilArrayQuery API

```php
class UtilArrayQuery {
    // Query Operations
    public static function match($criteria, $document): bool
    public static function get(array $data, string $path, $default = null): mixed
    public static function check($value, $condition): bool

    // Fuzzy Search
    public static function fuzzy_search($search, $text, $distance = 3): float
    public static function levenshtein_utf8($s1, $s2): int

    // ID Generation
    public static function generateId(): string
}
```

### QueryExecutor API

```php
class QueryExecutor {
    public function __construct(\PDO $connection)

    // Configuration
    public function setLogging(bool $enabled): self
    public function setPerformanceMonitoring(bool $enabled): self
    public function clearLogs(): void

    // Query Execution
    public function executeQuery(string $sql, array $params = []): \PDOStatement
    public function executeUpdate(string $sql, array $params = []): int
    public function executeTransaction(array $queries): array

    // Utilities
    public function getLastInsertId(): string
    public function quote(string $string): string
    public function tableExists(string $tableName): bool
    public function sanitizeIdentifier(string $identifier): string
    public function quoteTable(string $tableName): string

    // Monitoring
    public function getQueryLog(): array
    public function getQueryStats(): array
}
```

### Factory API

```php
class Factory {
    public static function createClient(?string $path = null, array $options = []): Client
    public static function createDatabase(string $path, string $name, array $options = []): Database
    public static function createCollection(string $path, string $databaseName, string $collectionName, array $options = []): Collection
    public static function createCollectionFromDatabase(Database $database, string $collectionName): Collection
}
```

### Config API

```php
class Config {
    public static function set(string $key, $value): void
    public static function get(string $key, $default = null)
    public static function all(): array
    public static function reset(): void
    public static function has(string $key): bool
}
```

### DatabaseMetrics API

```php
class DatabaseMetrics {
    public function __construct(Database $db)
    public function getHealthMetrics(): array
    public function checkIntegrity(): array
    public function getDataMetrics(): array
    public function getPerformanceMetrics(): array
    public function getIndexMetrics(): array
    public function getCollectionMetrics(): array
}
```

### CollectionManager API

```php
class CollectionManager {
    public function __construct(Database $database)
    public function setCacheEnabled(bool $enabled): void
    public function isCacheEnabled(): bool
    public function saveCollectionConfig(string $collectionName, array $config): void
    public function loadCollectionConfig(string $collectionName): array
    public function getAllCollectionConfigs(): array
    public function deleteCollectionConfig(string $collectionName): void
    public function getMetadata(string $collectionName): array
    public function getAllMetadata(): array
    public function clearCaches(): void
    public function getCollectionStats(string $collectionName): array
    public function getAllCollectionStats(): array
}
```

## Lihat Dokumentasi Lainnya

- [Getting Started](getting-started.md) - Panduan memulai
- [Advanced Features](advanced.md) - Fitur lanjutan
- [Performance & Security](performance-security.md) - Optimasi dan keamanan
- **[Security Enhancements](SECURITY-ENHANCEMENTS.md) - Validasi keamanan terbaru** ⭐ NEW
- [Deployment Guide](deployment-production.md) - Deploy ke production
- [Framework Integration](framework-integration.md) - Integrasi dengan framework
- [Migration Guide](migration-upgrade.md) - Migrasi dan upgrade
- [Troubleshooting](troubleshooting.md) - Pemecahan masalah
- [API Reference](api/) - Dokumentasi API lengkap
