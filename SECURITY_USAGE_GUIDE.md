# 🔐 Panduan Keamanan BangronDB - Update Fitur Query Operators

**Status**: Dokumentasi resmi untuk perubahan keamanan di BangronDB v1.0.1+

---

## 📍 Ringkasan Perubahan

Sejak update keamanan (Maret 2026), BangronDB telah mengimplementasikan validasi ketat pada operator query untuk mencegah **Remote Code Execution (RCE)** dan **NoSQL Injection**.

### Yang Berubah:

1. ✅ Operator `$where` dan `$func` sekarang **hanya menerima Closure** (anonymous functions)
2. ✅ Nama field dalam query divalidasi dengan whitelist alfanumerik
3. ✅ Password database dienkripsi lebih aman
4. ✅ Semua file kode menggunakan `strict types` untuk type safety

### Yang Tetap Sama:

- Semua fitur lain berfungsi normal
- API yang sudah berjalan tetap kompatibel (kecuali yang disebutkan di atas)
- Performa tidak berubah

---

## 🚨 Operator `$where` - Perubahan PENTING

### ❌ Cara Lama (Sekarang DIBLOKIR)

```php
// INI TIDAK BERFUNGSI LAGI - Akan throw ValidationException
$users = $collection->find([
    'email' => ['$where' => 'is_array']  // ❌ String tidak diizinkan!
]);

// INI JUGA TIDAK BERFUNGSI
$users = $collection->find([
    'status' => ['$where' => 'strlen']  // ❌ Bahaya RCE
]);
```

**Mengapa diubah?**  
String seperti `'system'`, `'exec'`, `'shell_exec'` bisa digunakan untuk serangan RCE (Remote Code Execution) yang sangat berbahaya.

### ✅ Cara Baru (HARUS DIGUNAKAN)

Gunakan **Closure (arrow function atau anonymous function)**:

```php
// ✅ BENAR - Menggunakan arrow function
$users = $collection->find([
    'age' => ['$where' => fn($doc) => $doc['age'] > 18]
]);

// ✅ BENAR - Menggunakan anonymous function
$users = $collection->find([
    'status' => ['$where' => function($document) {
        return $document['status'] === 'active' &&
               isset($document['verified']);
    }]
]);

// ✅ BENAR - Complex logic
$users = $collection->find([
    'email' => ['$where' => fn($doc) =>
        strpos($doc['email'], '@gmail.com') !== false &&
        strlen($doc['email']) > 10
    ]
]);
```

### 📝 Contoh Migrasi

**Sebelum:**

```php
$results = $collection->find([
    'value' => ['$where' => 'is_array']
]);
```

**Sesudah:**

```php
$results = $collection->find([
    'value' => ['$where' => fn($doc) => is_array($doc['value'])]
]);
```

---

## 🚨 Operator `$func` - Perubahan PENTING

### ❌ Cara Lama (Sekarang DIBLOKIR)

```php
// INI TIDAK BERFUNGSI LAGI - Akan throw ValidationException
$users = $collection->find([
    'name' => ['$func' => 'strlen']  // ❌ String tidak diizinkan!
]);

$users = $collection->find([
    'data' => ['$func' => 'json_decode']  // ❌ Bahaya RCE
]);
```

### ✅ Cara Baru (HARUS DIGUNAKAN)

Gunakan **Closure:**

```php
// ✅ BENAR - Operator $func dengan Closure
$users = $collection->find([
    'name' => ['$func' => fn($val) => strlen($val) > 5]
]);

// ✅ BENAR - Complex transformation
$users = $collection->find([
    'tags' => ['$func' => function($value) {
        if (is_string($value)) {
            $tags = json_decode($value, true);
            return is_array($tags) && count($tags) > 0;
        }
        return false;
    }]
]);

// ✅ BENAR - Shorthand $fn
$results = $collection->find([
    'amount' => ['$fn' => fn($val) => $val > 1000]
]);

// ✅ BENAR - Shorthand $f
$results = $collection->find([
    'count' => ['$f' => fn($val) => $val % 2 === 0]  // Genap?
]);
```

