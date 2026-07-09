<?php

/**
 * Contoh 23: ACL Model — Access Control List & Schema Relation Type
 *
 * Implementasi ACL (Access Control List) menggunakan BangronDB.
 * Berbeda dengan RBAC (Contoh 22), ACL memberikan permission langsung
 * pada RESOURCE tertentu, bukan berdasarkan role.
 *
 * Mencakup:
 *   - Perbandaan konsep RBAC vs ACL
 *   - Schema dengan type 'relation' untuk referensi antar collection
 *   - ACL pattern: User → Resource → Permission (granular per-resource)
 *   - Group-based ACL (user groups untuk mempermudah manajemen)
 *   - Hook untuk auto-validasi referensi (foreign key emulation)
 *   - Populate dengan dot-notation pada relasi
 *   - Aggregation untuk audit trail
 *
 * CATATAN PENTING tentang type 'relation':
 *   BangronDB adalah document database tanpa foreign key bawaan.
 *   Type 'relation' di schema HANYA memvalidasi bahwa nilainya string
 *   (biasanya berisi _id dokumen lain). Tidak ada ON DELETE CASCADE
 *   atau referential integrity otomatis. Gunakan HOOKS untuk
 *   meniru perilaku foreign key jika diperlukan.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;
use BangronDB\Exceptions\ValidationException;

sep('Contoh 23: ACL Model & Schema Relation Type');

// ============================================================
//  1. RBAC vs ACL — PENJELASAN
// ============================================================
sub('1. RBAC vs ACL — Perbedaan Konsep');

echo <<<EXPLAIN

RBAC (Role-Based Access Control) — Contoh 22:
  User → Role → Permission
  "Budi adalah ADMIN, jadi bisa CREATE USER"
  ✅ Cocok untuk: sistem dengan role yang jelas & stabil

ACL (Access Control List) — Contoh ini:
  User → Resource → Permission
  "Budi bisa EDIT pada Dokumen #123"
  ✅ Cocok untuk: sistem kolaborasi, file sharing, CMS, SaaS multi-tenant
  ✅ Lebih granular: permission per-instance, bukan per-tipe

Tipe Schema 'relation':
  Hanya validasi string (ID referensi). Tidak ada FK otomatis.
  Gunakan hook untuk validasi referensi lintas collection.

EXPLAIN;

// ============================================================
//  2. INISIALISASI
// ============================================================
sub('2. Inisialisasi Database & Collection');

$client = createIsolatedClient('example23');
$db     = $client->createDB('acl_app');

// Collection inti ACL
$users      = $db->createCollection('users');
$groups     = $db->createCollection('groups');       // user groups
$userGroups = $db->createCollection('user_groups');  // junction: user ↔ group
$resources  = $db->createCollection('resources');    // dokumen/file/page yang dilindungi
$acls       = $db->createCollection('acls');         // access control entries

echo "6 collection dibuat: users, groups, user_groups, resources, acls\n";
echo "  + ACL: permissions langsung per user per resource\n";
echo "  + Groups: mengelola banyak user sekaligus\n";

// ============================================================
//  3. SCHEMA — MENGGUNAKAN TYPE 'relation'
// ============================================================
sub('3. Schema dengan type "relation"');

// --- Users ---
$users->setSchema([
    'username'   => ['type' => 'string', 'required' => true, 'min' => 3, 'max' => 50, 'unique' => true],
    'email'      => ['type' => 'email',  'required' => true, 'unique' => true],
    'password'   => ['type' => 'string', 'required' => true, 'min' => 8],
    'full_name'  => ['type' => 'string', 'required' => true, 'min' => 1, 'max' => 100],
    'is_active'  => ['type' => 'bool',   'required' => true],
]);
$users->saveConfiguration();

// --- Groups (misal: tim, departemen) ---
$groups->setSchema([
    'name'        => ['type' => 'string', 'required' => true, 'min' => 2, 'max' => 50, 'unique' => true],
    'display_name'=> ['type' => 'string', 'required' => true, 'min' => 2, 'max' => 100],
    'description' => ['type' => 'string', 'max' => 500],
]);
$groups->saveConfiguration();

// --- User Groups (junction) ---
// type 'relation' di sini: user_id dan group_id menyimpan ID string
// dari collection lain. BangronDB TIDAK memvalidasi apakah ID tersebut
// benar-benar ada — gunakan hook untuk itu.
$userGroups->setSchema([
    'user_id'  => ['type' => 'relation', 'required' => true, 'description' => 'Reference ke users._id'],
    'group_id' => ['type' => 'relation', 'required' => true, 'description' => 'Reference ke groups._id'],
]);
$userGroups->saveConfiguration();

// --- Resources (entitas yang dilindungi ACL) ---
$resources->setSchema([
    'name'        => ['type' => 'string', 'required' => true, 'min' => 1, 'max' => 200],
    'type'        => ['type' => 'string', 'required' => true, 'enum' => ['document', 'folder', 'page', 'api_endpoint', 'report']],
    'owner_id'    => ['type' => 'relation', 'required' => true, 'description' => 'Reference ke users._id (pemilik)'],
    'parent_id'   => ['type' => 'relation', 'description' => 'Reference ke resources._id (folder induk)'],
    'status'      => ['type' => 'string', 'required' => true, 'enum' => ['draft', 'published', 'archived']],
    'content'     => ['type' => 'string'],
]);
$resources->saveConfiguration();

// --- ACLs (Access Control Entries) ---
// Inti dari ACL: siapa punya akses apa ke resource mana
$acls->setSchema([
    'resource_id'    => ['type' => 'relation', 'required' => true, 'description' => 'Reference ke resources._id'],
    'grantee_type'   => ['type' => 'string',  'required' => true, 'enum' => ['user', 'group']],
    'grantee_id'     => ['type' => 'relation', 'required' => true, 'description' => 'Reference ke users._id ATAU groups._id'],
    'permission'     => ['type' => 'string',  'required' => true, 'enum' => ['read', 'write', 'admin']],
    'granted_by'     => ['type' => 'relation', 'required' => true, 'description' => 'Reference ke users._id (siapa yang memberi akses)'],
]);
$acls->saveConfiguration();

echo "Schema selesai dikonfigurasi:\n";
echo "  - users:          5 fields\n";
echo "  - groups:         3 fields\n";
echo "  - user_groups:    2 fields (relation ×2)\n";
echo "  - resources:      6 fields (relation ×2: owner_id, parent_id)\n";
echo "  - acls:           5 fields (relation ×3: resource_id, grantee_id, granted_by)\n";
echo "\nCatatan: type 'relation' hanya validasi string. Referensi integrity harus\n";
echo "ditangani via hook atau aplikasi layer.\n";

// ============================================================
//  4. HOOKS — AUTO TIMESTAMPS + VALIDASI REFERENSI
// ============================================================
sub('4. Hooks — Auto Timestamps & Referensi Validation');

// Auto timestamps untuk semua collection
foreach ([$users, $groups, $userGroups, $resources, $acls] as $coll) {
    $coll->on('beforeInsert', function ($doc) {
        $doc['created_at'] = date('c');
        $doc['updated_at'] = date('c');
        return $doc;
    });
    $coll->on('beforeUpdate', function ($criteria, $data) {
        if (!isset($data['$set'])) $data['$set'] = [];
        $data['$set']['updated_at'] = date('c');
        return [$criteria, $data];
    });
}

// Hook: hash password user
$users->on('beforeInsert', function ($doc) {
    if (isset($doc['password'])) {
        $doc['password_hash'] = password_hash($doc['password'], PASSWORD_BCRYPT);
        unset($doc['password']);
    }
    return $doc;
});

// Hook: validasi referensi ACL (emulasi foreign key)
// Memastikan resource_id, grantee_id, dan granted_by benar-benar ada
$acls->on('beforeInsert', function ($doc) use ($db) {
    $errors = [];

    // Validasi resource_id
    if (!$db->selectCollection('resources')->findOne(['_id' => $doc['resource_id']])) {
        $errors[] = "resource_id '{$doc['resource_id']}' tidak ditemukan";
    }

    // Validasi grantee berdasarkan tipe
    if ($doc['grantee_type'] === 'user') {
        if (!$db->selectCollection('users')->findOne(['_id' => $doc['grantee_id']])) {
            $errors[] = "grantee (user) '{$doc['grantee_id']}' tidak ditemukan";
        }
    } elseif ($doc['grantee_type'] === 'group') {
        if (!$db->selectCollection('groups')->findOne(['_id' => $doc['grantee_id']])) {
            $errors[] = "grantee (group) '{$doc['grantee_id']}' tidak ditemukan";
        }
    }

    // Validasi granted_by
    if (!$db->selectCollection('users')->findOne(['_id' => $doc['granted_by']])) {
        $errors[] = "granted_by '{$doc['granted_by']}' tidak ditemukan";
    }

    if (!empty($errors)) {
        // Throw exception untuk membatalkan insert
        throw new \InvalidArgumentException('Referensi tidak valid: ' . implode('; ', $errors));
    }

    return $doc;
});

echo "Hooks terpasang:\n";
echo "  - Auto timestamps (5 collection)\n";
echo "  - Password hashing (users)\n";
echo "  - Foreign key validation (acls)\n";

// ============================================================
//  5. INDEXING
// ============================================================
sub('5. Indexing');

$users->createIndex('email');
$users->createIndex('username');
$groups->createIndex('name');
$resources->createIndex('owner_id');
$resources->createIndex('type');
$resources->createIndex('parent_id');
$acls->createIndex('resource_id');
$acls->createIndex('grantee_type');
$acls->createIndex('grantee_id');
$acls->createIndex('permission');

echo "10 index dibuat untuk query ACL yang efisien\n";

// ============================================================
//  6. SEED DATA — USERS
// ============================================================
sub('6. Seed Data — Users');

$budiId  = $users->insert([
    'username'  => 'budi',
    'email'     => 'budi@company.com',
    'password'  => 'BudiSecure123!',
    'full_name' => 'Budi Santoso',
    'is_active' => true,
]);

$aniId   = $users->insert([
    'username'  => 'ani',
    'email'     => 'ani@company.com',
    'password'  => 'AniSecure456!',
    'full_name' => 'Ani Rahayu',
    'is_active' => true,
]);

$citraId = $users->insert([
    'username'  => 'citra',
    'email'     => 'citra@company.com',
    'password'  => 'CitraSecure789!',
    'full_name' => 'Citra Dewi',
    'is_active' => true,
]);

$daniId  = $users->insert([
    'username'  => 'dani',
    'email'     => 'dani@company.com',
    'password'  => 'DaniSecure012!',
    'full_name' => 'Dani Pratama',
    'is_active' => true,
]);

echo "4 users dibuat\n";

// ============================================================
//  7. SEED DATA — GROUPS
// ============================================================
sub('7. Seed Data — Groups');

$engineeringId = $groups->insert([
    'name'         => 'engineering',
    'display_name' => 'Tim Engineering',
    'description'  => 'Tim pengembang dan teknis',
]);

$marketingId = $groups->insert([
    'name'         => 'marketing',
    'display_name' => 'Tim Marketing',
    'description'  => 'Tim pemasaran dan konten',
]);

$managementId = $groups->insert([
    'name'         => 'management',
    'display_name' => 'Tim Manajemen',
    'description'  => 'Manajer dan kepala divisi',
]);

echo "3 groups dibuat: engineering, marketing, management\n";

// ============================================================
//  8. USER-GROUP ASSIGNMENT
// ============================================================
sub('8. User-Group Assignment');

// Budi & Ani → engineering
$userGroups->insert(['user_id' => $budiId,  'group_id' => $engineeringId]);
$userGroups->insert(['user_id' => $aniId,   'group_id' => $engineeringId]);

// Citra → marketing
$userGroups->insert(['user_id' => $citraId, 'group_id' => $marketingId]);

// Dani & Ani → management
$userGroups->insert(['user_id' => $daniId,  'group_id' => $managementId]);
$userGroups->insert(['user_id' => $aniId,   'group_id' => $managementId]);

echo "Assignment:\n";
echo "  - budi  → engineering\n";
echo "  - ani   → engineering + management\n";
echo "  - citra → marketing\n";
echo "  - dani  → management\n";

// ============================================================
//  9. SEED DATA — RESOURCES
// ============================================================
sub('9. Seed Data — Resources');

// Folder induk
$folderProjId = $resources->insert([
    'name'      => 'Project Alpha',
    'type'      => 'folder',
    'owner_id'  => $budiId,
    'parent_id' => null,
    'status'    => 'published',
]);

$folderMktId = $resources->insert([
    'name'      => 'Marketing Campaign Q3',
    'type'      => 'folder',
    'owner_id'  => $citraId,
    'parent_id' => null,
    'status'    => 'published',
]);

// Dokumen di dalam folder
$doc1Id = $resources->insert([
    'name'      => 'Spesifikasi Teknis v2',
    'type'      => 'document',
    'owner_id'  => $budiId,
    'parent_id' => $folderProjId,
    'status'    => 'published',
    'content'   => 'Spesifikasi lengkap untuk Project Alpha...',
]);

$doc2Id = $resources->insert([
    'name'      => 'Arsitektur Database',
    'type'      => 'document',
    'owner_id'  => $aniId,
    'parent_id' => $folderProjId,
    'status'    => 'draft',
    'content'   => 'Desain arsitektur database baru...',
]);

$doc3Id = $resources->insert([
    'name'      => 'Budget Plan Q3',
    'type'      => 'document',
    'owner_id'  => $citraId,
    'parent_id' => $folderMktId,
    'status'    => 'published',
    'content'   => 'Rencana anggaran marketing Q3...',
]);

$page1Id = $resources->insert([
    'name'      => 'Dashboard Analytics',
    'type'      => 'page',
    'owner_id'  => $aniId,
    'parent_id' => null,
    'status'    => 'published',
    'content'   => 'Halaman dashboard untuk analytics...',
]);

$report1Id = $resources->insert([
    'name'      => 'Laporan Penjualan Juni',
    'type'      => 'report',
    'owner_id'  => $daniId,
    'parent_id' => null,
    'status'    => 'published',
    'content'   => 'Data penjualan bulan Juni 2025...',
]);

echo "6 resources dibuat:\n";
echo "  Folders:  Project Alpha, Marketing Campaign Q3\n";
echo "  Docs:     Spesifikasi Teknis v2, Arsitektur Database, Budget Plan Q3\n";
echo "  Pages:    Dashboard Analytics\n";
echo "  Reports:  Laporan Penjualan Juni\n";

// ============================================================
//  10. ACL RULES — INTI SISTEM
// ============================================================
sub('10. ACL Rules — Inti Sistem');

// ACL untuk Project Alpha (folder)
// Owner (budi) otomatis admin — tapi kita tetap buat explicit ACL
$acls->insert(['resource_id' => $folderProjId, 'grantee_type' => 'user', 'grantee_id' => $budiId,  'permission' => 'admin',   'granted_by' => $budiId]);
$acls->insert(['resource_id' => $folderProjId, 'grantee_type' => 'user', 'grantee_id' => $aniId,   'permission' => 'write',  'granted_by' => $budiId]);
$acls->insert(['resource_id' => $folderProjId, 'grantee_type' => 'group','grantee_id' => $engineeringId, 'permission' => 'read', 'granted_by' => $budiId]);

// ACL untuk Spesifikasi Teknis (doc di dalam Project Alpha)
$acls->insert(['resource_id' => $doc1Id, 'grantee_type' => 'user', 'grantee_id' => $budiId,  'permission' => 'admin',  'granted_by' => $budiId]);
$acls->insert(['resource_id' => $doc1Id, 'grantee_type' => 'user', 'grantee_id' => $aniId,   'permission' => 'write', 'granted_by' => $budiId]);
$acls->insert(['resource_id' => $doc1Id, 'grantee_type' => 'user', 'grantee_id' => $daniId,  'permission' => 'read',  'granted_by' => $budiId]);

// ACL untuk Arsitektur Database — hanya budi & ani
$acls->insert(['resource_id' => $doc2Id, 'grantee_type' => 'user', 'grantee_id' => $budiId, 'permission' => 'admin',  'granted_by' => $aniId]);
$acls->insert(['resource_id' => $doc2Id, 'grantee_type' => 'user', 'grantee_id' => $aniId,  'permission' => 'write', 'granted_by' => $aniId]);

// ACL untuk Marketing Campaign Q3
$acls->insert(['resource_id' => $folderMktId, 'grantee_type' => 'user', 'grantee_id' => $citraId, 'permission' => 'admin',   'granted_by' => $citraId]);
$acls->insert(['resource_id' => $folderMktId, 'grantee_type' => 'group','grantee_id' => $marketingId,  'permission' => 'write', 'granted_by' => $citraId]);
$acls->insert(['resource_id' => $folderMktId, 'grantee_type' => 'user', 'grantee_id' => $daniId,  'permission' => 'read',  'granted_by' => $citraId]);

// ACL untuk Budget Plan Q3
$acls->insert(['resource_id' => $doc3Id, 'grantee_type' => 'user', 'grantee_id' => $citraId, 'permission' => 'admin',  'granted_by' => $citraId]);
$acls->insert(['resource_id' => $doc3Id, 'grantee_type' => 'user', 'grantee_id' => $daniId,  'permission' => 'read',  'granted_by' => $citraId]);

// ACL untuk Dashboard Analytics
$acls->insert(['resource_id' => $page1Id, 'grantee_type' => 'user', 'grantee_id' => $aniId,   'permission' => 'admin', 'granted_by' => $aniId]);
$acls->insert(['resource_id' => $page1Id, 'grantee_type' => 'group','grantee_id' => $managementId,  'permission' => 'read', 'granted_by' => $aniId]);
$acls->insert(['resource_id' => $page1Id, 'grantee_type' => 'user', 'grantee_id' => $budiId,  'permission' => 'read', 'granted_by' => $aniId]);

// ACL untuk Laporan Penjualan
$acls->insert(['resource_id' => $report1Id, 'grantee_type' => 'user', 'grantee_id' => $daniId,  'permission' => 'admin', 'granted_by' => $daniId]);
$acls->insert(['resource_id' => $report1Id, 'grantee_type' => 'group','grantee_id' => $managementId,  'permission' => 'read', 'granted_by' => $daniId]);
$acls->insert(['resource_id' => $report1Id, 'grantee_type' => 'user', 'grantee_id' => $citraId, 'permission' => 'read', 'granted_by' => $daniId]);

echo "20 ACL rules dibuat\n";
echo "  - 14 ACL untuk user spesifik\n";
echo "  - 6 ACL untuk group\n";
echo "\nPerhatikan: setiap resource punya ACL berbeda!\n";
echo "  Ini beda dengan RBAC dimana permission ditentukan oleh role.\n";

// ============================================================
//  11. DEMONSTRASI VALIDASI REFERENSI (HOOK)
// ============================================================
sub('11. Validasi Referensi via Hook (FK Emulation)');

echo "11a. Coba insert ACL dengan resource_id tidak valid:\n";
try {
    $acls->insert([
        'resource_id'  => 'nonexistent-id-12345',
        'grantee_type' => 'user',
        'grantee_id'   => $budiId,
        'permission'   => 'read',
        'granted_by'   => $budiId,
    ]);
    echo "  UNEXPECTED: seharusnya gagal!\n";
} catch (\InvalidArgumentException $e) {
    echo "  DITOLAK: " . substr($e->getMessage(), 0, 80) . "\n";
}

echo "\n11b. Coba insert ACL dengan grantee_id user tidak valid:\n";
try {
    $acls->insert([
        'resource_id'  => $doc1Id,
        'grantee_type' => 'user',
        'grantee_id'   => 'nonexistent-user-99999',
        'permission'   => 'read',
        'granted_by'   => $budiId,
    ]);
    echo "  UNEXPECTED: seharusnya gagal!\n";
} catch (\InvalidArgumentException $e) {
    echo "  DITOLAK: " . substr($e->getMessage(), 0, 80) . "\n";
}

echo "\n11c. Coba insert ACL dengan grantee group tidak valid:\n";
try {
    $acls->insert([
        'resource_id'  => $doc1Id,
        'grantee_type' => 'group',
        'grantee_id'   => 'nonexistent-group-99999',
        'permission'   => 'read',
        'granted_by'   => $budiId,
    ]);
    echo "  UNEXPECTED: seharusnya gagal!\n";
} catch (\InvalidArgumentException $e) {
    echo "  DITOLAK: " . substr($e->getMessage(), 0, 80) . "\n";
}

echo "\n→ Hook berhasil meniru foreign key constraint!\n";

// ============================================================
//  12. QUERY PATTERNS — ACL
// ============================================================
sub('12. Query Patterns — ACL');

// 12a. Semua ACL untuk resource tertentu
echo "12a. ACL untuk 'Spesifikasi Teknis v2':\n";
$doc1Acls = $acls->find(['resource_id' => $doc1Id])->toArray();
$doc1Acls = $acls->populate($doc1Acls, 'grantee_id', 'users', '_id', 'grantee');
foreach ($doc1Acls as $acl) {
    $granteeName = $acl['grantee']['username'] ?? $acl['grantee_id'];
    echo "  - {$granteeName} ({$acl['grantee_type']}): {$acl['permission']}\n";
}

// 12b. Semua resource yang bisa diakses user tertentu
echo "\n12b. Resource yang bisa di-READ oleh ani:\n";
// Langkah 1: cari semua group ani
$aniGroupIds = array_column(
    $userGroups->find(['user_id' => $aniId])->toArray(),
    'group_id'
);

// Langkah 2: cari semua ACL dimana ani user ATAU ani member group
$aniAclCriteria = [
    '$or' => [
        ['grantee_type' => 'user',  'grantee_id' => $aniId],
        ['grantee_type' => 'group', 'grantee_id' => ['$in' => $aniGroupIds]],
    ],
    'permission' => ['$in' => ['read', 'write', 'admin']],
];
$aniAcls = $acls->find($aniAclCriteria)->toArray();

// Langkah 3: dapatkan resource_id unik
$aniResourceIds = array_unique(array_column($aniAcls, 'resource_id'));

// Langkah 4: populate resources
$aniResources = $resources->find(['_id' => ['$in' => $aniResourceIds]])
    ->sort(['type' => 1, 'name' => 1])
    ->toArray();

foreach ($aniResources as $r) {
    // cari permission tertinggi untuk resource ini
    $resourceAcls = array_filter($aniAcls, fn($a) => $a['resource_id'] === $r['_id']);
    $perms = array_column($resourceAcls, 'permission');
    // admin > write > read
    $best = in_array('admin', $perms) ? 'ADMIN' : (in_array('write', $perms) ? 'WRITE' : 'READ');
    echo "  - [{$r['type']}] {$r['name']} → {$best}\n";
}

// 12c. Semua ACL yang diberikan oleh user tertentu
echo "\n12c. ACL yang diberikan oleh budi:\n";
$budiGranted = $acls->find(['granted_by' => $budiId])->toArray();
$budiGranted = $acls->populate($budiGranted, 'resource_id', 'resources', '_id', 'resource');
$budiGranted = $acls->populate($budiGranted, 'grantee_id', 'users', '_id', 'grantee');
foreach ($budiGranted as $acl) {
    $rName = $acl['resource']['name'] ?? '?';
    $gName = $acl['grantee']['username'] ?? $acl['grantee_id'];
    echo "  - {$rName} → {$gName} ({$acl['permission']})\n";
}

// ============================================================
//  13. HIERARCHY — FOLDER INHERITANCE
// ============================================================
sub('13. ACL Hierarchy — Folder Inheritance');

/**
 * Contoh logika inheritance: jika user punya ACL di folder,
 * maka secara logis user juga punya akses ke sub-dokumen.
 * Ini TIDAK otomatis — harus diimplementasikan di aplikasi layer.
 */
