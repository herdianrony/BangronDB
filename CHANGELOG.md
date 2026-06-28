# Changelog

Semua perubahan penting pada project ini dicatat di file ini.

Format mengikuti [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) dan [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-06-28

### Added

- `Client::listCollections($database)` (alias `listCollection()`) untuk menampilkan daftar nama collection dalam sebuah database; mengembalikan `[]` jika database tidak ada
- Constraint schema `'unique' => true` — `insert()` / `update()` menolak nilai duplikat dengan `ValidationException` (`UNIQUE_CONSTRAINT_VIOLATION`); nilai `null` dikecualikan, dan update pada dokumen yang sama tidak dianggap duplikat
- `ValidationException::uniqueConstraintViolation()` dan `Collection::validateUnique()`
- Regression tests: `ListCollectionsTest`, `UniqueConstraintTest`, `BlindIndexTest`

### Changed

- **Security:** searchable field ber-hash pada collection terenkripsi kini memakai **keyed HMAC blind index** (diturunkan dari encryption key via PBKDF2 salt khusus), menggantikan SHA-256 polos yang rentan brute-force/korelasi antar-DB; collection tanpa enkripsi tetap memakai SHA-256 (kompatibel)
- Validasi konfigurasi collection diperketat dengan `in_array(..., true)` untuk key config dan pengecekan collection existence di `CollectionManager`
- Validasi enum schema sekarang menggunakan strict comparison agar nilai seperti `0`, `false`, dan `'0'` tidak tertukar
- Format persistensi `id_mode` prefix dinormalisasi menjadi `prefix:VALUE`
- Loader konfigurasi tetap backward compatible untuk config lama yang masih menyimpan prefix mentah seperti `USR`

### Fixed

- `$in` / `$nin` pada searchable field ber-hash kini diarahkan ke SQL fast-path (nilai query ikut di-hash dengan blind index); sebelumnya jatuh ke matcher in-memory dan diam-diam mengembalikan kosong
- Edge case nested array pada operator `$in` / `$nin` sekarang ditolak eksplisit dengan exception yang jelas, alih-alih lolos ke PDO sebagai string `Array`
- Regression tests ditambahkan untuk enum strictness, prefix config compatibility, dan validasi `$in` / `$nin`

### Migration

- Setelah upgrade, jalankan `Collection::rehashSearchableField($field)` pada koleksi terenkripsi yang memakai searchable hashed field agar index lama (SHA-256) dimigrasikan ke keyed HMAC

## [1.0.0] - 2026-06-20

### Added

- MongoDB-like API untuk PHP di atas backend SQLite
- Storage file-based dan in-memory
- Enkripsi dokumen dengan AES-256-GCM
- Searchable fields untuk data terenkripsi
- Hooks untuk lifecycle insert, update, dan remove
- Relationships / populate antar-collection dan antar-database dalam satu client
- Schema validation (type, enum, regex, min/max)
- Soft deletes dengan restore dan force delete
- Multiple ID modes (UUID, manual, prefix)
- Query operators seperti `$gt`, `$gte`, `$lt`, `$lte`, `$ne`, `$in`, `$nin`, `$exists`, `$or`, `$and`, `$regex`, `$where`, `$func`, dan `$fuzzy`
- Indexing berbasis `json_extract`
- Health monitoring, metrics, dan integrity check
- Change notification per collection
- Dynamic configuration per collection
- Transaction support melalui PDO SQLite
- Client API yang lebih konsisten untuk lifecycle database: `createDB()`, `dbExists()`, `renameDB()`, dan `dropDB()`
- Client helper untuk lifecycle collection dari level atas: `createCollection()`, `collectionExists()`, `renameCollection()`, dan `dropCollection()`
- Database API yang lebih konsisten untuk lifecycle collection: `collectionExists()` dan `renameCollection($oldName, $newName)`
- `selectDB()` dan `selectCollection()` kini bersifat non-lazy, sehingga pembuatan resource dilakukan secara eksplisit
- Ditambahkan catatan migrasi kompatibilitas untuk perubahan dari lazy ke non-lazy
- Contoh aplikasi dan example scripts diperbarui ke API terbaru
- CI matrix diperbarui ke PHP 8.1+ dan static analysis PHPStan ditambahkan

### Changed

- Dokumentasi utama, changelog, panduan kontribusi, panduan keamanan, dan dokumentasi examples diselaraskan dengan implementasi aktual
- Penanganan metadata collection dipusatkan ke `Database` untuk mengurangi duplikasi internal
- Jalur explicit upsert disederhanakan agar mengikuti lifecycle `insert()` / `update()` yang sama

### Fixed

- `Database::setEncryptionKey()` kini aman dipanggil saat runtime
- `save()` dengan `_id` sekarang memicu hooks dan change tracking secara konsisten
- `remove()` pada jalur bulk delete kini mengembalikan jumlah dokumen yang benar
- Searchable fields tetap terbaca setelah collection dibuka ulang
- Rename collection kini ikut menyinkronkan cache, metadata, dan konfigurasi tersimpan
- Validasi searchable field diperketat sebelum dipakai membentuk nama kolom SQL
- Query execution exception tidak lagi mengekspos SQL dan parameter mentah lewat properti public

### Security

- `$where` dan `$func` hanya menerima Closure untuk mengurangi risiko RCE
- Validasi nama field menggunakan whitelist karakter yang aman
- PRAGMA key escaping untuk mengurangi risiko SQLite injection
- Regex hardening diperketat untuk membantu mengurangi risiko ReDoS bypass
- Validasi path database dan directory path kini dipanggil dari entry point utama
- Key derivation kini menggunakan salt per-database dengan fallback kompatibilitas untuk data lama
- `declare(strict_types=1)` di semua file core

[1.0.0]: https://github.com/herdianrony/BangronDB/releases/tag/v1.0.0
