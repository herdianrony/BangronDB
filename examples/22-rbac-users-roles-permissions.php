<?php

/**
 * Contoh 22: RBAC Model — Users, Roles & Permissions
 *
 * Implementasi lengkap Role-Based Access Control (RBAC) menggunakan BangronDB.
 * Mencakup:
 *   - Schema validation untuk setiap collection
 *   - Auto-timestamps via hooks
 *   - Relasi many-to-many (users ↔ roles, roles ↔ permissions)
 *   - Populate (fluent & manual) untuk join data
 *   - Indexing untuk performa query
 *   - Aggregation pipeline untuk statistik
 *   - Helper class untuk operasi RBAC sehari-hari
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;
use BangronDB\Exceptions\ValidationException;

sep('Contoh 22: RBAC Model — Users, Roles & Permissions');

// ============================================================
//  1. INISIALISASI DATABASE & COLLECTION
// ============================================================
sub('1. Inisialisasi Database & Collection');

$client = createIsolatedClient('example22');
$db     = $client->createDB('rbac_app');

$users      = $db->createCollection('users');
$roles      = $db->createCollection('roles');
$permissions = $db->createCollection('permissions');
$userRoles  = $db->createCollection('user_roles');       // junction: user ↔ role
$rolePerms  = $db->createCollection('role_permissions'); // junction: role ↔ permission

echo "5 collection dibuat: users, roles, permissions, user_roles, role_permissions\n";

// ============================================================
//  2. SCHEMA VALIDATION
// ============================================================
sub('2. Schema Validation');

// --- Users Schema ---
$users->setSchema([
    'username'  => ['type' => 'string', 'required' => true, 'min' => 3, 'max' => 50, 'unique' => true],
    'email'     => ['type' => 'email',  'required' => true, 'unique' => true],
    'password'  => ['type' => 'string', 'required' => true, 'min' => 8],
    'full_name' => ['type' => 'string', 'required' => true, 'min' => 1, 'max' => 100],
    'is_active' => ['type' => 'bool',   'required' => true],
    'avatar_url' => ['type' => 'url'],
]);
$users->saveConfiguration();

// --- Roles Schema ---
$roles->setSchema([
    'name'        => ['type' => 'string', 'required' => true, 'min' => 2, 'max' => 50, 'unique' => true],
    'display_name'=> ['type' => 'string', 'required' => true, 'min' => 2, 'max' => 100],
    'description' => ['type' => 'string', 'max' => 500],
    'level'       => ['type' => 'int', 'required' => true, 'min' => 0, 'max' => 100],
    'is_system'   => ['type' => 'bool'],
]);
$roles->saveConfiguration();

// --- Permissions Schema ---
$permissions->setSchema([
    'name'        => ['type' => 'string', 'required' => true, 'min' => 3, 'max' => 100, 'unique' => true],
    'display_name'=> ['type' => 'string', 'required' => true, 'min' => 3, 'max' => 150],
    'module'      => ['type' => 'string', 'required' => true, 'max' => 50],
    'description' => ['type' => 'string', 'max' => 500],
]);
$permissions->saveConfiguration();

// --- User Roles (Junction) Schema ---
$userRoles->setSchema([
    'user_id' => ['type' => 'string', 'required' => true],
    'role_id' => ['type' => 'string', 'required' => true],
]);
$userRoles->saveConfiguration();

// --- Role Permissions (Junction) Schema ---
$rolePerms->setSchema([
    'role_id'       => ['type' => 'string', 'required' => true],
    'permission_id' => ['type' => 'string', 'required' => true],
]);
$rolePerms->saveConfiguration();

echo "Schema untuk semua collection sudah dikonfigurasi & disimpan\n";

// ============================================================
//  3. HOOKS — AUTO TIMESTAMPS
// ============================================================
sub('3. Hooks — Auto Timestamps');

// Terapkan auto timestamps ke semua collection utama
foreach ([$users, $roles, $permissions, $userRoles, $rolePerms] as $collection) {
    $collection->on('beforeInsert', function ($doc) {
        $doc['created_at'] = date('c');
        $doc['updated_at'] = date('c');
        return $doc;
    });
    $collection->on('beforeUpdate', function ($criteria, $data) {
        if (!isset($data['$set'])) {
            $data['$set'] = [];
        }
        $data['$set']['updated_at'] = date('c');
        return [$criteria, $data];
    });
}
echo "Auto created_at & updated_at diterapkan ke 5 collection\n";

// Hook: hash password sebelum insert user
$users->on('beforeInsert', function ($doc) {
    if (isset($doc['password'])) {
        $doc['password_hash'] = password_hash($doc['password'], PASSWORD_BCRYPT);
        unset($doc['password']); // jangan simpan plain password
    }
    return $doc;
});
echo "Hook password hashing aktif (password → password_hash)\n";

// ============================================================
//  4. INDEXING
// ============================================================
sub('4. Indexing');

$users->createIndex('email');
$users->createIndex('username');
$roles->createIndex('name');
$permissions->createIndex('name');
$permissions->createIndex('module');
$userRoles->createIndex('user_id');
$userRoles->createIndex('role_id');
$rolePerms->createIndex('role_id');
$rolePerms->createIndex('permission_id');

echo "Index dibuat untuk field-field yang sering di-query\n";

// ============================================================
//  5. SEED DATA — ROLES
// ============================================================
sub('5. Seed Data — Roles');

$superAdminId = $roles->insert([
    'name'         => 'super_admin',
    'display_name' => 'Super Administrator',
    'description'  => 'Akses penuh ke seluruh sistem',
    'level'        => 100,
    'is_system'    => true,
]);

$adminId = $roles->insert([
    'name'         => 'admin',
    'display_name' => 'Administrator',
    'description'  => 'Mengelola pengguna dan konten',
    'level'        => 80,
    'is_system'    => true,
]);

$editorId = $roles->insert([
    'name'         => 'editor',
    'display_name' => 'Editor',
    'description'  => 'Mengelola dan mempublikasikan konten',
    'level'        => 50,
    'is_system'    => false,
]);

$viewerId = $roles->insert([
    'name'         => 'viewer',
    'display_name' => 'Viewer',
    'description'  => 'Hanya bisa melihat konten',
    'level'        => 10,
    'is_system'    => false,
]);

echo "4 roles dibuat:\n";
echo "  - super_admin (level 100)\n";
echo "  - admin       (level 80)\n";
echo "  - editor      (level 50)\n";
echo "  - viewer      (level 10)\n";

// ============================================================
//  6. SEED DATA — PERMISSIONS
// ============================================================
sub('6. Seed Data — Permissions');

$permData = [
    // User management
    ['name' => 'users.create',  'display_name' => 'Buat User',           'module' => 'users'],
    ['name' => 'users.read',    'display_name' => 'Lihat User',           'module' => 'users'],
    ['name' => 'users.update',  'display_name' => 'Edit User',            'module' => 'users'],
    ['name' => 'users.delete',  'display_name' => 'Hapus User',           'module' => 'users'],
    // Content management
    ['name' => 'posts.create',  'display_name' => 'Buat Artikel',        'module' => 'posts'],
    ['name' => 'posts.read',    'display_name' => 'Lihat Artikel',        'module' => 'posts'],
    ['name' => 'posts.update',  'display_name' => 'Edit Artikel',         'module' => 'posts'],
    ['name' => 'posts.delete',  'display_name' => 'Hapus Artikel',        'module' => 'posts'],
    ['name' => 'posts.publish', 'display_name' => 'Publikasikan Artikel', 'module' => 'posts'],
    // Settings
    ['name' => 'settings.read',    'display_name' => 'Lihat Pengaturan',    'module' => 'settings'],
    ['name' => 'settings.update',  'display_name' => 'Ubah Pengaturan',     'module' => 'settings'],
    // Reports
    ['name' => 'reports.read',   'display_name' => 'Lihat Laporan',       'module' => 'reports'],
    ['name' => 'reports.export', 'display_name' => 'Ekspor Laporan',       'module' => 'reports'],
];

$permIds = [];
foreach ($permData as $perm) {
    $permIds[$perm['name']] = $permissions->insert($perm);
}

echo count($permData) . " permissions dibuat di 4 module (users, posts, settings, reports)\n";

// ============================================================
//  7. ROLE-PERMISSION MAPPING
// ============================================================
sub('7. Role-Permission Mapping');

// super_admin: semua permission
foreach ($permIds as $permId) {
    $rolePerms->insert(['role_id' => $superAdminId, 'permission_id' => $permId]);
}

// admin: semua kecuali settings
$adminPerms = ['users.create','users.read','users.update','users.delete',
               'posts.create','posts.read','posts.update','posts.delete','posts.publish',
               'reports.read','reports.export'];
foreach ($adminPerms as $pName) {
    $rolePerms->insert(['role_id' => $adminId, 'permission_id' => $permIds[$pName]]);
}

// editor: konten saja
$editorPerms = ['posts.create','posts.read','posts.update','posts.publish'];
foreach ($editorPerms as $pName) {
    $rolePerms->insert(['role_id' => $editorId, 'permission_id' => $permIds[$pName]]);
}

// viewer: read saja
$viewerPerms = ['posts.read','reports.read'];
foreach ($viewerPerms as $pName) {
    $rolePerms->insert(['role_id' => $viewerId, 'permission_id' => $permIds[$pName]]);
}

echo "super_admin: " . $rolePerms->count(['role_id' => $superAdminId]) . " permissions\n";
echo "admin:       " . $rolePerms->count(['role_id' => $adminId]) . " permissions\n";
echo "editor:      " . $rolePerms->count(['role_id' => $editorId]) . " permissions\n";
echo "viewer:      " . $rolePerms->count(['role_id' => $viewerId]) . " permissions\n";

// ============================================================
//  8. SEED DATA — USERS
// ============================================================
sub('8. Seed Data — Users');

$userId1 = $users->insert([
    'username'   => 'budi_s',
    'email'      => 'budi@superadmin.com',
    'password'   => 'SuperSecret123!',
    'full_name'  => 'Budi Santoso',
    'is_active'  => true,
    'avatar_url' => 'https://example.com/avatars/budi.jpg',
]);

$userId2 = $users->insert([
    'username'   => 'ani_admin',
    'email'      => 'ani@admin.com',
    'password'   => 'AdminPass456!',
    'full_name'  => 'Ani Rahayu',
    'is_active'  => true,
]);

$userId3 = $users->insert([
    'username'   => 'citra_editor',
    'email'      => 'citra@editor.com',
    'password'   => 'EditorPass789!',
    'full_name'  => 'Citra Dewi',
    'is_active'  => true,
]);

$userId4 = $users->insert([
    'username'   => 'dani_viewer',
    'email'      => 'dani@viewer.com',
    'password'   => 'ViewerPass012!',
    'full_name'  => 'Dani Pratama',
    'is_active'  => false, // user non-aktif
]);

echo "4 users dibuat:\n";
echo "  - budi_s        (Super Admin)\n";
echo "  - ani_admin     (Admin)\n";
echo "  - citra_editor  (Editor)\n";
echo "  - dani_viewer   (Viewer, non-aktif)\n";

// ============================================================
//  9. USER-ROLE ASSIGNMENT
// ============================================================
sub('9. User-Role Assignment');

// Budi → super_admin
$userRoles->insert(['user_id' => $userId1, 'role_id' => $superAdminId]);

// Ani → admin + editor (multi-role)
$userRoles->insert(['user_id' => $userId2, 'role_id' => $adminId]);
$userRoles->insert(['user_id' => $userId2, 'role_id' => $editorId]);

// Citra → editor
$userRoles->insert(['user_id' => $userId3, 'role_id' => $editorId]);

// Dani → viewer
$userRoles->insert(['user_id' => $userId4, 'role_id' => $viewerId]);

echo "budi_s:       1 role (super_admin)\n";
echo "ani_admin:    2 roles (admin + editor)\n";
echo "citra_editor: 1 role (editor)\n";
echo "dani_viewer:  1 role (viewer)\n";

// ============================================================
//  10. QUERY PATTERNS
// ============================================================
sub('10. Query Patterns');

// 10a. Cari user aktif
echo "10a. User aktif:\n";
$activeUsers = $users->find(['is_active' => true], ['username' => 1, 'email' => 1, 'full_name' => 1, 'is_active' => 1])
    ->sort(['username' => 1])
    ->toArray();
foreach ($activeUsers as $u) {
    echo "  - {$u['username']} ({$u['full_name']}) — active: " . ($u['is_active'] ? 'yes' : 'no') . "\n";
}

// 10b. Cari user dengan regex (nama depan)
echo "\n10b. User dengan nama 'Ani' atau 'Citra':\n";
$matched = $users->find([
    'full_name' => ['$regex' => '/^(Ani|Citra)/']
], ['username' => 1, 'full_name' => 1])->toArray();
foreach ($matched as $u) {
    echo "  - {$u['username']}: {$u['full_name']}\n";
}

// 10c. Cari roles dengan level >= 50
echo "\n10c. Roles level >= 50:\n";
$highRoles = $roles->find(['level' => ['$gte' => 50]])->sort(['level' => -1])->toArray();
foreach ($highRoles as $r) {
    echo "  - {$r['name']} (level {$r['level']}): {$r['display_name']}\n";
}

// 10d. Permission per module
echo "\n10d. Permissions di module 'posts':\n";
$postPerms = $permissions->find(['module' => 'posts'])->sort(['name' => 1])->toArray();
foreach ($postPerms as $p) {
    echo "  - {$p['name']}: {$p['display_name']}\n";
}

// 10e. Cari junction: user_roles untuk user tertentu
echo "\n10e. Role assignments untuk ani_admin:\n";
$aniAssignments = $userRoles->find(['user_id' => $userId2])->toArray();
foreach ($aniAssignments as $a) {
    echo "  - role_id: {$a['role_id']}\n";
}

// ============================================================
//  11. POPULATE — RELASI
// ============================================================
sub('11. Populate — Relasi');

// 11a. User + Roles (manual populate)
echo "11a. User beserta roles (manual populate):\n";
$allUsers = $users->find(null, ['password_hash' => 0])->sort(['username' => 1])->toArray();
$usersWithRoles = $users->populate($allUsers, '_id', 'user_roles', 'user_id', 'role_assignments');
// Populate ke-2: role_assignments.role_id → roles
$usersWithRoles = $users->populate($usersWithRoles, 'role_assignments.role_id', 'roles', '_id', 'role');

foreach ($usersWithRoles as $u) {
    $roleNames = array_map(fn($r) => $r['display_name'], $u['role'] ?? []);
    echo "  - {$u['username']}: " . implode(', ', $roleNames) . "\n";
}

// 11b. Role + Permissions (fluent populate via Cursor)
echo "\n11b. Role 'editor' beserta permissions (fluent populate):\n";
$editorWithPerms = $rolePerms->find(['role_id' => $editorId])
    ->populate('permission_id', $permissions, ['as' => 'permission'])
    ->toArray();

echo "  Editor memiliki " . count($editorWithPerms) . " permissions:\n";
foreach ($editorWithPerms as $rp) {
    echo "    - {$rp['permission']['name']}: {$rp['permission']['display_name']}\n";
}

// 11c. User lengkap: user → roles → permissions
echo "\n11c. User 'ani_admin' lengkap (user → roles → permissions):\n";
// Ambil role assignments ani
$aniRoleAssignments = $userRoles->find(['user_id' => $userId2])->toArray();
$aniRoleAssignments = $userRoles->populate($aniRoleAssignments, 'role_id', 'roles', '_id', 'role');

// Kumpulkan semua permission_id dari semua role ani
$allPermIds = [];
foreach ($aniRoleAssignments as $ra) {
    $rp = $rolePerms->find(['role_id' => $ra['role_id']])->toArray();
    foreach ($rp as $r) {
        $allPermIds[] = $r['permission_id'];
    }
}
$allPermIds = array_unique($allPermIds);

// Populate permissions
$aniPerms = [];
if (!empty($allPermIds)) {
    $aniPermDocs = $permissions->find(['_id' => ['$in' => $allPermIds]])->sort(['module' => 1, 'name' => 1])->toArray();
    // Group by module
    foreach ($aniPermDocs as $p) {
        $aniPerms[$p['module']][] = $p['display_name'];
    }
}

echo "  Roles: " . implode(', ', array_map(fn($ra) => $ra['role']['display_name'], $aniRoleAssignments)) . "\n";
echo "  Total permissions: " . count($aniPermDocs ?? []) . "\n";
foreach ($aniPerms as $module => $perms) {
    echo "    [{$module}] " . implode(', ', $perms) . "\n";
}

// ============================================================
//  12. AGGREGATION — STATISTIK
// ============================================================
sub('12. Aggregation — Statistik');

// 12a. Jumlah user per role
echo "12a. Jumlah user per role:\n";
$allAssignments = $userRoles->find()->toArray();
$allAssignments = $userRoles->populate($allAssignments, 'role_id', 'roles', '_id', 'role');

$roleUserCounts = [];
foreach ($allAssignments as $a) {
    $name = $a['role']['display_name'];
    $roleUserCounts[$name] = ($roleUserCounts[$name] ?? 0) + 1;
}
foreach ($roleUserCounts as $role => $count) {
    echo "  - {$role}: {$count} user(s)\n";
}

// 12b. Jumlah permission per module
echo "\n12b. Jumlah permission per module (aggregation pipeline):\n";
$moduleStats = $permissions->aggregate([
    ['$group' => [
        '_id'   => '$module',
        'count' => ['$sum' => 1],
        'perms' => ['$push' => '$name'],
    ]],
    ['$sort'  => ['count' => -1]],
]);
foreach ($moduleStats as $stat) {
    echo "  - {$stat['_id']}: {$stat['count']} permissions (" . implode(', ', $stat['perms']) . ")\n";
}

// 12c. Rata-rata level role
echo "\n12c. Statistik level role:\n";
$levelStats = $roles->aggregate([
    ['$group' => [
        '_id'   => null,
        'avg'   => ['$avg' => '$level'],
        'min'   => ['$min' => '$level'],
        'max'   => ['$max' => '$level'],
        'total' => ['$sum' => 1],
    ]],
]);
$ls = $levelStats[0];
echo "  Total roles: {$ls['total']}\n";
echo "  Level — min: {$ls['min']}, max: {$ls['max']}, avg: " . round($ls['avg'], 1) . "\n";

// 12d. Aggregation: permission paling banyak dimiliki role
echo "\n12d. Permission yang paling banyak di-assign ke role:\n";
$popularPerms = $rolePerms->aggregate([
    ['$group' => [
        '_id'   => '$permission_id',
        'count' => ['$sum' => 1],
    ]],
    ['$sort'  => ['count' => -1]],
    ['$limit' => 5],
]);
$popularPerms = $permissions->populate($popularPerms, '_id', 'permissions', '_id', 'permission');
foreach ($popularPerms as $pp) {
    echo "  - {$pp['permission']['name']}: di-assign ke {$pp['count']} role(s)\n";
}

// ============================================================
//  13. UPDATE & HOOKS
// ============================================================
sub('13. Update & Hooks');

// 13a. Update user
echo "13a. Update profile user:\n";
$users->update(['_id' => $userId3], ['$set' => ['full_name' => 'Citra Dewi Lestari', 'avatar_url' => 'https://example.com/avatars/citra2.jpg']]);
$updated = $users->findOne(['_id' => $userId3], ['full_name' => 1, 'updated_at' => 1]);
echo "  Nama baru: {$updated['full_name']}\n";
echo "  updated_at terisi: " . (isset($updated['updated_at']) ? 'ya' : 'tidak') . "\n";

// 13b. Assign role baru ke user (multi-role)
echo "\n13b. Tambah role 'viewer' ke citra_editor:\n";
$userRoles->insert(['user_id' => $userId3, 'role_id' => $viewerId]);
$citraRoles = $userRoles->find(['user_id' => $userId3])->toArray();
$citraRoles = $userRoles->populate($citraRoles, 'role_id', 'roles', '_id', 'role');
echo "  Citra sekarang punya " . count($citraRoles) . " roles: ";
echo implode(', ', array_map(fn($r) => $r['role']['display_name'], $citraRoles)) . "\n";

// 13c. Revoke role dari user
echo "\n13c. Hapus role 'viewer' dari citra_editor:\n";
$userRoles->remove(['user_id' => $userId3, 'role_id' => $viewerId]);
echo "  Sisa roles: " . $userRoles->count(['user_id' => $userId3]) . "\n";

// ============================================================
//  14. SOFT DELETE & RESTORE
// ============================================================
sub('14. Soft Delete & Restore');

$users->useSoftDeletes(true);
echo "Soft deletes diaktifkan untuk users\n";

// 14a. Soft delete user
echo "14a. Soft delete dani_viewer:\n";
$users->remove(['_id' => $userId4]);
echo "  Total users (tanpa trashed): " . $users->count() . "\n";
echo "  Total users (dengan trashed): " . $users->find()->withTrashed()->count() . "\n";

// 14b. Restore user
echo "\n14b. Restore dani_viewer:\n";
$users->restore(['_id' => $userId4]);
$restored = $users->findOne(['_id' => $userId4], ['username' => 1, 'is_active' => 1]);
echo "  Restored: {$restored['username']} (active: " . ($restored['is_active'] ? 'yes' : 'no') . ")\n";
echo "  Total users sekarang: " . $users->count() . "\n";

// ============================================================
//  15. RBAC HELPER CLASS
// ============================================================
sub('15. RBAC Helper Class — Contoh Penggunaan');

/**
 * Helper class untuk operasi RBAC sehari-hari.
 * Contoh ini menunjukkan bagaimana membungkus BangronDB
 * dalam layer abstraksi bisnis.
 */
