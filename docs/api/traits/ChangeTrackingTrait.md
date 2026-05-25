# ChangeTrackingTrait API Reference

Trait untuk mengelola notifikasi perubahan dan pelacakan versi collection.

## Trait Overview

```php
trait BangronDB\Traits\ChangeTrackingTrait
{
    // Methods
    public function notifyChange(): void
    public function getLastModified(): array
}
```

## Deskripsi

ChangeTrackingTrait menyediakan mekanisme pelacakan perubahan data pada collection menggunakan tabel metadata (`_meta`). Setiap operasi yang mengubah data collection akan menaikkan nomor versi, sehingga sistem eksternal dapat mendeteksi kapan data telah dimodifikasi.

---

## Metode

### `notifyChange(): void`

Memberitahu bahwa collection telah berubah dengan menaikkan version counter di tabel `_meta`.

Metode ini otomatis dipanggil oleh operasi write (insert, update, remove) pada collection. Tidak perlu dipanggil secara manual dalam penggunaan normal.

**Parameters:**

Tidak ada parameter.

**Return:** `void`

**Behavior:**

1. Memeriksa apakah metadata sudah ada di tabel `_meta` untuk collection ini
2. Mengambil nomor versi saat ini (default: `0`)
3. Menaikkan versi sebesar 1
4. Menyimpan versi baru beserta timestamp ISO 8601 ke tabel `_meta`

**Database Schema (_meta table):**

```json
{
  "_id": "users",
  "version": 42,
  "last_updated": "2024-01-15T10:30:00+00:00"
}
```

**Error Handling:**

- Metode ini gagal secara diam-diam (*silently fail*) jika tabel `_meta` belum siap atau terjadi masalah database lainnya
- Tidak melempar exception ke caller

**Examples:**

```php
// Otomatis dipanggil saat operasi write
$users->insert(['name' => 'John', 'email' => 'john@example.com']);
// notifyChange() dipanggil secara internal

$users->update(['_id' => $userId], ['$set' => ['name' => 'Jane']]);
// notifyChange() dipanggil secara internal

$users->remove(['_id' => $userId]);
// notifyChange() dipanggil secara internal
```

---

### `getLastModified(): array`

Mengambil nomor versi dan timestamp terakhir dari metadata collection.

Mengembalikan informasi versi saat ini yang dapat digunakan untuk membandingkan apakah data telah berubah sejak terakhir kali diperiksa.

**Parameters:**

Tidak ada parameter.

**Return:** `array{version: int, last_updated: string|null}`

| Key            | Type        | Deskripsi                                        |
| -------------- | ----------- | ------------------------------------------------ |
| `version`      | `int`       | Nomor versi saat ini. Default: `0`              |
| `last_updated` | `string\|null` | Timestamp ISO 8601 terakhir kali data berubah. Default: `null` |

**Return Values:**

- Jika metadata ditemukan: Mengembalikan array dengan `version` dan `last_updated` dari database
- Jika metadata tidak ditemukan: Mengembalikan `['version' => 0, 'last_updated' => null]`
- Jika terjadi error database: Mengembalikan `['version' => 0, 'last_updated' => null]`

**Examples:**

```php
$users = $db->selectCollection('users');

// Cek versi terakhir
$info = $users->getLastModified();
echo "Version: {$info['version']}";
echo "Last updated: {$info['last_updated']}";
// Output:
// Version: 42
// Last updated: 2024-01-15T10:30:00+00:00

// Cek jika collection belum pernah diubah
$empty = $db->selectCollection('empty_collection');
$info = $empty->getLastModified();
// ['version' => 0, 'last_updated' => null]
```

---

## Implementation Details

### Alur Kerja notifyChange()

```
notifyChange()
    │
    ├─ Query: SELECT document FROM _meta WHERE json_extract(document, '$._id') = ?
    │
    ├─ Metadata ada?
    │   ├─ YA → Ambil version saat ini, increment +1
    │   │      UPDATE _meta SET document = ? WHERE id = ?
    │   │
    │   └─ TIDAK → Set version = 1
    │              INSERT INTO _meta (document) VALUES (?)
    │
    └─ Error? → Silent fail (tidak melempar exception)
```

### Alur Kerja getLastModified()

```
getLastModified()
    │
    ├─ Query: SELECT document FROM _meta WHERE json_extract(document, '$._id') = ?
    │
    ├─ Hasil ditemukan?
    │   ├─ YA → Parse JSON, return version & last_updated
    │   │
    │   └─ TIDAK → return ['version' => 0, 'last_updated' => null]
    │
    └─ Error? → return ['version' => 0, 'last_updated' => null]
```

### Tabel _meta

ChangeTrackingTrait menggunakan tabel `_meta` yang tersedia di setiap database BangronDB:

| Column    | Type    | Deskripsi                               |
| --------- | ------- | --------------------------------------- |
| `id`      | integer | Auto-increment primary key              |
| `document` | text    | JSON document berisi metadata collection |

---

## Usage Examples

