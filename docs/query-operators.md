---
layout: doc
title: "Query Operators"
description: "Daftar lengkap operator query."
toc: true
edit_on_github: true
prev:
  url: /features/
  title: "Features"
next:
  url: /schema-metadata-guide/
  title: "Schema & Metadata"
---
# Query Operators

Referensi lengkap operator query yang didukung BangronDB dalam `find()`, `findOne()`, `count()`, `update()`, `remove()`, dan method query lainnya.

---

## Perbandingan (Comparison)

### `$eq` — Sama Dengan

```php
// Implicit (langsung)
$users->find(['status' => 'active']);

// Explicit
$users->find(['status' => ['$eq' => 'active']]);
```

### `$ne` — Tidak Sama Dengan

```php
$users->find(['status' => ['$ne' => 'banned']]);
```

### `$gt` — Lebih Besar

```php
$products->find(['price' => ['$gt' => 10000]]);
```

### `$gte` — Lebih Besar atau Sama Dengan

```php
$users->find(['age' => ['$gte' => 18]]);
```

### `$lt` — Lebih Kecil

```php
$orders->find(['total' => ['$lt' => 50000]]);
```

### `$lte` — Lebih Kecil atau Sama Dengan

```php
$items->find(['stock' => ['$lte' => 5]]);
```

### Kombinasi Beberapa Operator pada Satu Field

```php
$users->find([
    'age' => ['$gte' => 18, '$lte' => 30]
]);
```

---

## Logika (Logical)

### `$and` — Semua Kondisi Benar (AND)

```php
$users->find([
    '$and' => [
        ['age' => ['$gte' => 18]],
        ['status' => 'active'],
    ]
]);
```

> **Catatan:** Secara default, semua kondisi di level atas sudah berlaku sebagai AND. Gunakan `$and` hanya jika perlu menggabungkan kondisi kompleks.

### `$or` — Salah Satu Benar (OR)

```php
$users->find([
    '$or' => [
        ['role' => 'admin'],
        ['role' => 'moderator'],
    ]
]);
```

### `$not` — Negasi Regex

```php
// Kecualikan nama yang diawali 'test'
$users->find(['name' => ['$not' => '/^test/']]);
```

> `$not` hanya untuk negasi regex. Untuk negasi umum, gunakan `$ne`.

---

## Array Operators

### `$in` — Di Dalam Array

```php
// Field skalar: nilai ada di array
$users->find(['role' => ['$in' => ['admin', 'editor', 'moderator']]]);

// Field array: ada irisan
$posts->find(['tags' => ['$in' => ['php', 'laravel']]]);
```

### `$nin` — Tidak Di Dalam Array

```php
$users->find(['status' => ['$nin' => ['banned', 'suspended']]]);
```

### `$has` — Array Mengandung Nilai

```php
$posts->find(['tags' => ['$has' => 'php']]);
```

> Berbeda dengan `$in`: `$has` hanya untuk field bertipe array dan tidak menerima array sebagai argumen.

### `$all` — Array Mengandung Semua Nilai

```php
$posts->find(['tags' => ['$all' => ['php', 'mysql']]]);
```

### `$size` — Ukuran Array

```php
$orders->find(['items' => ['$size' => 3]]);
```

---

## Regex & Pencarian Teks

### `$regex` / `$preg` / `$match` — Regex Match

```php
// Pola langsung (otomatis di-escape, aman)
$users->find(['email' => ['$regex' => 'admin@']]);

// Pola dengan delimiter (untuk flag) — gunakan ini untuk case-insensitive
$users->find(['name' => ['$regex' => '/^john/iu']]);
```

> **Catatan:** `$options` disediakan untuk kompatibilitas sintaks MongoDB, tetapi **saat ini nilainya tidak diterapkan** ke regex. Untuk flag seperti case-insensitive (`i`) atau Unicode (`u`), sertakan langsung di dalam pola delimiter: `/^john/iu`.

