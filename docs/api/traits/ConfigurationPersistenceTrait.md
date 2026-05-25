# ConfigurationPersistenceTrait API Reference

Trait untuk menyimpan dan memuat konfigurasi collection ke/dari database.

## Trait Overview

```php
trait BangronDB\Traits\ConfigurationPersistenceTrait
{
    // Properties
    protected array $customConfig = [];

    // Methods
    protected function loadConfiguration(): void
    public function saveConfiguration(): void
    public function setCustomConfig(string $key, $value): self
    public function getCustomConfig(string $key, $default = null): mixed
    public function getAllCustomConfig(): array
    public function setCustomConfigArray(array $config): self
}
```

## Deskripsi

ConfigurationPersistenceTrait menyediakan mekanisme untuk menyimpan dan memuat seluruh konfigurasi collection ke dalam database. Trait ini memastikan bahwa pengaturan seperti ID mode, searchable fields, schema validation, soft delete, dan konfigurasi kustom tetap tersedia meskipun aplikasi di-restart.

---

## Properties

### `$customConfig`

Menyimpan nilai konfigurasi kustom yang didefinisikan pengguna.

**Type:** `array`
**Default:** `[]`
**Visibility:** `protected`

---

## Metode

### `loadConfiguration(): void`

Memuat konfigurasi collection dari database.

Metode ini otomatis dipanggil saat collection dibuat melalui `$db->selectCollection()`. Memulihkan semua pengaturan yang sebelumnya disimpan termasuk ID mode, searchable fields, schema validation, soft delete, dan custom config.

**Parameters:**

Tidak ada parameter.

**Return:** `void`

**Visibility:** `protected`

**Behavior:**

1. Memuat konfigurasi dari database via `$this->database->loadCollectionConfig($this->name)`
2. Menerapkan konfigurasi yang dimuat ke collection:
   - **ID Mode** — Menggunakan `setIdModeFromString()` untuk memulihkan mode ID (`auto`, `manual`, atau prefix)
   - **Searchable Fields** — Memulihkan field pencarian beserta status hashing
   - **Schema** — Memulihkan validasi schema dokumen
   - **Soft Deletes** — Memulihkan status dan nama field soft delete
   - **Custom Config** — Memulihkan semua konfigurasi kustom

**Catatan Penting:**

> Kunci enkripsi (`encryption_key`) **tidak** disimpan dalam konfigurasi karena alasan keamanan. Enkripsi harus diaktifkan secara manual setelah collection dimuat menggunakan `$collection->setEncryptionKey('your-key')`.

**Examples:**

```php
// Konfigurasi dimuat otomatis saat collection dibuat
$users = $db->selectCollection('users');
// Semua pengaturan sebelumnya yang disimpan sudah aktif:
// - ID mode
// - Searchable fields
// - Schema validation
// - Soft delete settings
// - Custom config values

// Enkripsi harus diaktifkan manual (kunci tidak disimpan)
$users->setEncryptionKey(getenv('ENCRYPTION_KEY'));
```

---

### `saveConfiguration(): void`

Menyimpan konfigurasi collection saat ini ke database.

Metode ini mempertahankan semua pengaturan collection sehingga dapat dipulihkan saat collection dimuat kembali di masa mendatang.

**Parameters:**

Tidak ada parameter.

**Return:** `void`

**Behavior:**

Menyimpan konfigurasi berikut ke database:

| Key                       | Type    | Deskripsi                                       |
| ------------------------- | ------- | ----------------------------------------------- |
| `id_mode`                 | `string`| Mode ID: `auto`, `manual`, atau string prefix  |
| `encryption_enabled`      | `bool`  | Status apakah enkripsi aktif (bukan kuncinya)   |
| `searchable_fields`       | `array` | Field pencarian beserta status hashing           |
| `schema`                  | `array` | Schema validasi dokumen                          |
| `soft_deletes_enabled`    | `bool`  | Status soft delete aktif/tidak                   |
| `deleted_at_field`        | `string`| Nama field untuk soft delete timestamp           |
| `custom_config`           | `array` | Semua nilai konfigurasi kustom                  |

**Examples:**

```php
$users = $db->selectCollection('users');

// Konfigurasi collection
$users->setIdMode(Collection::ID_MODE_PREFIX);
$users->setIdMode('user:');
$users->setSearchableFields(['email'], true);
$users->setSchema([
    'name' => ['required' => true],
    'email' => ['required' => true, 'type' => 'string'],
]);
$users->useSoftDeletes(true);
$users->setCustomConfig('max_documents', 1000);

// Simpan semua konfigurasi ke database
$users->saveConfiguration();

// Konfigurasi akan otomatis dimuat saat:
// $users = $db->selectCollection('users');
```

