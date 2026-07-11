---
layout: doc
category: dasar
permalink: /docs/schema-metadata-guide/
title: "Schema & Metadata"
description: "Schema format, validasi, type aliases."
toc: true
edit_on_github: true
prev:
  url: /docs/query-operators/
  title: "Query Operators"
next:
  url: /docs/hook-patterns/
  title: "Hook Patterns"
---
# Schema Metadata Guide

BangronDB schema mendukung dua kategori properti: **validasi aktif** yang diperiksa otomatis saat insert/update, dan **metadata** yang disimpan sebagai informasi tambahan untuk digunakan oleh aplikasi di atas BangronDB.

---

## Format Schema

Schema didefinisikan dalam format **flat** — setiap key level atas adalah nama field:

```php
$collection->setSchema([
    'name'  => ['type' => 'string', 'required' => true, 'min' => 2, 'max' => 100],
    'email' => ['type' => 'string', 'required' => true, 'unique' => true, 'regex' => '/^[^@]+@[^@]+\.[^@]+$/'],
    'role'  => ['type' => 'string', 'enum' => ['admin', 'user', 'editor']],
    'age'   => ['type' => 'integer', 'min' => 0, 'max' => 150],
    'tags'  => ['type' => 'array', 'max' => 10],
]);
```

> **Tidak ada** wrapper `'fields' => [...]` atau top-level `'required' => [...]`. Semua aturan berada langsung di bawah nama field.

---

## Properti Validasi Aktif

Properti berikut **benar-benar diperiksa** oleh BangronDB saat `insert()` dan `update()`:

| Properti | Tipe | Kapan Divalidasi |
|----------|------|-----------------|
| `type` | `string` | Setiap insert/update yang menyentuh field |
| `required` | `bool` | Insert dan update — field harus `isset()` |
| `enum` / `options` | `array` | Nilai harus ada di array (strict `===` comparison) |
| `regex` | `string` | Nilai string harus cocok pola regex |
| `min` | `int\|float` | Angka: nilai minimum. String: panjang minimum. Array: jumlah item minimum. |
| `max` | `int\|float` | Angka: nilai maksimum. String: panjang maksimum. Array: jumlah item maksimum. |
| `unique` | `bool` | Nilai tidak boleh duplikat di seluruh collection |

### Tipe Data yang Didukung (`type`)

| Tipe | Alias | Divalidasi Sebagai |
|------|-------|-------------------|
| `string` | `text`, `email`, `password`, `url`, `slug`, `date`, `datetime`, `time`, `relation`, `enum` | `is_string()` |
| `integer` | `int` | `is_int()` |
| `float` | `double`, `number`, `decimal` | `is_float()` atau `is_int()` |
| `boolean` | `bool`, `checkbox`, `switch` | `is_bool()` |
| `array` | `tags` | `is_array()` |
| `object` | `json` | `is_object()` atau associative array |

Tipe yang tidak dikenali akan **diterima** (pass-through) untuk forward compatibility.

### `min` / `max` Bersifat Polimorfik

```php
'age' => ['type' => 'integer', 'min' => 0, 'max' => 150],    // numeric range
'name' => ['type' => 'string', 'min' => 2, 'max' => 100],    // string length
'tags' => ['type' => 'array', 'max' => 10],                   // array count
```

### `unique` dan Enkripsi

Pada collection terenkripsi, `unique` memerlukan field tersebut juga dijadikan **searchable** agar bisa di-query:

```php
$collection->setSchema(['email' => ['type' => 'string', 'unique' => true]]);
$collection->setEncryptionKey($key);
$collection->setSearchableFields(['email' => ['hash' => true]]);  // WAJIB
```

Tanpa searchable field, unique constraint tidak bisa menemukan duplikat karena data terenkripsi.

---

## Properti Metadata

