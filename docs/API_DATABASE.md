# Database API – BangronDB v1.2.0

`BangronDB\Database`

---

## __construct

```php
public function __construct(string $path = ':memory:', array $options = [])
```
Biasanya via `Client::createDB()`.

**Options**
- `encryption_key string|null`
- `encryption_key_version string|null`  // v1.2.0
- `base_path string` – untuk path confinement

---

## Encryption

```php
public function getEncryptionKey(): ?string
public function getEncryptionKeyVersion(): ?string   // v1.2.0
public function setEncryptionKey(?string $key, ?string $keyVersion = null): self
public function isEncryptionEnabled(): bool
public function getEncryptionKeyStatus(): array
```

**Example Request**
```php
$db->setEncryptionKey($_ENV['DB_KEY'], 'v2-2026');
$status = $db->getEncryptionKeyStatus();
```

**Response**
```json
{
  "enabled": true,
  "key_length": 44,
  "key_version": "v2-2026"
}
```

---

## Collection Management

```php
public function createCollection(string $name): Collection
public function getCollectionNames(): array
public function tableExists(string $table): bool
public function dropCollection(string $name): void
```

**Example Response `getCollectionNames()`**
```json
["users","orders","products"]
```

---

## Health & Metrics

```php
public function getHealthMetrics(): array
public function checkIntegrity(): array
public function getDataMetrics(): array
public function getPerformanceMetrics(): array
public function getIndexMetrics(): array
public function getCollectionMetrics(): array
public function getHealthReport(): array
```

**Example Response `getHealthReport()`**
```json
{
  "status": "healthy",
  "issues": [],
  "warnings": [],
  "recommendations": [],
  "timestamp": 1719580000
}
```

**Example Response `getHealthMetrics()`**
```json
{
  "database": {
    "size_bytes": 24576,
    "page_count": 6,
    "encryption_enabled": true
  },
  "integrity": {"status": "healthy"},
  "performance": {"fragmentation_ratio": 0.02},
  "collections": {
    "users": {"documents": 124, "size_bytes": 8192}
  }
}
```

---

## Collection Config Persistence

```php
public function saveCollectionConfig(string $collectionName, array $config): void
public function loadCollectionConfig(string $collectionName): array
public function getAllCollectionConfigs(): array
public function deleteCollectionConfig(string $collectionName): void
```

**Stored config JSON**
```json
{
  "id_mode": "auto",
  "encryption_enabled": true,
  "encryption_key_version": "v2-2026",
  "searchable_fields": {"email": true},
  "schema": {},
  "soft_deletes_enabled": false,
  "deleted_at_field": "_deleted_at",
  "custom_config": {},
  "created_at": 1719580000,
  "updated_at": 1719580000
}
```
> `encryption_key` **tidak pernah** disimpan. Hanya `encryption_enabled` + `encryption_key_version`.

---

## Metadata

```php
public function touchCollectionMetadata(string $collectionName): array
public function getCollectionMetadata(string $collectionName): array
public function getAllCollectionMetadata(): array
```

**Response**
```json
{"version": 5, "last_updated": "2026-06-29T10:00:00+07:00"}
```

---

## Transactions

```php
public function executeTransaction(callable $callback): mixed
```

**Example Request**
```php
$db->executeTransaction(function($db) use ($users, $orders) {
  $uid = $users->insert(['name'=>'Ana']);
  $orders->insert(['user_id'=>$uid, 'total'=>99]);
  return $uid;
});
```
**Response**
```
"550e8400-e29b-41d4-a716-446655440000"
```
Jika callback throw, otomatis rollback.

---

## Raw Query

```php
public function executeRaw(string $sql, array $params = []): PDOStatement
public function executeRawUpdate(string $sql, array $params = []): int
```

---

## Maintenance

```php
public function vacuum(): void
public function close(): void
public static function closeAll(): void
```