class RbacService
{
    public function __construct(
        private readonly \BangronDB\Database $db
    ) {}

    /** Assign role ke user */
    public function assignRole(string $userId, string $roleId): void
    {
        $userRoles = $this->db->selectCollection('user_roles');
        $exists = $userRoles->findOne(['user_id' => $userId, 'role_id' => $roleId]);
        if ($exists) {
            return; // sudah di-assign
        }
        $userRoles->insert(['user_id' => $userId, 'role_id' => $roleId]);
    }

    /** Revoke role dari user */
    public function revokeRole(string $userId, string $roleId): int
    {
        return $this->db->selectCollection('user_roles')
            ->remove(['user_id' => $userId, 'role_id' => $roleId]);
    }

    /** Cek apakah user memiliki permission tertentu */
    public function hasPermission(string $userId, string $permissionName): bool
    {
        $rolePerms = $this->db->selectCollection('role_permissions');
        $perms     = $this->db->selectCollection('permissions');

        // 1. Cari permission_id dari nama
        $perm = $perms->findOne(['name' => $permissionName], ['_id' => 1]);
        if (!$perm) {
            return false;
        }

        // 2. Cari semua role user
        $userRoles = $this->db->selectCollection('user_roles');
        $assignments = $userRoles->find(['user_id' => $userId])->toArray();
        $roleIds = array_column($assignments, 'role_id');

        if (empty($roleIds)) {
            return false;
        }

        // 3. Cek apakah ada role yang memiliki permission ini
        $count = $rolePerms->count([
            'role_id'       => ['$in' => $roleIds],
            'permission_id' => $perm['_id'],
        ]);

        return $count > 0;
    }