echo "Implementasi inheritance (aplikasi layer):\n\n";

function checkAccess(
    \BangronDB\Database $db,
    string $userId,
    string $resourceId,
    string $requiredPermission = 'read'
): array {
    $acls      = $db->selectCollection('acls');
    $resources = $db->selectCollection('resources');
    $userGroups = $db->selectCollection('user_groups');

    // Dapatkan semua group user
    $groupIds = array_column(
        $userGroups->find(['user_id' => $userId])->toArray(),
        'group_id'
    );

    // Cari ACL langsung di resource ini
    $directAcls = $acls->find([
        'resource_id' => $resourceId,
        '$or' => [
            ['grantee_type' => 'user',  'grantee_id' => $userId],
            ['grantee_type' => 'group', 'grantee_id' => ['$in' => $groupIds]],
        ],
    ])->toArray();

    // Cek apakah owner (selalu admin)
    $resource = $resources->findOne(['_id' => $resourceId]);
    $isOwner = ($resource['owner_id'] ?? null) === $userId;

    if ($isOwner) {
        return ['granted' => true, 'level' => 'admin', 'source' => 'owner'];
    }

    $permHierarchy = ['read' => 1, 'write' => 2, 'admin' => 3];
    $requiredLevel = $permHierarchy[$requiredPermission] ?? 0;

    // Cek ACL langsung
    foreach ($directAcls as $acl) {
        $aclLevel = $permHierarchy[$acl['permission']] ?? 0;
        if ($aclLevel >= $requiredLevel) {
            return [
                'granted' => true,
                'level'   => $acl['permission'],
                'source'  => "direct ({$acl['grantee_type']})",
            ];
        }
    }

    // INHERITANCE: cek ACL di parent folder (rekursif naik)
    $parentId = $resource['parent_id'] ?? null;
    if ($parentId) {
        $parentResult = checkAccess($db, $userId, $parentId, $requiredPermission);
        if ($parentResult['granted']) {
            $parentResult['source'] = 'inherited from parent → ' . $parentResult['source'];
            return $parentResult;
        }
    }

    return ['granted' => false, 'level' => null, 'source' => 'no matching ACL'];
}

