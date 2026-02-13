# 👶 BangronDB untuk Pemula

Panduan **super sederhana** untuk orang yang baru pertama kali menggunakan BangronDB.

## 🤔 Apa Itu BangronDB?

Bayangkan BangronDB sebagai **kotak penyimpanan digital untuk data Anda**:

- 📦 **Seperti spreadsheet** - menyimpan data dalam format yang terstruktur
- 💻 **Tapi lebih powerful** - bisa melakukan query kompleks dengan kode
- 🔒 **Aman** - data bisa di-enkripsi
- ⚡ **Cepat** - menggunakan SQLite yang sangat efisien

## 🎯 Konsep Super Dasar

### 1. Client = Manajer Utama

```php
$client = new Client('/folder/data'); // Menunjuk ke folder penyimpanan
```

**Bayangkan**: Client adalah orang yang mengelola beberapa lemari arsip.

### 2. Database = Lemari Arsip

```php
$db = $client->app; // "app" adalah nama lemari
```

**Bayangkan**: Satu lemari menyimpan dokumen-dokumen bisnis Anda.

File fisik: `app.bangron` (file di komputer Anda)

### 3. Collection = Laci di Lemari

```php
$users = $db->users;        // Laci untuk data users
$products = $db->products;  // Laci untuk data products
```

**Bayangkan**: Satu lemari punya beberapa laci, setiap laci menyimpan jenis data berbeda.

### 4. Document = File di Laci

```php
[
    '_id' => '123',           // ID unik (seperti nomor dokumen)
    'name' => 'John Doe',     // Data
    'email' => 'john@example.com'
]
```

**Bayangkan**: Setiap dokumen adalah satu file dengan informasi lengkap.

---

## 📝 Operasi Dasar (CRUD)

### C = CREATE (Tambah Data)

```php
// Tambah satu data
$userId = $users->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Hasilnya: $userId = "550e8400-e29b-41d4-a716-446655440000"
// (Ini adalah ID unik yang otomatis dibuat)

echo "User ditambah dengan ID: " . $userId;
```

### R = READ (Baca Data)

```php
// Cari satu data
$user = $users->findOne(['name' => 'John Doe']);
echo $user['email']; // Output: john@example.com

// Cari semua data
$allUsers = $users->find()->toArray();
foreach ($allUsers as $user) {
    echo $user['name'] . "\n";
}

// Cari dengan kriteria
$activeUsers = $users->find(['status' => 'active'])->toArray();
```

### U = UPDATE (Ubah Data)

```php
// Ubah salah satu data
$users->update(
    ['_id' => '123'],           // Cari dokumen ini
    ['email' => 'new@example.com'] // Ubah field ini
);
```

### D = DELETE (Hapus Data)

```php
// Hapus data
$users->remove(['_id' => '123']);

// Atau gunakan soft delete (aman, bisa di-restore)
// Penjelasan lebih lanjut di bagian Soft Deletes
```

---

## ⚙️ Setup Pertama Kali

### Step 1: Install via Composer

```bash
composer require herdianrony/bangrondb
```

### Step 2: Buat File Database

```php
<?php
// config.php
require_once 'vendor/autoload.php';

use BangronDB\Client;

// Buat folder 'data' di project Anda terlebih dahulu
$client = new Client(__DIR__ . '/data');
$db = $client->app; // Atau nama database Anda
```

### Step 3: Mulai Menggunakan

```php
<?php
require_once 'config.php';

// Ambil collection
$users = $db->users;

// Tambah data
$id = $users->insert([
    'name' => 'Saya',
    'email' => 'saya@example.com'
]);

echo "Data ditambah dengan ID: " . $id;
```

---

## 🔍 Query dengan Kriteria

### Operator Perbandingan

```php
// Lebih besar dari (>)
$users->find(['age' => ['$gt' => 18]]); // age > 18

// Lebih besar atau sama (>=)
$users->find(['age' => ['$gte' => 18]]); // age >= 18

// Lebih kecil dari (<)
$users->find(['age' => ['$lt' => 65]]); // age < 65

// Lebih kecil atau sama (<=)
$users->find(['age' => ['$lte' => 60]]); // age <= 60

// Tidak sama dengan (!=)
$users->find(['status' => ['$ne' => 'inactive']]); // status != 'inactive'

// Sama dengan (=)
$users->find(['role' => 'admin']); // role = 'admin'
```

### Operator Dalam Daftar

```php
// Dalam daftar (IN)
$users->find(['role' => ['$in' => ['admin', 'editor']]]);
// role = 'admin' OR role = 'editor'

// Tidak dalam daftar (NOT IN)
$users->find(['role' => ['$nin' => ['guest', 'banned']]]);
// role != 'guest' AND role != 'banned'
```

### Operator Logika

```php
// OR (salah satu syarat terpenuhi)
$users->find([
    '$or' => [
        ['age' => ['$lt' => 18]],
        ['age' => ['$gt' => 65]]
    ]
]);
// age < 18 OR age > 65

// AND (semua syarat terpenuhi)
$users->find([
    '$and' => [
        ['status' => 'active'],
        ['age' => ['$gte' => 21]]
    ]
]);
// status = 'active' AND age >= 21
```

---

## 🔐 Enkripsi Data Sensitif

### Untuk Koleksi Tertentu