    /** Ambil semua permission untuk user */
    public function getUserPermissions(string $userId): array
    {
        $userRoles = $this->db->selectCollection('user_roles');
        $rolePerms = $this->db->selectCollection('role_permissions');
        $perms     = $this->db->selectCollection('permissions');

        $assignments = $userRoles->find(['user_id' => $userId])->toArray();
        $roleIds = array_column($assignments, 'role_id');

        if (empty($roleIds)) {
            return [];
        }

        $rpList = $rolePerms->find(['role_id' => ['$in' => $roleIds]])->toArray();
        $permIds = array_unique(array_column($rpList, 'permission_id'));

        if (empty($permIds)) {
            return [];
        }

        return $perms->find(['_id' => ['$in' => $permIds]])
            ->sort(['module' => 1, 'name' => 1])
            ->toArray();
    }

    /** Ambil semua role untuk user */
    public function getUserRoles(string $userId): array
    {
        $userRoles = $this->db->selectCollection('user_roles');
        $roles     = $this->db->selectCollection('roles');

        $assignments = $userRoles->find(['user_id' => $userId])->toArray();
        return $userRoles->populate($assignments, 'role_id', $roles, '_id', 'role');
    }
}

$rbac = new RbacService($db);

// Cek permission
echo "15a. Cek permissions:\n";
$checks = [
    ['user' => 'budi_s',        'userId' => $userId1, 'perm' => 'settings.update'],
    ['user' => 'ani_admin',     'userId' => $userId2, 'perm' => 'users.create'],
    ['user' => 'ani_admin',     'userId' => $userId2, 'perm' => 'settings.update'], // tidak punya
    ['user' => 'citra_editor',  'userId' => $userId3, 'perm' => 'posts.publish'],
    ['user' => 'citra_editor',  'userId' => $userId3, 'perm' => 'users.delete'],   // tidak punya
    ['user' => 'dani_viewer',   'userId' => $userId4, 'perm' => 'posts.read'],
    ['user' => 'dani_viewer',   'userId' => $userId4, 'perm' => 'posts.create'],   // tidak punya
];

