# Collection API – BangronDB v1.1.0

`BangronDB\Collection`

---

## Insert

### insert
```php
public function insert(array $document = []): mixed
```

**Request**
```php
$id = $users->insert([
  'name' => 'John Doe',
  'email' => 'john@example.com',
  'role' => 'admin'
]);
```
**Response**
```
"550e8400-e29b-41d4-a716-446655440000"
```
`_id` UUID v4 auto-generated.

**Batch insert – Request**
```php
$count = $users->insert([
  ['name'=>'Bob'],
  ['name'=>'Charlie']
]);
```
**Response**
```
2
```

### save
```php
public function save(array $document, bool $create = false): mixed
```
Upsert – jika `_id` ada → update, jika tidak → insert.

**Response**: `_id` string / int

---

## Find

### find
```php
public function find(mixed $criteria = null, ?array $projection = null): Cursor
```

**Request**
```php
$cursor = $users->find(['role' => 'admin']);
$rows = $cursor->toArray();
```

**Response**
```json
[
  {"_id":"550e8400-e29b-41d4-a716-446655440000", "name":"John Doe", "email":"john@example.com", "role":"admin"},
  {"_id":"660f9511-f39c-52e5-b827-557766551111", "name":"Ana", "email":"ana@example.com", "role":"admin"}
]
```

### findOne
```php
public function findOne(mixed $criteria = null, ?array $projection = null): ?array
```

**Response – found**
```json
{"_id":"550e8400-e29b-41d4-a716-446655440000", "name":"John Doe", "email":"john@example.com"}
```
**Response – not found**
```
null
```

### count
```php
public function count(mixed $criteria = null): int
```

**Example**
```php
$users->count(['role'=>'admin']);
// Response: 2
```

---

## Update

```php
public function update(mixed $criteria, array $data, bool $merge = true): int
```

Operators: `$set`, `$unset`, `$inc`, `$push`, `$pull`

**Request**
```php
$updated = $users->update(
  ['_id' => $id],
  ['$set' => ['role' => 'superadmin'], '$inc' => ['login_count' => 1]]
);
```

**Response**
```
1
```

**Document setelah update**
```json
{"_id":"550e8400-e29b-41d4-a716-446655440000", "name":"John Doe", "role":"superadmin", "login_count":1}
```

---

## Remove

```php
public function remove(mixed $criteria): int
public function forceDelete(mixed $criteria): int   // bypass soft delete
```

**Request**
```php
$deleted = $users->remove(['_id' => $id]);
```

**Response**
```
1
```

---

## ID Modes

```php
public function setIdModeAuto(): self      // UUID v4, default
public function setIdModeManual(): self    // pakai _id dari user
public function setIdModePrefix(string $prefix): self
public function getIdMode(): string
```

**Example – prefix mode – Request**
```php
$orders->setIdModePrefix('ORD-');
$id = $orders->insert(['total'=>99]);
```

**Response `_id`**
```
"ORD-550e8400e29b41d4"
```

---

## Schema Validation

```php
public function setSchema(array $schema): self
public function getSchema(): array
public function validate(array $document): bool
```

**Request**
```php
$users->setSchema([
  'email' => ['type'=>'string', 'required'=>true, 'regex'=>'/^.+@.+\..+$/'],
  'age' => ['type'=>'int', 'min'=>0, 'max'=>150],
  'role' => ['enum'=>['admin','user','editor']]
]);
```

**Invalid insert – Response**
```
ValidationException: Field 'email' is required
ValidationException: Field 'age' must be >= 0
ValidationException: Field 'role' must be one of: admin, user, editor (strict)
```

Enum bersifat strict: `0 !== false !== '0'`

---

## Encryption – v1.1.0

```php
public function setEncryptionKey(?string $key, ?string $keyVersion = null): self
public function getEncryptionKeyVersion(): ?string
public function isEncrypted(): bool
public function rotateEncryptionKey(string $newKey, ?string $newKeyVersion = null): int
public function reencryptAll(): int
```

**Stored document – encrypted – Response (raw DB)**
```json
{
  "_id":"550e8400-e29b-41d4-a716-446655440000",
  "encrypted_data":"Base64(AES-256-GCM)...",
  "iv":"Base64...",
  "tag":"Base64...",
  "hmac":"7a4f...",
  "enc_v": 2,
  "key_v": "v2-2026"
}
```
- IV: **12 byte** (NIST SP 800-38D), decrypt legacy 16-byte OK
- Cipher: AES-256-GCM
- Key derivation: PBKDF2-SHA256, 100k iter
- Integrity: GCM auth tag + HMAC-SHA256, `hash_equals()`

**Rotate – Request**
```php
$users->setEncryptionKey($oldKey, 'v1');
$count = $users->rotateEncryptionKey($newKey, 'v2');
```

**Response**
```
124
```
Jumlah dokumen yang berhasil di-re-encrypt.

**reencryptAll – Request**
```php
$users->setEncryptionKey($key, 'v2-rotated');
$count = $users->reencryptAll();
```
**Response**
```
124
```

---

## Searchable Fields (Blind Index)

