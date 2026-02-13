# Client

Kelas utama untuk mengelola database BangronDB. Bertanggung jawab untuk membuat koneksi ke database dan mengelola koleksi.

## Properti

### `$databases`

- **Tipe**: `array<string,\BangronDB\Database>`
- **Deskripsi**: Array yang menyimpan instance database yang telah dibuat.

### `$path`

- **Tipe**: `string`
- **Deskripsi**: Path ke file database atau `:memory:` untuk database in-memory.

## Konstanta

### `DATABASE_NAME_REGEX`

- **Nilai**: `'/^[a-z0-9_-]+$/i'`
- **Deskripsi**: Regex untuk validasi nama database.

## Metode

### `__construct(string $path, array $options = [])`

Konstruktor untuk membuat instance Client baru.

**Parameter:**

- `$path` (string): Path ke file database atau `:memory:`
- `$options` (array): Opsi konfigurasi client

**Contoh:**

```php
$client = new Client('/path/to/db');
```

### `listDBs(): array`

Mengembalikan daftar nama database.

**Return:** Array nama database

### `selectCollection(string $database, string $collection): Collection`

Memilih koleksi dari database tertentu.

**Parameter:**

- `$database` (string): Nama database
- `$collection` (string): Nama koleksi

**Return:** Instance `Collection`

### `selectDB(string $name, array $options = []): Database`

Memilih atau membuat database baru.

**Parameter:**

- `$name` (string): Nama database
- `$options` (array): Opsi khusus database

**Return:** Instance `Database`

### `__get(string $database): Database`

Magic getter untuk akses database.

**Parameter:**

- `$database` (string): Nama database

**Return:** Instance `Database`

**Contoh:**

```php
$db = $client->mydatabase;
```

### `close(): void`

Menutup semua koneksi database yang dikelola oleh client ini.

### `__destruct()`

Destruktor yang memastikan semua koneksi ditutup.

## Metode Private

### `normalizePath(string $path): string`

Menormalkan path dengan menghapus slash di akhir.

### `getMemoryDatabaseNames(): array`

Mendapatkan nama database dari memory (hanya untuk database in-memory).

### `getDiskDatabaseNames(): array`

Mendapatkan nama database dari disk dengan membaca direktori.

### `isDatabaseFile(\SplFileInfo $fileInfo): bool`

Memeriksa apakah file adalah file database BangronDB (berakhiran .bangron).

### `validateDatabaseName(string $name): void`

Memvalidasi nama database terhadap regex.

### `createDatabaseInstance(string $name, array $options = []): Database`

Membuat instance Database baru dengan opsi yang digabungkan.

### `buildDatabasePath(string $name): string`

Membuat path lengkap untuk file database.

## Contoh Penggunaan

```php
use BangronDB\Client;

// Membuat client untuk database file
$client = new Client('/path/to/databases');

// Membuat client untuk database in-memory
$client = new Client(':memory:');

// Melihat daftar database
$databases = $client->listDBs();

// Memilih database
$db = $client->selectDB('myapp');

// Mengakses koleksi
$collection = $client->selectCollection('myapp', 'users');

// Menggunakan magic getter
$db = $client->myapp;
$collection = $db->users;

// Menutup koneksi
$client->close();
```