// Test: Ani akses Spesifikasi Teknis (doc di dalam folder)
$tests = [
    ['user' => 'ani',   'userId' => $aniId,   'resource' => 'Spesifikasi Teknis v2',   'resourceId' => $doc1Id,   'perm' => 'write'],
    ['user' => 'dani',  'userId' => $daniId,  'resource' => 'Spesifikasi Teknis v2',   'resourceId' => $doc1Id,   'perm' => 'read'],
    ['user' => 'dani',  'userId' => $daniId,  'resource' => 'Spesifikasi Teknis v2',   'resourceId' => $doc1Id,   'perm' => 'write'], // harus ditolak
    ['user' => 'citra', 'userId' => $citraId, 'resource' => 'Spesifikasi Teknis v2',   'resourceId' => $doc1Id,   'perm' => 'read'], // tidak punya ACL
    ['user' => 'budi',  'userId' => $budiId,  'resource' => 'Arsitektur Database',     'resourceId' => $doc2Id,   'perm' => 'admin'],
    ['user' => 'ani',   'userId' => $aniId,   'resource' => 'Arsitektur Database',     'resourceId' => $doc2Id,   'perm' => 'write'],
    ['user' => 'dani',  'userId' => $daniId,  'resource' => 'Dashboard Analytics',      'resourceId' => $page1Id,  'perm' => 'read'], // via management group
    ['user' => 'citra', 'userId' => $citraId, 'resource' => 'Laporan Penjualan Juni',  'resourceId' => $report1Id, 'perm' => 'read'],
];