---

### `setCustomConfig(string $key, $value): self`

Menetapkan nilai konfigurasi kustom untuk collection.

Memungkinkan penyimpanan pengaturan kustom yang spesifik untuk collection, seperti batasan, metadata, atau preferensi aplikasi.

**Parameters:**

| Parameter | Type     | Deskripsi                    |
| --------- | -------- | ---------------------------- |
| `$key`    | `string` | Kunci konfigurasi            |
| `$value`  | `mixed`  | Nilai konfigurasi (serializable) |

**Return:** `self` untuk method chaining

**Examples:**

```php
$users = $db->selectCollection('users');

// Set konfigurasi tunggal
$users->setCustomConfig('max_documents', 10000);
$users->setCustomConfig('cache_ttl', 3600);
$users->setCustomConfig('description', 'User accounts collection');
$users->setCustomConfig('owner', 'team-backend');

// Method chaining
$users->setCustomConfig('region', 'asia')
      ->setCustomConfig('replicated', true)
      ->saveConfiguration();
```

---

### `getCustomConfig(string $key, $default = null): mixed`

Mengambil nilai konfigurasi kustom berdasarkan kunci.

**Parameters:**

| Parameter | Type     | Deskripsi                                  |
| --------- | -------- | ------------------------------------------ |
| `$key`    | `string` | Kunci konfigurasi yang akan diambil        |
| `$default`| `mixed`  | Nilai default jika kunci tidak ditemukan. Default: `null` |

**Return:** `mixed` — Nilai konfigurasi atau `$default` jika kunci tidak ada.

**Examples:**

```php
$users = $db->selectCollection('users');

// Ambil konfigurasi
$maxDocs = $users->getCustomConfig('max_documents');         // 10000
$ttl = $users->getCustomConfig('cache_ttl');                 // 3600
$desc = $users->getCustomConfig('description');              // 'User accounts...'

// Dengan default value
$priority = $users->getCustomConfig('priority', 'normal');  // 'normal' (key tidak ada)
$enabled = $users->getCustomConfig('enabled', false);       // false (key tidak ada)
```

---

### `getAllCustomConfig(): array`

Mengambil semua nilai konfigurasi kustom yang tersimpan.

**Parameters:**

Tidak ada parameter.

**Return:** `array` — Semua key-value pair konfigurasi kustom.

**Examples:**

```php
$users = $db->selectCollection('users');

// Ambil semua custom config
$config = $users->getAllCustomConfig();
// [
//     'max_documents' => 10000,
//     'cache_ttl' => 3600,
//     'description' => 'User accounts collection',
//     'owner' => 'team-backend'
// ]

// Iterasi semua konfigurasi
foreach ($users->getAllCustomConfig() as $key => $value) {
    echo "{$key}: " . json_encode($value) . "\n";
}
```

---

### `setCustomConfigArray(array $config): self`

Menetapkan beberapa nilai konfigurasi kustom sekaligus.

Nilai yang ada akan diperbarui, dan nilai baru akan ditambahkan. Konfigurasi yang tidak disertakan akan tetap tersimpan (merge operation).

**Parameters:**

| Parameter | Type    | Deskripsi                                  |
| --------- | ------- | ------------------------------------------ |
| `$config` | `array` | Array asosiatif key-value pair konfigurasi |

**Return:** `self` untuk method chaining

**Behavior:**

- Menggunakan `array_merge()` untuk menggabungkan konfigurasi baru dengan yang sudah ada
- Konfigurasi yang tidak disertakan dalam `$config` akan tetap tersimpan
- Untuk menghapus key, gunakan `setCustomConfig($key, null)` atau assign array baru

**Examples:**

```php
$users = $db->selectCollection('users');

// Set beberapa konfigurasi sekaligus
$users->setCustomConfigArray([
    'max_documents' => 50000,
    'cache_ttl' => 7200,
    'description' => 'Production user collection',
    'owner' => 'team-platform',
    'region' => 'asia',
    'replicated' => true,
]);

// Method chaining
$users->setCustomConfigArray(['region' => 'eu', 'sharded' => true])
      ->saveConfiguration();

// Merge - konfigurasi lama tetap ada
$users->setCustomConfig('max_documents', 10000);
$users->setCustomConfig('cache_ttl', 3600);
$users->setCustomConfigArray([
    'description' => 'Updated description',  // Diupdate
    'owner' => 'team-infra',                  // Ditambahkan
    // max_documents dan cache_ttl tetap tersimpan
]);
```

---

## Implementation Details

### Struktur Konfigurasi di Database

Konfigurasi disimpan dalam tabel internal database melalui `saveCollectionConfig()`:

