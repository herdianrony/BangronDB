# Traits Overview

BangronDB menggunakan beberapa Traits untuk menyusun fungsionalitas Collection. Berikut adalah ringkasan semua Traits yang tersedia:

## Daftar Traits

### EncryptionTrait

**File:** `src/Traits/EncryptionTrait.php`
**Fungsi:** Enkripsi dan dekripsi dokumen menggunakan AES-256-CBC
**Metode Utama:**

- `setEncryptionKey(?string $key)`
- `isEncrypted(): bool`
- `encodeStored(array $doc): string`
- `decodeStored(string $stored): ?array`

### HooksTrait

**File:** `src/Traits/HooksTrait.php`
**Fungsi:** Event hooks untuk operasi CRUD
**Event Hooks:**

- `beforeInsert`, `afterInsert`
- `beforeUpdate`, `afterUpdate`
- `beforeRemove`, `afterRemove`
  **Metode Utama:**
- `on(string $event, callable $fn)`
- `off(string $event, ?callable $fn)`

### IdGeneratorTrait

**File:** `src/Traits/IdGeneratorTrait.php`
**Fungsi:** Generasi ID otomatis dengan berbagai mode
**Mode ID:**

- `auto`: UUID v4
- `manual`: ID manual
- `prefix`: ID dengan prefix counter
  **Metode Utama:**
- `setIdModeAuto()`
- `setIdModeManual()`
- `setIdModePrefix(string $prefix)`
- `setPrefix(string $prefix)`
- `setSuffix(string $suffix)`

### QueryBuilderTrait

**File:** `src/Traits/QueryBuilderTrait.php`
**Fungsi:** Membangun query SQL dari kriteria MongoDB-like
**Operator Didukung:**

- `$gt`, `$gte`, `$lt`, `$lte`
- `$in`, `$nin`
- `$exists`
- `$and`, `$or`
  **Metode Utama:** Internal SQL building methods

### SchemaValidationTrait

**File:** `src/Traits/SchemaValidationTrait.php`
**Fungsi:** Validasi dokumen terhadap schema yang didefinisikan
**Validasi Didukung:**

- Tipe data (string, int, float, bool, array, object)
- Required fields
- Enum values
- Min/max length atau nilai
- Regex patterns
  **Metode Utama:**
- `setSchema(array $schema)`
- `validate(array $document): bool`

### SearchableFieldsTrait

**File:** `src/Traits/SearchableFieldsTrait.php`
**Fungsi:** Field pencarian untuk dokumen terenkripsi
**Fitur:**

- Index terpisah untuk field sensitif
- Opsi hashing untuk privacy
- Query pada data terenkripsi
  **Metode Utama:**
- `setSearchableFields(array $fields, bool $hash)`
- `removeSearchableField(string $field)`
- `_computeSearchIndexValues(array $doc): array`

### SoftDeleteTrait

**File:** `src/Traits/SoftDeleteTrait.php`
**Fungsi:** Soft delete dengan flag deletion timestamp
**Fitur:**

- Mark deleted instead of actual deletion
- Query filtering for trashed records
- Restore functionality
  **Metode Utama:**
- `useSoftDeletes(bool $enabled)`
- `restore($criteria): int`
- `forceDelete($criteria): int`

## Penggunaan dalam Collection

Collection menggunakan semua Traits ini untuk menyediakan API lengkap:

```php
class Collection {
    use EncryptionTrait;
    use HooksTrait;
    use SearchableFieldsTrait;
    use IdGeneratorTrait;
    use QueryBuilderTrait;
    use SchemaValidationTrait;
    use SoftDeleteTrait;
}
```

## Contoh Konfigurasi Lengkap

```php
$collection = $db->selectCollection('users');

// Enkripsi
$collection->setEncryptionKey('secret-key');

// Hooks
$collection->on('beforeInsert', function($doc) {
    $doc['created_at'] = time();
    return $doc;
});

// Searchable fields
$collection->setSearchableFields(['email'], true); // hashed

// ID generation
$collection->setIdModePrefix('USR');

// Schema validation
$collection->setSchema([
    'name' => ['type' => 'string', 'required' => true],
    'email' => ['type' => 'string', 'regex' => '/@.+/'],
    'age' => ['type' => 'int', 'min' => 0, 'max' => 150]
]);

// Soft deletes
$collection->useSoftDeletes(true);
```

## File Dokumentasi Detail

- [EncryptionTrait](traits/EncryptionTrait.md)
- [HooksTrait](traits/HooksTrait.md)
- [IdGeneratorTrait](traits/IdGeneratorTrait.md)
- [QueryBuilderTrait](traits/QueryBuilderTrait.md)
- [SchemaValidationTrait](traits/SchemaValidationTrait.md)
- [SearchableFieldsTrait](traits/SearchableFieldsTrait.md)
- [SoftDeleteTrait](traits/SoftDeleteTrait.md)