foreach ($tests as $t) {
    $result = checkAccess($db, $t['userId'], $t['resourceId'], $t['perm']);
    $status = $result['granted'] ? 'GRANTED' : 'DENIED';
    $level  = $result['level'] ? " ({$result['level']})" : '';
    echo "  {$t['user']} → {$t['perm']} on '{$t['resource']}': {$status}{$level}";
    echo " [{$result['source']}]\n";
}

// ============================================================
//  14. AGGREGATION — AUDIT & STATISTIK
// ============================================================
sub('14. Aggregation — Audit & Statistik');

// 14a. Distribusi permission
echo "14a. Distribusi ACL per permission:\n";
$permDist = $acls->aggregate([
    ['$group' => [
        '_id'   => '$permission',
        'count' => ['$sum' => 1],
    ]],
    ['$sort'  => ['count' => -1]],
]);
foreach ($permDist as $stat) {
    echo "  - {$stat['_id']}: {$stat['count']} ACL(s)\n";
}

// 14b. Distribusi per grantee_type
echo "\n14b. Distribusi per grantee type:\n";
$typeDist = $acls->aggregate([
    ['$group' => [
        '_id'   => '$grantee_type',
        'count' => ['$sum' => 1],
    ]],
]);
foreach ($typeDist as $stat) {
    echo "  - {$stat['_id']}: {$stat['count']} ACL(s)\n";
}

