# Panduan Memulai BangronDB

Panduan lengkap untuk memulai menggunakan BangronDB dalam proyek PHP Anda.

## Instalasi

### Persyaratan Sistem

- PHP 8.0 atau lebih tinggi
- Ekstensi PDO SQLite (biasanya sudah tersedia)
- Ekstensi OpenSSL (untuk fitur enkripsi)

### Via Composer

```bash
composer require bangrondb/bangrondb
```

### Manual Installation

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

## Quick Start

### 1. Setup Database

```php
<?php
require_once 'vendor/autoload.php'; // Jika menggunakan Composer

use BangronDB\Client;

// Buat client untuk mengelola databases
$client = new Client(__DIR__ . '/data');

// Pilih database (otomatis dibuat jika tidak ada)
$db = $client->blog;
```

### 2. Operasi Dasar CRUD

```php
// Pilih collection
$posts = $db->posts;

// Insert dokumen
$postId = $posts->insert([
    'title' => 'Hello World',
    'content' => 'Ini adalah post pertama saya',
    'author' => 'John Doe',
    'tags' => ['php', 'tutorial'],
    'created_at' => date('c')
]);

echo "Post inserted with ID: $postId\n";

// Find dokumen
$post = $posts->findOne(['author' => 'John Doe']);
print_r($post);

// Update dokumen
$posts->update(
    ['_id' => $postId],
    ['$set' => ['views' => 150, 'updated_at' => date('c')]]
);

// Delete dokumen
$posts->remove(['_id' => $postId]);
```

### 3. Query Lanjutan

```php
// Cari dengan kriteria
$publishedPosts = $posts->find(['status' => 'published'])->toArray();

// Query dengan operator
$popularPosts = $posts->find([
    'views' => ['$gte' => 100],
    'created_at' => ['$gte' => '2024-01-01']
])->toArray();

// Sorting dan pagination
$recentPosts = $posts->find()
    ->sort(['created_at' => -1])
    ->limit(10)
    ->toArray();
```

## Struktur Proyek

### Struktur Direktori yang Disarankan

```
project/
├── src/
│   └── models/
├── data/           # Database files akan dibuat di sini
├── public/
├── vendor/         # Jika menggunakan Composer
└── index.php
```

### Inisialisasi Database

```php
<?php
// config/database.php
require_once __DIR__ . '/../vendor/autoload.php';

use BangronDB\Client;

class Database {
    private static $client = null;

    public static function getClient() {
        if (self::$client === null) {
            self::$client = new Client(__DIR__ . '/../data');
        }
        return self::$client;
    }

    public static function getDB($name = 'app') {
        return self::getClient()->$name;
    }
}
```

## Model Pattern

### Membuat Model Class

```php
<?php
// src/models/User.php
require_once __DIR__ . '/../../config/database.php';

class User {
    private $collection;

    public function __construct() {
        $db = Database::getDB('app');
        $this->collection = $db->users;
    }

    public function create($data) {
        // Set default values
        $data['created_at'] = date('c');
        $data['status'] = $data['status'] ?? 'active';

        return $this->collection->insert($data);
    }

    public function findById($id) {
        return $this->collection->findOne(['_id' => $id]);
    }

    public function findByEmail($email) {
        return $this->collection->findOne(['email' => $email]);
    }

    public function update($id, $data) {
        $data['updated_at'] = date('c');
        return $this->collection->update(['_id' => $id], ['$set' => $data]);
    }

    public function delete($id) {
        return $this->collection->remove(['_id' => $id]);
    }

    public function getActiveUsers() {
        return $this->collection->find(['status' => 'active'])
            ->sort(['created_at' => -1])
            ->toArray();
    }
}
```

### Penggunaan Model

```php
<?php
// public/users.php
require_once __DIR__ . '/../src/models/User.php';

$userModel = new User();

// Buat user baru
$userId = $userModel->create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'role' => 'admin'
]);

// Cari user
$user = $userModel->findById($userId);
$userByEmail = $userModel->findByEmail('john@example.com');

// Update user
$userModel->update($userId, [
    'last_login' => date('c'),
    'login_count' => ($user['login_count'] ?? 0) + 1
]);

// Ambil semua active users
$activeUsers = $userModel->getActiveUsers();
```

## Fitur Lanjutan

### Enkripsi Data

```php
<?php
// Setup collection dengan enkripsi
$users = $db->users;
$users->setEncryptionKey('your-secret-key-here');

// Data sensitif akan dienkripsi otomatis
$userId = $users->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',     // Tidak dienkripsi
    'ssn' => '123-45-6789',            // Dienkripsi
    'credit_card' => '4111111111111111' // Dienkripsi
]);

// Ketika diambil, data terenkripsi akan didekripsi otomatis
$user = $users->findOne(['_id' => $userId]);
echo $user['ssn']; // Output: 123-45-6789 (sudah didekripsi)
```

### Searchable Fields

```php
<?php
// Setup field pencarian dengan hashing untuk privasi
$users->setSearchableFields(['email'], true); // Hash email

// Sekarang bisa query berdasarkan email
$user = $users->findOne(['email' => 'john@example.com']);

// Atau query dengan hash langsung
$hash = hash('sha256', 'john@example.com');
$user = $users->findOne(['email' => $hash]);
```

