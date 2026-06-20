# BangronDB 1.0.0

Tanggal rilis: 2026-06-20

BangronDB 1.0.0 adalah rilis stabil awal untuk database dokumen berbasis SQLite dengan API bergaya MongoDB untuk PHP.

## Sorotan Utama

### Database dokumen ringan tanpa server terpisah
BangronDB dirancang untuk aplikasi PHP yang membutuhkan penyimpanan lokal atau embedded tanpa kompleksitas operasional database server terpisah.

### API bergaya MongoDB
Operasi dokumen seperti `insert`, `find`, `findOne`, `update`, `remove`, dan `save` menggunakan pola yang familiar bagi pengguna MongoDB.

### Enkripsi dan searchable fields
Rilis ini sudah mencakup:

- enkripsi dokumen dengan AES-256-GCM
- searchable fields untuk query pada data terenkripsi
- pemisahan yang jelas antara konfigurasi tersimpan dan encryption key runtime

### Hooks dan change tracking
Lifecycle dokumen mendukung hooks untuk insert, update, dan remove. Metadata perubahan collection juga tersedia melalui change notification.

### Schema validation dan soft deletes
BangronDB mendukung validasi schema dan soft deletes, sehingga lebih nyaman dipakai untuk aplikasi bisnis yang butuh guardrail tambahan.

## Included in 1.0.0

- backend SQLite file-based dan in-memory
- hooks system
- populate relasi
- dynamic configuration
- indexing
- health metrics dan integrity check
- transaction support melalui PDO SQLite
- examples untuk use case nyata

## Stabilization & polish included before first stable tag

Menjelang penetapan rilis stabil awal, beberapa area telah dirapikan:

- explicit upsert kini konsisten dengan lifecycle `insert()` / `update()`
- bulk delete mengembalikan jumlah dokumen yang akurat
- searchable fields tetap persist setelah collection dibuka ulang
- rename collection ikut menyinkronkan cache, metadata, dan konfigurasi
- dokumentasi diselaraskan dengan implementasi aktual

## Security guardrails

Rilis ini juga sudah mencakup guardrail penting:

- Closure-only untuk `$where` dan `$func`
- validasi nama field
- validasi path database
- PRAGMA key escaping
- regex hardening
- `strict_types=1` di semua file core

## Test status

Diverifikasi dengan:

- 277 tests
- 823 assertions
- seluruh test lulus
