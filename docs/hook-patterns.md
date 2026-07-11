---
layout: doc
title: "Hook Patterns"
description: "8 pola praktis hook."
toc: true
edit_on_github: true
prev:
  url: /docs/schema-metadata-guide/
  title: "Schema & Metadata"
next:
  url: /docs/security/
  title: "Security"
---
# Pola Penggunaan Hook

Panduan praktis untuk berbagai pola penggunaan hook system BangronDB dalam aplikasi nyata.

> **Referensi API lengkap:** Lihat [features.md#hooks-event-system](features.md#hooks-event-system)
>
> **Contoh dasar hook:** Lihat [../examples/11-hooks.php](../examples/11-hooks.php)
>
> **Contoh ACL dinamis:** Lihat [../examples/24-dynamic-acl-per-collection.php](../examples/24-dynamic-acl-per-collection.php)
>
> **Contoh nyata di e-commerce:** Lihat [../examples/19-ecommerce-app.php](../examples/19-ecommerce-app.php)

---

## Daftar Isi

1. [Auto-Timestamp](#1-auto-timestamp)
2. [Audit Logging](#2-audit-logging)
3. [ACL Dinamis per Collection](#3-acl-dinamis-per-collection)
4. [Transformasi Data / Sanitasi](#4-transformasi-data--sanitasi)
5. [Soft Validation (Aturan Bisnis)](#5-soft-validation-aturan-bisnis)
6. [Slug Generation](#6-slug-generation)
7. [Cascade Delete](#7-cascade-delete)
8. [Rate Limiting (Penghitung In-Memory)](#8-rate-limiting-penghitung-in-memory)
9. [Tips dan Trik](#tips-dan-trik)

---

## 1. Auto-Timestamp

**Kapan digunakan:** Hampir setiap aplikasi membutuhkan pencatatan waktu pembuatan dan pembaruan dokumen. Pola ini memastikan kolom `created_at` dan `updated_at` selalu terisi secara otomatis — tidak perlu diatur manual di setiap pemanggilan `insert()` atau `update()`.

```php
use BangronDB\Client;

$client = new Client('/path/to/data');
$db = $client->createDB('my_app');
$articles = $db->createCollection('articles');

// ── beforeInsert: set created_at dan updated_at ─────────
$articles->on('beforeInsert', function ($document) {
    $document['created_at'] = date('c');
    $document['updated_at'] = date('c');
    return $document;
});

// ── beforeUpdate: set updated_at ────────────────────────
$articles->on('beforeUpdate', function ($criteria, $data) {
    if (!isset($data['$set'])) {
        $data['$set'] = [];
    }
    $data['$set']['updated_at'] = date('c');
    return [$criteria, $data];
});

// ── Penggunaan ──────────────────────────────────────────
$id = $articles->insert(['title' => 'Pertama', 'body' => 'Isi artikel']);
// → dokumen otomatis berisi created_at & updated_at

$articles->update(['_id' => $id], ['$set' => ['title' => 'Judul Baru']]);
// → updated_at otomatis diperbarui, created_at tetap
```

### Catatan

- Gunakan format ISO 8601 (`date('c')`) agar konsisten dan mudah di-parse.
- Jika Anda menggunakan `$set` secara eksplisit, hook `beforeUpdate` secara aman menambahkan `updated_at` ke dalam `$set` yang sudah ada.
- Jika Anda melakukan `$inc` atau operator lain tanpa `$set`, pastikan hook menangani kasus tersebut — atau selalu gunakan `$set` bareng dengan operator lain.

---

## 2. Audit Logging

**Kapan digunakan:** Ketika Anda perlu mencatat siapa, kapan, dan apa yang berubah di database. Berguna untuk kepatuhan (compliance), debugging, dan keamanan. Pola ini menulis log ke collection `audit_log` terpisah menggunakan `afterInsert`, `afterUpdate`, dan `afterRemove`.

```php
use BangronDB\Client;

$client = new Client('/path/to/data');
$db = $client->createDB('my_app');

$products = $db->createCollection('products');
$auditLog = $db->createCollection('audit_log');

// ── Helper untuk menulis audit log ──────────────────────
function writeAudit(\BangronDB\Collection $auditLog, array $entry): void
{
    $entry['timestamp'] = date('c');
    $auditLog->insert($entry);
}

// ── afterInsert: log pembuatan dokumen ──────────────────
$products->on('afterInsert', function ($document, $insertId) use ($auditLog) {
    writeAudit($auditLog, [
        'action'    => 'insert',
        'collection'=> 'products',
        'document_id' => $insertId,
        'data'      => $document,
        'actor'     => getCurrentUser(), // implementasi Anda
    ]);
});

// ── afterUpdate: log pembaruan dokumen ──────────────────
$products->on('afterUpdate', function ($originalDoc, $updatedDocument) use ($auditLog) {
    writeAudit($auditLog, [
        'action'     => 'update',
        'collection' => 'products',
        'document_id'=> $originalDoc['_id'] ?? null,
        'old_data'   => $originalDoc,
        'new_data'   => $updatedDocument,
        'actor'      => getCurrentUser(),
    ]);
});

// ── afterRemove: log penghapusan dokumen ────────────────
$products->on('afterRemove', function ($document) use ($auditLog) {
    writeAudit($auditLog, [
        'action'     => 'remove',
        'collection' => 'products',
        'document_id'=> $document['_id'] ?? null,
        'deleted_data' => $document,
        'actor'      => getCurrentUser(),
    ]);
});

// ── Penggunaan ──────────────────────────────────────────
$id = $products->insert(['name' => 'Laptop', 'price' => 15000000]);
$products->update(['_id' => $id], ['$set' => ['price' => 14000000]]);
$products->remove(['_id' => $id]);

// Cek audit log
$logs = $auditLog->find(['collection' => 'products'])->sort(['timestamp' => 1])->toArray();
foreach ($logs as $log) {
    echo "[{$log['timestamp']}] {$log['action']} oleh {$log['actor']}\n";
}
```

### Catatan

- `afterUpdate` menerima dua parameter: dokumen asli dan dokumen yang sudah diperbarui. Anda bisa membandingkan keduanya untuk mencatat hanya field yang berubah.
- `afterRemove` menerima dokumen yang sudah dihapus — pastikan untuk menyimpan salinan data penting sebelum dokumen benar-benar hilang.
- Untuk performa, pertimbangkan untuk menggunakan TTL (Time-To-Live) pada collection `audit_log` jika log hanya perlu disimpan untuk jangka waktu tertentu. Lihat [contoh TTL](../examples/08-ttl-expiration.php).
- Return value dari hook `after*` diabaikan — tidak perlu mengembalikan apa pun.

---

## 3. ACL Dinamis per Collection

**Kapan digunakan:** Ketika aplikasi Anda membutuhkan kontrol akses berbeda-beda per collection, dan aturan ACL-nya bisa berubah saat runtime tanpa restart aplikasi. Pola ini menggabungkan `setCustomConfig('acl', [...])` untuk menyimpan aturan, dan hooks `beforeInsert`/`beforeUpdate`/`beforeRemove` untuk menegakkannya.

```php
use BangronDB\Client;

$client = new Client('/path/to/data');
$db = $client->createDB('acl_app');

$posts = $db->createCollection('posts');

// ── Definisikan ACL per collection ──────────────────────
$posts->setCustomConfig('acl', [
    'admin'  => ['create', 'read', 'update', 'delete'],
    'editor' => ['create', 'read', 'update'],
    'viewer' => ['read'],
]);
$posts->saveConfiguration(); // persisten ke disk

// ── Helper: enforce ACL via hooks ───────────────────────
function enforceAcl(\BangronDB\Collection $collection, string $currentRole): void
{
    $acl = $collection->getCustomConfig('acl', []);
    $allowed = $acl[$currentRole] ?? [];

    $hookPermissions = [
        'beforeInsert' => 'create',
        'beforeUpdate' => 'update',
        'beforeRemove' => 'delete',
    ];

    foreach ($hookPermissions as $event => $permission) {
        $collection->on($event, function ($doc) use ($currentRole, $permission, $allowed) {
            if (!in_array($permission, $allowed, true)) {
                error_log("ACL DENIED: role='{$currentRole}' permission='{$permission}'");
                return false; // batalkan operasi
            }
            return $doc;
        });
    }
}

// ── Penggunaan ──────────────────────────────────────────
// Simulasi: user saat ini adalah 'viewer'
$viewerPosts = $db->selectCollection('posts');
enforceAcl($viewerPosts, 'viewer');

$result = $viewerPosts->insert(['title' => 'Test']);
// → $result === false (ditolak oleh ACL)
```

### Catatan

- ACL disimpan via `setCustomConfig` dan `saveConfiguration`, sehingga **persisten** antar sesi. Namun **hooks tidak persisten** — Anda harus memanggil `enforceAcl()` ulang pada setiap startup/request.
- `beforeUpdate` **tidak mendukung `return false`** untuk membatalkan operasi. Untuk ACL pada update, gunakan pendekatan alternatif: modify `$criteria` agar tidak cocok dengan dokumen manapun, atau terapkan pengecekan di layer aplikasi sebelum memanggil `update()`.
- Untuk kontrol akses baca (`read`), tidak ada hook yang tersedia karena operasi `find()`/`findOne()` tidak memicu hook. Terapkan di middleware atau service layer.
- Contoh lengkap tersedia di [../examples/24-dynamic-acl-per-collection.php](../examples/24-dynamic-acl-per-collection.php).

---

## 4. Transformasi Data / Sanitasi

**Kapan digunakan:** Ketika data dari input pengguna perlu dibersihkan atau dinormalisasi sebelum disimpan — misalnya trim whitespace, normalisasi email ke huruf kecil, atau menghapus karakter berbahaya. Pola ini memastikan data selalu bersih di level database, terlepas dari apakah aplikasi sudah membersihkannya atau belum.

```php
use BangronDB\Client;

$client = new Client('/path/to/data');
$db = $client->createDB('my_app');
$users = $db->createCollection('users');

// ── Helper: sanitasi string secara rekursif ─────────────
function sanitizeDocument(array $doc): array
{
    foreach ($doc as $key => $value) {
        if (is_string($value)) {
            // Trim whitespace
            $doc[$key] = trim($value);

            // Normalisasi email
            if ($key === 'email') {
                $doc[$key] = strtolower($doc[$key]);
            }

            // Normalisasi nama (title case)
            if ($key === 'name' || $key === 'display_name') {
                $doc[$key] = ucwords(strtolower($doc[$key]));
            }

            // Hapus tag HTML dari field teks
            if (in_array($key, ['bio', 'address'], true)) {
                $doc[$key] = strip_tags($doc[$key]);
            }
        } elseif (is_array($value)) {
            $doc[$key] = sanitizeDocument($value);
        }
    }
    return $doc;
}

// ── beforeInsert: sanitasi dokumen baru ─────────────────
$users->on('beforeInsert', function ($document) {
    return sanitizeDocument($document);
});

// ── beforeUpdate: sanitasi data yang diperbarui ─────────
$users->on('beforeUpdate', function ($criteria, $data) {
    $data = sanitizeDocument($data);
    return [$criteria, $data];
});

// ── Penggunaan ──────────────────────────────────────────
$users->insert([
    'name'  => '  AHMAD  BUDI  ',
    'email' => 'AHMAD.BUDI@Example.COM  ',
    'bio'   => '<b>Developer</b> <script>alert("xss")</script>',
]);

$user = $users->findOne(['email' => 'ahmad.budi@example.com']);
// name:     "Ahmad Budi"
// email:    "ahmad.budi@example.com"
// bio:      "Developer alert("xss")"
```

### Catatan

- Hook ini berjalan **sebelum** validasi schema, sehingga data sudah bersih saat dicek oleh validator.
- Sanitasi bersifat rekursif sehingga nested array juga diproses.
- Jangan lupa untuk juga mendaftarkan hook `beforeUpdate` — banyak pengembang hanya mendaftarkan `beforeInsert` dan lupa bahwa data bisa masuk kotor melalui update.
- Untuk keamanan yang lebih kuat (pencegahan XSS di output), lakukan escaping di layer view/template, bukan hanya di hook.

---

## 5. Soft Validation (Aturan Bisnis)

**Kapan digunakan:** Ketika ada aturan bisnis yang tidak bisa diekspresikan melalui schema validasi — misalnya "hanya boleh 5 user aktif per plan", "stok tidak boleh negatif", atau "tidak boleh ada dua promo aktif di waktu yang sama". Schema validasi BangronDB cocok untuk aturan per-field, sedangkan soft validation di hook cocok untuk aturan lintas-dokumen atau lintas-collection.

```php
use BangronDB\Client;

$client = new Client('/path/to/data');
$db = $client->createDB('my_app');
$users = $db->createCollection('users');

// ── beforeInsert: batasi jumlah user aktif per plan ─────
$users->on('beforeInsert', function ($document) use ($users) {
    $plan = $document['plan'] ?? 'free';
    $maxPerPlan = [
        'free'     => 5,
        'starter'  => 20,
        'business' => 100,
        'enterprise' => PHP_INT_MAX,
    ];

    $limit = $maxPerPlan[$plan] ?? $maxPerPlan['free'];
    $currentCount = $users->count(['plan' => $plan, 'status' => 'active']);

    if ($currentCount >= $limit) {
        error_log("Validation: plan '{$plan}' sudah mencapai batas {$limit} user aktif");
        return false; // batalkan insert
    }

    return $document;
});

// ── beforeUpdate: cegah stok negatif ────────────────────
$products = $db->createCollection('products');
$products->on('beforeUpdate', function ($criteria, $data) use ($products) {
    // Jika operasi mengurangi stok
    if (isset($data['$inc']['stock']) && $data['$inc']['stock'] < 0) {
        $docs = $products->find($criteria)->toArray();
        foreach ($docs as $doc) {
            $newStock = ($doc['stock'] ?? 0) + $data['$inc']['stock'];
            if ($newStock < 0) {
                error_log("Validation: stok '{$doc['name']}' akan menjadi negatif ({$newStock})");
                // Ubah criteria agar tidak cocok — operasi tidak mengubah apa pun
                return [['_id' => '__never_match__'], $data];
            }
        }
    }
    return [$criteria, $data];
});

// ── Penggunaan ──────────────────────────────────────────
// Insert 5 user dengan plan 'free' — semua berhasil
for ($i = 1; $i <= 5; $i++) {
    $users->insert(['name' => "User {$i}", 'plan' => 'free', 'status' => 'active']);
}

// Insert ke-6 — ditolak
$result = $users->insert(['name' => 'User 6', 'plan' => 'free', 'status' => 'active']);
// → $result === false (dibatalkan oleh hook)

// Namun plan 'starter' masih bisa
$users->insert(['name' => 'User Starter', 'plan' => 'starter', 'status' => 'active']);
// → berhasil
```

### Catatan

- `beforeUpdate` **tidak mendukung `return false`**. Untuk "membatalkan" update dari hook `beforeUpdate`, triknya adalah mengubah `$criteria` agar tidak cocok dengan dokumen manapun (lihat contoh di atas).
- Query tambahan di dalam hook (seperti `$users->count()` pada contoh) menambah overhead. Pertimbangkan cache sederhana jika validasi ini sering dipanggil.
- Aturan bisnis yang kompleks mungkin lebih baik ditempatkan di service layer aplikasi, bukan di hook. Gunakan hook untuk aturan yang **harus** selalu berlaku terlepas dari entry point (API, CLI, dll).

---

## 6. Slug Generation

**Kapan digunakan:** Ketika dokumen perlu memiliki slug yang ramah URL (misalnya untuk artikel, halaman, atau produk). Slug di-generate otomatis dari field `name` atau `title` saat insert, sehingga pengembang tidak perlu mengaturnya manual.

```php
use BangronDB\Client;

$client = new Client('/path/to/data');
$db = $client->createDB('my_app');
$articles = $db->createCollection('articles');

// ── Helper: generate slug dari teks ─────────────────────
function generateSlug(string $text, \BangronDB\Collection $collection, string $field = 'slug'): string
{
    // Konversi ke slug dasar
    $slug = strtolower($text);
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
    $slug = trim($slug, '-');

    // Pastikan slug unik — tambahkan suffix jika perlu
    $baseSlug = $slug;
    $counter  = 1;

    while ($collection->count([$field => $slug]) > 0) {
        $slug = $baseSlug . '-' . $counter++;
    }

    return $slug;
}

// ── beforeInsert: auto-generate slug ────────────────────
$articles->on('beforeInsert', function ($document) use ($articles) {
    if (isset($document['title']) && empty($document['slug'])) {
        $document['slug'] = generateSlug($document['title'], $articles);
    }
    return $document;
});

// ── beforeUpdate: regenerate slug jika title berubah ─────
$articles->on('beforeUpdate', function ($criteria, $data) use ($articles) {
    // Deteksi perubahan title via $set
    if (isset($data['$set']['title'])) {
        $newSlug = generateSlug($data['$set']['title'], $articles);
        $data['$set']['slug'] = $newSlug;
    }
    return [$criteria, $data];
});

// ── Penggunaan ──────────────────────────────────────────
$id1 = $articles->insert(['title' => 'Panduan Lengkap BangronDB']);
// → slug: "panduan-lengkap-bangrondb"

$id2 = $articles->insert(['title' => 'Panduan Lengkap BangronDB']);
// → slug: "panduan-lengkap-bangrondb-1" (unik)

$articles->update(['_id' => $id1], ['$set' => ['title' => 'Panduan BangronDB 2025']]);
// → slug otomatis di-update menjadi "panduan-bangrondb-2025"
```

### Catatan

- Slug hanya di-generate jika belum diset manual. Pengembang tetap bisa meng-override slug.
- Loop `while` untuk keunikan slug bisa lambat jika banyak dokumen dengan slug serupa. Untuk performa lebih baik, pertimbangkan menggunakan counter atomik atau indeks unik.
- Jika dokumen di-update dan slug baru sudah dipakai dokumen lain, slug akan mendapat suffix. Pastikan ini sesuai dengan kebutuhan aplikasi Anda.

---

## 7. Cascade Delete

**Kapan digunakan:** Ketika menghapus dokumen induk harus ikut menghapus semua dokumen terkait di collection lain — misalnya menghapus user beserta semua pesanan dan komentar miliknya. Pola ini menggunakan `beforeRemove` untuk menangani penghapusan berantai.

```php
use BangronDB\Client;

$client = new Client('/path/to/data');
$db = $client->createDB('my_app');

$users    = $db->createCollection('users');
$orders   = $db->createCollection('orders');
$comments = $db->createCollection('comments');

// ── beforeRemove: hapus pesanan & komentar user ─────────
$users->on('beforeRemove', function ($document) use ($orders, $comments) {
    $userId = $document['_id'] ?? null;
    if ($userId === null) {
        return $document;
    }

    // Hapus semua pesanan user
    $orderCount = $orders->remove(['user_id' => $userId]);
    error_log("Cascade: menghapus {$orderCount} pesanan untuk user {$userId}");

    // Hapus semua komentar user
    $commentCount = $comments->remove(['user_id' => $userId]);
    error_log("Cascade: menghapus {$commentCount} komentar untuk user {$userId}");

    return $document;
});

// ── Penggunaan ──────────────────────────────────────────
$userId = $users->insert(['name' => 'Budi', 'email' => 'budi@mail.com']);

$orders->insert(['user_id' => $userId, 'product' => 'Laptop', 'total' => 15000000]);
$orders->insert(['user_id' => $userId, 'product' => 'Mouse',  'total' => 250000]);

$comments->insert(['user_id' => $userId, 'text' => 'Artikel bagus!']);
$comments->insert(['user_id' => $userId, 'text' => 'Sangat membantu']);

echo "Orders sebelum hapus: " . $orders->count() . "\n";   // 2
echo "Comments sebelum hapus: " . $comments->count() . "\n"; // 2

// Hapus user — pesanan & komentar ikut terhapus
$users->remove(['_id' => $userId]);

echo "Orders sesudah hapus: " . $orders->count() . "\n";    // 0
echo "Comments sesudah hapus: " . $comments->count() . "\n"; // 0
```

### Catatan

- Hook `beforeRemove` dipanggil **per dokumen**. Jika Anda menghapus banyak dokumen sekaligus (misalnya `remove(['status' => 'inactive'])`), hook akan dipanggil untuk setiap dokumen yang cocok.
- Cascade delete bersifat satu arah. Jika ada relasi siklik, bisa terjadi penghapusan tak terbatas. Pastikan arah cascade jelas.
- Operasi cascade di dalam hook **tidak berada dalam transaksi** (kecuali Anda membungkus seluruh operasi dalam transaksi manual). Jika penghapusan pesanan gagal di tengah jalan, user tetap akan terhapus. Pertimbangkan untuk membungkus dalam transaksi jika konsistensi mutlak diperlukan. Lihat [contoh transaksi](../examples/13-transactions.php).
- Untuk relasi yang kompleks dengan banyak level, pertimbangkan menggunakan pendekatan "soft delete" sebagai alternatif cascade.

---

## 8. Rate Limiting (Penghitung In-Memory)

**Kapan digunakan:** Ketika Anda perlu membatasi jumlah operasi tertentu dalam jendela waktu — misalnya "maksimal 10 insert per menit per IP". Pola ini menggunakan penghitung di memory (bukan di database) untuk kecepatan, cocok untuk proteksi dasar terhadap abuse.

```php
use BangronDB\Client;

$client = new Client('/path/to/data');
$db = $client->createDB('my_app');
$contacts = $db->createCollection('contacts');

// ── Konfigurasi rate limit ──────────────────────────────
$maxRequests    = 10;    // maksimal 10 operasi
$windowSeconds  = 60;    // dalam 60 detik
$counterStore   = [];    // in-memory counter: key => [count, windowStart]

// ── Helper: cek rate limit ──────────────────────────────
function checkRateLimit(
    string $key,
    int $max,
    int $window,
    array &$store
): bool {
    $now = time();
    $windowStart = $now - $window;

    if (!isset($store[$key]) || $store[$key]['start'] < $windowStart) {
        // Mulai jendela baru
        $store[$key] = ['count' => 1, 'start' => $now];
        return true;
    }

    $store[$key]['count']++;
    return $store[$key]['count'] <= $max;
}

// ── beforeInsert: terapkan rate limit ───────────────────
$contacts->on('beforeInsert', function ($document) use (
    &$counterStore,
    $maxRequests,
    $windowSeconds
) {
    // Gunakan IP sebagai identifier (ganti dengan mekanisme auth yang sesuai)
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = "contact_insert:{$ip}";

    if (!checkRateLimit($key, $maxRequests, $windowSeconds, $counterStore)) {
        error_log("Rate limit exceeded untuk {$key}");
        return false; // batalkan insert
    }

    return $document;
});

// ── Penggunaan ──────────────────────────────────────────
// Simulasi: 12 insert berturut-turut
$success = 0;
$blocked = 0;

for ($i = 1; $i <= 12; $i++) {
    $result = $contacts->insert([
        'name'  => "Contact {$i}",
        'email' => "contact{$i}@test.com",
    ]);
    if ($result !== false) {
        $success++;
    } else {
        $blocked++;
    }
}

echo "Berhasil: {$success}, Diblokir: {$blocked}\n";
// → Berhasil: 10, Diblokir: 2
```

### Catatan

- **Counter in-memory tidak persisten** — berfungsi selama proses PHP berjalan. Pada request baru (misalnya di web server), counter direset. Untuk rate limit yang persisten, gunakan Redis, Memcached, atau tabel khusus di database.
- Pada lingkungan PHP-FPM dengan多个 worker, setiap worker memiliki counter sendiri. Rate limit per-worker, bukan global. Untuk rate limit global, gunakan penyimpanan eksternal.
- IP address bisa di-spoof. Untuk keamanan yang lebih baik, gunakan identitas user yang terautentikasi sebagai key.
- Nilai default (`$maxRequests = 10, $windowSeconds = 60`) hanyalah contoh — sesuaikan dengan kebutuhan aplikasi Anda.

---

## Tips dan Trik

### Urutan Eksekusi Hook

Hook untuk event yang sama dijalankan **sesuai urutan pendaftaran** (registration order). Jika Anda mendaftarkan hook A lalu hook B, maka A akan dijalankan lebih dulu.

```php
$collection->on('beforeInsert', function ($doc) {
    // Hook 1: dijalankan pertama
    $doc['step'] = 1;
    return $doc;
});

$collection->on('beforeInsert', function ($doc) {
    // Hook 2: dijalankan kedua, menerima hasil dari Hook 1
    $doc['step'] = ($doc['step'] ?? 0) + 1;
    return $doc;
});

// Dokumen akhir akan memiliki step = 2
```

**Penting:** Output dari hook sebelumnya menjadi input untuk hook berikutnya. Jika hook pertama memodifikasi dokumen, hook kedua menerima dokumen yang sudah dimodifikasi.

### Penanganan Exception di Hook

Jika sebuah hook melempar exception (`throw`), BangronDB akan menangkapnya (catch), mencatat pesan error ke `error_log()`, dan **operasi tetap dilanjutkan**.

```php
$collection->on('beforeInsert', function ($doc) {
    throw new \RuntimeException("Error di hook!");
    // → exception di-catch, di-log, dan operasi INSERT TETAP BERJALAN
    // Ini TIDAK membatalkan operasi
});

$collection->on('beforeInsert', function ($doc) {
    // Untuk membatalkan operasi, gunakan return false:
    if (empty($doc['required_field'])) {
        return false; // → operasi dibatalkan
    }
    return $doc;
});
```

**Aturan penting:**
- `return false` → membatalkan operasi (hanya untuk `beforeInsert` dan `beforeRemove`)
- `throw exception` → **tidak** membatalkan operasi, hanya mencatat error
- `beforeUpdate` tidak mendukung `return false` sama sekali

### Pertimbangan Performa

- **Query tambahan di hook menambah overhead.** Setiap query di dalam hook (misalnya `count()`, `find()`) menambah latensi operasi utama.
- **Hook dijalankan untuk setiap dokumen** pada operasi bulk. Jika Anda meng-insert 1000 dokumen, `beforeInsert` dipanggil 1000 kali.
- **Buat hook ringan.** Hindari operasi I/O berat (HTTP request, file write) di dalam hook. Untuk operasi asynchronous, pertimbangkan untuk mengantri tugas ke job queue.
- **Hindari chain yang terlalu panjang.** Jika ada terlalu banyak hook untuk event yang sama, setiap hook menambah pemanggilan fungsi. Pertimbangkan untuk menggabungkan hook yang terkait.

### Kapan Menggunakan Hook vs Middleware vs Service Layer

| Aspek | Hook | Middleware | Service Layer |
|-------|------|-----------|---------------|
| **Cakupan** | Per-collection, per-event | Per-route / per-request | Seluruh aplikasi |
| **Akses data** | Langsung ke dokumen yang sedang diproses | Hanya request/response | Penuh (bisa akses banyak collection) |
| **Persistensi** | Tidak persisten (harus di-daftar ulang) | Persisten (bagian dari kode aplikasi) | Persisten (bagian dari kode aplikasi) |
| **Dukungan veto** | Ya (`beforeInsert`, `beforeRemove`) | Ya (return response) | Ya (throw exception) |
| **Cocok untuk** | Auto-timestamp, sanitasi, audit log, cascade | Autentikasi, authorization global, logging request | Business logic kompleks, koordinasi multi-collection |

**Rekomendasi:**
- Gunakan **hook** untuk logika yang erat kaitannya dengan operasi CRUD pada collection tertentu dan harus selalu berlaku (auto-timestamp, sanitasi, audit).
- Gunakan **middleware** untuk logika yang berlaku lintas collection (autentikasi, rate limiting global, request logging).
- Gunakan **service layer** untuk business logic kompleks yang melibatkan koordinasi antar collection atau validasi yang memerlukan konteks lebih luas dari sekadar dokumen yang sedang diproses.

---

> **Bacaan lanjutan:**
> - [Hook API lengkap](features.md#hooks-event-system) — referensi semua event, parameter, dan return value
> - [Contoh hook dasar](../examples/11-hooks.php) — hook chaining, veto, auto-timestamp
> - [Contoh ACL dinamis](../examples/24-dynamic-acl-per-collection.php) — implementasi ACL per collection
> - [Contoh e-commerce](../examples/19-ecommerce-app.php) — penggunaan hook di aplikasi nyata