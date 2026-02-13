<?php

/**
 * Contoh 04: Soft Deletes.
 *
 * Demonstrasi soft delete dengan restore capability.
 */

require_once __DIR__.'/bootstrap.php';

echo "=== Contoh 04: Soft Deletes ===\n\n";

// Buat client dengan database isolated
$client = createIsolatedClient('softdelete_demo');
$db = $client->selectDB('app');
$users = $db->users;

// Enable soft deletes
$users->useSoftDeletes(true);

echo "1. Insert data\n";
echo "----------------\n";

$user1 = $users->insert(['name' => 'User 1', 'status' => 'active']);
$user2 = $users->insert(['name' => 'User 2', 'status' => 'active']);
$user3 = $users->insert(['name' => 'User 3', 'status' => 'active']);

$total = $users->count();
echo "Total users: $total (aktif saja)\n";

echo "\n2. Soft delete user 2\n";
echo "----------------------\n";

$removed = $users->remove(['_id' => $user2]);
echo "Soft deleted: $removed user(s)\n";

$total = $users->count();
echo "Total users: $total (aktif saja)\n";

$allUsers = $users->find()->toArray();
echo "Active users:\n";
print_r($allUsers);

echo "\n3. Lihat semua termasuk deleted\n";
echo "--------------------------------\n";

$allWithDeleted = $users->find()->withTrashed()->toArray();
echo "All users (including deleted):\n";
print_r($allWithDeleted);

echo "\n4. Lihat hanya deleted\n";
echo "------------------------\n";

$onlyDeleted = $users->find()->onlyTrashed()->toArray();
echo "Only deleted users:\n";
print_r($onlyDeleted);

echo "\n5. Restore user 2\n";
echo "-----------------\n";

$restored = $users->restore(['_id' => $user2]);
echo "Restored: $restored user(s)\n";

$total = $users->count();
echo "Total users: $total (aktif)\n";

$allUsers = $users->find()->toArray();
echo "Active users after restore:\n";
print_r($allUsers);

echo "\n6. Force delete permanen\n";
echo "------------------------\n";

// Hapus permanen user 3
$forceDeleted = $users->forceDelete(['_id' => $user3]);
echo "Force deleted: $forceDeleted user(s)\n";

$total = $users->count();
echo "Total users: $total\n";

$allWithDeleted = $users->find()->withTrashed()->toArray();
echo "All users (including deleted):\n";
print_r($allWithDeleted);

echo "\n7. Soft delete dengan criteria\n";
echo "------------------------------\n";

$user4 = $users->insert(['name' => 'User 4', 'status' => 'inactive']);
$user5 = $users->insert(['name' => 'User 5', 'status' => 'inactive']);

// Soft delete semua inactive
$removed = $users->remove(['status' => 'inactive']);
echo "Soft deleted $removed inactive users\n";

$activeUsers = $users->find()->toArray();
echo "Active users:\n";
print_r($activeUsers);

$deletedUsers = $users->find()->onlyTrashed()->toArray();
echo "Deleted users:\n";
print_r($deletedUsers);

echo "\n=== Cleanup ===\n";
@$db->drop();
$client->close();
echo "Database dibersihkan.\n";
