# UtilArrayQuery Class

Dokumentasi API untuk class `UtilArrayQuery` yang menyediakan utilitas untuk query dan operasi array.

## Namespace

```php
namespace BangronDB;
```

## Deskripsi

`UtilArrayQuery` menyediakan metode statis untuk:

- Mengambil nilai dari array menggunakan dot notation
- Pencocokan kriteria MongoDB-like
- Fuzzy search dengan Levenshtein distance
- UUID v4 generation

---

## Metode Array

### `get()`

Mengambil nilai dari array menggunakan dot notation.

```php
public static function get(array $data, string $path, $default = null): mixed
```

**Parameter:**

- `array $data` - Array sumber
- `string $path` - Path dengan dot notation (contoh: 'user.name')
- `mixed $default` - Nilai default jika tidak ditemukan

**Nilai Kembali:**

- Nilai pada path atau default

**Contoh:**

```php
$data = [
    'user' => [
        'name' => 'John',
        'profile' => [
            'age' => 30
        ]
    ]
];

// Ambil nilai sederhana
$name = UtilArrayQuery::get($data, 'user.name'); // 'John'

// Ambil nilai bersarang
$age = UtilArrayQuery::get($data, 'user.profile.age'); // 30

// Nilai default jika tidak ditemukan
$email = UtilArrayQuery::get($data, 'user.email', 'default@email.com'); // 'default@email.com'
```

---

## Metode Query

### `match()`

Mencocokkan kriteria terhadap dokumen.

```php
public static function match($criteria, $document): bool
```

**Parameter:**

- `array $criteria` - Kriteria query
- `array $document` - Dokumen untuk dicocokkan

**Nilai Kembali:**

- `true` jika cocok, `false` jika tidak

**Contoh:**

```php
$criteria = [
    'status' => 'active',
    'age' => ['$gte' => 18],
    'role' => ['$in' => ['admin', 'user']]
];

$document = [
    'status' => 'active',
    'name' => 'John',
    'age' => 25,
    'role' => 'admin'
];

$matches = UtilArrayQuery::match($criteria, $document); // true
```

---

### `check()`

Mengecek nilai terhadap kondisi.

```php
public static function check($value, $condition): bool
```

**Parameter:**

- `mixed $value` - Nilai untuk dicek
- `array $condition` - Kondisi yang harus dipenuhi

**Nilai Kembali:**

- `true` jika memenuhi kondisi, `false` jika tidak

---

## Operator Query

### Operator Perbandingan

#### `$eq` - Sama dengan

```php
['age' => ['$eq' => 25]]
// Sama dengan: age == 25
```

#### `$ne` - Tidak sama dengan

```php
['status' => ['$ne' => 'deleted']]
// status != 'deleted'
```

#### `$gte` - Lebih besar atau sama

```php
['age' => ['$gte' => 18]]
// age >= 18
```

#### `$gt` - Lebih besar

```php
['price' => ['$gt' => 100]]
// price > 100
```

#### `$lte` - Lebih kecil atau sama

```php
['quantity' => ['$lte' => 10]]
// quantity <= 10
```

#### `$lt` - Lebih kecil

```php
['score' => ['$lt' => 50]]
// score < 50
```

---

### Operator Array

#### `$in` - Dalam daftar

```php
['role' => ['$in' => ['admin', 'moderator']]]
// role IN ('admin', 'moderator')
```

#### `$nin` - Tidak dalam daftar

```php
['status' => ['$nin' => ['banned', 'inactive']]]
// status NOT IN ('banned', 'inactive')
```

#### `$has` - Memuat nilai

```php
['tags' => ['$has' => 'featured']]
// Array tags memuat 'featured'
```

#### `$all` - Memuat semua nilai

```php
['tags' => ['$all' => ['featured', 'premium']]]
// Array tags memuat SEMUA: 'featured' DAN 'premium'
```

#### `$size` - Ukuran array

```php
['items' => ['$size' => 5]]
// Array items memiliki tepat 5 elemen
```

---

### Operator String

#### `$regex` - Regex matching

```php
['email' => ['$regex' => '@gmail\.com$']]
// Email berakhir dengan @gmail.com
```

**Opsi Regex:**

```php
['name' => ['$regex' => 'john', '$options' => 'i']]
// Case-insensitive search
```

#### `$not` - Negasi regex

```php
['email' => ['$not' => '@spam\.com$']]
// Email tidak berakhir dengan @spam.com
```

---

### Operator Lainnya

#### `$mod` - Modulo

```php
['number' => ['$mod' => [2, 0]]]
// number % 2 == 0 (bilangan genap)
```

#### `$exists` - Keberadaan field

```php
['phone' => ['$exists' => true]]
// Field phone ada dan tidak null

['phone' => ['$exists' => false]]
// Field phone tidak ada atau null
```

#### `$fuzzy` / `$text` - Fuzzy search

```php
['name' => ['$fuzzy' => ['search' => 'john', 'distance' => 2]]]
// Fuzzy match dengan jarak maksimum 2

['description' => ['$text' => ['$search' => 'database', '$minScore' => 0.7]]]
// Text search dengan minimum score 0.7
```

#### `$func` / `$fn` / `$f` - Custom function

```php
['custom' => ['$func' => function($value) {
    return $value > 0 && $value < 100;
}]]
// Custom validation function
```

#### `$where` - JavaScript-like condition

```php
['$where' => function($doc) {
    return $doc['age'] > 18 && $doc['status'] === 'active';
}]
// Custom condition dengan closure
```

---

## Operator Logika

### `$and` - Dan

```php
['$and' => [
    ['status' => 'active'],
    ['age' => ['$gte' => 18]],
    ['role' => 'admin']
]]
// Semua kondisi harus terpenuhi
```

