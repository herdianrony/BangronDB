# BangronDB Examples

Kumpulan contoh penggunaan BangronDB untuk berbagai skenario.

## Struktur

```
examples/
├── bootstrap.php              # Setup common
├── 01-basic-crud.php         # Operasi CRUD dasar
├── 02-encryption.php          # Enkripsi data (.env)
├── 03-schema-validation.php  # Validasi schema
├── 04-soft-deletes.php       # Soft deletes
├── 05-searchable-fields.php  # Searchable fields
├── 06-hooks.php              # Event hooks
├── 07-relationships.php      # Relasi & population
├── 08-transactions.php       # Transaksi
├── 09-multiple-databases.php # Multiple databases
├── 10-advanced.php           # Semua fitur digabungkan
├── 11-query-operators.php    # Query operators
├── 12-hospital-system.php    # Sistem rumah sakit
├── 13-hospital-complex.php   # Sistem RS multi-database
├── 14-custom-config.php      # Custom config (permissions)
├── 15-encryption-env.php     # Encryption dengan .env
├── 16-computer-store.php     # Computer store & service center
├── 17-config-schema.php       # _Config schema dengan relasi data
└── 18-dynamic-backend.php     # Dynamic backend dengan hasMany/belongsTo
```

## Menjalankan Contoh

```bash
# Install dependencies
composer install

# Jalankan contoh
php examples/01-basic-crud.php
php examples/02-encryption.php
# ... dan seterusnya
```

## Deskripsi Contoh

### 01-basic-crud.php

Demonstrasi operasi Create, Read, Update, Delete dasar.

### 02-encryption.php

Enkripsi AES-256-CBC untuk data sensitif per-collection.
**Key dari .env - TIDAK disimpan di database.**

### 03-schema-validation.php

Validasi schema dokumen dengan berbagai rules.
**Wajib panggil `saveConfiguration()` agar schema persist.**

### 04-soft-deletes.php

Soft delete dengan kemampuan restore dan force delete.

### 05-searchable-fields.php

Field pencarian dengan hashing untuk privasi.

### 06-hooks.php

Event hooks untuk intercept operasi (before/after).
**Hooks tidak persist - daftar ulang setiap session.**

### 07-relationships.php

Relasi antar collections dengan populate.

### 08-transactions.php

Transaksi untuk atomic operations.

### 09-multiple-databases.php

Pengelolaan multiple databases dalam satu client.

### 10-advanced.php

Kombinasi semua fitur dalam satu contoh.

### 11-query-operators.php

Semua query operators MongoDB-like.

### 12-hospital-system.php

Sistem manajemen rumah sakit dengan:

- Enkripsi data medis
- Schema validation
- Soft deletes
- Hooks untuk audit trail
- Searchable fields

### 13-hospital-complex.php

Sistem rumah sakit multi-database:

- Master DB: Patients, Departments, Rooms
- HR DB: Doctors, Nurses
- Transaction DB: Appointments, Medical Records, Billing

### 14-custom-config.php

Custom configuration untuk:

- Role-based permissions
- Settings kustom
- Persistence ke database

### 15-encryption-env.php

Enkripsi dengan key dari environment variable (.env).

## saveConfiguration()

Untuk fitur berikut, **WAJIB** panggil `saveConfiguration()` agar tersimpan:

| Fitur              | Contoh                                                             |
| ------------------ | ------------------------------------------------------------------ |
| Schema validation  | `$users->setSchema([...]); $users->saveConfiguration();`           |
| Encryption enabled | `$users->setEncryptionKey($key); $users->saveConfiguration();`     |
| Searchable fields  | `$users->setSearchableFields([...]); $users->saveConfiguration();` |
| Soft deletes       | `$users->useSoftDeletes(true); $users->saveConfiguration();`       |
| Custom config      | `$users->setCustomConfig(...); $users->saveConfiguration();`       |

**Catatan:**

- `saveConfiguration()` menyimpan ke tabel `_config` di database
- Encryption key TIDAK disimpan - harus dari .env/vault eksternal
- Hooks TIDAK persist - daftar ulang di bootstrap

## Database Isolation

Setiap contoh menggunakan database isolated dengan path unik:

```php
$client = createIsolatedClient('nama_contoh');
```

Database akan otomatis dibersihkan setelah contoh selesai (kecuali contoh yang menggunakan path tetap untuk testing persistence).

## Production Usage

Untuk production, setup collection di file terpisah dan jalankan sekali:

```php
// setup.php - jalankan saat deploy
$client = new Client($path, ['encryption_key' => $_ENV['DB_ENCRYPTION_KEY']]);
$db = $client->selectDB('app');

$users = $db->users;
$users->setSchema([...]);
$users->setEncryptionKey($_ENV['DB_ENCRYPTION_KEY']);
$users->saveConfiguration(); // WAJIB
```

Lihat [`docs/configuration-workflow.md`](../docs/configuration-workflow.md) untuk detail lebih lanjut.
