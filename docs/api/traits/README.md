# Traits API Reference

Dokumentasi lengkap untuk semua trait yang digunakan di BangronDB.

## Daftar Traits

| Trait                                                    | Deskripsi                        | File                      |
| -------------------------------------------------------- | -------------------------------- | ------------------------- |
| [EncryptionTrait](traits/EncryptionTrait.md)             | Enkripsi data dengan AES-256-CBC | EncryptionTrait.php       |
| [HooksTrait](traits/HooksTrait.md)                       | Sistem event hooks               | HooksTrait.php            |
| [IdGeneratorTrait](traits/IdGeneratorTrait.md)           | Generasi ID dokumen              | IdGeneratorTrait.php      |
| [QueryBuilderTrait](traits/QueryBuilderTrait.md)         | Query builder MongoDB-like       | QueryBuilderTrait.php     |
| [SchemaValidationTrait](traits/SchemaValidationTrait.md) | Validasi schema dokumen          | SchemaValidationTrait.php |
| [SearchableFieldsTrait](traits/SearchableFieldsTrait.md) | Field pencarian dengan hashing   | SearchableFieldsTrait.php |
| [SoftDeleteTrait](traits/SoftDeleteTrait.md)             | Soft delete dengan restore       | SoftDeleteTrait.php       |

---

## Penggunaan Traits

Traits digunakan dalam class `Collection` untuk menyediakan fungsionalitas modular:

```php
class Collection
{
    use EncryptionTrait;      // Enkripsi data
    use HooksTrait;          // Event hooks
    use SearchableFieldsTrait; // Field pencarian
    use IdGeneratorTrait;    // Generasi ID
    use QueryBuilderTrait;   // Query builder
    use SchemaValidationTrait; // Validasi schema
    use SoftDeleteTrait;     // Soft delete

    // ...
}
```

---

## EncryptionTrait

Menyediakan enkripsi AES-256-CBC untuk data sensitif.

### Metode Utama

```php
// Set encryption key
public function setEncryptionKey(?string $key): self

// Cek apakah encryption aktif
public function isEncrypted(): bool

// Encrypt data
protected function encodeStored(array $document): string

// Decrypt data
protected function decodeStored(string $data): ?array
```

**Contoh:**

```php
$users->setEncryptionKey('your-32-char-key');
$id = $users->insert(['ssn' => '123-45-6789']); // Auto encrypt
$user = $users->findOne(['_id' => $id]); // Auto decrypt
```

---

## HooksTrait

Menyediakan sistem event hooks untuk intercept operasi.

### Metode Utama

```php
// Daftarkan hook
public function on(string $event, callable $fn): void

// Hapus hook
public function off(string $event, ?callable $fn = null): void

// Ambil semua hooks
public function getHooks(): array
```

### Events Tersedia

- `beforeInsert` - Sebelum insert dokumen
- `afterInsert` - Setelah insert dokumen
- `beforeUpdate` - Sebelum update dokumen
- `afterUpdate` - Setelah update dokumen
- `beforeRemove` - Sebelum hapus dokumen
- `afterRemove` - Setelah hapus dokumen

**Contoh:**

```php
$users->on('beforeInsert', function($doc) {
    $doc['created_at'] = date('c');
    return $doc;
});
```

---

## IdGeneratorTrait

Menyediakan berbagai mode generasi ID dokumen.

### Metode Utama

```php
// Set ID mode
public function setIdMode(string $mode): self

// Get ID mode
public function getIdMode(): string

// Generate ID
protected function ensureDocumentId(array $document): array
```

### ID Modes

- `ID_MODE_AUTO` - UUID v4 otomatis
- `ID_MODE_MANUAL` - Gunakan \_id yang provided
- `ID_MODE_PREFIX` - Generate dengan prefix

**Contoh:**

```php
$users->setIdMode(Collection::ID_MODE_PREFIX);
$users->setIdMode('user:'); // Prefix 'user:'
$id = $users->insert(['name' => 'John']);
// Output: 'user:abc123-def456...'
```

---

## QueryBuilderTrait

Menyediakan query builder dengan operator MongoDB-like.

### Metode Utama

```php
// Find documents
public function find($criteria = null, $projection = null): Cursor

// Find one document
public function findOne($criteria = null, $projection = null): ?array

// Count documents
public function count($criteria = null): int
```

**Contoh:**

```php
$users->find(['status' => 'active'])
    ->sort(['created_at' => -1])
    ->limit(10)
    ->toArray();
```

---

## SchemaValidationTrait

Menyediakan validasi schema dokumen.

### Metode Utama

```php
// Set schema
public function setSchema(array $schema): self

// Get schema
public function getSchema(): array

// Validate document
public function validate(array $document): bool
```

**Contoh:**

```php
$users->setSchema([
    'email' => ['required' => true, 'type' => 'string', 'regex' => '/^[^@\s]+@[^@\s]+\.[^@\s]+$/'],
    'age' => ['type' => 'int', 'min' => 0, 'max' => 150],
    'role' => ['enum' => ['admin', 'user', 'guest']]
]);
```

---

## SearchableFieldsTrait

Menyediakan field pencarian dengan optional hashing untuk privasi.

### Metode Utama

```php
// Set searchable fields
public function setSearchableFields(array $fields, bool $hash = false): self

// Get searchable fields
public function getSearchableFields(): array
```

**Contoh:**

```php
// Hash untuk privasi
$users->setSearchableFields(['email'], true);

// Plain text search
$users->setSearchableFields(['name', 'category'], false);

$user = $users->findOne(['email' => 'john@example.com']);
```

---

## SoftDeleteTrait

Menyediakan soft delete dengan kemampuan restore.

### Metode Utama

```php
// Enable/disable soft deletes
public function useSoftDeletes(bool $enabled = true): self

// Check if soft deletes enabled
public function softDeletesEnabled(): bool

// Get deleted field name
public function getDeletedAtField(): string

// Restore deleted documents
public function restore($criteria): int
```

**Contoh:**

```php
$users->useSoftDeletes(true);

// Soft delete
$users->remove(['_id' => $id]);

// Query aktif saja (default)
$activeUsers = $users->find()->toArray();

// Dengan deleted
$allUsers = $users->find()->withTrashed()->toArray();

// Hanya deleted
$deletedUsers = $users->find()->onlyTrashed()->toArray();

// Restore
$users->restore(['_id' => $id]);
```

---

## Trait Combinations

Traits dapat dikombinasikan untuk fungsionalitas lengkap:

```php
// User collection dengan semua fitur
$users = $db->users;

// Enkripsi
$users->setEncryptionKey('user-key');

// Searchable fields dengan hashing
$users->setSearchableFields(['email'], true);

// Schema validation
$users->setSchema([
    'email' => ['required' => true, 'type' => 'string'],
    'name' => ['required' => true, 'type' => 'string'],
]);

// Soft deletes
$users->useSoftDeletes(true);

// Hooks
$users->on('beforeInsert', function($doc) {
    $doc['created_at'] = date('c');
    return $doc;
});

// Custom ID
$users->setIdMode('user:');

// Simpan konfigurasi
$users->saveConfiguration();
```

---

## Konfigurasi yang Disimpan

Konfigurasi traits disimpan dalam database dan dimuat otomatis:

```php
// Saat collection dibuat
$collection = $db->selectCollection('users');

// Konfigurasi dimuat otomatis dari _config table
// Encryption, schema, searchable fields, dll.
// siap digunakan
```
