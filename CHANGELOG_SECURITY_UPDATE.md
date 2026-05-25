# Security Update - BangronDB v1.0.1

**Release Date**: March 25, 2026  
**Status**: STABLE ✅

---

## 🔐 Security Improvements

### Critical Vulnerabilities Fixed

#### 1. **RCE Prevention** [CRITICAL]

- ✅ Operator `$where` sekarang hanya menerima Closure (bukan string function names)
- ✅ Operator `$func`/`$fn`/`$f` sekarang hanya menerima Closure
- ✅ Mencegah eksekusi kode seperti `'system'`, `'exec'`, `'shell_exec'`

**Migration**:

```php
// Lama (diblokir): ['$where' => 'is_array']
// Baru (aman):    ['$where' => fn($doc) => is_array($doc['field'])]
```

#### 2. **NoSQL Injection Prevention** [HIGH]

- ✅ Field names sekarang divalidasi dengan whitelist alfanumerik
- ✅ Mencegah injection melalui nama field: `"field'; DROP--"`
- ✅ Aturan: hanya `a-z`, `A-Z`, `0-9`, `_`, `-`, `.` diizinkan

#### 3. **PRAGMA Key Injection Prevention** [HIGH]

- ✅ Encryption key di-escape dengan benar untuk SQLite
- ✅ Rejection untuk karakter kontrol

#### 4. **Path Traversal Prevention** [MEDIUM]

- ✅ Database path divalidasi dengan `realpath()`
- ✅ Mencegah `../../etc/passwd` attacks
- ✅ Support optional base path restriction

#### 5. **Regex Delimiter Injection Prevention** [MEDIUM]

- ✅ Forward slash dalam raw regex patterns di-escape
- ✅ Mencegah pemutusan delimiter regex

#### 6. **Error Suppression Removal** [MEDIUM]

- ✅ Menghapus semua `@` error suppression operator
- ✅ Explicit error handling di semua operasi JSON decode

#### 7. **Type Safety** [MEDIUM]

- ✅ `declare(strict_types=1)` ditambahkan ke 17 file core
- ✅ Type coercion errors akan langsung terdeteksi
- ✅ Type safety untuk semua public methods

---

## 📝 Breaking Changes

### Operator `$where` - PERUBAHAN BREAKING

**Operator ini sekarang HANYA menerima Closure:**

```php
// ❌ INI TIDAK BERFUNGSI LAGI
$users = $collection->find([
    'status' => ['$where' => 'is_array']  // ERROR!
]);

// ✅ GUNAKAN INI SEBALIKNYA
$users = $collection->find([
    'status' => ['$where' => fn($doc) => is_array($doc['status'])]
]);
```

### Operator `$func`/`$fn`/`$f` - PERUBAHAN BREAKING

**Operator ini sekarang HANYA menerima Closure:**

```php
// ❌ INI TIDAK BERFUNGSI LAGI
$data = $collection->find([
    'value' => ['$func' => 'strlen']  // ERROR!
]);

// ✅ GUNAKAN INI SEBALIKNYA
$data = $collection->find([
    'value' => ['$func' => fn($val) => strlen($val) > 5]
]);
```

### Field Namen Validation - PERUBAHAN BREAKING

**Nama field sekarang divalidasi dengan whitelist:**

```php
// ❌ TIDAK DIIZINKAN
['field"with"quotes' => 'value']        // ERROR
['field; DROP--' => 'value']            // ERROR
['field(with)parens' => 'value']        // ERROR
['field<with>tags' => 'value']          // ERROR

// ✅ DIIZINKAN
['field_with_underscore' => 'value']    // OK
['field-with-hyphen' => 'value']        // OK
['field.with.dots' => 'value']          // OK
['field123' => 'value']                 // OK
```

---

## ✨ New Features

### 1. **FieldValidator Utility Class**

