# Security API – BangronDB v1.1.0

## FieldValidator
`BangronDB\Security\FieldValidator`

- `validateFieldName(string $fieldName): void` – whitelist `[A-Za-z0-9_.-]`, max 255
- `validateDatabasePath(string $path, ?string $basePath = null): string` – cegah path traversal
- `sanitizeSchemaRegexPattern(string $pattern): string` – ReDoS protection
- `validateSafeCallable($value, string $operatorName): void` – `$where`/`$func` hanya Closure
- `escapePragmaKey(string $key): string`

## Encryption v2
- Cipher: AES-256-GCM
- Key derivation: PBKDF2-SHA256, 100k iter, 32 byte
- IV: **12 byte random** (NIST), decrypt legacy 16-byte OK
- Auth: GCM tag + HMAC-SHA256, `hash_equals()`
- Salt: per-database random
- Document: `enc_v: 2`, `key_v: string|null`

```php
$collection->setEncryptionKey($_ENV['DB_ENCRYPTION_KEY'], 'v2-2026');
$collection->rotateEncryptionKey($newKey, 'v3'); // return int
$collection->reencryptAll(); // return int
```

## Searchable Encrypted Fields – Blind Index
```php
$collection->setSearchableFields(['email'=>['hash'=>true]]);
```
`si_email = HMAC-SHA256(strtolower(email), searchKey)`
`searchKey = PBKDF2(encryption_key, salt="searchindex:")`

Migration:
```php
$collection->rehashSearchableField('email');
```

## Custom Config – Sensitive Key Blocking v1.1.0
```php
$col->setCustomConfig('encryption_key', 'x');
// InvalidArgumentException: Custom config key 'encryption_key' is forbidden
```
Blocked keys: `encryption_key, encryptionkey, password, passwd, secret, token, api_key, apikey, private_key, credential`

Applies to: `setCustomConfig()`, `setCustomConfigArray()`, `saveConfiguration()`, `loadConfiguration()`

## Secure Bootstrap
```php
$db->setEncryptionKey($_ENV['DB_ENCRYPTION_KEY'], 'v2-2026');
$users->setSearchableFields(['email'=>['hash'=>true]]); // minimal only
```
Jangan hardcode key, jangan commit `.env`, jangan over-expose searchable fields.

See: `SECURITY_USAGE_GUIDE.md`, `examples/13-security-features.php`, `examples/15-auth-encrypted.php`, `examples/16-key-rotation.php`
