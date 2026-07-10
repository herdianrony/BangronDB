# Security Usage Guide

Panduan praktis untuk menggunakan fitur keamanan BangronDB dengan benar.

## Daftar Isi

- [Enkripsi](#enkripsi)
- [Searchable Fields pada Data Terenkripsi](#searchable-fields-pada-data-terenkripsi)
- [Key Rotation](#key-rotation)
- [Validasi & Hardening](#validasi--hardening)
- [Sensitive Config Blocking](#sensitive-config-blocking)
- [Hardening Kit](#hardening-kit)

---

## Enkripsi

Lihat dokumentasi lengkap: [docs/security.md](docs/security.md#enkripsi-aes-256-gcm)

### Aturan Penting

1. **Selalu supply encryption key dari environment**, bukan hardcode:
   ```php
   $collection->setEncryptionKey($_ENV['DB_ENCRYPTION_KEY']);
   ```

2. **Key minimum 32 karakter** — kunci lebih pendek akan ditolak.

3. **Gunakan key version** untuk mendukung key rotation di masa depan:
   ```php
   $collection->setEncryptionKey($key, 'v1-2026');
   ```

4. **Enkripsi bersifat per-collection** — setiap collection bisa memiliki key berbeda.

---

## Searchable Fields pada Data Terenkripsi

Lihat dokumentasi lengkap: [docs/security.md#searchable-fields)

Saat collection dienkripsi, query equality (`findOne(['email' => '...'])`) tidak akan menemukan dokumen karena data tersimpan terenkripsi. Searchable fields membuat blind index terpisah.

### Hash vs Plain

```php
// Hash (HMAC-SHA256) — untuk data sensitif
$collection->setSearchableFields([
    'email' => ['hash' => true],   // tidak bisa di-reverse
    'phone' => ['hash' => true],
]);

// Plain — untuk data non-sensitif tapi tetap di collection terenkripsi
$collection->setSearchableFields([
    'username' => ['hash' => false],
    'category' => ['hash' => false],
]);
```

### Migrasi dari SHA-256 ke HMAC

Jika Anda upgrade dari versi lama yang menggunakan plain SHA-256:

```php
$count = $collection->rehashSearchableField('email');
echo "$count rows rehashed\n";
```

---

## Key Rotation

Lihat dokumentasi lengkap: [docs/security.md#key-rotation)

```php
// 1. Set key lama untuk membaca
$collection->setEncryptionKey($oldKey, 'v1-2026');

// 2. Rotate ke key baru
$rotated = $collection->rotateEncryptionKey($newKey, 'v2-2027');

// 3. Set key baru sebagai active key
$collection->setEncryptionKey($newKey, 'v2-2027');
```

Contoh lengkap: [examples/21-key-rotation.php](examples/21-key-rotation.php)

---

## Validasi & Hardening

BangronDB menerapkan beberapa guardrail secara otomatis:

| Fitur | Detail |
|-------|--------|
| **Closure-only** | `$where` dan `$func` hanya menerima `Closure` — string/array callable ditolak (mencegah RCE) |
| **Field validation** | Nama field divalidasi sebelum digunakan di SQL (mencegah injection) |
| **PRAGMA escaping** | Keyword SQLite di-escape (mencegah SQLite injection) |
| **Regex hardening** | Pola ReDoS otomatis ditolak, panjang maks 500 karakter |
| **Path traversal** | Path database divalidasi terhadap `../` traversal |
| **`declare(strict_types=1)`** | Type safety di seluruh codebase |

Lihat contoh: [examples/18-security-features.php](examples/18-security-features.php)

---

## Sensitive Config Blocking

Field sensitif **tidak bisa** disimpan via `setCustomConfig()`:

```php
// ❌ DITOLAK — throw InvalidArgumentException
$collection->setCustomConfig('encryption_key', 'secret');
$collection->setCustomConfig('password', 'admin123');
$collection->setCustomConfig('api_key', 'sk-xxx');

// ✅ DITERIMA
$collection->setCustomConfig('theme', 'dark');
$collection->setCustomConfig('locale', 'id_ID');
```

**Field yang diblokir:** `encryption_key`, `encryptionkey`, `password`, `passwd`, `secret`, `token`, `api_key`, `apikey`, `private_key`, `credential`.

---

## Hardening Kit

Untuk produksi, lihat `examples/secure-bootstrap/` yang menyediakan `SecureClientFactory` — wrapper untuk memastikan encryption key tidak di-hardcode dan searchable fields dikontrol via allowlist.