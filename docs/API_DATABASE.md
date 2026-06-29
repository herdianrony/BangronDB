# Database API – BangronDB v1.1.0

`BangronDB\Database`

## Encryption
```php
$db->setEncryptionKey(?string $key, ?string $keyVersion = null): self
$db->getEncryptionKey(): ?string
$db->getEncryptionKeyVersion(): ?string
$db->isEncryptionEnabled(): bool
$db->getEncryptionKeyStatus(): array
// Response: ['enabled'=>true,'key_length'=>44,'key_version'=>'v2-2026']
```

## Collection Management
`createCollection(string $name): Collection`
`getCollectionNames(): array`
`tableExists(string $table): bool`
`dropCollection(string $name): void`

## Health & Metrics
`getHealthMetrics(): array`
`checkIntegrity(): array`
`getDataMetrics(): array`
`getPerformanceMetrics(): array`
`getHealthReport(): array`
// Response: {"status":"healthy","issues":[],"warnings":[],"timestamp":1719580000}

## Config Persistence
`saveCollectionConfig(string $name, array $config): void`
`loadCollectionConfig(string $name): array`
`getAllCollectionConfigs(): array`
`deleteCollectionConfig(string $name): void`

Stored config includes: `id_mode, encryption_enabled, encryption_key_version, searchable_fields, schema, soft_deletes_enabled, deleted_at_field, custom_config`
> encryption_key **tidak pernah** disimpan.

## Transactions
`executeTransaction(callable $callback): mixed`

## Raw Query
`executeRaw(string $sql, array $params = []): PDOStatement`
`executeRawUpdate(string $sql, array $params = []): int`

## Maintenance
`vacuum(): void`
`close(): void`
`closeAll(): void`
