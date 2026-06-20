# Draft Release Notes — BangronDB 1.0.2

> Status: draft
> Tanggal draft: 2026-06-20

BangronDB 1.0.2 berfokus pada stabilitas runtime, konsistensi lifecycle dokumen, pengurangan duplikasi internal, dan sinkronisasi dokumentasi dengan perilaku library saat ini.

## Highlight

### Konsistensi `save()` / explicit upsert
Jalur `save()` dengan `_id` sekarang mengikuti alur yang konsisten dengan `insert()` dan `update()`, sehingga:

- hooks tetap terpanggil
- change notification ikut diperbarui
- behavior explicit upsert lebih mudah diprediksi

### Bulk delete return value lebih akurat
`remove()` pada jalur bulk delete sekarang mengembalikan jumlah dokumen yang benar-benar terhapus.

### Searchable fields lebih stabil
Konfigurasi searchable fields sekarang tetap terbaca dengan benar setelah collection dibuka ulang.

### Rename collection lebih aman
Saat collection di-rename, referensi internal kini ikut disinkronkan, termasuk:

- cache collection
- metadata perubahan
- konfigurasi collection yang tersimpan

### Refactor metadata handling
Logika metadata collection dipusatkan ke `Database` untuk mengurangi duplikasi dan memudahkan maintenance ke depan.

## Fixed

- memperbaiki runtime edge case pada `Database::setEncryptionKey()`
- memperbaiki explicit upsert agar hooks dan change tracking berjalan konsisten
- memperbaiki hasil return pada bulk delete
- memperbaiki persistence searchable fields setelah reopen
- memperbaiki sinkronisasi cache / metadata / config setelah rename collection
- memperketat validasi nama searchable field

## Internal Improvements

- mengurangi duplikasi logika metadata antara trait dan manager
- menyederhanakan alur upsert agar reuse jalur lifecycle yang sudah ada
- menambah regression tests untuk kasus runtime yang sebelumnya lolos dari suite

## Documentation

- README diselaraskan dengan implementasi aktual
- changelog diperbarui
- panduan kontribusi diperjelas
- panduan keamanan diperbarui
- daftar examples diselaraskan dengan file yang benar-benar ada

## Test Status

Tervalidasi dengan:

- 277 tests
- 823 assertions
- seluruh test lulus

## Catatan Upgrade

Tidak ada breaking change yang direncanakan untuk patch release ini, tetapi disarankan untuk:

- meninjau alur `save()` jika sebelumnya mengandalkan behavior implicit yang tidak konsisten
- memastikan encryption key tetap disuplai dari runtime / environment
- menjalankan seluruh test aplikasi setelah upgrade
