# Cursor

Kelas untuk mengiterasi hasil query dari operasi find(). Mengimplementasi IteratorAggregate untuk kompatibilitas dengan loop foreach.

## Interface

Mengimplementasi `\IteratorAggregate` untuk mendukung iterasi.

## Properti

### `$collection`

- **Tipe**: `Collection`
- **Deskripsi**: Referensi ke koleksi asal

### `$criteria`

- **Tipe**: `mixed`
- **Deskripsi**: Kriteria query yang digunakan

## Query Modifiers

### `limit(int $limit): self`

Mengatur jumlah maksimum dokumen yang dikembalikan.

**Parameter:**

- `$limit` (int): Jumlah maksimum dokumen

**Return:** Instance Cursor untuk chaining

### `skip(int $skip): self`

Mengatur jumlah dokumen yang akan dilewati.

**Parameter:**

- `$skip` (int): Jumlah dokumen untuk dilewati

**Return:** Instance Cursor untuk chaining

### `sort($sort): self`

Mengatur urutan hasil query.

**Parameter:**

- `$sort` (array): Array field => direction (1 untuk ASC, -1 untuk DESC)

**Return:** Instance Cursor untuk chaining

**Contoh:**

```php
$cursor = $collection->find()
    ->sort(['name' => 1, 'age' => -1])
    ->limit(10)
    ->skip(20);
```

## Populate API

### `populate(string $path, Collection $collection, array $options = []): self`

Menambahkan rule populate untuk relationship tunggal.

**Parameter:**

- `$path` (string): Path field yang akan di-populate
- `$collection` (Collection): Koleksi target
- `$options` (array): Opsi populate

**Return:** Instance Cursor untuk chaining

### `populateMany(array $defs): self`

Menambahkan multiple populate rules sekaligus.

**Parameter:**

- `$defs` (array): Array definisi populate

**Return:** Instance Cursor untuk chaining

### `with(string|array $path, ?Collection $collection = null, array $options = []): self`

API populate alternatif dengan interface fluent.

**Parameter:**

- `$path` (string|array): Path atau array definisi
- `$collection` (Collection): Koleksi target
- `$options` (array): Opsi populate

**Return:** Instance Cursor untuk chaining

**Contoh:**

```php
// Populate tunggal
$cursor = $collection->find()
    ->populate('user_id', $usersCollection);

// Populate multiple
$cursor = $collection->find()
    ->with([
        'user_id' => $usersCollection,
        'category_id' => $categoriesCollection
    ]);

// Populate dengan opsi
$cursor = $collection->find()
    ->populate('author', $usersCollection, ['as' => 'author_info']);
```

## Soft Delete Modifiers

### `withTrashed(): self`

Menyertakan dokumen yang di-soft delete dalam hasil.

**Return:** Instance Cursor untuk chaining

### `onlyTrashed(): self`

Hanya mengembalikan dokumen yang di-soft delete.

**Return:** Instance Cursor untuk chaining

## Output Methods

### `toArray(): array`

Mengkonversi cursor ke array dokumen.

**Return:** Array dokumen

### `count(): int`

Menghitung jumlah dokumen dalam cursor (dengan memperhitungkan limit/skip).

**Return:** Jumlah dokumen

### `each($callable): self`

Menerapkan callback ke setiap dokumen dalam cursor.

**Parameter:**

- `$callable` (callable): Fungsi yang akan dipanggil untuk setiap dokumen

**Return:** Instance Cursor untuk chaining

**Contoh:**

```php
$cursor->each(function($doc) {
    echo $doc['name'] . "\n";
});
```

## Iterator Methods

### `rewind(): void`

Mengatur ulang cursor ke awal.

### `current(): ?array`

Mengembalikan dokumen saat ini.

**Return:** Array dokumen atau null

### `key(): int`

Mengembalikan key posisi saat ini.

**Return:** Posisi integer

### `next(): void`

Memindah ke dokumen berikutnya.

### `valid(): bool`

Memeriksa apakah posisi saat ini valid.

**Return:** True jika valid

### `getIterator(): \Traversable`

Mengembalikan iterator untuk foreach loops.

## Kriteria Query

Cursor mendukung kriteria pencarian yang sama dengan Collection.find():

### Contoh Kriteria

```php
// Equality
$cursor = $collection->find(['status' => 'active']);

// Comparison operators
$cursor = $collection->find(['age' => ['$gte' => 18]]);

// Logical operators
$cursor = $collection->find([
    '$and' => [
        ['age' => ['$gte' => 18]],
        ['status' => 'active']
    ]
]);

// Regex
$cursor = $collection->find(['name' => ['$regex' => '/^John/']]);

// Array operators
$cursor = $collection->find(['tags' => ['$in' => ['php', 'javascript']]]);
```

## Projection

Cursor mendukung projection untuk membatasi field yang dikembalikan:

```php
// Inclusive projection (hanya field tertentu)
$cursor = $collection->find(['status' => 'active'], ['name' => 1, 'email' => 1]);

// Exclusive projection (kecuali field tertentu)
$cursor = $collection->find(['status' => 'active'], ['password' => 0, 'secret' => 0]);
```

## Contoh Penggunaan Lengkap

```php
use BangronDB\Client;

$client = new Client('/path/to/db');
$posts = $client->selectCollection('blog', 'posts');
$users = $client->selectCollection('blog', 'users');

// Query sederhana dengan pagination
$recentPosts = $posts->find(['published' => true])
    ->sort(['created_at' => -1])
    ->limit(10)
    ->skip(0)
    ->toArray();

// Query dengan populate
$postsWithAuthors = $posts->find(['published' => true])
    ->populate('author_id', $users, ['as' => 'author'])
    ->sort(['created_at' => -1])
    ->limit(5)
    ->toArray();

// Menggunakan foreach
foreach ($posts->find() as $post) {
    echo $post['title'] . "\n";
}

// Menghitung hasil
$totalPosts = $posts->find(['published' => true])->count();

// Menggunakan each untuk processing
$posts->find(['status' => 'draft'])
    ->each(function($post) {
        // Process each draft post
        echo "Processing: " . $post['title'] . "\n";
    });
```