Properti berikut **diterima** di definisi schema dan disimpan, tetapi **tidak digunakan** dalam validasi otomatis. Mereka ada untuk digunakan oleh aplikasi Anda (misalnya untuk generate form UI):

| Properti | Tipe | Kegunaan Umum |
|----------|------|--------------|
| `label` | `string` | Label tampilan: `'label' => 'Nama Lengkap'` |
| `searchable` | `bool` | Penanda bahwa field bisa dicari |
| `sortable` | `bool` | Penanda bahwa field bisa di-sort |
| `index` | `bool` | Penanda untuk membuat index |
| `ui` | `array` | Konfigurasi tampilan (tipe input, placeholder, help text, dll.) |
| `hidden` | `bool` | Sembunyikan dari output UI |
| `readonly` | `bool` | Penanda read-only untuk UI |
| `filterable` | `bool` | Penanda untuk kemampuan filter |
| `default` | `mixed` | Nilai default untuk field |
| `relation` | `array` | Definisi relasi antar collection |

### Contoh Schema dengan Metadata

```php
$collection->setSchema([
    'name'  => [
        'type'     => 'string',
        'required' => true,
        'min'      => 2,
        'max'      => 100,
        'label'    => 'Nama Lengkap',
        'ui'       => ['input' => 'text', 'placeholder' => 'Masukkan nama'],
        'sortable' => true,
        'searchable' => true,
    ],
    'email' => [
        'type'     => 'string',
        'required' => true,
        'regex'    => '/^[^@]+@[^@]+\.[^@]+$/',
        'unique'   => true,
        'label'    => 'Alamat Email',
        'ui'       => ['input' => 'email', 'placeholder' => 'user@example.com'],
        'hidden'   => false,
    ],
    'password' => [
        'type'     => 'string',
        'required' => true,
        'min'      => 8,
        'label'    => 'Kata Sandi',
        'ui'       => ['input' => 'password', 'placeholder' => 'Minimal 8 karakter'],
        'hidden'   => true,
    ],
    'role'  => [
        'type'     => 'string',
        'enum'     => ['admin', 'user', 'editor'],
        'default'  => 'user',
        'label'    => 'Peran',
        'ui'       => ['input' => 'select'],
        'filterable' => true,
    ],
    'department_id' => [
        'type'     => 'string',
        'label'    => 'Departemen',
        'relation' => ['collection' => 'departments', 'field' => '_id', 'type' => 'belongsTo'],
    ],
]);
```

### Membaca Metadata dari Schema

```php
$schema = $collection->getSchema();

// Ambil label sebuah field
$label = $schema['name']['label'] ?? 'name';

// Ambil konfigurasi UI
$ui = $schema['name']['ui'] ?? [];

// Cek apakah field bisa di-sort
$sortable = $schema['name']['sortable'] ?? false;

// Ambil semua field yang bisa di-filter
$filterable = array_keys(array_filter($schema, fn($r) => ($r['filterable'] ?? false)));
```

### Persistensi Metadata

Metadata yang disimpan di schema akan ikut tersimpan saat `saveConfiguration()` dipanggil:

```php
$collection->setSchema([...]);  // termasuk properti metadata
$collection->saveConfiguration();  // schema tersimpan ke database
```

Saat collection dibuka kembali, schema (termasuk metadata) akan di-load otomatis.

---

## Validasi Manual

Jika Anda perlu memvalidasi dokumen tanpa menyimpannya:

```php
try {
    $isValid = $collection->validate($document);
    echo "Valid!";
} catch (\BangronDB\Exceptions\ValidationException $e) {
    echo "Error: {$e->getMessage()}";
    echo "Code: {$e->getErrorCode()}";
    print_r($e->getContext());
}
```

---

## Lihat Juga

- [docs/features.md — Schema Validation](features.md#schema-validation)
- [examples/04-schema-validation.php](../examples/04-schema-validation.php) — Contoh lengkap validasi
- [docs/api-reference.md — Collection Methods](api-reference.md#collection)