```json
{
  "id_mode": "user:",
  "encryption_enabled": false,
  "searchable_fields": {
    "email": true
  },
  "schema": {
    "name": { "required": true, "type": "string" },
    "email": { "required": true, "type": "string" }
  },
  "soft_deletes_enabled": true,
  "deleted_at_field": "_deleted_at",
  "custom_config": {
    "max_documents": 10000,
    "cache_ttl": 3600,
    "description": "User accounts collection"
  }
}
```

### Alur Kerja

```
Collection dibuat ($db->selectCollection())
    │
    ├─ loadConfiguration()
    │   ├─ loadCollectionConfig('users') → array|null
    │   ├─ id_mode → setIdModeFromString()
    │   ├─ searchable_fields → setSearchableFields()
    │   ├─ schema → setSchema()
    │   ├─ soft_deletes → useSoftDeletes()
    │   └─ custom_config → $this->customConfig
    │
    └─ Collection siap digunakan dengan konfigurasi lengkap


saveConfiguration()
    │
    ├─ Kumpulkan semua konfigurasi saat ini
    │   ├─ id_mode → getIdModeString()
    │   ├─ encryption_enabled → ($this->encryptionKey !== null)
    │   ├─ searchable_fields → getSearchableFieldsForConfig()
    │   ├─ schema → getSchema()
    │   ├─ soft_deletes → softDeletesEnabled()
    │   ├─ deleted_at_field → getDeletedAtField()
    │   └─ custom_config → $this->customConfig
    │
    └─ saveCollectionConfig('users', $config)
```

### Keamanan Enkripsi

Kunci enkripsi **tidak pernah** disimpan dalam konfigurasi yang dipersisten. Hanya status enkripsi yang disimpan (`encryption_enabled: true/false`). Kunci harus diberikan secara runtime:

```php
$users = $db->selectCollection('users');
// Konfigurasi dimuat: encryption_enabled = true
// Tapi enkripsi belum aktif karena belum ada kunci

$users->setEncryptionKey(getenv('ENCRYPTION_KEY'));
// Sekarang enkripsi aktif
```

---

## Usage Examples

### Setup Awal Collection

```php
// Buat dan konfigurasi collection baru
$users = $db->selectCollection('users');

$users->setIdMode(Collection::ID_MODE_PREFIX);
$users->setIdMode('user:');
$users->setSearchableFields(['email'], true);
$users->setSchema([
    'email'    => ['required' => true, 'type' => 'string', 'regex' => '/^[^@\s]+@[^@\s]+\.[^@\s]+$/'],
    'name'     => ['required' => true, 'type' => 'string'],
    'age'      => ['type' => 'int', 'min' => 0, 'max' => 150],
    'role'     => ['enum' => ['admin', 'user', 'guest']],
]);
$users->useSoftDeletes(true);

// Custom config untuk metadata aplikasi
$users->setCustomConfigArray([
    'description' => 'User accounts for the main application',
    'owner'       => 'team-backend',
    'created_by'  => 'admin',
    'environment' => getenv('APP_ENV'),
]);

// Simpan semua ke database
$users->saveConfiguration();
```

### Konfigurasi Environment-Specific

```php
$users = $db->selectCollection('users');

// Set konfigurasi berdasarkan environment
$env = getenv('APP_ENV');

$users->setCustomConfig('environment', $env);
$users->setCustomConfig('debug_mode', $env === 'development');

if ($env === 'production') {
    $users->setCustomConfig('max_documents', 1000000);
    $users->setCustomConfig('cache_ttl', 7200);
    $users->setCustomConfig('backup_enabled', true);
} else {
    $users->setCustomConfig('max_documents', 10000);
    $users->setCustomConfig('cache_ttl', 60);
    $users->setCustomConfig('backup_enabled', false);
}

$users->saveConfiguration();
```

### Membaca Konfigurasi Collection

```php
function inspectCollection($collection) {
    $custom = $collection->getAllCustomConfig();

    echo "Collection: " . $collection->getName() . "\n";
    echo "ID Mode: " . $collection->getIdMode() . "\n";
    echo "Soft Deletes: " . ($collection->softDeletesEnabled() ? 'Yes' : 'No') . "\n";
    echo "Encrypted: " . ($collection->isEncrypted() ? 'Yes' : 'No') . "\n";
    echo "Searchable Fields: " . implode(', ', array_keys($collection->getSearchableFields())) . "\n";
    echo "Custom Config:\n";

    foreach ($custom as $key => $value) {
        echo "  - {$key}: " . json_encode($value) . "\n";
    }
}

inspectCollection($db->users);
```

### Reset Konfigurasi