foreach ($checks as $check) {
    $has = $rbac->hasPermission($check['userId'], $check['perm']) ? 'YES' : 'NO';
    echo "  {$check['user']} → {$check['perm']}: {$has}\n";
}

// Ambil semua permissions untuk user
echo "\n15b. Semua permissions ani_admin:\n";
$aniPermsList = $rbac->getUserPermissions($userId2);
$grouped = [];
foreach ($aniPermsList as $p) {
    $grouped[$p['module']][] = $p['display_name'];
}
foreach ($grouped as $mod => $plist) {
    echo "  [{$mod}] " . implode(', ', $plist) . "\n";
}

// ============================================================
//  16. STREAMING — MEMORY EFFICIENT
// ============================================================
sub('16. Streaming — Generator untuk Data Besar');

echo "16. Stream semua users (memory efficient):\n";
$stream = $users->stream(null, ['projection' => ['username' => 1, 'email' => 1, 'is_active' => 1]]);
foreach ($stream as $doc) {
    echo "  - {$doc['username']} ({$doc['email']}) active=" . ($doc['is_active'] ? 'Y' : 'N') . "\n";
}

// Stream dengan sort & limit
echo "\n16b. Stream 2 user pertama (sorted by username DESC):\n";
$stream2 = $users->stream(null, [
    'sort'  => ['username' => -1],
    'limit' => 2,
    'projection' => ['username' => 1, 'full_name' => 1],
]);
foreach ($stream2 as $doc) {
    echo "  - {$doc['username']}: {$doc['full_name']}\n";
}

