# 📓 BangronDB Glossary (Kamus Istilah)

Penjelasan istilah teknis yang sering digunakan dalam dokumentasi BangronDB.

## A

### API (Application Programming Interface)

Interface untuk berkomunikasi dengan software. BangronDB punya API yang mirip MongoDB.

**Contoh**: `$collection->find()` adalah bagian dari API BangronDB.

### Autoload / Autoloading

Mekanisme otomatis untuk mengimport/meload class tanpa perlu manual require.

**Contoh**: Dengan Composer, tidak perlu `require_once 'Client.php'`, cukup `use BangronDB\Client`.

### ACID

Karakteristik database yang aman:

- **Atomicity** - Operasi all-or-nothing
- **Consistency** - Data selalu konsisten
- **Isolation** - Transaksi terisolasi
- **Durability** - Data persisten di disk

---

## B

### Bangron File (.bangron)

File database BangronDB (sebenarnya SQLite file dengan ekstension berbeda).

**Contoh**: `app.bangron` adalah database file yang menyimpan semua koleksi.

### Batch Insert

Menambahkan banyak dokumen sekaligus (lebih efisien daripada satu-satu).

```php
$users->insert([
    ['name' => 'John'],
    ['name' => 'Jane'],
    ['name' => 'Bob']
]);
```

---

## C

### Cache

Penyimpanan sementara untuk data yang sering diakses (lebih cepat).

### Collection

Kelompok data sejenis dalam database (dalam SQL, mirip dengan tabel).

**Contoh**: Collection "users" menyimpan semua data user.

### Composite Index

Index yang melibatkan lebih dari satu field.

```php
$collection->createIndex(['status', 'created_at']);
```

### Coroutine

Fungsi yang bisa di-pause dan di-resume (tidak sering digunakan di sini).

### CRUD

Operasi data dasar:

- **C**reate - Tambah data
- **R**ead - Baca data
- **U**pdate - Ubah data
- **D**elete - Hapus data

---

## D

### Database

Kumpulan koleksi yang tersimpan dalam satu file.

**Contoh**: `$client->app` mengakses database bernama "app".

### Document

Satu record data dalam koleksi (mirip baris di tabel SQL).

```php
['_id' => '123', 'name' => 'John', 'email' => 'john@example.com']
```

### Dot Notation

Cara mengakses nested field dengan titik.

```php
$users->find(['address.city' => 'New York']); // address adalah object dengan property city
```

---

## E

### Encryption

Teknik mengamankan data dengan mengkodekannya menggunakan algoritma (AES-256).

**Contoh**: Data terenkripsi tidak bisa dibaca tanpa key/password yang benar.

### Encryption Key

Kunci/password untuk mengenkripsi dan mendekripsi data.

```php
$collection->setEncryptionKey('my-secret-key');
```

---

## F

### Fragmentation

Pemborosan ruang disk karena data tidak tersusun rapi setelah update/delete banyak.

**Solusi**: Gunakan `VACUUM` untuk mengoptimalkan.

### Foreign Key

Field yang merujuk ke ID dokumen di koleksi lain.

**Contoh**: `author_id` di posts collection merujuk ke `_id` di users collection.

### Fuzzy Search

Pencarian yang toleran terhadap typo atau kesalahan spelling.

```php
$collection->find(['name' => ['$fuzzy' => ['$search' => 'john']]]);
// Akan menemukan: 'jon', 'joan', 'john'
```

---

## G

### GLUE Code / Glue Function

Code yang menghubungkan berbagai sistem (tidak langsung relevan).

---

## H

### Hash / Hashing

Fungsi satu arah yang mengubah data menjadi string tetap.

```php
hash('sha256', 'password') = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'
```

### Hook

Code yang berjalan otomatis pada event tertentu (before/after operation).

```php
$collection->on('beforeInsert', function($doc) {
    $doc['created_at'] = date('c');
    return $doc;
});
```

### HTTPS

Protocol aman untuk komunikasi internet (dengan SSL/TLS encryption).

---

## I

### Index

Struktur untuk mempercepat pencarian pada field tertentu.

```php
$collection->createIndex('email'); // Pencarian by email akan lebih cepat
```

### **$in**

Operator untuk query "dalam daftar".

```php
['role' => ['$in' => ['admin', 'editor']]]
// role = 'admin' OR role = 'editor'
```

### Integration

Menggabungkan BangronDB dengan framework lain (Laravel, Symfony, dll).

---

## J

### JSON (JavaScript Object Notation)

Format data standar dengan key-value pairs.

```json
{
  "name": "John",
  "age": 30,
  "hobbies": ["reading", "coding"]
}
```

### JSON Schema

Spesifikasi untuk validasi struktur JSON.

---

## K

### Key-Value

Pasangan identifier dan value (mirip array associative di PHP).

```php
['name' => 'John', 'email' => 'john@example.com']
```

---

## L

### Levenshtein Distance

Algoritma mengukur perbedaan antara 2 string (untuk fuzzy search).

---

## M

### Magic Method

Method special di PHP yang triggered oleh action tertentu.

```php
$db = $client->users; // Trigger __get magic method
```

### Merge / Merging

Menggabungkan data lama dengan data baru (update mode default).

```php
$collection->update(['_id' => '1'], ['age' => 31]); // Merge mode
// Result: Semua field lama tetap, age diubah jadi 31
```

