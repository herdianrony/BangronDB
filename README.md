# BangronDB

BangronDB adalah database dokumen berbasis SQLite untuk PHP dengan API bergaya MongoDB. Library ini cocok untuk aplikasi kecil hingga menengah yang membutuhkan penyimpanan lokal, query fleksibel, enkripsi, hooks, schema validation, dan relasi sederhana tanpa harus menjalankan server database terpisah.

[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%3E%3D%208.1-blue.svg)](https://www.php.net)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%209-brightgreen.svg)](phpstan.neon)

## Sorotan Fitur

- API mirip MongoDB untuk operasi dokumen
- Backend SQLite berbasis file atau in-memory
- **Dual query strategy**: SQL-first via `json_extract`, fallback ke PHP-side `UtilArrayQuery` untuk query kompleks
- Enkripsi dokumen dengan **AES-256-GCM** + key rotation (v1.2.0)
- Searchable fields (blind index SHA-256) untuk query pada data terenkripsi
- Hooks untuk lifecycle insert, update, dan remove
- Schema validation: type, enum/options, regex, min/max, unique constraint
- Aggregation pipeline: `$match`, `$group`, `$sort`, `$limit`, `$skip`, `$project`, `$count`, `$unset`
- Soft delete dengan restore dan force delete
- TTL (Time-To-Live) auto-expiration
- Cursor streaming via PHP Generator untuk efisiensi memori
- ID mode fleksibel: UUID, manual, prefix
- Populate relasi antar-collection dan antar-database
- `EXPLAIN` query plan dan optimization suggestions
- Health metrics, integrity check, dan change notification
- Konfigurasi collection yang persisten ke database
- Security auditor utilitas (opsional)

## Arsitektur

### Struktur Kode

```
src/
├── Client.php              # Entry point: mengelola banyak database
├── Database.php            # Satu file .bangron / :memory:
├── Collection.php          # Tabel dokumen (menggunakan traits)
├── Cursor.php              # Lazy query result iterator
├── QueryExecutor.php       # Eksekusi SQL via PDO prepared statements
├── UtilArrayQuery.php      # PHP-side query engine (fallback)
├── Config.php              # Global configuration
├── Enums/                  # Enum: HookEvent, IdMode
├── Exceptions/             # Typed exceptions
├── Security/               # SecurityAuditor (utilitas opsional)
└── Traits/                 # Fitur-fitur collection (horizontal reuse)
    ├── QueryBuilderTrait.php
    ├── EncryptionTrait.php
    ├── SchemaValidationTrait.php
    ├── HooksTrait.php
    ├── SearchableFieldsTrait.php
    ├── IdGeneratorTrait.php
    ├── SoftDeleteTrait.php
    ├── TtlTrait.php
    ├── ChangeTrackingTrait.php
    └── ConfigurationPersistenceTrait.php
```

### Desain Trait-based

`Collection` menggunakan **traits** untuk horizontal code reuse — bukan inheritance. Setiap trait adalah unit tunggal yang fokus pada satu tanggung jawab:

| Trait | Tanggung Jawab |
|-------|---------------|
| `QueryBuilderTrait` | Query logic, `find()`, `findOne()`, `count()`, operator parsing |
| `EncryptionTrait` | Enkripsi/dekripsi AES-256-GCM, key rotation |
| `SchemaValidationTrait` | Validasi type, enum, regex, min/max, unique |
| `HooksTrait` | Event lifecycle (before/after insert/update/remove) |
| `SearchableFieldsTrait` | Blind index untuk query pada data terenkripsi |
| `IdGeneratorTrait` | UUID, manual, prefix ID generation |
| `SoftDeleteTrait` | Soft delete, restore, force delete |
| `TtlTrait` | Auto-expiration dokumen berdasarkan TTL |
| `ChangeTrackingTrait` | Versioning dan change notification |
| `ConfigurationPersistenceTrait` | Simpan/muat konfigurasi collection ke database |

Pendekatan ini menjaga `Collection.php` tetap ringan sebagai koordinator, sementara logika detail tersebar di file terpisah yang bisa di-maintain secara independen.

### Dual Query Strategy

BangronDB menggunakan **dua lapis query** secara otomatis:

```
Query masuk → _canTranslateToJsonWhere()?
                ├─ YA → _buildJsonWhere() → SQL WHERE via json_extract/->>/->  (cepat)
                └─ TIDAK → fetch semua → UtilArrayQuery::match() di PHP        (fleksibel)
```

- **SQL-first**: Query sederhana (equality, comparison, `$in`, logical operators) diterjemahkan ke SQL `WHERE json_extract(document, '$.field')` dan dieksekusi langsung oleh SQLite engine. Ini memanfaatkan index dan meminimalkan data yang di-load ke PHP.
- **PHP fallback**: Query kompleks (regex, closure/`$where`, fuzzy search, dot notation nested) yang tidak bisa diterjemahkan ke SQL menggunakan `UtilArrayQuery` untuk filtering di level PHP.

Strategi ini otomatis — pengguna tidak perlu memilih secara manual.

### Yang BUKAN Bagian Core

Beberapa fitur yang sering dikira built-in sebenarnya adalah **pola aplikasi** yang didemonstrasikan di `examples/`, bukan bagian dari library:

- **RBAC/ACL** → Contoh 22, 23, 24: pola menggunakan `setCustomConfig()` + hooks untuk enforce permission di application layer
- **Change tracking audit log** → Contoh di examples: pola menggunakan hooks untuk mencatat perubahan
- **Authentication** → Contoh 20: pola hashing password + hooks

BangronDB menyediakan **primitif** (hooks, schema, custom config) yang memungkinkan pola-pola ini dibangun di atasnya, tanpa memaksakan paradigma tertentu ke aplikasi pengguna.

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

### InsertMany / UpdateMany / DeleteMany (API MongoDB-compatible)

```php
// InsertMany: insert batch dengan hasil detail
$result = $users->insertMany([
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob',   'email' => 'bob@example.com'],
]);
// ['inserted_count' => 2, 'inserted_ids' => ['...', '...']]

// UpdateMany: update semua dokumen yang cocok
$result = $users->updateMany(['status' => 'pending'], ['$set' => ['status' => 'active']]);
// ['matched_count' => 5, 'modified_count' => 5]

// DeleteMany
$result = $users->deleteMany(['status' => 'banned']);
// ['deleted_count' => 2]
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

## Aggregation Pipeline

```php
$results = $users->aggregate([
    ['$match'  => ['status' => 'active']],
    ['$group'  => ['_id' => '$role', 'total' => ['$sum' => 1], 'avg_age' => ['$avg' => '$age']]],
    ['$sort'   => ['total' => -1]],
    ['$limit'  => 10],
]);
```

Operator yang didukung:

| Stage | Deskripsi |
|-------|-----------|
| `$match` | Filter dokumen (sintaks sama seperti `find()`) |
| `$group` | Grouping dengan akumulator: `$sum`, `$avg`, `$min`, `$max`, `$count`, `$first`, `$last`, `$push`, `$addToSet` |
| `$sort` | Urutkan dokumen |
| `$limit` | Batasi jumlah hasil |
| `$skip` | Lewati N dokumen pertama |
| `$project` | Reshape dokumen (include/exclude field, field reference) |
| `$count` | Hitung dokumen yang lolos pipeline |
| `$unset` | Hapus field dari semua dokumen |

Field reference menggunakan prefix `$`: `'$fieldName'` merujuk ke nilai field dokumen.

## Cursor Streaming

Untuk dataset besar, gunakan `stream()` yang menghasilkan `Generator` — memori tetap konstan terlepas dari ukuran hasil:

```php
foreach ($users->stream(['status' => 'active'], [
    'sort'  => ['created_at' => -1],
    'limit' => 10000,
]) as $doc) {
    processDocument($doc); // hanya satu dokumen di memori
}
```

## Explain Query

Analisis bagaimana query dieksekusi — apakah menggunakan index, full scan, dan saran optimasi:

```php
$explanation = $users->explain(['status' => 'active', 'age' => ['$gte' => 21]]);

echo $explanation['query_plan']['uses_index'] ? 'Uses index' : 'Full scan';
echo "Scanned: {$explanation['performance']['documents_scanned']} documents";
echo "Time: {$explanation['performance']['execution_time_ms']}ms";

foreach ($explanation['suggestions'] as $suggestion) {
    echo "Suggestion: {$suggestion}";
}
```

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

// Format baru (direkomendasikan) — kontrol per-field
$users->setSearchableFields([
    'email' => ['hash' => true],     // HMAC-SHA256 blind index (aman)
    'username' => ['hash' => false], // Plain text (untuk field non-sensitif)
]);

// Format lama (masih didukung) — semua field mendapat perlakuan sama
$users->setSearchableFields(['email', 'phone'], true);

$users->saveConfiguration();
```

**Catatan teknis:** BangronDB menggunakan **AES-256-GCM**, key derivation berbasis PBKDF2 SHA-256, IV acak per enkripsi, dan payload Base64 di dokumen JSON. Key version (v1.2.0) memungkinkan key rotation tanpa re-encrypt manual.

### Key Rotation

```php
// Set key lama, lalu rotate ke key baru
$users->setEncryptionKey($oldKey, 'v1');
$rotated = $users->rotateEncryptionKey($newKey, 'v2'); // return jumlah dokumen

// Set key baru sebagai active
$users->setEncryptionKey($newKey, 'v2');
```

> Lihat contoh 21 (`21-key-rotation.php`) untuk demo lengkap termasuk `reencryptAll()`.

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

> Lihat [docs/schema-metadata-guide.md](docs/schema-metadata-guide.md) untuk panduan lengkap tentang properti metadata (`label`, `ui`, `relation`, dll.) yang tidak divalidasi otomatis.

## TTL (Time-To-Live)

Dokumen bisa di-set untuk auto-expire setelah waktu tertentu:

```php
// Aktifkan TTL pada field 'expires_at' dengan default 1 jam
$logs->enableTtl('expires_at', 3600);

// Tanpa default TTL (set manual per dokumen)
$logs->enableTtl('expires_at');

$logs->insert(['message' => 'temp log']); // otomatis dapat expires_at = now() + 3600

// Insert dengan expiry manual (override default)
$logs->insert(['message' => 'short', 'expires_at' => time() + 300]);

// Bersihkan dokumen yang sudah expired
$removed = $logs->cleanExpired();

// Statistik TTL
$stats = $logs->ttlStats();
```

> `cleanExpired()` harus dipanggil manual (misalnya via cron). BangronDB tidak membersihkan otomatis.

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
        return false; // reject deletion
    }
});
```

Event yang tersedia:

- `beforeInsert` / `afterInsert`
- `beforeUpdate` / `afterUpdate`
- `beforeRemove` / `afterRemove`

> Hook adalah **primitif** yang fleksibel. Bisa digunakan untuk ACL enforcement, audit logging, auto-timestamp, data transformation, dan lain-lain — semuanya di application layer, bukan di core library.

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

### Custom Config

Simpan metadata aplikasi per collection — persisten dan auto-load saat reconnect:

```php
// Simpan ACL, settings, atau metadata lain
$users->setCustomConfig('acl', [
    'admin'  => ['create', 'read', 'update', 'delete'],
    'editor' => ['create', 'read', 'update'],
    'viewer' => ['read'],
]);
$users->setCustomConfig('max_login_attempts', 3);
$users->saveConfiguration();

