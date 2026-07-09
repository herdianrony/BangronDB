# Fitur Lanjutan

Fitur-fitur BangronDB yang melampaui CRUD dasar.

---

## Hooks (Event System)

Hooks memungkinkan Anda mengeksekusi kode sebelum atau sesudah operasi CRUD.

### Event yang Tersedia

| Event | Waktu Eksekusi | Return `false` untuk |
|-------|---------------|---------------------|
| `beforeInsert` | Sebelum insert | Membatalkan insert |
| `afterInsert` | Setelah insert | - |
| `beforeUpdate` | Sebelum update | - (return array untuk modify) |
| `afterUpdate` | Setelah update | - |
| `beforeRemove` | Sebelum remove | Membatalkan remove |
| `afterRemove` | Setelah remove | - |

### Menggunakan Hooks

```php
$collection->on('beforeInsert', function ($document) {
    // Modifikasi dokumen sebelum insert
    $document['created_at'] = time();
    $document['slug'] = strtolower(str_replace(' ', '-', $document['name']));
    return $document;
});

$collection->on('beforeInsert', function ($document) {
    // Validasi — return false untuk membatalkan
    if (empty($document['email'])) {
        return false;
    }
    return $document;
});

$collection->on('afterInsert', function ($document, $id) {
    error_log("New document inserted: {$id}");
});

$collection->on('beforeUpdate', function ($criteria, $data) {
    // Modify criteria/data sebelum update
    return [
        'criteria' => $criteria,
        'data' => $data,
    ];
});
```

### Menghapus Hooks

```php
// Hapus semua hooks untuk event tertentu
$collection->off('beforeInsert');

// Hapus hook spesifik
$myHook = function ($doc) { return $doc; };
$collection->on('beforeInsert', $myHook);
$collection->off('beforeInsert', $myHook);
```

### Konstanta Hook

```php
use BangronDB\Collection;

$collection->on(Collection::HOOK_BEFORE_INSERT, function ($doc) {
    return $doc;
});
```

> **Catatan:** Jika hook melempar exception, exception tersebut di-catch dan di-log via `error_log()`. Operasi tetap dilanjutkan. Untuk membatalkan operasi, return `false` (bukan throw).

---

## Soft Delete

Hapus dokumen secara logis (tidak permanen dari database).

### Mengaktifkan

```php
$collection->useSoftDeletes();

// Custom field name (default: `deleted_at`)
$collection->useSoftDeletes(true)->setDeletedAtField('removed_at');
```

### Penggunaan

```php
// remove() → soft delete jika diaktifkan
$collection->remove(['status' => 'spam']);  // set deleted_at = timestamp

// Dokumen soft-deleted OTOMATIS dikecualikan dari query
$active = $collection->find()->toArray();  // tidak termasuk yang di-soft-delete

// Include soft-deleted
$all = $collection->find()->withTrashed()->toArray();

// Hanya soft-deleted
$trashed = $collection->find()->onlyTrashed()->toArray();

// Restore
$collection->restore(['_id' => 'abc']);  // hapus field deleted_at

// Hard delete (abaikan soft delete)
$collection->forceDelete(['_id' => 'abc']);
```

### Cek Status

```php
$collection->softDeletesEnabled();       // bool
$collection->getDeletedAtField();        // string, default: 'deleted_at'
```

---

## TTL (Time-To-Live)

Dokumen otomatis kedaluwarsa berdasarkan timestamp.

### Mengaktifkan

```php
// Tanpa default TTL (manual set expires_at per dokumen)
$collection->enableTtl('expires_at');

// Dengan default TTL (otomatis set saat insert)
$collection->enableTtl('expires_at', 3600);  // 1 jam

// Nonaktifkan
$collection->disableTtl();
```

### Penggunaan

```php
// Insert — default TTL otomatis di-set jika tidak ada expires_at
$collection->insert(['code' => '123456', 'data' => 'otp']);
// Otomatis: expires_at = time() + 3600

// Insert dengan expiry manual (override default)
$collection->insert(['code' => '789012', 'expires_at' => time() + 7200]);

// Bersihkan dokumen expired
$removed = $collection->cleanExpired();  // return jumlah yang dihapus

// Hitung dokumen expired
$expiredCount = $collection->expiredCount();
```

### Statistik

```php
$stats = $collection->ttlStats();
// $stats = [
//     'ttl_enabled' => true,
//     'ttl_field' => 'expires_at',
//     'default_ttl_seconds' => 3600,
//     'documents_with_ttl' => 15,
//     'expired_count' => 3,
//     'active_count' => 12,
//     'next_expires_at' => 1752000000,
//     'next_expires_in_seconds' => 1234,
// ]
```

