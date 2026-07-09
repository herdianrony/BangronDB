# BangronDB Roadmap

> **BangronDB akan selalu memilih kesederhanaan daripada kompleksitas. Setiap fitur baru harus memperkuat kemampuannya sebagai Embedded Document Database, bukan mengubahnya menjadi ORM atau Framework.**

---

## Visi

> **BangronDB akan tetap menjadi Embedded Document Database untuk PHP yang ringan, aman, mudah digunakan, dan kaya fitur.**

BangronDB **tidak bertujuan menjadi ORM**, **tidak bertujuan menjadi Framework**, dan **tidak bertujuan menggantikan database server seperti MongoDB atau PostgreSQL**.

Setiap fitur baru harus memperkuat identitas BangronDB sebagai **embedded document database**, bukan memperluas ruang lingkupnya.

---

## Prinsip Pengembangan

Sebelum menerima sebuah fitur baru, maintainer harus menjawab tiga pertanyaan berikut:

### 1. Apakah fitur ini membuat BangronDB menjadi Document Database yang lebih baik?

Jika **tidak**, maka fitur tersebut kemungkinan tidak perlu.

### 2. Apakah fitur ini tetap menjaga BangronDB tetap ringan?

Jika fitur tersebut menambah kompleksitas yang tidak sebanding dengan manfaatnya, maka sebaiknya ditolak.

### 3. Apakah fitur ini bisa diterapkan tanpa mengubah API yang sudah ada?

Backward compatibility harus menjadi prioritas.

---

## Prioritas 1 — Stabilitas (v1.x)

### Tujuan

Meningkatkan kualitas internal tanpa menambah kompleksitas API.

### Fokus

- Bug fixing
- Peningkatan performa query
- Optimasi penggunaan memori
- Konsistensi API
- Dokumentasi lengkap
- Test coverage tinggi

### Target

- Test coverage >95%
- Semua contoh (`examples/`) dapat dijalankan tanpa modifikasi
- Seluruh fitur memiliki dokumentasi
- Setiap bug memiliki regression test

---

## Prioritas 2 — Pengalaman Pengembang (Developer Experience)

BangronDB harus mudah dipelajari oleh developer PHP yang belum pernah menggunakan document database.

### Fokus

- Dokumentasi lebih baik
- Contoh penggunaan nyata (real-world examples)
- Pesan error yang informatif
- Konsistensi penamaan method
- API Reference yang lengkap

### Bukan Fokus

- Wizard
- GUI
- Code Generator
- IDE Plugin

Karena tujuan BangronDB adalah library, bukan IDE.

---

## Prioritas 3 — Kemampuan Document Database

Setelah stabil, fitur baru harus memperkuat kemampuan sebagai document database.

### Projection

```php
find(
    [],
    [
        'projection' => [
            'password' => 0
        ]
    ]
);
```

**Alasan:** Mengurangi data yang dikembalikan dan meningkatkan performa.

### Bulk Operations

```php
insertMany();
updateMany();
deleteMany();
```

**Alasan:** Operasi massal adalah kebutuhan umum pada document database.

### Aggregation

```php
aggregate([
    ['$match' => ...],
    ['$group' => ...],
    ['$sort' => ...]
]);
```

**Alasan:** Memungkinkan analisis data tanpa memindahkan seluruh dokumen ke PHP.

### Explain Query

```php
$collection->explain(...)
```

Menampilkan:

- Index yang digunakan
- Full scan atau tidak
- Jumlah dokumen yang dipindai
- Waktu eksekusi

**Alasan:** Mempermudah optimasi performa.

### Cursor Streaming

Menggunakan generator (`yield`) agar data besar tidak dimuat sekaligus ke memori.

**Alasan:** Mengurangi konsumsi memori untuk dataset besar.

### TTL Document

Dokumen dapat memiliki waktu kedaluwarsa otomatis.

Contoh penggunaan:

- Session
- OTP
- Cache
- Temporary Token

---

## Prioritas 4 — Security

Karena keamanan merupakan nilai jual BangronDB, pengembangan harus difokuskan pada penyempurnaan fitur yang sudah ada.