// Baca kapan saja
$acl = $users->getCustomConfig('acl');
$all = $users->getAllCustomConfig();
```

> Sensitive keys (`encryption_key`, `password`, `secret`, `token`, dll.) **ditolak** otomatis dan tidak bisa disimpan via `setCustomConfig`. Encryption key selalu supply dari `.env`, secret manager, atau runtime config.
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

| Fitur                                 | Tujuan                           |
| ------------------------------------- | -------------------------------- |
| Closure-only untuk `$where` / `$func` | Mencegah RCE                     |
| Validasi field name                   | Mencegah injection               |
| PRAGMA key escaping                   | Mencegah SQLite injection        |
| Regex hardening (ReDoS)               | Mengurangi risiko catastrophic backtracking |
| Validasi path                         | Mengurangi risiko path traversal |
| Sensitive config key blocking         | Mencegah credential leakage      |
| `strict_types=1`                      | Type safety                      |

Lihat juga [SECURITY_USAGE_GUIDE.md](SECURITY_USAGE_GUIDE.md).

## API Ringkas

### Client

| Method                                         | Keterangan                                                         |
| ---------------------------------------------- | ------------------------------------------------------------------ |
| `new Client($path, $options = [])`             | Membuat client                                                     |
| `createDB($name, $options = [])`               | Membuat database secara eksplisit                                  |
| `dbExists($name)`                              | Mengecek apakah database ada                                       |
| `listDBs()`                                    | Daftar database                                                    |
| `selectDB($name)`                              | Ambil database                                                     |
| `renameDB($oldName, $newName)`                 | Rename database                                                    |
| `dropDB($name)`                                | Hapus database                                                     |
| `createCollection($db, $collection)`           | Membuat collection langsung dari level client                      |
| `collectionExists($db, $collection)`           | Mengecek collection dari level client                              |
| `listCollections($db)` / `listCollection($db)` | Daftar nama collection di sebuah database (`[]` jika DB tidak ada) |
| `renameCollection($db, $oldName, $newName)`    | Rename collection dari level client                                |
| `dropCollection($db, $collection)`             | Hapus collection dari level client                                 |
| `selectCollection($db, $collection)`           | Ambil collection langsung                                          |
| `close()`                                      | Tutup koneksi                                                      |

### Database

| Method                                                    | Keterangan                     |
| --------------------------------------------------------- | ------------------------------ |
| `selectCollection($name)`                                 | Ambil collection               |
| `createCollection($name)`                                 | Buat collection                |
| `collectionExists($name)`                                 | Mengecek apakah collection ada |
| `renameCollection($oldName, $newName)`                    | Rename collection              |
| `dropCollection($name)`                                   | Hapus collection               |
| `getCollectionNames()`                                    | Daftar nama collection         |
| `createJsonIndex($collection, $field, $indexName = null)` | Buat index JSON                |
| `dropIndex($indexName)`                                   | Hapus index                    |
| `getHealthMetrics()`                                      | Ambil health metrics           |
| `getHealthReport()`                                       | Ambil health report            |
| `getPerformanceMetrics()`                                 | Ambil metrik performa          |
| `getCollectionMetrics()`                                  | Ambil metrik per collection    |
| `saveCollectionConfig($name, $config)`                    | Simpan konfigurasi             |
| `loadCollectionConfig($name)`                             | Muat konfigurasi               |
| `deleteCollectionConfig($name)`                           | Hapus konfigurasi              |
| `checkIntegrity()`                                        | Jalankan integrity check       |
| `vacuum()`                                                | Optimasi file database         |

### Collection

| Method                                                               | Keterangan                         |
| -------------------------------------------------------------------- | ---------------------------------- |
| `insert($document)`                                                  | Insert satu/banyak dokumen         |
| `insertMany($documents)`                                             | Insert batch dengan hasil detail   |
| `updateMany($criteria, $data, $options = [])`                        | Update batch dengan hasil detail   |
| `deleteMany($criteria)`                                              | Delete batch dengan hasil detail   |
| `find($criteria = null, $projection = null)`                         | Query dokumen                      |
| `findOne($criteria = null, $projection = null)`                      | Query satu dokumen                 |
| `update($criteria, $data, $merge = true)`                            | Update dokumen                     |
| `remove($criteria)`                                                  | Hapus dokumen                      |
| `count($criteria = null)`                                            | Hitung dokumen                     |
| `save($document)`                                                    | Insert / upsert dokumen            |
| `aggregate($pipeline)`                                               | Aggregation pipeline               |
| `explain($criteria = null)`                                          | Query plan analysis                |
| `stream($criteria = null, $options = [])`                            | Cursor streaming (Generator)       |
| `drop()`                                                             | Hapus collection                   |
| `renameCollection($newName)`                                         | Rename collection                  |
| `setIdModeAuto()` / `setIdModeManual()` / `setIdModePrefix($prefix)` | Atur mode ID                       |
| `setEncryptionKey($key, $version = null)`                            | Atur key enkripsi + versi          |
| `rotateEncryptionKey($newKey, $newVersion = null)`                   | Rotasi key enkripsi                |
| `reencryptAll()`                                                     | Re-encrypt semua dokumen (bump versi) |
| `setSearchableFields($fields, $hash = false)`                        | Atur searchable fields             |
| `removeSearchableField($field, $dropColumn = false)`                 | Hapus searchable field             |
| `rehashSearchableField($field)`                                     | Rehash blind index (migration)     |
| `setSchema($schema)`                                                 | Atur schema                        |
| `validate($document)`                                                | Validasi dokumen manual            |
| `enableTtl($field, $seconds = null)`                                    | Aktifkan TTL auto-expiration     |
| `disableTtl()`                                                       | Nonaktifkan TTL                 |
| `cleanExpired()`                                                     | Hapus dokumen expired            |
| `expiredCount()`                                                     | Hitung dokumen expired           |
| `ttlStats()`                                                         | Status TTL collection            |
| `useSoftDeletes($enabled = true)`                                    | Aktifkan soft delete               |
| `setDeletedAtField($field)`                                         | Custom field name (default: deleted_at) |
| `restore($criteria)`                                                 | Restore dokumen terhapus           |
| `forceDelete($criteria)`                                             | Hapus permanen                     |
| `on($event, $callback)`                                              | Register hook                      |
| `off($event, $callback = null)`                                      | Hapus hook                         |
| `createIndex($field, $indexName = null)`                             | Buat index                         |
| `getLastModified()`                                                  | Ambil metadata perubahan           |
| `notifyChange()`                                                     | Trigger manual change notification |
| `saveConfiguration()`                                                | Simpan konfigurasi collection      |
| `setCustomConfig($key, $value)`                                      | Simpan custom config               |
| `getCustomConfig($key, $default = null)`                             | Baca custom config                 |
| `getAllCustomConfig()`                                               | Baca semua custom config           |

### Cursor

| Method                                         | Keterangan                      |
| ---------------------------------------------- | ------------------------------- |
| `limit($n)`                                    | Batas hasil                     |
| `skip($n)`                                     | Lewati hasil awal               |
| `sort($fields)`                                | Urutkan hasil                   |
| `populate($field, $collection, $options = [])` | Populate relasi                 |
| `withTrashed()`                                | Sertakan soft-deleted           |
| `onlyTrashed()`                                | Hanya soft-deleted              |
| `toArray()`                                    | Materialisasi ke array          |
| `toArraySafe($maxResults = null)`              | Materialisasi dengan batas aman |
| `each($callback)`                              | Iterasi tiap dokumen            |

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

| No | File | Topik |
|----|------|-------|
| 01 | `01-quick-start-crud.php` | Quick start CRUD |
| 02 | `02-query-operators.php` | Query operators |
| 03 | `03-encryption-searchable.php` | Enkripsi & searchable fields |
| 04 | `04-schema-validation.php` | Schema validation |
| 05 | `05-bulk-operations.php` | Bulk insert/update/delete |
| 06 | `06-aggregation-pipeline.php` | Aggregation pipeline |
| 07 | `07-cursor-streaming.php` | Cursor streaming (Generator) |
| 08 | `08-ttl-expiration.php` | TTL auto-expiration |
| 09 | `09-explain-query.php` | Explain query plan |
| 10 | `10-soft-deletes.php` | Soft delete & restore |
| 11 | `11-hooks.php` | Hooks lifecycle |
| 12 | `12-relationships-populate.php` | Relasi & populate |
| 13 | `13-transactions.php` | Transaksi |
| 14 | `14-indexing-health-monitoring.php` | Indexing & health monitoring |
| 15 | `15-dynamic-configuration.php` | Konfigurasi dinamis |
| 16 | `16-multiple-databases.php` | Multiple databases |
| 17 | `17-id-modes-collection-management.php` | ID modes & collection management |
| 18 | `18-security-features.php` | Fitur keamanan |
| 19 | `19-ecommerce-app.php` | Aplikasi e-commerce lengkap |
| 20 | `20-auth-encrypted.php` | Auth dengan enkripsi |
| 21 | `21-key-rotation.php` | Key rotation |
| 22 | `22-rbac-users-roles-permissions.php` | RBAC (pola aplikasi) |
| 23 | `23-acl-relation-type.php` | ACL dengan relation type |
| 24 | `24-dynamic-acl-per-collection.php` | Dynamic ACL per collection |

## Dokumentasi

| Dokumen | Deskripsi |
|---------|-----------|
| [Getting Started](docs/getting-started.md) | Panduan cepat instalasi dan penggunaan dasar |
| [Fitur Lanjutan](docs/features.md) | Hooks, soft delete, TTL, enkripsi, aggregation, dan lainnya |
| [Query Operators](docs/query-operators.md) | Daftar lengkap operator query yang didukung |
| [Schema & Metadata Guide](docs/schema-metadata-guide.md) | Panduan properti schema, validasi aktif, dan metadata |
| [Hook Patterns](docs/hook-patterns.md) | 8 pola penggunaan hook dalam aplikasi nyata |
| [Framework Integration](docs/framework-integration.md) | Integrasi dengan Laravel, Lumen, Slim, Flight, CodeIgniter 4, Symfony, dan lainnya |
| [API Reference](docs/api-reference.md) | Referensi API lengkap |
| [Security](docs/security.md) | Keamanan, enkripsi, dan best practices |
| [Roadmap](docs/roadmap.md) | Fitur yang sudah dan akan diimplementasikan |

## Catatan Kompatibilitas

Jika Anda bermigrasi dari perilaku lama yang mengandalkan create implicit saat `selectDB()` / `selectCollection()`, lihat:

- [BACKWARD_COMPATIBILITY_NOTES.md](BACKWARD_COMPATIBILITY_NOTES.md)

## Kontribusi

Lihat [CONTRIBUTING.md](CONTRIBUTING.md).

## Lisensi

BangronDB dilisensikan dengan [MIT](LICENSE).