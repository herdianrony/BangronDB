# Database

Kelas untuk mengelola koneksi database SQLite dan operasi-operasi database level. Bertanggung jawab untuk koneksi PDO, fungsi custom SQLite, dan manajemen koleksi.

## Konstanta

### `DSN_PATH_MEMORY`

- **Nilai**: `':memory:'`
- **Deskripsi**: DSN untuk database in-memory

### `COLLECTION_NAME_REGEX`

- **Nilai**: `'/^[A-Za-z0-9_]+$/'`
- **Deskripsi**: Regex untuk validasi nama koleksi

### `IDENTIFIER_REGEX`

- **Nilai**: `'/^[A-Za-z0-9_]+$/'`
- **Deskripsi**: Regex untuk validasi identifier database

## Properti

### `$path`

- **Tipe**: `string`
- **Deskripsi**: Path ke file database

### `$client`

- **Tipe**: `?Client`
- **Deskripsi**: Referensi ke client yang membuat database ini

### `$encryptionKey`

- **Tipe**: `?string`
- **Deskripsi**: Kunci enkripsi untuk database level

### `$connection`

- **Tipe**: `\PDO`
- **Deskripsi**: Koneksi PDO ke database SQLite

## Metode Konstruktor

### `__construct(string $path = self::DSN_PATH_MEMORY, array $options = [])`

Membuat instance Database baru dengan koneksi PDO.

**Parameter:**

- `$path` (string): Path database atau `:memory:`
- `$options` (array): Opsi koneksi PDO

**Opsi yang didukung:**

- `encryption_key`: Kunci enkripsi database level

**Contoh:**

```php
// Database file
$db = new Database('/path/to/mydb.bangron');

// Database in-memory
$db = new Database(':memory:');

// Database dengan enkripsi
$db = new Database('/path/to/encrypted.db', [
    'encryption_key' => 'my-secret-key'
]);
```

## Metode Koleksi

### `createCollection(string $name): void`

Membuat tabel koleksi baru di database.

**Parameter:**

- `$name` (string): Nama koleksi

**Throws:** `\InvalidArgumentException` jika nama tidak valid

### `dropCollection(string $name): void`

Menghapus tabel koleksi dari database.

**Parameter:**

- `$name` (string): Nama koleksi

### `getCollectionNames(): array`

Mengembalikan array nama semua koleksi di database.

**Return:** Array nama koleksi

### `listCollections(): array`

Mengembalikan array instance Collection untuk semua koleksi.

**Return:** Array `Collection`

### `selectCollection(string $name): Collection`

Memilih atau membuat koleksi baru.

**Parameter:**

- `$name` (string): Nama koleksi

**Return:** Instance `Collection`

### `__get(string $collection): Collection`

Magic getter untuk akses koleksi.

**Parameter:**

- `$collection` (string): Nama koleksi

**Return:** Instance `Collection`

**Contoh:**

```php
$users = $db->selectCollection('users');
// atau
$users = $db->users;
```

## Metode Index

### `createJsonIndex(string $collection, string $field, ?string $indexName = null): void`

Membuat index JSON untuk field tertentu menggunakan `json_extract()`.

**Parameter:**

- `$collection` (string): Nama koleksi
- `$field` (string): Field untuk di-index
- `$indexName` (string): Nama index opsional

### `dropIndex(string $indexName): void`

Menghapus index berdasarkan nama.

**Parameter:**

- `$indexName` (string): Nama index

### `quoteIdentifier(string $name): string`

Meng-quote identifier database dengan aman.

**Parameter:**

- `$name` (string): Identifier untuk di-quote

**Return:** Identifier yang sudah di-quote

## Metode Kriteria dan Query

### `registerCriteriaFunction($criteria): ?string`

Mendaftarkan fungsi kriteria untuk query kompleks.

**Parameter:**

- `$criteria` (mixed): Kriteria pencarian

**Return:** ID fungsi yang terdaftar atau null

### `callCriteriaFunction(string $id, $document): bool`

Menjalankan fungsi kriteria terdaftar.

**Parameter:**

