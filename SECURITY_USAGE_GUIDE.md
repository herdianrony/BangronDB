# Panduan Keamanan BangronDB

Dokumen ini merangkum guardrail keamanan penting di BangronDB dan cara memakainya dengan benar.

## Ringkasan

BangronDB memiliki beberapa proteksi bawaan untuk mengurangi risiko:

- remote code execution (RCE)
- injection pada query / identifier
- path traversal
- regex denial of service (ReDoS)
- penyalahgunaan key pada PRAGMA

## 1. `$where` dan `$func` hanya menerima Closure

Operator berikut **tidak menerima string function name**:

- `$where`
- `$func`
- alias terkait seperti `$fn` dan `$f`

### Contoh yang diblokir

```php
$collection->find(['status' => ['$where' => 'is_array']]);
$collection->find(['value' => ['$func' => 'strlen']]);
```

### Contoh yang benar

```php
$collection->find([
    'status' => [
        '$where' => fn($doc) => is_array($doc['status'] ?? null),
    ],
]);

$collection->find([
    'value' => [
        '$func' => fn($val) => is_string($val) && strlen($val) > 5,
    ],
]);
```

## 2. Nama field divalidasi

BangronDB hanya menerima nama field yang terdiri dari:

- huruf dan angka
- underscore (`_`)
- hyphen (`-`)
- dot (`.`)

### Diizinkan

```php
[
    'user_name' => 'john',
    'user-email' => 'john@example.com',
    'address.city' => 'Jakarta',
]
```

### Ditolak

```php
["field'; DROP TABLE users;--" => 'value'];
['field" OR "1"="1' => 'value'];
['field(name)' => 'value'];
```

Validasi ini juga relevan untuk searchable fields dan field yang dipakai dalam sorting atau indexing.

## 3. Path database perlu dijaga

BangronDB sekarang memvalidasi path database dan directory path lebih awal pada entry point utama seperti `Client` dan `Database`.

Gunakan path yang eksplisit dan berada di direktori aplikasi Anda.

### Aman

```php
new Database(__DIR__ . '/data/app.bangron');
```

### Hindari

```php
new Database('../../etc/passwd');
```

Jika Anda menerima input path dari luar, validasi terlebih dahulu.

## 4. Encryption key tidak boleh hard-coded sembarangan

BangronDB mendukung enkripsi dokumen dengan **AES-256-GCM**. Key sebaiknya:

- minimal 32 karakter
- berasal dari `.env`, vault, atau secret manager
- tidak disimpan di konfigurasi collection yang dipersist ke database

### Contoh

```php
$db = new Database(__DIR__ . '/secure.bangron', [
    'encryption_key' => $_ENV['DB_ENCRYPTION_KEY'],
]);
```

### Catatan

- key di-escape saat dipakai pada PRAGMA
- key collection-level tidak otomatis dipersist
- BangronDB kini menggunakan salt per-database untuk derivasi key baru
- data terenkripsi lama tetap didukung lewat fallback kompatibilitas
- ganti key secara hati-hati dan uji data terenkripsi setelah rotasi

## 5. Searchable fields untuk data terenkripsi

Searchable fields memudahkan query pada data terenkripsi, tetapi tetap ada trade-off.

```php
$users->setEncryptionKey($_ENV['DB_ENCRYPTION_KEY']);
$users->setSearchableFields(['email', 'phone'], true); // true = hash
$users->saveConfiguration();
```

### Cara kerja hashing (blind index)

- Untuk collection **terenkripsi**, nilai hash searchable disimpan sebagai
  **HMAC-SHA256 berkunci** ("blind index"). Kunci HMAC diturunkan dari
  encryption key via PBKDF2 dengan salt khusus (`searchindex:`), sehingga
  terpisah dari kunci enkripsi data.
- Artinya: jika file `.bangron` bocor, penyerang **tidak bisa** brute-force /
  rainbow-table nilai email/phone dari kolom `si_*` tanpa mengetahui kunci, dan
  nilai yang sama **tidak** berkorelasi antar-database (kunci berbeda → hash
  berbeda).