// ============================================================
//  17. EXPLAIN QUERY — OPTIMIZATION INSIGHT
// ============================================================
sub('17. Explain Query');

echo "17a. Explain: cari user aktif\n";
$explanation = $users->explain(['is_active' => true]);
echo "  SQL: " . substr($explanation['query_plan']['sql'] ?? '', 0, 80) . "...\n";
echo "  Scan ratio: " . ($explanation['performance']['scan_ratio'] ?? 'N/A') . "\n";

echo "\n17b. Explain: cari permission berdasarkan module\n";
$explanation2 = $permissions->explain(['module' => 'posts']);
echo "  SQL: " . substr($explanation2['query_plan']['sql'] ?? '', 0, 80) . "...\n";
echo "  Index used: " . ($explanation2['query_plan']['uses_index'] ? 'yes' : 'no') . "\n";

// ============================================================
//  18. VALIDATION ERROR HANDLING
// ============================================================
sub('18. Validation Error Handling');

echo "18a. Insert user tanpa field required:\n";
try {
    $users->insert(['username' => 'incomplete']);
} catch (ValidationException $e) {
    echo "  ValidationException: " . substr($e->getMessage(), 0, 100) . "\n";
} catch (Exception $e) {
    echo "  Error: " . substr($e->getMessage(), 0, 100) . "\n";
}