// 14c. Resource dengan paling banyak ACL
echo "\n14c. Resource dengan ACL terbanyak:\n";
$resourceAclCounts = $acls->aggregate([
    ['$group' => [
        '_id'   => '$resource_id',
        'count' => ['$sum' => 1],
    ]],
    ['$sort'  => ['count' => -1]],
    ['$limit' => 5],
]);
$resourceAclCounts = $resources->populate($resourceAclCounts, '_id', 'resources', '_id', 'resource');
foreach ($resourceAclCounts as $stat) {
    echo "  - {$stat['resource']['name']}: {$stat['count']} ACL(s)\n";
}

// 14d. User yang memberikan paling banyak ACL
echo "\n14d. User paling aktif memberikan akses:\n";
$grantors = $acls->aggregate([
    ['$group' => [
        '_id'   => '$granted_by',
        'count' => ['$sum' => 1],
    ]],
    ['$sort'  => ['count' => -1]],
]);
$grantors = $users->populate($grantors, '_id', 'users', '_id', 'user');
foreach ($grantors as $stat) {
    echo "  - {$stat['user']['full_name']}: memberikan {$stat['count']} ACL(s)\n";
}

// 14e. Resource per type
echo "\n14e. Jumlah resource per type:\n";
$typeStats = $resources->aggregate([
    ['$group' => [
        '_id'   => '$type',
        'count' => ['$sum' => 1],
    ]],
    ['$sort'  => ['count' => -1]],
]);
foreach ($typeStats as $stat) {
    echo "  - {$stat['_id']}: {$stat['count']}\n";
}