- Untuk collection **tanpa** encryption key, nilai tetap memakai SHA-256 biasa
  (kompatibel ke belakang; data tersebut memang tidak rahasia).
- Migrasi data lama (SHA-256 polos → HMAC) setelah mengaktifkan enkripsi:
  panggil `$collection->rehashSearchableField('email')`.

> Catatan: query `$in` / `$nin` pada field searchable yang di-hash kini
> dijalankan lewat SQL fast-path agar nilai query ikut di-hash dengan blind
> index yang sama (sebelumnya bisa diam-diam mengembalikan kosong).

### Rekomendasi

- gunakan hashing (`true`) untuk field sensitif seperti email, phone, username login
- jangan menjadikan terlalu banyak field sensitif sebagai searchable field tanpa alasan jelas
- dokumentasikan searchable field yang dipakai aplikasi Anda

## 6. Validasi enum dan operator membership

Schema `enum` divalidasi dengan **strict comparison**, jadi perbedaan tipe tetap dihormati.

```php
$users->setSchema([
    'status' => ['enum' => ['0']],
]);

$users->insert(['status' => 0]); // akan ditolak
```

Untuk query membership, item pada `$in` / `$nin` harus berupa nilai scalar. Nested array seperti berikut sekarang akan ditolak eksplisit:

```php
$users->find([
    'role' => ['$in' => ['admin', ['editor']]],
]);
```

Ini membantu mencegah edge case yang sebelumnya bisa lolos ke driver SQL sebagai string `Array`.

## 7. Regex dan fuzzy search

BangronDB menerapkan pembatasan tambahan untuk membantu mengurangi risiko ReDoS, termasuk penolakan terhadap beberapa pola recursive, lookbehind, numeric backreference, dan nested quantifier yang berbahaya. Meski begitu, tetap hindari pola regex yang terlalu kompleks.

### Lebih aman

```php
$collection->find(['name' => ['$regex' => '^John']]);
```

### Hindari pola user-generated yang terlalu bebas

```php
$collection->find(['name' => ['$regex' => $rawUserInput]]);
```

Jika input regex berasal dari user, sanitasi atau batasi pola yang diizinkan.

## 8. Hooks dan custom logic

Hooks sangat berguna, tetapi tetap merupakan titik masuk business logic tambahan.

### Rekomendasi

- hindari hook yang terlalu besar atau memiliki side effect tersembunyi
- tangani exception secara eksplisit jika hook melakukan operasi penting
- dokumentasikan hook yang wajib didaftarkan ulang setiap startup/request

Ingat: hook **tidak dipersist** ke database.

## 9. Utilitas `FieldValidator`

BangronDB menyediakan utilitas untuk validasi keamanan dasar:

```php
use BangronDB\Security\FieldValidator;

FieldValidator::isValidFieldName('user_name');
FieldValidator::validateFieldName('address.city');
FieldValidator::isSafeCallable(fn($x) => true);
FieldValidator::isSafeCallable('system');
```

## Troubleshooting

### Error: only accepts Closure objects

Ubah dari:

```php
['$where' => 'is_array']
```

Menjadi:

```php
['$where' => fn($doc) => is_array($doc['field'] ?? null)]
```

### Error: Invalid field name

Ubah dari:

```php
['field; DROP' => 'value']
```

Menjadi:

```php
['field_name' => 'value']
```

### Data terenkripsi tidak bisa dibaca

Periksa hal berikut:

- key runtime benar
- key belum berubah dari saat data ditulis
- collection-level key dan database-level key tidak tertukar

## Checklist Praktik Aman

- [ ] Gunakan key dari environment variable atau secret manager
- [ ] Jangan pakai string function name pada `$where` / `$func`
- [ ] Batasi field yang dijadikan searchable
- [ ] Validasi input user sebelum dipakai sebagai query
- [ ] Tambahkan regression test untuk area encryption, hooks, searchable fields, dan SQL fast-path
- [ ] Audit logika hook yang sensitif
- [ ] Jalankan static analysis (`composer analyze`) di workflow development Anda