### 📝 Contoh Migrasi

**Sebelum:**

```php
$results = $collection->find([
    'price' => ['$func' => 'floatval'],
    'items' => ['$func' => 'count']
]);
```

**Sesudah:**

```php
$results = $collection->find([
    'price' => ['$func' => fn($val) => floatval($val) > 0],
    'items' => ['$func' => fn($val) => is_array($val) && count($val) > 0]
]);
```

---

## ✅ Operator `$where` dan `$func` - Contoh Valid

### Contoh 1: Filter Umur

```php
// Temukan pengguna dengan umur > 18
$adults = $collection->find([
    'age' => ['$where' => fn($doc) => $doc['age'] > 18]
]);
```

### Contoh 2: Validasi Email

```php
// Temukan pengguna dengan email Gmail
$gmailUsers = $collection->find([
    'email' => ['$where' => fn($doc) =>
        preg_match('/@gmail\.com$/', $doc['email'] ?? '')
    ]
]);
```

### Contoh 3: Transformasi Nilai

```php
// Hanya data dimana nama > 5 karakter
$longNames = $collection->find([
    'name' => ['$func' => fn($val) => strlen($val ?? '') > 5]
]);
```

### Contoh 4: Validasi Tipe Data

```php
// Data dengan field tertentu berupa array
$results = $collection->find([
    'tags' => ['$func' => fn($val) => is_array($val) && count($val) > 0]
]);
```

### Contoh 5: Logika Boolean Kompleks

```php
// Pengguna premium yang aktif dalam 7 hari terakhir
$activeVips = $collection->find([
    'status' => ['$where' => function($doc) {
        $isPremium = ($doc['tier'] ?? 'free') === 'premium';
        $lastActive = strtotime($doc['last_active'] ?? 0);
        $weekAgo = time() - (7 * 24 * 60 * 60);

        return $isPremium && $lastActive > $weekAgo;
    }]
]);
```

---

## 📋 Validasi Nama Field - Aturan Baru

Operator query sekarang juga memvalidasi nama field menggunakan whitelist keamanan.

### ✅ Nama Field yang DIIZINKAN

```php
// Alfanumerik + underscore + hyphen + dot
$collection->find([
    'user_name' => 'john',        // ✅ underscore
    'user-email' => 'john@x.com', // ✅ hyphen
    'address.city' => 'Jakarta',  // ✅ dot notation
    'phone123' => '081234567',    // ✅ numbers
    '_private' => 'value',        // ✅ underscore prefix
    'nested.field.value' => 'ok'  // ✅ nested dots
]);
```

### ❌ Nama Field yang DIBLOKIR

```php
// String dengan karakter berberbahaya
$collection->find([
    "field'; DROP--" => 'value',        // ❌ SQL injection attempt
    'field" OR "1"="1' => 'value',      // ❌ injection
    'field; system()' => 'value',       // ❌ exec attempt
    'field(hack)' => 'value',           // ❌ parentheses
    'field<script>' => 'value',         // ❌ tags
    'field[0]' => 'value',              // ❌ brackets
    'field`backtick`' => 'value',       // ❌ backticks
]);
// Semua di atas akan throw ValidationException
```

### 📝 Contoh Migrasi

Jika Anda memiliki kode lama dengan karakter berbahaya di nama field:

**Sebelum (Risiko):**

```php
// Jangan lakukan ini!
$data = $collection->find([]);
foreach ($data as $doc) {
    $query = "SELECT * FROM users WHERE " . $unsafeFieldName . " = ?";
}
```

**Sesudah (Aman):**

```php
// Gunakan field names dengan karakter aman
$collection->setSchema([
    'user_name' => 'string',
    'user_email' => 'string',
    'phone_number' => 'string'
]);

$data = $collection->find([
    'user_email' => $email,
    'user_name' => $name
]);
```

