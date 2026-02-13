# BangronDB API Reference

Dokumentasi API lengkap untuk library BangronDB.

## Classes Utama

### Core Classes

| Class                       | Deskripsi                              | File           |
| --------------------------- | -------------------------------------- | -------------- |
| [Client](Client.md)         | Client utama untuk mengelola databases | Client.php     |
| [Database](Database.md)     | Instance database SQLite               | Database.php   |
| [Collection](Collection.md) | Collection untuk operasi dokumen       | Collection.php |
| [Cursor](Cursor.md)         | Cursor untuk iterasi hasil query       | Cursor.php     |

### Management Classes

| Class                                     | Deskripsi                        | File                  |
| ----------------------------------------- | -------------------------------- | --------------------- |
| [Config](Config.md)                       | Konfigurasi global library       | Config.php            |
| [Factory](Factory.md)                     | Factory untuk membuat instances  | Factory.php           |
| [CollectionManager](CollectionManager.md) | Manajemen metadata & konfigurasi | CollectionManager.php |
| [DatabaseMetrics](DatabaseMetrics.md)     | Metrik kesehatan database        | DatabaseMetrics.php   |

### Utility Classes

| Class                               | Deskripsi                               | File               |
| ----------------------------------- | --------------------------------------- | ------------------ |
| [QueryExecutor](QueryExecutor.md)   | Eksekusi query dengan enhanced features | QueryExecutor.php  |
| [UtilArrayQuery](UtilArrayQuery.md) | Utilitas query & array operations       | UtilArrayQuery.php |

### Exceptions

| Class                       | Deskripsi                           | File |
| --------------------------- | ----------------------------------- | ---- |
| [Exceptions](Exceptions.md) | Dokumentasi semua exception classes | -    |

## Traits

Lihat [Traits README](traits/README.md) untuk dokumentasi lengkap:

- [EncryptionTrait](traits/EncryptionTrait.md) - Enkripsi AES-256-CBC
- [HooksTrait](traits/HooksTrait.md) - Sistem event hooks
- [IdGeneratorTrait](traits/IdGeneratorTrait.md) - Generasi ID dokumen
- [QueryBuilderTrait](traits/QueryBuilderTrait.md) - Query builder
- [SchemaValidationTrait](traits/SchemaValidationTrait.md) - Validasi schema
- [SearchableFieldsTrait](traits/SearchableFieldsTrait.md) - Field pencarian
- [SoftDeleteTrait](traits/SoftDeleteTrait.md) - Soft delete

## Quick API Reference

### Client

```php
$client = new Client('/path/to/database');
$databases = $client->listDBs();
$db = $client->selectDB('mydb');
$db = $client->mydb; // Magic property
```

### Database

```php
$db = $client->selectDB('mydb');
$collections = $db->getCollectionNames();
$collection = $db->selectCollection('users');
$collection = $db->users; // Magic property

// Metrik
$metrics = $db->getHealthMetrics();
```

### Collection

```php
$collection = $db->users;

// CRUD
$id = $collection->insert(['name' => 'John']);
$doc = $collection->findOne(['_id' => $id]);
$updated = $collection->update(['_id' => $id], ['$set' => ['name' => 'Jane']]);
$removed = $collection->remove(['_id' => $id]);

// Query
$cursor = $collection->find(['status' => 'active'])
    ->sort(['created_at' => -1])
    ->limit(10);

$count = $collection->count(['status' => 'active']);
```

### Cursor

```php
$cursor = $collection->find(['status' => 'active']);

// Array conversion
$docs = $cursor->toArray();

// Iterator
foreach ($cursor as $doc) {
    // process $doc
}

// Pagination
$cursor->limit(10)->skip(20);

// Soft deletes
$cursor->withTrashed()->toArray();
$cursor->onlyTrashed()->toArray();
```

---

## Error Handling

```php
use BangronDB\Exceptions\DatabaseException;
use BangronDB\Exceptions\CollectionException;
use BangronDB\Exceptions\ValidationException;

try {
    $collection->insert($data);
} catch (ValidationException $e) {
    // Validasi gagal
} catch (CollectionException $e) {
    // Collection error
} catch (DatabaseException $e) {
    // Database error
}
```

---

## Dependency Injection

### Manual

```php
use BangronDB\Client;

$client = new Client('/path/to/data');
$db = $client->selectDB('app');
$collection = $db->selectCollection('users');
```

### Factory Pattern

```php
use BangronDB\Config;
use BangronDB\Factory;

Config::set('default_path', '/path/to/data');

$client = Factory::createClient();
$db = Factory::createDatabase('/path/to/data', 'app');
$collection = Factory::createCollection('/path/to/data', 'app', 'users');
```

---

## Configuration

### Client Options

```php
$client = new Client('/path/to/data', [
    'encryption_key' => 'key',
    'query_logging' => true,
    'performance_monitoring' => true,
]);
```

### Global Config

```php
Config::set('default_path', ':memory:');
Config::set('encryption_key', 'default-key');
Config::set('journal_mode', 'WAL');
```

---

## Lifecycle

### Opening Database

```php
$client = new Client('/path/to/data');
$db = $client->selectDB('app');
```

### Closing Database

```php
$db->close();     // Tutup satu database
$client->close(); // Tutup semua databases

// Atau biarkan destructor menutup otomatis
```

---

## Best Practices

1. **Gunakan try-catch untuk error handling**
2. **Aktifkan query logging saat development**
3. **Gunakan prepared statements (auto dengan QueryExecutor)**
4. **Simpan konfigurasi collection dengan `saveConfiguration()`**
5. **Gunakan soft deletes untuk data sensitif**
6. **Enable encryption untuk data rahasia**
7. **Gunakan searchable fields untuk encrypted data lookup**
