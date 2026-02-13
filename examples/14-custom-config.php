<?php

/**
 * Contoh 14: Custom Configuration dengan Permissions.
 *
 * Demonstrasi penggunaan custom config untuk menyimpan
 * permission dan setting lainnya.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;
use BangronDB\Collection;

echo "=== Contoh 14: Custom Configuration ===\n\n";

// Use fixed path for persistence testing
$path = __DIR__ . '/data/custom_config_demo_fixed';
// Create directory if it doesn't exist
if (!is_dir($path)) {
    mkdir($path, 0755, true);
}
$client = new Client($path);
$db = $client->selectDB('app');
$users = $db->users;

echo "1. Setup Collection dengan Custom Config\n";
echo "-----------------------------------------\n";

// Set schema
$users->setSchema([
    'name' => ['type' => 'string', 'required' => true],
    'email' => ['type' => 'string', 'required' => true],
    'role' => ['enum' => ['admin', 'editor', 'viewer']],
]);

// Set custom config untuk permissions
$users->setCustomConfig('permissions', [
    'admin' => ['create', 'read', 'update', 'delete'],
    'editor' => ['create', 'read', 'update'],
    'viewer' => ['read'],
]);

// Set custom config untuk other settings
$users->setCustomConfig('max_login_attempts', 3);
$users->setCustomConfig('session_timeout', 3600);
$users->setCustomConfig('theme', 'dark');

// Simpan konfigurasi
$users->saveConfiguration();

echo "Custom config saved:\n";
echo "- permissions: role-based access control\n";
echo "- max_login_attempts: 3\n";
echo "- session_timeout: 3600 seconds\n";
echo "- theme: dark\n\n";

echo "2. Insert Users dengan Different Roles\n";
echo "---------------------------------------\n";

$adminId = $users->insert([
    'name' => 'Admin User',
    'email' => 'admin@example.com',
    'role' => 'admin',
]);
echo "Admin created: $adminId\n";

$editorId = $users->insert([
    'name' => 'Editor User',
    'email' => 'editor@example.com',
    'role' => 'editor',
]);
echo "Editor created: $editorId\n";

$viewerId = $users->insert([
    'name' => 'Viewer User',
    'email' => 'viewer@example.com',
    'role' => 'viewer',
]);
echo "Viewer created: $viewerId\n\n";

echo "3. Read Custom Config\n";
echo "---------------------\n";

// Get single custom config
$permissions = $users->getCustomConfig('permissions');
echo "Permissions config:\n";
print_r($permissions);

$maxLogin = $users->getCustomConfig('max_login_attempts');
echo "\nMax login attempts: $maxLogin\n";

$theme = $users->getCustomConfig('theme');
echo "Theme: $theme\n\n";

echo "4. Get All Custom Config\n";
echo "------------------------\n";

$allConfig = $users->getAllCustomConfig();
echo "All custom config:\n";
print_r($allConfig);

echo "\n5. Check User Permissions\n";
echo "-------------------------\n";

// Get permissions based on user role
function getUserPermissions(Collection $users, string $userId): array
{
    $user = $users->findOne(['_id' => $userId]);
    if (!$user) {
        return [];
    }

    $role = $user['role'] ?? 'viewer';
    $permissions = $users->getCustomConfig('permissions', []);

    return $permissions[$role] ?? [];
}

$adminPerms = getUserPermissions($users, $adminId);
echo 'Admin permissions: ' . implode(', ', $adminPerms) . "\n";

$editorPerms = getUserPermissions($users, $editorId);
echo 'Editor permissions: ' . implode(', ', $editorPerms) . "\n";

$viewerPerms = getUserPermissions($users, $viewerId);
echo 'Viewer permissions: ' . implode(', ', $viewerPerms) . "\n";

echo "\n6. Update Custom Config\n";
echo "-----------------------\n";

$users->setCustomConfig('theme', 'light');
$users->saveConfiguration();

$newTheme = $users->getCustomConfig('theme');
echo "Theme updated to: $newTheme\n\n";

echo "7. Verify Config Persistence\n";
echo "----------------------------\n";

// Close first
$client->close();

// Reconnect using the SAME path
$client2 = new Client($path);
$db2 = $client2->selectDB('app');
$users2 = $db2->users;

$persistedPerms = $users2->getCustomConfig('permissions');
echo "Persisted permissions:\n";
print_r($persistedPerms);

$persistedTheme = $users2->getCustomConfig('theme');
echo "\nPersisted theme: $persistedTheme\n";

echo "\n=== Cleanup ===\n";
@$db2->drop();
@$client2->close();
echo "Database dibersihkan.\n";
