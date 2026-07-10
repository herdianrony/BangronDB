# Tips & Trick BangronDB: Skenario Project ERP dengan Flight PHP

> Dokumen ini berisi panduan praktis memakai BangronDB pada project ERP menggunakan framework **Flight PHP**. Ditujukan untuk PHP developer yang sudah familiar dengan konsep database document (NoSQL) dan ingin mengimplementasikan modul ERP (inventory, sales, purchasing, accounting) tanpa menjalankan server database terpisah.

## Daftar Isi

1. [Pendahuluan & Stack](#1-pendahuluan--stack)
2. [Setup Project Flight PHP + BangronDB](#2-setup-project-flight-php--bangrondb)
3. [Schema Design untuk ERP](#3-schema-design-untuk-erp)
4. [Query Patterns ERP](#4-query-patterns-erp)
5. [Hooks & Events untuk Business Logic](#5-hooks--events-untuk-business-logic)
6. [Performance & Indexing](#6-performance--indexing)
7. [Security untuk Data Sensitif ERP](#7-security-untuk-data-sensitif-erp)
8. [Relasi & Cross-Collection Populate](#8-relasi--cross-collection-populate)
9. [Transaction Safety](#9-transaction-safety)
10. [Anti-Pattern & Tips Praktis](#10-anti-pattern--tips-praktis)
11. [Kesimpulan & Referensi](#11-kesimpulan--referensi)

---

## 1. Pendahuluan & Stack

ERP (Enterprise Resource Planning) adalah kelas aplikasi bisnis yang menyatukan inventory, sales, purchasing, accounting, dan HR dalam satu sistem. Pola datanya relasional kompleks: transaksi merujuk master data, journal entry selalu berpasangan (debit-kredit), dan stok bergerak antar gudang. Tradisionalnya ERP memakai RDBMS seperti PostgreSQL atau MySQL, yang berarti tim DevOps harus mengelola server database, backup, connection pool, dan replication.

BangronDB menawarkan jalan lain: sebuah library PHP murni di atas SQLite yang menyimpan dokumen JSON dalam satu file `.bangron`. Tidak ada server, tidak ada koneksi network, tidak ada password database yang harus di-rotate. Untuk ERP skala SMB (single-tenant, 1-50 user, jutaan transaksi) ini seringkali cukup — terutama bila aplikasi di-deploy sebagai appliance atau on-premise di server pelanggan.

**Stack yang dipakai dokumen ini:**

- **Flight PHP** — micro-framework dengan routing ringan, cocok untuk ERP yang lebih fokus ke business logic daripada MVC ceremony.
- **BangronDB** — database dokumen embedded, schema validation, hooks, encryption.
- **PHP 8.1+** — sesuai requirement `composer.json` BangronDB.

**Kapan BangronDB cocok untuk ERP:**

- Aplikasi single-tenant yang di-deploy per-customer (appliance model).
- ERP vertikal (industry-specific) dengan volume menengah.
- Proof-of-concept atau MVP yang butuh cepat jalan tanpa setup infra.
- Modul ERP yang embedding ke aplikasi existing (mis. plugin POS yang butuh stok sendiri).

**Kapan tidak cocok:**

- Multi-tenant SaaS dengan ribuan tenant concurrent (pertimbangkan PostgreSQL + row-level security).
- Write-heavy concurrent (>100 write/detik dari user berbeda) — SQLite WAL punya limitasi.
- ERP enterprise dengan requirement replication geografis.

---

## 2. Setup Project Flight PHP + BangronDB

### 2.1 Composer Install

```bash
mkdir erp-app && cd erp-app
composer init --name="acme/erp" --type=project --require="php:^8.1"
composer require flightphp/core
composer require herdianrony/bangrondb
```

### 2.2 Struktur Direktori

```
erp-app/
├── composer.json
├── public/
│   └── index.php           # Flight entry point
├── config/
│   └── database.php        # Konfigurasi path & encryption key
├── src/
│   ├── Services/
│   │   └── DatabaseService.php  # Bootstrap BangronDB sebagai Flight service
│   ├── Models/
│   │   ├── Product.php
│   │   ├── Customer.php
│   │   ├── SalesOrder.php
│   │   └── ...
│   └── Controllers/
│       ├── ProductController.php
│       ├── SalesOrderController.php
│       └── ...
└── data/                   # File .bangron disimpan di sini (jangan di-commit)
```

### 2.3 Bootstrap BangronDB di Flight

File `src/Services/DatabaseService.php`:

```php
<?php
declare(strict_types=1);

namespace Acme\Erp\Services;

use BangronDB\Client;
use BangronDB\Config;

class DatabaseService
{
    public static function register(): void
    {
        // Path database — di production, simpan di luar webroot
        $dataPath = __DIR__ . '/../../data';
        if (!is_dir($dataPath)) {
            mkdir($dataPath, 0755, true);
        }

        // Konfigurasi global BangronDB
        Config::set('default_path', $dataPath);
        Config::set('journal_mode', 'WAL');       // Concurrent read + single write
        Config::set('synchronous', 'NORMAL');      // Balance safety vs speed

        // Encryption key WAJIB dari env, jangan hardcode
        $encKey = $_ENV['BANGRON_ENCRYPTION_KEY'] ?? null;
        if ($encKey === null || strlen($encKey) < 32) {
            throw new \RuntimeException(
                'BANGRON_ENCRYPTION_KEY must be set in .env and at least 32 chars.'
            );
        }

        // Buat client dan register sebagai Flight service
        $client = new Client($dataPath, ['encryption_key' => $encKey]);

        // Default database untuk ERP
        $db = $client->createDB('erp_main');

        Flight::set('bangron.client', $client);
        Flight::set('bangron.db', $db);
    }
}
```

File `public/index.php`:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/DatabaseService.php';

use Acme\Erp\Services\DatabaseService;

// Bootstrap database
DatabaseService::register();

// Helper untuk akses collection
function db(): \BangronDB\Database
{
    return Flight::get('bangron.db');
}

function collection(string $name): \BangronDB\Collection
{
    return db()->createCollection($name);
}

// Routes
Flight::route('GET /api/products', function () {
    $products = collection('products')->find(
        ['is_active' => true],
        [],
        ['limit' => 50, 'sort' => ['name' => 1]]
    );
    Flight::json($products->toArray());
});

Flight::route('POST /api/sales-orders', function () {
    $data = Flight::request()->data->getData();
    // TODO: validasi + insert
    Flight::json(['status' => 'created'], 201);
});

Flight::start();
```

### 2.4 File `.env` (jangan di-commit)

```bash
BANGRON_ENCRYPTION_KEY=change-me-to-32-char-random-string-XXXXX
```

Tambahkan ke `.gitignore`:

```
.env
data/
*.bangron
```

---

## 3. Schema Design untuk ERP

ERP punya banyak entitas. Strategi di BangronDB: **satu collection per entitas bisnis**, bukan satu collection per tabel SQL. Dokumen boleh nested untuk array kecil (line items dalam SO), tetapi jangan nested untuk relasi one-to-many besar (pisahkan jadi collection sendiri).

### 3.1 Master Data: Products, Customers, Suppliers

```php
<?php
// Schema untuk collection 'products'
collection('products')->setSchema([
    'sku'            => ['type' => 'string', 'required' => true, 'unique' => true,
                         'regex' => '/^[A-Z0-9\-]{3,20}$/'],
    'name'           => ['type' => 'string', 'required' => true, 'min' => 2, 'max' => 200],
    'category'       => ['type' => 'string', 'required' => true,
                         'enum' => ['raw', 'wip', 'finished', 'service']],
    'uom'            => ['type' => 'string', 'required' => true,
                         'enum' => ['pcs', 'kg', 'm', 'l', 'box']],
    'cost_price'     => ['type' => 'float', 'min' => 0],         // akan di-encrypt
    'sale_price'     => ['type' => 'float', 'required' => true, 'min' => 0],
    'tax_rate'       => ['type' => 'float', 'min' => 0, 'max' => 1],
    'is_active'      => ['type' => 'bool'],
    'min_stock'      => ['type' => 'int', 'min' => 0],
    'reorder_point'  => ['type' => 'int', 'min' => 0],
])->saveConfiguration();

// Schema untuk collection 'customers'
collection('customers')->setSchema([
    'code'           => ['type' => 'string', 'required' => true, 'unique' => true,
                         'regex' => '/^CUST-[0-9]{5}$/'],
    'name'           => ['type' => 'string', 'required' => true, 'min' => 2, 'max' => 200],
    'email'          => ['type' => 'string', 'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
    'phone'          => ['type' => 'string', 'regex' => '/^\+?[0-9]{8,15}$/'],
    'npwp'           => ['type' => 'string', 'regex' => '/^[0-9]{2}\.[0-9]{3}\.[0-9]{3}\.[0-9-]+$/'],
    'payment_terms'  => ['type' => 'int', 'min' => 0, 'max' => 365], // hari
    'credit_limit'   => ['type' => 'float', 'min' => 0],
    'tax_type'       => ['type' => 'string', 'enum' => ['pkp', 'non_pkp']],
    'addresses'      => ['type' => 'array', 'max' => 5],   // max 5 alamat
    'is_active'      => ['type' => 'bool'],
])->saveConfiguration();
```

### 3.2 Transaksi: Sales Orders, Invoices

```php
// Schema 'sales_orders' — dokumen dengan line items nested
collection('sales_orders')->setSchema([
    'so_number'      => ['type' => 'string', 'required' => true, 'unique' => true,
                         'regex' => '/^SO-[0-9]{4}-[0-9]{6}$/'],
    'so_date'        => ['type' => 'string', 'required' => true],  // ISO date
    'customer_id'    => ['type' => 'string', 'required' => true],  // FK ke customers._id
    'sales_rep_id'   => ['type' => 'string', 'required' => true],
    'status'         => ['type' => 'string', 'required' => true,
                         'enum' => ['draft', 'confirmed', 'partial', 'fulfilled', 'cancelled']],
    'lines'          => ['type' => 'array', 'required' => true, 'min' => 1, 'max' => 100],
    'subtotal'       => ['type' => 'float', 'min' => 0],
    'tax_total'      => ['type' => 'float', 'min' => 0],
    'grand_total'    => ['type' => 'float', 'min' => 0],
    'notes'          => ['type' => 'string', 'max' => 1000],
])->saveConfiguration();

// Schema 'invoices'
collection('invoices')->setSchema([
    'inv_number'     => ['type' => 'string', 'required' => true, 'unique' => true],
    'inv_date'       => ['type' => 'string', 'required' => true],
    'due_date'       => ['type' => 'string', 'required' => true],
    'customer_id'    => ['type' => 'string', 'required' => true],
    'so_id'          => ['type' => 'string'],  // bisa null (direct invoice)
    'amount'         => ['type' => 'float', 'required' => true, 'min' => 0],
    'paid_amount'    => ['type' => 'float', 'min' => 0],
    'status'         => ['type' => 'string', 'enum' => ['unpaid', 'partial', 'paid', 'void']],
    'journal_id'     => ['type' => 'string'], // reference ke journal entry
])->saveConfiguration();
```

### 3.3 Accounting: Chart of Accounts, Journal Entries

```php
// Schema 'chart_of_accounts'
collection('coa')->setSchema([
    'account_code'   => ['type' => 'string', 'required' => true, 'unique' => true,
                         'regex' => '/^[0-9]{4}-[0-9]{2,4}$/'],
    'account_name'   => ['type' => 'string', 'required' => true],
    'account_type'   => ['type' => 'string', 'required' => true,
                         'enum' => ['asset', 'liability', 'equity', 'revenue', 'expense']],
    'parent_code'    => ['type' => 'string'],
    'is_postable'    => ['type' => 'bool'],
    'is_active'      => ['type' => 'bool'],
])->saveConfiguration();

// Schema 'journal_entries' — double-entry bookkeeping
collection('journal_entries')->setSchema([
    'je_number'      => ['type' => 'string', 'required' => true, 'unique' => true],
    'je_date'        => ['type' => 'string', 'required' => true],
    'description'    => ['type' => 'string', 'required' => true, 'max' => 500],
    'source_type'    => ['type' => 'string', 'enum' => ['manual', 'sales', 'purchase', 'payment', 'adjustment']],
    'source_id'      => ['type' => 'string'],  // ID dokumen sumber (invoice_id, dll)
    'lines'          => ['type' => 'array', 'required' => true, 'min' => 2, 'max' => 50],
    'total_debit'    => ['type' => 'float', 'required' => true],
    'total_credit'   => ['type' => 'float', 'required' => true],
    'is_posted'      => ['type' => 'bool'],
])->saveConfiguration();
```

**Tips schema design ERP:**

1. Selalu set `unique` untuk field kode bisnis (`sku`, `so_number`, `inv_number`) — BangronDB akan reject insert duplikat.
2. Pakai `enum` untuk field status — mencegah typo seperti `'Confrimed'` vs `'confirmed'`.
3. `regex` untuk kode dengan format konsisten — memudahkan parsing dan reporting.
4. Pisahkan master data (`products`, `customers`) dari transaksi (`sales_orders`) — jangan gabung dalam satu collection.
5. Setelah `setSchema()`, selalu panggil `saveConfiguration()` agar schema persistent di database (tidak hilang saat aplikasi restart).

---

## 4. Query Patterns ERP

### 4.1 Stock Card / Inventory Balance (Aggregation Pipeline)

Laporan kartu stok: untuk 1 produk, tampilkan semua pergerakan (masuk/keluar) dan saldo running.

```php
function getStockCard(string $productId, string $fromDate, string $toDate): array
{
    return collection('stock_movements')->aggregate([
        ['$match' => [
            'product_id' => $productId,
            'movement_date' => ['$gte' => $fromDate, '$lte' => $toDate],
        ]],
        ['$sort' => ['movement_date' => 1]],
        ['$group' => [
            '_id' => '$product_id',
            'total_in'   => ['$sum' => ['$cond' => [['$gte' => ['$qty', 0]], '$qty', 0]]],
            'total_out'  => ['$sum' => ['$cond' => [['$lt'  => ['$qty', 0]], '$qty', 0]]],
            'movements'  => ['$push' => [
                'date' => '$movement_date',
                'qty'  => '$qty',
                'ref'  => '$reference',
                'type' => '$movement_type',
            ]],
        ]],
    ]);
}
```

### 4.2 Sales Pipeline (Multi-stage Query)

Status pipeline sales: hitung jumlah SO per status untuk dashboard.

```php
function getSalesPipeline(string $month): array
{
    return collection('sales_orders')->aggregate([
        ['$match' => [
            'so_date' => ['$regex' => '^' . $month],  // '2026-07'
            'status'  => ['$ne' => 'cancelled'],
        ]],
        ['$group' => [
            '_id'    => '$status',
            'count'  => ['$sum' => 1],
            'total'  => ['$sum' => '$grand_total'],
        ]],
        ['$sort' => ['_id' => 1]],
    ]);
}
// Output: [['_id' => 'draft', 'count' => 12, 'total' => 50000000], ...]
```

### 4.3 AR Aging Report (Bucket by Days Overdue)

Laporan piutang jatuh tempo: kelompokkan invoice unpaid berdasarkan umur.

```php
function getARAging(string $asOfDate): array
{
    $asOfTimestamp = strtotime($asOfDate);
    return collection('invoices')->aggregate([
        ['$match' => [
            'status'      => ['$in' => ['unpaid', 'partial']],
            'inv_date'    => ['$lte' => $asOfDate],
        ]],
        ['$project' => [
            'inv_number'  => 1,
            'customer_id' => 1,
            'amount'      => 1,
            'paid_amount' => 1,
            'balance'     => ['$subtract' => ['$amount', '$paid_amount']],
            'days_overdue' => ['$subtract' => [$asOfTimestamp, ['$toLong' => ['$toLong' => strtotime('$due_date')]]]],
        ]],
        ['$group' => [
            '_id' => [
                '$switch' => [
                    'branches' => [
                        ['case' => ['$lte' => ['$days_overdue', 0]], 'then' => 'current'],
                        ['case' => ['$lte' => ['$days_overdue', 30 * 86400]], 'then' => '1-30'],
                        ['case' => ['$lte' => ['$days_overdue', 60 * 86400]], 'then' => '31-60'],
                        ['case' => ['$lte' => ['$days_overdue', 90 * 86400]], 'then' => '61-90'],
                    ],
                    'default' => '90+',
                ],
            ],
            'count'   => ['$sum' => 1],
            'balance' => ['$sum' => '$balance'],
        ]],
    ]);
}
```

### 4.4 Profit & Loss Statement (Multi-stage Aggregation)

```php
function getProfitLoss(string $fromDate, string $toDate): array
{
    $journal = collection('journal_entries');
    $rows = $journal->aggregate([
        ['$match' => [
            'je_date' => ['$gte' => $fromDate, '$lte' => $toDate],
            'is_posted' => true,
        ]],
        ['$unwind' => '$lines'],
        ['$group' => [
            '_id' => '$lines.account_code',
            'debit'  => ['$sum' => '$lines.debit'],
            'credit' => ['$sum' => '$lines.credit'],
        ]],
        ['$sort' => ['_id' => 1]],
    ]);

    // Gabungkan dengan chart_of_accounts untuk dapat tipe akun
    $coa = collection('coa')->find([])->toArray();
    $coaMap = array_column($coa, null, 'account_code');

    $revenue = $expense = 0;
    foreach ($rows as $row) {
        $account = $coaMap[$row['_id']] ?? null;
        if (!$account) continue;
        $net = $row['debit'] - $row['credit'];
        if ($account['account_type'] === 'revenue') $revenue += -$net; // revenue: credit normal
        if ($account['account_type'] === 'expense') $expense += $net;  // expense: debit normal
    }
    return [
        'revenue'  => $revenue,
        'expense'  => $expense,
        'profit'   => $revenue - $expense,
        'detail'   => $rows,
    ];
}
```

---

## 5. Hooks & Events untuk Business Logic

Hooks adalah event lifecycle yang dipanggil sebelum/sesudah operasi insert/update/remove. Di ERP, hooks sangat berguna untuk menjaga konsistensi data antar collection — misalnya, insert SO otomatis mengurangi stok, atau create invoice otomatis membuat journal entry.

### 5.1 Auto-Update Stock saat Sales Order Confirmed

```php
collection('sales_orders')->on('afterUpdate', function (array $oldDoc, array $newDoc) {
    // Hanya trigger saat status berubah dari non-confirmed → confirmed
    if (($oldDoc['status'] ?? '') !== 'confirmed' && $newDoc['status'] === 'confirmed') {
        // Bungkus multiple stock_movement dalam transaction — kalau satu line gagal,
        // semua rollback. Lihat §9 untuk pola lengkap.
        $conn = collection('stock_movements')->database->connection;
        $conn->beginTransaction();
        try {
            // Buat stock_movement untuk setiap line item
            $movements = [];
            foreach ($newDoc['lines'] as $line) {
                $movements[] = [
                    'product_id'     => $line['product_id'],
                    'movement_date'  => $newDoc['so_date'],
                    'movement_type'  => 'sales_out',
                    'qty'            => -$line['qty'],   // negatif = keluar
                    'reference'      => $newDoc['so_number'],
                    'warehouse_id'   => $line['warehouse_id'] ?? 'WH-MAIN',
                ];
            }
            // insertMany dalam transaction — atomic dengan operasi SO update di atas
            collection('stock_movements')->insertMany($movements);

            // Update status SO → 'fulfilled' jika semua line sudah ada movement
            // (atau biarkan manual fulfilment yang update)
            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            throw $e;  // re-throw agar caller tahu SO confirm gagal
        }
    }
});
```

### 5.2 Auto-Create Journal Entry saat Invoice Dibuat (Double-Entry)

```php
collection('invoices')->on('afterInsert', function (array $doc) {
    // Skip jika sudah ada journal_id (mis. dari import)
    if (!empty($doc['journal_id'])) return;

    // Insert journal_entries + update invoice.journal_id WAJIB atomic.
    // Lihat §9 untuk pola lengkap.
    $conn = collection('invoices')->database->connection;
    $conn->beginTransaction();
    try {
        // Buat journal entry untuk invoice (DR Accounts Receivable, CR Sales Revenue)
        $jeNumber = 'JE-' . date('Y') . '-' . str_pad(
            (string) collection('journal_entries')->count([]) + 1, 6, '0', STR_PAD_LEFT
        );

        $journalId = collection('journal_entries')->insert([
            'je_number'    => $jeNumber,
            'je_date'      => $doc['inv_date'],
            'description'  => 'Auto-JE for ' . $doc['inv_number'],
            'source_type'  => 'sales',
            'source_id'    => $doc['_id'],
            'is_posted'    => true,
            'total_debit'  => $doc['amount'],
            'total_credit' => $doc['amount'],
            'lines' => [
                ['account_code' => '1100-00', 'debit'  => $doc['amount'], 'credit' => 0], // AR
                ['account_code' => '4000-00', 'debit'  => 0, 'credit' => $doc['amount']], // Revenue
            ],
        ]);

        // Update invoice dengan journal_id — atomic dengan insert JE di atas
        // (tanpa trigger hook recursion)
        collection('invoices')->update(
            ['_id' => $doc['_id']],
            ['$set' => ['journal_id' => $journalId]]
        );
        $conn->commit();
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        throw $e;  // re-throw — invoice insert + JE tidak boleh setengah jadi
    }
});
```

### 5.3 Audit Log via All Hooks

```php
function registerAuditLog(string $collectionName): void
{
    $audit = collection('audit_log');
    $user  = $_SESSION['user_id'] ?? 'system';

    $log = function (string $action, array $doc) use ($audit, $user, $collectionName) {
        $audit->insert([
            'timestamp'  => date('c'),
            'user_id'    => $user,
            'collection' => $collectionName,
            'action'     => $action,
            'doc_id'     => $doc['_id'] ?? null,
            'doc'        => $doc,
        ]);
    };

    collection($collectionName)
        ->on('afterInsert', fn($d) => $log('insert', $d))
        ->on('afterUpdate', fn($old, $new) => $log('update', $new))
        ->on('afterRemove', fn($d) => $log('remove', $d));
}

// Registrasi untuk semua collection ERP
foreach (['products', 'customers', 'suppliers', 'sales_orders', 'invoices', 'payments',
          'journal_entries', 'stock_movements'] as $name) {
    registerAuditLog($name);
}
```

**Tips hooks ERP:**

1. **Hindari recursion** — jika hook A insert ke collection B, dan B punya hook yang insert ke A, akan terjadi loop. Gunakan flag di dokumen (`['skip_hook' => true]`) untuk memutus recursion.
2. **Hooks tetap berjalan meski transaksi SQL gagal?** Tidak — `insertMany()` auto-rollback jika ada error, dan hook `afterInsert` tidak akan terpanggil untuk batch yang gagal.
3. **Untuk operasi kritis** seperti posting journal entry, bungkus hook dalam `beginTransaction()`/`commit()`/`rollBack()` agar atomic. Lihat [§9. Transaction Safety](#9-transaction-safety) untuk pola lengkap. (`executeTransaction()` di QueryExecutor adalah alternatif SQL-level; PDO `beginTransaction()` di level collection lebih idiomatis.)

---

## 6. Performance & Indexing

### 6.1 Searchable Fields untuk Query Cepat

Field yang sering di-filter wajib dijadikan searchable — BangronDB akan otomatis buat blind index column di SQLite sehingga query equality pakai SQL fast-path, bukan PHP-side fallback.

```php
// Setup searchable fields saat inisialisasi collection
collection('products')->setSearchableFields([
    'sku'       => ['hash' => false],   // tidak di-hash, perlu exact match cepat
    'category'  => ['hash' => false],
    'is_active' => ['hash' => false],
])->saveConfiguration();

collection('customers')->setSearchableFields([
    'code'  => ['hash' => false],
    'email' => ['hash' => true],   // di-hash untuk privasi (blind index)
    'npwp'  => ['hash' => true],   // data sensitif
])->saveConfiguration();

collection('sales_orders')->setSearchableFields([
    'so_number'    => ['hash' => false],
    'customer_id'  => ['hash' => false],
    'status'       => ['hash' => false],
    'so_date'      => ['hash' => false],
])->saveConfiguration();
```

### 6.2 Cursor Streaming untuk Laporan Besar

Laporan P&L tahunan bisa melibatkan puluh ribu journal entries. Jangan pakai `find()->toArray()` — gunakan `stream()`:

```php
function exportJournalCSV(string $year): void
{
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="journal-' . $year . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['JE Number', 'Date', 'Account', 'Debit', 'Credit', 'Description']);

    // Stream — memory konstan, tidak peduli berapa ribu baris
    $cursor = collection('journal_entries')
        ->find(['je_date' => ['$regex' => '^' . $year]])
        ->sort(['je_date' => 1]);

    foreach ($cursor->stream() as $je) {
        foreach ($je['lines'] as $line) {
            fputcsv($out, [
                $je['je_number'], $je['je_date'], $line['account_code'],
                $line['debit'], $line['credit'], $je['description'],
            ]);
        }
    }
    fclose($out);
}
```

### 6.3 EXPLAIN untuk Optimasi Query

Sebelum optimasi, selalu jalankan `explain()` untuk lihat query plan:

```php
$plan = collection('sales_orders')->explain([
    'status'       => 'confirmed',
    'so_date'      => ['$gte' => '2026-01-01'],
    'customer_id'  => 'CUST-00001',
]);

print_r($plan);
// Output berisi: strategy (sql_first | php_fallback), index_used, estimated_rows
// Jika "php_fallback", berarti searchable field belum di-set → tambahkan
```

### 6.4 insertMany untuk Bulk Import

Import master data produk dari CSV/Excel — gunakan `insertMany`, bukan loop `insert()`:

```php
function importProductsFromCSV(string $csvPath): int
{
    $handle = fopen($csvPath, 'r');
    $header = fgetcsv($handle);  // skip header row

    $batch = [];
    $count = 0;
    while (($row = fgetcsv($handle)) !== false) {
        $batch[] = [
            'sku'         => $row[0],
            'name'        => $row[1],
            'category'    => $row[2],
            'uom'         => $row[3],
            'sale_price'  => (float) $row[4],
            'cost_price'  => (float) $row[5],
            'is_active'   => true,
        ];

        // Batch 500 dokumen per insertMany untuk hindari memory besar
        if (count($batch) === 500) {
            collection('products')->insertMany($batch);
            $count += 500;
            $batch = [];
        }
    }
    if (!empty($batch)) {
        collection('products')->insertMany($batch);
        $count += count($batch);
    }
    fclose($handle);
    return $count;
}
```

`insertMany()` auto-transactional — semua berhasil atau semua di-rollback, tidak ada setengah-insert.

---

## 7. Security untuk Data Sensitif ERP

ERP menyimpan data sensitif: harga beli (rahasia bisnis), gaji karyawan, NPWP customer, data kartu kredit. BangronDB mendukung AES-256-GCM encryption per-collection dengan key rotation.

### 7.1 Encryption pada Field Sensitif

```php
// Encryption di-set per-collection, bukan per-field.
// Strategi: kelompokkan field sensitif ke collection terpisah.
collection('product_costs')->setEncryptionKey(
    $_ENV['BANGRON_COST_ENCRYPTION_KEY']
);
collection('product_costs')->setSchema([
    'product_id'  => ['type' => 'string', 'required' => true, 'unique' => true],
    'cost_price'  => ['type' => 'float', 'required' => true],
    'landed_cost' => ['type' => 'float'],
    'supplier_id' => ['type' => 'string'],
    'effective_date' => ['type' => 'string'],
])->saveConfiguration();

// Insert — otomatis di-encrypt di storage
collection('product_costs')->insert([
    'product_id'  => 'SKU-001',
    'cost_price'  => 75000.50,
    'landed_cost' => 78500.00,
    'supplier_id' => 'SUP-001',
    'effective_date' => '2026-07-01',
]);

// Tanpa key: dokumen tidak bisa di-decrypt
// Dengan key: $doc['cost_price'] mengembalikan 75000.50
$doc = collection('product_costs')->setEncryptionKey($key)->findOne(['product_id' => 'SKU-001']);
```

### 7.2 Searchable Blind Index untuk Data PII

Email dan NPWP customer perlu bisa di-query (untuk cari customer by email), tapi tidak boleh disimpan plaintext:

```php
collection('customers')->setSearchableFields([
    'email' => ['hash' => true],  // SHA-256 dengan key (HMAC)
    'npwp'  => ['hash' => true],
])->saveConfiguration();

// Query tetap bisa — BangronDB akan hash nilai input sebelum match
$customer = collection('customers')->findOne(['email' => 'john@example.com']);
// Blind index dipakai di SQL WHERE — cepat dan aman
```

### 7.3 RBAC untuk Menu ERP

ERP punya banyak role: admin, accounting, sales, warehouse, HR. Implementasi sederhana:

```php
class RBAC
{
    private array $permissions;

    public function __construct()
    {
        // Load permission matrix dari collection 'rbac_permissions'
        $rows = collection('rbac_permissions')->find([])->toArray();
        foreach ($rows as $row) {
            $this->permissions[$row['role']][$row['resource']] = $row['actions'];
        }
    }

    public function can(string $role, string $resource, string $action): bool
    {
        return in_array($action, $this->permissions[$role][$resource] ?? [], true);
    }

    public function require(string $role, string $resource, string $action): void
    {
        if (!$this->can($role, $resource, $action)) {
            Flight::halt(403, json_encode(['error' => "Forbidden: $role cannot $action $resource"]));
        }
    }
}

// Pakai di controller
Flight::route('POST /api/sales-orders', function () {
    $rbac = new RBAC();
    $rbac->require($_SESSION['role'], 'sales_orders', 'create');
    // ... logic insert SO
});
```

### 7.4 Audit Log dengan Change Tracking

BangronDB punya built-in change tracking (`getLastModified()`). Untuk audit yang lebih detail, gunakan collection terpisah:

```php
// Lihat siapa mengubah product SKU-001 dan kapan
$audit = collection('audit_log')->find(
    ['collection' => 'products', 'doc_id' => 'SKU-001'],
    [],
    ['sort' => ['timestamp' => -1], 'limit' => 50]
)->toArray();

foreach ($audit as $entry) {
    echo "[{$entry['timestamp']}] {$entry['user_id']} - {$entry['action']}\n";
}
```

---

## 8. Relasi & Cross-Collection Populate

BangronDB tidak punya JOIN seperti SQL, tapi punya `populate()` untuk mengambil dokumen terkait secara lazy.

### 8.1 Populate Sederhana: SO → Customer

```php
// Ambil SO dengan data customer di-include
$so = collection('sales_orders')
    ->findOne(['so_number' => 'SO-2026-000001'])
    ->populate('customer_id', 'customers', ['name', 'code', 'email']);

// Hasil:
// [
//   'so_number' => 'SO-2026-000001',
//   'customer_id' => 'CUST-00001',   // tetap ada ID asli
//   'customer' => [                   // field populated baru
//     'name' => 'PT Maju Jaya',
//     'code' => 'CUST-00001',
//     'email' => 'ap@majujaya.co.id',
//   ],
//   ...
// ]
```

### 8.2 Populate Multi-Level: SO → Customer → Sales Rep

```php
$so = collection('sales_orders')
    ->findOne(['so_number' => 'SO-2026-000001'])
    ->populateMany([
        'customer_id'   => ['collection' => 'customers', 'fields' => ['name', 'code']],
        'sales_rep_id'  => ['collection' => 'users',     'fields' => ['name', 'email']],
    ]);
```

### 8.3 Cross-Database Populate

ERP besar sering pisahkan database finance dari operational:

```php
$finance = Flight::get('bangron.client')->selectDB('erp_finance');
$operational = Flight::get('bangron.client')->selectDB('erp_operational');

// Populate invoice dari finance dengan customer dari operational
$invoice = $finance->collection('invoices')
    ->findOne(['inv_number' => 'INV-2026-000001'])
    ->populate('customer_id', 'operational.customers', ['name', 'code']);
// Notasi 'operational.customers' = database.collection
```

### 8.4 Foreign Key Emulation via Hooks

BangronDB tidak enforce FK secara native. Emulasikan dengan hook `beforeRemove`:

```php
collection('customers')->on('beforeRemove', function (array $criteria) {
    // Cek apakah customer masih punya SO/Invoice outstanding
    $customers = collection('customers')->find($criteria)->toArray();
    foreach ($customers as $cust) {
        $soCount = collection('sales_orders')->count([
            'customer_id' => $cust['_id'],
            'status'      => ['$in' => ['draft', 'confirmed', 'partial']],
        ]);
        if ($soCount > 0) {
            throw new \RuntimeException(
                "Cannot delete customer {$cust['code']}: $soCount outstanding SO(s)"
            );
        }
        // Alternatif: return false untuk silently veto delete
    }
});
```

---

## 9. Transaction Safety

ERP penuh dengan operasi multi-step yang WAJIB atomic. Tanpa transaction, kegagalan sebagian operasi akan menyebabkan inkonsistensi data bisnis (stok salah, journal tidak balance, audit log hilang).

### 9.1 Skenario yang WAJIB Pakai Transaction

| Skenario | Langkah Atomic | Konsekuensi Tanpa Transaction |
|----------|----------------|-------------------------------|
| Konfirmasi SO | Insert multiple stock_movements + update SO status | Stok sebagian terpotong, SO status tidak update |
| Create Invoice + Auto JE | Insert invoice + insert journal_entries + update invoice.journal_id | Invoice tanpa JE → laporan keuangan salah |
| Stock Transfer | Out movement WH-A + In movement WH-B | Stok hilang/lebih (double-entry gagal) |
| Payroll Post | Insert payroll_run + multiple payslips + JE | Sebagian karyawan tidak dibayar, tapi JE sudah post |
| Void Invoice | Reverse JE + update invoice status + audit log | JE masih ada padahal invoice void |
| Bulk Master Import | insertMany products + audit log | Sebagian insert, sebagian gagal |
| Migration Script | Update multiple dokumen dengan field baru | Sebagian dokumen ter-migrate, sebagian tidak |

### 9.2 Pola Transaction di Hook

Hook `afterInsert`/`afterUpdate` berjalan dalam transaction yang sama dengan operasi trigger-nya. Manfaatkan untuk konsistensi atomic:

```php
// Hook ini otomatis dalam transaction yang sama dengan insert invoice
collection('invoices')->on('afterInsert', function (array $invoice) {
    $jeId = collection('journal_entries')->insert([...]);
    // Update invoice dengan journal_id — atomic dengan insert di atas
    collection('invoices')->update(
        ['_id' => $invoice['_id']],
        ['$set' => ['journal_id' => $jeId]]
    );
});

// Caller:
$conn = collection('invoices')->database->connection;
$conn->beginTransaction();
try {
    collection('invoices')->insert($invoice);  // trigger hook → insert JE + update invoice
    $conn->commit();  // semua atomic
} catch (\Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    throw $e;
}
```

> **Catatan:** Pada §5.1 dan §5.2, hook membungkus sendiri `beginTransaction()` agar self-contained (tetap atomic walau caller lupa wrap). Jika caller juga `beginTransaction()`, pastikan tidak double-begin — pakai cek `$conn->inTransaction()` sebelum begin, atau pilih salah satu pola saja.

### 9.3 Pola Manual untuk Multi-Step Non-Hook

Untuk operasi yang tidak melalui hook, bungkus manual:

```php
function transferStock(string $productId, string $fromWh, string $toWh, int $qty): void {
    $conn = collection('stock_movements')->database->connection;
    $conn->beginTransaction();
    try {
        // 1. Out from source warehouse
        collection('stock_movements')->insert([
            'movement_type' => 'transfer_out',
            'product_id'    => $productId,
            'warehouse_id'  => $fromWh,
            'qty'           => -$qty,
            // ...
        ]);
        // 2. In to destination warehouse
        collection('stock_movements')->insert([
            'movement_type' => 'transfer_in',
            'product_id'    => $productId,
            'warehouse_id'  => $toWh,
            'qty'           => $qty,
            // ...
        ]);
        $conn->commit();
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        throw $e;
    }
}
```

Pola ini cocok juga untuk **migration script** (update massal dokumen dengan field baru): baca per-batch, wrap tiap batch dalam transaction, commit per batch agar tidak lock database terlalu lama.

### 9.4 Aturan Penting

1. Cek `inTransaction()` sebelum `rollBack()` — nested transaction handling.
2. Re-throw exception setelah rollback — jangan swallow.
3. Side effects (kirim email, API call) DI LUAR transaction.
4. Transaction cross-collection dalam database yang sama = OK.
5. Transaction cross-DATABASE tidak didukung — `erp_sales.bangron` dan `erp_finance.bangron` punya connection terpisah. Pakai [Saga Pattern](modular-architecture.md#82-strategi-mitigasi-inconsistency) atau idempotent hook dengan status flag.
6. Hook cross-database TIDAK atomic — hook di `erp_sales.invoices` yang insert ke `erp_finance.journal_entries` tidak dalam transaction yang sama.

Lihat juga: [Auth & ACL → Transaction Safety](project-scenarios-auth-acl.md#8-transaction-safety-atomic-multi-step-operasi) untuk pola lengkap.

---

## 10. Anti-Pattern & Tips Praktis

### 10.1 Anti-Pattern yang Sering Muncul

1. **Simpan transaksi sebagai 1 dokumen raksasa** — mis. SO dengan 1000 line items. BangronDB optimal untuk dokumen <100KB. Pisahkan line items ke collection `sales_order_lines` jika >500 line.

2. **Skip schema validation "untuk cepat"** — sadar atau tidak, ini menumpuk debt. Schema validation adalah kontrak API Anda. Setelah production, sangat susah menambahkan constraint karena data lama mungkin melanggar.

3. **Tidak pakai transaction untuk transfer stok** — transfer antar gudang harus atomic (pengurangan di WH-A + penambahan di WH-B). Pakai PDO `beginTransaction()` di level collection (lihat [§9.3](#93-pola-manual-untuk-multi-step-non-hook)):

   ```php
   $conn = collection('stock_movements')->database->connection;
   $conn->beginTransaction();
   try {
       collection('stock_movements')->insert([...]);  // WH-A out
       collection('stock_movements')->insert([...]);  // WH-B in
       $conn->commit();
   } catch (\Throwable $e) {
       if ($conn->inTransaction()) $conn->rollBack();
       throw $e;
   }
   ```

   Alternatif SQL-level: `db()->queryExecutor->executeTransaction([...])` (lebih low-level, kurang idiomatis).

4. **Hard-delete tanpa audit** — di ERP, delete adalah operasi berbahaya. Selalu pakai soft delete (`useSoftDeletes(true)`) untuk transaksi, dan simpan alasan delete di field `delete_reason`.

5. **Encryption key di code / git** — ini kardus. Simpan di `.env`, rotate per tahun, backup di password manager. Lihat [docs/security.md](security.md) untuk panduan key rotation.

6. **Query N+1 di loop reporting** — mis. loop 1000 invoice, di tiap iterasi `findOne(['customer_id' => ...])`. Solusi: ambil semua customer sekali, map ke dictionary, lookup in-memory.

7. **Tidak index field yang sering di-filter** — `customer_id`, `so_date`, `status` wajib searchable. Cek dengan `explain()` sebelum production.

### 10.2 Tips Praktis

- **Backup**: file `.bangron` adalah satu file — backup cukup copy file. Untuk backup hot (saat aplikasi running), pakai SQLite `.backup` command via PDO.
- **Multi-tenant**: kalau harus multi-tenant, buat 1 database `.bangron` per tenant (di subfolder `data/tenant-{id}/`), bukan 1 collection dengan field `tenant_id`. Lebih aman dan isolasi lebih baik.
- **Versioning data**: gunakan `ChangeTrackingTrait` (`getLastModified()`) untuk cache invalidation — mis. dashboard P&L cache hasil 5 menit, invalidate saat ada journal_entries baru.
- **Migration schema**: jika schema berubah (tambah field), dokumen lama tidak otomatis di-migrate. Buat migration script satu kali yang baca semua dokumen, tambah field default, update. Jalankan di maintenance window. Bungkus tiap batch dalam `beginTransaction()` agar tidak ada dokumen yang ter-migrate setengah (lihat [§9.3](#93-pola-manual-untuk-multi-step-non-hook)).

---

## 11. Kesimpulan & Referensi

### Kapan BangronDB Cocok untuk ERP

BangronDB shines pada skenario ERP berikut:

- **Single-tenant SMB**: 1-50 user, jutaan transaksi, satu server. Tidak perlu DBA, tidak perlu connection pool.
- **Appliance / on-premise**: aplikasi di-deploy di server customer. Cukup taruh file `.bangron` di `/var/lib/erp/` dan backup harian.
- **Modul ERP embedded**: mis. plugin WooCommerce yang butuh inventory sendiri, atau modul HR yang embed ke aplikasi existing.
- **MVP / PoC**: butuh cepat jalan tanpa setup PostgreSQL. Schema validation + hooks BangronDB sudah cukup untuk demo fungsional.

### Kapan Tidak Cocok

- **Multi-tenant SaaS skala besar** — SQLite WAL punya limitasi concurrent writer. Pertimbangkan PostgreSQL dengan RLS.
- **Write-heavy concurrent** (>100 write/detik dari user berbeda) — SQLite akan serialize write, performance turun.
- **Replication geografis** — BangronDB tidak punya built-in replication. Untuk multi-region, butuh aplikasi-level sync.

### Kombinasi dengan Flight PHP

Flight PHP cocok dengan BangronDB karena keduanya sama-sama "embedded" — tidak butuh server terpisah. Pola yang umum:

- Flight untuk HTTP routing + middleware (auth, RBAC, validation request).
- BangronDB untuk persistence + business logic (schema validation, hooks, encryption).
- Service class di `src/Services/` untuk orchestration — mis. `SalesOrderService` yang koordinasi insert SO + stock_movement + journal_entry dalam transaction.

### Referensi Dokumen Lain

- [Getting Started](getting-started.md) — instalasi, konsep dasar, quick start CRUD.
- [Query Operators](query-operators.md) — daftar lengkap operator `$gt`, `$in`, `$regex`, dll.
- [Security](security.md) — encryption, key rotation, blind index, security auditor.
- [Hook Patterns](hook-patterns.md) — pola hook lanjutan, veto, recursion guard.
- [Schema Metadata Guide](schema-metadata-guide.md) — UI-enhanced types (email, password, slug, relation), metadata collection.
- [Framework Integration](framework-integration.md) — integrasi dengan Laravel, Slim, CodeIgniter, Symfony, dan framework lain.
- [API Reference](api-reference.md) — referensi lengkap semua method Client, Database, Collection, Cursor.

### Contoh Code Lengkap

Contoh code lengkap ERP dengan Flight PHP + BangronDB (multi-modul: products, customers, SO, invoice, journal, reporting) tersedia di direktori `examples/`:

- `examples/19-ecommerce-app.php` — pola e-commerce yang mirip ERP (products, orders, stock).
- `examples/22-rbac-users-roles-permissions.php` — implementasi RBAC lengkap.
- `examples/13-transactions.php` — pola transaction untuk operasi atomic.

---

**Terakhir diperbarui:** Sesuai commit terbaru BangronDB (`master` branch).  
**Lisensi dokumen:** Mengikuti lisensi repo BangronDB (MIT).
