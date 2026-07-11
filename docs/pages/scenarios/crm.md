---
layout: doc
category: scenarios
permalink: /docs/scenarios/crm/
title: "Project Scenarios: CRM"
description: "Leads, opportunities, sales pipeline."
toc: true
edit_on_github: true
prev:
  url: /docs/scenarios/erp/
  title: "Project Scenarios: ERP"
next:
  url: /docs/scenarios/scm/
  title: "Project Scenarios: SCM"
---
# Tips & Trick BangronDB: Skenario Project CRM dengan Flight PHP

> Dokumen ini berisi panduan praktis memakai BangronDB pada project CRM (Customer Relationship Management) menggunakan framework **Flight PHP**. Fokus pada pengelolaan leads, opportunities, activities, sales pipeline, dan multi-channel communication. Ditujukan untuk PHP developer.

## Daftar Isi

1. [Pendahuluan](#1-pendahuluan)
2. [Schema Design CRM](#2-schema-design-crm)
3. [Query Patterns CRM](#3-query-patterns-crm)
4. [Hooks & Events CRM](#4-hooks--events-crm)
5. [Performance & Indexing](#5-performance--indexing)
6. [Security di CRM](#6-security-di-crm)
7. [Relasi & Cross-Module Populate](#7-relasi--cross-module-populate)
9. [Transaction Safety](#9-transaction-safety)
10. [Anti-Pattern CRM](#10-anti-pattern-crm)

---

## 1. Pendahuluan

CRM berbeda dari ERP: fokusnya pada **akuisisi dan retensi pelanggan**, bukan transaksi keuangan. Pola datanya lebih dinamis — leads bisa berubah jadi opportunities, opportunities bisa menang/kalah, setiap interaksi (call, email, meeting) dicatat sebagai activity. Volume data besar di collection `activities` (bisa jutaan baris per tahun), sedangkan master data (`leads`, `contacts`) relatif kecil.

**Kapan BangronDB cocok untuk CRM:**

- CRM tim sales kecil-menengah (5-50 sales rep, <100k leads).
- CRM vertikal (real estate, asuransi, B2B) yang di-deploy per-customer.
- Modul CRM yang embed ke aplikasi existing (mis. CRM di dalam ERP).

**Kapan tidak cocok:**

- CRM enterprise dengan real-time collaboration (pertimbangkan PostgreSQL + WebSocket).
- CRM dengan integrasi marketing automation volume tinggi (jutaan event/hari).

---

## 2. Schema Design CRM

### 2.1 Leads & Contacts

```php
collection('leads')->setSchema([
    'lead_id'       => ['type' => 'string', 'required' => true, 'unique' => true,
                         'regex' => '/^LD-[0-9]{6}$/'],
    'first_name'    => ['type' => 'string', 'required' => true, 'min' => 1, 'max' => 100],
    'last_name'     => ['type' => 'string', 'max' => 100],
    'email'         => ['type' => 'string', 'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
    'phone'         => ['type' => 'string', 'regex' => '/^\+?[0-9]{8,15}$/'],
    'company'       => ['type' => 'string', 'max' => 200],
    'job_title'     => ['type' => 'string', 'max' => 100],
    'source'        => ['type' => 'string', 'enum' => ['website', 'referral', 'cold_call',
                         'social_media', 'event', 'advertisement', 'other']],
    'status'        => ['type' => 'string', 'enum' => ['new', 'contacted', 'qualified',
                         'unqualified', 'converted'], 'required' => true],
    'assigned_to'   => ['type' => 'string', 'required' => true], // sales rep user_id
    'lead_score'    => ['type' => 'int', 'min' => 0, 'max' => 100],
    'tags'          => ['type' => 'array', 'max' => 20],
    'converted_to'  => ['type' => 'string'], // opportunity_id atau customer_id
    'created_at'    => ['type' => 'string', 'required' => true],
    'last_contact'  => ['type' => 'string'],
])->saveConfiguration();

collection('contacts')->setSchema([
    'contact_id'    => ['type' => 'string', 'required' => true, 'unique' => true],
    'customer_id'   => ['type' => 'string', 'required' => true], // FK ke customers
    'first_name'    => ['type' => 'string', 'required' => true],
    'last_name'     => ['type' => 'string'],
    'email'         => ['type' => 'string', 'unique' => true],
    'phone'         => ['type' => 'string'],
    'job_title'     => ['type' => 'string'],
    'department'    => ['type' => 'string'],
    'is_primary'    => ['type' => 'bool'],
    'is_decision_maker' => ['type' => 'bool'],
])->saveConfiguration();
```

### 2.2 Opportunities (Sales Pipeline)

```php
collection('opportunities')->setSchema([
    'opp_id'        => ['type' => 'string', 'required' => true, 'unique' => true,
                         'regex' => '/^OPP-[0-9]{6}$/'],
    'opp_name'      => ['type' => 'string', 'required' => true, 'max' => 200],
    'customer_id'   => ['type' => 'string', 'required' => true],
    'contact_id'    => ['type' => 'string'],
    'amount'        => ['type' => 'float', 'required' => true, 'min' => 0],
    'currency'      => ['type' => 'string', 'enum' => ['IDR', 'USD', 'SGD', 'EUR'], 'required' => true],
    'stage'         => ['type' => 'string', 'required' => true,
                         'enum' => ['prospecting', 'qualification', 'needs_analysis',
                                    'proposal', 'negotiation', 'closed_won', 'closed_lost']],
    'probability'   => ['type' => 'int', 'min' => 0, 'max' => 100], // win probability %
    'expected_close' => ['type' => 'string'], // ISO date
    'assigned_to'   => ['type' => 'string', 'required' => true],
    'lead_source'   => ['type' => 'string'],
    'loss_reason'   => ['type' => 'string', 'max' => 500],
    'competitors'   => ['type' => 'array', 'max' => 10],
    'created_at'    => ['type' => 'string', 'required' => true],
    'closed_at'     => ['type' => 'string'],
])->saveConfiguration();
```

### 2.3 Activities (Multi-channel Log)

```php
collection('activities')->setSchema([
    'activity_id'   => ['type' => 'string', 'required' => true, 'unique' => true],
    'activity_type' => ['type' => 'string', 'required' => true,
                         'enum' => ['call', 'email', 'meeting', 'task', 'note',
                                    'whatsapp', 'visit', 'demo']],
    'subject'       => ['type' => 'string', 'required' => true, 'max' => 200],
    'description'   => ['type' => 'string', 'max' => 5000],
    'related_to'    => ['type' => 'string', 'required' => true], // lead_id / opp_id / customer_id
    'related_type'  => ['type' => 'string', 'enum' => ['lead', 'opportunity', 'customer', 'contact']],
    'direction'     => ['type' => 'string', 'enum' => ['inbound', 'outbound']],
    'channel'       => ['type' => 'string', 'enum' => ['phone', 'email', 'whatsapp',
                         'in_person', 'video_call', 'social']],
    'duration_minutes' => ['type' => 'int', 'min' => 0],
    'outcome'       => ['type' => 'string', 'enum' => ['successful', 'no_answer', 'busy',
                         'left_voicemail', 'rescheduled', 'follow_up_needed']],
    'scheduled_at'  => ['type' => 'string'],
    'completed_at'  => ['type' => 'string'],
    'assigned_to'   => ['type' => 'string', 'required' => true],
    'attachments'   => ['type' => 'array', 'max' => 10],
])->saveConfiguration();
```

**Tips schema CRM:**

- `tags` array di leads — fleksibel untuk kategorisasi ad-hoc tanpa schema change.
- `lead_score` 0-100 — dipakai untuk prioritization sales rep.
- `probability` di opportunity — harus konsisten dengan `stage` (mis. `negotiation` ≥ 75%).
- `activities` selalu punya `related_to` + `related_type` — polimorfik, tapi tetap ter-index.

---

## 3. Query Patterns CRM

### 3.1 Sales Pipeline Funnel (Per Stage)

```php
function getSalesFunnel(string $month, ?string $salesRepId = null): array
{
    $match = ['created_at' => ['$regex' => '^' . $month]];
    if ($salesRepId) $match['assigned_to'] = $salesRepId;

    return collection('opportunities')->aggregate([
        ['$match' => $match],
        ['$group' => [
            '_id'         => '$stage',
            'count'       => ['$sum' => 1],
            'total_value' => ['$sum' => '$amount'],
            'avg_value'   => ['$avg' => '$amount'],
        ]],
        ['$sort' => ['_id' => 1]],
    ]);
}
// Output: [['_id' => 'prospecting', 'count' => 50, 'total_value' => 500000000, ...], ...]
```

### 3.2 Lead Conversion Rate

```php
function getLeadConversionRate(string $fromDate, string $toDate): array
{
    return collection('leads')->aggregate([
        ['$match' => ['created_at' => ['$gte' => $fromDate, '$lte' => $toDate]]],
        ['$group' => [
            '_id'     => '$source',
            'total'   => ['$sum' => 1],
            'won'     => ['$sum' => ['$cond' => [['$eq' => ['$status', 'converted']], 1, 0]]],
            'lost'    => ['$sum' => ['$cond' => [['$eq' => ['$status', 'unqualified']], 1, 0]]],
        ]],
        ['$project' => [
            'source'          => '$_id',
            'total'           => 1,
            'won'             => 1,
            'lost'            => 1,
            'conversion_rate' => ['$multiply' => [['$divide' => ['$won', '$total']], 100]],
        ]],
        ['$sort' => ['conversion_rate' => -1]],
    ]);
}
```

### 3.3 Activity Follow-up Reminder

Cari activity yang scheduled tapi belum completed dan sudah lewat waktu:

```php
function getOverdueActivities(string $salesRepId): array
{
    $now = date('c');
    return collection('activities')->find(
        [
            'assigned_to'   => $salesRepId,
            'completed_at'  => null,
            'scheduled_at'  => ['$lte' => $now],
        ],
        [],
        ['sort' => ['scheduled_at' => 1], 'limit' => 20]
    )->toArray();
}
```

### 3.4 Customer 360° View (Aggregated)

Gabungkan semua interaksi dengan 1 customer:

```php
function getCustomer360(string $customerId): array
{
    $db = db();
    return [
        'customer'    => $db->collection('customers')->findOne(['_id' => $customerId]),
        'contacts'    => $db->collection('contacts')->find(['customer_id' => $customerId])->toArray(),
        'opportunities' => $db->collection('opportunities')
            ->find(['customer_id' => $customerId], [], ['sort' => ['created_at' => -1]])
            ->toArray(),
        'activities'  => $db->collection('activities')
            ->find(['related_to' => $customerId], [], ['sort' => ['completed_at' => -1], 'limit' => 50])
            ->toArray(),
        'stats' => [
            'total_opp_value' => array_sum(array_column(
                $db->collection('opportunities')
                    ->find(['customer_id' => $customerId, 'stage' => ['$ne' => 'closed_lost']])
                    ->toArray(),
                'amount'
            )),
            'last_activity' => $db->collection('activities')->findOne(
                ['related_to' => $customerId],
                [],
                ['sort' => ['completed_at' => -1]]
            )['completed_at'] ?? null,
        ],
    ];
}
```

---

## 4. Hooks & Events CRM

### 4.1 Auto-Convert Lead → Customer + Opportunity

Saat lead status berubah ke `converted`, otomatis buat customer dan opportunity:

```php
collection('leads')->on('afterUpdate', function (array $old, array $new) {
    if (($old['status'] ?? '') !== 'converted' && $new['status'] === 'converted') {
        // Cek apakah sudah pernah convert (idempotent) — hindari double-convert
        if (!empty($new['converted_to'])) return;

        // Customer ada di database erp_core, lead ada di database crm → cross-database.
        // Tidak bisa satu transaction. Pakai 2 transaction terpisah + compensating action (saga).
        // Lihat §9.2 untuk pola lengkap.
        $erpCore = Flight::get('bangron.client')->selectDB('erp_core');

        // Transaction 1: Insert customer di erp_core
        $conn1 = $erpCore->connection;
        $conn1->beginTransaction();
        try {
            $customerId = $erpCore->collection('customers')->insert([
                'code'      => 'CUST-' . str_pad(
                    (string) ($erpCore->collection('customers')->count([]) + 1), 5, '0', STR_PAD_LEFT
                ),
                'name'      => $new['company'] ?? trim($new['first_name'] . ' ' . $new['last_name']),
                'email'     => $new['email'],
                'phone'     => $new['phone'],
                'source'    => 'crm_conversion',
                'is_active' => true,
            ]);
            $conn1->commit();
        } catch (\Throwable $e) {
            if ($conn1->inTransaction()) $conn1->rollBack();
            throw $e;
        }

        // Transaction 2: Insert opportunity + update lead di crm
        $conn2 = collection('opportunities')->database->connection;
        $conn2->beginTransaction();
        try {
            $oppId = 'OPP-' . str_pad(
                (string) (collection('opportunities')->count([]) + 1), 6, '0', STR_PAD_LEFT
            );
            collection('opportunities')->insert([
                'opp_id'      => $oppId,
                'opp_name'    => 'New business from ' . ($new['company'] ?? $new['first_name']),
                'customer_id' => $customerId,
                'amount'      => 0,
                'currency'    => 'IDR',
                'stage'       => 'prospecting',
                'probability' => 10,
                'assigned_to' => $new['assigned_to'],
                'lead_source' => $new['source'],
                'created_at'  => date('c'),
            ]);

            // Update lead dengan reference konversi — atomic dengan insert opportunity di atas
            collection('leads')->update(
                ['_id' => $new['_id']],
                ['$set' => ['converted_to' => $customerId]]
            );
            $conn2->commit();
        } catch (\Throwable $e) {
            if ($conn2->inTransaction()) $conn2->rollBack();
            // COMPENSATING ACTION: hapus customer yang baru dibuat di erp_core (saga pattern).
            // Karena transaction 2 gagal, customer jadi yatim — rollback manual.
            $erpCore->collection('customers')->remove(['_id' => $customerId]);
            throw $e;
        }
    }
});
```

### 4.2 Auto-Update Lead Score Berdasarkan Activity

```php
collection('activities')->on('afterInsert', function (array $activity) {
    if ($activity['related_type'] !== 'lead') return;

    $lead = collection('leads')->findOne(['_id' => $activity['related_to']]);
    if (!$lead) return;

    // Skor bertambah berdasarkan jenis activity
    $scoreMap = [
        'call'      => 5,
        'email'     => 3,
        'meeting'   => 15,
        'demo'      => 20,
        'whatsapp'  => 2,
        'visit'     => 25,
    ];
    $addition = $scoreMap[$activity['activity_type']] ?? 0;
    $newScore = min(100, ($lead['lead_score'] ?? 0) + $addition);

    collection('leads')->update(
        ['_id' => $lead['_id']],
        ['$set' => ['lead_score' => $newScore, 'last_contact' => $activity['completed_at'] ?? date('c')]]
    );
});
```

> **Catatan:** Hook `afterInsert` ini berjalan dalam transaction yang sama dengan insert activity — update `lead_score` + `last_contact` otomatis atomic. Kalau update gagal, activity juga rollback. Tidak perlu `beginTransaction()` eksplisit. Lihat [§9. Transaction Safety](#9-transaction-safety).

### 4.3 Auto-Update Stage Probability

Sinkronisasi `probability` dengan `stage` agar konsisten:

```php
collection('opportunities')->on('beforeUpdate', function (array $criteria, array $data) {
    // Jika stage diubah, set probability sesuai mapping
    $stageProb = [
        'prospecting'   => 10,
        'qualification' => 25,
        'needs_analysis'=> 40,
        'proposal'      => 60,
        'negotiation'   => 75,
        'closed_won'    => 100,
        'closed_lost'   => 0,
    ];
    if (isset($data['$set']['stage']) && !isset($data['$set']['probability'])) {
        $stage = $data['$set']['stage'];
        if (isset($stageProb[$stage])) {
            $data['$set']['probability'] = $stageProb[$stage];
        }
        if (in_array($stage, ['closed_won', 'closed_lost'], true)) {
            $data['$set']['closed_at'] = date('c');
        }
        return $data; // return modified data
    }
});
```

> **Catatan:** Karena hook ini `beforeUpdate`, field `probability` + `closed_at` di-set dalam payload update yang sama dengan `stage` — semua berkomitmen dalam satu operasi update (atomic secara alami). Tidak perlu `beginTransaction()` eksplisit. Lihat [§9. Transaction Safety](#9-transaction-safety).

---

## 5. Performance & Indexing

### 5.1 Searchable Fields

```php
collection('leads')->setSearchableFields([
    'lead_id'     => ['hash' => false],
    'email'       => ['hash' => true],   // PII, blind index
    'phone'       => ['hash' => true],
    'status'      => ['hash' => false],
    'assigned_to' => ['hash' => false],
    'source'      => ['hash' => false],
])->saveConfiguration();

collection('opportunities')->setSearchableFields([
    'opp_id'      => ['hash' => false],
    'customer_id' => ['hash' => false],
    'stage'       => ['hash' => false],
    'assigned_to' => ['hash' => false],
    'expected_close' => ['hash' => false],
])->saveConfiguration();

// activities = high-volume, wajib searchable
collection('activities')->setSearchableFields([
    'related_to'   => ['hash' => false],
    'related_type' => ['hash' => false],
    'assigned_to'  => ['hash' => false],
    'activity_type'=> ['hash' => false],
    'scheduled_at' => ['hash' => false],
])->saveConfiguration();
```

### 5.2 Cursor untuk Activity Feed Dashboard

Activity feed sales rep bisa ribuan baris per bulan:

```php
function getActivityFeed(string $salesRepId, int $limit = 100): \Generator
{
    $cursor = collection('activities')
        ->find(['assigned_to' => $salesRepId], [], ['sort' => ['completed_at' => -1]])
        ->limit($limit);

    foreach ($cursor->stream() as $activity) {
        // Populate nama lead/opportunity/customer
        if ($activity['related_type'] === 'lead') {
            $related = collection('leads')->findOne(['_id' => $activity['related_to']]);
        } elseif ($activity['related_type'] === 'opportunity') {
            $related = collection('opportunities')->findOne(['_id' => $activity['related_to']]);
        } else {
            $related = collection('customers')->findOne(['_id' => $activity['related_to']]);
        }
        $activity['related_name'] = $related['first_name'] ?? $related['opp_name'] ?? $related['name'] ?? 'Unknown';
        yield $activity;
    }
}
```

### 5.3 Aggregation Bulanan dengan Caching

Laporan pipeline bulanan tidak perlu di-query real-time. Cache di collection `report_cache`:

```php
function getCachedPipelineReport(string $month): array
{
    $cache = collection('report_cache')->findOne(['report_key' => 'pipeline_' . $month]);
    if ($cache && strtotime($cache['cached_at']) > time() - 3600) {
        return $cache['data']; // cache 1 jam
    }

    $data = getSalesFunnel($month);
    collection('report_cache')->update(
        ['report_key' => 'pipeline_' . $month],
        ['$set' => ['data' => $data, 'cached_at' => date('c')]],
        true // upsert
    );
    return $data;
}
```

---

## 6. Security di CRM

### 6.1 PII Encryption (Email, Phone, alamat)

CRM menyimpan data pribadi calon customer — wajib encrypt:

```php
collection('leads')->setEncryptionKey($_ENV['CRM_PII_KEY']);
collection('contacts')->setEncryptionKey($_ENV['CRM_PII_KEY']);
// Setelah ini, field sensitif (email, phone, addresses) otomatis di-encrypt saat storage
// Searchable fields dengan hash=true tetap bisa di-query (blind index)
```

### 6.2 Sales Rep Data Isolation

Sales rep hanya boleh lihat lead/opportunity yang assigned ke dirinya:

```php
function getMyLeads(): array
{
    $userId = $_SESSION['user_id'];
    $role   = $_SESSION['role'];

    $criteria = [];
    if ($role !== 'sales_manager' && $role !== 'admin') {
        $criteria['assigned_to'] = $userId;
    }

    return collection('leads')->find($criteria, [], [
        'sort' => ['lead_score' => -1, 'created_at' => -1], 'limit' => 100
    ])->toArray();
}
```

### 6.3 GDPR / UU PDP Compliance

- **Right to erasure**: implementasi soft delete dulu (recoverable), lalu force delete setelah grace period:

```php
collection('leads')->on('beforeRemove', function (array $criteria) {
    // Log dulu siapa yang request delete
    collection('gdpr_delete_requests')->insert([
        'collection' => 'leads',
        'criteria'   => $criteria,
        'requested_by' => $_SESSION['user_id'],
        'requested_at' => date('c'),
        'reason'     => 'customer_request',
    ]);
});
```

- **Right to export**: pakai cursor streaming untuk export semua data 1 customer:

```php
function exportCustomerData(string $customerId): void
{
    $data = ['customer_id' => $customerId, 'exported_at' => date('c')];
    foreach (['leads', 'opportunities', 'activities', 'contacts'] as $col) {
        $data[$col] = collection($col)
            ->find(['related_to' => $customerId])  // atau customer_id
            ->stream();
    }
    Flight::json($data);
}
```

### 6.4 Audit Log Sales Activity

Setiap perubahan stage opportunity harus tercatat (regulatory + sales coaching):

```php
collection('opportunities')->on('afterUpdate', function (array $old, array $new) {
    if (($old['stage'] ?? '') !== ($new['stage'] ?? '')) {
        collection('stage_change_log')->insert([
            'opp_id'      => $new['opp_id'],
            'from_stage'  => $old['stage'] ?? null,
            'to_stage'    => $new['stage'],
            'changed_by'  => $_SESSION['user_id'] ?? 'system',
            'changed_at'  => date('c'),
            'old_amount'  => $old['amount'] ?? null,
            'new_amount'  => $new['amount'] ?? null,
        ]);
    }
});
```

---

## 7. Relasi & Cross-Module Populate

### 7.1 Opportunity → Customer → Contacts

```php
$opp = collection('opportunities')
    ->findOne(['opp_id' => 'OPP-000123'])
    ->populateMany([
        'customer_id' => ['collection' => 'customers', 'fields' => ['name', 'code', 'email']],
        'contact_id'  => ['collection' => 'contacts',  'fields' => ['first_name', 'last_name', 'email', 'phone']],
    ]);
```

### 7.2 Cross-Database: CRM Opportunity → ERP Sales Order

Saat opportunity `closed_won`, sales rep bisa create SO langsung di ERP:

```php
function opportunityToSalesOrder(string $oppId): string
{
    $crm = Flight::get('bangron.client')->selectDB('crm');
    $erp = Flight::get('bangron.client')->selectDB('erp_sales');

    $opp = $crm->collection('opportunities')->findOne(['opp_id' => $oppId]);

    // Buat SO di database ERP
    $soNumber = 'SO-' . date('Y') . '-' . str_pad(
        (string) ($erp->collection('sales_orders')->count([]) + 1), 6, '0', STR_PAD_LEFT
    );
    $soId = $erp->collection('sales_orders')->insert([
        'so_number'    => $soNumber,
        'so_date'      => date('Y-m-d'),
        'customer_id'  => $opp['customer_id'],  // reference cross-database
        'sales_rep_id' => $opp['assigned_to'],
        'status'       => 'draft',
        'lines'        => [], // sales rep isi manual
        'subtotal'     => 0,
        'tax_total'    => 0,
        'grand_total'  => 0,
        'source_opportunity_id' => $oppId, // back-reference
    ]);

    // Update opportunity dengan reference SO
    $crm->collection('opportunities')->update(
        ['_id' => $opp['_id']],
        ['$set' => ['converted_so_id' => $soId]]
    );

    return $soNumber;
}
```

### 7.3 Activity Cross-Reference Display

Aktivitas CRM sering merujuk ke customer ERP. Pakai cross-database populate:

```php
$activities = collection('activities')->find(
    ['related_type' => 'customer', 'completed_at' => ['$gte' => '2026-07-01']]
);

foreach ($activities->stream() as $activity) {
    // Populate customer dari database erp_core
    $customer = Flight::get('bangron.client')
        ->selectDB('erp_core')
        ->collection('customers')
        ->findOne(['_id' => $activity['related_to']]);
    $activity['customer_name'] = $customer['name'] ?? 'Unknown';
    yield $activity;
}
```

---

## 8. Transaction Safety

CRM punya beberapa operasi multi-step yang WAJIB atomic, terutama lead conversion (cross-database) dan stage transitions.

### 8.1 Skenario yang WAJIB Pakai Transaction

| Skenario | Langkah Atomic | Konsekuensi Tanpa Transaction |
|----------|----------------|-------------------------------|
| Lead Conversion | Insert customer + insert opportunity + update lead.converted_to | Lead "converted" tapi customer/opportunity tidak ada |
| Activity Insert + Lead Score Update | Insert activity + update lead.lead_score + update lead.last_contact | Score tidak update padahal activity tercatat |
| Stage Change + Audit Log | Update opportunity.stage + insert stage_change_log + update closed_at | Stage berubah tapi audit log hilang |
| Opportunity Won → Create SO (cross-DB) | Update opp di crm + insert SO di erp_sales | Opp "won" tapi SO tidak ada (cross-DB, pakai 2 transaction) |
| Bulk Lead Import | insertMany leads + audit log | Sebagian insert, sebagian gagal |
| Merge Duplicate Leads | Update activities.related_to + delete duplicate lead + audit log | Activities lost, duplicate masih ada |

### 8.2 Lead Conversion (Cross-Database Transaction)

Karena customer ada di `erp_core` dan lead ada di `crm`, tidak bisa satu transaction. Pakai 2 transaction terpisah dengan idempotent flag:

```php
collection('leads')->on('afterUpdate', function (array $old, array $new) {
    if (($old['status'] ?? '') !== 'converted' && $new['status'] === 'converted') {
        // Cek apakah sudah pernah convert (idempotent)
        if (!empty($new['converted_to'])) return;

        // Transaction 1: Insert customer di erp_core
        $erpCore = Flight::get('bangron.client')->selectDB('erp_core');
        $conn1 = $erpCore->connection;
        $conn1->beginTransaction();
        try {
            $customerId = $erpCore->collection('customers')->insert([
                'code'  => 'CUST-' . str_pad((string) ($erpCore->collection('customers')->count([]) + 1), 5, '0', STR_PAD_LEFT),
                'name'  => $new['company'] ?? trim($new['first_name'] . ' ' . $new['last_name']),
                // ...
            ]);
            $conn1->commit();
        } catch (\Throwable $e) {
            if ($conn1->inTransaction()) $conn1->rollBack();
            throw $e;
        }

        // Transaction 2: Insert opportunity + update lead di crm
        $conn2 = collection('opportunities')->database->connection;
        $conn2->beginTransaction();
        try {
            $oppId = collection('opportunities')->insert([
                'opp_id'      => 'OPP-' . uniqid(),
                'customer_id' => $customerId,
                // ...
            ]);
            collection('leads')->update(
                ['_id' => $new['_id']],
                ['$set' => ['converted_to' => $customerId]]
            );
            $conn2->commit();
        } catch (\Throwable $e) {
            if ($conn2->inTransaction()) $conn2->rollBack();
            // COMPENSATING ACTION: hapus customer yang sudah dibuat
            // (saga pattern)
            $erpCore->collection('customers')->remove(['_id' => $customerId]);
            throw $e;
        }
    }
});
```

> Implementasi lengkap (dengan field customer/opportunity yang lengkap) ada di [§4.1](#41-auto-convert-lead--customer--opportunity).

### 8.3 Aturan Penting

1. Cek `inTransaction()` sebelum `rollBack()`.
2. Re-throw exception setelah rollback.
3. Side effects (kirim email notifikasi, sync ke marketing automation) DI LUAR transaction.
4. Cross-database (crm ↔ erp_core) TIDAK atomic — pakai 2 transaction + compensating action (saga).
5. Hook dalam database yang sama = atomic otomatis.
6. Untuk bulk import, pakai `insertMany()` yang otomatis transactional.

Lihat juga: [Auth & ACL → Transaction Safety](/docs/scenarios/auth-acl/#8-transaction-safety-atomic-multi-step-operasi) untuk pola lengkap.

---

## 9. Anti-Pattern CRM

1. **Simpan seluruh riwayat komunikasi di field array lead** — leads akan jadi dokumen raksasa. Pisahkan ke `activities` collection.

2. **Hardcode stage pipeline di code** — pakai collection `pipeline_stages` agar sales manager bisa edit stage tanpa code change.

3. **Skip activity logging "karena cepat"** — di CRM, data activity adalah gold mine untuk analytics. Selalu log, bahkan untuk WhatsApp quick reply.

4. **Tidak pakai lead_score** — tanpa scoring, sales rep kerja sequential, bukan prioritized. Auto-scoring via hooks hemat waktu manual.

5. **Email/phone plaintext** — PII wajib encryption + blind index. GDPR/UU PDP denda bisa miliaran.

6. **Permission check di controller saja** — tambahkan juga di query level: `find(['assigned_to' => $userId])` agar tidak bocor via API.

7. **Tidak cache laporan pipeline** — aggregation atas ribuan opportunities lambat. Cache 1 jam di `report_cache` collection.

---

## Referensi

- [ERP Scenario](/docs/scenarios/erp/) — modul ERP yang sering jadi target konversi CRM.
- [Modular Architecture](/docs/modular-architecture/) — cara setup multi-database (CRM + ERP + SCM + HRIS + POS).
- [Hook Patterns](/docs/hook-patterns/) — pola hook lanjutan.
- [Security](/docs/security/) — encryption, blind index, RBAC.