### Use Cases

- **OTP / Verification Code** — `enableTtl('expires_at', 300)` (5 menit)
- **Session** — `enableTtl('expires_at', 3600)` (1 jam)
- **Cache** — `enableTtl('expires_at', 86400)` (24 jam)
- **Temporary Token** — `enableTtl('expires_at', 600)` (10 menit)

> **Catatan:** `cleanExpired()` harus dipanggil manual (misalnya via cron job). BangronDB tidak membersihkan otomatis.

---

## Aggregation Pipeline

Proses data langsung di SQLite tanpa memuat semua dokumen ke PHP.

### Pipeline Operators

| Operator | Deskripsi |
|----------|-----------|
| `$match` | Filter dokumen (sama seperti `find` criteria) |
| `$group` | Grouping dengan fungsi agregasi |
| `$sort` | Sort hasil |
| `$limit` | Batasi jumlah hasil |
| `$skip` | Lewati N hasil pertama |
| `$project` | Pilih/rename field |
| `$count` | Hitung hasil dan simpan ke field |
| `$unset` | Hapus field dari hasil |

### Contoh: $match

```php
$results = $collection->aggregate([
    ['$match' => ['status' => 'completed']],
]);
```

### Contoh: $group

```php
// Group dengan $sum
$results = $collection->aggregate([
    ['$group' => [
        '_id' => '$category',
        'total' => ['$sum' => '$amount'],
    ]]
]);

// Group dengan $count
$results = $collection->aggregate([
    ['$group' => [
        '_id' => '$category',
        'count' => ['$count' => null],
    ]]
]);

// Group dengan $avg (return null untuk group kosong)
$results = $collection->aggregate([
    ['$group' => [
        '_id' => '$category',
        'avg_amount' => ['$avg' => '$amount'],
    ]]
]);

// Group dengan $min / $max
$results = $collection->aggregate([
    ['$group' => [
        '_id' => null,
        'max_price' => ['$max' => '$price'],
        'min_price' => ['$min' => '$price'],
    ]]
]);
```

### Contoh: $sort, $limit, $skip

```php
$results = $collection->aggregate([
    ['$match' => ['status' => 'active']],
    ['$sort' => ['created_at' => -1]],
    ['$skip' => 10],
    ['$limit' => 5],
]);
```

### Contoh: $project

```php
// Include field (inclusive)
$results = $collection->aggregate([
    ['$project' => ['name' => 1, 'email' => 1]],
]);

// Rename field
$results = $collection->aggregate([
    ['$project' => ['nama' => '$name', 'umur' => '$age']],
]);
```

### Contoh: $unset

```php
$results = $collection->aggregate([
    ['$unset' => ['password', 'secret']],
]);
```

### Contoh: $count

```php
$results = $collection->aggregate([
    ['$match' => ['status' => 'completed']],
    ['$count' => 'completed_count'],
]);
// Result: [['completed_count' => 42]]
```

### Pipeline Kompleks

```php
$results = $collection->aggregate([
    ['$match' => ['status' => 'completed']],
    ['$group' => [
        '_id' => '$category',
        'total' => ['$sum' => '$amount'],
        'count' => ['$count' => null],
    ]],
    ['$sort' => ['total' => -1]],
    ['$limit' => 5],
]);
```

---

## Explain Query

Analisis bagaimana query dieksekusi untuk optimasi performa.

```php
$explanation = $collection->explain(['age' => ['$gte' => 25]]);

// Result:
// [
//     'query_plan' => [
//         'strategy' => 'json_extract',
//         'uses_index' => false,
//         'index_name' => null,
//     ],
//     'performance' => [
//         'documents_matched' => 5,
//         'total_documents' => 100,
//         'execution_time_ms' => 0.45,
//         'scan_ratio' => 0.05,  // 5% dari dokumen dipindai
//         'criteria_summary' => 'age >= 25',
//     ],
//     'suggestions' => [
//         'Consider creating an index on field: age',
//     ],
// ]
```

### Tips

- `scan_ratio` mendekati `1.0` (100%) → pertimbangkan untuk membuat index
- `uses_index = true` → query sudah optimal
- Jika `scan_ratio = 0` dan `criteria = null` → tidak ada filter

---

## Cursor Streaming

Untuk dataset besar, gunakan `stream()` agar tidak semua dokumen dimuat ke memori sekaligus.

```php
// Stream semua dokumen
foreach ($collection->stream() as $doc) {
    // Proses satu per satu
    echo $doc['name'] . "\n";
}

// Stream dengan criteria dan options
foreach ($collection->stream(
    ['status' => 'active'],
    ['sort' => ['created_at' => -1], 'limit' => 100]
) as $doc) {
    // Proses
}
```

