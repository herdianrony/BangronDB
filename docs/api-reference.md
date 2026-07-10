# API Reference

Referensi lengkap semua class dan method publik BangronDB.

---

## Client

Titik masuk utama untuk berinteraksi dengan BangronDB.

```php
use BangronDB\Client;

$client = new Client(string $path, array $options = []);
```

**Parameter `$path`:** Path direktori tempat database disimpan, atau `:memory:` untuk database di memori.

**Parameter `$options` (array):**
| Key | Tipe | Deskripsi |
|-----|------|-----------|
| `encryption_key` | `string` | Encryption key default untuk semua collection |
| `encryption_key_version` | `string` | Versi encryption key |
| `base_path` | `string` | Base path override untuk validasi direktori |

### Method

| Method | Return | Deskripsi |
|--------|--------|-----------|
| `createDB(string $name, array $options = [])` | `Database` | Buat database baru (atau return existing jika sudah ada di cache) |
| `selectDB(string $name, array $options = [])` | `Database` | Pilih database yang sudah ada. **Throw `DatabaseException` jika tidak ada.** |
| `dbExists(string $name)` | `bool` | Cek apakah database ada |
| `dropDB(string $name)` | `bool` | Hapus database |
| `renameDB(string $oldName, string $newName)` | `bool` | Rename database |
| `listDBs()` | `array` | Daftar nama semua database |
| `createCollection(string $database, string $collection)` | `Collection` | Buat collection (shortcut: createDB + createCollection) |
| `selectCollection(string $database, string $collection)` | `Collection` | Pilih collection yang sudah ada |
| `collectionExists(string $database, string $collection)` | `bool` | Cek apakah collection ada |
| `listCollections(string $database)` | `array` | Daftar nama collection |
| `renameCollection(string $database, string $oldName, string $newName)` | `bool` | Rename collection |
| `dropCollection(string $database, string $collection)` | `bool` | Hapus collection |
| `close()` | `void` | Tutup semua koneksi database |

**Magic property:** `$client->mydb` equivalen dengan `$client->createDB('mydb')`.

---

## Database

Mewakili satu file database (`.bangron`) atau database in-memory.

```php
$db = $client->createDB('myapp');
```

### Konstanta

| Konstanta | Nilai | Deskripsi |
|-----------|-------|-----------|
| `Database::DSN_PATH_MEMORY` | `':memory:'` | Untuk database in-memory |

### Property Publik

| Property | Tipe | Deskripsi |
|----------|------|-----------|
| `$path` | `string` | Path database |
| `$connection` | `PDO` | Koneksi PDO SQLite (akses langsung jika perlu) |
| `$client` | `?Client` | Reference ke parent Client |

### Method — Collection Management

| Method | Return | Deskripsi |
|--------|--------|-----------|
| `createCollection(string $name)` | `Collection` | Buat collection baru |
| `selectCollection(string $name)` | `Collection` | Pilih collection yang sudah ada |
| `collectionExists(string $name)` | `bool` | Cek apakah collection ada |
| `dropCollection(string $name)` | `void` | Hapus collection |
| `renameCollection(string $oldName, string $newName)` | `bool` | Rename collection |
| `getCollectionNames()` | `array` | Daftar nama semua collection |
| `listCollections()` | `array` | Daftar semua object Collection |

**Magic property:** `$db->users` equivalen dengan `$db->selectCollection('users')` atau `$db->createCollection('users')`.

### Method — Index

| Method | Return | Deskripsi |
|--------|--------|-----------|
| `createJsonIndex(string $collection, string $field, ?string $indexName = null)` | `void` | Buat JSON index pada field |
| `dropIndex(string $indexName)` | `void` | Hapus index |

### Method — Encryption

| Method | Return | Deskripsi |
|--------|--------|-----------|
| `setEncryptionKey(?string $key, ?string $keyVersion = null)` | `self` | Set encryption key |
| `getEncryptionKey()` | `?string` | Ambil encryption key |
| `getEncryptionKeyVersion()` | `?string` | Ambil versi key |
| `isEncryptionEnabled()` | `bool` | Cek apakah enkripsi aktif |
| `getEncryptionKeyStatus()` | `array` | Status lengkap encryption |

### Method — Metrics & Health