// ============================================================
//  15. POPULATE — MULTI-LEVEL RELASI
// ============================================================
sub('15. Populate — Multi-Level Relasi');

// Resources + owner + parent folder
echo "15. Semua dokumen dengan owner dan parent folder:\n";
$docs = $resources->find(['type' => 'document'])->toArray();

// Populate owner
$docs = $resources->populate($docs, 'owner_id', 'users', '_id', 'owner');

// Populate parent folder
$docs = $resources->populate($docs, 'parent_id', 'resources', '_id', 'parent');

foreach ($docs as $d) {
    $ownerName = $d['owner']['full_name'] ?? '?';
    $parentName = $d['parent']['name'] ?? '(root)';
    echo "  - {$d['name']} ({$d['status']})\n";
    echo "    Owner: {$ownerName} | Folder: {$parentName}\n";
}

// ============================================================
//  16. STREAMING + PROJECTION
// ============================================================
sub('16. Streaming — Resource List');

echo "16. Stream semua resources (ringan, tanpa content):\n";
$stream = $resources->stream(null, [
    'projection' => ['name' => 1, 'type' => 1, 'status' => 1, 'owner_id' => 1],
    'sort'       => ['type' => 1, 'name' => 1],
]);
foreach ($stream as $doc) {
    echo "  [{$doc['type']}] {$doc['name']} ({$doc['status']})\n";
}

// ============================================================
//  17. UPDATE & REVOKE ACL
// ============================================================
sub('17. Update & Revoke ACL');

// 17a. Berikan akses write ke dani pada Spesifikasi Teknis
echo "17a. Upgrade dani: read → write pada 'Spesifikasi Teknis v2':\n";
$acls->update(
    ['resource_id' => $doc1Id, 'grantee_type' => 'user', 'grantee_id' => $daniId],
    ['$set' => ['permission' => 'write']]
);
$updated = $acls->findOne([
    'resource_id' => $doc1Id, 'grantee_type' => 'user', 'grantee_id' => $daniId
]);
echo "  Permission sekarang: {$updated['permission']}\n";

// 17b. Verifikasi dengan checkAccess
$result = checkAccess($db, $daniId, $doc1Id, 'write');
echo "  Verifikasi: dani → write on 'Spesifikasi Teknis': " . ($result['granted'] ? 'GRANTED' : 'DENIED') . "\n";

