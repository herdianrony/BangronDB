---
layout: doc
permalink: /docs/scenarios/pos/
title: "Project Scenarios: POS"
description: "Cash drawer, transactions, multi-outlet sync."
toc: true
edit_on_github: true
prev:
  url: /docs/scenarios/hris/
  title: "Project Scenarios: HRIS"
next:
  url: /docs/scenarios/auth-acl/
  title: "Auth & ACL"
---
# Tips & Trick BangronDB: Skenario Project POS dengan Flight PHP

> Panduan praktis implementasi BangronDB pada modul POS (Point of Sale) — mencakup cash drawer, transaction processing, receipt printing, multi-outlet sync, dan real-time stock deduction. Pola high-volume write (ribuan transaksi/outlet/hari) dengan offline-first capability. Stack: Flight PHP.

## Daftar Isi

1. [Pendahuluan](#1-pendahuluan)
2. [Schema Design POS](#2-schema-design-pos)
3. [Query Patterns POS](#3-query-patterns-pos)
4. [Hooks & Events POS](#4-hooks--events-pos)
5. [Performance & Indexing](#5-performance--indexing)
6. [Security di POS](#6-security-di-pos)
7. [Relasi & Cross-Module Populate](#7-relasi--cross-module-populate)
8. [Transaction Safety](#8-transaction-safety)
9. [Anti-Pattern POS](#9-anti-pattern-pos)

---

## 1. Pendahuluan

POS berbeda dari modul lain: **write-heavy, real-time, offline-tolerant**. Setiap transaksi = insert baru, stok harus langsung update, receipt harus cetak dalam <2 detik. Outlet retail sering internet tidak stabil, sehingga POS harus bisa jalan offline (local database) lalu sync ke central saat online.

BangronDB ideal untuk POS karena:

- **Embedded** — tidak butuh koneksi ke server database. Local `.bangron` file per outlet.
- **Single-writer fast** — SQLite WAL mode serializes write dengan baik untuk 1 kasir.
- **Easy backup/sync** — file `.bangron` bisa di-compress & upload ke server pusat.
- **Schema validation** — mencegah kasir input transaksi dengan field salah.

**Kapan BangronDB cocok untuk POS:**

- POS single-outlet dengan 1-5 kasir concurrent.
- POS multi-outlet dengan sync periodik (per jam/hari), bukan real-time.
- Pop-up store / event POS yang butuh setup cepat.

**Kapan tidak cocok:**

- POS dengan >10 kasir concurrent di 1 outlet (butuh PostgreSQL + connection pool).
- Real-time central dashboard dengan <1 detik latency antar outlet.

---

## 2. Schema Design POS

### 2.1 Outlets & Cashiers

```php
collection('outlets')->setSchema([
    'outlet_id'     => ['type' => 'string', 'required' => true, 'unique' => true,
                         'regex' => '/^OUT-[0-9]{3}$/'],
    'name'          => ['type' => 'string', 'required' => true, 'min' => 2, 'max' => 100],
    'address'       => ['type' => 'string', 'max' => 500],
    'phone'         => ['type' => 'string', 'regex' => '/^\+?[0-9]{8,15}$/'],
    'timezone'      => ['type' => 'string', 'required' => true, 'default' => 'Asia/Jakarta'],
    'tax_rate'      => ['type' => 'float', 'min' => 0, 'max' => 1],
    'service_rate'  => ['type' => 'float', 'min' => 0, 'max' => 1],
    'is_active'     => ['type' => 'bool'],
    'open_hours'    => ['type' => 'array', 'max' => 7], // per day of week
])->saveConfiguration();

collection('cashiers')->setSchema([
    'cashier_id'    => ['type' => 'string', 'required' => true, 'unique' => true],
    'outlet_id'     => ['type' => 'string', 'required' => true],
    'user_id'       => ['type' => 'string', 'required' => true], // link ke users
    'pin_code_hash' => ['type' => 'string', 'required' => true], // bcrypt hash
    'shift_id'      => ['type' => 'string'], // shift yang sedang active
    'is_active'     => ['type' => 'bool'],
])->saveConfiguration();
```

### 2.2 Products & Categories (POS-specific)

```php
collection('pos_products')->setSchema([
    'sku'           => ['type' => 'string', 'required' => true, 'unique' => true],
    'name'          => ['type' => 'string', 'required' => true, 'max' => 100],
    'category'      => ['type' => 'string', 'required' => true],
    'price'         => ['type' => 'float', 'required' => true, 'min' => 0],
    'cost'          => ['type' => 'float', 'min' => 0], // di-encrypt
    'taxable'       => ['type' => 'bool'],
    'has_variant'   => ['type' => 'bool'],
    'variants'      => ['type' => 'array', 'max' => 50], // [{name, sku, price, stock}]
    'image_url'     => ['type' => 'string'],
    'is_active'     => ['type' => 'bool'],
    'requires_scale'=> ['type' => 'bool'], // untuk produk per-kg
    'allow_discount'=> ['type' => 'bool'],
])->saveConfiguration();

collection('categories')->setSchema([
    'category_id'   => ['type' => 'string', 'required' => true, 'unique' => true],
    'name'          => ['type' => 'string', 'required' => true, 'max' => 50],
    'parent_id'     => ['type' => 'string'], // nested category
    'display_order' => ['type' => 'int', 'min' => 0],
    'color'         => ['type' => 'string', 'regex' => '/^#[0-9A-F]{6}$/i'],
    'icon'          => ['type' => 'string'],
])->saveConfiguration();
```

### 2.3 Transactions (Sales)

```php
collection('transactions')->setSchema([
    'transaction_id'=> ['type' => 'string', 'required' => true, 'unique' => true],
    'transaction_no'=> ['type' => 'string', 'required' => true, 'unique' => true,
                         'regex' => '/^TRX-[0-9]{8}-[0-9]{6}$/'],
    'outlet_id'     => ['type' => 'string', 'required' => true],
    'cashier_id'    => ['type' => 'string', 'required' => true],
    'shift_id'      => ['type' => 'string', 'required' => true],
    'transaction_date' => ['type' => 'string', 'required' => true],
    'customer_id'   => ['type' => 'string'], // null = walk-in
    'lines'         => ['type' => 'array', 'required' => true, 'min' => 1, 'max' => 100],
    'subtotal'      => ['type' => 'float', 'required' => true, 'min' => 0],
    'discount_total'=> ['type' => 'float', 'min' => 0],
    'tax_total'     => ['type' => 'float', 'min' => 0],
    'service_total' => ['type' => 'float', 'min' => 0],
    'rounding'      => ['type' => 'float'], // pembulatan ke 50/100 rupiah
    'grand_total'   => ['type' => 'float', 'required' => true, 'min' => 0],
    'payments'      => ['type' => 'array', 'required' => true, 'min' => 1, 'max' => 5],
    'change_amount' => ['type' => 'float', 'min' => 0],
    'status'        => ['type' => 'string', 'enum' => ['completed', 'voided', 'refunded',
                         'partial_refund'], 'required' => true],
    'table_number'  => ['type' => 'string'], // untuk restaurant POS
    'order_type'    => ['type' => 'string', 'enum' => ['dine_in', 'takeaway', 'delivery',
                         'online'], 'default' => 'dine_in'],
    'notes'         => ['type' => 'string', 'max' => 200],
    'sync_status'   => ['type' => 'string', 'enum' => ['pending', 'synced', 'failed'],
                         'default' => 'pending'],
])->saveConfiguration();
```

### 2.4 Cash Drawer Sessions

```php
collection('cash_sessions')->setSchema([
    'session_id'    => ['type' => 'string', 'required' => true, 'unique' => true],
    'outlet_id'     => ['type' => 'string', 'required' => true],
    'cashier_id'    => ['type' => 'string', 'required' => true],
    'shift_id'      => ['type' => 'string', 'required' => true],
    'opened_at'     => ['type' => 'string', 'required' => true],
    'closed_at'     => ['type' => 'string'],
    'opening_balance' => ['type' => 'float', 'required' => true, 'min' => 0],
    'closing_balance_expected' => ['type' => 'float', 'min' => 0],
    'closing_balance_actual'   => ['type' => 'float', 'min' => 0],
    'cash_difference'  => ['type' => 'float'],
    'cash_in_amount'   => ['type' => 'float', 'min' => 0], // tambah kasir
    'cash_out_amount'  => ['type' => 'float', 'min' => 0], // tarik kasir
    'transaction_count'=> ['type' => 'int', 'min' => 0],
    'status'        => ['type' => 'string', 'enum' => ['open', 'closed', 'reconciled'],
                         'default' => 'open'],
    'notes'         => ['type' => 'string', 'max' => 500],
])->saveConfiguration();
```

**Tips schema POS:**

- `transaction_no` format `TRX-YYYYMMDD-NNNNNN` — sort lexicographic = sort chronological.
- `sync_status` untuk offline-first: `pending` = belum sync ke central, `synced` = sudah, `failed` = perlu retry.
- `payments` array of `[{method, amount, reference}]` — support multi-payment (cash + e-wallet + card).
- `rounding` penting di Indonesia — pembulangan ke kelipatan 50/100 rupiah untuk cash.

---

## 3. Query Patterns POS

### 3.1 Daily Sales Summary per Outlet

```php
function getDailySales(string $outletId, string $date): array
{
    return collection('transactions')->aggregate([
        ['$match' => [
            'outlet_id'        => $outletId,
            'transaction_date' => $date,
            'status'           => 'completed',
        ]],
        ['$group' => [
            '_id'         => '$order_type',
            'count'       => ['$sum' => 1],
            'subtotal'    => ['$sum' => '$subtotal'],
            'discount'    => ['$sum' => '$discount_total'],
            'tax'         => ['$sum' => '$tax_total'],
            'grand_total' => ['$sum' => '$grand_total'],
        ]],
        ['$sort' => ['_id' => 1]],
    ]);
}
```

### 3.2 Hourly Sales Heatmap (untuk stafing)

```php
function getHourlySales(string $outletId, string $date): array
{
    $transactions = collection('transactions')->find(
        ['outlet_id' => $outletId, 'transaction_date' => $date, 'status' => 'completed'],
        ['transaction_no', 'grand_total', 'transaction_date']
    )->toArray();

    $hourly = array_fill(0, 24, ['count' => 0, 'total' => 0]);
    foreach ($transactions as $trx) {
        // transaction_date format: '2026-07-10 14:32:15'
        $hour = (int) substr($trx['transaction_date'], 11, 2);
        $hourly[$hour]['count']++;
        $hourly[$hour]['total'] += $trx['grand_total'];
    }
    return $hourly;
}
```

### 3.3 Best-Selling Products

```php
function getBestSellers(string $outletId, string $fromDate, string $toDate, int $top = 10): array
{
    return collection('transactions')->aggregate([
        ['$match' => [
            'outlet_id'        => $outletId,
            'transaction_date' => ['$gte' => $fromDate, '$lte' => $toDate],
            'status'           => 'completed',
        ]],
        ['$unwind' => '$lines'],
        ['$group' => [
            '_id'        => '$lines.sku',
            'name'       => ['$first' => '$lines.name'],
            'qty_sold'   => ['$sum' => '$lines.qty'],
            'revenue'    => ['$sum' => '$lines.subtotal'],
            'transaction_count' => ['$sum' => 1],
        ]],
        ['$sort' => ['qty_sold' => -1]],
        ['$limit' => $top],
    ]);
}
```

### 3.4 Cash Session Reconciliation

```php
function reconcileCashSession(string $sessionId): array
{
    $session = collection('cash_sessions')->findOne(['session_id' => $sessionId]);
    if (!$session) throw new \RuntimeException('Session not found');

    // Hitung expected balance dari transaksi cash
    $cashTransactions = collection('transactions')->aggregate([
        ['$match' => [
            'session_id' => $sessionId,
            'status'     => 'completed',
        ]],
        ['$unwind' => '$payments'],
        ['$match' => ['payments.method' => 'cash']],
        ['$group' => [
            '_id'          => null,
            'total_cash'   => ['$sum' => '$payments.amount'],
            'total_change' => ['$sum' => '$change_amount'],
        ]],
    ]);

    $cashIn = $cashTransactions[0]['total_cash'] ?? 0;
    $change = $cashTransactions[0]['total_change'] ?? 0;
    $expected = $session['opening_balance'] + $cashIn - $change
              - ($session['cash_out_amount'] ?? 0) + ($session['cash_in_amount'] ?? 0);

    $difference = ($session['closing_balance_actual'] ?? 0) - $expected;

    return [
        'expected' => $expected,
        'actual'   => $session['closing_balance_actual'] ?? 0,
        'difference' => $difference,
        'cash_in_count' => $cashTransactions[0]['total_cash'] ?? 0,
    ];
}
```

### 3.5 Payment Method Mix

```php
function getPaymentMethodMix(string $outletId, string $fromDate, string $toDate): array
{
    return collection('transactions')->aggregate([
        ['$match' => [
            'outlet_id'        => $outletId,
            'transaction_date' => ['$gte' => $fromDate, '$lte' => $toDate],
            'status'           => 'completed',
        ]],
        ['$unwind' => '$payments'],
        ['$group' => [
            '_id'         => '$payments.method',
            'count'       => ['$sum' => 1],
            'total_amount'=> ['$sum' => '$payments.amount'],
        ]],
        ['$sort' => ['total_amount' => -1]],
    ]);
}
```

---

## 4. Hooks & Events POS

### 4.1 Auto-Decrease Stock saat Transaction Completed

Insert multiple `stock_movements` untuk semua line items — wajib atomic. Kalau satu line gagal, semua rollback, dan transaksi POS di-mark sebagai `error` agar kasir tahu (lihat §8.2 untuk pola lengkap):

```php
collection('transactions')->on('afterInsert', function (array $trx) {
    if ($trx['status'] !== 'completed') return;

    // Buat stock_movement untuk setiap line
    $movements = [];
    foreach ($trx['lines'] as $line) {
        $movements[] = [
            'movement_id'    => 'MV-' . $trx['transaction_id'] . '-' . $line['sku'],
            'movement_date'  => $trx['transaction_date'],
            'product_id'     => $line['sku'],
            'warehouse_id'   => $trx['outlet_id'], // outlet = warehouse untuk POS
            'movement_type'  => 'sales_out',
            'qty'            => -$line['qty'],
            'reference_type' => 'pos_trx',
            'reference_id'   => $trx['transaction_id'],
            'created_by'     => $trx['cashier_id'],
        ];
    }

    // insertMany otomatis transactional — wrap eksplisit agar kalau gagal,
    // transaksi POS di-mark error (kasir harus tahu stok tidak terpotong)
    $conn = collection('stock_movements')->database->connection;
    $conn->beginTransaction();
    try {
        collection('stock_movements')->insertMany($movements);
        $conn->commit();
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        // Transaksi POS sudah ter-insert; mark sebagai error agar kasir bisa re-process / void.
        collection('transactions')->update(
            ['_id' => $trx['_id']],
            ['$set' => ['status' => 'error', 'error_message' => $e->getMessage()]]
        );
        throw $e;
    }
});
```

> **Catatan:** `afterInsert` hook berjalan dalam transaction yang sama dengan operasi trigger-nya bila caller membungkus insert dengan `beginTransaction()`. Pattern di atas self-contained (tetap atomic walau caller lupa wrap). Jika caller juga `beginTransaction()`, jangan double-begin — pilih salah satu pola (lihat §8.2).

### 4.2 Auto-Generate Transaction Number

```php
collection('transactions')->on('beforeInsert', function (array $doc) {
    if (!empty($doc['transaction_no'])) return $doc;

    $date = date('Ymd');
    $count = collection('transactions')->count([
        'outlet_id'        => $doc['outlet_id'],
        'transaction_date' => $doc['transaction_date'],
    ]);
    $doc['transaction_no'] = sprintf('TRX-%s-%06d', $date, $count + 1);
    return $doc;
});
```

> **Catatan:** `beforeInsert` return modified doc = hook berjalan dalam transaction yang sama dengan insert caller. Penetapan `transaction_no` otomatis atomic dengan insert transaksi — tidak perlu `beginTransaction()` eksplisit di hook ini (lihat §8.2).

### 4.3 Auto-Calculate Tax, Service, Rounding

```php
collection('transactions')->on('beforeInsert', function (array $doc) {
    $outlet = collection('outlets')->findOne(['outlet_id' => $doc['outlet_id']]);

    $subtotal = array_sum(array_map(fn($l) => $l['subtotal'], $doc['lines']));

    // Tax & service hanya untuk dine-in (opsional, sesuai policy)
    $taxRate = $outlet['tax_rate'] ?? 0;
    $serviceRate = ($doc['order_type'] ?? 'dine_in') === 'dine_in'
        ? ($outlet['service_rate'] ?? 0) : 0;

    $doc['subtotal']      = $subtotal;
    $doc['tax_total']     = round($subtotal * $taxRate);
    $doc['service_total'] = round($subtotal * $serviceRate);

    $beforeRound = $subtotal - ($doc['discount_total'] ?? 0)
                 + $doc['tax_total'] + $doc['service_total'];

    // Rounding ke 50 rupiah terdekat (Indonesia)
    $rounded = round($beforeRound / 50) * 50;
    $doc['rounding']    = $rounded - $beforeRound;
    $doc['grand_total'] = $rounded;

    return $doc;
});
```

> **Catatan:** Sama seperti §4.2 — `beforeInsert` return modified doc berjalan dalam transaction yang sama dengan insert caller. Field `subtotal`, `tax_total`, `service_total`, `rounding`, `grand_total` di-set atomik dengan insert transaksi. Tidak perlu `beginTransaction()` eksplisit di hook ini (lihat §8.2).

### 4.4 Void Transaction (Reverse Stock Movement)

Void tidak delete transaction, tapi insert reverse stock movement + update transaction status. WAJIB atomic — kalau stok kembali tapi status tidak update (atau sebaliknya), kasir bingung (lihat §8.3 untuk pola lengkap):

```php
function voidTransaction(string $trxId, string $reason): void
{
    $trx = collection('transactions')->findOne(['transaction_id' => $trxId]);
    if (!$trx) throw new \RuntimeException('Transaction not found');
    if ($trx['status'] !== 'completed') {
        throw new \RuntimeException('Only completed transactions can be voided');
    }

    // Insert reverse stock movements + update transaction status — WAJIB atomic
    $conn = collection('stock_movements')->database->connection;
    $conn->beginTransaction();
    try {
        // 1. Insert reverse stock movements (insertMany — bulk, atomic dengan status update)
        $movements = [];
        foreach ($trx['lines'] as $line) {
            $movements[] = [
                'movement_id'    => 'MV-VOID-' . $trxId . '-' . $line['sku'],
                'movement_date'  => date('Y-m-d H:i:s'),
                'product_id'     => $line['sku'],
                'warehouse_id'   => $trx['outlet_id'],
                'movement_type'  => 'return_in', // stok masuk kembali
                'qty'            => $line['qty'],
                'reference_type' => 'pos_void',
                'reference_id'   => $trxId,
                'created_by'     => $_SESSION['user_id'],
                'notes'          => 'Void: ' . $reason,
            ];
        }
        collection('stock_movements')->insertMany($movements);

        // 2. Update status transaksi
        collection('transactions')->update(
            ['_id' => $trx['_id']],
            ['$set' => ['status' => 'voided', 'void_reason' => $reason, 'voided_at' => date('c')]]
        );

        $conn->commit();
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        throw $e;
    }

    // Audit log di luar transaction — jangan block void kalau audit log gagal (lihat §8.5 rule 3)
    collection('transaction_audit_logs')->insert([
        'transaction_id' => $trxId,
        'action'         => 'void',
        'performed_by'   => $_SESSION['user_id'],
        'performed_at'   => date('c'),
        'reason'         => $reason,
    ]);
}
```

---

## 5. Performance & Indexing

### 5.1 Searchable Fields

```php
collection('transactions')->setSearchableFields([
    'transaction_id'  => ['hash' => false],
    'transaction_no'  => ['hash' => false],
    'outlet_id'       => ['hash' => false],
    'cashier_id'      => ['hash' => false],
    'shift_id'        => ['hash' => false],
    'transaction_date'=> ['hash' => false],
    'status'          => ['hash' => false],
    'sync_status'     => ['hash' => false],
])->saveConfiguration();

collection('pos_products')->setSearchableFields([
    'sku'       => ['hash' => false],
    'category'  => ['hash' => false],
    'is_active' => ['hash' => false],
])->saveConfiguration();
```

### 5.2 TTL untuk Transaction Audit Logs

Transaction audit log (siapa void, siapa refund, kapan) bisa besar. Set TTL 1 tahun:

```php
collection('transaction_audit_logs')->setTTL(60 * 60 * 24 * 365);
// Transaksi tetap disimpan, hanya audit log yang expire
```

### 5.3 Cursor untuk Export Transaksi Bulanan

Export untuk tax reporting:

```php
function exportMonthlyTransactions(string $outletId, int $year, int $month): void
{
    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate   = date('Y-m-t', strtotime($startDate));

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="pos-' . $outletId . '-' . $year . $month . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Trx No', 'Date', 'Cashier', 'Subtotal', 'Tax', 'Total', 'Payment Method']);

    $cursor = collection('transactions')
        ->find([
            'outlet_id'        => $outletId,
            'transaction_date' => ['$gte' => $startDate, '$lte' => $endDate],
            'status'           => 'completed',
        ])
        ->sort(['transaction_date' => 1]);

    foreach ($cursor->stream() as $trx) {
        $payMethods = implode('+', array_column($trx['payments'], 'method'));
        fputcsv($out, [
            $trx['transaction_no'], $trx['transaction_date'],
            $trx['cashier_id'], $trx['subtotal'], $trx['tax_total'],
            $trx['grand_total'], $payMethods,
        ]);
    }
    fclose($out);
}
```

### 5.4 Batch Insert untuk Sync dari Offline POS

Saat outlet offline sync ke central, per-transaksi harus atomic (cross-DB: pos_outlet ↔ pos_central). Pakai per-trx loop dengan transaction per database + idempotent flag (`sync_status`). Pola cross-DB lengkap (POS outlet → erp_sales + erp_finance) ada di §8.4:

```php
function syncOutletToCentral(string $outletId): array
{
    $central = Flight::get('bangron.client')->selectDB('pos_central');
    $local   = Flight::get('bangron.client')->selectDB('pos_outlet_' . $outletId);

    // Ambil semua transaksi pending sync
    $pending = $local->collection('transactions')
        ->find(['sync_status' => 'pending'])
        ->limit(500) // batch 500
        ->toArray();

    if (empty($pending)) return ['synced' => 0, 'failed' => 0];

    $synced = 0; $failed = 0;

    // Per-trx sync — tiap trx atomic di sisi central.
    // Idempotent: insert di central pakai _id yang sama, kalau sudah ada skip (SQLite UNIQUE conflict).
    foreach ($pending as $trx) {
        // Transaction 1: insert trx ke central — atomic
        $connCentral = $central->connection;
        try {
            $connCentral->beginTransaction();
            $central->collection('transactions')->insert($trx);
            $connCentral->commit();
        } catch (\Throwable $e) {
            if ($connCentral->inTransaction()) $connCentral->rollBack();
            $failed++;
            // Tandai sync_status = 'failed' agar di-retry di run berikutnya
            $local->collection('transactions')->update(
                ['_id' => $trx['_id']],
                ['$set' => ['sync_status' => 'failed', 'sync_error' => $e->getMessage()]]
            );
            continue;
        }

        // Transaction 2: update sync_status di local — atomic di sisi local
        $connLocal = $local->connection;
        try {
            $connLocal->beginTransaction();
            $local->collection('transactions')->update(
                ['_id' => $trx['_id']],
                ['$set' => ['sync_status' => 'synced', 'synced_at' => date('c')]]
            );
            $connLocal->commit();
        } catch (\Throwable $e) {
            if ($connLocal->inTransaction()) $connLocal->rollBack();
            // Central sudah punya trx — sync_status tetap 'pending',
            // di retry berikutnya insert central akan konflik _id (idempotent) lalu lanjut ke update local.
            $failed++;
            continue;
        }
        $synced++;
    }
    return ['synced' => $synced, 'failed' => $failed];
}
```

> **Catatan:** pos_outlet ↔ pos_central adalah cross-DB — tidak bisa satu transaction. Pakai 2 transaction per trx (saga) + `sync_status` flag sebagai idempotent marker. Lihat §8.4 untuk pola cross-DB POS → erp_sales + erp_finance yang lebih kompleks (saga + compensating action).

---

## 6. Security di POS

### 6.1 Cashier PIN Encryption

PIN kasir tidak boleh plaintext — pakai bcrypt:

```php
function hashCashierPin(string $pin): string
{
    return password_hash($pin, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyCashierPin(string $pin, string $hash): bool
{
    return password_verify($pin, $hash);
}

// Saat create cashier
collection('cashiers')->insert([
    'cashier_id'    => 'CSH-' . uniqid(),
    'outlet_id'     => $outletId,
    'user_id'       => $userId,
    'pin_code_hash' => hashCashierPin($rawPin),
    'is_active'     => true,
]);

// Saat login kasir (di POS UI)
$cashier = collection('cashiers')->findOne(['cashier_id' => $inputId, 'outlet_id' => $outletId, 'is_active' => true]);
if (!$cashier || !verifyCashierPin($inputPin, $cashier['pin_code_hash'])) {
    throw new \RuntimeException('Invalid cashier credentials');
}
```

### 6.2 Refund Authorization

Refund memerlukan otoritas manager — tidak bisa kasir biasa:

```php
function processRefund(string $trxId, float $amount, string $reason, string $managerPin): array
{
    // Verify manager
    $manager = collection('cashiers')->findOne([
        'outlet_id' => $outletId,
        'is_active' => true,
        'role'      => 'manager',
    ]);
    if (!$manager || !verifyCashierPin($managerPin, $manager['pin_code_hash'])) {
        throw new \RuntimeException('Manager authorization required for refund');
    }

    $trx = collection('transactions')->findOne(['transaction_id' => $trxId]);
    if (!$trx) throw new \RuntimeException('Transaction not found');

    if ($amount > $trx['grand_total']) {
        throw new \RuntimeException('Refund amount exceeds transaction total');
    }

    // Create refund record + update transaction status — WAJIB atomic (lihat §8.3)
    $newStatus = $amount >= $trx['grand_total'] ? 'refunded' : 'partial_refund';
    $refundId  = 'REF-' . uniqid();

    $conn = collection('refunds')->database->connection;
    $conn->beginTransaction();
    try {
        collection('refunds')->insert([
            'refund_id'    => $refundId,
            'transaction_id' => $trxId,
            'amount'       => $amount,
            'reason'       => $reason,
            'authorized_by'=> $manager['cashier_id'],
            'refunded_at'  => date('c'),
            'outlet_id'    => $trx['outlet_id'],
        ]);

        collection('transactions')->update(
            ['_id' => $trx['_id']],
            ['$set' => ['status' => $newStatus]]
        );

        $conn->commit();
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        throw $e;
    }

    // Audit log di luar transaction — jangan block refund kalau audit log gagal (lihat §8.5 rule 3)
    collection('transaction_audit_logs')->insert([
        'transaction_id' => $trxId,
        'action'         => 'refund',
        'performed_by'   => $manager['cashier_id'],
        'performed_at'   => date('c'),
        'amount'         => $amount,
        'reason'         => $reason,
    ]);

    return ['refund_id' => $refundId, 'status' => $newStatus];
}
```

### 6.3 Encryption untuk Cost Price

Harga modal produk rahasia — kasir tidak boleh lihat:

```php
// Pisahkan cost data ke collection ter-encrypt
collection('product_costs')->setEncryptionKey($_ENV['POS_COST_KEY']);
collection('product_costs')->setSchema([
    'sku'         => ['type' => 'string', 'required' => true, 'unique' => true],
    'cost'        => ['type' => 'float', 'required' => true],
    'margin_pct'  => ['type' => 'float'],
])->saveConfiguration();

// Saat kasir query products, cost field tidak di-include
$products = collection('pos_products')->find(
    ['is_active' => true],
    ['sku', 'name', 'price', 'category', 'image_url'] // exclude cost
)->toArray();
```

### 6.4 Audit Log untuk Void & Refund

Void dan refund adalah operasi sensitif (potensi fraud):

```php
collection('transactions')->on('afterUpdate', function (array $old, array $new) {
    if (($old['status'] ?? '') === ($new['status'] ?? '')) return;

    collection('transaction_audit_logs')->insert([
        'transaction_id'  => $new['transaction_id'],
        'action'          => 'status_change',
        'from_status'     => $old['status'] ?? null,
        'to_status'       => $new['status'],
        'performed_by'    => $_SESSION['user_id'] ?? 'system',
        'performed_at'    => date('c'),
        'ip_address'      => $_SERVER['REMOTE_ADDR'] ?? '',
        'void_reason'     => $new['void_reason'] ?? null,
    ]);
});
```

---

## 7. Relasi & Cross-Module Populate

### 7.1 Transaction → Outlet + Cashier + Customer

```php
$trx = collection('transactions')
    ->findOne(['transaction_no' => 'TRX-20260710-000123'])
    ->populateMany([
        'outlet_id'  => ['collection' => 'outlets', 'fields' => ['name', 'address', 'tax_rate']],
        'cashier_id' => ['collection' => 'cashiers', 'fields' => ['user_id', 'shift_id']],
        'customer_id'=> ['collection' => 'customers', 'fields' => ['name', 'phone', 'email']],
    ]);
```

### 7.2 Cross-Database: POS Transaction → ERP Sales Order

Setiap akhir hari, POS transactions bisa di-sync ke ERP sebagai sales orders:

```php
function syncPosToErp(string $outletId, string $date): int
{
    $pos = Flight::get('bangron.client')->selectDB('pos_outlet_' . $outletId);
    $erp = Flight::get('bangron.client')->selectDB('erp_sales');

    $transactions = $pos->collection('transactions')->find([
        'outlet_id'        => $outletId,
        'transaction_date' => $date,
        'status'           => 'completed',
        'synced_to_erp'    => false,
    ])->toArray();

    $count = 0;
    foreach ($transactions as $trx) {
        $soNumber = 'SO-' . $date . '-' . substr($trx['transaction_no'], -6);
        $erp->collection('sales_orders')->insert([
            'so_number'   => $soNumber,
            'so_date'     => $trx['transaction_date'],
            'customer_id' => $trx['customer_id'] ?? 'WALK-IN-' . $trx['outlet_id'],
            'sales_rep_id'=> $trx['cashier_id'],
            'status'      => 'fulfilled', // POS langsung delivered
            'lines'       => array_map(fn($l) => [
                'product_id' => $l['sku'],
                'qty'        => $l['qty'],
                'unit_price' => $l['price'],
                'subtotal'   => $l['subtotal'],
            ], $trx['lines']),
            'subtotal'    => $trx['subtotal'],
            'tax_total'   => $trx['tax_total'],
            'grand_total' => $trx['grand_total'],
            'source'      => 'pos_sync',
            'source_ref'  => $trx['transaction_no'],
        ]);

        $pos->collection('transactions')->update(
            ['_id' => $trx['_id']],
            ['$set' => ['synced_to_erp' => true]]
        );
        $count++;
    }
    return $count;
}
```

### 7.3 Cross-Module: POS → ERP Cash Payment Journal

```php
collection('transactions')->on('afterInsert', function (array $trx) {
    if ($trx['status'] !== 'completed') return;

    $erpFinance = Flight::get('bangron.client')->selectDB('erp_finance');
    foreach ($trx['payments'] as $payment) {
        $cashAccount = $payment['method'] === 'cash' ? '1100-10' : '1100-20';
        $erpFinance->collection('journal_entries')->insert([
            'je_number'   => 'JE-POS-' . $trx['transaction_no'],
            'je_date'     => $trx['transaction_date'],
            'description' => 'POS sales ' . $trx['transaction_no'],
            'source_type' => 'pos_sales',
            'source_id'   => $trx['_id'],
            'is_posted'   => true,
            'total_debit' => $payment['amount'],
            'total_credit'=> $payment['amount'],
            'lines' => [
                ['account_code' => $cashAccount,    'debit' => $payment['amount'], 'credit' => 0],
                ['account_code' => '4000-00',       'debit' => 0, 'credit' => $trx['subtotal']],
                ['account_code' => '2300-00',       'debit' => 0, 'credit' => $trx['tax_total']],
            ],
        ]);
    }
});
```

---

## 8. Transaction Safety

POS adalah modul dengan rate write tertinggi dan konsekuensi langsung ke pelanggan — kalau transaksi gagal di tengah, kasir harus tahu dan stok harus konsisten.

### 8.1 Skenario yang WAJIB Pakai Transaction

| Skenario | Langkah Atomic | Konsekuensi Tanpa Transaction |
|----------|----------------|-------------------------------|
| Sale Completed | Insert transaction + insert stock_movements + update cash_session | Transaksi tercatat tapi stok tidak berkurang |
| Void Transaction | Insert reverse stock_movements + update transaction.status + audit log | Stok tidak kembali, tapi transaksi voided |
| Refund Processing | Insert refund record + reverse stock + update transaction.status + insert JE | Refund dicatat tapi stok/JE tidak konsisten |
| Cash Session Close | Update cash_session.status + insert reconciliation record + audit log | Session closed tanpa audit trail |
| Sync Outlet→Central (per trx) | Insert SO di erp_sales + insert JE di erp_finance + update sync_status | SO ada tapi JE tidak ada (cross-DB, pakai 2 transaction + idempotent flag) |
| Bulk Receipt Print | Insert transaction + insert print_log | Transaksi ada tapi print gagal tidak tercatat |
| Multi-Payment Transaction | Insert transaction + insert multiple payment records | Pembayaran parsial — sebagian payment hilang |

### 8.2 Pola Transaction di Hook Sale Completed

```php
collection('transactions')->on('afterInsert', function (array $trx) {
    if ($trx['status'] !== 'completed') return;

    $conn = collection('stock_movements')->database->connection;
    $conn->beginTransaction();
    try {
        // Insert stock movements untuk semua line items — atomic
        $movements = [];
        foreach ($trx['lines'] as $line) {
            $movements[] = [
                'movement_id'    => 'MV-' . $trx['transaction_id'] . '-' . $line['sku'],
                'movement_date'  => $trx['transaction_date'],
                'product_id'     => $line['sku'],
                'warehouse_id'   => $trx['outlet_id'],
                'movement_type'  => 'sales_out',
                'qty'            => -$line['qty'],
                'reference_type' => 'pos_trx',
                'reference_id'   => $trx['transaction_id'],
                'created_by'     => $trx['cashier_id'],
            ];
        }
        collection('stock_movements')->insertMany($movements);

        // Update cash_session transaction_count — atomic dengan stock movements
        collection('cash_sessions')->update(
            ['session_id' => $trx['shift_id']],
            ['$inc' => ['transaction_count' => 1]]
        );

        $conn->commit();
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        // Mark transaction as error — kasir harus tahu transaksi gagal
        collection('transactions')->update(
            ['_id' => $trx['_id']],
            ['$set' => ['status' => 'error', 'error_message' => $e->getMessage()]]
        );
        throw $e;
    }
});
```

### 8.3 Void & Refund dengan Transaction

Void dan refund adalah operasi sensitif — stok harus kembali, JE harus reverse, status harus update. Semua atomic:

```php
function voidTransaction(string $trxId, string $reason): void {
    $trx = collection('transactions')->findOne(['transaction_id' => $trxId]);
    if (!$trx) throw new \RuntimeException('Transaction not found');
    if ($trx['status'] !== 'completed') {
        throw new \RuntimeException('Only completed transactions can be voided');
    }

    $conn = collection('stock_movements')->database->connection;
    $conn->beginTransaction();
    try {
        // 1. Insert reverse stock movements
        $movements = [];
        foreach ($trx['lines'] as $line) {
            $movements[] = [
                'movement_id'    => 'MV-VOID-' . $trxId . '-' . $line['sku'],
                'movement_date'  => date('Y-m-d H:i:s'),
                'product_id'     => $line['sku'],
                'warehouse_id'   => $trx['outlet_id'],
                'movement_type'  => 'return_in',
                'qty'            => $line['qty'], // positif = stok kembali
                'reference_type' => 'pos_void',
                'reference_id'   => $trxId,
                'created_by'     => $_SESSION['user_id'],
                'notes'          => 'Void: ' . $reason,
            ];
        }
        collection('stock_movements')->insertMany($movements);

        // 2. Update transaction status
        collection('transactions')->update(
            ['_id' => $trx['_id']],
            ['$set' => [
                'status'       => 'voided',
                'void_reason'  => $reason,
                'voided_at'    => date('c'),
                'voided_by'    => $_SESSION['user_id'],
            ]]
        );

        // 3. Update cash_session transaction_count (decrement)
        collection('cash_sessions')->update(
            ['session_id' => $trx['shift_id']],
            ['$inc' => ['transaction_count' => -1]]
        );

        $conn->commit();
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        throw $e;
    }

    // Audit log di luar transaction — jangan block void kalau audit log gagal
    collection('transaction_audit_logs')->insert([
        'transaction_id' => $trxId,
        'action'         => 'void',
        'performed_by'   => $_SESSION['user_id'],
        'performed_at'   => date('c'),
        'reason'         => $reason,
    ]);
}
```

### 8.4 Sync Outlet → Central (Cross-Database)

Sync POS outlet ke ERP pusat melibatkan 2 database — tidak bisa satu transaction. Pakai idempotent pattern dengan `sync_status` flag:

```php
function syncOutletToCentral(string $outletId): array {
    $pos = Flight::get('bangron.client')->selectDB('pos_outlet_' . $outletId);
    $erpSales = Flight::get('bangron.client')->selectDB('erp_sales');
    $erpFinance = Flight::get('bangron.client')->selectDB('erp_finance');

    $pending = $pos->collection('transactions')->find([
        'sync_status' => 'pending', 'status' => 'completed'
    ])->limit(500)->toArray();

    $synced = ['so' => 0, 'je' => 0, 'failed' => 0];

    foreach ($pending as $trx) {
        // Transaction 1: Insert SO di erp_sales
        $conn1 = $erpSales->connection;
        try {
            $conn1->beginTransaction();
            $soId = $erpSales->collection('sales_orders')->insert([
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
            $conn1->commit();
        } catch (\Throwable $e) {
            if ($conn1->inTransaction()) $conn1->rollBack();
            $synced['failed']++;
            // Log error, skip to next transaction
            continue;
        }

        // Transaction 2: Insert JE di erp_finance (per payment method)
        $conn2 = $erpFinance->connection;
        try {
            $conn2->beginTransaction();
            foreach ($trx['payments'] as $payment) {
                $erpFinance->collection('journal_entries')->insert([
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
            $conn2->commit();
        } catch (\Throwable $e) {
            if ($conn2->inTransaction()) $conn2->rollBack();
            // COMPENSATING: hapus SO yang sudah dibuat (saga pattern)
            $erpSales->collection('sales_orders')->remove(['_id' => $soId]);
            $synced['failed']++;
            continue;
        }

        // Both transactions sukses — update sync_status di local POS
        $pos->collection('transactions')->update(
            ['_id' => $trx['_id']],
            ['$set' => ['sync_status' => 'synced', 'synced_at' => date('c')]]
        );
        $synced['so']++;
    }
    return $synced;
}
```

### 8.5 Aturan Penting

1. Cek `inTransaction()` sebelum `rollBack()`.
2. Re-throw exception setelah rollback — atau compensating action untuk cross-DB.
3. Side effects (print receipt, send to customer display, notify manager) DI LUAR transaction.
4. Receipt printing TIDAK boleh di dalam transaction — kalau printer hang, transaction lock lama.
5. Cross-database (POS outlet ↔ erp_sales ↔ erp_finance) TIDAK atomic — pakai idempotent flag + saga.
6. `insertMany()` otomatis transactional — pakai untuk stock movements bulk.
7. Jika stock_movement insert gagal di hook afterInsert, transaction POS tetap ada di database tapi stok salah. Mark transaction status sebagai 'error' agar kasir tahu.

Lihat juga: [Auth & ACL → Transaction Safety](/docs/scenarios/auth-acl/#8-transaction-safety-atomic-multi-step-operasi) untuk pola lengkap.

---

## 9. Anti-Pattern POS

1. **Update transaction langsung untuk void** — transaksi harus immutable. Pakai reverse entry (stock_movement `return_in`) + status update.

2. **Simpan PIN kasir plaintext atau MD5** — wajib bcrypt. MD5 bisa di-crack dalam detik.

3. **Tidak handle offline mode** — kalau internet putus saat kasir bayar, transaksi harus tetap bisa. Pakai `sync_status` field, sync ke central belakangan.

4. **Hitung stock secara real-time dengan `SUM(qty)`** — untuk POS high-volume, query ini lambat. Maintain `current_stock` field di `pos_products` dan update via hook.

5. **Tidak rounding ke 50/100 rupiah** — kasir harus hitung manual kembalian dengan sen. Auto-rounding wajib di Indonesia.

6. **Refund tanpa otorisasi manager** — kasir bisa refund ke rekening sendiri. Wajib PIN manager untuk refund.

7. **Tidak pisahkan cost data dari product** — kasir yang query `pos_products` bisa lihat margin. Pakai collection `product_costs` ter-encrypt.

8. **Sync POS → ERP tanpa idempotency** — jika sync di-retry, transaksi dobel di ERP. Pakai field `source_ref` di SO + cek unique sebelum insert.

9. **Tidak log void/refund** — fraud detection susah. Audit log wajib untuk void & refund.

10. **Cash session tidak di-close harian** — selisih kas susah ditelusuri. Wajib close shift dengan reconcile expected vs actual.

---

## Referensi

- [ERP Scenario](/docs/scenarios/erp/) — modul ERP yang jadi tujuan sync POS (sales_order, journal_entry).
- [SCM Scenario](/docs/scenarios/scm/) — modul SCM untuk stock_movement reference.
- [Modular Architecture](/docs/modular-architecture/) — setup multi-database POS + ERP + SCM.
- [Security](/docs/security/) — PIN hashing, encryption cost data, audit log.
