# BangronDB

BangronDB adalah database dokumen berbasis SQLite untuk PHP dengan API bergaya MongoDB. Library ini cocok untuk aplikasi kecil hingga menengah yang membutuhkan penyimpanan lokal, query fleksibel, enkripsi, hooks, schema validation, dan relasi sederhana tanpa harus menjalankan server database terpisah.

[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%3E%3D%208.1-blue.svg)](https://www.php.net)

## Sorotan Fitur

- API mirip MongoDB untuk operasi dokumen
- Backend SQLite berbasis file atau in-memory
- Enkripsi dokumen dengan **AES-256-GCM**
- Searchable fields untuk data terenkripsi
- Hooks untuk lifecycle insert, update, dan remove
- Schema validation untuk type, enum, regex, min/max
- Soft delete dengan restore dan force delete
- ID mode fleksibel: UUID, manual, prefix
- Populate relasi antar-collection dan antar-database dalam satu client
- Health metrics, integrity check, dan change notification
- Konfigurasi collection yang bisa disimpan ke database

## Kebutuhan Sistem

- PHP **8.1+**
- Ekstensi `pdo_sqlite`
- Ekstensi `openssl`
- Composer

## Instalasi

```bash
composer require herdianrony/bangrondb
```

## Quick Start

```php
use BangronDB\Client;

$client = new Client(__DIR__ . '/data');
$client->createDB('app');
$client->createCollection('app', 'users');

$users = $client->selectCollection('app', 'users');

// Insert
$userId = $users->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'role' => 'admin',
]);

// Find
$user = $users->findOne(['_id' => $userId]);

echo $user['name'] . PHP_EOL;

// Update
$users->update(['_id' => $userId], [
    '$set' => ['role' => 'superadmin'],
]);

// Delete
$users->remove(['_id' => $userId]);
```

## Konsep Dasar

```text
Client -> Database (.bangron / :memory:) -> Collection -> Document
```

- **Client** mengelola banyak database dalam satu path.
- **Database** mewakili satu file SQLite/BangronDB.
- **Collection** mewakili tabel dokumen.
- **Document** disimpan sebagai JSON.

## Membuat Client dan Database

```php
use BangronDB\Client;

// File-based storage
$client = new Client(__DIR__ . '/data');

// In-memory
$memoryClient = new Client(':memory:');

// Dengan opsi runtime
$secureClient = new Client(__DIR__ . '/data', [
    'encryption_key' => $_ENV['DB_ENCRYPTION_KEY'] ?? null,
    'query_logging' => false,
    'performance_monitoring' => false,
]);

// API eksplisit untuk lifecycle database
$client->createDB('app');
$client->dbExists('app'); // true

$db = $client->selectDB('app');
$db = $client->app; // magic getter, untuk database yang sudah ada

$client->createCollection('app', 'users');
$client->collectionExists('app', 'users'); // true
$client->listCollections('app');           // ['users', ...] (alias: listCollection)

$users = $client->selectCollection('app', 'users');
$users = $db->users; // magic getter, untuk collection yang sudah ada

$client->renameCollection('app', 'users', 'members');
$client->dropCollection('app', 'members');

// Atau dari object Database jika lebih nyaman
$db->createCollection('logs');
$db->collectionExists('logs'); // true

// Rename / hapus database
$client->renameDB('app', 'app_v2');
$client->dropDB('app_v2');

$client->close();
```

> Mulai versi ini, `selectDB()` dan `selectCollection()` bersifat **non-lazy**: keduanya hanya memilih resource yang sudah ada.
>
> Untuk membuat resource baru secara eksplisit, gunakan:
>
> - `createDB()` untuk database
> - `createCollection()` untuk collection

## CRUD

### Insert

```php
$id = $users->insert([
    'name' => 'Alice',
    'email' => 'alice@example.com',
]);

$count = $users->insert([
    ['name' => 'Bob'],
    ['name' => 'Charlie'],
]);
```

### Find

```php
$all = $users->find()->toArray();
$one = $users->findOne(['name' => 'Alice']);

$activeAdults = $users->find([
    'status' => 'active',
    'age' => ['$gte' => 21],
])->toArray();

$projection = $users->find(
    ['status' => 'active'],
    ['name' => 1, 'email' => 1]
)->toArray();

$total = $users->count(['status' => 'active']);
```

### Update

```php
// Merge update (default)
$users->update(['name' => 'Alice'], ['city' => 'Jakarta']);

// Replace update
$users->update(['name' => 'Alice'], ['name' => 'Alice', 'city' => 'Bandung'], false);

// Operator-style update
$users->update(['name' => 'Alice'], [
    '$set' => ['role' => 'editor'],
    '$unset' => ['legacy_field' => ''],
]);
```

### Save / Upsert

```php
// Tanpa _id => insert baru
$newId = $users->save(['name' => 'Dina']);

// Dengan _id => update jika sudah ada, insert jika belum ada
$users->save([
    '_id' => 'USR-000001',
    'name' => 'Dina Updated',
]);
```

### Delete

```php
$deleted = $users->remove(['status' => 'inactive']);
$users->remove([]); // hapus semua dokumen
```

## Pagination, Sorting, Projection

```php
$results = $users->find(['status' => 'active'])
    ->sort(['age' => 1])
    ->skip(10)
    ->limit(5)
    ->toArray();
```

## Query Operators

### Comparison

```php
$users->find(['age' => ['$gt' => 18]]);
$users->find(['age' => ['$gte' => 21]]);
$users->find(['age' => ['$lt' => 65]]);
$users->find(['age' => ['$lte' => 60]]);
$users->find(['age' => ['$ne' => 30]]);
```

### Array / Membership

```php
$users->find(['role' => ['$in' => ['admin', 'editor']]]);
$users->find(['role' => ['$nin' => ['guest', 'banned']]]);
$users->find(['tags' => ['$all' => ['php', 'sqlite']]]);
$users->find(['tags' => ['$size' => 3]]);
```

### Existence dan Logical

```php
$users->find(['email' => ['$exists' => true]]);

$users->find(['$or' => [
    ['age' => ['$lt' => 18]],
    ['age' => ['$gt' => 65]],
]]);

$users->find(['$and' => [
    ['status' => 'active'],
    ['age' => ['$gte' => 21]],
]]);
```

### Regex, Closure, Fuzzy Search, Dot Notation

```php
$users->find(['name' => ['$regex' => '^John']]);

$users->find(['age' => ['$where' => fn($doc) => $doc['age'] > 18]]);
$users->find(['name' => ['$func' => fn($val) => strlen($val) > 5]]);

$users->find([
    'description' => [
        '$fuzzy' => [
            '$search' => 'important',
            '$minScore' => 0.7,
        ],
    ],
]);

$users->find(['address.city' => 'Jakarta']);
```

> `'$where'` dan `'$func'` hanya menerima **Closure**, bukan string function name.

## Enkripsi

### Database-level encryption

```php
use BangronDB\Database;

$db = new Database(__DIR__ . '/secure.bangron', [
    'encryption_key' => $_ENV['DB_ENCRYPTION_KEY'],
]);
```

### Collection-level encryption

```php
$users->setEncryptionKey($_ENV['DB_ENCRYPTION_KEY']);

$users->insert([
    'name' => 'Alice',
    'ssn' => '123-45-6789',
]);
```

### Searchable fields untuk data terenkripsi

```php
$users->setEncryptionKey($_ENV['DB_ENCRYPTION_KEY']);
$users->setSearchableFields(['email', 'phone'], true); // true = SHA-256 hash
$users->saveConfiguration();
```

**Catatan teknis:** BangronDB menggunakan **AES-256-GCM**, key derivation berbasis PBKDF2 SHA-256, IV acak per enkripsi, dan payload Base64 di dokumen JSON.

## Schema Validation

```php
$users->setSchema([
    'username' => ['required' => true, 'type' => 'string', 'min' => 3, 'max' => 50],
    'email'    => ['required' => true, 'type' => 'string', 'unique' => true, 'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
    'age'      => ['type' => 'int', 'min' => 13, 'max' => 120],
    'role'     => ['type' => 'string', 'enum' => ['admin', 'user', 'moderator']],
]);

$users->validate([
    'username' => 'john',
    'email' => 'john@example.com',
]);
```

> Validasi `enum` menggunakan strict comparison. Misalnya, nilai `0`, `false`, dan `'0'` dianggap berbeda.

### Unique constraint

Tandai sebuah field dengan `'unique' => true` agar BangronDB menolak dokumen baru
(atau update) yang nilainya sudah ada di koleksi. Pengecekan dijalankan otomatis
saat `insert()` / `update()` dan melempar `ValidationException`
(`UNIQUE_CONSTRAINT_VIOLATION`). Nilai `null` tidak dikenai constraint, dan update
pada dokumen yang sama (nilai tidak berubah) tidak dianggap duplikat.

```php
$users->setSchema(['email' => ['type' => 'string', 'unique' => true]]);
$users->insert(['email' => 'a@example.com']);
$users->insert(['email' => 'a@example.com']); // throws ValidationException
```

> Catatan untuk field **terenkripsi**: pengecekan unik memakai query equality,
> sehingga pada koleksi terenkripsi field tersebut harus juga dijadikan
> **searchable** (`setSearchableFields([... => ['hash' => true]])`) agar nilai
> dapat dicari lewat blind index. Tanpa itu, dokumen terenkripsi tidak bisa
> di-query per-nilai dan constraint tidak akan menemukan duplikat.

## Soft Deletes

```php
$users->useSoftDeletes(true);

$users->remove(['username' => 'johndoe']);
$users->find()->withTrashed()->toArray();
$users->find()->onlyTrashed()->toArray();
$users->restore(['username' => 'johndoe']);
$users->forceDelete(['username' => 'johndoe']);
```

## Hooks

```php
$users->on('beforeInsert', function ($document) {
    $document['created_at'] = date('c');
    return $document;
});

$users->on('afterInsert', function ($document, $insertId) {
    error_log('Inserted: ' . $insertId);
});

$users->on('beforeUpdate', function ($criteria, $data) {
    $data['updated_at'] = date('c');
    return ['criteria' => $criteria, 'data' => $data];
});

$users->on('beforeRemove', function ($document) {
    if ($document['protected'] ?? false) {
        return false;
    }
});
```

Event yang tersedia:

- `beforeInsert`
- `afterInsert`
- `beforeUpdate`
- `afterUpdate`
- `beforeRemove`
- `afterRemove`

## Relationships / Populate

```php
$posts = $db->posts->find()
    ->populate('author_id', $db->users, ['as' => 'author'])
    ->toArray();

$post = $db->posts->populate($post, 'comment_ids', 'app.comments', '_id', 'comments');
```

## Indexing

```php
$users->createIndex('email');
$users->createIndex('address.city');
$users->createIndex('status', 'idx_status');

$db->dropIndex('idx_status');
```

## Health & Monitoring

```php
$health = $db->getHealthMetrics();
$report = $db->getHealthReport();
$perf   = $db->getPerformanceMetrics();
$index  = $db->getIndexMetrics();
$coll   = $db->getCollectionMetrics();

$db->checkIntegrity();
$db->vacuum();
```

## Change Notification

```php
$lastModified = $users->getLastModified();
// ['version' => 42, 'last_updated' => '2026-06-20T10:30:45+07:00']

$users->notifyChange();
```

## Dynamic Configuration

Konfigurasi collection berikut bisa disimpan ke database:

- ID mode
- searchable fields
- schema
- soft deletes
- custom config

```php
$users->setIdModePrefix('USR');
$users->setSearchableFields(['email'], true);
$users->setSchema([...]);
$users->useSoftDeletes(true);
$users->saveConfiguration();
```

> Encryption key **tidak disimpan** di database. Selalu supply dari `.env`, secret manager, atau runtime config.
>
> Catatan: konfigurasi ID prefix yang dipersist kini dinormalisasi ke format `prefix:USR`. Konfigurasi lama yang masih menyimpan prefix mentah seperti `USR` tetap didukung saat dibaca ulang.

## Transactions

BangronDB menggunakan PDO SQLite di bawahnya, jadi Anda bisa memakai transaksi langsung lewat koneksi PDO:

```php
$db->connection->beginTransaction();

try {
    $db->users->insert(['name' => 'Alice']);
    $db->profiles->insert(['user' => 'Alice']);
    $db->connection->commit();
} catch (\Throwable $e) {
    $db->connection->rollBack();
    throw $e;
}
```

## Keamanan

BangronDB menerapkan beberapa guardrail penting:

| Fitur | Tujuan |
|------|--------|
| Closure-only untuk `$where` / `$func` | Mencegah RCE |
| Validasi field name | Mencegah injection |
| PRAGMA key escaping | Mencegah SQLite injection |
| Regex hardening | Mengurangi risiko ReDoS |
| Validasi path | Mengurangi risiko path traversal |
| `strict_types=1` | Type safety |

Lihat juga [SECURITY_USAGE_GUIDE.md](SECURITY_USAGE_GUIDE.md).

## API Ringkas

### Client

| Method | Keterangan |
|--------|------------|
| `new Client($path, $options = [])` | Membuat client |
| `createDB($name, $options = [])` | Membuat database secara eksplisit |
| `dbExists($name)` | Mengecek apakah database ada |
| `listDBs()` | Daftar database |
| `selectDB($name)` | Ambil database |
| `renameDB($oldName, $newName)` | Rename database |
| `dropDB($name)` | Hapus database |
| `createCollection($db, $collection)` | Membuat collection langsung dari level client |
| `collectionExists($db, $collection)` | Mengecek collection dari level client |
| `listCollections($db)` / `listCollection($db)` | Daftar nama collection di sebuah database (`[]` jika DB tidak ada) |
| `renameCollection($db, $oldName, $newName)` | Rename collection dari level client |
| `dropCollection($db, $collection)` | Hapus collection dari level client |
| `selectCollection($db, $collection)` | Ambil collection langsung |
| `close()` | Tutup koneksi |

### Database

| Method | Keterangan |
|--------|------------|
| `selectCollection($name)` | Ambil collection |
| `createCollection($name)` | Buat collection |
| `collectionExists($name)` | Mengecek apakah collection ada |
| `renameCollection($oldName, $newName)` | Rename collection |
| `dropCollection($name)` | Hapus collection |
| `getCollectionNames()` | Daftar nama collection |
| `createJsonIndex($collection, $field, $indexName = null)` | Buat index JSON |
| `dropIndex($indexName)` | Hapus index |
| `getHealthMetrics()` | Ambil health metrics |
| `getHealthReport()` | Ambil health report |
| `getPerformanceMetrics()` | Ambil metrik performa |
| `getCollectionMetrics()` | Ambil metrik per collection |
| `saveCollectionConfig($name, $config)` | Simpan konfigurasi |
| `loadCollectionConfig($name)` | Muat konfigurasi |
| `deleteCollectionConfig($name)` | Hapus konfigurasi |
| `checkIntegrity()` | Jalankan integrity check |
| `vacuum()` | Optimasi file database |

### Collection

| Method | Keterangan |
|--------|------------|
| `insert($document)` | Insert satu/banyak dokumen |
| `find($criteria = null, $projection = null)` | Query dokumen |
| `findOne($criteria = null, $projection = null)` | Query satu dokumen |
| `update($criteria, $data, $merge = true)` | Update dokumen |
| `remove($criteria)` | Hapus dokumen |
| `count($criteria = null)` | Hitung dokumen |
| `save($document)` | Insert / upsert dokumen |
| `drop()` | Hapus collection |
| `renameCollection($newName)` | Rename collection |
| `setIdModeAuto()` / `setIdModeManual()` / `setIdModePrefix($prefix)` | Atur mode ID |
| `setEncryptionKey($key, $version = null)` | Atur key enkripsi + versi (v1.2.0) |
| `setSearchableFields($fields, $hash = false)` | Atur searchable fields |
| `removeSearchableField($field, $dropColumn = false)` | Hapus searchable field |
| `setSchema($schema)` | Atur schema |
| `useSoftDeletes($enabled = true)` | Aktifkan soft delete |
| `restore($criteria)` | Restore dokumen terhapus |
| `forceDelete($criteria)` | Hapus permanen |
| `on($event, $callback)` | Register hook |
| `off($event, $callback = null)` | Hapus hook |
| `createIndex($field, $indexName = null)` | Buat index |
| `getLastModified()` | Ambil metadata perubahan |
| `notifyChange()` | Trigger manual change notification |
| `saveConfiguration()` | Simpan konfigurasi collection |

### Cursor

| Method | Keterangan |
|--------|------------|
| `limit($n)` | Batas hasil |
| `skip($n)` | Lewati hasil awal |
| `sort($fields)` | Urutkan hasil |
| `populate($field, $collection, $options = [])` | Populate relasi |
| `withTrashed()` | Sertakan soft-deleted |
| `onlyTrashed()` | Hanya soft-deleted |
| `toArray()` | Materialisasi ke array |
| `toArraySafe($maxResults = null)` | Materialisasi dengan batas aman |
| `each($callback)` | Iterasi tiap dokumen |

## Konfigurasi Environment

Salin `.env.example` menjadi `.env` lalu isi sesuai kebutuhan:

```env
DB_PATH=                         # Kosongkan untuk in-memory
ENCRYPTION_KEY=                  # Key kuat minimal 32 karakter
QUERY_LOGGING=false
PERFORMANCE_MONITORING=false
```

## Contoh Lengkap

Lihat folder [examples/](examples/) untuk contoh end-to-end:

- `01-quick-start-crud.php`
- `02-query-operators.php`
- `03-encryption-searchable.php`
- `04-schema-validation.php`
- `05-soft-deletes.php`
- `06-hooks.php`
- `07-relationships-populate.php`
- `08-transactions.php`
- `09-indexing-health-monitoring.php`
- `10-dynamic-configuration.php`
- `11-multiple-databases.php`
- `12-id-modes-collection-management.php`
- `13-security-features.php`
- `14-ecommerce-app.php`
- `15-auth-encrypted.php`

## Catatan Kompatibilitas

Jika Anda bermigrasi dari perilaku lama yang mengandalkan create implicit saat `selectDB()` / `selectCollection()`, lihat:

- [BACKWARD_COMPATIBILITY_NOTES.md](BACKWARD_COMPATIBILITY_NOTES.md)

## Kontribusi

Lihat [CONTRIBUTING.md](CONTRIBUTING.md).

## Lisensi

BangronDB dilisensikan dengan [MIT](LICENSE).
