# DatabaseMetrics Class

Dokumentasi API untuk class `DatabaseMetrics` yang menyediakan metrik kesehatan dan monitoring database.

## Namespace

```php
namespace BangronDB;
```

## Deskripsi

`DatabaseMetrics` adalah class helper yang menyediakan informasi kesehatan database, metrik data, performa, dan statistik collection. Class ini digunakan untuk monitoring dan troubleshooting.

## Constructor

```php
public function __construct(Database $db)
```

**Parameter:**

- `Database $db` - Instance Database

**Contoh:**

```php
$metrics = new DatabaseMetrics($db);
```

---

## Metode

### `getHealthMetrics()`

Mendapatkan semua metrik kesehatan database.

```php
public function getHealthMetrics(): array
```

**Nilai Kembali:**

```php
[
    'database' => [
        'path' => '/path/to/db.bangron',
        'type' => 'file', // atau 'memory'
        'encryption_enabled' => true/false,
    ],
    'integrity' => [
        'status' => 'healthy' | 'corrupted' | 'error',
        'details' => [...],
        'checked_at' => 1704067200,
    ],
    'metrics' => [...],
    'performance' => [...],
    'collections' => [...],
]
```

**Contoh:**

```php
$health = $metrics->getHealthMetrics();
echo "Status: " . $health['integrity']['status'] . "\n";
```

---

### `checkIntegrity()`

Memeriksa integritas database menggunakan SQLite's PRAGMA integrity_check.

```php
public function checkIntegrity(): array
```

**Nilai Kembali:**

```php
[
    'status' => 'healthy' | 'corrupted' | 'error',
    'details' => ['ok'] | ['error message'],
    'checked_at' => 1704067200,
]
```

**Contoh:**

```php
$integrity = $metrics->checkIntegrity();
if ($integrity['status'] === 'healthy') {
    echo "Database integrity OK\n";
}
```

---

### `getDataMetrics()`

Mendapatkan metrik data untuk database.

```php
public function getDataMetrics(): array
```

**Nilai Kembali:**

```php
[
    'total_collections' => 5,
    'total_documents' => 1000,
    'total_size_bytes' => 1048576, // 1MB
    'avg_document_size' => 1048.57,
    'collections' => [
        'users' => [
            'documents' => 500,
            'size_bytes' => 524288,
            'avg_document_size' => 1048.57,
        ],
        'posts' => [...],
    ],
    'last_updated' => 1704067200,
]
```

**Contoh:**

```php
$data = $metrics->getDataMetrics();
echo "Total documents: " . $data['total_documents'] . "\n";
echo "Total size: " . round($data['total_size_bytes'] / 1024, 2) . " KB\n";
```

---

### `getPerformanceMetrics()`

Mendapatkan metrik performa database.

```php
public function getPerformanceMetrics(): array
```

**Nilai Kembali:**

```php
[
    'file_size_bytes' => 1048576,
    'page_count' => 256,
    'page_size' => 4096,
    'total_pages_bytes' => 1048576,
    'freelist_count' => 10,
    'fragmentation_ratio' => 0.039,
    'indexes' => [...],
    'cache_size_pages' => -1024,
]
```

**Contoh:**

```php
$perf = $metrics->getPerformanceMetrics();
echo "Page count: " . $perf['page_count'] . "\n";
echo "Fragmentation: " . ($perf['fragmentation_ratio'] * 100) . "%\n";
```

---

### `getIndexMetrics()`

Mendapatkan metrik index database.

```php
public function getIndexMetrics(): array
```

**Nilai Kembali:**

```php
[
    'idx_users_email' => [
        'table' => 'users',
        'type' => 'json_index',
        'definition' => 'CREATE INDEX ...',
    ],
    'idx_posts_created_at' => [...],
]
```

**Contoh:**

```php
$indexes = $metrics->getIndexMetrics();
foreach ($indexes as $name => $info) {
    echo "Index: $name\n";
    echo "Table: " . $info['table'] . "\n";
    echo "Type: " . $info['type'] . "\n";
}
```

---

### `getCollectionMetrics()`

Mendapatkan metrik untuk setiap collection.

```php
public function getCollectionMetrics(): array
```

**Nilai Kembali:**

```php
[
    'users' => [
        'documents' => 500,
        'size_bytes' => 524288,
        'indexes' => ['idx_users_email', 'idx_users_created_at'],
        'index_count' => 2,
        'hooks' => [
            'beforeInsert' => 1,
            'afterInsert' => 0,
            'beforeUpdate' => 2,
            'afterUpdate' => 0,
            'beforeRemove' => 0,
            'afterRemove' => 0,
        ],
        'encryption_enabled' => true,
        'id_mode' => 'auto',
        'searchable_fields' => ['email'],
    ],
    'posts' => [...],
]
```

