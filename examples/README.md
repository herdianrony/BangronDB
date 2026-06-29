# BangronDB Examples

Folder `examples/` berisi contoh penggunaan BangronDB untuk fitur inti sampai skenario aplikasi yang lebih lengkap.

## Daftar Contoh

| # | File | Topik | Sorotan |
|---|------|-------|----------|
| 01 | `01-quick-start-crud.php` | Quick Start & CRUD | insert, find, update, delete, projection, pagination, sorting, save/upsert |
| 02 | `02-query-operators.php` | Query Operators | comparison, logical, array operators, regex, Closure, fuzzy search, dot notation |
| 03 | `03-encryption-searchable.php` | Enkripsi & Searchable Fields | AES-256-GCM, hashed/plain searchable fields, runtime key |
| 04 | `04-schema-validation.php` | Schema Validation | required, type, enum, regex, min/max, validasi sebelum insert/update |
| 05 | `05-soft-deletes.php` | Soft Deletes | soft delete, restore, force delete, `withTrashed()`, `onlyTrashed()` |
| 06 | `06-hooks.php` | Hooks | before/after insert, update, remove, veto, chaining |
| 07 | `07-relationships-populate.php` | Relationships / Populate | populate tunggal, nested, array reference, cross-database |
| 08 | `08-transactions.php` | Transactions | beginTransaction, commit, rollback, atomic workflow |
| 09 | `09-indexing-health-monitoring.php` | Indexing & Monitoring | index, metrics, health report, integrity check, change notification |
| 10 | `10-dynamic-configuration.php` | Dynamic Configuration | `saveConfiguration()`, auto-load config, custom config |
| 11 | `11-multiple-databases.php` | Multiple Databases | multi database, isolasi data, cross-database populate |
| 12 | `12-id-modes-collection-management.php` | ID Modes & Collection Management | UUID, manual ID, prefix ID, rename, drop |
| 13 | `13-security-features.php` | Security | Closure-only operators, field validation, path safety, PRAGMA escaping |
| 14 | `14-ecommerce-app.php` | Real-World Example | schema, hooks, encryption, searchable fields, soft deletes, transactions, populate |
| 15 | `15-auth-encrypted.php` | Authentication Example | data auth terenkripsi, query aman, praktik penyimpanan secret |
| 16 | `16-key-rotation.php` | Encryption Key Rotation – v1.2.0 | AES-256-GCM v2 (IV 12-byte), key_version, rotateEncryptionKey(), reencryptAll(), sensitive config blocking |

## Cara Menjalankan

Semua example kini menggunakan API eksplisit untuk lifecycle resource:

- `createDB()` untuk membuat database
- `createCollection()` untuk membuat collection
- `selectDB()` / `selectCollection()` hanya untuk resource yang sudah ada

### Migration note: lazy -> non-lazy

Jika Anda sebelumnya mengandalkan pola lama seperti ini:

```php
$db = $client->selectDB('app');
$users = $db->selectCollection('users');
```

Sekarang gunakan pola eksplisit:

```php
$client->createDB('app');
$client->createCollection('app', 'users');

$db = $client->selectDB('app');
$users = $db->selectCollection('users');
```

Atau langsung dari level client:

```php
$client->createDB('app');
$users = $client->createCollection('app', 'users');
```

Pastikan dependency sudah terpasang:

```bash
composer install
```

Jalankan salah satu example:

```bash
php examples/01-quick-start-crud.php
```

Atau dengan FrankenPHP:

```bash
frankenphp php-cli examples/01-quick-start-crud.php
```

## Catatan Penting

### 1. `saveConfiguration()` untuk persist konfigurasi

Jika Anda ingin konfigurasi collection tetap tersimpan setelah aplikasi dibuka ulang, panggil:

```php
$collection->setSchema([...]);
$collection->setSearchableFields([...]);
$collection->useSoftDeletes(true);
$collection->saveConfiguration();
```

Tanpa `saveConfiguration()`, konfigurasi tersebut hanya berlaku di runtime saat ini.

Untuk collection dengan prefix ID, format yang dipersist sekarang dinormalisasi menjadi `prefix:USR`. Konfigurasi lama yang masih menyimpan prefix mentah seperti `USR` tetap bisa dibaca.

### 2. Enum bersifat strict dan `$in` / `$nin` hanya menerima item scalar

- validasi `enum` membedakan `0`, `false`, dan `'0'`
- item pada `$in` / `$nin` harus berupa nilai scalar
- nested array pada `$in` / `$nin` akan ditolak eksplisit

### 3. Encryption key tidak disimpan di database

Selalu supply key dari environment atau secret manager:

```php
$collection->setEncryptionKey($_ENV['DB_ENCRYPTION_KEY']);
```

### 4. Hooks tidak dipersist

Hooks harus didaftarkan ulang setiap startup / request:

```php
$collection->on('beforeInsert', function ($doc) {
    $doc['created_at'] = date('c');
    return $doc;
});
```

### 5. Example adalah panduan, bukan benchmark mutlak

Beberapa example menekankan kejelasan alur. Untuk production, Anda tetap perlu menyesuaikan:

- struktur folder
- manajemen secret
- penanganan exception
- logging
- strategi backup
