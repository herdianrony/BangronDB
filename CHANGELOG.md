# Changelog

Semua perubahan penting pada project ini dicatat di file ini.

Format mengikuti [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) dan [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Draft release notes untuk rilis patch berikutnya
- Test tambahan untuk explicit upsert, akurasi bulk delete, reload searchable fields, sinkronisasi rename, dan runtime encryption key update

### Changed

- Dokumentasi utama, panduan kontribusi, panduan keamanan, dan dokumentasi examples dirapikan agar sinkron dengan implementasi saat ini
- Penanganan metadata collection dipusatkan ke `Database` untuk mengurangi duplikasi logika
- Jalur explicit upsert disederhanakan agar reuse lifecycle `insert()` / `update()`

### Fixed

- `Database::setEncryptionKey()` kini aman dipanggil saat runtime
- `save()` dengan `_id` sekarang memicu hooks dan change tracking secara konsisten
- `remove()` pada jalur bulk delete kini mengembalikan jumlah dokumen yang benar
- Searchable fields kini tetap persist setelah collection dibuka ulang
- Rename collection kini ikut menyinkronkan cache, metadata, dan konfigurasi tersimpan
- Validasi searchable field diperketat sebelum dipakai membentuk nama kolom SQL

## [1.0.1] - 2026-03-25

### Security

- Operator `$where` dan `$func` hanya menerima Closure untuk mencegah RCE
- Validasi nama field menggunakan whitelist untuk mengurangi risiko injection
- PRAGMA key escaping untuk mengurangi risiko SQLite injection
- Validasi path database untuk mengurangi risiko path traversal
- Regex hardening untuk mengurangi risiko ReDoS
- `declare(strict_types=1)` di semua file core
- Penambahan utilitas `FieldValidator` (`src/Security/FieldValidator.php`)

### Breaking Changes

- `$where` dan `$func` tidak lagi menerima string function names
- Nama field harus menggunakan karakter alfanumerik, `_`, `-`, atau `.`
- Lihat [SECURITY_USAGE_GUIDE.md](SECURITY_USAGE_GUIDE.md) untuk panduan migrasi

## [1.0.0] - 2024-01-15

### Added

- MongoDB-like API untuk PHP
- Backend SQLite dengan dukungan file-based dan in-memory storage
- Enkripsi dokumen dan collection
- Searchable encrypted fields dengan hashing
- Hooks system (`beforeInsert`, `afterInsert`, `beforeUpdate`, `afterUpdate`, `beforeRemove`, `afterRemove`)
- Relationships / populate antar-collection dan antar-database dalam satu client
- Schema validation (type, enum, regex, range)
- Soft deletes dengan restore
- Multiple ID modes (UUID, manual, prefix)
- Query operators (`$gt`, `$gte`, `$lt`, `$lte`, `$ne`, `$in`, `$nin`, `$exists`, `$or`, `$and`, `$regex`, `$where`, `$func`, `$fuzzy`)
- Indexing berbasis `json_extract`
- Health monitoring & metrics
- Change notification
- Dynamic configuration per collection
- Transaction support via PDO SQLite

[1.0.1]: https://github.com/herdianrony/BangronDB/releases/tag/v1.0.1
[1.0.0]: https://github.com/herdianrony/BangronDB/releases/tag/v1.0.0