```php
public function setSearchableFields(array $fields): self
public function getSearchableFields(): array
public function rehashSearchableField(string $field): int
```

**Request – setup**
```php
$users->setEncryptionKey($_ENV['DB_ENCRYPTION_KEY'], 'v2');
$users->setSearchableFields([
  'email' => ['hash' => true],
  'phone' => ['hash' => true]
]);
```

**Query – Request**
```php
$user = $users->findOne(['email' => 'john@example.com']);
```

**Response**
```json
{"_id":"550e8400-e29b-41d4-a716-446655440000", "name":"John Doe", "email":"john@example.com"}
```

Blind index di DB: `si_email = HMAC-SHA256(strtolower(email), searchKey)`  
`searchKey = PBKDF2(encryption_key, salt="searchindex:", 100k)`

**Migration v1.0 → v1.1.0 – Request**
```php
$users->rehashSearchableField('email');
```
**Response**
```
124
```
rows updated – upgrade SHA-256 plain → HMAC keyed

---

## Soft Deletes

```php
public function useSoftDeletes(bool $enable = true): self
public function softDeletesEnabled(): bool
public function setDeletedAtField(string $field): self
public function getDeletedAtField(): string
public function restore(mixed $criteria): int
public function onlyTrashed(): Cursor
public function withTrashed(): Cursor
```

**Example Request**
```php
$users->useSoftDeletes(true);
$users->remove(['_id'=>$id]); // soft delete, set _deleted_at = time()
$users->find()->toArray(); // tidak termasuk yang ter-delete
$trashed = $users->onlyTrashed()->find()->toArray();
$restored = $users->restore(['_id'=>$id]);
```

**Response restore**
```
1
```

---

## Hooks

```php
public function on(string $event, Closure $callback): self
public function off(string $event, ?Closure $callback = null): self
public function getHooks(string $event): array
```

Events: `beforeInsert, afterInsert, beforeUpdate, afterUpdate, beforeRemove, afterRemove`

**Request**
```php
$users->on('beforeInsert', function(&$doc){
  $doc['created_at'] = time();
  return $doc;
});
```

**Response – Hook terdaftar**
```php
$users->getHooks('beforeInsert'); // [Closure, ...]
```

> Hooks **tidak di-persist** ke database – daftar ulang tiap bootstrap/request.

---

## Relationships / Populate

```php
public function populate(array $documents, string $localField, string $foreign, string $foreignField = '_id', ?string $as = null): mixed
```

**Request**
```php
$orders = $ordersCol->find(['user_id' => '550e8400-...'])->toArray();
$populated = $usersCol->populate($orders, 'user_id', 'users', '_id', 'user');
```

**Response**
```json
[
  {
    "_id":"ord_123",
    "total":99,
    "user_id":"550e8400-e29b-41d4-a716-446655440000",
    "user": {
      "_id":"550e8400-e29b-41d4-a716-446655440000",
      "name":"John Doe",
      "email":"john@example.com"
    }
  }
]
```

Support populate antar-collection dan antar-database dalam satu client.

---

## Indexing

```php
public function createIndex(string $field, ?string $indexName = null): void
public function dropIndex(string $indexName): void
public function createJsonIndex(string $field): void
```

**Request**
```php
$users->createIndex('email', 'idx_users_email');
$users->dropIndex('idx_users_email');
```

---

## Configuration Persistence

```php
public function saveConfiguration(): void
```

Menyimpan: `id_mode, encryption_enabled, encryption_key_version, searchable_fields, schema, soft_deletes_enabled, deleted_at_field, custom_config`

```php
public function setCustomConfig(string $key, $value): self
public function getCustomConfig(string $key, $default = null)
public function getAllCustomConfig(): array
public function setCustomConfigArray(array $config): self
```

**v1.1.0 Security – Blocked keys – Request**
```php
$col->setCustomConfig('encryption_key', 'secret123');
```

**Response**
```
InvalidArgumentException: Custom config key 'encryption_key' is forbidden - sensitive credentials must not be persisted. Provide encryption keys at runtime via setEncryptionKey() / $_ENV.
```

Blocked keys (case-insensitive): `encryption_key, encryptionkey, password, passwd, secret, token, api_key, apikey, private_key, credential`

**Valid custom config – Request**
```php
$col->setCustomConfig('theme', 'dark');
$col->setCustomConfig('locale', 'id_ID');
$col->saveConfiguration();
```

**Response – getCustomConfig**
```php
$col->getCustomConfig('theme'); // "dark"
$col->getAllCustomConfig(); // ['theme'=>'dark','locale'=>'id_ID']
```

---

## Lain-lain

```php
public function drop(): void
public function count(mixed $criteria = null): int
public function renameCollection(string $newname): bool
public function validateUnique(string $field, $value, ?string $excludeId = null): bool
public function setMaxDocumentSize(int $bytes): self
public function getMaxDocumentSize(): int   // default 10485760 (10MB)
```

**validateUnique – Response**
```php
$users->validateUnique('email', 'john@example.com'); // true = unique, false = sudah ada
```
