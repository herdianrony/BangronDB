# Backward Compatibility Notes

Dokumen ini merangkum perubahan perilaku penting yang perlu diperhatikan saat migrasi ke API lifecycle eksplisit BangronDB.

## Perubahan Utama

### 1. `selectDB()` sekarang non-lazy

Sebelumnya:

```php
$client->selectDB('app');
```

Jika database belum ada, pemanggilan ini dapat membuat database secara implisit.

Sekarang:

- `selectDB('app')` hanya memilih database yang sudah ada
- jika database belum ada, method akan melempar `DatabaseException`

Gunakan:

```php
$client->createDB('app');
$db = $client->selectDB('app');
```

## 2. `selectCollection()` sekarang non-lazy

Sebelumnya:

```php
$db->selectCollection('users');
```

Jika collection belum ada, pemanggilan ini dapat membuat collection secara implisit.

Sekarang:

- `selectCollection('users')` hanya memilih collection yang sudah ada
- jika collection belum ada, method akan melempar `CollectionException`

Gunakan:

```php
$db->createCollection('users');
$users = $db->selectCollection('users');
```

Atau dari level client:

```php
$client->createCollection('app', 'users');
$users = $client->selectCollection('app', 'users');
```

## 3. Magic getter juga ikut non-lazy

Contoh berikut sekarang juga mengasumsikan resource sudah dibuat lebih dulu:

```php
$client->app;
$db->users;
```

Jadi pattern yang aman adalah:

```php
$client->createDB('app');
$db = $client->app;

$db->createCollection('users');
$users = $db->users;
```

## 4. API eksplisit yang dianjurkan

### Database lifecycle

- `createDB()`
- `dbExists()`
- `selectDB()`
- `renameDB()`
- `dropDB()`

### Collection lifecycle dari `Client`

- `createCollection()`
- `collectionExists()`
- `selectCollection()`
- `renameCollection()`
- `dropCollection()`

### Collection lifecycle dari `Database`

- `createCollection()`
- `collectionExists()`
- `selectCollection()`
- `renameCollection()`
- `dropCollection()`

## 5. Format persistensi `id_mode` prefix dinormalisasi

Jika Anda menggunakan `saveConfiguration()` dengan mode ID prefix, format yang dipersist ke database kini dinormalisasi menjadi:

```php
prefix:USR
```

Sebelumnya beberapa konfigurasi dapat tersimpan sebagai prefix mentah, misalnya:

```php
USR
```

Keduanya tetap bisa dibaca, tetapi format baru yang direkomendasikan dan akan disimpan selanjutnya adalah `prefix:...`.

## Pola migrasi cepat

### Sebelum

```php
$client = new Client(__DIR__ . '/data');
$db = $client->selectDB('app');
$users = $db->selectCollection('users');
```

### Sesudah

```php
$client = new Client(__DIR__ . '/data');
$client->createDB('app');
$client->createCollection('app', 'users');

$db = $client->selectDB('app');
$users = $db->selectCollection('users');
```
