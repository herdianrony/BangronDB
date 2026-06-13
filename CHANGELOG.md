# Changelog

Semua perubahan notable pada project ini akan dicatat di file ini.

Format mengikuti [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) dan [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-03-25

### Security

- Operator `$where` dan `$func` hanya menerima Closure (mencegah RCE)
- Field name validation dengan whitelist (mencegah NoSQL injection)
- PRAGMA key escaping (mencegah SQLite injection)
- Database path validation (mencegah path traversal)
- Regex delimiter escaping (mencegah ReDoS)
- `declare(strict_types=1)` di semua file core
- Removed `@` error suppression operators
- Added `FieldValidator` utility class (`src/Security/FieldValidator.php`)
- 36 new security tests (total: 273 tests, 810 assertions)

### Breaking Changes

- `$where` dan `$func` tidak lagi menerima string function names
- Field names harus alfanumerik + `_`, `-`, `.` saja
- Lihat [SECURITY_USAGE_GUIDE.md](SECURITY_USAGE_GUIDE.md) untuk panduan migrasi

## [1.0.0] - 2024-01-15

### Added

- MongoDB-like API untuk PHP
- SQLite backend dengan ACID compliance
- AES-256-CBC encryption (field-level dan collection-level)
- Searchable encrypted fields dengan SHA-256 hashing
- Hooks system (beforeInsert, afterInsert, beforeUpdate, afterUpdate, beforeRemove, afterRemove)
- Relationships / Populate antar collections
- Schema validation (type, enum, regex, range)
- Soft deletes dengan restore
- Multiple ID modes (UUID, manual, prefix)
- Query operators ($gt, $gte, $lt, $lte, $ne, $in, $nin, $exists, $or, $and, $regex, $where, $func, $fuzzy)
- Indexing dengan json_extract
- Health monitoring & metrics
- Change notification
- Dynamic configuration per collection
- Transaction support
- Cross-database operations (attach/detach)
- 273 unit tests

[1.0.1]: https://github.com/herdianrony/BangronDB/releases/tag/v1.0.1
[1.0.0]: https://github.com/herdianrony/BangronDB/releases/tag/v1.0.0
