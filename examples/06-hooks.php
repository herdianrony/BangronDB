<?php

/**
 * Contoh 06: Hooks (Events).
 *
 * Demonstrasi event hooks untuk intercept dan modifikasi operasi.
 */

require_once __DIR__.'/bootstrap.php';

echo "=== Contoh 06: Hooks ===\n\n";

// Buat client dengan database isolated
$client = createIsolatedClient('hooks_demo');
$db = $client->selectDB('app');
$users = $db->users;

echo "1. Before Insert Hook - Auto timestamps\n";
echo "---------------------------------------\n";

$users->on('beforeInsert', function ($doc) {
    // Set created_at jika belum ada
    if (!isset($doc['created_at'])) {
        $doc['created_at'] = date('c');
    }
    // Set default status
    if (!isset($doc['status'])) {
        $doc['status'] = 'pending';
    }

    return $doc;
});

$users->on('afterInsert', function ($doc, $id) {
    echo "  -> After insert: User '$id' created\n";
});

$userId = $users->insert(['name' => 'User 1']);
$user = $users->findOne(['_id' => $userId]);
echo "Inserted user:\n";
print_r($user);

echo "\n2. Before Update Hook - Audit trail\n";
echo "------------------------------------\n";

$users->on('beforeUpdate', function ($criteria, $data) {
    // Tambah updated_at
    if (!isset($data['$set'])) {
        $data['$set'] = [];
    }
    $data['$set']['updated_at'] = date('c');
    $data['$set']['updated_by'] = 'system';

    echo "  -> Before update: Adding timestamp\n";

    return [$criteria, $data];
});

$users->update(['_id' => $userId], ['$set' => ['status' => 'active']]);
$user = $users->findOne(['_id' => $userId]);
echo "Updated user:\n";
print_r($user);

echo "\n3. Before Remove Hook - Prevent delete\n";
echo "---------------------------------------\n";

$users->on('beforeRemove', function ($doc) {
    // Cegah hapus admin
    if (isset($doc['role']) && $doc['role'] === 'admin') {
        echo "  -> Cannot delete admin user!\n";

        return false; // Cancel delete
    }
    echo "  -> Delete allowed\n";

    return true;
});

$adminId = $users->insert(['name' => 'Admin', 'role' => 'admin']);
$normalId = $users->insert(['name' => 'Normal', 'role' => 'user']);

echo "Attempt to delete admin:\n";
$users->remove(['_id' => $adminId]);

echo "Attempt to delete normal user:\n";
$users->remove(['_id' => $normalId]);

$allUsers = $users->find()->toArray();
echo "Remaining users:\n";
print_r($allUsers);

echo "\n4. After Insert Hook - Trigger actions\n";
echo "----------------------------------------\n";

// Simulasi: Kirim email welcome
$users->on('afterInsert', function ($doc, $id) {
    // Simulasi email send
    echo "  -> SIMULASI: Sending welcome email to {$doc['email']}\n";
});

$users->insert([
    'name' => 'New User',
    'email' => 'newuser@example.com',
]);

echo "\n5. Hook chaining\n";
echo "----------------\n";

// Multiple hooks pada event yang sama
$logs = [];

$users->on('beforeInsert', function ($doc) use (&$logs) {
    $logs[] = 'Hook 1: Normalizing name';
    $doc['name'] = trim(ucwords(strtolower($doc['name'])));

    return $doc;
});

$users->on('beforeInsert', function ($doc) use (&$logs) {
    $logs[] = 'Hook 2: Adding metadata';
    $doc['meta'] = ['inserted_via' => 'hook'];

    return $doc;
});

$userId = $users->insert(['name' => '  test name  ']);
$user = $users->findOne(['_id' => $userId]);
echo "After hook chaining:\n";
print_r($user);
echo "Logs:\n";
print_r($logs);

echo "\n=== Cleanup ===\n";
@$db->drop();
$client->close();
echo "Database dibersihkan.\n";
