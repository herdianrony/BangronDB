# BangronDB 1.0.0

BangronDB 1.0.0 adalah rilis stabil awal untuk database dokumen berbasis SQLite di PHP dengan API bergaya MongoDB.

## ✨ Highlights

- **SQLite document database** yang ringan, tanpa server terpisah
- **MongoDB-like document API** untuk CRUD dan query fleksibel
- **AES-256-GCM encryption** untuk data sensitif
- **Searchable fields** untuk query pada data terenkripsi
- **Hooks, schema validation, soft deletes, populate, indexing, dan metrics**
- **Lifecycle API eksplisit** untuk database dan collection
- **`selectDB()` dan `selectCollection()` kini non-lazy**
- **Path validation, regex hardening, dan exception hardening** untuk pengurangan risiko security foot-gun
- **Examples 01–15 sudah diperbarui dan diverifikasi berjalan**

## ✅ Explicit Lifecycle API

### Database

- `createDB()`
- `dbExists()`
- `selectDB()`
- `renameDB()`
- `dropDB()`

### Collection dari level Client

- `createCollection()`
- `collectionExists()`
- `selectCollection()`
- `renameCollection()`
- `dropCollection()`

### Collection dari level Database

- `createCollection()`
- `collectionExists()`
- `selectCollection()`
- `renameCollection()`
- `dropCollection()`

## 🔁 Migration Note

Jika sebelumnya Anda mengandalkan create implicit saat:

- `selectDB()`
- `selectCollection()`

maka sekarang gunakan API eksplisit:

- `createDB()` untuk membuat database
- `createCollection()` untuk membuat collection

Detail migrasi tersedia di:

- `BACKWARD_COMPATIBILITY_NOTES.md`

## 🧪 Validation Status

- **304 tests**
- **882 assertions**
- seluruh test suite lulus
- **examples 01–15 berhasil dijalankan**
- **PHPStan** lulus dengan baseline terkelola

## 📚 Docs & Resources

- README: `README.md`
- Examples guide: `examples/README.md`
- Security guide: `SECURITY_USAGE_GUIDE.md`
- Compatibility notes: `BACKWARD_COMPATIBILITY_NOTES.md`
- Release notes: `RELEASE_NOTES_1.0.0.md`

## 🚀 Install

```bash
composer require herdianrony/bangrondb
```