> **Keamanan:** BangronDB secara otomatis memvalidasi regex terhadap ReDoS (Regular Expression Denial of Service). Pola berbahaya akan ditolak. Panjang maksimum regex: 500 karakter.

### `$fuzzy` / `$text` — Pencarian Fuzzy

```php
// Default: distance 3, minScore 0.7
$users->find(['name' => ['$fuzzy' => 'Jhn Doe']]);

// Custom parameter
$users->find(['name' => [
    '$fuzzy' => [
        '$search' => 'Jhn Doe',
        '$distance' => 2,
        '$minScore' => 0.8,
    ]
]]);
```

> Fuzzy search menggunakan Levenshtein distance. Cocok untuk fitur pencarian yang toleran terhadap typo.

---

## Custom Function

### `$func` / `$fn` / `$f` — Custom Function

```php
$users->find(['age' => ['$fn' => fn($v) => $v >= 18 && $v <= 65]]);

// Dengan validasi keamanan — hanya Closure yang diizinkan
$users->find(['score' => ['$func' => function ($val) {
    return $val > 50 && $val < 200;
}]]);
```

> **Keamanan:** Hanya `Closure` yang diizinkan. String callable, array callable, dan function name semuanya ditolak.

### `$where` — Custom Criteria Function

```php
$orders->find(['$where' => function ($doc) {
    return $doc['items_count'] > 0
        && $doc['total'] > $doc['discount'];
}]);
```

> `$where` menerima seluruh dokumen sebagai argument, bukan nilai field tunggal. Dieksekusi pada setiap dokumen saat iterasi (bukan SQL level).

---

## Eksistensi & Lainnya

### `$exists` — Field Ada / Tidak Ada

```php
// Field ada
$users->find(['phone' => ['$exists' => true]]);

// Field tidak ada
$users->find(['phone' => ['$exists' => false]]);
```

### `$mod` — Modulo

```php
// id habis dibagi 10 = 0
$items->find(['id_num' => ['$mod' => [10, 0]]]);
```

---

## Dot Notation (Nested Field)

Akses field di dalam nested object:

```php
// Dokumen: { 'address' => { 'city' => 'Jakarta', 'zip' => '12345' } }

$users->find(['address.city' => 'Jakarta']);
$users->find(['address.zip' => ['$regex' => '^12']]);
```

---

## Kombinasi Kompleks

```php
$users->find([
    '$and' => [
        ['age' => ['$gte' => 18]],
        [
            '$or' => [
                ['role' => 'admin'],
                ['role' => 'moderator'],
                ['points' => ['$gte' => 1000]],
            ]
        ],
        ['status' => ['$ne' => 'banned']],
        ['name' => ['$not' => '/^system/']],
    ]
]);
```

---

## Update Operators

Digunakan di dalam `update()` dan `updateMany()`:

### `$set` — Set Nilai Field

```php
$users->update(
    ['_id' => 'abc'],
    ['$set' => ['name' => 'New Name', 'age' => 30]]
);
```

### `$unset` — Hapus Field

```php
$users->update(
    ['_id' => 'abc'],
    ['$unset' => ['temp_token' => '']]
);
```

### `$inc` — Increment Atomik

```php
// Increment 1 field
$users->update(
    ['_id' => 'abc'],
    ['$inc' => ['login_count' => 1]]
);

// Increment beberapa field sekaligus (hanya angka)
$users->update(
    ['_id' => 'abc'],
    ['$inc' => ['views' => 1, 'points' => 10, 'balance' => -50]]
);
```

> **Validasi:** `$inc` hanya menerima nilai numerik (int/float). String, boolean, null, dan array akan throw `InvalidArgumentException`.

### Merge vs Replace

```php
// Merge (default) — hanya field yang disebut yang diubah
$users->update(['_id' => 'abc'], ['age' => 31, 'city' => 'Jakarta']);
// Dokumen lain tetap utuh

// Replace (merge = false) — seluruh dokumen diganti
$users->update(['_id' => 'abc'], ['age' => 31], false);
// Hanya 'age' dan '_id' yang tersisa
```