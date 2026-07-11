---
layout: doc
title: "Project Scenarios: HRIS"
description: "Employees, attendance, payroll, PII encryption."
toc: true
edit_on_github: true
prev:
  url: /project-scenarios-scm/
  title: "Project Scenarios: SCM"
next:
  url: /project-scenarios-pos/
  title: "Project Scenarios: POS"
---
# Tips & Trick BangronDB: Skenario Project HRIS dengan Flight PHP

> Panduan praktis implementasi BangronDB pada modul HRIS (Human Resource Information System) — mencakup employee master, attendance, leave, payroll, performance review, dan training. Data sangat sensitif (gaji, KTP, NPWP) sehingga encryption wajib. Stack: Flight PHP.

## Daftar Isi

1. [Pendahuluan](#1-pendahuluan)
2. [Schema Design HRIS](#2-schema-design-hris)
3. [Query Patterns HRIS](#3-query-patterns-hris)
4. [Hooks & Events HRIS](#4-hooks--events-hris)
5. [Performance & Indexing](#5-performance--indexing)
6. [Security di HRIS](#6-security-di-hris)
7. [Relasi & Cross-Module Populate](#7-relasi--cross-module-populate)
8. [Transaction Safety](#8-transaction-safety)
9. [Anti-Pattern HRIS](#9-anti-pattern-hris)

---

## 1. Pendahuluan

HRIS menyimpan **data paling sensitif di perusahaan**: gaji, KTP, NPWP, riwayat kesehatan, performa. Kebocoran data = denda besar (UU PDP Indonesia, GDPR internasional) + rusak reputasi. Pola data HRIS lebih statis dari modul lain — employee master jarang berubah, tapi attendance & payroll high-volume (ribuan baris/hari).

**Kapan BangronDB cocok untuk HRIS:**

- HRIS untuk perusahaan 50-2000 karyawan.
- HR vertikal (klinik, manufaktur, retail) yang di-deploy on-premise.
- Modul HR/Payroll yang embed ke aplikasi existing.

**Kapan tidak cocok:**

- Perusahaan dengan multi-cabang yang butuh real-time sync (pertimbangkan PostgreSQL + replication).
- Perusahaan dengan >10.000 karyawan dan self-service portal high-concurrency.

---

## 2. Schema Design HRIS

### 2.1 Employee Master (PII Sensitif)

```php
collection('employees')->setSchema([
    'employee_id'   => ['type' => 'string', 'required' => true, 'unique' => true,
                         'regex' => '/^EMP-[0-9]{5}$/'],
    'first_name'    => ['type' => 'string', 'required' => true, 'min' => 1, 'max' => 100],
    'last_name'     => ['type' => 'string', 'max' => 100],
    'gender'        => ['type' => 'string', 'enum' => ['M', 'F']],
    'birth_date'    => ['type' => 'string'],
    'birth_place'   => ['type' => 'string'],
    'national_id'   => ['type' => 'string', 'regex' => '/^[0-9]{16}$/'], // NIK KTP
    'npwp'          => ['type' => 'string', 'regex' => '/^[0-9]{2}\.[0-9]{3}\.[0-9]{3}\.[0-9-]+$/'],
    'marital_status'=> ['type' => 'string', 'enum' => ['single', 'married', 'divorced', 'widowed']],
    'religion'      => ['type' => 'string', 'enum' => ['islam', 'kristen', 'katolik', 'hindu',
                         'buddha', 'konghucu']],
    'blood_type'    => ['type' => 'string', 'enum' => ['A', 'B', 'AB', 'O', 'unknown']],
    'phone'         => ['type' => 'string', 'regex' => '/^\+?[0-9]{8,15}$/'],
    'email'         => ['type' => 'string', 'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
    'address'       => ['type' => 'string', 'max' => 500],
    'department'    => ['type' => 'string', 'required' => true],
    'position'      => ['type' => 'string', 'required' => true],
    'grade'         => ['type' => 'string', 'enum' => ['A', 'B', 'C', 'D', 'E', 'M', 'EX']],
    'employment_status' => ['type' => 'string', 'enum' => ['permanent', 'contract', 'probation',
                             'intern', 'resigned']],
    'hire_date'     => ['type' => 'string', 'required' => true],
    'resign_date'   => ['type' => 'string'],
    'manager_id'    => ['type' => 'string'], // FK ke diri sendiri
    'bank_account'  => ['type' => 'string'], // akan di-encrypt
    'bpjs_kesehatan'=> ['type' => 'string'],
    'bpjs_ketenagakerjaan' => ['type' => 'string'],
    'is_active'     => ['type' => 'bool'],
])->saveConfiguration();
```

### 2.2 Attendance (High-Volume Daily Log)

```php
collection('attendance')->setSchema([
    'attendance_id' => ['type' => 'string', 'required' => true, 'unique' => true],
    'employee_id'   => ['type' => 'string', 'required' => true],
    'date'          => ['type' => 'string', 'required' => true], // YYYY-MM-DD
    'shift_id'      => ['type' => 'string'],
    'check_in'      => ['type' => 'string'], // ISO datetime
    'check_out'     => ['type' => 'string'],
    'check_in_method'  => ['type' => 'string', 'enum' => ['fingerprint', 'face', 'rfid',
                          'manual', 'mobile_gps']],
    'check_out_method' => ['type' => 'string', 'enum' => ['fingerprint', 'face', 'rfid',
                          'manual', 'mobile_gps']],
    'schedule_in'   => ['type' => 'string'],
    'schedule_out'  => ['type' => 'string'],
    'late_minutes'  => ['type' => 'int', 'min' => 0],
    'early_out_minutes' => ['type' => 'int', 'min' => 0],
    'overtime_minutes' => ['type' => 'int', 'min' => 0],
    'work_minutes'  => ['type' => 'int', 'min' => 0],
    'status'        => ['type' => 'string', 'enum' => ['present', 'late', 'absent',
                         'leave', 'holiday', 'sick', 'business_trip']],
    'location_in'   => ['type' => 'string'],
    'location_out'  => ['type' => 'string'],
    'notes'         => ['type' => 'string', 'max' => 200],
])->saveConfiguration();
```

### 2.3 Leave Requests

```php
collection('leave_requests')->setSchema([
    'leave_id'      => ['type' => 'string', 'required' => true, 'unique' => true],
    'employee_id'   => ['type' => 'string', 'required' => true],
    'leave_type'    => ['type' => 'string', 'required' => true,
                         'enum' => ['annual', 'sick', 'maternity', 'paternity', 'marriage',
                                    'death_family', 'religious', 'unpaid', 'official_duty']],
    'start_date'    => ['type' => 'string', 'required' => true],
    'end_date'      => ['type' => 'string', 'required' => true],
    'days'          => ['type' => 'float', 'required' => true, 'min' => 0.5],
    'reason'        => ['type' => 'string', 'max' => 500],
    'status'        => ['type' => 'string', 'enum' => ['pending', 'approved', 'rejected',
                         'cancelled'], 'required' => true],
    'requested_at'  => ['type' => 'string', 'required' => true],
    'approved_by'   => ['type' => 'string'],
    'approved_at'   => ['type' => 'string'],
    'rejection_reason' => ['type' => 'string', 'max' => 200],
    'attachment'    => ['type' => 'string'], // URL dokumen pendukung (surat dokter, dll)
])->saveConfiguration();
```

### 2.4 Payroll Components

```php
collection('payroll_runs')->setSchema([
    'payroll_id'    => ['type' => 'string', 'required' => true, 'unique' => true],
    'period_year'   => ['type' => 'int', 'required' => true, 'min' => 2020, 'max' => 2100],
    'period_month'  => ['type' => 'int', 'required' => true, 'min' => 1, 'max' => 12],
    'run_date'      => ['type' => 'string', 'required' => true],
    'status'        => ['type' => 'string', 'enum' => ['draft', 'processing', 'completed',
                         'approved', 'paid', 'cancelled']],
    'employee_count'=> ['type' => 'int', 'min' => 0],
    'total_gross'   => ['type' => 'float', 'min' => 0],
    'total_deduction' => ['type' => 'float', 'min' => 0],
    'total_net'     => ['type' => 'float', 'min' => 0],
    'processed_by'  => ['type' => 'string', 'required' => true],
    'approved_by'   => ['type' => 'string'],
])->saveConfiguration();

// Payslip per employee — ter-encrypt (gaji rahasia)
collection('payslips')->setSchema([
    'payslip_id'    => ['type' => 'string', 'required' => true, 'unique' => true],
    'payroll_id'    => ['type' => 'string', 'required' => true],
    'employee_id'   => ['type' => 'string', 'required' => true],
    'period_year'   => ['type' => 'int', 'required' => true],
    'period_month'  => ['type' => 'int', 'required' => true],
    'basic_salary'  => ['type' => 'float', 'required' => true],
    'allowances'    => ['type' => 'array'], // [{name, amount}, ...]
    'overtime_pay'  => ['type' => 'float', 'min' => 0],
    'bonus'         => ['type' => 'float', 'min' => 0],
    'gross_salary'  => ['type' => 'float', 'required' => true],
    'deductions'    => ['type' => 'array'], // [{name, amount}, ...]
    'tax_pph21'     => ['type' => 'float', 'min' => 0],
    'bpjs_kesehatan'=> ['type' => 'float', 'min' => 0],
    'bpjs_ketenagakerjaan' => ['type' => 'float', 'min' => 0],
    'net_salary'    => ['type' => 'float', 'required' => true],
    'bank_account'  => ['type' => 'string'], // di-encrypt
    'payment_status'=> ['type' => 'string', 'enum' => ['pending', 'paid', 'failed']],
    'paid_at'       => ['type' => 'string'],
])->saveConfiguration();
```

**Tips schema HRIS:**

- `employees.national_id` (NIK) dan `npwp` WAJIB di-encrypt + blind index.
- `attendance` tidak di-encrypt (data operasional), tapi akses di-batasin via RBAC.
- `payslips` WAJIB di-encrypt seluruhnya (gaji = rahasia).
- `leave_requests.attachment` simpan URL/path saja, file fisik di storage terpisah (S3/MinIO).

---

## 3. Query Patterns HRIS

### 3.1 Headcount Report per Department

```php
function getHeadcountReport(string $asOfDate): array
{
    return collection('employees')->aggregate([
        ['$match' => [
            'is_active'   => true,
            'hire_date'   => ['$lte' => $asOfDate],
            '$or' => [
                ['resign_date' => null],
                ['resign_date' => ['$gt' => $asOfDate]],
            ],
        ]],
        ['$group' => [
            '_id'        => ['department' => '$department', 'status' => '$employment_status'],
            'count'      => ['$sum' => 1],
            'avg_grade'  => ['$avg' => '$grade'],
        ]],
        ['$sort' => ['_id.department' => 1, '_id.status' => 1]],
    ]);
}
```

### 3.2 Attendance Summary Bulanan

```php
function getAttendanceSummary(string $employeeId, int $year, int $month): array
{
    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate   = date('Y-m-t', strtotime($startDate));

    return collection('attendance')->aggregate([
        ['$match' => [
            'employee_id' => $employeeId,
            'date'        => ['$gte' => $startDate, '$lte' => $endDate],
        ]],
        ['$group' => [
            '_id'              => '$status',
            'count'            => ['$sum' => 1],
            'total_late_min'   => ['$sum' => '$late_minutes'],
            'total_overtime_min' => ['$sum' => '$overtime_minutes'],
            'total_work_min'   => ['$sum' => '$work_minutes'],
        ]],
        ['$sort' => ['_id' => 1]],
    ]);
}
// Output: [['_id' => 'present', 'count' => 18, ...], ['_id' => 'late', 'count' => 3, ...], ...]
```

### 3.3 Leave Balance Calculator

Hitung sisa cuti tahunan berdasarkan request approved:

```php
function getLeaveBalance(string $employeeId, int $year): array
{
    // Default entitlement per grade (bisa dari config collection)
    $grade = collection('employees')->findOne(['_id' => $employeeId])['grade'] ?? 'C';
    $entitlement = ['EX' => 20, 'M' => 18, 'A' => 15, 'B' => 12, 'C' => 12, 'D' => 10, 'E' => 10][$grade] ?? 12;

    $used = collection('leave_requests')->aggregate([
        ['$match' => [
            'employee_id'  => $employeeId,
            'leave_type'   => 'annual',
            'status'       => 'approved',
            'start_date'   => ['$regex' => '^' . $year],
        ]],
        ['$group' => ['_id' => null, 'total_days' => ['$sum' => '$days']]],
    ]);

    $usedDays = $used[0]['total_days'] ?? 0;
    return [
        'entitlement' => $entitlement,
        'used'        => $usedDays,
        'remaining'   => $entitlement - $usedDays,
        'carry_forward' => 0, // bisa di-config per company policy
    ];
}
```

### 3.4 Payroll Tax Calculator (PPh21 Indonesia — TER Scheme)

```php
function calculatePPh21TER(float $grossMonthly, string $ptkpStatus): float
{
    // PTKP status: TK0, TK1, TK2, TK3, K0, K1, K2, K3
    $terRates = [
        'TK0' => [['limit' => 5400000, 'rate' => 0], ['limit' => 5650000, 'rate' => 0.05],
                  ['limit' => 6150000, 'rate' => 0.10], ['limit' => 6250000, 'rate' => 0.15],
                  ['limit' => 6350000, 'rate' => 0.20], ['limit' => 6750000, 'rate' => 0.25],
                  ['limit' => 7350000, 'rate' => 0.30], ['limit' => null, 'rate' => 0.35]],
        // ... PTKP lainnya
    ];

    $brackets = $terRates[$ptkpStatus] ?? $terRates['TK0'];
    $tax = 0;
    $previousLimit = 0;

    foreach ($brackets as $bracket) {
        if ($grossMonthly <= $previousLimit) break;
        $currentLimit = $bracket['limit'] ?? PHP_FLOAT_MAX;
        $taxable = min($grossMonthly, $currentLimit) - $previousLimit;
        if ($taxable > 0) {
            $tax += $taxable * $bracket['rate'];
        }
        $previousLimit = $currentLimit;
    }
    return $tax;
}
```

---

## 4. Hooks & Events HRIS

### 4.1 Auto-Calculate Late & Overtime saat Check-Out

```php
collection('attendance')->on('beforeUpdate', function (array $criteria, array $data) {
    // Hanya saat check_out di-set
    if (!isset($data['$set']['check_out'])) return $data;

    // Fetch dokumen attendance saat ini
    $att = collection('attendance')->findOne($criteria);
    if (!$att || !$att['check_in']) return $data;

    $checkIn  = strtotime($att['check_in']);
    $checkOut = strtotime($data['$set']['check_out']);
    $scheduleIn  = strtotime($att['schedule_in']);
    $scheduleOut = strtotime($att['schedule_out']);

    // Hitung late
    $lateMin = max(0, ($checkIn - $scheduleIn) / 60);
    // Hitung early out
    $earlyOutMin = max(0, ($scheduleOut - $checkOut) / 60);
    // Hitung overtime
    $overtimeMin = max(0, ($checkOut - $scheduleOut) / 60);
    // Total work minutes
    $workMin = ($checkOut - $checkIn) / 60;

    $data['$set']['late_minutes']      = (int) $lateMin;
    $data['$set']['early_out_minutes'] = (int) $earlyOutMin;
    $data['$set']['overtime_minutes']  = (int) $overtimeMin;
    $data['$set']['work_minutes']      = (int) $workMin;
    $data['$set']['status']            = $lateMin > 0 ? 'late' : 'present';

    return $data;
});
```

> **Catatan:** Hook `beforeUpdate` hanya mengembalikan modified `$data` — perubahan terjadi dalam satu `update()` call yang sama dengan caller, jadi **atomic otomatis**. Tidak perlu `beginTransaction()` eksplisit di hook ini. Audit log tambahan (misalnya catat siapa yang check-out) dapat di-insert via hook `afterUpdate` terpisah; jika ingin atomic dengan update, caller harus membungkus dengan `beginTransaction()` (lihat [§8. Transaction Safety](#8-transaction-safety)).

### 4.2 Auto-Update Leave Balance saat Leave Approved

Update leave_request status + insert multiple attendance records (per hari cuti) **WAJIB atomic** — kalau status approved tapi attendance gagal insert, karyawan tercatat absen. Bungkus dalam `beginTransaction()`/`commit()`/`rollBack()` (lihat [§8. Transaction Safety](#8-transaction-safety)):

```php
collection('leave_requests')->on('afterUpdate', function (array $old, array $new) {
    if (($old['status'] ?? '') !== 'approved' && $new['status'] === 'approved') {
        $conn = collection('attendance')->database->connection;
        $conn->beginTransaction();
        try {
            // Insert attendance record dengan status 'leave' untuk setiap hari cuti
            $start = strtotime($new['start_date']);
            $end   = strtotime($new['end_date']);
            $attRecords = [];
            for ($d = $start; $d <= $end; $d = strtotime('+1 day', $d)) {
                $dateStr = date('Y-m-d', $d);
                // Skip weekend
                $dow = date('N', $d);
                if ($dow >= 6) continue;

                $attRecords[] = [
                    'attendance_id' => 'ATT-' . $new['employee_id'] . '-' . $dateStr,
                    'employee_id'   => $new['employee_id'],
                    'date'          => $dateStr,
                    'status'        => 'leave',
                    'notes'         => 'Auto from leave request ' . $new['leave_id'],
                ];
            }
            if (!empty($attRecords)) {
                collection('attendance')->insertMany($attRecords);
            }
            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            throw $e;
        }
    }
});
```

> **Catatan:** Pola yang lebih robust adalah standalone function `approveLeave($leaveId, $approverId)` yang membungkus **update leave_request + insert attendance** dalam satu `beginTransaction()` di caller side — lihat [§8.3](#83-pola-leave-approval-insert-multiple-attendance). Hook `afterUpdate` di atas tetap atomic untuk insertMany-nya sendiri; bila caller-side `beginTransaction()` dipakai, hook berjalan dalam transaction yang sama (jangan double-begin).

### 4.3 Auto-Generate Payslip saat Payroll Run

Payroll run meng-insert ribuan payslips — **WAJIB atomic**, atau pakai **batch transaction** (500 payslips per transaction) untuk hindari lock lama. Lihat [§8. Transaction Safety](#8-transaction-safety):

```php
collection('payroll_runs')->on('afterInsert', function (array $payroll) {
    if ($payroll['status'] !== 'draft') return;

    // Update status → processing
    collection('payroll_runs')->update(
        ['_id' => $payroll['_id']],
        ['$set' => ['status' => 'processing']]
    );

    // Get semua active employees
    $employees = collection('employees')->find([
        'is_active'   => true,
        'hire_date'   => ['$lte' => $payroll['run_date']],
    ])->toArray();

    // Batch payslips: 500 per transaction — hindari lock database lama.
    $batches   = array_chunk($employees, 500);
    $conn      = collection('payslips')->database->connection;
    $totalGross = $totalDeduction = $totalNet = 0;

    foreach ($batches as $batch) {
        $conn->beginTransaction();
        try {
            foreach ($batch as $emp) {
                // Calculate basic salary, allowances, overtime from attendance, deductions
                $attendance = getAttendanceSummary($emp['_id'], $payroll['period_year'], $payroll['period_month']);
                // ... (calculation logic)

                $grossSalary = $emp['basic_salary'] + $allowances + $overtimePay;
                $taxPph21    = calculatePPh21TER($grossSalary, $emp['ptkp_status'] ?? 'TK0');
                $netSalary   = $grossSalary - $taxPph21 - $emp['bpjs_deductions'];

                collection('payslips')->insert([
                    'payslip_id'  => 'PS-' . $payroll['payroll_id'] . '-' . $emp['employee_id'],
                    'payroll_id'  => $payroll['_id'],
                    'employee_id' => $emp['_id'],
                    'period_year' => $payroll['period_year'],
                    'period_month'=> $payroll['period_month'],
                    'basic_salary'=> $emp['basic_salary'],
                    'allowances'  => $allowanceList,
                    'overtime_pay'=> $overtimePay,
                    'gross_salary'=> $grossSalary,
                    'deductions'  => $deductionList,
                    'tax_pph21'   => $taxPph21,
                    'bpjs_kesehatan' => $emp['bpjs_kesehatan_deduction'] ?? 0,
                    'bpjs_ketenagakerjaan' => $emp['bpjs_ketenagakerjaan_deduction'] ?? 0,
                    'net_salary'  => $netSalary,
                    'bank_account'=> $emp['bank_account'],
                    'payment_status' => 'pending',
                ]);

                $totalGross     += $grossSalary;
                $totalDeduction += ($grossSalary - $netSalary);
                $totalNet       += $netSalary;
            }
            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            // Mark payroll as failed — sebagian payslip sudah commit di batch sebelumnya,
            // tapi batch yang gagal di-rollback. Pakai reconciliation job untuk cleanup.
            collection('payroll_runs')->update(
                ['_id' => $payroll['_id']],
                ['$set' => ['status' => 'failed', 'error' => $e->getMessage()]]
            );
            throw $e;
        }
    }

    // Update payroll_run dengan total — transaction terpisah dari batch payslips.
    $conn->beginTransaction();
    try {
        collection('payroll_runs')->update(
            ['_id' => $payroll['_id']],
            ['$set' => [
                'status'          => 'completed',
                'employee_count'  => count($employees),
                'total_gross'     => $totalGross,
                'total_deduction' => $totalDeduction,
                'total_net'       => $totalNet,
            ]]
        );
        $conn->commit();
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        throw $e;
    }
});
```

> **Catatan:** JE ke `erp_finance` (hook `afterUpdate` pada `payroll_runs` saat status → `approved`) TIDAK dalam transaction yang sama — cross-database. Pakai idempotent flag `je_posted: true` di payroll_run + reconciliation job (lihat [§8.4 rule 4](#84-aturan-penting)).

### 4.4 Auto-Deactivate Employee saat Resign Date Tercapai

Update employee `is_active` + insert audit log **WAJIB atomic**. Revoke user access di `auth` (cross-DB) tidak bisa dalam transaction yang sama — pakai idempotent flag `access_revoked: true` + reconciliation job (lihat [§8. Transaction Safety](#8-transaction-safety)):

```php
collection('employees')->on('afterUpdate', function (array $old, array $new) {
    if (!empty($new['resign_date']) && $new['resign_date'] <= date('Y-m-d')) {
        if (($new['is_active'] ?? true) === true) {
            $conn = collection('employees')->database->connection;
            $conn->beginTransaction();
            try {
                // 1. Update employee is_active + employment_status — atomic dengan audit log
                collection('employees')->update(
                    ['_id' => $new['_id']],
                    ['$set' => [
                        'is_active'         => false,
                        'employment_status' => 'resigned',
                        'access_revoked'    => false, // flag: user access belum di-revoke (cross-DB)
                    ]]
                );

                // 2. Insert audit log — atomic dengan update di atas
                collection('hris_audit_log')->insert([
                    'entity_type'   => 'employee',
                    'entity_id'     => $new['_id'],
                    'action'        => 'auto_deactivate_on_resign',
                    'old_value'     => ['is_active' => true, 'employment_status' => $old['employment_status'] ?? null],
                    'new_value'     => ['is_active' => false, 'employment_status' => 'resigned'],
                    'reason'        => 'Resign date reached: ' . $new['resign_date'],
                    'acted_by'      => 'system',
                    'acted_at'      => date('c'),
                ]);

                $conn->commit();
            } catch (\Throwable $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                throw $e;
            }

            // 3. Revoke user access di erp_core/auth — CROSS-DATABASE, tidak atomic.
            //    Pakai idempotent flag `access_revoked: false` di employee; reconciliation job
            //    akan retry revoke untuk employee yang flag-nya masih false (lihat §8.4 rule 4).
            try {
                $erpCore = Flight::get('bangron.client')->selectDB('erp_core');
                $erpCore->collection('users')->update(
                    ['user_id' => $new['employee_id']],
                    ['$set' => ['is_active' => false, 'deactivated_at' => date('c'), 'source' => 'hris_resign_sync']]
                );
                // Update flag untuk mark reconciliation selesai
                collection('employees')->update(
                    ['_id' => $new['_id']],
                    ['$set' => ['access_revoked' => true]]
                );
            } catch (\Throwable $e) {
                // Log error; reconciliation job akan retry. JANGAN re-throw — resign sudah
                // ter-commit, failure cross-DB tidak boleh rollback data HRIS.
                error_log('Failed to revoke user access for resigned employee ' . $new['employee_id'] . ': ' . $e->getMessage());
            }
        }
    }
});
```

---

## 5. Performance & Indexing

### 5.1 Searchable Fields

```php
collection('employees')->setSearchableFields([
    'employee_id'   => ['hash' => false],
    'national_id'   => ['hash' => true],  // PII, blind index
    'npwp'          => ['hash' => true],
    'email'         => ['hash' => true],
    'department'    => ['hash' => false],
    'manager_id'    => ['hash' => false],
    'is_active'     => ['hash' => false],
])->saveConfiguration();

// Attendance = high-volume, wajib searchable
collection('attendance')->setSearchableFields([
    'employee_id'   => ['hash' => false],
    'date'          => ['hash' => false],
    'status'        => ['hash' => false],
    'shift_id'      => ['hash' => false],
])->saveConfiguration();

collection('payslips')->setSearchableFields([
    'payroll_id'    => ['hash' => false],
    'employee_id'   => ['hash' => false],
    'period_year'   => ['hash' => false],
    'period_month'  => ['hash' => false],
])->saveConfiguration();
```

### 5.2 TTL untuk Attendance Raw Data

Raw attendance logs dari mesin fingerprint bisa sangat besar. Set TTL untuk logs >2 tahun:

```php
collection('attendance_raw_logs')->setTTL(60 * 60 * 24 * 365 * 2); // 2 tahun
// Setelah 2 tahun, raw logs akan dihapus otomatis. Summary tetap ada di attendance collection.
```

### 5.3 Cursor untuk Export Payroll Tahunan

Export semua payslip tahunan untuk tax reporting:

```php
function exportAnnualPayroll(int $year): void
{
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="payroll-' . $year . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Month', 'Employee ID', 'Name', 'Gross', 'Tax', 'BPJS', 'Net']);

    $cursor = collection('payslips')
        ->find(['period_year' => $year])
        ->sort(['period_month' => 1, 'employee_id' => 1]);

    foreach ($cursor->stream() as $ps) {
        $emp = collection('employees')->findOne(['_id' => $ps['employee_id']]);
        fputcsv($out, [
            $ps['period_month'], $ps['employee_id'],
            trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')),
            $ps['gross_salary'], $ps['tax_pph21'],
            $ps['bpjs_kesehatan'] + $ps['bpjs_ketenagakerjaan'],
            $ps['net_salary'],
        ]);
    }
    fclose($out);
}
```

---

## 6. Security di HRIS

### 6.1 Encryption Field Sensitif (Paling Penting)

```php
// Pisahkan data PII sangat sensitif ke collection ter-encrypt terpisah
collection('employee_pii')->setEncryptionKey($_ENV['HRIS_PII_KEY']);
collection('employee_pii')->setSchema([
    'employee_id'   => ['type' => 'string', 'required' => true, 'unique' => true],
    'national_id'   => ['type' => 'string', 'required' => true], // NIK KTP
    'npwp'          => ['type' => 'string'],
    'bank_account'  => ['type' => 'string'],
    'bpjs_kesehatan'=> ['type' => 'string'],
    'bpjs_ketenagakerjaan' => ['type' => 'string'],
    'family_data'   => ['type' => 'array'], // nama keluarga, tanggal lahir anak
    'medical_history' => ['type' => 'string'], // jika ada
])->saveConfiguration();

// Payslips WAJIB di-encrypt seluruhnya
collection('payslips')->setEncryptionKey($_ENV['HRIS_PAYROLL_KEY']);
```

### 6.2 Blind Index untuk PII Query

NIK KTP sering dipakai untuk lookup employee — wajib blind index:

```php
collection('employee_pii')->setSearchableFields([
    'national_id' => ['hash' => true],
    'npwp'        => ['hash' => true],
])->saveConfiguration();

// Query tetap bisa — BangronDB akan hash input sebelum match
$emp = collection('employee_pii')->findOne(['national_id' => '3201234567890001']);
```

### 6.3 Multi-Level RBAC

HRIS punya banyak role sensitif:

```php
class HRIS_RBAC {
    private array $perms = [
        'employee_self'    => ['view_own_profile', 'view_own_payslip', 'request_leave'],
        'employee_manager' => ['view_subordinate_profile', 'approve_leave_subordinate',
                                'view_team_attendance'],
        'hr_staff'         => ['view_all_employees', 'manage_employees', 'view_attendance_all'],
        'hr_manager'       => ['view_all_employees', 'manage_employees', 'run_payroll',
                                'approve_payroll', 'view_all_payslips'],
        'finance'          => ['view_payroll_summary', 'view_payslip_paid_only', 'export_payroll'],
        'admin'            => ['*'],
    ];

    public function can(string $role, string $action): bool
    {
        $allowed = $this->perms[$role] ?? [];
        return in_array('*', $allowed, true) || in_array($action, $allowed, true);
    }
}

// Restrict payslip access — karyawan hanya bisa lihat payslip sendiri
Flight::route('GET /api/payslips/@id', function ($id) use ($rbac) {
    $currentUserId = $_SESSION['user_id'];
    $role = $_SESSION['role'];

    $payslip = collection('payslips')->findOne(['payslip_id' => $id]);
    if (!$payslip) Flight::halt(404, 'Not found');

    if ($payslip['employee_id'] !== $currentUserId
        && !$rbac->can($role, 'view_all_payslips')) {
        Flight::halt(403, 'Forbidden');
    }
    Flight::json($payslip);
});
```

### 6.4 Audit Log untuk Akses Data Sensitif

Setiap akses ke PII wajib tercatat:

```php
collection('employee_pii')->on('afterFind', function (array $criteria) {
    // Catat siapa yang akses data PII kapan
    collection('pii_access_log')->insert([
        'accessed_by'  => $_SESSION['user_id'] ?? 'system',
        'accessed_at'  => date('c'),
        'criteria'     => $criteria,
        'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ]);
});
```

### 6.5 Data Retention Policy

Untuk karyawan yang sudah resign, sebagian data harus di-retention, sebagian dihapus:

```php
function cleanupResignedEmployeeData(string $employeeId, string $resignDate): void
{
    $retentionYears = 5; // sesuai regulasi
    $cutoffDate = date('Y-m-d', strtotime("+$retentionYears years", strtotime($resignDate)));

    if (date('Y-m-d') < $cutoffDate) return;

    // Anonymize PII (tetap simpan untuk audit, tapi tanpa identitas)
    collection('employee_pii')->update(
        ['employee_id' => $employeeId],
        ['$set' => [
            'national_id' => 'ANONYMIZED-' . substr(md5($employeeId), 0, 8),
            'bank_account' => null,
            'medical_history' => null,
        ]]
    );

    // Attendance >2 tahun bisa dihapus
    collection('attendance')->remove([
        'employee_id' => $employeeId,
        'date'        => ['$lt' => date('Y-m-d', strtotime('-2 years'))],
    ]);

    // Payslips tetap simpan (regulasi tax 10 tahun)
}
```

---

## 7. Relasi & Cross-Module Populate

### 7.1 Employee → Manager (Self-Reference)

```php
$emp = collection('employees')
    ->findOne(['employee_id' => 'EMP-00123'])
    ->populate('manager_id', 'employees', ['employee_id', 'first_name', 'last_name', 'email']);
```

### 7.2 Cross-Database: HRIS Employee → ERP User Account

Saat karyawan baru di-add di HRIS, otomatis create user account di ERP:

```php
collection('employees')->on('afterInsert', function (array $emp) {
    $erp = Flight::get('bangron.client')->selectDB('erp_core');
    $erp->collection('users')->insert([
        'user_id'    => $emp['employee_id'], // sama dengan employee_id
        'username'   => strtolower(str_replace(' ', '.', $emp['first_name'] . '.' . $emp['last_name'])),
        'email'      => $emp['email'],
        'role'       => 'employee',
        'is_active'  => true,
        'created_at' => date('c'),
        'source'     => 'hris_sync',
    ]);
});
```

### 7.3 Cross-Module: Payroll → ERP Journal Entry

Payroll run otomatis generate journal entry di ERP finance:

```php
collection('payroll_runs')->on('afterUpdate', function (array $old, array $new) {
    if (($old['status'] ?? '') !== 'approved' && $new['status'] === 'approved') {
        $erpFinance = Flight::get('bangron.client')->selectDB('erp_finance');

        $erpFinance->collection('journal_entries')->insert([
            'je_number'   => 'JE-PAY-' . $new['payroll_id'],
            'je_date'     => $new['run_date'],
            'description' => 'Payroll for ' . $new['period_year'] . '-' . $new['period_month'],
            'source_type' => 'payroll',
            'source_id'   => $new['_id'],
            'is_posted'   => true,
            'total_debit' => $new['total_gross'],
            'total_credit'=> $new['total_gross'],
            'lines' => [
                ['account_code' => '5100-00', 'debit' => $new['total_gross'], 'credit' => 0], // Salary Expense
                ['account_code' => '2100-10', 'debit' => 0, 'credit' => $new['total_net']],   // Salary Payable
                ['account_code' => '2100-20', 'debit' => 0, 'credit' => $new['total_deduction']], // Tax & BPJS Payable
            ],
        ]);
    }
});
```

### 7.4 Attendance → Payroll (Cross-Period Reference)

```php
function getPayslipWithAttendance(string $payslipId): array
{
    $ps = collection('payslips')->findOne(['payslip_id' => $payslipId]);
    $attSummary = collection('attendance')->aggregate([
        ['$match' => [
            'employee_id' => $ps['employee_id'],
            'date'        => ['$regex' => sprintf('^%04d-%02d', $ps['period_year'], $ps['period_month'])],
        ]],
        ['$group' => [
            '_id' => '$status',
            'count' => ['$sum' => 1],
            'total_overtime' => ['$sum' => '$overtime_minutes'],
        ]],
    ]);
    $ps['attendance_summary'] = $attSummary;
    return $ps;
}
```

---

## 8. Transaction Safety

HRIS adalah modul dengan konsekuensi tertinggi jika data inkonsisten — gaji salah bisa berarti karyawan tidak dibayar atau dibayar dobel, attendance salah bisa berarti potongan gaji tidak adil, PII bocor bisa denda regulasi.

### 8.1 Skenario yang WAJIB Pakai Transaction

| Skenario | Langkah Atomic | Konsekuensi Tanpa Transaction |
|----------|----------------|-------------------------------|
| Leave Approval | Update leave_request.status + insert multiple attendance records (per hari cuti) | Status approved tapi attendance tidak update → karyawan tercatat absen |
| Payroll Run | Insert payroll_run + multiple payslips (ribuan) + JE (cross-DB) | Sebagian karyawan dapat payslip, sebagian tidak |
| Payroll Approval | Update payroll_run.status + insert JE di erp_finance | Status approved tapi JE tidak ada → laporan keuangan salah |
| Employee Resign | Update employee.is_active + revoke user access (cross-DB ke auth) + insert exit interview | Employee inactive tapi masih bisa login |
| Attendance Check-Out | Update attendance + audit log (hook beforeUpdate return modified data) | Audit log hilang padahal data sudah update |
| Payslip Payment | Update payslip.payment_status + insert payment JE + update cash_session | Paid di payslip tapi JE tidak ada |
| PII Update | Update employee_pii (encrypted) + audit log | PII berubah tanpa audit trail (regulasi violation) |
| Bulk Attendance Import | insertMany attendance + audit log | Sebagian insert, sebagian gagal |

### 8.2 Pola Transaction untuk Payroll Run

Payroll run insert ribuan payslips. Pakai batch transaction (500 payslips per transaction) untuk hindari lock lama:

```php
function runPayroll(string $payrollId, array $employeeIds): void {
    $payrollRun = collection('payroll_runs')->findOne(['_id' => $payrollId]);

    // Update status → processing
    collection('payroll_runs')->update(
        ['_id' => $payrollId],
        ['$set' => ['status' => 'processing']]
    );

    // Batch payslips: 500 per transaction
    $batches = array_chunk($employeeIds, 500);
    $conn = collection('payslips')->database->connection;
    $totalGross = $totalNet = $totalDeduction = 0;

    foreach ($batches as $batchIndex => $batch) {
        $conn->beginTransaction();
        try {
            foreach ($batch as $empId) {
                $emp = collection('employees')->findOne(['_id' => $empId]);
                if (!$emp) continue;

                // Calculate payslip
                $grossSalary = $emp['basic_salary'] + calculateAllowances($emp);
                $taxPph21 = calculatePPh21TER($grossSalary, $emp['ptkp_status'] ?? 'TK0');
                $netSalary = $grossSalary - $taxPph21 - calculateBpjsDeductions($emp);

                collection('payslips')->insert([
                    'payslip_id'  => 'PS-' . $payrollId . '-' . $emp['employee_id'],
                    'payroll_id'  => $payrollId,
                    'employee_id' => $emp['_id'],
                    'period_year' => $payrollRun['period_year'],
                    'period_month'=> $payrollRun['period_month'],
                    'basic_salary'=> $emp['basic_salary'],
                    'gross_salary'=> $grossSalary,
                    'tax_pph21'   => $taxPph21,
                    'net_salary'  => $netSalary,
                    'payment_status' => 'pending',
                ]);

                $totalGross += $grossSalary;
                $totalDeduction += ($grossSalary - $netSalary);
                $totalNet += $netSalary;
            }
            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            // Mark payroll as failed
            collection('payroll_runs')->update(
                ['_id' => $payrollId],
                ['$set' => ['status' => 'failed', 'error' => $e->getMessage()]]
            );
            throw $e;
        }
    }

    // Update payroll_run dengan total — transaction terpisah
    $conn->beginTransaction();
    try {
        collection('payroll_runs')->update(
            ['_id' => $payrollId],
            ['$set' => [
                'status'          => 'completed',
                'employee_count'  => count($employeeIds),
                'total_gross'     => $totalGross,
                'total_deduction' => $totalDeduction,
                'total_net'       => $totalNet,
            ]]
        );
        $conn->commit();
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        throw $e;
    }
}
```

### 8.3 Pola Leave Approval (Insert Multiple Attendance)

```php
function approveLeave(string $leaveId, string $approverId): void {
    $leave = collection('leave_requests')->findOne(['_id' => $leaveId]);
    if (!$leave || $leave['status'] !== 'pending') {
        throw new \RuntimeException('Leave request not found or already processed');
    }

    $conn = collection('attendance')->database->connection;
    $conn->beginTransaction();
    try {
        // Update leave status
        collection('leave_requests')->update(
            ['_id' => $leaveId],
            ['$set' => [
                'status'      => 'approved',
                'approved_by' => $approverId,
                'approved_at' => date('c'),
            ]]
        );

        // Insert attendance records untuk setiap hari cuti
        $start = strtotime($leave['start_date']);
        $end   = strtotime($leave['end_date']);
        $attRecords = [];
        for ($d = $start; $d <= $end; $d = strtotime('+1 day', $d)) {
            $dow = date('N', $d);
            if ($dow >= 6) continue; // skip weekend

            $attRecords[] = [
                'attendance_id' => 'ATT-' . $leave['employee_id'] . '-' . date('Y-m-d', $d),
                'employee_id'   => $leave['employee_id'],
                'date'          => date('Y-m-d', $d),
                'status'        => 'leave',
                'notes'         => 'Auto from leave request ' . $leave['leave_id'],
            ];
        }
        if (!empty($attRecords)) {
            collection('attendance')->insertMany($attRecords);
        }

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
3. Side effects (kirim payslip PDF via email, sync ke bank untuk payment) DI LUAR transaction.
4. Cross-database (hris ↔ erp_finance untuk payroll JE, hris ↔ auth untuk user access) TIDAK atomic — pakai 2 transaction + idempotent flag.
5. PII update WAJIB audit log dalam transaction yang sama — regulasi (UU PDP, GDPR) mewajibkan audit trail.
6. Payslips WAJIB encryption — transaction tetap berfungsi normal dengan encrypted collection.
7. Untuk payroll run ribuan karyawan, pakai batch transaction (500 per batch) untuk hindari lock lama.

Lihat juga: [Auth & ACL → Transaction Safety](project-scenarios-auth-acl.md#8-transaction-safety-atomic-multi-step-operasi) untuk pola lengkap.

---

## 9. Anti-Pattern HRIS

1. **Simpan NIK/bank account di collection `employees` tanpa encryption** — kebocoran = bencana. Wajib pisahkan ke `employee_pii` dengan encryption + blind index.

2. **Payslip tidak di-encrypt** — siapa saja yang punya akses file `.bangron` bisa lihat gaji semua karyawan. Encryption key dari `.env`, bukan di code.

3. **Tidak ada audit log akses PII** — regulator bisa minta "siapa akses data karyawan X kapan". Tanpa log, denda.

4. **Payroll calculation di code, bukan di config** — perubahan tarif PPh21, BPJS, tunjangan = harus edit code + redeploy. Simpan di collection `payroll_config` agar HR bisa update.

5. **Hard-delete karyawan resign** — regulasi tax Indonesia mewajibkan penyimpanan data payroll 10 tahun. Pakai soft-delete (set `is_active = false`), jangan `remove()`.

6. **Manager bisa lihat payslip subordinate** — kecuali explicit policy, payslip hanya untuk diri sendiri + HR/finance. RBAC harus ketat.

7. **Tidak handle payroll retry** — jika bank transfer gagal untuk sebagian karyawan, harus bisa re-run per karyawan tanpa double-pay. Status `payment_status` per payslip wajib.

8. **Attendance raw logs disimpan selamanya** — bisa GB per tahun untuk perusahaan besar. Set TTL untuk raw logs, simpan summary saja.

---

## Referensi

- [ERP Scenario](project-scenarios-erp.md) — modul ERP yang jadi tujuan integrasi (journal entry, user account).
- [Modular Architecture](modular-architecture.md) — setup multi-database HRIS + ERP + lainnya.
- [Security](security.md) — encryption, blind index, audit log (sangat relevan untuk HRIS).
- [Hook Patterns](hook-patterns.md) — pola hook untuk business logic payroll & attendance.
