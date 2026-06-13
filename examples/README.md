# BangronDB Examples

Kumpulan contoh penggunaan BangronDB yang mencakup semua fitur dan berbagai skenario.

## Daftar Contoh

| # | File | Topik | Fitur yang Dicover |
|---|------|-------|--------------------|
| 01 | `01-quick-start-crud.php` | Quick Start & CRUD | insert, find, update, delete, pagination, sorting, projection, save/upsert |
| 02 | `02-query-operators.php` | Query Operators | $gt, $gte, $lt, $lte, $ne, $in, $nin, $and, $or, $has, $all, $size, $regex, $not, $exists, $where, $func, $fuzzy, dot notation |
| 03 | `03-encryption-searchable.php` | Enkripsi & Searchable | AES-256 encryption, searchable fields (hashed/plain), encryption key dari .env |
| 04 | `04-schema-validation.php` | Schema Validation | type, required, enum, regex, min/max, array constraints, validate(), update validation |
| 05 | `05-soft-deletes.php` | Soft Deletes | soft delete, restore, force delete, withTrashed, onlyTrashed |
| 06 | `06-hooks.php` | Hooks (Events) | beforeInsert, afterInsert, beforeUpdate, afterUpdate, beforeRemove, afterRemove, veto, chaining |
| 07 | `07-relationships-populate.php` | Relationships | single populate, array references, multi-level, cross-database, cursor-based |
| 08 | `08-transactions.php` | Transactions | beginTransaction, commit, rollback, batch insert, atomic operations |
| 09 | `09-indexing-health-monitoring.php` | Indexing & Monitoring | createIndex, dropIndex, health metrics, health report, performance, VACUUM, change notification |
| 10 | `10-dynamic-configuration.php` | Dynamic Configuration | saveConfiguration, custom config, permissions, auto-load, persistence |
| 11 | `11-multiple-databases.php` | Multiple Databases | multi-DB, data isolation, cross-DB populate, attach/detach |
| 12 | `12-id-modes-collection-management.php` | ID Modes & Management | UUID auto, manual ID, prefix ID, renameCollection, dropCollection |
| 13 | `13-security-features.php` | Security | Closure-only $where/$func, field validation, path traversal, PRAGMA escaping, FieldValidator |
| 14 | `14-ecommerce-app.php` | Real-World App | E-commerce: schema, hooks, encryption, searchable, soft deletes, transactions, populate, monitoring |

## Menjalankan

```bash
composer install

# Jalankan satu contoh
php examples/01-quick-start-crud.php

# Atau dengan FrankenPHP
frankenphp php-cli examples/01-quick-start-crud.php
```

## Catatan Penting

### saveConfiguration()
Untuk fitur berikut, **WAJIB** panggil `saveConfiguration()` agar konfigurasi tersimpan di database:

```php
$collection->setSchema([...]);
$collection->setSearchableFields([...]);
$collection->useSoftDeletes(true);
$collection->saveConfiguration(); // WAJIB
```

### Encryption Key
Encryption key **TIDAK** disimpan di database. Selalu sediakan dari `.env` atau secret manager:

```php
$collection->setEncryptionKey($_ENV['DB_ENCRYPTION_KEY']);
```

### Hooks Tidak Persist
Hooks harus didaftarkan ulang setiap session (tidak disimpan di database):

```php
// Daftar di bootstrap/setiap request
$collection->on('beforeInsert', function($doc) { ... });
```
