# Changelog – BangronDB v1.1.0
**Security Hardening Release – 2026-06-29**

## Security
### Encryption v2 – AES-256-GCM NIST compliant
- IV: 16-byte → **12-byte**
- Document metadata: `enc_v: 2`, `key_v: string|null`
- `Collection::setEncryptionKey($key, $keyVersion = null)`
- `Database::setEncryptionKey($key, $keyVersion = null)`
- `Collection::rotateEncryptionKey($newKey, $newKeyVersion = null): int`
- `Collection::reencryptAll(): int`

### Sensitive Config Blocking
- `setCustomConfig()` throws `InvalidArgumentException` for: `encryption_key, password, secret, token, api_key, private_key, credential, passwd, encryptionkey, apikey`
- Applies to: `setCustomConfig()`, `setCustomConfigArray()`, `saveConfiguration()`, `loadConfiguration()`
- `CollectionManager`: `encryption_key` removed from validKeys

### Database
- `encryptionKeyVersion` support throughout
- `saveCollectionConfig()` stores `encryption_key_version`

## API Changes
- **BREAKING (security, intentional):** `setCustomConfig('encryption_key', …)` now throws. Use `setEncryptionKey($_ENV['DB_ENCRYPTION_KEY'], $version)` at runtime.
- All other changes backward compatible.
- Encrypted documents v1.0 (IV 16-byte) still decrypt correctly.

## Examples – updated for v1.1.0
- `examples/03-encryption-searchable.php` – use `$_ENV` + key_version
- `examples/10-dynamic-configuration.php` – use `$_ENV` + key_version
- `examples/14-ecommerce-app.php` – use `$_ENV` + key_version
- `examples/15-auth-encrypted.php` – use `$_ENV` + key_version
- **NEW:** `examples/16-key-rotation.php` – Encryption v2, rotateEncryptionKey, reencryptAll, sensitive config blocking
- **NEW:** `examples/secure-bootstrap/` – SecureClientFactory, migrate_blind_index.php, .env.example

## Tests
New: `tests/SecurityValidationTest_v110.php` – 9 tests
- EncryptionV2Uses12ByteIV
- KeyVersionIsStoredAndRetrieved
- RotateEncryptionKey
- ReencryptAll
- CustomConfigBlocksEncryptionKey / SensitiveKeys
- CustomConfigAllowsSafeKeys
- CollectionManagerRejectsEncryptionKeyInConfig
- DatabaseEncryptionKeyVersion

Upstream v1.0.0 suite: 315 tests – needs minor updates for new encrypted document format.

## Documentation
New API Reference – v1.1.0 – split per module:
- `docs/API_REFERENCE.md`
- `docs/API_CLIENT.md`
- `docs/API_DATABASE.md`
- `docs/API_COLLECTION.md`
- `docs/API_CURSOR.md`
- `docs/API_QUERY_OPERATORS.md`
- `docs/API_SECURITY.md`

All methods include signature, example usage, example response JSON.

## Upgrade Guide v1.0 → v1.1
1. Backup `.bangron` files
2. Set key version: `$collection->setEncryptionKey($_ENV['DB_ENCRYPTION_KEY'], 'v2-2026')`
3. Old documents auto-decrypt, new documents use enc_v=2, IV 12-byte
4. Remove any `setCustomConfig('encryption_key', …)` calls – will now throw
5. Key rotation: `$collection->rotateEncryptionKey($newKey, 'v3')`

## Files Changed
- `src/Traits/EncryptionTrait.php` – IV 12-byte, key_version, rotate helpers
- `src/Collection.php` – `ENCRYPTION_VERSION = 2`
- `src/Database.php` – keyVersion support
- `src/Traits/ConfigurationPersistenceTrait.php` – sensitive config filter
- `src/CollectionManager.php` – remove encryption_key from validKeys
- `examples/03,10,14,15` – use $_ENV + key_version
- `examples/16-key-rotation.php` – new
- `examples/secure-bootstrap/` – new
- `tests/SecurityValidationTest_v110.php` – new
- `docs/API_*.md` – new (7 files)

---
Release by: Rony Herdian – herdianrony@gmail.com