echo "\n18b. Insert user dengan email duplikat:\n";
try {
    $users->insert([
        'username'  => 'dup_user',
        'email'     => 'budi@superadmin.com', // duplikat
        'password'  => 'SomePass123!',
        'full_name' => 'Duplicate User',
        'is_active' => true,
    ]);
} catch (ValidationException $e) {
    echo "  ValidationException: " . substr($e->getMessage(), 0, 100) . "\n";
} catch (Exception $e) {
    echo "  Error: " . substr($e->getMessage(), 0, 100) . "\n";
}

echo "\n18c. Insert role dengan level di luar range:\n";
try {
    $roles->insert([
        'name'         => 'invalid_role',
        'display_name' => 'Invalid Role',
        'description'  => 'Level terlalu tinggi',
        'level'        => 999, // max: 100
        'is_system'    => false,
    ]);
} catch (ValidationException $e) {
    echo "  ValidationException: " . substr($e->getMessage(), 0, 100) . "\n";
} catch (Exception $e) {
    echo "  Error: " . substr($e->getMessage(), 0, 100) . "\n";
}

echo "\n18d. Insert user dengan password terlalu pendek:\n";
try {
    $users->insert([
        'username'  => 'short_pass',
        'email'     => 'short@test.com',
        'password'  => 'abc', // min: 8
        'full_name' => 'Short Password',
        'is_active' => true,
    ]);
} catch (ValidationException $e) {
    echo "  ValidationException: " . substr($e->getMessage(), 0, 100) . "\n";
} catch (Exception $e) {
    echo "  Error: " . substr($e->getMessage(), 0, 100) . "\n";
}