> **Catatan:** `stream()` menggunakan PHP Generator (`yield`). Projection di `stream()` **belum** diterapkan di iterator. Untuk query dengan projection, gunakan `find()->toArray()`.

---

## Schema Validation

Validasi struktur dokumen sebelum insert/update.

```php
$collection->setSchema([
    'required' => ['name', 'email'],
    'fields' => [
        'name' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 100],
        'email' => ['type' => 'string', 'regex' => '/^[^@]+@[^@]+\.[^@]+$/'],
        'age' => ['type' => 'integer', 'min' => 0, 'max' => 150],
        'role' => ['type' => 'string', 'in' => ['admin', 'user', 'editor']],
        'tags' => ['type' => 'array'],
    ],
    'unique' => ['email'],  // Unique constraint
]);

// Insert yang valid → OK
$collection->insert(['name' => 'Alice', 'email' => 'alice@test.com', 'age' => 25]);

// Insert tanpa field required → throw ValidationException
$collection->insert(['name' => 'Bob']);  // Error: 'email' is required

// Tipe salah → throw ValidationException
$collection->insert(['name' => 'Charlie', 'email' => 'c@test.com', 'age' => 'twenty-five']);
```

### Unique Constraint

```php
$collection->setSchema([
    'unique' => ['email', 'username'],
]);

// Duplikat → throw ValidationException
$collection->insert(['email' => 'alice@test.com', 'username' => 'alice']);
$collection->insert(['email' => 'alice@test.com', 'username' => 'alice2']);
// Error: Duplicate value for field 'email'
```

---

## ID Modes

BangronDB mendukung 3 mode pembuatan ID.

### Auto (Default)

```php
// UUID v4 otomatis
$id = $collection->insert(['name' => 'Alice']);
// $id = '550e8400-e29b-41d4-a716-446655440000'
```

### Manual

```php
$collection->setIdModeManual();
$collection->insert(['_id' => 'user_alice', 'name' => 'Alice']);
```

### Prefix

```php
$collection->setIdModePrefix('usr');
// Atau dengan suffix
$collection->setPrefix('usr')->setSuffix('_id');
$id = $collection->insert(['name' => 'Alice']);
// $id = 'usr_550e8400-e29b-41d4-a716-446655440000'
```

---

## Populate (Relasi)

BangronDB mendukung populate relasi antar collection (mirip MongoDB populate / Mongoose).

```php
// Dokumen orders: { user_id: 'abc', items: [...] }

// Via Collection
$orders = $db->createCollection('orders');
$users = $db->createCollection('users');

$populated = $orders->populate(
    $orders->find()->toArray(),
    'user_id',       // local field
    'users',         // foreign collection (name atau 'db.collection')
    '_id',           // foreign field
    'user'           // alias di result
);

// Via Cursor chaining
$results = $orders->find()
    ->populate('user_id', $users, '_id', 'user_info')
    ->toArray();

// Multiple populate
$results = $orders->find()
    ->populateMany([
        ['path' => 'user_id', 'collection' => $users, 'as' => 'user'],
        ['path' => 'items.product_id', 'collection' => $products, 'as' => 'product'],
    ])
    ->toArray();

// Via with() (fluent)
$results = $orders->find()
    ->with('user_id', $users)
    ->toArray();
```

---

## Change Tracking

```php
// Ambil info perubahan terakhir
$last = $collection->getLastModified();
// ['version' => 5, 'timestamp' => 1752000000]
```

---

## Configuration Persistence

Konfigurasi collection bisa disimpan ke database dan dimuat kembali.

```php
// Set custom config
$collection->setCustomConfig('theme', 'dark');
$collection->setCustomConfigArray([
    'feature_flags' => ['beta' => true],
    'locale' => 'id_ID',
]);

// Simpan ke database
$collection->saveConfiguration();

// Load (otomatis saat collection dibuka)
$theme = $collection->getCustomConfig('theme');
$all = $collection->getAllCustomConfig();
```

> **Keamanan:** Field sensitif seperti `encryption_key`, `password`, `secret`, `token`, `api_key`, `private_key`, `credential` **ditolak** oleh `setCustomConfig()` dan `setCustomConfigArray()`.

---

## Database Metrics & Health

```php
// Health report lengkap
$report = $db->getHealthReport();

// Metrik spesifik
$db->getHealthMetrics();
$db->checkIntegrity();
$db->getDataMetrics();
$db->getPerformanceMetrics();
$db->getIndexMetrics();
$db->getCollectionMetrics();

// Vacuum (reclaim space)
$db->vacuum();

// Security audit
$audit = $db->securityAudit();
$collectionAudit = $collection->securityAudit();
```