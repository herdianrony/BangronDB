# SchemaValidationTrait

Trait untuk validasi dokumen terhadap schema yang didefinisikan. Mendukung berbagai jenis validasi termasuk tipe data, required fields, enum, range, dan regex.

## Properti

### `$schema`

- **Tipe**: `array`
- **Deskripsi**: Definisi schema validasi per field

## Metode Utama

### `setSchema(array $schema): self`

Mengatur schema validasi untuk koleksi.

**Parameter:**

- `$schema` (array): Array definisi schema per field

**Return:** Instance untuk chaining

### `getSchema(): array`

Mengembalikan schema yang didefinisikan.

**Return:** Array schema

### `validate(array $document): bool`

Memvalidasi dokumen terhadap schema.

**Parameter:**

- `$document` (array): Dokumen untuk divalidasi

**Throws:** `\Exception` jika validasi gagal
**Return:** True jika valid

## Struktur Schema

Schema didefinisikan sebagai array dengan field name sebagai key:

```php
[
    'field_name' => [
        'type' => 'string|int|float|bool|array|object',
        'required' => true|false,
        'enum' => ['value1', 'value2'],
        'min' => number,
        'max' => number,
        'regex' => '/pattern/',
        // ... opsi lainnya
    ]
]
```

## Opsi Validasi

### type

Menentukan tipe data yang diharapkan.

**Tipe yang didukung:**

- `'string'`: String
- `'int'` atau `'integer'`: Integer
- `'float'` atau `'double'`: Float
- `'bool'` atau `'boolean'`: Boolean
- `'array'`: Array
- `'object'`: Object atau associative array

### required

Menandai field sebagai wajib ada.

- `true`: Field harus ada dan tidak null
- `false` atau tidak diset: Field opsional

### enum

Membatasi nilai ke daftar tertentu.

```php
'enum' => ['active', 'inactive', 'pending']
```

### min / max

Validasi range untuk numeric atau panjang string/array.

**Untuk numeric:** Batas nilai minimum/maksimum
**Untuk string/array:** Batas panjang minimum/maksimum

### regex

Validasi pattern menggunakan regular expression.

```php
'regex' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/'
```

## Contoh Schema

### Schema Lengkap

```php
$schema = [
    'name' => [
        'type' => 'string',
        'required' => true,
        'min' => 2,
        'max' => 100
    ],
    'email' => [
        'type' => 'string',
        'required' => true,
        'regex' => '/@/'
    ],
    'age' => [
        'type' => 'int',
        'min' => 0,
        'max' => 150
    ],
    'status' => [
        'type' => 'string',
        'enum' => ['active', 'inactive', 'pending'],
        'required' => true
    ],
    'tags' => [
        'type' => 'array',
        'max' => 10 // max 10 tags
    ],
    'profile' => [
        'type' => 'object'
    ]
];

$collection->setSchema($schema);
```

### Validasi Otomatis

```php
try {
    $collection->insert([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30,
        'status' => 'active'
    ]);
    echo "Dokumen valid!";
} catch (Exception $e) {
    echo "Validasi gagal: " . $e->getMessage();
}
```

## Error Messages

Error messages dalam bahasa Inggris dengan konteks field:

- `"Field 'name' is required."`
- `"Field 'age' must be of type 'int'."`
- `"Field 'status' must be one of: active, inactive, pending"`
- `"Field 'name' length must be at least 2."`
- `"Field 'email' does not match pattern."`

## Tips Penggunaan

1. **Validasi Incremental**: Mulai dengan validasi dasar, tambah kompleksitas gradually
2. **Testing**: Test schema dengan berbagai input edge cases
3. **Performance**: Validasi kompleks dapat mempengaruhi performa insert/update
4. **Migration**: Update schema existing collections dengan hati-hati

## Kombinasi dengan Traits Lain

Schema validation bekerja baik dengan traits lain:

```php
$collection
    ->setSchema($schema)           // Validasi
    ->setEncryptionKey('key')      // Enkripsi
    ->on('beforeInsert', $validator) // Hooks tambahan
    ->useSoftDeletes(true);        // Soft deletes
```

## Schema Evolution

Untuk mengubah schema existing collection:

```php
// Load schema lama
$oldSchema = $collection->getSchema();

// Modifikasi
$newSchema = array_merge($oldSchema, [
    'new_field' => ['type' => 'string', 'required' => false]
]);

// Apply schema baru
$collection->setSchema($newSchema);
$collection->saveConfiguration();
```
