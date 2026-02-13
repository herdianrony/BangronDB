# CollectionManager Class

Dokumentasi API untuk class `CollectionManager` yang mengelola metadata dan konfigurasi collection.

## Namespace

```php
namespace BangronDB;
```

## Deskripsi

`CollectionManager` menyediakan antarmuka tingkat tinggi untuk mengelola konfigurasi collection, pelacakan metadata, dan caching untuk performa yang lebih baik. Class ini menangani penyimpanan dan pembacaan konfigurasi dari database.

## Constructor

```php
public function __construct(Database $database)
```

**Parameter:**

- `Database $database` - Instance Database

**Contoh:**

```php
$manager = new CollectionManager($db);
```

---

## Konfigurasi Collection

### `saveCollectionConfig()`

Menyimpan konfigurasi collection.

```php
public function saveCollectionConfig(string $collectionName, array $config): void
```

**Parameter:**

- `string $collectionName` - Nama collection
- `array $config` - Array konfigurasi

**Konfigurasi yang Valid:**

```php
$config = [
    'id_mode' => 'auto',              // atau 'manual', 'prefix:XYZ'
    'encryption_key' => 'key',        // kunci enkripsi
    'searchable_fields' => ['email'], // field yang dapat dicari
    'schema' => [...],                // schema validasi
    'soft_deletes_enabled' => true,   // aktifkan soft deletes
    'deleted_at_field' => '_deleted_at',
];
```

**Contoh:**

```php
$manager->saveCollectionConfig('users', [
    'id_mode' => 'auto',
    'encryption_key' => 'user-encryption-key',
    'soft_deletes_enabled' => true,
    'schema' => [
        'email' => ['required' => true, 'type' => 'string'],
        'name' => ['type' => 'string'],
    ],
]);
```

---

### `loadCollectionConfig()`

Memuat konfigurasi collection.

```php
public function loadCollectionConfig(string $collectionName): array
```

**Parameter:**

- `string $collectionName` - Nama collection

**Nilai Kembali:**

- `array` - Konfigurasi collection

**Contoh:**

```php
$config = $manager->loadCollectionConfig('users');
print_r($config);
```

---

### `getAllCollectionConfigs()`

Mendapatkan semua konfigurasi collection.

```php
public function getAllCollectionConfigs(): array
```

**Nilai Kembali:**

- `array` - Associative array dari nama collection ke konfigurasi

**Contoh:**

```php
$allConfigs = $manager->getAllCollectionConfigs();
foreach ($allConfigs as $name => $config) {
    echo "Collection: $name\n";
}
```

---

### `deleteCollectionConfig()`

Menghapus konfigurasi collection.

```php
public function deleteCollectionConfig(string $collectionName): void
```

**Parameter:**

- `string $collectionName` - Nama collection

**Contoh:**

```php
$manager->deleteCollectionConfig('users');
```

---

## Metadata Collection

### `updateMetadata()`

Memperbarui metadata collection.

```php
public function updateMetadata(string $collectionName, array $metadata = []): void
```

**Parameter:**

- `string $collectionName` - Nama collection
- `array $metadata` - Metadata tambahan (opsional)

**Contoh:**

```php
$manager->updateMetadata('users', [
    'last_accessed' => time(),
    'custom_field' => 'value',
]);
```

---

### `getMetadata()`

Mendapatkan metadata collection.

```php
public function getMetadata(string $collectionName): array
```

**Parameter:**

- `string $collectionName` - Nama collection

**Nilai Kembali:**

```php
[
    'version' => 1,           // Versi collection
    'last_updated' => '2024-01-01T12:00:00Z',  // Timestamp update
]
```

**Contoh:**

```php
$metadata = $manager->getMetadata('users');
echo "Version: " . $metadata['version'] . "\n";
```

---

### `getAllMetadata()`

Mendapatkan semua metadata collection.

```php
public function getAllMetadata(): array
```

**Nilai Kembali:**

