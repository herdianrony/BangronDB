# Config Class

Dokumentasi API untuk class `Config` yang mengelola konfigurasi global BangronDB.

## Namespace

```php
namespace BangronDB;
```

## Deskripsi

Class `Config` menyediakan antarmuka statis untuk mengelola opsi konfigurasi global untuk library database. Ini mencakup pengaturan seperti path default, encryption key, dan berbagai opsi SQLite.

## Metode Statis

### `set()`

Menetapkan nilai konfigurasi.

```php
public static function set(string $key, mixed $value): void
```

**Parameter:**

- `string $key` - Kunci konfigurasi
- `mixed $value` - Nilai konfigurasi

**Contoh:**

```php
Config::set('default_path', '/var/data/bangrondb');
Config::set('encryption_key', 'your-secret-key');
Config::set('journal_mode', 'WAL');
```

**Kunci Valid:**

- `default_path` - Path database default
- `encryption_key` - Kunci enkripsi default
- `journal_mode` - Mode journal SQLite (WAL, DELETE, dll)
- `synchronous` - Pengaturan synchronous SQLite (NORMAL, FULL, OFF)
- `page_size` - Ukuran page SQLite dalam bytes
- `cache_size` - Ukuran cache dalam KB (angka negatif = KB)
- `auto_vacuum` - Mode auto vacuum (NONE, FULL, INCREMENTAL)

**Throws:**

- `\InvalidArgumentException` - Jika kunci tidak valid

---

### `get()`

Mendapatkan nilai konfigurasi.

```php
public static function get(string $key, mixed $default = null): mixed
```

**Parameter:**

- `string $key` - Kunci konfigurasi
- `mixed $default` - Nilai default jika kunci tidak ditemukan

**Nilai Kembali:**

- Nilai konfigurasi atau nilai default

**Contoh:**

```php
$path = Config::get('default_path', ':memory:');
$key = Config::get('encryption_key');
```

---

### `all()`

Mendapatkan semua nilai konfigurasi.

```php
public static function all(): array
```

**Nilai Kembali:**

- Array berisi semua konfigurasi

**Contoh:**

```php
$allConfig = Config::all();
// Output: ['default_path' => ':memory:', 'encryption_key' => null, ...]
```

---

### `has()`

Memeriksa apakah kunci konfigurasi ada.

```php
public static function has(string $key): bool
```

**Parameter:**

- `string $key` - Kunci konfigurasi

**Nilai Kembali:**

- `true` jika kunci ada, `false` jika tidak

**Contoh:**

```php
if (Config::has('encryption_key')) {
    // Encryption key sudah diset
}
```

---

### `reset()`

Mereset konfigurasi ke default.

```php
public static function reset(): void
```

**Contoh:**

```php
Config::reset();
// Semua konfigurasi kembali ke nilai default
```

---

## Nilai Default

```php
private static array $defaults = [
    'default_path' => ':memory:',
    'encryption_key' => null,
    'journal_mode' => 'WAL',
    'synchronous' => 'NORMAL',
    'page_size' => 4096,
    'cache_size' => -1024, // KB
    'auto_vacuum' => 'INCREMENTAL',
];
```

---

## Penggunaan dengan Factory

Class `Config` terintegrasi dengan `Factory` untuk pembuatan instances:

```php
use BangronDB\Config;
use BangronDB\Factory;

// Set konfigurasi global
Config::set('default_path', '/var/data/bangrondb');
Config::set('encryption_key', 'default-key');

// Buat client dengan konfigurasi
$client = Factory::createClient();
// Atau dengan path custom
$client = Factory::createClient('/custom/path');
```

---

## Contoh Lengkap

```php
use BangronDB\Config;
use BangronDB\Factory;

// Inisialisasi konfigurasi
Config::set('default_path', __DIR__ . '/data');
Config::set('journal_mode', 'WAL');
Config::set('synchronous', 'NORMAL');
Config::set('page_size', 4096);
Config::set('cache_size', -2048); // 2MB cache

// Cek konfigurasi
$path = Config::get('default_path');
echo "Database path: $path\n";

// Cek apakah encryption key ada
if (Config::has('encryption_key')) {
    echo "Encryption enabled\n";
}

// Ambil semua konfigurasi
$all = Config::all();
print_r($all);

// Reset jika diperlukan
Config::reset();
```