### Polling untuk Perubahan Data

```php
$users = $db->selectCollection('users');

// Simpan versi awal
$lastCheck = $users->getLastModified();
$lastVersion = $lastCheck['version'];

// ... lakukan operasi lain ...

// Cek apakah ada perubahan
$current = $users->getLastModified();
if ($current['version'] > $lastVersion) {
    echo "Data telah berubah sejak terakhir dicek!";
    echo "Versi: {$lastVersion} → {$current['version']}";
    echo "Terakhir diubah: {$current['last_updated']}";
}
```

### Cache Invalidation

```php
class UserCache
{
    private int $cachedVersion = 0;
    private array $cache = [];

    public function getUsers($collection): array
    {
        $info = $collection->getLastModified();

        if ($info['version'] !== $this->cachedVersion) {
            // Cache invalid, refresh data
            $this->cache = $collection->find()->toArray();
            $this->cachedVersion = $info['version'];
        }

        return $this->cache;
    }
}

$cache = new UserCache();
$users = $cache->getUsers($db->users); // Fetch dari DB
$users = $cache->getUsers($db->users); // Dari cache (versi sama)
```

### Synchronization Antar Aplikasi

```php
// Aplikasi A - menulis data
$users = $db->selectCollection('users');
$users->insert(['name' => 'New User']);
// Version naik ke N+1

// Aplikasi B - mengecek perubahan
$users = $db->selectCollection('users');
$latest = $users->getLastModified();

if ($latest['version'] > $myLastKnownVersion) {
    // Pull perubahan dari database
    $changedDocs = $users->find([
        'modified_at' => ['$gte' => $myLastCheckTime]
    ])->toArray();
}
```

### Monitoring Perubahan

```php
function monitorCollectionChanges($collection, $intervalSeconds = 60) {
    $lastVersion = 0;

    while (true) {
        $info = $collection->getLastModified();

        if ($info['version'] > $lastVersion) {
            $changes = $info['version'] - $lastVersion;
            echo "[" . date('H:i:s') . "] {$changes} perubahan terdeteksi";
            echo " Versi: {$lastVersion} → {$info['version']}";
            echo " Terakhir: {$info['last_updated']}\n";
            $lastVersion = $info['version'];
        }

        sleep($intervalSeconds);
    }
}

// Monitor collection users setiap 30 detik
monitorCollectionChanges($db->users, 30);
```

---

## Integration with Other Features

### Dengan Hooks

```php
// notifyChange() dipanggil setelah hooks afterInsert, afterUpdate, afterRemove
$users->on('afterInsert', function($doc) {
    echo "Dokumen diinsert, version akan naik";
});

$users->on('afterUpdate', function($doc) {
    echo "Dokumen diupdate, version akan naik";
});
```

### Dengan ConfigurationPersistenceTrait

```php
// Change tracking bekerja bersama config persistence
$users = $db->selectCollection('users');

// Konfigurasi dimuat (mempengaruhi internal state)
// Version tracking tetap berjalan terpisah
$users->saveConfiguration();

// Cek kapan terakhir kali ada perubahan
$info = $users->getLastModified();
```

### Dengan Soft Delete

```php
$users->useSoftDeletes(true);

// Soft delete juga menaikkan version
$users->remove(['_id' => $userId]);
// Version naik

$info = $users->getLastModified();
// ['version' => 43, 'last_updated' => '2024-01-15T10:35:00+00:00']
```

---

## Performance Considerations

### Overhead

- `notifyChange()` melakukan 1-2 query tambahan per operasi write (SELECT + INSERT/UPDATE)
- `getLastModified()` melakukan 1 query SELECT

### Rekomendasi

| Situasi                          | Dampak                         |
| -------------------------------- | ------------------------------ |
| High-frequency writes            | Overhead minimal (optimistic)  |
| Read-heavy dengan polling        | Gunakan interval yang wajar    |
| Multiple application instances   | Cocok untuk cache invalidation |

---

## Error Handling

| Skenario                        | Behavior                       |
| ------------------------------- | ------------------------------ |
| Tabel `_meta` belum ada         | Silent fail, tidak ada error   |
| Query execution gagal           | Silent fail, tidak ada error   |
| Metadata tidak ditemukan        | `getLastModified()` return default |
| Concurrent version updates      | SQLite handle secara serial    |

---

## Best Practices

### Penggunaan yang Direkomendasikan

✅ **Baik untuk:**

- Cache invalidation pada aplikasi
- Deteksi perubahan data antar service
- Monitoring dan audit trail
- Synchronization data

❌ **Tidak ideal untuk:**

- Tracking per-dokumen (gunakan field `updated_at` di dokumen)
- Rate limiting berdasarkan perubahan
- Sebagai pengganti event system yang kompleks

### Tips

```php
// Simpan version terakhir di session/cache
$_SESSION['users_version'] = $users->getLastModified()['version'];

// Bandingkan saat request berikutnya
if ($users->getLastModified()['version'] > $_SESSION['users_version']) {
    // Refresh data
}
```