- `array` - Associative array dari nama collection ke metadata

**Contoh:**

```php
$allMetadata = $manager->getAllMetadata();
```

---

## Caching

### `setCacheEnabled()`

Mengaktifkan atau menonaktifkan caching.

```php
public function setCacheEnabled(bool $enabled): void
```

**Parameter:**

- `bool $enabled` - `true` untuk aktifkan, `false` untuk nonaktifkan

**Contoh:**

```php
// Nonaktifkan cache
$manager->setCacheEnabled(false);

// Aktifkan kembali
$manager->setCacheEnabled(true);
```

---

### `isCacheEnabled()`

Memeriksa apakah caching aktif.

```php
public function isCacheEnabled(): bool
```

**Nilai Kembali:**

- `true` jika caching aktif, `false` jika tidak

**Contoh:**

```php
if ($manager->isCacheEnabled()) {
    echo "Cache enabled\n";
}
```

---

### `clearCaches()`

Menghapus semua cache.

```php
public function clearCaches(): void
```

**Contoh:**

```php
$manager->clearCaches();
```

---

## Statistik Collection

### `getCollectionStats()`

Mendapatkan statistik lengkap untuk satu collection.

```php
public function getCollectionStats(string $collectionName): array
```

**Parameter:**

- `string $collectionName` - Nama collection

**Nilai Kembali:**

```php
[
    'config' => [...],    // Konfigurasi collection
    'metadata' => [...],  // Metadata collection
    'exists' => true,     // Apakah collection ada
]
```

**Contoh:**

```php
$stats = $manager->getCollectionStats('users');
```

---

### `getAllCollectionStats()`

Mendapatkan statistik untuk semua collection.

```php
public function getAllCollectionStats(): array
```

**Nilai Kembali:**

- `array` - Associative array dari nama collection ke statistik

**Contoh:**

```php
$allStats = $manager->getAllCollectionStats();
```

---

## Deteksi Perubahan

### `isModifiedSince()`

Memeriksa apakah collection telah diubah sejak timestamp tertentu.

```php
public function isModifiedSince(string $collectionName, int $timestamp): bool
```

**Parameter:**

- `string $collectionName` - Nama collection
- `int $timestamp` - Timestamp untuk perbandingan

**Nilai Kembali:**

- `true` jika modified, `false` jika tidak

**Contoh:**

```php
$lastCheck = strtotime('2024-01-01');
if ($manager->isModifiedSince('users', $lastCheck)) {
    echo "Collection users telah diubah\n";
}
```

---

### `getModifiedSince()`

Mendapatkan collection yang diubah sejak timestamp tertentu.

```php
public function getModifiedSince(int $timestamp): array
```

**Parameter:**

- `int $timestamp` - Timestamp untuk perbandingan

**Nilai Kembali:**

- `array` - Collection yang telah diubah

**Contoh:**

```php
$modified = $manager->getModifiedSince(strtotime('2024-01-01'));
```

---

## Contoh Penggunaan Lengkap

```php
use BangronDB\Client;

$client = new Client('/path/to/data');
$db = $client->selectDB('app');
$manager = new CollectionManager($db);

// Simpan konfigurasi collection
$manager->saveCollectionConfig('users', [
    'id_mode' => 'auto',
    'encryption_key' => 'user-key',
    'searchable_fields' => ['email'],
    'soft_deletes_enabled' => true,
]);

// Muat konfigurasi
$config = $manager->loadCollectionConfig('users');

// Update metadata
$manager->updateMetadata('users', ['last_modified' => time()]);

// Dapatkan metadata
$metadata = $manager->getMetadata('users');

// Dapatkan statistik collection
$stats = $manager->getCollectionStats('users');

// Cek apakah collection berubah
if ($manager->isModifiedSince('users', $lastCheckTime)) {
    // Refresh data
}

// Nonaktifkan caching jika diperlukan
$manager->setCacheEnabled(false);
```
