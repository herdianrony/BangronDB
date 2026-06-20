# BangronDB 1.0.0

BangronDB 1.0.0 adalah rilis stabil awal untuk database dokumen berbasis SQLite dengan API bergaya MongoDB untuk PHP.

## Highlights

- Database dokumen ringan tanpa server terpisah
- Backend SQLite file-based dan in-memory
- Enkripsi dokumen dengan AES-256-GCM
- Searchable fields untuk data terenkripsi
- Hooks, schema validation, soft deletes, populate, indexing, dan metrics
- API lifecycle yang lebih eksplisit untuk database dan collection
- `selectDB()` dan `selectCollection()` kini non-lazy
- Semua examples sudah diperbarui dan diverifikasi berjalan

## Explicit lifecycle API

### Database

- `createDB()`
- `dbExists()`
- `selectDB()`
- `renameDB()`
- `dropDB()`

### Collection (Client-level)

- `createCollection()`
- `collectionExists()`
- `selectCollection()`
- `renameCollection()`
- `dropCollection()`

### Collection (Database-level)

- `createCollection()`
- `collectionExists()`
- `selectCollection()`
- `renameCollection()`
- `dropCollection()`

## Backward compatibility note

Jika Anda sebelumnya mengandalkan create implicit saat `selectDB()` atau `selectCollection()`, lihat:

- `BACKWARD_COMPATIBILITY_NOTES.md`

## Validation status

- 292 tests
- 862 assertions
- seluruh test suite lulus
- examples 01–15 berhasil dijalankan

## Docs included

- `README.md`
- `examples/README.md`
- `SECURITY_USAGE_GUIDE.md`
- `BACKWARD_COMPATIBILITY_NOTES.md`
- `RELEASE_NOTES_1.0.0.md`