```php
function resetCustomConfig($collection) {
    // Reset ke konfigurasi default
    $collection->setCustomConfigArray([
        'description' => 'Default collection',
        'owner' => 'system',
    ]);
    $collection->saveConfiguration();

    echo "Custom config reset";
}

resetCustomConfig($db->users);
```

### Perbandingan Konfigurasi Antar Collection

```php
function compareCollections($db, array $collectionNames) {
    $configs = [];

    foreach ($collectionNames as $name) {
        $collection = $db->selectCollection($name);
        $configs[$name] = [
            'id_mode'     => $collection->getIdMode(),
            'soft_deletes' => $collection->softDeletesEnabled(),
            'encrypted'   => $collection->isEncrypted(),
            'custom'      => $collection->getAllCustomConfig(),
        ];
    }

    return $configs;
}

$comparison = compareCollections($db, ['users', 'posts', 'comments']);
print_r($comparison);
```

---

## Integration with Other Features

### Dengan IdGeneratorTrait

```php
$users->setIdMode(Collection::ID_MODE_PREFIX);
$users->setIdMode('usr:');
$users->saveConfiguration();
// ID mode dipulihkan saat collection dimuat kembali
```

### Dengan SearchableFieldsTrait

```php
$users->setSearchableFields(['email', 'username'], true);
$users->saveConfiguration();
// Searchable fields dan status hashing dipulihkan otomatis
```

### Dengan SchemaValidationTrait

```php
$users->setSchema([
    'email' => ['required' => true, 'type' => 'string'],
    'name'  => ['required' => true, 'type' => 'string'],
]);
$users->saveConfiguration();
// Schema validation dipulihkan otomatis
```

### Dengan SoftDeleteTrait

```php
$users->useSoftDeletes(true);
$users->setDeletedAtField('deleted_on');
$users->saveConfiguration();
// Soft delete settings dipulihkan otomatis
```

### Dengan ChangeTrackingTrait

```php
// Version tracking dan config persistence bekerja bersamaan
$users->setCustomConfig('version', $users->getLastModified()['version']);
$users->saveConfiguration();
```

### Dengan HooksTrait

```php
// Auto-save configuration setelah perubahan
$users->on('afterUpdate', function($doc) use ($users) {
    $users->setCustomConfig('last_update', date('c'));
    $users->saveConfiguration();
});
```

---

## Performance Considerations

### Overhead

| Operasi              | Query Tambahan | Dampak                |
| -------------------- | -------------- | --------------------- |
| `loadConfiguration()`| 1 query        | Sekali per collection creation |
| `saveConfiguration()`| 1 query        | Hanya saat dipanggil manual |

### Rekomendasi

- **Jangan panggil `saveConfiguration()`** di setiap operasi write
- Panggil `saveConfiguration()` hanya saat ada perubahan konfigurasi yang signifikan
- Custom config tidak memiliki batasan ukuran, tapi hindari menyimpan data besar

---

## Error Handling

| Skenario                              | Behavior                      |
| ------------------------------------- | ----------------------------- |
| Tabel config belum ada                | `loadConfiguration()` skip    |
| Config kosong/tidak ada               | Collection menggunakan default |
| `saveConfiguration()` gagal           | Melempar `QueryExecutionException` |
| Custom config key tidak ditemukan     | `getCustomConfig()` return default |

---

## Best Practices

### Kapan Memanggil saveConfiguration()

```php
// ✅ Baik: Simpan setelah konfigurasi awal
$users->setSchema([...]);
$users->useSoftDeletes(true);
$users->saveConfiguration();

// ✅ Baik: Simpan setelah perubahan konfigurasi yang penting
$users->setSearchableFields(['email'], true);
$users->saveConfiguration();

// ❌ Buruk: Simpan di setiap operasi write
$users->insert($doc);
$users->saveConfiguration(); // Tidak perlu
```

### Keamanan

```php
// ✅ Jangan simpan kunci sensitif di custom config
$users->setCustomConfig('api_key', 'secret-123'); // ❌ Buruk
$users->setCustomConfig('api_key_set', true);    // ✅ Baik - simpan status saja

// ✅ Gunakan .env atau vault untuk kunci sensitif
$users->setEncryptionKey(getenv('ENCRYPTION_KEY')); // Dari environment
```

### Organisasi Custom Config

```php
// Gunakan prefix untuk mengorganisasi custom config
$users->setCustomConfigArray([
    'app.description'    => 'User collection',
    'app.owner'          => 'team-backend',
    'limits.max_docs'    => 10000,
    'limits.max_size_mb' => 500,
    'cache.enabled'      => true,
    'cache.ttl'          => 3600,
    'audit.enabled'      => true,
    'audit.retention_days' => 90,
]);
```
