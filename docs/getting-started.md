# Getting Started

Panduan cepat untuk mulai menggunakan BangronDB — Embedded Document Database untuk PHP yang ringan dan aman, dibangun di atas SQLite.

## Instalasi

```bash
composer require bangrondb/bangrondb
```

> **Requirement:** PHP 8.1+ dengan ekstensi PDO dan SQLite3.

## Hello World

```php
<?php
require_once 'vendor/autoload.php';

use BangronDB\Client;

// 1. Buat client (database disimpan di direktori ini)
$client = new Client(__DIR__ . '/data');

// 2. Buat database
$db = $client->createDB('myapp');

// 3. Buat collection
$users = $db->createCollection('users');

// 4. Insert dokumen
$id = $users->insert([
    'name'  => 'John Doe',
    'email' => 'john@example.com',
    'age'   => 30,
]);
echo "Inserted ID: {$id}\n";

// 5. Baca dokumen
$user = $users->findOne(['name' => 'John Doe']);
echo "Name: {$user['name']}, Age: {$user['age']}\n";

// 6. Update
$users->update(['name' => 'John Doe'], ['$set' => ['age' => 31]]);

// 7. Hapus
$users->remove(['name' => 'John Doe']);

$client->close();
```

## Konsep Dasar

### Hierarki: Client → Database → Collection → Document

```
Client (koneksi ke direktori/data)
  └── Database (file .bangron / :memory:)
        └── Collection (tabel SQLite + JSON document)
              └── Document (array asosiatif PHP)
```

- **Client** — Titik masuk utama. Mengelola koneksi ke direktori database.
- **Database** — Satu file `.bangron` (atau `:memory:` untuk in-memory). Mengandung beberapa collection.
- **Collection** — Seperti tabel di database relasional, tapi menyimpan dokumen JSON.
- **Document** — Satu record berupa array asosiatif PHP.

### In-Memory Database

Untuk testing atau data sementara, gunakan `:memory:`:

```php
$client = new Client(':memory:');
$db = $client->createDB('test');
$items = $db->createCollection('items');
// Data hilang saat script selesai
```

### Magic Property Access

Akses singkat via property:

```php
// Equivalen:
$db = $client->createDB('myapp');
$db = $client->myapp;

// Equivalen:
$users = $db->createCollection('users');
$users = $db->users;        // createCollection jika belum ada, selectCollection jika sudah ada
```

## CRUD Lengkap

### Insert

```php
// Single insert — return ID (string)
// Catatan: return false jika beforeInsert hook melempar false (veto)
$id = $collection->insert(['name' => 'Alice', 'age' => 25]);

// Batch insert — return jumlah dokumen (int)
// Catatan: return false jika salah satu dokumen di-veto oleh hook (transaksi di-rollback)
$count = $collection->insert([
    ['name' => 'Bob', 'age' => 30],
    ['name' => 'Charlie', 'age' => 35],
]);

// insertMany — return array dengan detail
$result = $collection->insertMany([
    ['name' => 'Dave', 'age' => 28],
    ['name' => 'Eve', 'age' => 32],
]);
// $result = ['inserted_count' => 2, 'inserted_ids' => ['...', '...']]

// Upsert (insert jika belum ada, update jika sudah)
$collection->save(['_id' => 'custom-id', 'name' => 'Frank']);
```

### Read

```php
// Find one document
$doc = $collection->findOne(['email' => 'alice@example.com']);

// Find many → Cursor
$cursor = $collection->find(['status' => 'active']);

// Chain: sort, skip, limit, projection
$results = $collection->find(['age' => ['$gte' => 25]])
    ->sort(['age' => -1])       // DESC
    ->skip(10)
    ->limit(5)
    ->toArray();

// Projection (pilih field)
$users = $collection->find([], ['name' => 1, 'email' => 1])->toArray();

// Projection (exclude field)
$users = $collection->find([], ['password' => 0])->toArray();

// Count
$total = $collection->count();
$active = $collection->count(['status' => 'active']);
```

### Update

```php
// Merge update (default — hanya field yang disebut yang diubah)
$collection->update(
    ['name' => 'Alice'],
    ['age' => 26, 'city' => 'Jakarta']
);

// $set / $unset (MongoDB-style)
$collection->update(
    ['name' => 'Alice'],
    ['$set' => ['status' => 'active'], '$unset' => ['temp_field' => '']]
);

// $inc (atomic increment)
$collection->update(
    ['name' => 'Alice'],
    ['$inc' => ['login_count' => 1, 'points' => 10]]
);

// Replace (non-merge — seluruh dokumen diganti)
$collection->update(
    ['_id' => 'abc'],
    ['name' => 'New Name', 'age' => 99],
    false  // merge = false
);

// updateMany — return array dengan detail
$result = $collection->updateMany(
    ['status' => 'pending'],
    ['$set' => ['status' => 'processed']]
);
// $result = ['matched_count' => 5, 'modified_count' => 5]
```