### `$or` - Atau

```php
['$or' => [
    ['role' => 'admin'],
    ['role' => 'moderator'],
    ['permissions' => ['$has' => 'edit']]
]]
// Salah satu kondisi harus terpenuhi
```

---

## Fuzzy Search

### `fuzzy_search()`

Pencarian fuzzy dengan distance-based matching.

```php
public static function fuzzy_search($search, $text, $distance = 3): float
```

**Parameter:**

- `string $search` - Kata pencarian
- `string $text` - Teks untuk dicocokkan
- `int $distance` - Jarak Levenshtein maksimum (default: 3)

**Nilai Kembali:**

- `float` - Skor kecocokan (0.0 - 1.0+)

**Contoh:**

```php
// Pencarian fuzzy
$score = UtilArrayQuery::fuzzy_search('hello', 'helo');
// Output: Skor berdasarkan fuzzy match

$score = UtilArrayQuery::fuzzy_search('database', 'MySQL Database', 2);
// Output: Skor proximity
```

---

### `levenshtein_utf8()`

Menghitung Levenshtein distance dengan dukungan UTF-8.

```php
public static function levenshtein_utf8($s1, $s2): int
```

**Parameter:**

- `string $s1` - String pertama
- `string $s2` - String kedua

**Nilai Kembali:**

- `int` - Jarak Levenshtein

**Contoh:**

```php
$distance = UtilArrayQuery::levenshtein_utf8('hello', 'helo'); // 1
$distance = UtilArrayQuery::levenshtein_utf8('cafe', 'kafe'); // 1
$distance = UtilArrayQuery::levenshtein_utf8('北京', '东京'); // Jarak karakter Mandarin
```

---

## ID Generation

### `generateId()`

Menghasilkan UUID v4.

```php
public static function generateId(): string
```

**Nilai Kembali:**

- `string` - UUID v4 format

**Contoh:**

```php
$id = UtilArrayQuery::generateId();
// Output: 'a1b2c3d4-e5f6-7890-abcd-ef1234567890'
```

---

## Contoh Penggunaan Lengkap

### Query Sederhana

```php
$users = [
    ['name' => 'John', 'age' => 25, 'status' => 'active'],
    ['name' => 'Jane', 'age' => 30, 'status' => 'active'],
    ['name' => 'Bob', 'age' => 35, 'status' => 'inactive'],
];

// Cari user aktif
$active = array_filter($users, function($user) {
    return UtilArrayQuery::check($user['status'], ['$eq' => 'active']);
});

// Cari user dengan age >= 28
$adults = array_filter($users, function($user) {
    return UtilArrayQuery::check($user['age'], ['$gte' => 28]);
});
```

### Query Kompleks

```php
$products = [
    ['name' => 'Laptop', 'price' => 1000, 'category' => 'electronics', 'tags' => ['sale', 'new']],
    ['name' => 'Phone', 'price' => 500, 'category' => 'electronics', 'tags' => ['sale']],
    ['name' => 'Book', 'price' => 20, 'category' => 'books', 'tags' => ['new']],
];

$criteria = [
    '$and' => [
        ['category' => 'electronics'],
        ['$or' => [
            ['price' => ['$lte' => 800]],
            ['tags' => ['$has' => 'new']]
        ]]
    ]
];

$filtered = array_filter($products, function($product) use ($criteria) {
    return UtilArrayQuery::match($criteria, $product);
});
```

### Nested Field Query

```php
$orders = [
    [
        'id' => 1,
        'customer' => [
            'name' => 'John',
            'email' => 'john@example.com'
        ],
        'items' => [
            ['product' => 'Laptop', 'qty' => 2],
            ['product' => 'Mouse', 'qty' => 1]
        ]
    ]
];

// Cari berdasarkan nested field
$criteria = ['customer.email' => 'john@example.com'];
$matched = UtilArrayQuery::match($criteria, $orders[0]); // true

// Cari berdasarkan array dalam nested
$criteria = ['items.product' => 'Laptop'];
$matched = UtilArrayQuery::match($criteria, $orders[0]); // true
```

### Fuzzy Search

```php
$documents = [
    'Introduction to Database Systems',
    'Database Management Concepts',
    'Advanced Database Design',
    'Web Development Basics'
];

$search = 'databases';

// Fuzzy search
$results = array_filter($documents, function($doc) use ($search) {
    $score = UtilArrayQuery::fuzzy_search($search, $doc, 2);
    return $score >= 0.5; // Minimum score
});
```

### UUID Generation untuk ID Custom

```php
use BangronDB\UtilArrayQuery;

class MyModel
{
    protected string $id;

    public function __construct(array $data = [])
    {
        $this->id = $data['_id'] ?? UtilArrayQuery::generateId();
    }

    public function getId(): string
    {
        return $this->id;
    }
}

$model = new MyModel(['name' => 'Test']);
echo $model->getId(); // UUID v4
```

---

## Catatan Penting

1. **Case Sensitivity:**
   - Operator seperti `$eq`, `$in` tidak case-sensitive
   - String comparison adalah case-sensitive kecuali menggunakan regex dengan opsi `i`

2. **Type Coercion:**
   - Perbandingan menggunakan loose equality (`==`) bukan strict (`===`)
   - `'25'` akan cocok dengan `25`

3. **Null Handling:**
   - Jika field tidak ada, `null` akan dikembalikan
   - Operator `$exists` harus digunakan untuk pengecekan eksistensi

4. **Performance:**
   - Untuk dataset besar, pertimbangkan penggunaan index
   - Fuzzy search lebih lambat dari exact matching
   - `$regex` tanpa index bisa sangat lambat untuk dataset besar
