<?php

/**
 * Contoh 13: Hospital Management System - Complex Relations & Multi-Database.
 *
 * Demonstrasi penggunaan BangronDB untuk sistem manajemen rumah sakit dengan:
 * - Relasi data antar collection (patient -> medical records, doctor -> appointments)
 * - Cross-database relations (master data vs transaction data)
 * - Population untuk relasi antar document
 * - Comprehensive audit trail
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;
use BangronDB\Database;

echo "=== Sistem Manajemen Rumah Sakit - Complex Relations ===\n\n";

// ============================================
// Setup Multiple Databases
// ============================================

echo "1. Setup Multiple Databases\n";
echo "----------------------------\n";

// Master database untuk data utama (patients, doctors, departments)
$masterPath = __DIR__ . '/data/hospital_master';
if (!is_dir($masterPath)) {
    mkdir($masterPath, 0755, true);
}
$masterClient = new Client($masterPath);
$masterDb = $masterClient->selectDB('master');

// Transaction database untuk data operasional (appointments, medical records, billing)
$transactionPath = __DIR__ . '/data/hospital_transaction';
if (!is_dir($transactionPath)) {
    mkdir($transactionPath, 0755, true);
}
$transactionClient = new Client($transactionPath);
$transactionDb = $transactionClient->selectDB('transaction');

// HR database untuk staff management
$hrPath = __DIR__ . '/data/hospital_hr';
if (!is_dir($hrPath)) {
    mkdir($hrPath, 0755, true);
}
$hrClient = new Client($hrPath);
$hrDb = $hrClient->selectDB('hr');

echo "- Master DB: Patients, Departments, Rooms\n";
echo "- Transaction DB: Appointments, Medical Records, Billing\n";
echo "- HR DB: Staff, Schedules, Payroll\n\n";

// ============================================
// Setup Master Database Collections
// ============================================

echo "2. Setup Master Database\n";
echo "------------------------\n";

// Departments
$departments = $masterDb->departments;
$departments->setSchema([
    'name' => ['type' => 'string', 'required' => true],
    'code' => ['type' => 'string', 'required' => true],
    'floor' => ['type' => 'int'],
    'phone' => ['type' => 'string'],
    'head_doctor_id' => ['type' => 'string'],
]);
$departments->saveConfiguration();

// Rooms
$rooms = $masterDb->rooms;
$rooms->setSchema([
    'name' => ['type' => 'string', 'required' => true],
    'department_id' => ['type' => 'string', 'required' => true],
    'type' => ['enum' => ['general', 'vip', 'icu', 'emergency', 'operation']],
    'capacity' => ['type' => 'int'],
    'current_occupancy' => ['type' => 'int'],
    'status' => ['enum' => ['available', 'occupied', 'maintenance']],
]);
$rooms->saveConfiguration();

// Patients (Master data - akan direferensikan dari Transaction DB)
$patients = $masterDb->patients;
$patients->setEncryptionKey('patients-encryption-key-at-least-32-chars!!');
$patients->setSearchableFields(['nik', 'phone'], true);
$patients->setSchema([
    'nik' => ['type' => 'string', 'required' => true, 'min' => 16, 'max' => 16],
    'name' => ['type' => 'string', 'required' => true],
    'birth_date' => ['type' => 'string', 'required' => true],
    'gender' => ['enum' => ['L', 'P']],
    'blood_type' => ['enum' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']],
    'phone' => ['type' => 'string', 'required' => true],
    'emergency_contact' => ['type' => 'string'],
    'insurance_id' => ['type' => 'string'],
    'insurance_provider' => ['type' => 'string'],
]);
$patients->saveConfiguration();
echo "- Master collections: Departments, Rooms, Patients\n\n";

// ============================================
// Setup HR Database Collections
// ============================================

echo "3. Setup HR Database\n";
echo "--------------------\n";

// Doctors (HR database - karena ini staff)
$doctors = $hrDb->doctors;
$doctors->setSchema([
    'employee_id' => ['type' => 'string', 'required' => true],
    'name' => ['type' => 'string', 'required' => true],
    'specialization' => ['type' => 'string', 'required' => true],
    'license_number' => ['type' => 'string', 'required' => true],
    'phone' => ['type' => 'string', 'required' => true],
    'email' => ['type' => 'string', 'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
    'department_id' => ['type' => 'string'], // Reference to master DB
    'status' => ['enum' => ['active', 'inactive', 'on_leave']],
    'join_date' => ['type' => 'string'],
]);
$doctors->saveConfiguration();

// Nurses
$nurses = $hrDb->nurses;
$nurses->setSchema([
    'employee_id' => ['type' => 'string', 'required' => true],
    'name' => ['type' => 'string', 'required' => true],
    'phone' => ['type' => 'string', 'required' => true],
    'department_id' => ['type' => 'string'],
    'status' => ['enum' => ['active', 'inactive']],
]);
$nurses->saveConfiguration();
echo "- HR collections: Doctors, Nurses\n\n";

// ============================================
// Setup Transaction Database Collections
// ============================================

echo "4. Setup Transaction Database\n";
echo "------------------------------\n";

// Appointments
$appointments = $transactionDb->appointments;
$appointments->setSchema([
    'patient_id' => ['type' => 'string', 'required' => true], // Reference to master DB
    'doctor_id' => ['type' => 'string', 'required' => true], // Reference to HR DB
    'department_id' => ['type' => 'string'], // Reference to master DB
    'appointment_date' => ['type' => 'string', 'required' => true],
    'appointment_time' => ['type' => 'string', 'required' => true],
    'type' => ['enum' => ['consultation', 'follow_up', 'emergency', 'checkup']],
    'status' => ['enum' => ['scheduled', 'completed', 'cancelled', 'no_show']],
    'room_id' => ['type' => 'string'], // Reference to master DB
    'notes' => ['type' => 'string'],
]);
$appointments->saveConfiguration();

// Medical Records (with references ke multiple databases)
$medicalRecords = $transactionDb->medical_records;
$medicalRecords->setEncryptionKey('medical-records-encryption-key-32-chars!!');
$medicalRecords->setSchema([
    'patient_id' => ['type' => 'string', 'required' => true], // master DB
    'doctor_id' => ['type' => 'string', 'required' => true], // HR DB
    'appointment_id' => ['type' => 'string'], // Transaction DB
    'department_id' => ['type' => 'string'], // master DB
    'visit_date' => ['type' => 'string', 'required' => true],
    'chief_complaint' => ['type' => 'string', 'required' => true],
    'diagnosis' => ['type' => 'string', 'required' => true],
    'treatment' => ['type' => 'string'],
    'prescription' => ['type' => 'array'],
    'vital_signs' => ['type' => 'object'],
    'status' => ['enum' => ['active', 'follow_up', 'closed']],
]);
$medicalRecords->saveConfiguration();

// Billing
$billing = $transactionDb->billing;
$billing->setSchema([
    'patient_id' => ['type' => 'string', 'required' => true], // master DB
    'record_id' => ['type' => 'string', 'required' => true], // Transaction DB
    'items' => ['type' => 'array', 'required' => true],
    'total_amount' => ['type' => 'number', 'required' => true],
    'discount' => ['type' => 'number'],
    'insurance_claim' => ['type' => 'number'],
    'final_amount' => ['type' => 'number', 'required' => true],
    'status' => ['enum' => ['pending', 'paid', 'partial', 'insurance', 'waived']],
    'payment_method' => ['enum' => ['cash', 'card', 'insurance', 'transfer']],
    'paid_at' => ['type' => 'string'],
]);
$billing->saveConfiguration();
echo "- Transaction collections: Appointments, Medical Records, Billing\n\n";

// ============================================
// Insert Data with Cross-Database References
// ============================================

echo "5. Insert Master Data\n";
echo "---------------------\n";

// Departments
$dept1 = $departments->insert([
    'name' => 'Cardiology',
    'code' => 'CARD',
    'floor' => 3,
    'phone' => '+6221-555-0001',
]);
$dept2 = $departments->insert([
    'name' => 'General Medicine',
    'code' => 'GEN',
    'floor' => 2,
    'phone' => '+6221-555-0002',
]);
$dept3 = $departments->insert([
    'name' => 'Emergency',
    'code' => 'EMG',
    'floor' => 1,
    'phone' => '+6221-555-0003',
]);
echo "- Departments: Cardiology, General Medicine, Emergency\n";

// Rooms
$room1 = $rooms->insert([
    'name' => 'Room 301',
    'department_id' => $dept1,
    'type' => 'general',
    'capacity' => 4,
    'current_occupancy' => 2,
    'status' => 'available',
]);
$room2 = $rooms->insert([
    'name' => 'ICU-1',
    'department_id' => $dept1,
    'type' => 'icu',
    'capacity' => 1,
    'current_occupancy' => 1,
    'status' => 'occupied',
]);
$room3 = $rooms->insert([
    'name' => 'ER-Bay-1',
    'department_id' => $dept3,
    'type' => 'emergency',
    'capacity' => 1,
    'current_occupancy' => 0,
    'status' => 'available',
]);
echo "- Rooms: Room 301, ICU-1, ER-Bay-1\n\n";

echo "6. Insert HR Data\n";
echo "-----------------\n";

// Doctors (with department reference to master DB)
$doctor1 = $doctors->insert([
    'employee_id' => 'DR-001',
    'name' => 'Dr. Ahmad Fauzi',
    'specialization' => 'Cardiology',
    'license_number' => 'SIM-123456',
    'phone' => '+62812345678',
    'email' => 'ahmad.fauzi@hospital.com',
    'department_id' => $dept1, // Reference to master DB
    'status' => 'active',
    'join_date' => '2020-01-15',
]);
$doctor2 = $doctors->insert([
    'employee_id' => 'DR-002',
    'name' => 'Dr. Sarah Melinda',
    'specialization' => 'General Medicine',
    'license_number' => 'SIM-789012',
    'phone' => '+62898765432',
    'email' => 'sarah.melinda@hospital.com',
    'department_id' => $dept2, // Reference to master DB
    'status' => 'active',
    'join_date' => '2021-03-20',
]);
echo "- Doctors: Dr. Ahmad Fauzi (Cardiology), Dr. Sarah Melinda (General)\n\n";

echo "7. Insert Patients (Master DB)\n";
echo "------------------------------\n";

$patient1 = $patients->insert([
    'nik' => '3171012345678901',
    'name' => 'Budi Santoso',
    'birth_date' => '1985-05-15',
    'gender' => 'L',
    'blood_type' => 'A+',
    'phone' => '+6285512345678',
    'emergency_contact' => '+6285512345679',
    'insurance_id' => 'INS-001234',
    'insurance_provider' => 'BPJS Kesehatan',
]);
$patient2 = $patients->insert([
    'nik' => '3171098765432109',
    'name' => 'Siti Aminah',
    'birth_date' => '1990-08-22',
    'gender' => 'P',
    'blood_type' => 'O+',
    'phone' => '+6285598765432',
    'emergency_contact' => '+6285598765433',
    'insurance_id' => 'INS-005678',
    'insurance_provider' => 'Prudential',
]);
echo "- Patients: Budi Santoso, Siti Aminah\n\n";

// ============================================
// Create Appointments (with cross-database refs)
// ============================================

echo "8. Create Appointments with Cross-Database References\n";
echo "-----------------------------------------------------\n";

$apt1 = $appointments->insert([
    'patient_id' => $patient1, // master DB
    'doctor_id' => $doctor1, // HR DB
    'department_id' => $dept1, // master DB
    'appointment_date' => date('Y-m-d'),
    'appointment_time' => '10:00',
    'type' => 'consultation',
    'status' => 'scheduled',
    'room_id' => $room1, // master DB
    'notes' => 'Heart checkup rutin',
]);
echo "- Appointment: Budi Santoso -> Dr. Ahmad Fauzi (Cardiology)\n";

$apt2 = $appointments->insert([
    'patient_id' => $patient2, // master DB
    'doctor_id' => $doctor2, // HR DB
    'department_id' => $dept2, // master DB
    'appointment_date' => date('Y-m-d'),
    'appointment_time' => '14:00',
    'type' => 'follow_up',
    'status' => 'scheduled',
    'notes' => 'Follow up pemeriksaan umum',
]);
echo "- Appointment: Siti Aminah -> Dr. Sarah Melinda (General)\n\n";

// ============================================
// Medical Records with Multi-Database References
// ============================================

echo "9. Create Medical Records\n";
echo "-------------------------\n";

$record1 = $medicalRecords->insert([
    'patient_id' => $patient1, // master DB
    'doctor_id' => $doctor1, // HR DB
    'appointment_id' => $apt1, // Transaction DB
    'department_id' => $dept1, // master DB
    'visit_date' => date('Y-m-d'),
    'chief_complaint' => 'Nyeri dada saat aktivitas',
    'diagnosis' => 'Hypertensi grade 1',
    'treatment' => 'Amlodipine 5mg 1x1, Diet rendah garam',
    'prescription' => [
        ['medicine' => 'Amlodipine 5mg', 'dosage' => '1x1', 'duration' => '30 hari'],
    ],
    'vital_signs' => [
        'blood_pressure' => '140/90',
        'heart_rate' => 82,
        'temperature' => 36.6,
        'weight' => 75,
    ],
    'status' => 'active',
]);
echo "- Medical Record: Budi Santoso - Hypertensi grade 1\n\n";

// ============================================
// Billing
// ============================================

echo "10. Create Billing\n";
echo "------------------\n";

$bill1 = $billing->insert([
    'patient_id' => $patient1, // master DB
    'record_id' => $record1, // Transaction DB
    'items' => [
        ['description' => 'Konsultasi Dokter Spesialis', 'amount' => 200000],
        ['description' => 'Pemeriksaan Darah Lengkap', 'amount' => 150000],
        ['description' => 'EKG', 'amount' => 100000],
        ['description' => 'Obat Amlodipine 5mg (30 tablet)', 'amount' => 45000],
    ],
    'total_amount' => 495000,
    'discount' => 0,
    'insurance_claim' => 371250,
    'final_amount' => 123750,
    'status' => 'insurance',
    'payment_method' => 'insurance',
]);
echo "- Billing: Budi Santoso - Total Rp 495.000\n";
echo "  BPJS Cover: Rp 371.250\n";
echo "  Patient Pay: Rp 123.750\n\n";

// ============================================
// Population Examples (Relasi Data)
// ============================================

echo "11. Population Examples (Relasi Data)\n";
echo "-------------------------------------\n";

// Simple population: Appointments dengan Patient
echo "a. Appointments dengan Patient (cross-database population):\n";
$appointmentsWithPatients = $appointments->find()->toArray();
// Note: Cross-database population requires manual lookup in this implementation
foreach ($appointmentsWithPatients as $apt) {
    $patient = $patients->findOne(['_id' => $apt['patient_id']]);
    echo "  - {$apt['appointment_date']} {$apt['appointment_time']}: \n";
    echo '    Patient: ' . ($patient['name'] ?? 'Unknown') . "\n";
}

// Population: Medical Records dengan Doctor
echo "\nb. Medical Records dengan Doctor:\n";
$records = $medicalRecords->find()->toArray();
foreach ($records as $rec) {
    $doctor = $doctors->findOne(['_id' => $rec['doctor_id']]);
    $patient = $patients->findOne(['_id' => $rec['patient_id']]);
    echo "  - Diagnosis: {$rec['diagnosis']}\n";
    echo '    Doctor: ' . ($doctor['name'] ?? 'Unknown') . "\n";
    echo '    Patient: ' . ($patient['name'] ?? 'Unknown') . "\n";
}

// Population: Billing dengan Patient
echo "\nc. Billing dengan Patient:\n";
$bills = $billing->find()->toArray();
foreach ($bills as $bill) {
    $patient = $patients->findOne(['_id' => $bill['patient_id']]);
    echo '  - Amount: Rp ' . number_format($bill['final_amount'], 0, ',', '.') . "\n";
    echo '    Patient: ' . ($patient['name'] ?? 'Unknown') . "\n";
    echo "    Status: {$bill['status']}\n";
}

// ============================================
// Cross-Database Query Examples
// ============================================

echo "\n12. Cross-Database Query Examples\n";
echo "-----------------------------------\n";

// Find all active patients
echo "a. All Active Patients:\n";
$allPatients = $patients->find();
foreach ($allPatients as $p) {
    echo "  - {$p['name']} (NIK: {$p['nik']})\n";
}

// Find all active doctors
echo "\nb. All Active Doctors:\n";
$activeDoctors = $doctors->find(['status' => 'active']);
foreach ($activeDoctors as $d) {
    echo "  - {$d['name']} ({$d['specialization']})\n";
}

// Find appointments by department
echo "\nc. Appointments by Department:\n";
$cardioAppointments = $appointments->find(['department_id' => $dept1]);
foreach ($cardioAppointments as $apt) {
    echo "  - {$apt['appointment_date']} {$apt['appointment_time']}: {$apt['type']}\n";
}

// Find available rooms
echo "\nd. Available Rooms:\n";
$availableRooms = $rooms->find(['status' => 'available']);
foreach ($availableRooms as $r) {
    echo "  - {$r['name']} ({$r['type']}) - Capacity: {$r['capacity']}\n";
}

// ============================================
// Summary Statistics
// ============================================

echo "\n=== Summary Statistics ===\n";
echo "Master DB:\n";
echo '  - Patients: ' . $patients->count() . "\n";
echo '  - Departments: ' . $departments->count() . "\n";
echo '  - Rooms: ' . $rooms->count() . "\n\n";

echo "HR DB:\n";
echo '  - Doctors: ' . $doctors->count() . "\n";
echo '  - Nurses: ' . $nurses->count() . "\n\n";

echo "Transaction DB:\n";
echo '  - Appointments: ' . $appointments->count() . "\n";
echo '  - Medical Records: ' . $medicalRecords->count() . "\n";
echo '  - Billing Records: ' . $billing->count() . "\n\n";

// ============================================
// Cleanup
// ============================================

echo "=== Cleanup ===\n";
// Close connections first to release file locks
@Database::closeAll();

// Try to drop databases (suppress Windows file lock warnings)
@$masterDb->drop();
@$transactionDb->drop();
@$hrDb->drop();

@$masterClient->close();
@$transactionClient->close();
@$hrClient->close();

echo "All databases cleaned.\n";