```php
use BangronDB\Security\FieldValidator;

// Check field name validity
FieldValidator::isValidFieldName('user_name');  // true
FieldValidator::isValidFieldName("'; DROP--");  // false

// Validate with exception
FieldValidator::validateFieldName('safe_field');  // OK
FieldValidator::validateFieldName("bad'; field");  // throws ValidationException

// Validate callable safety
FieldValidator::isSafeCallable(fn($x) => true);   // true
FieldValidator::isSafeCallable('system');         // false
```

### 2. **Comprehensive Security Tests**

- 36 new security validation tests
- Coverage untuk semua attack vectors
- All tests passing ✅

---

## 📊 Test Results

```
PHPUnit 9.6.34 by Sebastian Bergmann and contributors.

Tests:      273 / 273 (100%)  ✅ ALL PASSED
- Existing:  237 tests
- Security:   36 tests (NEW)

Assertions: 810
Time:       ~4.9 seconds
Memory:     8.00 MB

Result: OK
```

---

## 📚 Documentation

Created comprehensive guides:

1. **[SECURITY_USAGE_GUIDE.md](./SECURITY_USAGE_GUIDE.md)** - User guide untuk perubahan keamanan
2. **[SECURITY_HARDENING_REPORT.md](./SECURITY_HARDENING_REPORT.md)** - Technical report detail

---

## 🔄 Migration Guide

### For Users with `$where` / `$func` operators:

#### Before:

```php
$users = $collection->find([
    'verified' => ['$where' => 'is_array'],
    'score' => ['$func' => 'floatval']
]);
```

#### After:

```php
$users = $collection->find([
    'verified' => ['$where' => fn($doc) => is_array($doc['verified'])],
    'score' => ['$func' => fn($val) => floatval($val) > 0]
]);
```

### For Users with Custom Field Names:

#### Before:

```php
$collection->find([
    'user"name' => 'john',
    'email; hack' => 'john@example.com'
]);
```

#### After:

```php
$collection->find([
    'user_name' => 'john',
    'email_address' => 'john@example.com'
]);
```

---

## 🎯 Backward Compatibility

✅ **100% backward compatible** untuk kode yang:

- Tidak menggunakan string function names dalam `$where`/`$func`
- Menggunakan field names dengan karakter alfanumerik saja
- Tidak mengandalkan implicit behavior dari error suppression

⚠️ **Requires code change** untuk:

- Code yang menggunakan `['$where' => 'function_name']`
- Code yang menggunakan `['$func' => 'function_name']`
- Code dengan field names mengandung special characters

---

## 🚀 Installation

```bash
composer update herdianrony/bangrondb
```

Tidak ada perubahan konfigurasi atau setup yang diperlukan. Update otomatis akan menerapkan semua security fixes.

---

## 📖 Related Documentation

- 📖 [SECURITY_USAGE_GUIDE.md](./SECURITY_USAGE_GUIDE.md) - Complete usage guide
- 📖 [SECURITY_HARDENING_REPORT.md](./SECURITY_HARDENING_REPORT.md) - Technical details
- 📖 [README.md](./README.md) - General documentation
- 🧪 [tests/SecurityValidationTest.php](./tests/SecurityValidationTest.php) - Test cases

---

## ✅ Checklist untuk Developers

- [ ] Read [SECURITY_USAGE_GUIDE.md](./SECURITY_USAGE_GUIDE.md)
- [ ] Update any `$where` operators to use Closures
- [ ] Update any `$func` operators to use Closures
- [ ] Audit field names for special characters
- [ ] Run tests: `vendor/bin/phpunit`
- [ ] Update unit tests if needed
- [ ] Deploy with confidence ✨

---

## 🔗 Issue/Support

Untuk pertanyaan atau issue:

1. Baca [SECURITY_USAGE_GUIDE.md](./SECURITY_USAGE_GUIDE.md) terlebih dahulu
2. Check [tests/SecurityValidationTest.php](./tests/SecurityValidationTest.php) untuk contoh
3. Buka issue dengan detail lengkap

---

**Version**: 1.0.1  
**Release Date**: March 25, 2026  
**Status**: Stable ✅  
**Security Audit**: ✅ Complete

---

_This security update is a critical improvement to protect against RCE, injection, and path traversal attacks._
