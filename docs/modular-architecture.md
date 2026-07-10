# Arsitektur Modular BangronDB: Integrasi ERP + CRM + SCM + HRIS + POS

> Dokumen ini menjelaskan strategi modular dan cross-database di BangronDB untuk membangun sistem otomasi bisnis terintegrasi: **ERP** (operasional bisnis), **CRM** (pelanggan), **SCM** (rantai pasok), **HRIS** (SDM), dan **POS** (kasir). Cocok untuk PHP developer yang membangun aplikasi multi-modul dengan Flight PHP.

## Daftar Isi

1. [Mengapa Modular?](#1-mengapa-modular)
2. [Strategi Database per Modul](#2-strategi-database-per-modul)
3. [Bootstrap Multi-Database di Flight PHP](#3-bootstrap-multi-database-di-flight-php)
4. [Cross-Database Populate](#4-cross-database-populate)
5. [Event-Driven Integration via Hooks](#5-event-driven-integration-via-hooks)
   - [5.1 ERP Sales → ERP Finance (Auto Journal)](#51-erp-sales--erp-finance-auto-journal)
   - [5.2 CRM → ERP Core (Lead Conversion)](#52-crm--erp-core-lead-conversion)
   - [5.3 SCM → ERP Finance (Goods Receipt Journal)](#53-scm--erp-finance-goods-receipt-journal)
   - [5.4 HRIS → ERP Finance (Payroll Journal)](#54-hris--erp-finance-payroll-journal)
   - [5.5 POS → ERP (Sync per Outlet)](#55-pos--erp-sync-per-outlet)
   - [5.6 Hook Recursion Guard](#56-hook-recursion-guard)
   - [5.7 Transaction di Cross-Module Integration](#57-transaction-di-cross-module-integration)
6. [Multi-Tenant dengan Strategi yang Sama](#6-multi-tenant-dengan-strategi-yang-sama)
7. [Backup, Restore, dan Migration](#7-backup-restore-dan-migration)
8. [Trade-off & Kapan Tidak Pakai Pola Ini](#8-trade-off--kapan-tidak-pakai-pola-ini)

---

## 1. Mengapa Modular?

Sistem otomasi bisnis tradisional biasanya monolitik: satu database raksasa dengan ratusan tabel. Pendekatan ini punya masalah:

- **Blast radius besar** — bug di modul HR bisa korupsi data POS.
- **Backup berat** — backup semua tabel tiap hari, padahal modul POS butuh backup per jam, HR cukup per minggu.
- **Permission kompleks** — RBAC harus handle akses cross-tabel, mudah bocor.
- **Scaling rigid** — modul high-volume (POS, attendance) harus pakai engine sama dengan modul low-volume (master data).
- **Vendor lock-in** — semua data dalam satu RDBMS, sulit migrasi sebagian.

BangronDB menawarkan pola lain: **satu file `.bangron` per modul bisnis**. Setiap modul punya:

- Database file terpisah → isolasi failure.
- Encryption key sendiri → blast radius kebocoran terbatas.
- Schema validation sendiri → kontrak API jelas.
- Backup schedule sendiri → fleksibel sesuai kebutuhan.
- Migrasi independen → bisa replace satu modul tanpa sentuh yang lain.

**Analogi**: ini seperti microservice, tapi di level database. Modul tetap dalam satu aplikasi PHP (monolith modular), tapi database-nya terpisah.

---

## 2. Strategi Database per Modul

### 2.1 Layout Direktori

```
erp-app/
├── data/
│   ├── erp_core.bangron       # Master data: products, customers, suppliers, users, coa
│   ├── erp_sales.bangron      # Sales orders, invoices, shipments outbound
│   ├── erp_finance.bangron    # Journal entries, payments, cash, AR/AP
│   ├── erp_inventory.bangron  # Stock movements, warehouses, adjustments (atau gabung ke SCM)
│   ├── crm.bangron            # Leads, opportunities, activities, contacts
│   ├── scm.bangron            # Purchase orders, goods receipts, supplier contracts
│   ├── hris.bangron           # Employees, attendance, leave, payroll, PII
│   ├── hris_pii.bangron       # Data sensitif HRIS (terpisah + extra encryption)
│   ├── pos_central.bangron    # POS aggregated data (sync dari outlet)
│   ├── pos_outlet_001.bangron # POS per outlet (offline-first)
│   ├── pos_outlet_002.bangron
│   └── shared.bangron         # Reference data: countries, currencies, tax codes
└── config/
    └── modules.php            # Konfigurasi modul: key, paths, RBAC matrix
```

### 2.2 Mengapa Pisahkan Modul?

| Modul | Volume | Sensitivitas | Backup Freq | Alasan Pisah |
|-------|--------|--------------|-------------|--------------|
| `erp_core` | Rendah | Sedang | Mingguan | Master data, jarang berubah |
| `erp_sales` | Sedang | Sedang | Harian | Transaksi, butuh audit trail |
| `erp_finance` | Sedang | Tinggi | Harian | Journal entries, regulasi tax |
| `crm` | Sedang | Tinggi (PII) | Harian | Leads/activities, GDPR-sensitive |
| `scm` | Sedang | Sedang | Harian | Stock movements, supplier data |
| `hris` | Sedang | Sangat Tinggi | Harian | Gaji, KTP, medical |
| `hris_pii` | Rendah | Ekstrem | Saat ada perubahan | Encryption terpisah |
| `pos_outlet_*` | Tinggi | Sedang | Per jam | Offline-first, sync ke central |
| `shared` | Rendah | Rendah | Saat ada perubahan | Reference data, read-only dari modul lain |

### 2.3 Konfigurasi Modul

File `config/modules.php`:

```php
<?php
declare(strict_types=1);

return [
    'erp_core' => [
        'path'           => __DIR__ . '/../data/erp_core.bangron',
        'encryption_key' => env('ERP_CORE_KEY'),
        'description'    => 'Master data: products, customers, suppliers, users',
    ],
    'erp_sales' => [
        'path'           => __DIR__ . '/../data/erp_sales.bangron',
        'encryption_key' => env('ERP_SALES_KEY'),
        'description'    => 'Sales orders, invoices, shipments',
    ],
    'erp_finance' => [
        'path'           => __DIR__ . '/../data/erp_finance.bangron',
        'encryption_key' => env('ERP_FINANCE_KEY'),
        'description'    => 'Journal entries, payments, AR/AP',
    ],
    'crm' => [
        'path'           => __DIR__ . '/../data/crm.bangron',
        'encryption_key' => env('CRM_KEY'),
        'description'    => 'Leads, opportunities, activities',
    ],
    'scm' => [
        'path'           => __DIR__ . '/../data/scm.bangron',
        'encryption_key' => env('SCM_KEY'),
        'description'    => 'Purchase orders, goods receipts, stock movements',
    ],
    'hris' => [
        'path'           => __DIR__ . '/../data/hris.bangron',
        'encryption_key' => env('HRIS_KEY'),
        'description'    => 'Employees, attendance, leave, payroll',
    ],
    'hris_pii' => [
        'path'           => __DIR__ . '/../data/hris_pii.bangron',
        'encryption_key' => env('HRIS_PII_KEY'),
        'description'    => 'NIK, NPWP, bank accounts (extra-sensitive)',
    ],
    'pos_central' => [
        'path'           => __DIR__ . '/../data/pos_central.bangron',
        'encryption_key' => env('POS_CENTRAL_KEY'),
        'description'    => 'Aggregated POS data from all outlets',
    ],
    'shared' => [
        'path'           => __DIR__ . '/../data/shared.bangron',
        'encryption_key' => env('SHARED_KEY'),
        'description'    => 'Reference data: countries, currencies, tax codes',
    ],
];
```

---

## 3. Bootstrap Multi-Database di Flight PHP

### 3.1 Module Manager Service

```php
<?php
declare(strict_types=1);

namespace Acme\Services;

use BangronDB\Client;
use BangronDB\Database;

class ModuleManager
{
    /** @var array<string, Database> */
    private array $dbs = [];

    private Client $client;

    public function __construct(string $dataPath)
    {
        $this->client = new Client($dataPath);
    }

    public function loadModules(array $moduleConfig): void
    {
        foreach ($moduleConfig as $name => $config) {
            $this->dbs[$name] = $this->client->createDB($name);
            // Set encryption key jika ada
            if (!empty($config['encryption_key'])) {
                $this->dbs[$name]->setEncryptionKey($config['encryption_key']);
            }
        }
    }

    public function db(string $module): Database
    {
        if (!isset($this->dbs[$module])) {
            throw new \InvalidArgumentException("Module '{$module}' not loaded");
        }
        return $this->dbs[$module];
    }

    public function collection(string $module, string $name): \BangronDB\Collection
    {
        return $this->db($module)->createCollection($name);
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}
```

### 3.2 Bootstrap di Flight

```php
<?php
// public/index.php
require_once __DIR__ . '/../vendor/autoload.php';

use Acme\Services\ModuleManager;
use BangronDB\Config;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Configure BangronDB global
Config::set('default_path', __DIR__ . '/../data');
Config::set('journal_mode', 'WAL');
Config::set('synchronous', 'NORMAL');

// Bootstrap modules
$moduleConfig = require __DIR__ . '/../config/modules.php';
$mm = new ModuleManager(__DIR__ . '/../data');
$mm->loadModules($moduleConfig);

// Register as Flight service
Flight::set('mm', $mm);

// Helper functions
function mm(): ModuleManager { return Flight::get('mm'); }
function db(string $module): \BangronDB\Database { return mm()->db($module); }
function coll(string $module, string $name): \BangronDB\Collection {
    return mm()->collection($module, $name);
}

// Example route: create SO in erp_sales, populate customer from erp_core
Flight::route('POST /api/sales-orders', function () {
    $data = Flight::request()->data->getData();

    // Insert SO di modul erp_sales
    $soId = coll('erp_sales', 'sales_orders')->insert($data);

    // Populate customer dari modul erp_core
    $so = coll('erp_sales', 'sales_orders')
        ->findOne(['_id' => $soId])
        ->populate('customer_id', 'erp_core.customers', ['name', 'code', 'email']);

    Flight::json($so, 201);
});

Flight::start();
```

---

## 4. Cross-Database Populate

BangronDB mendukung notasi `database.collection` untuk populate antar database:

### 4.1 Single-Level Cross-Database Populate

```php
// Sales Order di erp_sales, customer di erp_core
$so = coll('erp_sales', 'sales_orders')
    ->findOne(['so_number' => 'SO-2026-000001'])
    ->populate('customer_id', 'erp_core.customers', ['name', 'code', 'email']);
// Hasil: $so['customer'] = { name, code, email }
```

### 4.2 Multi-Level Cross-Database Populate

```php
// Opportunity di crm, customer di erp_core, contact juga di erp_core
$opp = coll('crm', 'opportunities')
    ->findOne(['opp_id' => 'OPP-000123'])
    ->populateMany([
        'customer_id' => ['collection' => 'erp_core.customers', 'fields' => ['name', 'code']],
        'contact_id'  => ['collection' => 'erp_core.contacts',  'fields' => ['first_name', 'email']],
    ]);
```

### 4.3 Cross-Module Chain Populate

Untuk dashboard customer 360° yang menggabungkan data dari semua modul:

```php
function getCustomer360(string $customerId): array
{
    return [
        // Dari erp_core
        'customer'    => coll('erp_core', 'customers')->findOne(['_id' => $customerId]),
        'contacts'    => coll('erp_core', 'contacts')->find(['customer_id' => $customerId])->toArray(),

        // Dari erp_sales
        'sales_orders'=> coll('erp_sales', 'sales_orders')
            ->find(['customer_id' => $customerId], [], ['sort' => ['so_date' => -1], 'limit' => 20])
            ->toArray(),
        'invoices'    => coll('erp_sales', 'invoices')
            ->find(['customer_id' => $customerId], [], ['sort' => ['inv_date' => -1], 'limit' => 10])
            ->toArray(),

        // Dari crm
        'opportunities' => coll('crm', 'opportunities')
            ->find(['customer_id' => $customerId], [], ['sort' => ['created_at' => -1]])
            ->toArray(),
        'activities'  => coll('crm', 'activities')
            ->find(['related_to' => $customerId], [], ['sort' => ['completed_at' => -1], 'limit' => 50])
            ->toArray(),

        // Dari scm (shipments ke customer ini)
        'shipments'   => coll('scm', 'shipments')
            ->find(['customer_id' => $customerId], [], ['sort' => ['ship_date' => -1], 'limit' => 10])
            ->toArray(),

        // Dari pos_central (transaksi POS customer ini)
        'pos_transactions' => coll('pos_central', 'transactions')
            ->find(['customer_id' => $customerId], [], ['sort' => ['transaction_date' => -1], 'limit' => 20])
            ->toArray(),
    ];
}
```

---

## 5. Event-Driven Integration via Hooks

Modul berbeda perlu sinkron — misalnya, saat invoice dibuat di `erp_sales`, otomatis buat journal entry di `erp_finance`. Pola: hook `afterInsert` di modul A → insert di modul B.

### 5.1 ERP Sales → ERP Finance (Auto Journal)

```php
coll('erp_sales', 'invoices')->on('afterInsert', function (array $invoice) {
    coll('erp_finance', 'journal_entries')->insert([
        'je_number'   => 'JE-' . $invoice['inv_number'],
        'je_date'     => $invoice['inv_date'],
        'description' => 'Auto-JE for invoice ' . $invoice['inv_number'],
        'source_type' => 'sales_invoice',
        'source_id'   => $invoice['_id'],
        'is_posted'   => true,
        'total_debit' => $invoice['amount'],
        'total_credit'=> $invoice['amount'],
        'lines' => [
            ['account_code' => '1100-00', 'debit' => $invoice['amount'], 'credit' => 0], // AR
            ['account_code' => '4000-00', 'debit' => 0, 'credit' => $invoice['amount']], // Revenue
        ],
    ]);
});
```

### 5.2 CRM → ERP Core (Lead Conversion)

```php
coll('crm', 'leads')->on('afterUpdate', function (array $old, array $new) {
    if (($old['status'] ?? '') !== 'converted' && $new['status'] === 'converted') {
        // Buat customer di erp_core
        $customerId = 'CUST-' . str_pad(
            (string) (coll('erp_core', 'customers')->count([]) + 1), 5, '0', STR_PAD_LEFT
        );
        coll('erp_core', 'customers')->insert([
            'code'  => $customerId,
            'name'  => $new['company'] ?? trim($new['first_name'] . ' ' . $new['last_name']),
            'email' => $new['email'],
            'phone' => $new['phone'],
            'source'=> 'crm_conversion',
            'is_active' => true,
        ]);
    }
});
```

### 5.3 SCM → ERP Finance (Goods Receipt Journal)

```php
coll('scm', 'goods_receipts')->on('afterInsert', function (array $gr) {
    // Stock movement (di scm)
    // ... (lihat docs/project-scenarios-scm.md)

    // Journal entry (di erp_finance)
    $totalValue = array_sum(array_map(
        fn($l) => $l['qty_received'] * ($l['unit_cost'] ?? 0),
        $gr['lines']
    ));

    coll('erp_finance', 'journal_entries')->insert([
        'je_number'   => 'JE-GR-' . $gr['gr_number'],
        'je_date'     => $gr['gr_date'],
        'description' => 'Auto-JE for GR ' . $gr['gr_number'],
        'source_type' => 'purchase_gr',
        'source_id'   => $gr['_id'],
        'is_posted'   => true,
        'total_debit' => $totalValue,
        'total_credit'=> $totalValue,
        'lines' => [
            ['account_code' => '1200-00', 'debit' => $totalValue, 'credit' => 0], // Inventory
            ['account_code' => '2100-00', 'debit' => 0, 'credit' => $totalValue], // AP
        ],
    ]);
});
```

### 5.4 HRIS → ERP Finance (Payroll Journal)

```php
coll('hris', 'payroll_runs')->on('afterUpdate', function (array $old, array $new) {
    if (($old['status'] ?? '') !== 'approved' && $new['status'] === 'approved') {
        coll('erp_finance', 'journal_entries')->insert([
            'je_number'   => 'JE-PAY-' . $new['payroll_id'],
            'je_date'     => $new['run_date'],
            'description' => 'Payroll ' . $new['period_year'] . '-' . $new['period_month'],
            'source_type' => 'payroll',
            'source_id'   => $new['_id'],
            'is_posted'   => true,
            'total_debit' => $new['total_gross'],
            'total_credit'=> $new['total_gross'],
            'lines' => [
                ['account_code' => '5100-00', 'debit' => $new['total_gross'], 'credit' => 0],
                ['account_code' => '2100-10', 'debit' => 0, 'credit' => $new['total_net']],
                ['account_code' => '2100-20', 'debit' => 0, 'credit' => $new['total_deduction']],
            ],
        ]);
    }
});
```

### 5.5 POS → ERP (Sync per Outlet)

```php
// Cron job per jam: sync POS outlet ke erp_sales + erp_finance
function syncOutletToCentral(string $outletId): array
{
    $pos = mm()->db('pos_outlet_' . $outletId);
    $synced = ['so' => 0, 'je' => 0];

    $pending = $pos->collection('transactions')->find([
        'sync_status' => 'pending', 'status' => 'completed'
    ])->limit(500)->toArray();

    foreach ($pending as $trx) {
        // Create SO di erp_sales
        coll('erp_sales', 'sales_orders')->insert([
            'so_number'   => 'SO-POS-' . $trx['transaction_no'],
            'so_date'     => $trx['transaction_date'],
            'customer_id' => $trx['customer_id'] ?? 'WALK-IN-' . $trx['outlet_id'],
            'sales_rep_id'=> $trx['cashier_id'],
            'status'      => 'fulfilled',
            'lines'       => $trx['lines'],
            'subtotal'    => $trx['subtotal'],
            'tax_total'   => $trx['tax_total'],
            'grand_total' => $trx['grand_total'],
            'source'      => 'pos_sync',
            'source_ref'  => $trx['transaction_no'],
        ]);
        $synced['so']++;

        // Create JE di erp_finance untuk cash payments
        foreach ($trx['payments'] as $payment) {
            coll('erp_finance', 'journal_entries')->insert([
                'je_number'   => 'JE-POS-' . $trx['transaction_no'] . '-' . $payment['method'],
                'je_date'     => $trx['transaction_date'],
                'description' => 'POS ' . $trx['transaction_no'],
                'source_type' => 'pos_sales',
                'source_id'   => $trx['_id'],
                'is_posted'   => true,
                'total_debit' => $payment['amount'],
                'total_credit'=> $payment['amount'],
                'lines' => [
                    ['account_code' => '1100-10', 'debit' => $payment['amount'], 'credit' => 0],
                    ['account_code' => '4000-00', 'debit' => 0, 'credit' => $trx['subtotal']],
                ],
            ]);
            $synced['je']++;
        }

        // Update sync status di local POS
        $pos->collection('transactions')->update(
            ['_id' => $trx['_id']],
            ['$set' => ['sync_status' => 'synced', 'synced_at' => date('c')]]
        );
    }
    return $synced;
}
```

### 5.6 Hook Recursion Guard

Saat hook di modul A insert ke modul B yang juga punya hook, gunakan flag untuk hindari loop:

```php
coll('erp_sales', 'sales_orders')->on('afterInsert', function (array $so) {
    // Skip jika SO berasal dari POS sync (sudah ada JE-nya)
    if (($so['source'] ?? '') === 'pos_sync') return;

    // ... normal hook logic
});
```

> **Catatan Transaction:** Hook cross-module (insert di modul B dari hook modul A) **TIDAK dalam transaction yang sama** — setiap modul punya PDO connection terpisah. Insert di modul A sukses tapi insert di modul B gagal = data tidak konsisten antar modul. Untuk pola idempotent + reconciliation + saga yang mengatasi keterbatasan ini, lihat §5.7 dan §8.2.

### 5.7 Transaction di Cross-Module Integration

Hook cross-module (insert di modul B dari hook modul A) **TIDAK dalam transaction yang sama** karena setiap modul punya PDO connection terpisah. Contoh:

```php
// Hook di modul erp_sales — connection-nya erp_sales
coll('erp_sales', 'invoices')->on('afterInsert', function (array $invoice) {
    // Insert ke erp_finance — connection-nya erp_finance, BUKAN erp_sales
    // TIDAK dalam transaction yang sama dengan insert invoice!
    coll('erp_finance', 'journal_entries')->insert([...]);

    // Update invoice.journal_id — connection erp_sales, atomic dengan insert invoice
    coll('erp_sales', 'invoices')->update(
        ['_id' => $invoice['_id']],
        ['$set' => ['journal_id' => $jeId]]
    );
});
```

**Aturan transaction cross-module:**

1. **Same-module operations = atomic otomatis** dalam hook.
2. **Cross-module operations = TIDAK atomic** — pakai idempotent pattern + reconciliation.
3. **Compensating action (saga)** untuk cross-module critical workflow.
4. **Reconciliation job** periodik untuk cek konsistensi (lihat §8.2).

Pola idempotent dengan flag:

```php
coll('erp_sales', 'invoices')->on('afterInsert', function (array $invoice) {
    if (!empty($invoice['journal_id'])) return; // sudah ada JE, skip (idempotent)

    // Cross-DB insert — TIDAK atomic dengan invoice insert
    $jeId = coll('erp_finance', 'journal_entries')->insert([...]);

    // Update invoice dengan journal_id — atomic dengan invoice (same DB)
    coll('erp_sales', 'invoices')->update(
        ['_id' => $invoice['_id']],
        ['$set' => ['journal_id' => $jeId]]
    );
});
```

Reconciliation job per jam untuk catch-up kalau cross-DB insert gagal:

```php
function reconcileInvoicesToJournal(): array {
    $invoices = coll('erp_sales', 'invoices')->find([
        'status' => 'completed', 'journal_id' => null
    ])->toArray();

    $reconciled = 0;
    foreach ($invoices as $inv) {
        // Insert JE yang missing
        $jeId = coll('erp_finance', 'journal_entries')->insert([...]);
        coll('erp_sales', 'invoices')->update(
            ['_id' => $inv['_id']],
            ['$set' => ['journal_id' => $jeId]]
        );
        $reconciled++;
    }
    return ['reconciled' => $reconciled];
}
```

---

## 6. Multi-Tenant dengan Strategi yang Sama

Untuk SaaS multi-tenant, extend strategi modular: **1 set database per tenant**.

### 6.1 Layout Multi-Tenant

```
erp-app/data/
├── tenant_001/
│   ├── erp_core.bangron
│   ├── erp_sales.bangron
│   └── ...
├── tenant_002/
│   ├── erp_core.bangron
│   └── ...
└── shared/                   # Reference data global
    └── shared.bangron
```

### 6.2 Tenant-Aware Module Manager

```php
class TenantModuleManager
{
    private string $baseDataPath;
    private array $tenantCache = [];

    public function __construct(string $baseDataPath)
    {
        $this->baseDataPath = $baseDataPath;
    }

    public function forTenant(string $tenantId): ModuleManager
    {
        if (!isset($this->tenantCache[$tenantId])) {
            $tenantPath = $this->baseDataPath . '/tenant_' . $tenantId;
            if (!is_dir($tenantPath)) {
                throw new \RuntimeException("Tenant not found: {$tenantId}");
            }
            $this->tenantCache[$tenantId] = new ModuleManager($tenantPath);
        }
        return $this->tenantCache[$tenantId];
    }
}

// Resolve tenant dari subdomain atau JWT
Flight::before('start', function (array $params) {
    $tenantId = resolveTenantFromHost($_SERVER['HTTP_HOST'] ?? '');
    $tmm = new TenantModuleManager(__DIR__ . '/../data');
    Flight::set('mm', $tmm->forTenant($tenantId));
});
```

### 6.3 Trade-off Multi-Tenant

**Pro:**
- Isolasi data sempurna — bug di tenant A tidak sentuh tenant B.
- Backup/restore per tenant — customer besar bisa backup lebih sering.
- Compliance mudah — data tenant bisa di-export/dihapus tanpa sentuh tenant lain.

**Kontra:**
- Banyak file — 1000 tenant = 1000 set database = 5000+ file `.bangron`.
- Schema migration harus di-loop per tenant — lambat.
- Cross-tenant query (mis. analytics) harus aggregate manual.

**Kapan pakai**: vertical SaaS dengan tenant sedikit (1-100) tapi data sensitivity tinggi (klinik, law firm, government). Untuk tenant ribuan, pertimbangkan single-database dengan `tenant_id` field.

---

## 7. Backup, Restore, dan Migration

### 7.1 Backup per Modul

```php
function backupModule(string $module, string $backupPath): void
{
    $source = mm()->db($module)->getPath();
    $dest   = $backupPath . '/' . $module . '-' . date('Y-m-d-His') . '.bangron';

    // Hot backup menggunakan SQLite .backup command (via PDO)
    $pdo = mm()->db($module)->getConnection();
    $pdo->exec("VACUUM INTO '{$dest}'");

    // Atau simple copy (tutup dulu connection-nya untuk konsistensi)
    // copy($source, $dest);
}
```

### 7.2 Backup Schedule per Modul

```php
// Cron job
$schedule = [
    'erp_core'    => 'weekly',    // master data, jarang berubah
    'erp_sales'   => 'daily',
    'erp_finance' => 'daily',
    'crm'         => 'daily',
    'scm'         => 'daily',
    'hris'        => 'daily',
    'hris_pii'    => 'on_change', // backup saat ada perubahan
    'pos_central' => 'hourly',    // high-volume
    'shared'      => 'on_change',
];

foreach ($schedule as $module => $freq) {
    if (shouldRun($freq, $module)) {
        backupModule($module, __DIR__ . '/../backups');
    }
}
```

### 7.3 Restore per Modul

```php
function restoreModule(string $module, string $backupFile): void
{
    // Tutup koneksi aktif
    mm()->db($module)->close();

    // Replace file
    $dest = mm()->db($module)->getPath();
    copy($backupFile, $dest);

    // Reopen — koneksi baru akan baca file hasil restore
    mm()->reloadModule($module);
}
```

### 7.4 Schema Migration per Modul

```php
class MigrationRunner
{
    public function run(string $module, string $fromVersion, string $toVersion): void
    {
        $migrationFile = __DIR__ . "/../migrations/{$module}/{$fromVersion}_to_{$toVersion}.php";
        if (!file_exists($migrationFile)) {
            throw new \RuntimeException("Migration not found: {$migrationFile}");
        }

        $db = mm()->db($module);
        $migration = require $migrationFile;
        $migration($db);

        // Update version tracking
        coll('shared', 'module_versions')->update(
            ['module' => $module],
            ['$set' => ['version' => $toVersion, 'migrated_at' => date('c')]],
            true
        );
    }
}

// Contoh migration: crm/v1.0_to_v1.1.php
return function (\BangronDB\Database $db) {
    $leads = $db->createCollection('leads');
    // Tambah field baru ke semua dokumen
    foreach ($leads->find([])->stream() as $lead) {
        if (!isset($lead['lead_score'])) {
            $leads->update(
                ['_id' => $lead['_id']],
                ['$set' => ['lead_score' => 50]]
            );
        }
    }
};
```

---

## 8. Trade-off & Kapan Tidak Pakai Pola Ini

### 8.1 Trade-off Pola Modular

**Pro:**
- Isolasi failure — modul A down tidak mempengaruhi modul B.
- Encryption key terpisah — kebocoran key HRIS tidak expose data POS.
- Backup fleksibel — per-modul, per-jadwal.
- Migrasi independen — schema change di modul A tidak ribet modul B.
- Team scaling — tim CRM bisa deploy tanpa koordinasi dengan tim HRIS.

**Kontra:**
- Cross-database populate lebih lambat dari JOIN SQL — ada 2 query round-trip.
- Tidak ada transactional consistency antar modul — insert di modul A sukses tapi insert di modul B gagal = data tidak konsisten.
- Memory usage lebih tinggi — multiple SQLite connections.
- Backup management lebih kompleks — harus track konsistensi antar modul.

### 8.2 Strategi Mitigasi Inconsistency

Karena tidak ada transaction cross-database, gunakan pattern berikut:

**1. Idempotent Hooks dengan Status Flag:**

Pola idempotent + transaction yang benar — setiap database dibungkus `beginTransaction()` masing-masing (cross-DB tidak bisa satu transaction):

```php
coll('erp_sales', 'invoices')->on('afterInsert', function (array $inv) {
    if (!empty($inv['journal_id'])) return; // sudah ada JE, skip (idempotent)

    // Cross-DB insert JE — atomic di sisi erp_finance
    $connFin = coll('erp_finance', 'journal_entries')->database->connection;
    $connFin->beginTransaction();
    try {
        $jeId = coll('erp_finance', 'journal_entries')->insert([...]);
        $connFin->commit();
    } catch (\Throwable $e) {
        if ($connFin->inTransaction()) $connFin->rollBack();
        throw $e;
    }

    // Update invoice.journal_id — atomic di sisi erp_sales (same DB dengan invoice insert)
    $connSales = coll('erp_sales', 'invoices')->database->connection;
    $connSales->beginTransaction();
    try {
        coll('erp_sales', 'invoices')->update(
            ['_id' => $inv['_id']],
            ['$set' => ['journal_id' => $jeId]]
        );
        $connSales->commit();
    } catch (\Throwable $e) {
        if ($connSales->inTransaction()) $connSales->rollBack();
        // JE sudah ter-insert di erp_finance tapi invoice.journal_id belum ter-update.
        // Reconciliation job per jam akan catch-up (lihat bawah) — idempotent flag mencegah double-insert JE.
        throw $e;
    }
});
```

**Catatan transaction:** Karena `erp_sales` dan `erp_finance` adalah 2 database terpisah, tidak ada satu transaction yang membungkus keduanya. Pola yang benar: (1) bungkus tiap DB operation dalam `beginTransaction()` masing-masing agar atomic di sisi DB-nya, (2) gunakan idempotent flag (`journal_id`) agar reconciliation job bisa retry tanpa double-insert JE. Untuk penjelasan lengkap pola cross-module transaction, lihat §5.7.

**2. Reconciliation Job:**

```php
// Cron job per jam: cek konsistensi antar modul
function reconcileInvoicesToJournal(): array
{
    $invoices = coll('erp_sales', 'invoices')->find([
        'status' => 'completed', 'journal_id' => null
    ])->toArray();

    $reconciled = 0;
    foreach ($invoices as $inv) {
        // Idempotent: cek dulu apakah JE sudah ada (mungkin sudah dibuat oleh hook,
        // hanya update invoice.journal_id yang gagal)
        $existingJe = coll('erp_finance', 'journal_entries')->findOne([
            'source_type' => 'sales_invoice', 'source_id' => $inv['_id']
        ]);

        if ($existingJe) {
            $jeId = $existingJe['_id'];
        } else {
            // Insert JE yang missing — atomic di sisi erp_finance
            $connFin = coll('erp_finance', 'journal_entries')->database->connection;
            $connFin->beginTransaction();
            try {
                $jeId = coll('erp_finance', 'journal_entries')->insert([
                    'je_number'   => 'JE-' . $inv['inv_number'],
                    'je_date'     => $inv['inv_date'],
                    'source_type' => 'sales_invoice',
                    'source_id'   => $inv['_id'],
                    // ... field JE lainnya ...
                ]);
                $connFin->commit();
            } catch (\Throwable $e) {
                if ($connFin->inTransaction()) $connFin->rollBack();
                continue; // skip invoice ini, retry di run berikutnya
            }
        }

        // Update invoice.journal_id — atomic di sisi erp_sales
        coll('erp_sales', 'invoices')->update(
            ['_id' => $inv['_id']],
            ['$set' => ['journal_id' => $jeId]]
        );
        $reconciled++;
    }
    return ['reconciled' => $reconciled];
}
```

**Catatan:** Reconciliation job ini juga idempotent — kalau dijalankan 2x, tidak akan double-insert JE (cek `findOne` dulu) dan tidak akan double-update invoice (idempotent `$set`).

**3. Saga Pattern untuk Critical Workflow:**

Untuk workflow kritis (mis. sales order → shipment → invoice → payment), implementasi compensating action:

```php
function createSalesOrderSaga(array $data): array
{
    $steps = [
        'create_so'      => fn() => coll('erp_sales', 'sales_orders')->insert($data),
        'create_shipment'=> fn($soId) => coll('scm', 'shipments')->insert(['so_id' => $soId, ...]),
        'create_invoice' => fn($soId) => coll('erp_sales', 'invoices')->insert(['so_id' => $soId, ...]),
    ];

    $compensations = [
        'create_so'       => fn($soId) => coll('erp_sales', 'sales_orders')->update(
            ['_id' => $soId], ['$set' => ['status' => 'cancelled']]
        ),
        'create_shipment' => fn($shipId) => coll('scm', 'shipments')->update(
            ['_id' => $shipId], ['$set' => ['status' => 'cancelled']]
        ),
    ];

    // Execute steps, rollback kalau ada yang gagal
    // ...
}
```

### 8.3 Kapan Tidak Pakai Pola Ini

- **Aplikasi sederhana dengan 1-2 modul** — overhead modular tidak sepadan. Pakai 1 database saja.
- **Butuh ACID cross-modul** — mis. financial transaction yang harus atomic antara sales + finance. Pakai PostgreSQL dengan transactional boundary.
- **Real-time dashboard cross-modul** — populate cross-database lambat. Materialize ke collection `dashboard_cache` yang di-refresh periodic.
- **Multi-region deployment** — BangronDB tidak punya replication. Untuk multi-region, butuh aplikasi-level sync.

---

## Kesimpulan

Strategi modular + cross-database BangronDB cocok untuk:

- **Aplikasi bisnis terintegrasi** (ERP + CRM + SCM + HRIS + POS) yang di-deploy per-customer.
- **SaaS vertical** dengan tenant sedikit tapi data sensitivity tinggi.
- **Appliance / on-premise** yang butuh isolasi modul tanpa kompleksitas microservice.

Dengan satu file `.bangron` per modul, Anda dapat:

- Backup per modul dengan schedule berbeda.
- Encryption key per modul — blast radius kebocoran terbatas.
- Schema migration independen.
- Cross-database populate untuk dashboard terintegrasi.
- Hook event-driven untuk sync antar modul.

Untuk project dengan requirement real-time consistency atau multi-region, pertimbangkan PostgreSQL atau distributed database. BangronDB bukan pengganti semua database — tapi untuk kelas aplikasi SMB / on-premise / appliance, pola modular ini adalah sweet spot.

---

## Referensi

- [ERP Scenario](project-scenarios-erp.md) — implementasi ERP modular.
- [CRM Scenario](project-scenarios-crm.md) — implementasi CRM dengan PII encryption.
- [SCM Scenario](project-scenarios-scm.md) — implementasi SCM dengan stock movement event log.
- [HRIS Scenario](project-scenarios-hris.md) — implementasi HRIS dengan multi-level encryption.
- [POS Scenario](project-scenarios-pos.md) — implementasi POS offline-first.
- [Framework Integration](framework-integration.md) — integrasi dengan framework lain.
- [Security](security.md) — encryption, blind index, RBAC.
- [Hook Patterns](hook-patterns.md) — pola hook lanjutan untuk event-driven integration.
