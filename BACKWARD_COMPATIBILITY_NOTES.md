# Backward Compatibility Notes

Catatan perilaku yang berubah antar versi BangronDB.

---

## Non-lazy `selectDB()` / `selectCollection()`

Mulai versi ini, `selectDB()` dan `selectCollection()` bersifat **non-lazy**: keduanya hanya memilih resource yang sudah ada.

### Perubahan

| Sebelum | Sesudah |
|---------|---------|
| `selectDB('app')` membuat DB jika belum ada | `selectDB('app')` throw `DatabaseException` jika belum ada |
| `selectCollection('users')` membuat collection jika belum ada | `selectCollection('users')` throw `CollectionException` jika belum ada |

### Migrasi

```php
// Sebelum (implicit creation)
$db = $client->selectDB('app');
$users = $db->selectCollection('users');

// Sesudah (explicit creation)
$client->createDB('app');
$client->createCollection('app', 'users');

// Kemudian pilih
$db = $client->selectDB('app');
$users = $db->selectCollection('users');
```

Magic getter (`$client->app`, `$db->users`) tetap menggunakan `selectDB()`/`selectCollection()` di bawahnya — jadi pastikan resource sudah dibuat sebelum mengakses via magic getter.

---

## Searchable Fields: Plain SHA-256 → HMAC-SHA256

Versi 1.2.0 mengubah blind index dari plain SHA-256 menjadi **HMAC-SHA256** (keyed).

### Dampak

- Searchable fields yang dibuat dengan versi lama menggunakan hash yang **berbeda** dari versi baru.
- Query equality pada field searchable yang belum di-migrate **tidak akan menemukan** dokumen lama.

### Migrasi

```php
$count = $collection->rehashSearchableField('email');
echo "$count rows rehashed\n";
```

Lihat juga `examples/secure-bootstrap/migrate_blind_index.php` untuk script migrasi batch.

---

## ID Prefix Normalization

Format konfigurasi ID prefix yang disimpan ke database kini dinormalisasi:

| Sebelum | Sesudah |
|---------|---------|
| `"USR"` (prefix mentah) | `"prefix:USR"` (dengan prefix type marker) |

Konfigurasi lama yang masih menyimpan prefix mentah seperti `USR` tetap bisa **dibaca** saat di-load ulang (backward compatible).

---

## `QueryExecutionException` Hierarchy

`QueryExecutionException` extends `\RuntimeException` (bukan `BangronDBException`). Ini **tidak berubah** — telah demikian sejak awal, tapi beberapa dokumentasi lama mungkin menuliskan sebaliknya.

Jika Anda menangkap exception secara generik:

```php
// ✅ Menangkap semua exception BangronDB + query
try {
    $collection->find(['age' => ['$gte' => 18]])->toArray();
} catch (BangronDBException $e) {
    // Database, Collection, Validation errors
} catch (QueryExecutionException $e) {
    // SQL execution errors (getSql(), getRedactedParams())
}
```