### Hooks untuk Business Logic

```php
<?php
// Hook sebelum insert
$users->on('beforeInsert', function($doc) {
    // Validasi
    if (empty($doc['email'])) {
        return false; // Batalkan insert
    }

    // Set default values
    $doc['created_at'] = date('c');
    $doc['email_verified'] = false;

    return $doc; // Return modified document
});

// Hook setelah insert
$users->on('afterInsert', function($doc, $id) {
    // Kirim email verifikasi
    sendVerificationEmail($doc['email'], $id);
});

// Hook sebelum update
$users->on('beforeUpdate', function($criteria, $data) {
    // Log perubahan
    error_log("Updating user: " . json_encode($criteria));

    // Set update timestamp
    if (!isset($data['$set'])) {
        $data['$set'] = [];
    }
    $data['$set']['updated_at'] = date('c');

    return [$criteria, $data];
});
```

### Relasi Antar Dokumen (Population)

```php
<?php
// Misalkan ada posts yang referensi users
$posts = $db->posts;
$users = $db->users;

// Insert sample data
$userId = $users->insert(['name' => 'John Doe']);
$postId = $posts->insert([
    'title' => 'My First Post',
    'content' => 'Hello world!',
    'author_id' => $userId
]);

// Populate relasi
$post = $posts->findOne(['_id' => $postId]);
$populatedPost = $posts->populate([$post], 'author_id', 'users', '_id', 'author')[0];

print_r($populatedPost);
/*
Array (
    '_id' => '...',
    'title' => 'My First Post',
    'author_id' => '...',
    'author' => Array (
        '_id' => '...',
        'name' => 'John Doe'
    )
)
*/
```

## Error Handling

### Try-Catch untuk Operasi Database

```php
<?php
try {
    $userId = $users->insert([
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ]);

    if (!$userId) {
        throw new Exception('Failed to insert user');
    }

    echo "User created with ID: $userId\n";

} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
```

### Validasi Data

```php
<?php
function validateUser($data) {
    $errors = [];

    if (empty($data['name'])) {
        $errors[] = 'Name is required';
    }

    if (empty($data['email'])) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    // Check if email already exists
    $existing = $users->findOne(['email' => $data['email']]);
    if ($existing) {
        $errors[] = 'Email already exists';
    }

    return $errors;
}

// Penggunaan
$userData = [
    'name' => 'John Doe',
    'email' => 'john@example.com'
];

$errors = validateUser($userData);
if (!empty($errors)) {
    echo "Validation errors:\n";
    foreach ($errors as $error) {
        echo "- $error\n";
    }
} else {
    $userId = $users->insert($userData);
    echo "User created successfully\n";
}
```

## Best Practices

### 1. Struktur Data

- Gunakan \_id sebagai primary identifier
- Simpan timestamp dalam format ISO 8601 (`date('c')`)
- Gunakan array untuk menyimpan multiple values
- Pertimbangkan normalisasi vs embedding berdasarkan use case

### 2. Indexing

```php
// Buat index untuk field yang sering di-query
$users->createIndex('email');      // Untuk lookup email
$posts->createIndex('author_id');  // Untuk relasi
$posts->createIndex('created_at'); // Untuk sorting chronological
```

### 3. Performance

- Gunakan pagination untuk query besar
- Manfaatkan searchable fields untuk query encrypted data
- Batch operations untuk multiple inserts/updates
- Monitor dengan health metrics
- Enable query logging untuk debugging

```php
$db->queryExecutor->setLogging(true)
                  ->setPerformanceMonitoring(true);
```

### 4. Security

- Selalu validasi input user
- Gunakan enkripsi untuk data sensitif
- Hash searchable fields untuk privacy
- Implement proper authentication/authorization di aplikasi layer
- Jangan gunakan `executeRaw()` untuk user input

## Troubleshooting

### Common Issues

**1. "Class not found" errors**

- Pastikan semua file sudah di-include
- Jika menggunakan Composer, jalankan `composer dump-autoload`

**2. Permission errors**

- Pastikan direktori data/ writable oleh web server
- Set proper file permissions: `chmod 755 data/`

**3. SQLite errors**

- Cek apakah PDO SQLite tersedia: `php -m | grep pdo_sqlite`
- Pastikan SQLite3 support diaktifkan

**4. Memory issues**

- Collection caching sudah dihapus untuk menghemat memory
- Untuk dataset besar, gunakan pagination

### Debug Mode

```php
<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Enable query logging
$db->queryExecutor->setLogging(true);

// Log SQL errors (dalam Collection.php)
protected function logSqlError(string $sql): void {
    trigger_error('SQL Error: ' . implode(', ', $this->database->connection->errorInfo()) . ":\n" . $sql);
}
```

## Selanjutnya

- Lihat [API Reference](api/) untuk dokumentasi lengkap
- Lihat [contoh](../examples/) untuk implementasi praktis
- Pelajari [panduan lanjutan](advanced.md) untuk fitur advanced
- Baca [panduan keamanan](performance-security.md) untuk best practices
