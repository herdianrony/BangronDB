---
layout: doc
category: keamanan-api
permalink: /docs/security/
title: "Security"
description: "Enkripsi, blind index, key rotation."
toc: true
edit_on_github: true
prev:
  url: /docs/hook-patterns/
  title: "Hook Patterns"
next:
  url: /docs/framework-integration/
  title: "Framework Integration"
---
# Keamanan

Fitur keamanan BangronDB: enkripsi, searchable fields, key rotation, dan audit.

---

## Enkripsi AES-256-GCM

BangronDB mendukung enkripsi per-collection menggunakan AES-256-GCM dengan PBKDF2-SHA256 (100.000 iterasi).

### Mengaktifkan Enkripsi

```php
use BangronDB\Client;

$client = new Client('/path/to/data');
$db = $client->createDB('secure_app');
$collection = $db->createCollection('users');

// Set encryption key (minimum 32 karakter)
$collection->setEncryptionKey('your-secret-key-at-least-32-chars-long!!');

// Dengan version tracking
$collection->setEncryptionKey($key, 'v2-2026');
```

### Melalui Client Options

```php
$client = new Client('/path/to/data', [
    'encryption_key' => getenv('DB_ENCRYPTION_KEY'),
    'encryption_key_version' => getenv('DB_ENCRYPTION_KEY_VERSION'),
]);
```

### Penggunaan Normal

Setelah enkripsi diaktifkan, semua operasi CRUD bekerja normal — enkripsi/dekripsi terjadi otomatis:

```php
// Insert → otomatis enkripsi
$collection->insert(['name' => 'Alice', 'ssn' => '123-45-6789']);

// Find → otomatis dekripsi
$doc = $collection->findOne(['name' => 'Alice']);
echo $doc['ssn'];  // '123-45-6789' — sudah didekripsi

// Update → otomatis enkripsi ulang
$collection->update(['name' => 'Alice'], ['$set' => ['ssn' => '987-65-4321']]);
```

### Format Dokumen Terenkripsi

Dokumen yang tersimpan di SQLite memiliki format:

```json
{
    "_id": "550e8400-...",
    "enc_v": 2,
    "key_v": "v2-2026",
    "iv": "base64_encoded_12_byte_iv",
    "hmac": "base64_encoded_hmac_sha256",
    "encrypted_data": "base64_encoded_ciphertext"
}
```

| Field | Deskripsi |
|-------|-----------|
| `enc_v` | Versi enkripsi (2 = GCM) |
| `key_v` | Versi key yang digunakan |
| `iv` | Initialization Vector (12 byte, random per dokumen) |
| `hmac` | HMAC-SHA256 untuk integritas |
| `encrypted_data` | Ciphertext AES-256-GCM |

---

## Searchable Fields

### Masalah

Saat data terenkripsi, `findOne(['email' => 'alice@example.com'])` **tidak akan menemukan** dokumen — karena email di database sudah terenkripsi.

### Solusi

Searchable fields membuat kolom indeks terpisah yang bisa dicari **tanpa mendekripsi seluruh database**.

### Hash Mode (HMAC Blind Index — v1.2.0)

```php
$collection->setSearchableFields([
    'email' => ['hash' => true],     // Keyed HMAC-SHA256 blind index
    'phone' => ['hash' => true],
]);
$collection->setEncryptionKey($encryptionKey);

// Insert → kolom si_email dan si_phone otomatis diisi
$collection->insert([
    'name' => 'Alice',
    'email' => 'alice@example.com',
    'phone' => '+62812345678',
]);

// Query → otomatis mencari di kolom si_email
$doc = $collection->findOne(['email' => 'alice@example.com']);
// Found! (hash dihitung dari input, dicocokkan dengan si_email)
```

**Keamanan HMAC Blind Index:**
- Hash **berkunci** (keyed) — menggunakan encryption key sebagai HMAC key
- **Tidak bisa di-reverse** — tidak mungkin mendapatkan email asli dari hash
- **Anti-correlation** — 2 database berbeda dengan user sama menghasilkan hash berbeda
- **Bukan plain SHA-256** — berbeda dari versi lama yang rentan terhadap rainbow table

### Plain Mode

```php
$collection->setSearchableFields([
    'username' => ['hash' => false],  // Lowercase plain text
    'category' => ['hash' => false],
]);
```