**Contoh:**

```php
$collections = $metrics->getCollectionMetrics();
foreach ($collections as $name => $data) {
    echo "Collection: $name\n";
    echo "Documents: " . $data['documents'] . "\n";
    echo "Size: " . round($data['size_bytes'] / 1024, 2) . " KB\n";
    echo "Indexes: " . implode(', ', $data['indexes']) . "\n";
}
```

---

## Contoh Penggunaan Lengkap

```php
use BangronDB\Client;

$client = new Client('/path/to/data');
$db = $client->selectDB('app');
$metrics = new DatabaseMetrics($db);

// 1. Health check
$health = $metrics->getHealthMetrics();
echo "Database Status: " . $health['integrity']['status'] . "\n";

// 2. Data overview
$data = $metrics->getDataMetrics();
echo "Total collections: " . $data['total_collections'] . "\n";
echo "Total documents: " . $data['total_documents'] . "\n";
echo "Total size: " . round($data['total_size_bytes'] / 1024 / 1024, 2) . " MB\n";

// 3. Performance check
$perf = $metrics->getPerformanceMetrics();
if ($perf['fragmentation_ratio'] > 0.2) {
    echo "Warning: High fragmentation - consider VACUUM\n";
}

// 4. Collection details
$collections = $metrics->getCollectionMetrics();
foreach ($collections as $name => $data) {
    echo "\nCollection: $name\n";
    echo "- Documents: {$data['documents']}\n";
    echo "- Size: " . round($data['size_bytes'] / 1024, 2) . " KB\n";
    echo "- Indexes: " . count($data['indexes']) . "\n";
    echo "- Encryption: " . ($data['encryption_enabled'] ? 'Yes' : 'No') . "\n";
    echo "- ID Mode: {$data['id_mode']}\n";
}

// 5. Index audit
$indexes = $metrics->getIndexMetrics();
echo "\nIndexes:\n";
foreach ($indexes as $name => $info) {
    echo "- $name ({$info['type']})\n";
}
```

---

## Health Status Codes

| Status      | Deskripsi          | Tindakan            |
| ----------- | ------------------ | ------------------- |
| `healthy`   | Database integral  | Tidak ada tindakan  |
| `corrupted` | Database corrupted | Restore dari backup |
| `error`     | Gagal memeriksa    | Cek error logs      |

---

## Interpretasi Metrik

### Fragmentation Ratio

```php
// Jika fragmentation > 20%, pertimbangkan VACUUM
if ($perf['fragmentation_ratio'] > 0.2) {
    $db->vacuum(); // VACUUM database
}
```

### Cache Size

```php
// Cache size negatif = KB (contoh: -1024 = 1024KB = 1MB)
// Cache size positif = jumlah pages
// Default biasanya -1024KB
```

### Page Size

```php
// Default 4096 bytes (4KB)
// Cocok untuk kebanyakan use cases
// Tidak perlu diubah kecuali ada alasan khusus
```

---

## Monitoring Script

```php
<?php
// monitoring.php

require_once 'vendor/autoload.php';

use BangronDB\Client;
use BangronDB\DatabaseMetrics;

$client = new Client('/path/to/data');
$db = $client->selectDB('app');
$metrics = new DatabaseMetrics($db);

$health = $metrics->getHealthMetrics();
$data = $metrics->getDataMetrics();
$perf = $metrics->getPerformanceMetrics();

// Check status
$status = 'OK';
$alerts = [];

if ($health['integrity']['status'] !== 'healthy') {
    $status = 'CRITICAL';
    $alerts[] = 'Database integrity check failed';
}

if ($perf['fragmentation_ratio'] > 0.3) {
    $status = 'WARNING';
    $alerts[] = 'High fragmentation: ' . ($perf['fragmentation_ratio'] * 100) . '%';
}

// Check disk space
$diskFree = disk_free_space('/path/to/data');
$diskTotal = disk_total_space('/path/to/data');
$diskUsage = ($diskTotal - $diskFree) / $diskTotal * 100;

if ($diskUsage > 90) {
    $status = 'CRITICAL';
    $alerts[] = 'Disk usage: ' . round($diskUsage, 2) . '%';
}

// Output
echo "[" . date('Y-m-d H:i:s') . "] $status\n";
echo "Documents: {$data['total_documents']}\n";
echo "Size: " . round($data['total_size_bytes'] / 1024 / 1024, 2) . " MB\n";
echo "Fragmentation: " . round($perf['fragmentation_ratio'] * 100, 2) . "%\n";
echo "Disk usage: " . round($diskUsage, 2) . "%\n";

if (!empty($alerts)) {
    echo "Alerts:\n";
    foreach ($alerts as $alert) {
        echo "- $alert\n";
    }
}
```
