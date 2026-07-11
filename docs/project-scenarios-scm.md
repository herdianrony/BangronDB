---
layout: doc
title: "Project Scenarios: SCM"
description: "Purchase orders, shipments, stock movements."
toc: true
edit_on_github: true
prev:
  url: /project-scenarios-crm/
  title: "Project Scenarios: CRM"
next:
  url: /project-scenarios-hris/
  title: "Project Scenarios: HRIS"
---
# Tips & Trick BangronDB: Skenario Project SCM dengan Flight PHP

> Panduan praktis implementasi BangronDB pada modul SCM (Supply Chain Management) — mencakup purchase orders, goods receipt, shipments, supplier management, demand forecasting, dan inventory movement antar-warehouse. Stack: Flight PHP.

## Daftar Isi

1. [Pendahuluan](#1-pendahuluan)
2. [Schema Design SCM](#2-schema-design-scm)
3. [Query Patterns SCM](#3-query-patterns-scm)
4. [Hooks & Events SCM](#4-hooks--events-scm)
5. [Performance & Indexing](#5-performance--indexing)
6. [Security di SCM](#6-security-di-scm)
7. [Relasi & Cross-Module Populate](#7-relasi--cross-module-populate)
8. [Transaction Safety](#8-transaction-safety)
9. [Anti-Pattern SCM](#9-anti-pattern-scm)

---

## 1. Pendahuluan

SCM fokus pada **arus barang dan informasi** dari supplier → warehouse → customer. Berbeda dari ERP inventory yang statis (current stock), SCM melacak **pergerakan** barang: kapan masuk, dari mana, ke mana, kapan keluar, dalam kondisi apa. Pola datanya event-sourced — setiap pergerakan adalah event yang tidak boleh di-edit (immutable), hanya bisa di-reverse dengan movement lawan.

**Kapan BangronDB cocok untuk SCM:**

- SCM untuk distributor/wholesaler dengan 1-10 warehouse.
- Manufacturer dengan BOM (bill of materials) dan production planning sederhana.
- Logistics provider dengan tracking shipment.

**Kapan tidak cocok:**

- Real-time tracking ribu shipment/detik (butuh streaming engine seperti Kafka).
- Multi-country SCM dengan customs regulation kompleks.

---

## 2. Schema Design SCM

### 2.1 Suppliers & Purchase Orders

```php
collection('suppliers')->setSchema([
    'supplier_id'   => ['type' => 'string', 'required' => true, 'unique' => true,
                         'regex' => '/^SUP-[0-9]{5}$/'],
    'name'          => ['type' => 'string', 'required' => true, 'min' => 2, 'max' => 200],
    'contact_person'=> ['type' => 'string', 'max' => 100],
    'email'         => ['type' => 'string', 'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
    'phone'         => ['type' => 'string', 'regex' => '/^\+?[0-9]{8,15}$/'],
    'payment_terms' => ['type' => 'int', 'min' => 0, 'max' => 365],
    'lead_time_days'=> ['type' => 'int', 'min' => 1, 'max' => 365],
    'rating'        => ['type' => 'int', 'min' => 1, 'max' => 5],
    'is_active'     => ['type' => 'bool'],
    'addresses'     => ['type' => 'array', 'max' => 3],
])->saveConfiguration();

collection('purchase_orders')->setSchema([
    'po_number'     => ['type' => 'string', 'required' => true, 'unique' => true,
                         'regex' => '/^PO-[0-9]{4}-[0-9]{6}$/'],
    'po_date'       => ['type' => 'string', 'required' => true],
    'expected_date' => ['type' => 'string'],
    'supplier_id'   => ['type' => 'string', 'required' => true],
    'warehouse_id'  => ['type' => 'string', 'required' => true],
    'buyer_id'      => ['type' => 'string', 'required' => true],
    'status'        => ['type' => 'string', 'required' => true,
                         'enum' => ['draft', 'submitted', 'approved', 'sent', 'partial_received',
                                    'received', 'cancelled']],
    'lines'         => ['type' => 'array', 'required' => true, 'min' => 1, 'max' => 200],
    'subtotal'      => ['type' => 'float', 'min' => 0],
    'tax_total'     => ['type' => 'float', 'min' => 0],
    'shipping_cost' => ['type' => 'float', 'min' => 0],
    'grand_total'   => ['type' => 'float', 'min' => 0],
    'terms'         => ['type' => 'string', 'max' => 1000],
])->saveConfiguration();
```

### 2.2 Goods Receipt & Shipments

```php
collection('goods_receipts')->setSchema([
    'gr_number'     => ['type' => 'string', 'required' => true, 'unique' => true],
    'gr_date'       => ['type' => 'string', 'required' => true],
    'po_id'         => ['type' => 'string', 'required' => true], // FK ke PO
    'supplier_id'   => ['type' => 'string', 'required' => true],
    'warehouse_id'  => ['type' => 'string', 'required' => true],
    'received_by'   => ['type' => 'string', 'required' => true],
    'delivery_note' => ['type' => 'string', 'max' => 100],
    'lines'         => ['type' => 'array', 'required' => true, 'min' => 1, 'max' => 200],
    'qc_status'     => ['type' => 'string', 'enum' => ['pending', 'passed', 'partial_reject', 'failed']],
    'notes'         => ['type' => 'string', 'max' => 500],
])->saveConfiguration();

collection('shipments')->setSchema([
    'ship_number'      => ['type' => 'string', 'required' => true, 'unique' => true],
    'ship_date'        => ['type' => 'string', 'required' => true],
    'so_id'            => ['type' => 'string'], // FK ke sales_order (bisa null untuk transfer)
    'from_warehouse'   => ['type' => 'string', 'required' => true],
    'to_warehouse'     => ['type' => 'string'], // null = shipment ke customer
    'customer_id'      => ['type' => 'string'],
    'carrier'          => ['type' => 'string'],
    'tracking_number'  => ['type' => 'string'],
    'shipping_cost'    => ['type' => 'float', 'min' => 0],
    'status'           => ['type' => 'string', 'enum' => ['pending', 'packed', 'shipped',
                            'in_transit', 'delivered', 'returned', 'cancelled']],
    'lines'            => ['type' => 'array', 'required' => true, 'min' => 1, 'max' => 200],
    'shipped_at'       => ['type' => 'string'],
    'delivered_at'     => ['type' => 'string'],
])->saveConfiguration();
```

### 2.3 Stock Movements (Immutable Event Log)

```php
collection('stock_movements')->setSchema([
    'movement_id'      => ['type' => 'string', 'required' => true, 'unique' => true],
    'movement_date'    => ['type' => 'string', 'required' => true],
    'product_id'       => ['type' => 'string', 'required' => true],
    'warehouse_id'     => ['type' => 'string', 'required' => true],
    'movement_type'    => ['type' => 'string', 'required' => true,
                            'enum' => ['purchase_in', 'sales_out', 'transfer_in', 'transfer_out',
                                       'adjustment_in', 'adjustment_out', 'return_in', 'return_out',
                                       'production_in', 'production_out', 'damage_out']],
    'qty'              => ['type' => 'int', 'required' => true], // positif=in, negatif=out
    'unit_cost'        => ['type' => 'float', 'min' => 0],
    'reference_type'   => ['type' => 'string', 'enum' => ['po', 'so', 'gr', 'ship', 'transfer', 'adjustment']],
    'reference_id'     => ['type' => 'string'],
    'batch_number'     => ['type' => 'string'],
    'expiry_date'      => ['type' => 'string'],
    'notes'            => ['type' => 'string', 'max' => 500],
    'created_by'       => ['type' => 'string', 'required' => true],
])->saveConfiguration();
```

**Tips schema SCM:**

- `stock_movements` **immutable** — jangan pernah update/delete. Untuk koreksi, insert movement lawan (`adjustment_in` untuk cancel `sales_out`).
- `lines` di PO/GR/Shipment selalu array of objects dengan `product_id`, `qty`, `unit_price`, `subtotal`.
- `batch_number` + `expiry_date` penting untuk FMCG/pharma — wajib di stock_movement.

---

## 3. Query Patterns SCM

### 3.1 Inventory Balance per Warehouse

Hitung stok akhir per produk per warehouse:

```php
function getInventoryBalance(string $warehouseId, ?string $productId = null): array
{
    $match = ['warehouse_id' => $warehouseId];
    if ($productId) $match['product_id'] = $productId;

    return collection('stock_movements')->aggregate([
        ['$match' => $match],
        ['$group' => [
            '_id'        => ['product_id' => '$product_id', 'warehouse_id' => '$warehouse_id'],
            'total_qty'  => ['$sum' => '$qty'],
            'last_movement' => ['$max' => '$movement_date'],
            'movement_count' => ['$sum' => 1],
        ]],
        ['$match' => ['total_qty' => ['$ne' => 0]]], // skip empty stock
        ['$sort' => ['_id.product_id' => 1]],
    ]);
}
```

### 3.2 Reorder Report (Stok di Bawah Reorder Point)

```php
function getReorderReport(string $warehouseId): array
{
    // 1. Hitung stok akhir per produk
    $balances = collection('stock_movements')->aggregate([
        ['$match' => ['warehouse_id' => $warehouseId]],
        ['$group' => ['_id' => '$product_id', 'qty' => ['$sum' => '$qty']]],
    ]);

    // 2. Cari produk yang stoknya di bawah reorder_point
    $reorder = [];
    foreach ($balances as $bal) {
        $product = collection('products')->findOne(['_id' => $bal['_id']]);
        if (!$product) continue;
        if ($bal['qty'] <= ($product['reorder_point'] ?? 0)) {
            $reorder[] = [
                'product_id'     => $product['_id'],
                'sku'            => $product['sku'],
                'name'           => $product['name'],
                'current_qty'    => $bal['qty'],
                'reorder_point'  => $product['reorder_point'],
                'min_stock'      => $product['min_stock'],
                'suggested_qty'  => $product['min_stock'] - $bal['qty'] + 10,
                'supplier_id'    => $product['primary_supplier'] ?? null,
            ];
        }
    }
    return $reorder;
}
```

### 3.3 Supplier Performance (Lead Time & Fill Rate)

```php
function getSupplierPerformance(string $fromDate, string $toDate): array
{
    return collection('purchase_orders')->aggregate([
        ['$match' => [
            'po_date'   => ['$gte' => $fromDate, '$lte' => $toDate],
            'status'    => ['$in' => ['received', 'partial_received']],
        ]],
        ['$group' => [
            '_id'            => '$supplier_id',
            'po_count'       => ['$sum' => 1],
            'total_value'    => ['$sum' => '$grand_total'],
            'on_time_count'  => ['$sum' => [
                '$cond' => [['$lte' => ['$gr_date', '$expected_date']], 1, 0]
            ]],
        ]],
        ['$project' => [
            'supplier_id'  => '$_id',
            'po_count'     => 1,
            'total_value'  => 1,
            'on_time_rate' => ['$multiply' => [
                ['$divide' => ['$on_time_count', '$po_count']], 100
            ]],
        ]],
        ['$sort' => ['on_time_rate' => -1]],
    ]);
}
```

### 3.4 Stock Card (Movement History per Product)

```php
function getStockCard(string $productId, string $warehouseId, string $fromDate, string $toDate): array
{
    $movements = collection('stock_movements')->find(
        [
            'product_id'    => $productId,
            'warehouse_id'  => $warehouseId,
            'movement_date' => ['$gte' => $fromDate, '$lte' => $toDate],
        ],
        [],
        ['sort' => ['movement_date' => 1, 'movement_id' => 1]]
    )->toArray();

    // Hitung running balance
    $running = 0;
    foreach ($movements as &$m) {
        $running += $m['qty'];
        $m['balance'] = $running;
    }
    return $movements;
}
```

---

## 4. Hooks & Events SCM

### 4.1 Auto-Create Stock Movement saat Goods Receipt

Insert multiple stock_movements + update PO status **wajib atomic** — kalau sebagian gagal, GR ada tapi stok tidak update (atau sebaliknya). Bungkus dalam `beginTransaction()`/`commit()`/`rollBack()` (lihat [§8. Transaction Safety](#8-transaction-safety)):

```php
collection('goods_receipts')->on('afterInsert', function (array $gr) {
    $conn = collection('stock_movements')->database->connection;
    $conn->beginTransaction();
    try {
        $movements = [];
        foreach ($gr['lines'] as $line) {
            $movements[] = [
                'movement_id'    => 'MV-' . uniqid(),
                'movement_date'  => $gr['gr_date'],
                'product_id'     => $line['product_id'],
                'warehouse_id'   => $gr['warehouse_id'],
                'movement_type'  => 'purchase_in',
                'qty'            => $line['qty_received'],
                'unit_cost'      => $line['unit_cost'] ?? 0,
                'reference_type' => 'gr',
                'reference_id'   => $gr['_id'],
                'batch_number'   => $line['batch_number'] ?? null,
                'expiry_date'    => $line['expiry_date'] ?? null,
                'created_by'     => $gr['received_by'],
            ];
        }
        // insertMany otomatis atomic, tapi kita bungkus agar update PO status
        // juga rollback kalau movements gagal (atau sebaliknya).
        collection('stock_movements')->insertMany($movements);

        // Update PO status — atomic dengan stock movements di atas
        collection('purchase_orders')->update(
            ['_id' => $gr['po_id']],
            ['$set' => ['status' => 'received']]
        );

        $conn->commit();
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        throw $e;
    }

    // JE ke erp_finance TIDAK dalam transaction yang sama (cross-database)
    // — pakai hook terpisah dengan idempotent flag `je_posted: true` (lihat §8.4 rule 4).
});
```

### 4.2 Auto-Stock-Out saat Shipment Delivered

Insert reverse movements + update shipment status **wajib atomic** — stok keluar tapi shipment status tidak update (atau sebaliknya) akan menyebabkan stok inkonsisten. Bungkus dalam `beginTransaction()`/`commit()`/`rollBack()` (lihat [§8. Transaction Safety](#8-transaction-safety)):

```php
collection('shipments')->on('afterUpdate', function (array $old, array $new) {
    if (($old['status'] ?? '') !== 'delivered' && $new['status'] === 'delivered') {
        $conn = collection('stock_movements')->database->connection;
        $conn->beginTransaction();
        try {
            $movements = [];
            foreach ($new['lines'] as $line) {
                $movements[] = [
                    'movement_id'    => 'MV-' . uniqid(),
                    'movement_date'  => $new['delivered_at'],
                    'product_id'     => $line['product_id'],
                    'warehouse_id'   => $new['from_warehouse'],
                    'movement_type'  => 'sales_out',
                    'qty'            => -$line['qty'],
                    'reference_type' => 'ship',
                    'reference_id'   => $new['_id'],
                    'created_by'     => 'system',
                ];
            }
            collection('stock_movements')->insertMany($movements);
            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            throw $e;
        }
    }
});
```

> **Catatan:** Hook `afterUpdate` berjalan dalam transaction yang sama dengan update shipment bila caller membungkusnya dengan `beginTransaction()`. Untuk self-containment, hook di atas membungkus sendiri `beginTransaction()` agar tetap atomic walau caller lupa wrap. Jangan kedua-duanya — pilih satu pola saja, atau cek `$conn->inTransaction()` sebelum `beginTransaction()` (lihat §8.2).

### 4.3 Stock Transfer Validation (Cek Stok Cukup)

```php
collection('stock_movements')->on('beforeInsert', function (array $doc) {
    if ($doc['movement_type'] === 'transfer_out' || $doc['movement_type'] === 'sales_out') {
        $balance = collection('stock_movements')->aggregate([
            ['$match' => [
                'product_id'   => $doc['product_id'],
                'warehouse_id' => $doc['warehouse_id'],
            ]],
            ['$group' => ['_id' => null, 'total' => ['$sum' => '$qty']]],
        ]);
        $current = $balance[0]['total'] ?? 0;

        if ($current < abs($doc['qty'])) {
            throw new \RuntimeException(sprintf(
                'Insufficient stock: product %s has %d in warehouse %s, but trying to move out %d',
                $doc['product_id'], $current, $doc['warehouse_id'], abs($doc['qty'])
            ));
        }
    }
});
```

### 4.4 Auto-Generate PO saat Reorder Point Tercapai

Hook ini bisa di-trigger dari scheduled job harian:

```php
function autoGenerateReorderPOs(): array
{
    $generated = [];
    $warehouses = ['WH-MAIN', 'WH-JKT', 'WH-SBY'];
    foreach ($warehouses as $wh) {
        $reorderList = getReorderReport($wh);
        // Group by supplier
        $bySupplier = [];
        foreach ($reorderList as $item) {
            if (!$item['supplier_id']) continue;
            $bySupplier[$item['supplier_id']][] = $item;
        }

        foreach ($bySupplier as $supplierId => $items) {
            // 1 PO = 1 transaction. Kalau satu PO gagal, PO lain tetap di-generate.
            $conn = collection('purchase_orders')->database->connection;
            $conn->beginTransaction();
            try {
                $poNumber = 'PO-' . date('Y') . '-' . str_pad(
                    (string) (collection('purchase_orders')->count([]) + 1), 6, '0', STR_PAD_LEFT
                );
                $lines = [];
                $subtotal = 0;
                foreach ($items as $item) {
                    $product = collection('products')->findOne(['_id' => $item['product_id']]);
                    $unitPrice = $product['last_purchase_price'] ?? 0;
                    $lines[] = [
                        'product_id'  => $item['product_id'],
                        'qty'         => $item['suggested_qty'],
                        'unit_price'  => $unitPrice,
                        'subtotal'    => $unitPrice * $item['suggested_qty'],
                    ];
                    $subtotal += $unitPrice * $item['suggested_qty'];
                }
                $poId = collection('purchase_orders')->insert([
                    'po_number'  => $poNumber,
                    'po_date'    => date('Y-m-d'),
                    'supplier_id'=> $supplierId,
                    'warehouse_id' => $wh,
                    'buyer_id'   => 'system',
                    'status'     => 'draft',
                    'lines'      => $lines,
                    'subtotal'   => $subtotal,
                    'tax_total'  => 0,
                    'shipping_cost' => 0,
                    'grand_total'=> $subtotal,
                ]);
                $conn->commit();
                $generated[] = $poNumber;
            } catch (\Throwable $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                // Log & lanjut ke supplier berikutnya — jangan gagalkan seluruh batch.
                error_log('PO auto-generate failed for supplier ' . $supplierId . ': ' . $e->getMessage());
            }
        }
    }
    return $generated;
}
```

---

## 5. Performance & Indexing

### 5.1 Searchable Fields

```php
collection('stock_movements')->setSearchableFields([
    'product_id'    => ['hash' => false],
    'warehouse_id'  => ['hash' => false],
    'movement_type' => ['hash' => false],
    'movement_date' => ['hash' => false],
    'reference_id'  => ['hash' => false],
])->saveConfiguration();

collection('purchase_orders')->setSearchableFields([
    'po_number'   => ['hash' => false],
    'supplier_id' => ['hash' => false],
    'warehouse_id'=> ['hash' => false],
    'status'      => ['hash' => false],
    'po_date'     => ['hash' => false],
])->saveConfiguration();
```

### 5.2 Cursor Streaming untuk Laporan Stock Card Tahunan

```php
function exportAnnualStockCard(string $year): void
{
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="stock-card-' . $year . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Product', 'Warehouse', 'Type', 'Qty', 'Reference', 'Balance']);

    $cursor = collection('stock_movements')
        ->find(['movement_date' => ['$regex' => '^' . $year]])
        ->sort(['product_id' => 1, 'movement_date' => 1]);

    $balances = []; // [product_id][warehouse_id] = running balance
    foreach ($cursor->stream() as $m) {
        $key = $m['product_id'] . '|' . $m['warehouse_id'];
        $balances[$key] = ($balances[$key] ?? 0) + $m['qty'];
        fputcsv($out, [
            $m['movement_date'], $m['product_id'], $m['warehouse_id'],
            $m['movement_type'], $m['qty'], $m['reference_id'],
            $balances[$key],
        ]);
    }
    fclose($out);
}
```

### 5.3 TTL untuk Shipment Tracking Events

Shipment tracking event (perubahan status carrier) bisa sangat banyak. Set TTL 90 hari untuk tracking event lama:

```php
collection('shipment_tracking_events')->setTTL(60 * 60 * 24 * 90); // 90 hari
// Setelah 90 hari, dokumen dengan `created_at` lama akan auto-expire
```

### 5.4 EXPLAIN untuk Query Movement

```php
$plan = collection('stock_movements')->explain([
    'product_id'   => 'SKU-001',
    'warehouse_id' => 'WH-MAIN',
    'movement_date'=> ['$gte' => '2026-01-01'],
]);
// Pastikan strategy = "sql_first" (pakai blind index) bukan "php_fallback"
```

---

## 6. Security di SCM

### 6.1 Cost Price Encryption

Harga beli (`unit_cost` di stock_movements, `last_purchase_price` di products) adalah rahasia bisnis:

```php
// Pisahkan cost data ke collection ter-encrypt
collection('product_costs')->setEncryptionKey($_ENV['SCM_COST_KEY']);
collection('product_costs')->setSchema([
    'product_id'  => ['type' => 'string', 'required' => true, 'unique' => true],
    'unit_cost'   => ['type' => 'float', 'required' => true],
    'landed_cost' => ['type' => 'float'],
    'supplier_id' => ['type' => 'string'],
    'effective_date' => ['type' => 'string'],
])->saveConfiguration();
```

### 6.2 Supplier Document Confidentiality

Kontrak harga dengan supplier wajib encrypt:

```php
collection('supplier_contracts')->setEncryptionKey($_ENV['SCM_CONTRACT_KEY']);
collection('supplier_contracts')->setSchema([
    'contract_id'   => ['type' => 'string', 'required' => true, 'unique' => true],
    'supplier_id'   => ['type' => 'string', 'required' => true],
    'contract_date' => ['type' => 'string', 'required' => true],
    'valid_until'   => ['type' => 'string', 'required' => true],
    'price_list'    => ['type' => 'array'], // encrypted
    'discount_tier' => ['type' => 'array'],
    'payment_terms' => ['type' => 'int'],
])->saveConfiguration();
```

### 6.3 RBAC per Warehouse

Operator warehouse hanya boleh akses data warehouse-nya:

```php
function getMyWarehouse(): string
{
    $userId = $_SESSION['user_id'];
    $user = collection('users')->findOne(['_id' => $userId]);
    if (!$user) throw new \RuntimeException('User not found');

    if (in_array($user['role'], ['scm_manager', 'admin'], true)) {
        return $_GET['warehouse_id'] ?? 'WH-MAIN'; // manager bebas pilih
    }
    return $user['assigned_warehouse'] ?? throw new \RuntimeException('No warehouse assigned');
}

// Pakai di semua query stock
$warehouseId = getMyWarehouse();
$stocks = collection('stock_movements')->find(['warehouse_id' => $warehouseId])->toArray();
```

### 6.4 Audit Log untuk Stock Adjustment

Stock adjustment (koreksi stok manual) adalah operasi sensitif — wajib audit:

```php
collection('stock_movements')->on('beforeInsert', function (array $doc) {
    if (in_array($doc['movement_type'], ['adjustment_in', 'adjustment_out'], true)) {
        collection('adjustment_audit_log')->insert([
            'movement_id'    => $doc['movement_id'],
            'product_id'     => $doc['product_id'],
            'warehouse_id'   => $doc['warehouse_id'],
            'qty'            => $doc['qty'],
            'reason'         => $doc['notes'] ?? '',
            'adjusted_by'    => $_SESSION['user_id'],
            'adjusted_at'    => date('c'),
            'ip_address'     => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    }
});
```

---

## 7. Relasi & Cross-Module Populate

### 7.1 PO → Supplier → Products

```php
$po = collection('purchase_orders')
    ->findOne(['po_number' => 'PO-2026-000001'])
    ->populate('supplier_id', 'suppliers', ['name', 'email', 'phone', 'lead_time_days']);
// Lines juga bisa di-populate produk-nya:
foreach ($po['lines'] as &$line) {
    $line['product'] = collection('products')->findOne(['_id' => $line['product_id']]);
}
```

### 7.2 Cross-Database: SCM Goods Receipt → ERP Journal Entry

Goods receipt menambah stok (SCM) dan menambah hutang (ERP finance):

```php
collection('goods_receipts')->on('afterInsert', function (array $gr) {
    // 1. Stock movement (di database SCM)
    // (sudah ada di hook 4.1)

    // 2. Journal entry (di database ERP finance)
    $erpFinance = Flight::get('bangron.client')->selectDB('erp_finance');
    $po = collection('purchase_orders')->findOne(['_id' => $gr['po_id']]);

    $totalValue = array_sum(array_map(
        fn($l) => $l['qty_received'] * ($l['unit_cost'] ?? 0),
        $gr['lines']
    ));

    $erpFinance->collection('journal_entries')->insert([
        'je_number'    => 'JE-' . date('Y') . '-' . uniqid(),
        'je_date'      => $gr['gr_date'],
        'description'  => 'Auto-JE for GR ' . $gr['gr_number'],
        'source_type'  => 'purchase',
        'source_id'    => $gr['_id'],
        'is_posted'    => true,
        'total_debit'  => $totalValue,
        'total_credit' => $totalValue,
        'lines' => [
            ['account_code' => '1200-00', 'debit' => $totalValue, 'credit' => 0], // Inventory
            ['account_code' => '2100-00', 'debit' => 0, 'credit' => $totalValue], // AP
        ],
    ]);
});
```

### 7.3 Cross-Database: SCM Shipment → ERP Sales Order

```php
function getShipmentWithSO(string $shipId): array
{
    $scm = Flight::get('bangron.client')->selectDB('scm');
    $erp = Flight::get('bangron.client')->selectDB('erp_sales');

    $ship = $scm->collection('shipments')->findOne(['_id' => $shipId]);
    if (!empty($ship['so_id'])) {
        $so = $erp->collection('sales_orders')->findOne(['_id' => $ship['so_id']]);
        $ship['sales_order'] = [
            'so_number'  => $so['so_number'] ?? null,
            'so_date'    => $so['so_date'] ?? null,
            'customer_id'=> $so['customer_id'] ?? null,
        ];
    }
    return $ship;
}
```

---

## 8. Transaction Safety

SCM adalah modul dengan operasi multi-step paling kritis — stok harus konsisten di semua warehouse. Tanpa transaction, kegagalan sebagian operasi bisa menyebabkan stok hilang/lebih, PO tidak update, atau JE tidak terkait.

### 8.1 Skenario yang WAJIB Pakai Transaction

| Skenario | Langkah Atomic | Konsekuensi Tanpa Transaction |
|----------|----------------|-------------------------------|
| Goods Receipt | Insert GR + multiple stock_movements + update PO status + insert JE (cross-DB) | GR ada tapi stok tidak update, atau JE tidak ada |
| Shipment Delivered | Insert reverse stock_movements + update shipment status + insert revenue JE | Stok keluar tapi shipment status salah |
| Stock Transfer | Out movement WH-A + In movement WH-B | Stok hilang (out tanpa in) atau stok lebih (in tanpa out) |
| Stock Adjustment | Insert adjustment movement + insert audit log | Adjustment tanpa audit trail |
| PO Auto-Generate (multiple POs) | Per PO: insert PO + update reorder flag | Sebagian PO insert, sebagian tidak |
| Return Goods | Insert return_in movement + update shipment status + reverse revenue JE | Stok masuk tapi shipment masih delivered |
| Bulk Stock Movement Import | insertMany movements + audit log | Sebagian insert, sebagian gagal |

### 8.2 Pola Transaction di Hook Goods Receipt

```php
collection('goods_receipts')->on('afterInsert', function (array $gr) {
    $conn = collection('stock_movements')->database->connection;
    $conn->beginTransaction();
    try {
        // Insert multiple stock movements — atomic
        $movements = [];
        foreach ($gr['lines'] as $line) {
            $movements[] = [
                'movement_id'    => 'MV-' . uniqid(),
                'movement_date'  => $gr['gr_date'],
                'product_id'     => $line['product_id'],
                'warehouse_id'   => $gr['warehouse_id'],
                'movement_type'  => 'purchase_in',
                'qty'            => $line['qty_received'],
                'unit_cost'      => $line['unit_cost'] ?? 0,
                'reference_type' => 'gr',
                'reference_id'   => $gr['_id'],
                'created_by'     => $gr['received_by'],
            ];
        }
        collection('stock_movements')->insertMany($movements);

        // Update PO status — atomic dengan stock movements
        collection('purchase_orders')->update(
            ['_id' => $gr['po_id']],
            ['$set' => ['status' => 'received']]
        );

        $conn->commit();
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        throw $e;
    }

    // JE ke erp_finance TIDAK dalam transaction yang sama (cross-database)
    // — pakai idempotent pattern di hook terpisah
});
```

### 8.3 Stock Transfer Manual (Pattern Penting)

Transfer antar warehouse WAJIB atomic — kalau out tanpa in, stok hilang:

```php
function transferStock(string $productId, string $fromWh, string $toWh, int $qty, string $refId): void {
    $conn = collection('stock_movements')->database->connection;
    $conn->beginTransaction();
    try {
        // Out from source
        collection('stock_movements')->insert([
            'movement_id'    => 'MV-' . uniqid(),
            'movement_date'  => date('Y-m-d'),
            'product_id'     => $productId,
            'warehouse_id'   => $fromWh,
            'movement_type'  => 'transfer_out',
            'qty'            => -$qty,
            'reference_type' => 'transfer',
            'reference_id'   => $refId,
            'created_by'     => $_SESSION['user_id'] ?? 'system',
        ]);

        // In to destination — atomic dengan out di atas
        collection('stock_movements')->insert([
            'movement_id'    => 'MV-' . uniqid(),
            'movement_date'  => date('Y-m-d'),
            'product_id'     => $productId,
            'warehouse_id'   => $toWh,
            'movement_type'  => 'transfer_in',
            'qty'            => $qty,
            'reference_type' => 'transfer',
            'reference_id'   => $refId,
            'created_by'     => $_SESSION['user_id'] ?? 'system',
        ]);

        $conn->commit();
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        throw $e;
    }
}
```

### 8.4 Aturan Penting

1. Cek `inTransaction()` sebelum `rollBack()`.
2. Re-throw exception setelah rollback.
3. Side effects (kirim notifikasi ke supplier, update dashboard real-time) DI LUAR transaction.
4. Cross-database (scm ↔ erp_finance untuk JE) TIDAK atomic — pakai idempotent hook dengan flag `je_posted: true` di GR, dan reconciliation job untuk cek konsistensi.
5. `stock_movements` harus immutable — untuk koreksi, insert movement lawan dalam transaction baru, jangan update/delete movement existing.
6. `insertMany()` otomatis transactional — pakai untuk bulk insert master data atau batch movements.

Lihat juga: [Auth & ACL → Transaction Safety](project-scenarios-auth-acl.md#8-transaction-safety-atomic-multi-step-operasi) untuk pola lengkap.

---

## 9. Anti-Pattern SCM

1. **Update stock_movement langsung** — ini melanggar prinsip immutable event log. Pakai adjustment movement lawan.

2. **Simpan current_stock sebagai field di products** — race condition antara 2 transaksi. Selalu hitung dari `SUM(qty)` di stock_movements, atau maintain dengan hook + locking.

3. **Tidak validasi stok cukup sebelum sales_out** — akan menyebabkan stok negatif. Pakai hook `beforeInsert` (lihat 4.3).

4. **Konversi PO → GR tanpa reference** — susah trace. Selalu set `po_id` di GR dan `reference_id` di stock_movements.

5. **Tidak pisahkan cost data** — harga beli di `products` collection = bocor ke semua user. Pakai collection `product_costs` ter-encrypt.

6. **Tracking event disimpan selamanya** — shipment tracking dari carrier bisa ribuan event per shipment. Pakai TTL.

7. **Tidak handle partial receipt** — GR bisa partial (barang datang bertahap). Selalu update PO status ke `partial_received` jika qty received < qty ordered.

---

## Referensi

- [ERP Scenario](project-scenarios-erp.md) — ERP modul yang sering jadi tujuan integrasi SCM (sales_order, journal_entry).
- [Modular Architecture](modular-architecture.md) — setup multi-database SCM + ERP + lainnya.
- [Hook Patterns](hook-patterns.md) — pola hook lanjutan.
- [Security](security.md) — encryption cost data, audit log.