### Delete

```php
// Soft delete (jika diaktifkan) atau hard delete
$removed = $collection->remove(['status' => 'spam']);

// deleteMany — return array dengan detail
$result = $collection->deleteMany(['status' => 'expired']);
// $result = ['deleted_count' => 3]

// Force hard delete (abaikan soft delete)
$collection->forceDelete(['_id' => 'abc']);
```

## Cursor API

`find()` mengembalikan `Cursor` yang mendukung chaining:

```php
$cursor = $collection->find(['status' => 'active'])
    ->sort(['created_at' => -1])
    ->skip(20)
    ->limit(10);

// Konversi ke array
$docs = $cursor->toArray();

// Hitung tanpa memuat data
$total = $cursor->count();

// Iterasi dengan each
$cursor->each(function ($doc) {
    echo $doc['name'] . "\n";
});

// Populate relasi
$cursor->populate('user_id', $usersCollection, ['as' => 'user_name']);
```

## Query Operators

Operator query yang didukung di dalam `find()`, `findOne()`, `count()`, dll:

### Perbandingan

| Operator | Deskripsi | Contoh |
|----------|-----------|--------|
| `$eq` | Sama dengan | `['age' => ['$eq' => 25]]` |
| `$ne` | Tidak sama dengan | `['status' => ['$ne' => 'banned']]` |
| `$gt` | Lebih besar | `['age' => ['$gt' => 18]]` |
| `$gte` | Lebih besar atau sama | `['age' => ['$gte' => 18]]` |
| `$lt` | Lebih kecil | `['price' => ['$lt' => 100]]` |
| `$lte` | Lebih kecil atau sama | `['price' => ['$lte' => 100]]` |
| `$in` | Di dalam array | `['status' => ['$in' => ['active', 'pending']]]` |
| `$nin` | Tidak di dalam array | `['role' => ['$nin' => ['banned', 'suspended']]]` |

### Logika

| Operator | Deskripsi | Contoh |
|----------|-----------|--------|
| `$and` | Semua kondisi benar | `['$and' => [['age' => ['$gte' => 18]], ['status' => 'active']]]` |
| `$or` | Salah satu kondisi benar | `['$or' => [['role' => 'admin'], ['role' => 'moderator']]]` |
| `$not` | Kecocokan regex negasi | `['name' => ['$not' => '/^test/']]` |

### Array

| Operator | Deskripsi | Contoh |
|----------|-----------|--------|
| `$has` | Array mengandung nilai | `['tags' => ['$has' => 'php']]` |
| `$all` | Array mengandung semua nilai | `['tags' => ['$all' => ['php', 'laravel']]]` |
| `$size` | Ukuran array sama dengan | `['items' => ['$size' => 3]]` |

### Lainnya

| Operator | Deskripsi | Contoh |
|----------|-----------|--------|
| `$regex` / `$preg` | Regex match | `['email' => ['$regex' => '/^admin@/']]` |
| `$fuzzy` / `$text` | Pencarian fuzzy | `['name' => ['$fuzzy' => 'Jhn Doe']]` |
| `$func` / `$fn` | Custom function | `['age' => ['$fn' => fn($v) => $v > 18]]` |
| `$exists` | Field ada/tidak | `['phone' => ['$exists' => true]]` |
| `$mod` | Modulo | `['id' => ['$mod' => [10, 0]]]` |
| `$where` | Custom criteria | `['$where' => fn($doc) => $doc['age'] > $doc['min_age']]` |
| `$options` | Placeholder regex | `['name' => ['$regex' => '/john/i', '$options' => 'iu']]` |

> **Catatan:** `$options` disediakan untuk kompatibilitas sintaks MongoDB, tetapi **saat ini tidak berpengaruh** pada eksekusi. Sertakan flag regex langsung di dalam pola (misalnya `/john/iu`) untuk mengaktifkan case-insensitive dan Unicode.

> Dokumentasi lengkap query operators ada di [query-operators.md](query-operators.md).

## Update Operators

| Operator | Deskripsi | Contoh |
|----------|-----------|--------|
| `$set` | Set nilai field | `['$set' => ['name' => 'New']]` |
| `$unset` | Hapus field | `['$unset' => ['temp' => '']]` |
| `$inc` | Increment atomik | `['$inc' => ['count' => 1]]` |

## Langkah Selanjutnya

- [API Reference lengkap](api-reference.md)
- [Fitur lanjutan](features.md) — Encryption, Hooks, Soft Delete, TTL, Aggregation
- [Keamanan](security.md) — Enkripsi, Searchable Fields, Key Rotation
- [Contoh lengkap](../examples/) — Semua contoh bisa dijalankan langsung