// 17c. Revoke akses (hapus ACL)
echo "\n17c. Revoke akses engineering group dari folder Project Alpha:\n";
$before = $acls->count([
    'resource_id' => $folderProjId,
    'grantee_type' => 'group',
    'grantee_id' => $engineeringId,
]);
$acls->remove([
    'resource_id' => $folderProjId,
    'grantee_type' => 'group',
    'grantee_id' => $engineeringId,
]);
$after = $acls->count([
    'resource_id' => $folderProjId,
    'grantee_type' => 'group',
    'grantee_id' => $engineeringId,
]);
echo "  ACL sebelum revoke: {$before}\n";
echo "  ACL setelah revoke: {$after}\n";

// ============================================================
//  18. ACL HELPER CLASS
// ============================================================
sub('18. ACL Helper Class — Contoh Penggunaan');

/**
 * Helper class untuk operasi ACL sehari-hari.
 * Membungkus BangronDB dalam layer abstraksi bisnis.
 */
class AclService
{
    /** Permission hierarchy (angka lebih tinggi = lebih kuat) */
    private const PERM_LEVELS = ['read' => 1, 'write' => 2, 'admin' => 3];

    public function __construct(
        private readonly \BangronDB\Database $db
    ) {}

    /** Berikan akses user/group ke resource */
    public function grant(string $resourceId, string $granteeType, string $granteeId, string $permission, string $grantedBy): string
    {
        $acls = $this->db->selectCollection('acls');
        return $acls->insert([
            'resource_id'  => $resourceId,
            'grantee_type' => $granteeType,
            'grantee_id'   => $granteeId,
            'permission'   => $permission,
            'granted_by'   => $grantedBy,
        ]);
    }

    /** Revoke akses */
    public function revoke(string $resourceId, string $granteeType, string $granteeId): int
    {
        return $this->db->selectCollection('acls')->remove([
            'resource_id'  => $resourceId,
            'grantee_type' => $granteeType,
            'grantee_id'   => $granteeId,
        ]);
    }

    /** Cek akses (dengan inheritance ke parent) */
    public function checkAccess(string $userId, string $resourceId, string $requiredPermission = 'read'): bool
    {
        $result = $this->getAccessInfo($userId, $resourceId);
        if (!$result['granted']) {
            return false;
        }
        return (self::PERM_LEVELS[$result['level']] ?? 0) >= (self::PERM_LEVELS[$requiredPermission] ?? 0);
    }

    /** Dapatkan info akses lengkap */
    public function getAccessInfo(string $userId, string $resourceId): array
    {
        $acls      = $this->db->selectCollection('acls');
        $resources = $this->db->selectCollection('resources');
        $userGroups = $this->db->selectCollection('user_groups');

        $resource = $resources->findOne(['_id' => $resourceId]);
        if (!$resource) {
            return ['granted' => false, 'level' => null, 'source' => 'resource not found'];
        }

        // Owner selalu admin
        if (($resource['owner_id'] ?? null) === $userId) {
            return ['granted' => true, 'level' => 'admin', 'source' => 'owner'];
        }

        $groupIds = array_column(
            $userGroups->find(['user_id' => $userId])->toArray(),
            'group_id'
        );

        // Cari ACL langsung
        $directAcls = $acls->find([
            'resource_id' => $resourceId,
            '$or' => [
                ['grantee_type' => 'user',  'grantee_id' => $userId],
                ['grantee_type' => 'group', 'grantee_id' => ['$in' => $groupIds]],
            ],
        ])->toArray();

        $bestLevel = 0;
        $bestSource = null;
        foreach ($directAcls as $acl) {
            $level = self::PERM_LEVELS[$acl['permission']] ?? 0;
            if ($level > $bestLevel) {
                $bestLevel = $level;
                $bestSource = "direct ({$acl['grantee_type']})";
            }
        }

        if ($bestLevel > 0) {
            $levelName = array_search($bestLevel, self::PERM_LEVELS);
            return ['granted' => true, 'level' => $levelName, 'source' => $bestSource];
        }

        // Inheritance: cek parent
        $parentId = $resource['parent_id'] ?? null;
        if ($parentId) {
            $parent = $this->getAccessInfo($userId, $parentId);
            if ($parent['granted']) {
                $parent['source'] = 'inherited → ' . $parent['source'];
                return $parent;
            }
        }

        return ['granted' => false, 'level' => null, 'source' => 'no matching ACL'];
    }

    /** Dapatkan semua resource yang bisa diakses user */
    public function getAccessibleResources(string $userId, ?string $permission = null): array
    {
        $acls      = $this->db->selectCollection('acls');
        $resources = $this->db->selectCollection('resources');
        $userGroups = $this->db->selectCollection('user_groups');

        $groupIds = array_column(
            $userGroups->find(['user_id' => $userId])->toArray(),
            'group_id'
        );

        $criteria = [
            '$or' => [
                ['grantee_type' => 'user',  'grantee_id' => $userId],
                ['grantee_type' => 'group', 'grantee_id' => ['$in' => $groupIds]],
            ],
        ];

        if ($permission) {
            $minLevel = self::PERM_LEVELS[$permission] ?? 0;
            $validPerms = array_keys(array_filter(self::PERM_LEVELS, fn($l) => $l >= $minLevel));
            $criteria['permission'] = ['$in' => $validPerms];
        }

        $userAcls = $acls->find($criteria)->toArray();
        $resourceIds = array_unique(array_column($userAcls, 'resource_id'));

        if (empty($resourceIds)) {
            return [];
        }

        return $resources->find(['_id' => ['$in' => $resourceIds]])
            ->sort(['type' => 1, 'name' => 1])
            ->toArray();
    }

