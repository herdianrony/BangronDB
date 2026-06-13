# Panduan Keamanan BangronDB

Ringkasan perubahan keamanan sejak v1.0.1+ untuk mencegah RCE, NoSQL Injection, dan Path Traversal.

## Perubahan Penting

### `$where` dan `$func` hanya menerima Closure

```php
// DIBLOKIR - string function names (risiko RCE)
$collection->find(['status' => ['$where' => 'is_array']]);
$collection->find(['value' => ['$func' => 'strlen']]);

// WAJIB - gunakan Closure/arrow function
$collection->find(['status' => ['$where' => fn($doc) => is_array($doc['status'])]]);
$collection->find(['value' => ['$func' => fn($val) => strlen($val) > 5]]);
```

### Field name validation

```php
// DIIZINKAN: alfanumerik + underscore + hyphen + dot
['user_name' => 'john', 'user-email' => 'a@b.com', 'address.city' => 'NYC']

// DIBLOKIR: karakter berbahaya
["field'; DROP--" => 'value']   // SQL injection attempt
['field" OR "1"="1' => 'val']   // injection
```

### Database path validation

```php
new Database('/data/db.sqlite');     // Aman
new Database('../../etc/passwd');    // DIBLOKIR - path traversal
```

### Encryption key escaping

```php
new Database(':memory:', [
    'encryption_key' => "it's-a-secret"  // Quote otomatis di-escape
]);
```

## Fitur Keamanan

| Fitur | Status |
|-------|--------|
| `$where`/`$func` hanya Closure | Mencegah RCE |
| Field name whitelist | Mencegah NoSQL injection |
| Path validation | Mencegah directory traversal |
| PRAGMA key escaping | Mencegah SQLite injection |
| Regex delimiter escaping | Mencegah ReDoS |
| `strict_types=1` | Type safety |

## FieldValidator Utility

```php
use BangronDB\Security\FieldValidator;

FieldValidator::isValidFieldName('user_name');           // true
FieldValidator::validateFieldName("'; DROP--");          // throws ValidationException
FieldValidator::isSafeCallable(fn($x) => true);          // true
FieldValidator::isSafeCallable('system');                // false
```

## Troubleshooting

**Error: "only accepts Closure objects"**
```php
// Ubah dari:
['$where' => 'is_array']
// Ke:
['$where' => fn($doc) => is_array($doc['field'])]
```

**Error: "Invalid field name"**
```php
// Ubah dari:
['field; DROP' => 'value']
// Ke:
['field_name' => 'value']
```