### Fokus

- Audit terhadap implementasi enkripsi
- Peningkatan proses rotasi kunci
- Validasi konfigurasi keamanan
- Dokumentasi keamanan yang lebih lengkap

### Tidak Perlu

Menambahkan algoritma enkripsi baru hanya demi variasi. Lebih baik satu algoritma yang kuat dan terdokumentasi dengan baik.

---

## Prioritas 5 — Monitoring

Monitoring hanya sebatas kesehatan database.

Contoh:

- Jumlah dokumen
- Ukuran database
- Status index
- Waktu query
- Fragmentasi database

### Bukan

- Dashboard Admin
- Monitoring server
- Logging framework

---

## Yang Tidak Akan Dibuat

Bagian ini penting agar arah proyek tetap jelas.

### ORM

Tidak akan ada:

```php
User::find();
User::save();
```

BangronDB tetap menggunakan Collection API.

### Active Record

Tidak akan diimplementasikan.

### MVC

Tidak menjadi bagian BangronDB.

### Repository

Developer bebas membuat repository sendiri di proyek masing-masing. BangronDB tidak menyediakan implementasi repository bawaan.

### Framework

BangronDB bukan framework.

Tidak akan ada:

- Router
- Controller
- Middleware
- Dependency Injection
- Service Container

### Code Generator

Tidak akan ada:

```
make:model
make:controller
make:repository
```

### SQL Builder

BangronDB bukan SQL abstraction layer.

### ORM Relationship

Tidak akan ada:

```php
belongsTo();
hasMany();
hasOne();
```

BangronDB tetap menggunakan konsep **Populate**.

---

## Filosofi API

API baru harus memenuhi prinsip berikut:

- Konsisten dengan gaya MongoDB
- Mudah dipelajari
- Tidak mengejutkan pengguna
- Sedikit method tetapi kuat
- Tidak menambah konfigurasi yang tidak perlu

---

## Kriteria Sebelum Merilis Versi Mayor

Versi mayor hanya dirilis jika memenuhi syarat berikut:

- Tidak ada perubahan API yang tidak perlu
- Dokumentasi lengkap
- Test coverage tinggi
- Performa meningkat dibanding versi sebelumnya
- Semua fitur utama telah stabil

Versi mayor bukan karena jumlah fitur bertambah, melainkan karena kualitas keseluruhan meningkat.

---

## Definisi "Selesai"

Sebuah fitur dianggap selesai jika:

- Kode telah diimplementasikan
- Memiliki dokumentasi
- Memiliki contoh penggunaan
- Memiliki unit test
- Memiliki integration test (jika relevan)
- Tidak mengurangi kompatibilitas dengan fitur yang sudah ada

---

## Feature First, Not Layer First

BangronDB berkembang berdasarkan **kemampuan document database**, bukan berdasarkan pola arsitektur enterprise.

| Contoh | Sesuai Visi? |
|--------|-------------|
| Menambahkan `aggregate()` | ✅ Ya |
| Menambahkan `bulkWrite()` | ✅ Ya |
| Menambahkan `projection()` | ✅ Ya |
| Menambahkan `RepositoryInterface` | ❌ Tidak |
| Menambahkan `ServiceContainer` | ❌ Bukan ruang lingkup |
| Menambahkan `Model` | ❌ Tanggung jawab aplikasi |

---

## Penutup

Kalau saya dipercaya menjadi maintainer BangronDB, saya akan menulis satu kalimat besar di bagian paling atas `ROADMAP.md`:

> **BangronDB akan selalu memilih kesederhanaan daripada kompleksitas. Setiap fitur baru harus memperkuat kemampuannya sebagai Embedded Document Database, bukan mengubahnya menjadi ORM atau Framework.**

Menurut saya, kalimat itu akan menjadi "kompas" setiap kali Anda menerima ide fitur baru. Sebelum mengimplementasikan sesuatu, Anda tinggal bertanya: **"Apakah ini membuat BangronDB menjadi document database yang lebih baik?"** Jika jawabannya tidak, kemungkinan besar fitur tersebut memang tidak perlu masuk ke inti BangronDB.