### Kapan Pakai Hash vs Plain

| Aspek | Hash (true) | Plain (false) |
|-------|-------------|---------------|
| Privasi | Tinggi | Rendah |
| Reversibility | Tidak bisa | Bisa dibaca |
| Cocok untuk | Email, NIK, SSN, phone | Username, kategori, status |

### Format API

```php
// Format lama (masih didukung)
$collection->setSearchableFields(['email', 'phone'], true);

// Format baru (direkomendasikan)
$collection->setSearchableFields([
    'email' => ['hash' => true],
    'username' => ['hash' => false],
]);
```

### Remove Searchable Field

```php
$collection->removeSearchableField('email');              // Config saja
$collection->removeSearchableField('email', true);        // Config + hapus kolom
```

### Rehash (Migration)

```php
// Setelah upgrade dari plain SHA-256 ke HMAC
$count = $collection->rehashSearchableField('email');
echo "$count rows rehashed\n";
```

---

## Key Rotation

Mengganti encryption key tanpa kehilangan akses ke data lama.

### rotateEncryptionKey

Re-encrypt semua dokumen dengan key baru:

```php
$collection->rotateEncryptionKey($newKey, 'v3-2027');
// Semua dokumen di-dekripsi dengan key lama, lalu dienkripsi dengan key baru
// Return: jumlah dokumen yang di-rotate
```

### reencryptAll

Re-encrypt dengan key yang sama (bump versi saja):

```php
$collection->setEncryptionKey($currentKey, 'v3-2027-01');
$collection->reencryptAll();
// Berguna untuk: refresh metadata, post-migration cleanup
```

### Alur Key Rotation

```php
// 1. Set key lama untuk membaca
$collection->setEncryptionKey($oldKey, 'v2-2026');

// 2. Rotate ke key baru
$collection->rotateEncryptionKey($newKey, 'v3-2027');

// 3. Set key baru sebagai active key
$collection->setEncryptionKey($newKey, 'v3-2027');
```

---

## Security Audit

Audit keamanan untuk memeriksa konfigurasi dan potensi masalah.

```php
// Audit collection
$audit = $collection->securityAudit();

// Audit seluruh database
$audit = $db->securityAudit();
```

### Hasil Audit

Audit mengembalikan array dengan informasi:
- Status enkripsi
- Searchable fields yang terkonfigurasi
- Potensi masalah keamanan
- Rekomendasi perbaikan

---

## Sensitive Config Blocking

BangronDB secara otomatis menolak penyimpanan field sensitif via `setCustomConfig()`:

```php
// ❌ DITOLAK — throw InvalidArgumentException
$collection->setCustomConfig('encryption_key', 'secret');
$collection->setCustomConfig('password', 'admin123');
$collection->setCustomConfig('api_key', 'sk-xxx');
$collection->setCustomConfigArray(['secret' => 'value']);

// ✅ DITERIMA
$collection->setCustomConfig('theme', 'dark');
$collection->setCustomConfig('locale', 'id_ID');
```

**Field yang diblokir:** `encryption_key`, `encryptionkey`, `password`, `passwd`, `secret`, `token`, `api_key`, `apikey`, `private_key`, `credential`.

---

## Field Validation

### SQL Injection Prevention

Semua field name divalidasi sebelum digunakan dalam query SQL:

```php
use BangronDB\Security\FieldValidator;

FieldValidator::validateFieldName('name');          // OK
FieldValidator::validateFieldName('user.name');      // OK (dot notation)
FieldValidator::validateFieldName('name; DROP TABLE');  // Throw!
```

### Callable Validation

Hanya `Closure` yang diizinkan sebagai callable (untuk `$fn`, `$where`):

```php
// ✅ Diterima
['age' => ['$fn' => fn($v) => $v > 18]]

// ❌ Ditolak
['age' => ['$fn' => 'is_numeric']]           // String callable
['age' => ['$fn' => [$this, 'checkAge']]]    // Array callable
```

### Regex Safety

- Panjang regex maksimum: 500 karakter
- Pola ReDoS (catastrophic backtracking) otomatis ditolak
- Pola tanpa delimiter otomatis di-escape dengan `preg_quote()`
- Recursive/subroutine calls ditolak

### Path Traversal Prevention

```php
// Path database divalidasi terhadap directory traversal
$client = new Client('../../etc/');  // Throw ValidationException!
```