```php
// Set encryption key untuk collection users
$users->setEncryptionKey('kunci-rahasia-32-karakter-atau-lebih!');

// Sekarang semua data yang disimpan akan ter-enkripsi otomatis
$userId = $users->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Data disimpan ter-enkripsi, tapi saat query tetap bisa normal
$user = $users->findOne(['email' => 'john@example.com']); // Berfungsi!
```

---

## 🎣 Hook: Aksi Otomatis pada Event

### Contoh: Tambah Timestamp Otomatis

Saat dokumenter ditambah, secara otomatis tambahkan waktu:

```php
// Setiap kali insert, jalankan function ini
$users->on('beforeInsert', function($document) {
    // Tambahkan created_at otomatis
    $document['created_at'] = date('Y-m-d H:i:s');
    return $document; // Return yang sudah dimodifikasi
});

// Sekarang, saat insert tidak perlu tambah created_at
$users->insert(['name' => 'John']); // created_at otomatis ditambah!
```

---

## 🔗 Relasi: Menghubungkan Data dari Koleksi Berbeda

Bayangkan: Koleksi users dan koleksi posts berbeda.

```php
// Collection: users
[
    '_id' => '1',
    'name' => 'John'
]

// Collection: posts
[
    '_id' => 'post1',
    'title' => 'Hello World',
    'author_id' => '1' // Merujuk ke user id
]

// Saat query posts, hubungkan dengan data user:
$posts = $db->posts->find()
    ->populate('author_id', $db->users, ['as' => 'author'])
    ->toArray();

// Hasil:
// [
//     '_id' => 'post1',
//     'title' => 'Hello World',
//     'author_id' => '1',
//     'author' => ['_id' => '1', 'name' => 'John'] // Data user sudah included!
// ]
```

---

## ✅ Validasi Data

Pastikan data yang disimpan valid sebelum masuk database:

```php
$users->setSchema([
    'name' => [
        'required' => true,      // Wajib ada
        'type' => 'string',      // Harus text
        'min' => 3,              // Minimal 3 karakter
        'max' => 100             // Maksimal 100 karakter
    ],
    'email' => [
        'required' => true,
        'type' => 'string',
        'regex' => '/^[\w\.-]+@[\w\.-]+\.\w+$/' // Format email
    ],
    'age' => [
        'type' => 'int',
        'min' => 0,
        'max' => 150
    ]
]);

// Sekarang saat insert dengan data invalid, akan error
try {
    $users->insert(['name' => 'Jo']); // ERROR! Min 3 chars
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

---

## 🗑️ Soft Delete: Hapus Tapi Bisa Dikembalikan

```php
// Enable soft delete
$users->useSoftDeletes(true);

// Delete (sebenarnya hanya ditandai sebagai deleted, bukan benar-benar dihapus)
$users->remove(['_id' => '123']);

// Query normal hanya ambil yang aktif
$activeUsers = $users->find()->toArray(); // Tidak include yang dihapus

// Include yang dihapus
$allUsers = $users->find()->withTrashed()->toArray();

// Hanya yang dihapus
$deletedUsers = $users->find()->onlyTrashed()->toArray();

// Restore data yang dihapus
$users->restore(['_id' => '123']);

// Delete permanen (tidak bisa dikembalikan)
$users->forceDelete(['_id' => '123']);
```

---

## 📊 Pagination & Sorting

```php
// Ambil 10 data, skip 0 (halaman pertama)
$users = $users->find()
    ->limit(10)    // Ambil 10 dokumen
    ->skip(0)      // Skip 0 dokumen (halaman 1)
    ->toArray();

// Halaman 2
$users = $users->find()
    ->limit(10)
    ->skip(10)     // Skip 10 dokumen (halaman 2)
    ->toArray();

// Sorting ascending (A-Z)
$users = $users->find()
    ->sort(['name' => 1])
    ->toArray();

// Sorting descending (Z-A)
$users = $users->find()
    ->sort(['name' => -1])
    ->toArray();

// Sorting multiple fields
$users = $users->find()
    ->sort(['status' => 1, 'created_at' => -1])
    ->toArray();
```

---

## 📈 Index untuk Query Cepat

Jika sering query field tertentu, buat index untuk mempercepat:

```php
// Buat index di field email (akan lebih cepat)
$users->createIndex('email');

// Buat index di field created_at
$users->createIndex('created_at');

// Query akan lebih cepat sekarang!
```

---

## 🚨 Error Handling: Tangani Kesalahan

```php
try {
    $id = $users->insert(['name' => 'Test']);
    echo "Berhasil! ID: " . $id;
} catch (InvalidArgumentException $e) {
    echo "Error input: " . $e->getMessage();
} catch (Exception $e) {
    echo "Error umum: " . $e->getMessage();
}
```

---

## 📚 Kelanjutan Belajar

Setelah memahami dasar-dasar di atas, Anda bisa belajar:

1. **Encryption** → docs/SECURITY-ENHANCEMENTS.md
2. **Advanced Queries** → docs/advanced.md
3. **Searchable Fields** → README.md bagian Searchable Fields
4. **Hooks Advanced** → README.md bagian Hooks & Events
5. **Relationships** → README.md bagian Populate & Relationships

---

## 🎯 Next: Real Project Example

Lihat [BEGINNER_PROJECT.md](BEGINNER_PROJECT.md) untuk contoh project nyata (TODO app) step by step dari 0.

---

**Butuh bantuan? Lihat [docs/troubleshooting.md](troubleshooting.md) untuk solusi error umum!**