---

## 🔐 Operator `$regex` - Perubahan Minor

### ℹ️ Apa yang Berubah

Operator regex sekarang lebih aman terhadap **ReDoS (Regex Denial of Service)** attacks.

### ✅ Format yang Masih Didukung

```php
// 1. Full regex dengan delimiter
$results = $collection->find([
    'email' => ['$regex' => '/^[a-z]+@gmail\.com$/i']
]);

// 2. Raw pattern (forward slash diescaping otomatis)
$results = $collection->find([
    'path' => ['$regex' => 'test/value']  // Aman untuk delimiter
]);

// 3. Shorthand $preg, $match
$results = $collection->find([
    'name' => ['$preg' => '/^john/i'],
    'text' => ['$match' => '/query/']
]);

// 4. Negation dengan $not
$results = $collection->find([
    'email' => ['$not' => '/@gmail\.com$/i']
]);
```

### ⚠️ Catatan Keamanan

```php
// Hindari pattern ReDoS yang kompleks
// ❌ Bahaya (bisa hang server)
$results->find(['text' => ['$regex' => '(a+)+b']]);

// ✅ Lebih aman
$results->find(['text' => ['$regex' => '/a+b/']]);
```

---

## 🛡️ Fitur Keamanan Baru

### 1. FieldValidator - Utility Validasi

```php
use BangronDB\Security\FieldValidator;

// Validasi nama field
if (FieldValidator::isValidFieldName('user_name')) {
    echo "Field name aman";
}

// Throw exception jika invalid
try {
    FieldValidator::validateFieldName("'; DROP--");
} catch (\BangronDB\Exceptions\ValidationException $e) {
    echo "Field name tidak aman: " . $e->getMessage();
}

// Validasi callable (hanya Closure)
$closure = fn($doc) => $doc['age'] > 18;
if (FieldValidator::isSafeCallable($closure)) {
    echo "Callable aman";
}

// Validasi path database
$safePath = FieldValidator::validateDatabasePath('/data/db.sqlite');
```

### 2. Database Path Validation

```php
// Path akan divalidasi otomatis
$db = new Database('/data/database.sqlite');  // ✅ Aman

// Mencegah directory traversal
$db = new Database('../../etc/passwd');  // ❌ Akan error
```

### 3. Encryption Key Escape

```php
// Key dengan quote akan diescaping otomatis
$db = new Database(':memory:', [
    'encryption_key' => "it's-a-secret"  // ✅ Otomatis di-escape
]);
```

---

## 📚 Contoh Lengkap - Query Kompleks Aman

```php
use BangronDB\Client;

$client = new Client(__DIR__ . '/data');
$db = $client->selectDB('myapp');
$users = $db->users;

// ✅ CONTOH 1: Filter dengan multiple conditions
$activeAdmins = $users->find([
    'role' => ['$in' => ['admin', 'moderator']],
    'status' => 'active',
    'age' => ['$where' => fn($doc) => $doc['age'] >= 18],
    'verified_at' => ['$exists' => true]
]);

// ✅ CONTOH 2: Complex custom function
$qualifiedUsers = $users->find([
    'score' => ['$func' => function($score) {
        $numeric = (float) $score;
        return $numeric > 100 && $numeric < 1000;
    }]
]);

// ✅ CONTOH 3: Nested field validation
$results = $users->find([
    'profile.preferred_contact' => ['$where' => fn($doc) =>
        in_array($doc['profile']['preferred_contact'] ?? '',
                 ['email', 'phone', 'sms'])
    ]
]);

// ✅ CONTOH 4: Text search aman
$searchResults = $users->find([
    'name' => ['$regex' => '/^john/i'],
    'email' => ['$func' => fn($email) =>
        preg_match('/@(gmail|yahoo)\.com$/', $email)
    ]
]);

// ✅ CONTOH 5: Logical operators
$results = $users->find([
    '$or' => [
        ['age' => ['$lt' => 18], 'parent_approval' => true],
        ['age' => ['$gte' => 18]],
        ['premium' => ['$where' => fn($doc) =>
            ($doc['tier'] ?? 'free') === 'premium'
        ]]
    ]
]);
```

