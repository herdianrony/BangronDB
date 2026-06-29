# Collection API – BangronDB v1.1.0

`BangronDB\Collection`

## Insert
`insert(array $document = []): mixed`
```php
$id = $users->insert(['name'=>'John','email'=>'john@example.com']);
// Response: "550e8400-e29b-41d4-a716-446655440000"
```
Batch: `$count = $users->insert([['name'=>'Bob'],['name'=>'Ana']]); // 2`

`save(array $document, bool $create = false): mixed` – upsert

## Find
`find(mixed $criteria = null, ?array $projection = null): Cursor`
`findOne(mixed $criteria = null, ?array $projection = null): ?array`
`count(mixed $criteria = null): int`

Response find:
```json
[{"_id":"...","name":"John","email":"john@example.com"}]
```

## Update
`update(mixed $criteria, array $data, bool $merge = true): int`
Operators: `$set, $unset, $inc, $push, $pull`
```php
$users->update(['_id'=>$id], ['$set'=>['role'=>'admin'], '$inc'=>['login_count'=>1]]);
// Response: 1
```

## Remove
`remove(mixed $criteria): int`
`forceDelete(mixed $criteria): int`

## ID Modes
`setIdModeAuto(): self` – UUID v4
`setIdModeManual(): self`
`setIdModePrefix(string $prefix): self`
`getIdMode(): string`

## Schema Validation
`setSchema(array $schema): self`
`getSchema(): array`
`validate(array $document): bool`
Example schema: `['email'=>['type'=>'string','required'=>true,'regex'=>'/^.+@.+\..+$/'], 'age'=>['type'=>'int','min'=>0,'max'=>150], 'role'=>['enum'=>['admin','user']]]`

## Encryption v1.1.0
`setEncryptionKey(?string $key, ?string $keyVersion = null): self`
`getEncryptionKeyVersion(): ?string`
`isEncrypted(): bool`
`rotateEncryptionKey(string $newKey, ?string $newKeyVersion = null): int`
`reencryptAll(): int`

Stored format:
```json
{"_id":"...","encrypted_data":"...","iv":"...","tag":"...","hmac":"...","enc_v":2,"key_v":"v2-2026"}
```

## Searchable Fields
`setSearchableFields(array $fields): self`
`getSearchableFields(): array`
`rehashSearchableField(string $field): int`
```php
$users->setEncryptionKey($key,'v2');
$users->setSearchableFields(['email'=>['hash'=>true]]);
$users->findOne(['email'=>'john@example.com']);
```

## Soft Deletes
`useSoftDeletes(bool $enable = true): self`
`softDeletesEnabled(): bool`
`restore(mixed $criteria): int`
`onlyTrashed(): Cursor`
`withTrashed(): Cursor`

## Hooks
`on(string $event, Closure $callback): self`
`off(string $event, ?Closure $callback = null): self`
Events: `beforeInsert, afterInsert, beforeUpdate, afterUpdate, beforeRemove, afterRemove`
Hooks tidak di-persist.

## Populate / Relations
`populate(array $documents, string $localField, string $foreign, string $foreignField = '_id', ?string $as = null): mixed`

## Indexing
`createIndex(string $field, ?string $indexName = null): void`
`dropIndex(string $indexName): void`
`createJsonIndex(string $field): void`

## Configuration
`saveConfiguration(): void`
`setCustomConfig(string $key, $value): self`
`getCustomConfig(string $key, $default = null)`
`getAllCustomConfig(): array`
`setCustomConfigArray(array $config): self`
> v1.1.0: setCustomConfig akan throw InvalidArgumentException untuk keys: encryption_key, password, secret, token, api_key, private_key, credential

## Other
`drop(): void`
`renameCollection(string $newname): bool`
`validateUnique(string $field, $value, ?string $excludeId = null): bool`
`setMaxDocumentSize(int $bytes): self`