// ============================================================
//  19. COLLECTION METRICS
// ============================================================
sub('19. Collection Metrics');

$metrics = $db->getCollectionMetrics();
echo "Metrics per collection:\n";
foreach ($metrics as $name => $m) {
    $count = $m['document_count'] ?? 0;
    $size  = $m['estimated_size_bytes'] ?? 0;
    echo "  - {$name}: {$count} docs, ~" . round($size / 1024, 1) . " KB\n";
}

// ============================================================
//  20. RINGKASAN ARSITEKTUR RBAC
// ============================================================
sub('20. Ringkasan Arsitektur RBAC');

echo <<<RBAASUMMARY

Struktur Collection:
  - users          : Data pengguna (username, email, password_hash, full_name, is_active)
  - roles          : Definisi role (name, display_name, level, is_system)
  - permissions    : Definisi permission (name, display_name, module)
  - user_roles     : Junction table many-to-many (user_id, role_id)
  - role_permissions: Junction table many-to-many (role_id, permission_id)

Relasi:
  User  ──< user_roles >── Role  ──< role_permissions >── Permission
  (many)    (N:M)        (many)    (N:M)                  (many)

Fitur BangronDB yang digunakan:
  - Schema Validation    : type, required, min, max, unique, regex, enum
  - Hooks (on/off)       : auto timestamps, password hashing
  - Populate             : manual & fluent (Cursor), multi-level, cross-collection
  - Aggregation Pipeline : \$group, \$sort, \$limit, \$push, \$sum, \$avg, \$min, \$max
  - Indexing             : JSON index untuk query yang sering digunakan
  - Soft Deletes         : useSoftDeletes(), restore(), withTrashed()
  - Streaming            : Generator untuk data besar
  - Explain              : Analisis query plan & index usage
  - Query Operators      : \$eq, \$in, \$gte, \$regex, \$exists
  - Cursor               : find() → sort(), limit(), skip(), withTrashed(), populate(), toArray()
  - Exceptions           : ValidationException, DatabaseException, CollectionException

RBAASUMMARY;

@$client->close();
echo "\nDone!\n";