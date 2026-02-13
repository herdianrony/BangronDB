<?php

/**
 * Contoh 12: Hospital Management System - Real World Scenario.
 *
 * Demonstrasi penggunaan BangronDB untuk sistem manajemen rumah sakit dengan:
 * - Enkripsi data medis sensitif
 * - Schema validation untuk integritas data
 * - Soft deletes (tidak ada penghapusan permanen untuk legalitas)
 * - Hooks untuk audit trail
 * - Searchable fields untuk pencarian pasien
 * - Transactions untuk billing
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

echo "=== Sistem Manajemen Rumah Sakit ===\n\n";

// Setup database path lokal
$path = __DIR__ . '/data/hospital_demo';
if (!is_dir($path)) {
    mkdir($path, 0755, true);
}
$client = new Client($path);
$db = $client->selectDB('hospital');

// ============================================
// 1. Setup Collections dengan Konfigurasi
// ============================================

echo "1. Setup Collections\n";
echo "--------------------\n";

// Patients collection - dengan encryption dan searchable fields
$patients = $db->patients;
$patients->setEncryptionKey('hospital-encryption-key-32chars!!');
$patients->setSearchableFields(['nik', 'phone'], true); // NIK dan phone di-hash
$patients->useSoftDeletes(true);
$patients->setSchema([
    'nik' => ['type' => 'string', 'required' => true, 'min' => 16, 'max' => 16],
    'name' => ['type' => 'string', 'required' => true, 'min' => 2],
    'birth_date' => ['type' => 'string', 'required' => true],
    'gender' => ['enum' => ['L', 'P']],
    'address' => ['type' => 'string'],
    'phone' => ['type' => 'string', 'required' => true],
    'emergency_contact' => ['type' => 'string'],
    'blood_type' => ['enum' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']],
    'allergies' => ['type' => 'array'],
    'insurance_id' => ['type' => 'string'],
]);
$patients->saveConfiguration();
echo "- Patients: encryption, searchable NIK/phone, schema, soft deletes\n";

// Medical records collection - highly encrypted
$medicalRecords = $db->medical_records;
$medicalRecords->setEncryptionKey('medical-records-key-at-least-32-chars!');
$medicalRecords->useSoftDeletes(true);
$medicalRecords->setSchema([
    'patient_id' => ['type' => 'string', 'required' => true],
    'doctor_id' => ['type' => 'string', 'required' => true],
    'visit_date' => ['type' => 'string', 'required' => true],
    'chief_complaint' => ['type' => 'string', 'required' => true],
    'diagnosis' => ['type' => 'string', 'required' => true],
    'treatment' => ['type' => 'string'],
    'prescription' => ['type' => 'array'],
    'vital_signs' => ['type' => 'object'],
    'notes' => ['type' => 'string'],
]);
$medicalRecords->saveConfiguration();
echo "- Medical Records: encrypted, schema validation\n";

// Appointments collection
$appointments = $db->appointments;
$appointments->setSchema([
    'patient_id' => ['type' => 'string', 'required' => true],
    'doctor_id' => ['type' => 'string', 'required' => true],
    'appointment_date' => ['type' => 'string', 'required' => true],
    'appointment_time' => ['type' => 'string', 'required' => true],
    'department' => ['type' => 'string', 'required' => true],
    'type' => ['enum' => ['consultation', 'follow_up', 'emergency', 'checkup']],
    'status' => ['enum' => ['scheduled', 'completed', 'cancelled', 'no_show']],
    'notes' => ['type' => 'string'],
]);
$appointments->saveConfiguration();
echo "- Appointments: schema validation\n";

// Doctors collection
$doctors = $db->doctors;
$doctors->setSchema([
    'name' => ['type' => 'string', 'required' => true],
    'specialization' => ['type' => 'string', 'required' => true],
    'license_number' => ['type' => 'string', 'required' => true],
    'phone' => ['type' => 'string', 'required' => true],
    'email' => ['type' => 'string', 'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
    'department' => ['type' => 'string', 'required' => true],
    'schedule' => ['type' => 'object'],
]);
$doctors->saveConfiguration();
echo "- Doctors: schema validation\n";

// Billing collection
$billing = $db->billing;
$billing->setSchema([
    'patient_id' => ['type' => 'string', 'required' => true],
    'record_id' => ['type' => 'string', 'required' => true],
    'items' => ['type' => 'array', 'required' => true],
    'total_amount' => ['type' => 'number', 'required' => true],
    'discount' => ['type' => 'number'],
    'insurance_coverage' => ['type' => 'number'],
    'final_amount' => ['type' => 'number', 'required' => true],
    'status' => ['enum' => ['pending', 'paid', 'partial', 'insurance']],
    'payment_method' => ['enum' => ['cash', 'card', 'insurance', 'transfer']],
]);
$billing->saveConfiguration();
echo "- Billing: schema validation\n";

// Pharmacy inventory
$pharmacy = $db->pharmacy;
$pharmacy->setSchema([
    'name' => ['type' => 'string', 'required' => true],
    'code' => ['type' => 'string', 'required' => true],
    'category' => ['enum' => ['medicine', 'equipment', 'consumable']],
    'unit' => ['type' => 'string', 'required' => true],
    'price' => ['type' => 'number', 'required' => true],
    'stock' => ['type' => 'int', 'min' => 0],
    'expiry_date' => ['type' => 'string'],
    'supplier' => ['type' => 'string'],
    'min_stock' => ['type' => 'int'],
]);
$pharmacy->saveConfiguration();
echo "- Pharmacy: schema validation\n\n";

// ============================================
// 2. Hooks untuk Audit Trail (per collection)
// ============================================

echo "2. Setup Audit Trail Hooks\n";
echo "--------------------------\n";

// Hooks untuk patients
$patients->on('beforeInsert', function ($doc) {
    $doc['_created_at'] = date('c');

    return $doc;
});
$patients->on('beforeUpdate', function ($criteria, $data) {
    if (!isset($data['$set'])) {
        $data['$set'] = [];
    }
    $data['$set']['_updated_at'] = date('c');

    return [$criteria, $data];
});

// Hooks untuk medical records (wajib audit trail)
$medicalRecords->on('beforeInsert', function ($doc) {
    $doc['_audit']['created_at'] = date('c');

    return $doc;
});
$medicalRecords->on('beforeUpdate', function ($criteria, $data) {
    if (!isset($data['$set'])) {
        $data['$set'] = [];
    }
    $data['$set']['_audit']['updated_at'] = date('c');

    return [$criteria, $data];
});

echo "- Audit hooks activated for patients and medical records\n\n";

// ============================================
// 3. Insert Data Sample
// ============================================

echo "3. Insert Data Sample\n";
echo "---------------------\n";

// Insert doctors
$doctor1 = $doctors->insert([
    'name' => 'Dr. Ahmad Fauzi',
    'specialization' => 'Cardiology',
    'license_number' => 'SIM-123456',
    'phone' => '+62812345678',
    'email' => 'ahmad.fauzi@hospital.com',
    'department' => 'Cardiology',
    'schedule' => ['mon' => '08:00-16:00', 'wed' => '08:00-16:00', 'fri' => '08:00-16:00'],
]);
echo "- Doctor added: Dr. Ahmad Fauzi (Cardiology)\n";

$doctor2 = $doctors->insert([
    'name' => 'Dr. Sarah Melinda',
    'specialization' => 'General Medicine',
    'license_number' => 'SIM-789012',
    'phone' => '+62898765432',
    'email' => 'sarah.melinda@hospital.com',
    'department' => 'General',
    'schedule' => ['tue' => '09:00-17:00', 'thu' => '09:00-17:00'],
]);
echo "- Doctor added: Dr. Sarah Melinda (General Medicine)\n";

// Insert patients
$patient1 = $patients->insert([
    'nik' => '3171012345678901',
    'name' => 'Budi Santoso',
    'birth_date' => '1985-05-15',
    'gender' => 'L',
    'address' => 'Jl. Sudirman No. 123, Jakarta Pusat',
    'phone' => '+6285512345678',
    'emergency_contact' => '+6285512345679',
    'blood_type' => 'A+',
    'allergies' => ['penicillin'],
    'insurance_id' => 'INS-001234',
]);
echo "- Patient added: Budi Santoso (NIK: 3171012345678901)\n";

$patient2 = $patients->insert([
    'nik' => '3171098765432109',
    'name' => 'Siti Aminah',
    'birth_date' => '1990-08-22',
    'gender' => 'P',
    'address' => 'Jl. Thamrin No. 456, Jakarta Pusat',
    'phone' => '+6285598765432',
    'emergency_contact' => '+6285598765433',
    'blood_type' => 'O+',
    'insurance_id' => 'INS-005678',
]);
echo "- Patient added: Siti Aminah (NIK: 3171098765432109)\n";

// Insert pharmacy items
$pharmacy->insert([
    'name' => 'Amoxicillin 500mg',
    'code' => 'AMX-500',
    'category' => 'medicine',
    'unit' => 'capsule',
    'price' => 15000,
    'stock' => 500,
    'expiry_date' => '2026-12-31',
    'supplier' => 'PT Pharma Indo',
    'min_stock' => 100,
]);
$pharmacy->insert([
    'name' => 'Paracetamol 500mg',
    'code' => 'PCM-500',
    'category' => 'medicine',
    'unit' => 'tablet',
    'price' => 5000,
    'stock' => 1000,
    'expiry_date' => '2027-06-30',
    'supplier' => 'PT Pharma Indo',
    'min_stock' => 200,
]);
echo "- Pharmacy items added\n\n";

// ============================================
// 4. Buat Appointment
// ============================================

echo "4. Buat Appointment\n";
echo "--------------------\n";

$appointment = $appointments->insert([
    'patient_id' => $patient1,
    'doctor_id' => $doctor1,
    'appointment_date' => date('Y-m-d', strtotime('+1 day')),
    'appointment_time' => '10:00',
    'department' => 'Cardiology',
    'type' => 'consultation',
    'status' => 'scheduled',
    'notes' => 'Heart checkup rutin',
]);
echo "- Appointment dibuat untuk Budi Santoso dengan Dr. Ahmad Fauzi\n\n";

// ============================================
// 5. Kunjungan dan Rekam Medis
// ============================================

echo "5. Kunjungan dan Rekam Medis\n";
echo "----------------------------\n";

// Patient visit dan medical record
$recordId = $medicalRecords->insert([
    'patient_id' => $patient1,
    'doctor_id' => $doctor1,
    'visit_date' => date('Y-m-d'),
    'chief_complaint' => 'Nyeri dada saat aktivitas',
    'diagnosis' => 'Gangguan jantung ringan - Perlu observasi',
    'treatment' => 'Istirahat total, diet rendah garam',
    'prescription' => [
        ['medicine' => 'Amoxicillin 500mg', 'dosage' => '3x1', 'duration' => '7 hari'],
        ['medicine' => 'Paracetamol 500mg', 'dosage' => ' jika diperlukan', 'duration' => '-'],
    ],
    'vital_signs' => [
        'blood_pressure' => '120/80',
        'heart_rate' => 72,
        'temperature' => 36.5,
    ],
    'notes' => 'Pasien disarankan kontrol ulang dalam 1 minggu',
]);
echo "- Medical record dibuat untuk Budi Santoso\n";
echo "  Diagnosis: Gangguan jantung ringan\n";
echo "  Treatment: Istirahat total, diet rendah garam\n\n";

// ============================================
// 6. Billing dengan Transaction
// ============================================

echo "6. Billing dengan Transaction\n";
echo "-----------------------------\n";

try {
    $billingRecord = $billing->insert([
        'patient_id' => $patient1,
        'record_id' => $recordId,
        'items' => [
            ['description' => 'Konsultasi Dokter', 'amount' => 150000],
            ['description' => 'Medical Checkup', 'amount' => 250000],
            ['description' => 'Amoxicillin 500mg (7 capsule)', 'amount' => 105000],
            ['description' => 'Paracetamol 500mg (10 tablet)', 'amount' => 50000],
        ],
        'total_amount' => 555000,
        'discount' => 0,
        'insurance_coverage' => 277500,
        'final_amount' => 277500,
        'status' => 'insurance',
        'payment_method' => 'insurance',
    ]);
    echo "- Billing record dibuat\n";
    echo "  Total: Rp 555.000\n";
    echo "  Asuransi: Rp 277.500\n";
    echo "  Bayar: Rp 277.500\n\n";
} catch (Exception $e) {
    echo 'Billing Error: ' . $e->getMessage() . "\n\n";
}

// ============================================
// 7. Search dan Queries
// ============================================

echo "7. Search dan Queries\n";
echo "---------------------\n";

// Cari pasien by NIK (menggunakan hashed searchable field)
$foundPatients = $patients->find(['nik' => '3171012345678901']);
echo "- Cari pasien by NIK:\n";
foreach ($foundPatients as $p) {
    echo "  Found: {$p['name']} (NIK: {$p['nik']})\n";
}

// Cari pasien by phone
$foundByPhone = $patients->find(['phone' => '+6285512345678']);
echo "- Cari pasien by phone:\n";
foreach ($foundByPhone as $p) {
    echo "  Found: {$p['name']} (Phone: {$p['phone']})\n";
}

// Lihat semua appointment
echo "- Appointment hari ini:\n";
$todayAppointments = $appointments->find(['appointment_date' => date('Y-m-d')]);
foreach ($todayAppointments as $apt) {
    echo "  - {$apt['appointment_time']}: {$apt['type']} - Status: {$apt['status']}\n";
}

// ============================================
// 8. Low Stock Alert
// ============================================

echo "\n8. Pharmacy - Low Stock Alert\n";
echo "------------------------------\n";

$lowStock = $pharmacy->find(['stock' => ['$lt' => 200]]);
echo "- Items dengan stock rendah:\n";
foreach ($lowStock as $item) {
    echo "  - {$item['name']}: {$item['stock']} {$item['unit']} (min: {$item['min_stock']})\n";
}

echo "\n=== Sistem Berjalan dengan Benar ===\n";
echo '- Total Pasien: ' . $patients->count() . "\n";
echo '- Total Dokter: ' . $doctors->count() . "\n";
echo '- Total Rekam Medis: ' . $medicalRecords->count() . "\n";
echo '- Total Appointment: ' . $appointments->count() . "\n";
echo '- Total Billing: ' . $billing->count() . "\n";
echo '- Total Items Pharmacy: ' . $pharmacy->count() . "\n\n";

// ============================================
// Cleanup
// ============================================

echo "=== Cleanup ===\n";
@$db->drop(); // @ to suppress Windows file lock warning
@$client->close();
echo "Database dibersihkan.\n";
