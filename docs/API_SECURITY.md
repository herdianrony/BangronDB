# Security API – BangronDB v1.2.0

`BangronDB\Security\FieldValidator` + Encryption v2

---

## FieldValidator

```php
use BangronDB\Security\FieldValidator;
```

### validateFieldName

```php
public static function validateFieldName(string $fieldName): void
// throws ValidationException
```

Whitelist: `[A-Za-z0-9_.-]`, max 255 chars, tolak `' " \` ; ( ) { } [ ] < > \ \n \r \0`

**Valid**
```
user_name
user-email
address.city
```

**Invalid – Response: throw**
```
field'; DROP TABLE users;--
field" OR "1"="1
field(name)
```

### validateDatabasePath

```php
public static function validateDatabasePath(string $path, ?string $basePath = null): string
```

Cegah path traversal `../`, pakai `realpath()`, confinement ke basePath.

**Valid Request**
```php
FieldValidator::validateDatabasePath(__DIR__.'/data/app.bangron');
// Response: "/var/www/app/data/app.bangron"
```

**Invalid – Response**
```
ValidationException: Database path '../../etc/passwd' contains disallowed parent-directory traversal segments
```

### sanitizeSchemaRegexPattern

```php
public static function sanitizeSchemaRegexPattern(string $pattern): string
```

ReDoS protection: max 500 chars, tolak nested quantifiers, backref numerik, recursion, lookbehind. Pattern berbahaya auto-downgrade ke literal `preg_quote()`.

**Example**
```php
$pattern = FieldValidator::sanitizeSchemaRegexPattern('/^(a+)+$/');
// Response: '/\^\(a\+\)\+\$\//u'   // di-quote, jadi literal match, aman
```

### validateSafeCallable

```php
public static function validateSafeCallable($value, string $operatorName = 'operator'): void
```

`$where` / `$func` hanya terima `Closure`. String function name seperti `system`, `exec` → throw.

**Valid Request**
```php
FieldValidator::validateSafeCallable(fn($d)=>$d['x']>1, 'where');
// Response: void (OK)
```

**Invalid – Response**
```php
FieldValidator::validateSafeCallable('system', 'where');
// ValidationException: The 'where' operator only accepts Closure objects (anonymous functions). String function names like 'system', 'exec', etc. are not allowed. Example: ['where' => fn($doc) => $doc['field'] > 10]
```

### escapePragmaKey

```php
public static function escapePragmaKey(string $key): string
```

Escape key untuk SQLite PRAGMA, tolak control chars `\x00-\x1F`.

---

## Encryption v2 – AES-256-GCM

`BangronDB\Traits\EncryptionTrait`

| Item | v1.2.0 |
|---|---|
| Cipher | AES-256-GCM |
| Key derivation | PBKDF2-SHA256, 100k iter, 32 byte |
| IV / nonce | **12 byte random** (NIST SP 800-38D), decrypt legacy 16-byte OK |
| Auth tag | GCM tag + HMAC-SHA256 extra, `hash_equals()` |
| Salt | Per-database random, stored in `_crypto.kdf_salt` |
| Document version | `enc_v: 2` |
| Key version | `key_v: string|null` |

### setEncryptionKey

```php
// Collection
public function setEncryptionKey(?string $key, ?string $keyVersion = null): self
// Database
public function setEncryptionKey(?string $key, ?string $keyVersion = null): self
```

**Example Request**
```php
$users->setEncryptionKey($_ENV['DB_ENCRYPTION_KEY'], 'v2-2026-06');
```

Key validation:
- minimal 32 karakter
- tolak weak key: repeated chars, sequential `0123456789`, `abcdefghij`, `qwerty`, entropy < 25%

**Invalid – Response**
```
InvalidArgumentException: Encryption key must be at least 32 characters long. Provided key is only 8 characters.
InvalidArgumentException: Encryption key appears to be weak. Avoid using simple patterns...
```

### rotateEncryptionKey

```php
public function rotateEncryptionKey(string $newKey, ?string $newKeyVersion = null): int
```
Decrypt semua dokumen dengan key lama, re-encrypt dengan key baru.

**Request**
```php
$count = $users->rotateEncryptionKey($newKey, 'v3');
```

**Response**
```
124
```

### reencryptAll

```php
public function reencryptAll(): int
```
Re-encrypt dengan key/version saat ini.

**Response**
```
124
```

---

## Searchable Encrypted Fields – Blind Index

```php
$collection->setEncryptionKey($key, 'v2');
$collection->setSearchableFields([
  'email' => ['hash' => true],
  'phone' => ['hash' => true]
]);
```

**Stored blind index**
```
si_email = HMAC-SHA256(strtolower(email), searchKey)
searchKey = PBKDF2(encryption_key, salt="searchindex:", 100k)
```

Keamanan:
- Tanpa key → tidak bisa brute-force / rainbow table
- Cross-DB correlation blocked (key berbeda → hash berbeda)
- Tanpa encryption_key → fallback SHA-256 plain (backward compat, data memang tidak rahasia)

**Migration – Request**
```php
$users->rehashSearchableField('email');
```

**Response**
```
124
```
rows updated – upgrade SHA-256 plain → HMAC keyed

---

## Custom Config – Sensitive Key Blocking (v1.2.0)

```php
$col->setCustomConfig('theme', 'dark'); // OK
```

**Blocked – Request**
```php
$col->setCustomConfig('encryption_key', 'secret123');
```

**Response**
```
InvalidArgumentException: Custom config key 'encryption_key' is forbidden - sensitive credentials must not be persisted. Provide encryption keys at runtime via setEncryptionKey() / $_ENV.
```

Blocked keys (case-insensitive):
`encryption_key, encryptionkey, password, passwd, secret, token, api_key, apikey, private_key, credential`

Berlaku di:
- `setCustomConfig()`
- `setCustomConfigArray()`
- `saveConfiguration()` – auto filter
- `loadConfiguration()` – auto filter

---

## Secure Bootstrap – Recommended

**.env**
```
DB_ENCRYPTION_KEY=openssl_rand_base64_48_chars_min
DB_ENCRYPTION_KEY_VERSION=v2-2026-06
DB_DATA_PATH=./data
```

**bootstrap.php – Request**
```php
$client = new Client($_ENV['DB_DATA_PATH'], [
  'encryption_key' => $_ENV['DB_ENCRYPTION_KEY'],
  'encryption_key_version' => $_ENV['DB_ENCRYPTION_KEY_VERSION'],
  'query_logging' => false,
  'performance_monitoring' => false,
]);

$users = $client->selectCollection('app','users');
$users->setEncryptionKey($_ENV['DB_ENCRYPTION_KEY'], $_ENV['DB_ENCRYPTION_KEY_VERSION']);
$users->setSearchableFields(['email'=>['hash'=>true]]); // minimal only
```

**Jangan:**
- hardcode key di source
- commit `.env` ke git
- jadikan banyak field sensitif sebagai searchable
- simpan encryption_key di `custom_config` / `saveConfiguration()`

---

Lihat lengkap: `SECURITY_USAGE_GUIDE.md`, `SECURITY_FIXES.md`, `examples/13-security-features.php`, `examples/15-auth-encrypted.php`, `examples/16-key-rotation.php`