- `$id` (string): ID fungsi kriteria
- `$document` (mixed): Dokumen untuk dievaluasi

**Return:** True jika cocok

## Metode Maintenance

### `vacuum(): void`

Menjalankan VACUUM untuk mengoptimalkan database dan mengklaim space.

### `close(): void`

Menutup koneksi database dan membersihkan registry.

### `drop(): void`

Menghapus file database (hanya untuk database file, bukan memory).

## Metode Health dan Metrics

### `getHealthMetrics(): array`

Mengembalikan metrics kesehatan database komprehensif.

**Return:** Array metrics kesehatan

### `checkIntegrity(): array`

Memeriksa integritas database menggunakan PRAGMA integrity_check.

**Return:** Array hasil integritas

### `getDataMetrics(): array`

Mengembalikan metrics data untuk semua koleksi.

**Return:** Array metrics data

### `getPerformanceMetrics(): array`

Mengembalikan metrics performa database.

**Return:** Array metrics performa

### `getIndexMetrics(): array`

Mengembalikan metrics index database.

**Return:** Array metrics index

### `getCollectionMetrics(): array`

Mengembalikan metrics detail untuk setiap koleksi.

**Return:** Array metrics koleksi

### `getHealthReport(): array`

Menghasilkan laporan kesehatan summary dengan status, issues, dan rekomendasi.

**Return:** Array laporan kesehatan

## Metode Konfigurasi Koleksi

### `saveCollectionConfig(string $collectionName, array $config): void`

Menyimpan konfigurasi koleksi ke tabel \_collections.

**Parameter:**

- `$collectionName` (string): Nama koleksi
- `$config` (array): Konfigurasi koleksi

### `loadCollectionConfig(string $collectionName): array`

Memuat konfigurasi koleksi dari database.

**Parameter:**

- `$collectionName` (string): Nama koleksi

**Return:** Array konfigurasi

### `getAllCollectionConfigs(): array`

Mengembalikan semua konfigurasi koleksi.

**Return:** Array konfigurasi koleksi

### `deleteCollectionConfig(string $collectionName): void`

Menghapus konfigurasi koleksi dari database.

**Parameter:**

- `$collectionName` (string): Nama koleksi

## Metode Utilitas

### `tableHasColumn(string $tableName, string $columnName): bool`

Memeriksa apakah tabel memiliki kolom tertentu.

**Parameter:**

- `$tableName` (string): Nama tabel
- `$columnName` (string): Nama kolom

**Return:** True jika kolom ada

## Static Methods

### `closeAll(): void`

Menutup semua instance Database yang aktif (best-effort cleanup).

## Contoh Penggunaan

```php
use BangronDB\Database;

// Membuat database baru
$db = new Database('/path/to/myapp.db');

// Membuat koleksi
$db->createCollection('users');
$db->createCollection('posts');

// Mengakses koleksi
$users = $db->selectCollection('users');
$posts = $db->users; // Magic getter

// Membuat index
$db->createJsonIndex('users', 'email');
$db->createJsonIndex('posts', 'author_id');

// Mendapatkan metrics
$health = $db->getHealthMetrics();
$report = $db->getHealthReport();

// Maintenance
$db->vacuum();

// Cleanup
$db->close();
```

## Metrics Yang Tersedia

### Health Metrics

- `database`: Info database (path, type, encryption)
- `integrity`: Status integritas database
- `metrics`: Metrics data umum
- `performance`: Metrics performa (page stats, fragmentation)
- `collections`: Metrics per koleksi

### Data Metrics

- `total_collections`: Jumlah koleksi
- `total_documents`: Total dokumen
- `total_size_bytes`: Total ukuran
- `avg_document_size`: Rata-rata ukuran dokumen
- `collections`: Metrics per koleksi (documents, size, avg_size)

### Collection Metrics

- `documents`: Jumlah dokumen
- `size_bytes`: Ukuran total
- `indexes`: Daftar index
- `index_count`: Jumlah index
- `hooks`: Jumlah hooks per event
- `encryption_enabled`: Status enkripsi
- `id_mode`: Mode ID generation
- `searchable_fields`: Field pencarian