### Metadata

Data tentang data (informasi tentang collection/database).

```php
['version' => 42, 'last_updated' => '2024-01-15']
```

### MongoDB

Database NoSQL yang terkenal (BangronDB APInya mirip MongoDB).

---

## N

### **$ne**

Operator "tidak sama dengan".

```php
['status' => ['$ne' => 'inactive']]
// status != 'inactive'
```

### **$nin**

Operator "tidak dalam daftar".

```php
['role' => ['$nin' => ['guest', 'banned']]]
// role != 'guest' AND role != 'banned'
```

### NoSQL

Database tanpa struktur SQL fixed (fleksibel).

---

## O

### Operator

Symbol atau keyword untuk query condition.

**Contoh**: `$gt`, `$in`, `$or`, `$and`.

### **$or**

Operator logika "atau" (salah satu syarat terpenuhi).

```php
['$or' => [['age' => ['$lt' => 18]], ['age' => ['$gt' => 65]]]]
// age < 18 OR age > 65
```

---

## P

### Pagination

Membagi hasil query menjadi halaman-halaman.

```php
->limit(10)->skip(20) // Halaman 3 (item 21-30)
```

### PDO (PHP Data Objects)

Library PHP untuk berkomunikasi dengan database.

### Populate

Menggabungkan data dari koleksi berbeda (seperti JOIN di SQL).

```php
$posts->find()->populate('author_id', $users, ['as' => 'author'])
```

### PSR-4

Standard PHP untuk autoloading class.

---

## Q

### Query

Perintah untuk mengambil/memanipulasi data.

**Contoh**: `$collection->find(['age' => ['$gt' => 18]])`.

### Query Operator

Symbol untuk criteria query (`$gt`, `$lt`, `$in`, dll).

---

## R

### Regex (Regular Expression)

Pattern untuk matching string dengan pattern tertentu.

```php
['email' => ['$regex' => '^[a-z]+@[a-z]+\.[a-z]+$']]
```

### Relational Data

Data yang punya hubungan/relasi dengan data lain (foreign key).

### Repository Pattern

Design pattern untuk abstraksi akses data.

---

## S

### Schema

Struktur/blueprint data yang valid.

```php
$collection->setSchema(['name' => ['required' => true, 'type' => 'string']]);
```

### Searchable Fields

Field yang di-index untuk pencarian cepat (especially on encrypted data).

```php
$collection->setSearchableFields(['email'], true); // Hash untuk privacy
```

### Soft Delete

Menghapus data secara logika (dipanday jadi deleted, tapi file tetap ada).

```php
$collection->useSoftDeletes(true);
$collection->remove(['_id' => '123']); // Data ditandai deleted, bukan dihapus fisik
```

### SQLite

Lightweight database engine (gunakan BangronDB).

### SSL/TLS

Protocol untuk encrypted communication.

---

## T

### Timestamp

Waktu dalam format Unix (jumlah detik sejak 1 Januari 1970).

```php
time() = 1705328524
```

### Transaction

Operasi database yang atomik (all-or-nothing).

---

## U

### UUID (Universally Unique Identifier)

ID unik yang generated secara random (tidak duplikat).

```
550e8400-e29b-41d4-a716-446655440000
```

### Upsert

Update jika data ada, insert jika tidak ada.

```php
$collection->save(['_id' => '123', 'name' => 'John']);
```

---

## V

### Validation

Proses memastikan data sesuai dengan aturan/schema.

---

## W

### WAL (Write-Ahead Logging)

Mode SQLite untuk better concurrency.

---

## X

### XML

Format data (mirip JSON, tapi berbeda syntax).

---

## Y

### YAML

Format data human-readable (sering untuk config).

---

## Z

### Zero-Configuration

Tidak perlu konfigurasi rumit (BangronDB ini termasuk).

---

## Operator Reference Cepat

| Operator  | Artinya               | Contoh                                            |
| --------- | --------------------- | ------------------------------------------------- |
| `$gt`     | Greater Than (>)      | `['age' => ['$gt' => 18]]`                        |
| `$gte`    | Greater or Equal (>=) | `['age' => ['$gte' => 18]]`                       |
| `$lt`     | Less Than (<)         | `['age' => ['$lt' => 65]]`                        |
| `$lte`    | Less or Equal (<=)    | `['age' => ['$lte' => 60]]`                       |
| `$ne`     | Not Equal (!=)        | `['status' => ['$ne' => 'inactive']]`             |
| `$in`     | In List               | `['role' => ['$in' => ['admin', 'user']]]`        |
| `$nin`    | Not In List           | `['role' => ['$nin' => ['guest']]]`               |
| `$or`     | OR logic              | `['$or' => [condition1, condition2]]`             |
| `$and`    | AND logic             | `['$and' => [condition1, condition2]]`            |
| `$regex`  | Pattern match         | `['email' => ['$regex' => '/^[a-z]+@/']]`         |
| `$exists` | Field exists          | `['email' => ['$exists' => true]]`                |
| `$fuzzy`  | Fuzzy search          | `['name' => ['$fuzzy' => ['$search' => 'john']]]` |

---

**Masih bingung dengan istilah tertentu? Buka issue atau lihat contoh di folder `examples/`!**
