<?php

/**
 * Contoh 10: Dynamic Configuration & Custom Config
 *
 * Save/load konfigurasi collection ke database, custom config
 * untuk permissions dan settings, auto-load configuration.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sep('Contoh 10: Dynamic Configuration & Custom Config');

$examplePath = __DIR__ . '/data/example10_' . uniqid();
@mkdir($examplePath, 0755, true);

$client = new Client($examplePath);
$db = $client->createDB('config_app');
$users = $db->createCollection('users');

// ── Setup Collection dengan Full Config ────────────────────
sub('Setup Collection dengan Configuration');

$users->setIdModePrefix('USR');
$users->setSchema([
    'name'  => ['type' => 'string', 'required' => true, 'min' => 2],
    'email' => ['type' => 'string', 'required' => true],
    'role'  => ['type' => 'string', 'enum' => ['admin', 'editor', 'viewer']],
]);
$users->useSoftDeletes(true);
$users->setSearchableFields(['email'], true);

// Custom config untuk permissions
$users->setCustomConfig('permissions', [
    'admin'  => ['create', 'read', 'update', 'delete'],
    'editor' => ['create', 'read', 'update'],
    'viewer' => ['read'],
]);
$users->setCustomConfig('max_login_attempts', 3);
$users->setCustomConfig('session_timeout', 3600);
$users->setCustomConfig('theme', 'dark');

// WAJIB: saveConfiguration() untuk persist
$users->saveConfiguration();
echo "Configuration saved to database\n";
echo "Catatan: ID prefix yang dipersist akan dinormalisasi ke format prefix:USR\n";

// ── Insert dengan Config ──────────────────────────────────
sub('Insert Data');

$users->setEncryptionKey($_ENV['DB_ENCRYPTION_KEY'] ?? 'config-app-encryption-key-32-char!!!', $_ENV['DB_ENCRYPTION_KEY_VERSION'] ?? 'v2-2026'); // v1.2.0: key from ENV + version

$adminId  = $users->insert(['name' => 'Admin',  'email' => 'admin@test.com',  'role' => 'admin']);
$editorId = $users->insert(['name' => 'Editor', 'email' => 'editor@test.com', 'role' => 'editor']);
$viewerId = $users->insert(['name' => 'Viewer', 'email' => 'viewer@test.com', 'role' => 'viewer']);

echo "Inserted 3 users with prefix IDs\n";

// ── Read Custom Config ────────────────────────────────────
sub('Read Custom Config');

$perms = $users->getCustomConfig('permissions');
echo "Admin permissions: " . implode(', ', $perms['admin']) . "\n";
echo "Theme: " . $users->getCustomConfig('theme') . "\n";
echo "Max login: " . $users->getCustomConfig('max_login_attempts') . "\n";

// ── Use Custom Config for Authorization ───────────────────
sub('Permission Check');

function checkPermission($users, $userId, $action): bool
{
    $user = $users->findOne(['_id' => $userId]);
    if (!$user) return false;

    $perms = $users->getCustomConfig('permissions', []);
    $role = $user['role'] ?? 'viewer';
    $allowed = $perms[$role] ?? [];

    return in_array($action, $allowed);
}

echo "Admin can delete: " . (checkPermission($users, $adminId, 'delete') ? 'yes' : 'no') . "\n";
echo "Editor can delete: " . (checkPermission($users, $editorId, 'delete') ? 'yes' : 'no') . "\n";
echo "Viewer can read: " . (checkPermission($users, $viewerId, 'read') ? 'yes' : 'no') . "\n";
echo "Viewer can create: " . (checkPermission($users, $viewerId, 'create') ? 'yes' : 'no') . "\n";

// ── Update Custom Config ──────────────────────────────────
sub('Update Custom Config');

$users->setCustomConfig('theme', 'light');
$users->setCustomConfigArray(['feature_flags' => ['dark_mode' => true, 'beta_ui' => false]]);
$users->saveConfiguration();
echo "Config updated and saved\n";

// ── Config Persistence Test ───────────────────────────────
sub('Config Persistence - Reconnect');

$client->close();

// Reconnect ke path yang sama
$client2 = new Client($examplePath);
$db2 = $client2->selectDB('config_app');
$users2 = $db2->selectCollection('users');

// Config otomatis dimuat dari database
$persistedTheme = $users2->getCustomConfig('theme');
echo "Persisted theme: {$persistedTheme}\n";

$allConfig = $users2->getAllCustomConfig();
echo "Custom config keys: " . implode(', ', array_keys($allConfig)) . "\n";

// ── Database-level Config Management ──────────────────────
sub('Database-level Config');

$config = $db2->loadCollectionConfig('users');
echo "ID mode (persisted format): {$config['id_mode']}\n";
echo "Soft deletes: " . ($config['soft_deletes_enabled'] ? 'yes' : 'no') . "\n";

$allConfigs = $db2->getAllCollectionConfigs();
echo "All configured collections: " . implode(', ', array_keys($allConfigs)) . "\n";

@$client2->close();
echo "\nDone!\n";
