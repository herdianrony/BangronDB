# Collection

Kelas utama untuk mengelola koleksi dokumen dalam database BangronDB. Menggabungkan berbagai trait untuk menyediakan fungsionalitas lengkap seperti enkripsi, hooks, validasi schema, pencarian, dan soft deletes.

## Traits Yang Digunakan

- `EncryptionTrait` - Enkripsi dokumen
- `HooksTrait` - Event hooks
- `SearchableFieldsTrait` - Field pencarian
- `IdGeneratorTrait` - Generasi ID otomatis
- `QueryBuilderTrait` - Pembangunan query SQL
- `SchemaValidationTrait` - Validasi schema
- `SoftDeleteTrait` - Soft deletes

## Konstanta

### Mode ID Generation

- `ID_MODE_AUTO` = 'auto' - Generate UUID v4 secara otomatis
- `ID_MODE_MANUAL` = 'manual' - Gunakan \_id yang disediakan
- `ID_MODE_PREFIX` = 'prefix' - Generate dengan prefix

### Event Hooks

- `HOOK_BEFORE_INSERT` = 'beforeInsert'
- `HOOK_AFTER_INSERT` = 'afterInsert'
- `HOOK_BEFORE_UPDATE` = 'beforeUpdate'
- `HOOK_AFTER_UPDATE` = 'afterUpdate'
- `HOOK_BEFORE_REMOVE` = 'beforeRemove'
- `HOOK_AFTER_REMOVE` = 'afterRemove'

## Properti

### `$database`

- **Tipe**: `Database`
- **Deskripsi**: Referensi ke database induk

### `$name`

- **Tipe**: `string`
- **Deskripsi**: Nama koleksi

## Metode Utama

### `__construct(string $name, Database $database)`

Konstruktor untuk membuat instance Collection baru.

**Parameter:**

- `$name` (string): Nama koleksi
- `$database` (Database): Instance database

### `drop()`

Menghapus koleksi dari database.

### `insert(array $document = []): mixed`

Menyisipkan dokumen baru. Mendukung array tunggal atau multiple dokumen.

**Parameter:**

- `$document` (array): Dokumen atau array dokumen

**Return:** ID insert untuk dokumen tunggal, atau jumlah dokumen untuk array

**Contoh:**

```php
// Insert satu dokumen
$id = $collection->insert(['name' => 'John', 'age' => 30]);

// Insert multiple dokumen
$count = $collection->insert([
    ['name' => 'John', 'age' => 30],
    ['name' => 'Jane', 'age' => 25]
]);
```

### `save(array $document, bool $create = false): mixed`

Menyimpan dokumen. Jika memiliki \_id, akan update; jika tidak, akan insert.

**Parameter:**

- `$document` (array): Dokumen untuk disimpan
- `$create` (bool): Flag untuk force create

**Return:** ID dokumen

### `update($criteria, array $data, bool $merge = true): int`

Mengupdate dokumen yang cocok dengan kriteria.

**Parameter:**

- `$criteria` (mixed): Kriteria pencarian
- `$data` (array): Data update
- `$merge` (bool): Merge atau replace

**Return:** Jumlah dokumen yang diupdate

**Contoh:**

```php
// Update sederhana
$collection->update(['name' => 'John'], ['age' => 31]);

// Update dengan operator
$collection->update(
    ['age' => ['$lt' => 30]],
    ['$set' => ['status' => 'young']]
);
```

### `remove($criteria): int`

Menghapus dokumen yang cocok dengan kriteria. Jika soft deletes aktif, akan menandai sebagai deleted.

**Parameter:**

- `$criteria` (mixed): Kriteria pencarian

**Return:** Jumlah dokumen yang dihapus

### `find($criteria = null, $projection = null): Cursor`

Mencari dokumen dengan kriteria opsional.

**Parameter:**

- `$criteria` (mixed): Kriteria pencarian
- `$projection` (array): Field yang akan dikembalikan

**Return:** Instance `Cursor`

**Contoh:**

```php
// Find semua
$cursor = $collection->find();

// Find dengan kriteria
$cursor = $collection->find(['age' => ['$gte' => 18]]);

// Find dengan projection
$cursor = $collection->find(['name' => 'John'], ['name' => 1, 'age' => 1]);
```

### `findOne($criteria = null, $projection = null): ?array`

Mencari satu dokumen yang cocok.

**Parameter:**

- `$criteria` (mixed): Kriteria pencarian
- `$projection` (array): Field yang akan dikembalikan

**Return:** Array dokumen atau null

### `count($criteria = null): int`

Menghitung jumlah dokumen yang cocok dengan kriteria.

**Parameter:**

- `$criteria` (mixed): Kriteria pencarian

**Return:** Jumlah dokumen

### `populate(array $documents, string $localField, string $foreign, string $foreignField = '_id', ?string $as = null): mixed`

Mengisi referensi dengan data dari koleksi lain.

**Parameter:**

- `$documents` (array): Dokumen yang akan di-populate
- `$localField` (string): Field lokal yang berisi referensi
- `$foreign` (string): Koleksi foreign atau 'db.collection'
- `$foreignField` (string): Field di koleksi foreign
- `$as` (string): Nama field hasil populate

**Return:** Dokumen yang telah di-populate

## Metode Lanjutan

### `forceDelete($criteria): int`

Menghapus permanen dokumen (melewati soft delete).

### `renameCollection($newname): bool`

Mengganti nama koleksi.

**Parameter:**

- `$newname` (string): Nama baru

**Return:** True jika berhasil

### `createIndex(string $field, ?string $indexName = null): void`

Membuat index JSON untuk field tertentu.

**Parameter:**

- `$field` (string): Field untuk di-index
- `$indexName` (string): Nama index opsional

## Metode Konfigurasi

### `saveConfiguration(): void`

Menyimpan konfigurasi koleksi ke database.

### `notifyChange(): void`

Memberitahu bahwa koleksi telah berubah (untuk tracking versi).

### `getLastModified(): array`

Mendapatkan versi dan waktu modifikasi terakhir koleksi.

**Return:** Array dengan 'version' dan 'last_updated'

## Operator Query

Collection mendukung berbagai operator MongoDB-like untuk query:

### Comparison Operators

- `$eq` - Equal
- `$ne` - Not equal
- `$gt` - Greater than
- `$gte` - Greater than or equal
- `$lt` - Less than
- `$lte` - Less than or equal

### Logical Operators

- `$and` - Logical AND
- `$or` - Logical OR
- `$not` - Logical NOT

### Element Operators

- `$exists` - Field existence check
- `$in` - Value in array
- `$nin` - Value not in array

### Evaluation Operators

- `$regex` - Regular expression match
- `$mod` - Modulo operation
- `$func` - Custom function evaluation

### Array Operators

- `$all` - Match all elements
- `$size` - Array size check
- `$has` - Array contains value

## Contoh Penggunaan Lengkap

```php
use BangronDB\Client;

$client = new Client('/path/to/db');
$users = $client->selectCollection('myapp', 'users');

// Insert dokumen
$userId = $users->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
]);

// Cari dokumen
$user = $users->findOne(['_id' => $userId]);
$adults = $users->find(['age' => ['$gte' => 18]])->toArray();

// Update dokumen
$users->update(
    ['email' => 'john@example.com'],
    ['$set' => ['age' => 31]]
);

// Hapus dokumen
$users->remove(['age' => ['$lt' => 18]]);

// Hitung dokumen
$totalUsers = $users->count();
$adultCount = $users->count(['age' => ['$gte' => 18]]);
```
