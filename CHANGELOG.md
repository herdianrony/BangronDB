# Changelog

Semua perubahan penting di project ini akan didokumentasikan di file ini.

Format berbasis [Keep a Changelog](https://keepachangelog.com/id/1.1.0/),
dan project ini mengikuti [Semantic Versioning](https://semver.org/lang/id/spec/v2.0.0.html).

## [1.3.0] — 2026-07-13

### Added

- **CI/CD Pipeline** — GitHub Actions workflow dengan 5 PHP versions (8.1–8.5)
  - PHPUnit tests (376 tests, 1112 assertions)
  - PHPStan static analysis level 6
  - Syntax lint (src + tests + examples)
  - Examples smoke test (24 example scripts)
  - GitHub Pages auto-deploy untuk documentation site
- **Documentation Site** — React + Vite + Tailwind, 23 halaman docs
  - Dark/light mode toggle dengan localStorage
  - Purple-blue palette (Just the Docs inspired)
  - Custom markdown renderer dengan syntax highlighting
  - Sidebar navigasi dengan grouped categories
  - TOC auto-generated dari headings
  - Prev/Next navigation antar halaman
  - Code blocks dengan copy button + language label
- **6 Project Scenarios Documentation**:
  - ERP (inventory, sales, accounting, journal entries)
  - CRM (leads, opportunities, sales pipeline, activities)
  - SCM (purchase orders, goods receipt, shipments, stock movements)
  - HRIS (employees, attendance, leave, payroll, PII encryption)
  - POS (cash drawer, transactions, multi-outlet sync, offline-first)
  - Auth & ACL (login, register, JWT, ACL per-collection via setCustomConfig)
- **Modular Architecture Guide** — strategi multi-database dengan
  cross-database populate + event-driven hooks
- **Transaction Safety Sections** — panduan `$db->connection->beginTransaction()`
  untuk operasi multi-step yang WAJIB atomic, di semua dokumen skenario
- **Examples Smoke Test** — CI job yang run semua 24 example scripts
  end-to-end, menangkap bug yang tidak tertangkap PHPUnit
- **Composer Caching** — GitHub Actions cache dependencies untuk build lebih cepat
- **Concurrency Control** — cancel CI run lama saat push baru

### Fixed

- **Regex sanitizer false-positive** (Issue di Example 04)
  - `DANGEROUS_REGEX_PATTERNS` salah mendeteksi `\+?` (literal `+` opsional)
    sebagai pola ReDoS berbahaya
  - Legitimate regexes seperti `/^\+?[0-9]{10,15}$/` (phone numbers) di-downgrade
    ke literal matching, menyebabkan schema validation reject valid input
  - Fix: tambah negative lookbehind `(?<!\\\\)` di quantifier-detection patterns
- **SQLite "database table is locked" pada dropCollection** (Issue di Example 17)
  - `QueryExecutor` cache `PDOStatement` objects yang memegang schema lock
  - Saat `DROP TABLE` dieksekusi, SQLite menolak dengan SQLITE_LOCKED
  - Fix: clear statement cache sebelum drop di `Database::dropCollection()`
- **PHP 8.1/8.2 incompatibility — konstanta di trait**
  - Commit `bbb0702` memindahkan 5 konstanta enkripsi kembali ke `EncryptionTrait`,
    padahal PHP < 8.2 tidak support constants in traits
  - Fix: pindahkan konstanta ke `Collection` class, trait references via `self::`
- **PHP 8.1/8.2 incompatibility — typed class constants**
  - Commit `2692dc9` pakai syntax `public const int MAX_DOCUMENT_DEPTH = 20`
    yang baru di PHP 8.3+
  - Fix: hapus type annotation → `public const MAX_DOCUMENT_DEPTH = 20` (PHP 8.0+)
- **PHPStan 231 errors → 0 errors**
  - Tambah PHPDoc type annotations (array value types, return types, parameter types)
    di 24 source files
  - Fix class_alias catch-clause false positives di ChangeTrackingTrait
- **Broken documentation links** — 10 link ke file `.md` lama di-update ke
  URL React SPA yang valid
- **Internal link navigation** — link antar halaman docs sekarang navigate
  dalam SPA (tidak open new tab)

### Changed

- **PHPStan badge** — level 9 → level 6 (sesuai `phpstan.neon` actual config)
- **README redaksi** — natural POV author (Rony), lebih storytelling
- **`_config.yml` permalink** — gunakan `:name` variable di front-matter
  individual (Jekyll `defaults` tidak support permalink variables)
- **`_layouts/home.html`** — refactor jadi layout lengkap dengan includes,
  hapus duplikat dari `index.html`
- **Sidebar** — extract ke `_includes/sidebar.html`, grouped by category
  dengan active state highlighting
- **`.gitattributes`** — tambah export-ignore untuk folder non-inti
  (docs, examples, tests, CI config) — Packagist distribution sekarang
  hanya berisi `src/` + `composer.json` + `README.md` + `LICENSE`

### Removed

- **`_layouts/default.html`** — dead code, fungsinya sama dengan `home.html`
- **Jekyll documentation** — diganti dengan React + Vite + Tailwind
  (backup ada di branch `backup-jekyll-docs`)

## [1.2.0] — 2026-06-29

### Added

- Encryption v2 dengan AES-256-GCM + key rotation
- Searchable fields (blind index SHA-256) untuk query pada data terenkripsi
- Hooks lifecycle (before/after insert, update, remove)
- Schema validation: type, enum/options, regex, min/max, unique constraint
- Aggregation pipeline: $match, $group, $sort, $limit, $skip, $project, $count, $unset
- Soft delete dengan restore dan force delete
- TTL (Time-To-Live) auto-expiration
- Cursor streaming via PHP Generator untuk efisiensi memori
- ID mode fleksibel: UUID, manual, prefix
- Populate relasi antar-collection dan antar-database
- EXPLAIN query plan dan optimization suggestions
- Health metrics, integrity check, dan change notification
- Konfigurasi collection yang persisten ke database
- Security auditor utilitas (opsional)

## [1.1.0] — 2026-06-20

### Added

- Security: BangronDB v1.1.0 — Encryption v2, key rotation, config hardening

## [1.0.0] — 2026-06-15

### Added

- Initial release
- MongoDB-style API untuk operasi dokumen
- Backend SQLite berbasis file atau in-memory
- Dual query strategy: SQL-first via json_extract, fallback ke PHP-side

[1.3.0]: https://github.com/herdianrony/BangronDB/releases/tag/v1.3.0
[1.2.0]: https://github.com/herdianrony/BangronDB/releases/tag/v1.2.0
[1.1.0]: https://github.com/herdianrony/BangronDB/releases/tag/v1.1.0
[1.0.0]: https://github.com/herdianrony/BangronDB/releases/tag/v1.0.0
