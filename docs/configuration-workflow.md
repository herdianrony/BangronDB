# Configuration Workflow

Panduan tentang kapan dan bagaimana menyimpan konfigurasi collection.

## Overview

Di BangronDB, ada dua jenis pengaturan:

1. **Runtime settings** - Berlaku hanya untuk session saat ini
2. **Persistent settings** - Disimpan ke database, bertahan setelah koneksi ditutup

## saveConfiguration()

Method `saveConfiguration()` menyimpan pengaturan collection ke tabel `_config` di database:

```php
$users->setSchema([...]);
$users->setEncryptionKey($key);
$users->saveConfiguration(); // WAJIB untuk persistence
```

### Apa yang Disimpan

| Pengaturan         | Disimpan? | Catatan                               |
| ------------------ | --------- | ------------------------------------- |
| Schema validation  | ✅        | Rules divalidasi setiap insert/update |
| Encryption enabled | ✅        | Boolean (key dari luar)               |
| Searchable fields  | ✅        | Field yang di-hash untuk search       |
| Soft deletes       | ✅        | \_deleted_at field                    |
| Custom config      | ✅        | Permissions, settings, dll            |
| ID mode            | ✅        | auto/manual/prefix                    |

### Apa yang TIDAK Disimpan

| Pengaturan     | Disimpan? | Catatan                        |
| -------------- | --------- | ------------------------------ |
| Encryption key | ❌        | HARUS dari .env/vault          |
| Hooks          | ❌        | Daftar ulang di setiap session |

## Workflow Production

```php
// File: bootstrap.php atau setup.php

// 1. Load key dari .env (sekali saja)
$encryptionKey = $_ENV['DB_ENCRYPTION_KEY'] ?? null;

// 2. Setup collections dengan konfigurasi
function setupCollections(Database $db): void
{
    // Users collection
    $users = $db->users;
    $users->setSchema([...]);
    $users->setEncryptionKey($encryptionKey);
    $users->setSearchableFields(['email'], true);
    $users->useSoftDeletes(true);
    $users->saveConfiguration(); // ⬅️ WAJIB

    // Orders collection
    $orders = $db->orders;
    $orders->setSchema([...]);
    $orders->setEncryptionKey($encryptionKey);
    $orders->saveConfiguration(); // ⬅️ WAJIB
}
```

## Auto-Load Behavior

Ketika collection di-load (via `$db->users`), konfigurasi otomatis di-load dari database:

```php
// Koneksi baru - konfigurasi di-load otomatis
$client = new Client($path, ['encryption_key' => $_ENV['DB_ENCRYPTION_KEY']]);
$db = $client->selectDB('app');
$users = $db->users; // ⬅️ Config di-load otomatis dari _config table
```

## Checklist Persisted Settings

✅ **Panggil `saveConfiguration()` SETELAH:**

- `setSchema()` - Validasi rules
- `setEncryptionKey()` - Enable encryption (key tetap di luar)
- `setSearchableFields()` - Field untuk pencarian
- `useSoftDeletes()` - Enable soft delete
- `setCustomConfig()` - Custom settings (permissions, dll)

❌ **TIDAK perlu saveConfiguration() untuk:**

- `on()` hooks - Daftar ulang di bootstrap
- Database-level encryption key - Dari options saat connect

## Contoh Lengkap

```php
// setup.php - Jalankan sekali saat deploy/migrate
$client = new Client($path, ['encryption_key' => $_ENV['DB_ENCRYPTION_KEY']]);
$db = $client->selectDB('app');

$users = $db->users;
$users->setSchema([
    'name' => ['type' => 'string', 'required' => true],
    'email' => ['type' => 'string', 'required' => true],
]);
$users->setEncryptionKey($_ENV['DB_ENCRYPTION_KEY']);
$users->setSearchableFields(['email'], true);
$users->useSoftDeletes(true);
$users->setCustomConfig('permissions', [...]);
$users->saveConfiguration();

echo "Collection configured and saved!\n";

// app.php - Jalankan setiap request
$client = new Client($path, ['encryption_key' => $_ENV['DB_ENCRYPTION_KEY']]);
$db = $client->selectDB('app');

// Config sudah otomatis di-load
$users = $db->users;

// Hooks perlu di-daftar ulang setiap session
$users->on('beforeInsert', function($doc) {
    $doc['created_at'] = date('c');
    return $doc;
});
```