| Method | Return | Deskripsi |
|--------|--------|-----------|
| `getHealthReport()` | `array` | Laporan kesehatan lengkap |
| `getHealthMetrics()` | `array` | Metrik kesehatan |
| `checkIntegrity()` | `array` | Cek integritas SQLite |
| `getDataMetrics()` | `array` | Metrik data |
| `getPerformanceMetrics()` | `array` | Metrik performa |
| `getIndexMetrics()` | `array` | Metrik index |
| `getCollectionMetrics()` | `array` | Metrik per collection |
| `securityAudit()` | `array` | Audit keamanan |

### Method — Lainnya

| Method | Return | Deskripsi |
|--------|--------|-----------|
| `vacuum()` | `void` | Vacuum database (reclaim space) |
| `drop()` | `void` | Hapus file database (bukan `:memory:`) |
| `close()` | `void` | Tutup koneksi |

---

## Collection

Inti operasi CRUD dan query. Setiap collection menyimpan dokumen dalam format JSON di tabel SQLite.

```php
$collection = $db->createCollection('users');
```

### Konstanta

**ID Mode:**
| Konstanta | Nilai | Deskripsi |
|-----------|-------|-----------|
| `Collection::ID_MODE_AUTO` | `'auto'` | UUID v4 otomatis (default) |
| `Collection::ID_MODE_MANUAL` | `'manual'` | User menentukan `_id` sendiri |
| `Collection::ID_MODE_PREFIX` | `'prefix'` | ID dengan prefix + UUID |

**Hook Events:**
| Konstanta | Nilai |
|-----------|-------|
| `Collection::HOOK_BEFORE_INSERT` | `'beforeInsert'` |
| `Collection::HOOK_AFTER_INSERT` | `'afterInsert'` |
| `Collection::HOOK_BEFORE_UPDATE` | `'beforeUpdate'` |
| `Collection::HOOK_AFTER_UPDATE` | `'afterUpdate'` |
| `Collection::HOOK_BEFORE_REMOVE` | `'beforeRemove'` |
| `Collection::HOOK_AFTER_REMOVE` | `'afterRemove'` |

### Method — CRUD

| Method | Return | Deskripsi |
|--------|--------|-----------|
| `insert(array $document = [])` | `mixed` | Insert satu atau batch dokumen. Single → return ID (string). Batch (array dengan key 0) → return count (int). |
| `save(array $document, bool $create = false)` | `mixed` | Upsert: update jika `_id` ada, insert jika belum |
| `update(mixed $criteria, array $data, bool $merge = true)` | `int` | Update dokumen yang cocok. `$merge = true` (default) merge field, `false` = replace seluruh dokumen. |
| `remove(mixed $criteria)` | `int` | Hapus dokumen yang cocok (hormati soft delete) |
| `forceDelete(mixed $criteria)` | `int` | Hapus permanen (abaikan soft delete) |

### Method — Bulk Operations

| Method | Return | Deskripsi |
|--------|--------|-----------|
| `insertMany(array $documents)` | `array` | Insert banyak dokumen dalam transaksi. Return: `['inserted_count' => int, 'inserted_ids' => string[]]`. Hook rejection → rollback semua. |
| `updateMany(mixed $criteria, array $data, array $options = [])` | `array` | Update banyak dokumen (single-pass). Return: `['matched_count' => int, 'modified_count' => int]` |
| `deleteMany(mixed $criteria)` | `array` | Hapus banyak dokumen. Return: `['deleted_count' => int]` |

### Method — Query

| Method | Return | Deskripsi |
|--------|--------|-----------|
| `find(mixed $criteria = null, ?array $projection = null)` | `Cursor` | Cari dokumen, return Cursor untuk chaining |
| `findOne(mixed $criteria = null, ?array $projection = null)` | `?array` | Cari satu dokumen |
| `count(mixed $criteria = null)` | `int` | Hitung dokumen |

### Method — Aggregation Pipeline

| Method | Return | Deskripsi |
|--------|--------|-----------|
| `aggregate(array $pipeline)` | `array` | Eksekusi aggregation pipeline. Operators: `$match`, `$group`, `$sort`, `$limit`, `$skip`, `$project`, `$count`, `$unset` |