    /** Dapatkan semua ACL untuk resource tertentu */
    public function getResourceAcls(string $resourceId): array
    {
        $acls   = $this->db->selectCollection('acls');
        $users  = $this->db->selectCollection('users');
        $groups = $this->db->selectCollection('groups');

        $entries = $acls->find(['resource_id' => $resourceId])
            ->sort(['grantee_type' => 1, 'permission' => -1])
            ->toArray();

        // Populate grantee berdasarkan tipe
        foreach ($entries as &$entry) {
            if ($entry['grantee_type'] === 'user') {
                $grantee = $users->findOne(['_id' => $entry['grantee_id']], ['username' => 1, 'full_name' => 1]);
                $entry['grantee_name'] = $grantee['full_name'] ?? $entry['grantee_id'];
            } else {
                $grantee = $groups->findOne(['_id' => $entry['grantee_id']], ['display_name' => 1]);
                $entry['grantee_name'] = $grantee['display_name'] ?? $entry['grantee_id'];
            }
        }

        return $entries;
    }
}

// Gunakan AclService
$aclService = new AclService($db);

echo "18a. Resource yang bisa di-WRITE oleh citra:\n";
$citraWrite = $aclService->getAccessibleResources($citraId, 'write');
foreach ($citraWrite as $r) {
    $info = $aclService->getAccessInfo($citraId, $r['_id']);
    echo "  - [{$r['type']}] {$r['name']} ({$info['level']}) [{$info['source']}]\n";
}

echo "\n18b. Detail ACL untuk 'Budget Plan Q3':\n";
$budgetAcls = $aclService->getResourceAcls($doc3Id);
foreach ($budgetAcls as $acl) {
    $icon = match($acl['permission']) {
        'admin' => 'ADMIN',
        'write' => 'WRITE',
        default => 'READ',
    };
    echo "  - [{$icon}] {$acl['grantee_name']} ({$acl['grantee_type']})\n";
}

// ============================================================
//  19. PERBANDINGAN RBAC vs ACL
// ============================================================
sub('19. Perbandingan RBAC vs ACL');

echo <<<COMPARE

+------------------+------------------------+------------------------+
| Aspek            | RBAC (Contoh 22)       | ACL (Contoh ini)       |
+------------------+------------------------+------------------------+
| Granularitas     | Per role/tipe          | Per resource/instance  |
| Collections      | 5 (users,roles,perms,  | 5 (users,groups,       |
|                  |  user_roles,role_perms)|  user_groups,resources,|
|                  |                        |  acls)                 |
| Relasi           | User→Role→Permission   | User/Group→Resource    |
| Flexibilitas     | Rendah (fixed roles)   | Tinggi (per-instance)  |
| Kompleksitas     | Sederhana              | Lebih kompleks         |
| Cocok untuk      | App dengan role stabil | CMS, File sharing, SaaS|
| Permission check | Cek role → cek perms   | Cek ACL + inheritance  |
| Skalabilitas     | Baik untuk banyak user | Baik untuk banyak      |
|                  | sedikit role           | resource               |
| Maintenance      | Ubah role = ubah       | Setiap resource bisa   |
|                  | banyak user sekaligus  | punya ACL berbeda      |
+------------------+------------------------+------------------------+

KAPAN PAKAI RBAC:
  - Sistem internal perusahaan (admin, editor, viewer)
  - Aplikasi dengan role yang jarang berubah
  - Tim kecil, permission konsisten

KAPAN PAKAI ACL:
  - Google Docs-style collaboration
  - File sharing / cloud storage
  - CMS dimana user bisa share content spesifik
  - Multi-tenant SaaS
  - Sistem dimana user BUKAN admin bisa share resource

HYBRID (RBAC + ACL):
  - RBAC untuk permission global (contoh: semua admin bisa manage users)
  - ACL untuk resource-specific (contoh: budi share doc #123 ke ani)
  - Kombinasi keduanya memberikan fleksibilitas maksimal

COMPARE;

// ============================================================
//  20. RINGKASAN
// ============================================================
sub('20. Ringkasan — Fitur BangronDB yang Digunakan');

echo <<<SUMMARY

Struktur Collection (ACL):
  - users       : Data pengguna
  - groups      : Grup/pengelompokan user
  - user_groups : Junction user ↔ group
  - resources   : Entitas yang dilindungi (document, folder, page, report)
  - acls        : Access Control Entries (resource + grantee + permission)

Relasi (menggunakan type 'relation'):
  User ──< user_groups >── Group
  Resource (parent_id) ──> Resource (self-referencing)
  ACL (resource_id) ──> Resource
  ACL (grantee_id) ──> User ATAU Group
  Resource (owner_id) ──> User

Schema 'relation':
  - Hanya validasi string (tidak ada FK otomatis)
  - Gunakan hook beforeInsert untuk validasi referensi
  - Gunakan populate() untuk mengambil data terkait
  - Idealnya pair dengan index pada field relation

Fitur BangronDB:
  - type 'relation'     : Validasi string untuk ID referensi
  - Hook FK emulation  : beforeInsert validasi referensi lintas collection
  - Populate multi-level: owner, parent, grantee
  - Aggregation pipeline: statistik, distribusi, audit
  - Recursive inheritance: ACL folder → sub-resource (aplikasi layer)
  - Group-based ACL     : grantee_type = 'group' untuk akses massal
  - Streaming          : generator untuk resource list besar
  - Indexing           : 10 index untuk query cepat
  - Schema validation  : type, required, enum, unique, min, max
  - Soft delete        : (bisa ditambahkan untuk acls)
  - Explain            : analisis query plan

SUMMARY;

@$client->close();
echo "\nDone!\n";