---

## ⚡ Troubleshooting

### Error: "only accepts Closure objects"

```
ValidationException: The '$where' operator only accepts Closure objects
(anonymous functions). String function names like 'system', 'exec', etc.
are not allowed.
```

**Solusi:**

```php
// ❌ Ini yang error:
['field' => ['$where' => 'is_array']]

// ✅ Ubah jadi:
['field' => ['$where' => fn($doc) => is_array($doc['field'])]]
```

### Error: "Invalid field name"

```
ValidationException: Invalid field name 'field'; DROP--'.
Field names must be alphanumeric with underscores, hyphens, and dots only.
```

**Solusi:**
Gunakan nama field dengan karakter aman saja: `a-z`, `A-Z`, `0-9`, `_`, `-`, `.`

```php
// ❌ Ini yang error:
['field; DROP' => 'value']

// ✅ Ubah jadi:
['field_name' => 'value']
['field-name' => 'value']
['field.name' => 'value']
```

### Error: "PRAGMA key contains invalid control characters"

```
ValidationException: PRAGMA key contains invalid control characters
```

**Solusi:**
Encryption key tidak boleh mengandung karakter kontrol. Gunakan alfanumerik dan simbol biasa:

```php
// ❌ Ini yang error:
'encryption_key' => "key\x00with\ncontrol\rchars"

// ✅ Gunakan:
'encryption_key' => 'MySecureKey!@#$%^&*()'
```

---

## 📖 Referensi Cepat

| Fitur                           | Sebelum    | Sesudah             |
| ------------------------------- | ---------- | ------------------- |
| `$where` dengan string          | ✅ Bekerja | ❌ DIBLOKIR         |
| `$where` dengan Closure         | ✅ Bekerja | ✅ DIREKOMENDASIKAN |
| `$func` dengan string           | ✅ Bekerja | ❌ DIBLOKIR         |
| `$func` dengan Closure          | ✅ Bekerja | ✅ DIREKOMENDASIKAN |
| Field names dengan special char | ✅ Bekerja | ❌ DIBLOKIR         |
| Field names alfanumerik         | ✅ Bekerja | ✅ DIREKOMENDASIKAN |
| Database path validation        | -          | ✅ BARU             |
| Encryption key escape           | Parsial    | ✅ LENGKAP          |

---

## 🎓 Learning Resources

- 📖 [SECURITY_HARDENING_REPORT.md](./SECURITY_HARDENING_REPORT.md) - Laporan teknis detail
- 📖 [README.md](./README.md) - Dokumentasi lengkap BangronDB
- 📖 [examples/](./examples/) - Contoh penggunaan
- 🧪 [tests/SecurityValidationTest.php](./tests/SecurityValidationTest.php) - Test cases keamanan

---

## ✨ Key Takeaways

1. **`$where` dan `$func` sekarang HANYA terima Closure** - Gunakan arrow function `fn()` atau `function()`
2. **Field names harus alfanumerik** - `a-z`, `A-Z`, `0-9`, `_`, `-`, `.` saja
3. **Semua path database divalidasi** - Mencegah directory traversal
4. **Encryption key lebih aman** - Quote dan control chars di-escape otomatis
5. **Strict type checking aktif** - Menangkap tipe data error lebih awal

**Pertanyaan? Lihat [SECURITY_HARDENING_REPORT.md](./SECURITY_HARDENING_REPORT.md) atau tanyakan di issues!**

---

_Dokumentasi ini dibuat untuk BangronDB v1.0.1+ (Maret 2026)_
_Last Updated: 2026-03-25_