Lihat [features.md](features.md#aggregation-pipeline) untuk detail lengkap.

### Method — Explain & Stream

| Method | Return | Deskripsi |
|--------|--------|-----------|
| `explain(mixed $criteria = null)` | `array` | Analisis rencana eksekusi query (index usage, scan ratio, suggestions) |
| `stream(mixed $criteria = null, array $options = [])` | `Generator` | Stream dokumen via PHP generator (memory efficient). Options: `sort`, `skip`, `limit`, `projection`. |

### Method — Index

| Method | Return | Deskripsi |
|--------|--------|-----------|
| `createIndex(string $field, ?string $indexName = null)` | `void` | Buat JSON index pada field |

### Method — Populate

| Method | Return | Deskripsi |
|--------|--------|-----------|
| `populate(array $documents, string $localField, string $foreign, string $foreignField = '_id', ?string $as = null)` | `mixed` | Populate relasi antar collection |

### Method — Lainnya

| Method | Return | Deskripsi |
|--------|--------|-----------|
| `drop()` | `void` | Hapus collection ini |
| `renameCollection(string $newName)` | `bool` | Rename collection |
| `securityAudit()` | `array` | Audit keamanan collection |
| `invalidateCollectionCache()` | `void` | Reset cache (setelah rename/drop) |

---

## Cursor

Dikembalikan oleh `find()`. Mendukung method chaining dan iterable.

```php
$results = $collection->find(['status' => 'active'])
    ->sort(['created_at' => -1])
    ->skip(10)
    ->limit(5)
    ->toArray();
```

### Method

| Method | Return | Deskripsi |
|--------|--------|-----------|
| `count()` | `int` | Hitung dokumen yang cocok |
| `sort(array $sort)` | `self` | Sort: `['field' => 1]` ASC, `['field' => -1]` DESC |
| `skip(int $n)` | `self` | Lewati N dokumen pertama |
| `limit(int $n)` | `self` | Batasi hasil N dokumen |
| `toArray()` | `array` | Konversi ke array (apply projection) |
| `toArraySafe(?int $maxResults = null)` | `array` | Konversi ke array dengan batas memori |
| `each(callable $fn)` | `self` | Iterasi setiap dokumen |
| `populate(string $path, Collection $collection, array $options = [])` | `self` | Tambah rule populate |
| `populateMany(array $defs)` | `self` | Tambah beberapa populate rules |
| `with(string|array $path, ?Collection $collection = null, array $options = [])` | `self` | Alternatif populate API |
| `withTrashed()` | `self` | Include dokumen soft-deleted |
| `onlyTrashed()` | `self` | Hanya dokumen soft-deleted |
| `getIterator()` | `Traversable` | Untuk `foreach` |

---

## Config

Konfigurasi global (static).

```php
use BangronDB\Config;

Config::set('default_path', __DIR__ . '/data');
$path = Config::get('default_path');
```

| Method | Return | Deskripsi |
|--------|--------|-----------|
| `set(string $key, $value)` | `void` | Set konfigurasi |
| `get(string $key, $default = null)` | `mixed` | Ambil konfigurasi |
| `has(string $key)` | `bool` | Cek key ada |
| `all()` | `array` | Semua konfigurasi |
| `reset()` | `void` | Reset ke default |

---

## Factory

Cara cepat membuat komponen BangronDB.

```php
use BangronDB\Factory;

$client = Factory::createClient('/path/to/data');
$db = Factory::createDatabase('/path/to/data', 'myapp');
$collection = Factory::createCollection('/path/to/data', 'myapp', 'users');
```

---

## Exceptions

Semua exception BangronDB mewarisi `BangronDBException`, **kecuali** `QueryExecutionException`:

| Exception | Parent | Deskripsi |
|-----------|--------|-----------|
| `BangronDBException` | `Exception` | Base exception (dengan `errorCode`, `context`, `toJson()`) |
| `DatabaseException` | `BangronDBException` | Operasi database gagal |
| `CollectionException` | `BangronDBException` | Operasi collection gagal |
| `ValidationException` | `BangronDBException` | Validasi data gagal |
| `QueryExecutionException` | `RuntimeException` | Eksekusi query gagal (menyediakan `getSql()`, `getParams()`, `getRedactedParams()`) |

```php
use BangronDB\Exceptions\DatabaseException;

try {
    $db = $client->selectDB('nonexistent');
} catch (DatabaseException $e) {
    echo $e->getMessage();       // Pesan error
    echo $e->getErrorCode();     // Kode error machine-readable
    print_r($e->getContext());   // Konteks tambahan
    echo $e->toJson();           // Format JSON
}
```