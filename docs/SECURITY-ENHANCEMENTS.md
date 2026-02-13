# Security Enhancements

BangronDB telah memperbarui keamanan dengan validasi yang lebih ketat untuk melindungi data Anda.

## Encryption Key Validation

### Minimum Requirements

Mulai dari versi terbaru, encryption keys harus memenuhi persyaratan berikut:

- **Minimum 32 characters** untuk AES-256 encryption
- **No repeated patterns** (e.g., "aaaaa..." tidak diperbolehkan)
- **Sufficient entropy** (minimal 25% unique characters)
- **No sequential patterns** (e.g., "012345..." tidak diperbolehkan)

### ✅ Valid Examples

```php
// Strong keys - RECOMMENDED
$collection->setEncryptionKey('my-super-secret-32-chars-encryption-key!');
$collection->setEncryptionKey(bin2hex(random_bytes(32))); // Cryptographically secure
$collection->setEncryptionKey(base64_encode(random_bytes(32)));
```

### ❌ Invalid Examples  

```php
// TOO SHORT - Will throw InvalidArgumentException
$collection->setEncryptionKey('short'); // Only 5 chars

// WEAK PATTERN - Will throw InvalidArgumentException  
$collection->setEncryptionKey('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'); // Repeated
$collection->setEncryptionKey('012345678901234567890123456789'); // Sequential
```

### Error Messages

Ketika key tidak valid, Anda akan menerima error yang jelas:

```
InvalidArgumentException: Encryption key must be at least 32 characters long. 
Provided key is only 10 characters. For AES-256 encryption, use a strong 
random key of at least 32 characters.
```

### Best Practices

**Generate secure keys:**

```php
// Method 1: Using random_bytes (RECOMMENDED)
$key = bin2hex(random_bytes(32));
$collection->setEncryptionKey($key);

// Method 2: Using openssl
$key = base64_encode(openssl_random_pseudo_bytes(32));
$collection->setEncryptionKey($key);

// Method 3: Store in environment variable
// .env file:
// DB_ENCRYPTION_KEY=your-strong-32-char-minimum-key-here!

$collection->setEncryptionKey($_ENV['DB_ENCRYPTION_KEY']);
```

**Never:**
- ❌ Use simple words or phrases
- ❌ Use predictable patterns
- ❌ Hardcode weak keys in source code
- ❌ Share keys in version control

---

## Configuration Value Validation

BangronDB sekarang memvalidasi semua configuration values untuk mencegah kesalahan:

### Page Size Validation

```php
// ✅ Valid - Power of 2 between 512 and 65536
Config::set('page_size', 4096);
Config::set('page_size', 8192);

// ❌ Invalid - Will throw InvalidArgumentException
Config::set('page_size', 5000); // Not power of 2
Config::set('page_size', 100);  // Too small
```

**Valid page_size values:**
512, 1024, 2048, 4096, 8192, 16384, 32768, 65536

### Cache Size Validation

```php
// ✅ Valid - Non-zero integer
Config::set('cache_size', -2048); // Negative = KB
Config::set('cache_size', 1000);  // Positive = pages

// ❌ Invalid
Config::set('cache_size', 0); // Zero not allowed
```

### Enum Validations

**Journal Mode:**
```php
// Valid: DELETE, TRUNCATE, PERSIST, MEMORY, WAL, OFF
Config::set('journal_mode', 'WAL'); // ✅ Recommended

// Invalid
Config::set('journal_mode', 'INVALID'); // ❌ Error
```

**Synchronous Mode:**
```php
// Valid: OFF, NORMAL, FULL, EXTRA
Config::set('synchronous', 'NORMAL'); // ✅ Recommended

// Invalid
Config::set('synchronous', 'AUTO'); // ❌ Error
```

**Auto Vacuum:**
```php
// Valid: NONE, FULL, INCREMENTAL
Config::set('auto_vacuum', 'INCREMENTAL'); // ✅ Recommended

// Invalid
Config::set('auto_vacuum', 'AUTO'); // ❌ Error
```

---

## Memory Safety Limits

Untuk mencegah memory exhaustion, `Cursor::toArray()` memiliki default limit:

### Default Behavior

```php
// Default: Maximum 10,000 documents
$cursor = $collection->find();
$docs = $cursor->toArray(); // Auto-protected

// If query returns more than 10,000 docs:
// RuntimeException: Query would return 50000 documents, exceeding 
// safe limit of 10000. Use limit() or toArraySafe() instead.
```

### Safe Alternative

```php
// Stricter limit (1,000 documents by default)
$docs = $cursor->toArraySafe(); 

// Custom limit
$docs = $cursor->toArraySafe(500); // Max 500 docs
```

### Bypassing Limits (Use with Caution)

```php
// Explicitly set limit for large datasets
$docs = $collection->find()
    ->limit(50000) // Explicit limit bypasses default protection
    ->toArray();

// Better: Use pagination
$page1 = $collection->find()->limit(1000)->skip(0)->toArray();
$page2 = $collection->find()->limit(1000)->skip(1000)->toArray();

// Best: Use iterator for memory efficiency
foreach ($collection->find() as $doc) {
    processDocument($doc); // Process one at a time
}
```

---

## Migration Guide

### Updating Existing Code

Jika Anda menggunakan encryption keys yang pendek, update dengan:

```php
// Before (may fail now)
$collection->setEncryptionKey('mykey');

// After (compliant)
$collection->setEncryptionKey('mykey-extended-to-32-chars-min!');
```

### Updating Tests

Test Anda mungkin perlu update encryption keys:

```php
// Before
public function testEncryption() {
    $collection->setEncryptionKey('test');
    // ...
}

// After  
public function testEncryption() {
    $collection->setEncryptionKey('test-encryption-key-32-chars-min!');
    // ...
}
```

---

## FAQ

**Q: Mengapa minimum 32 characters?**  
A: AES-256 membutuhkan 256-bit key (32 bytes). Menggunakan key yang lebih pendek mengurangi security.

**Q: Bagaimana dengan existing data?**  
A: Data yang sudah terenkripsi tetap valid. Validasi ini hanya untuk new keys.

**Q: Apa yang terjadi jika saya set weak key?**  
A: `InvalidArgumentException` akan thrown sebelum data dienkripsi, melindungi Anda dari security issues.

**Q: Bolehkah disable validation?**  
A: Tidak. Validation ini untuk security Anda dan tidak bisa di-bypass.

---

## See Also

- [Performance & Security Guide](performance-security.md)
- [API Reference: EncryptionTrait](api/traits/EncryptionTrait.md)
- [API Reference: Config](api/Config.md)
- [API Reference: Cursor](api/Cursor.md)
