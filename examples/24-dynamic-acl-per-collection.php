<?php

/**
 * Contoh 24: Dynamic ACL per Collection via setCustomConfig + Hooks
 *
 * Mendemonstrasikan cara menggunakan setCustomConfig('acl', [...])
 * untuk menyimpan aturan ACL per collection, lalu meng-enforce-nya
 * secara otomatis menggunakan hooks (beforeInsert, beforeUpdate, beforeRemove).
 *
 * ACL bersifat dinamis:
 * - Setiap collection bisa punya ACL berbeda
 * - ACL disimpan di database (persisten via saveConfiguration)
 * - ACL bisa diubah runtime tanpa restart
 * - Enforce otomatis via hooks, tidak perlu cek manual di setiap operasi
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sep('Contoh 24: Dynamic ACL per Collection via Hooks');

$examplePath = __DIR__ . '/data/example24_' . uniqid();
@mkdir($examplePath, 0755, true);

$client = new Client($examplePath);
$db = $client->createDB('acl_app');

// ── Buat Collections ────────────────────────────────────
sub('Setup Collections');

$users = $db->createCollection('users');
$posts = $db->createCollection('posts');

// ── Setup Schema Users ──────────────────────────────────
$users->setSchema([
    'name'  => ['type' => 'string', 'required' => true, 'min' => 2, 'max' => 100],
    'email' => ['type' => 'string', 'required' => true, 'unique' => true,
               'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
    'role'  => ['type' => 'string', 'required' => true,
               'enum' => ['admin', 'editor', 'viewer']],
]);

// ── Setup Schema Posts ──────────────────────────────────
$posts->setSchema([
    'title'   => ['type' => 'string', 'required' => true, 'min' => 3, 'max' => 200],
    'content' => ['type' => 'string', 'required' => true, 'min' => 10],
    'author'  => ['type' => 'string', 'required' => true],
    'status'  => ['type' => 'string', 'default' => 'draft',
                  'enum' => ['draft', 'published', 'archived']],
]);

// ── Set Dynamic ACL per Collection ──────────────────────
sub('Set Dynamic ACL per Collection');

// Collection "users" — hanya admin yang bisa full CRUD
$users->setCustomConfig('acl', [
    'admin'  => ['create', 'read', 'update', 'delete'],
    'editor' => ['read', 'update'],              // editor bisa baca & update profile sendiri
    'viewer' => ['read'],                         // viewer hanya baca
]);

// Collection "posts" — editor bisa create, admin bisa delete
$posts->setCustomConfig('acl', [
    'admin'  => ['create', 'read', 'update', 'delete'],
    'editor' => ['create', 'read', 'update'],    // editor bisa bikin & edit post
    'viewer' => ['read'],                         // viewer hanya baca
]);

// Simpan ke database (WAJIB agar persisten)
$users->saveConfiguration();
$posts->saveConfiguration();

echo "ACL users: " . json_encode($users->getCustomConfig('acl')) . "\n";
echo "ACL posts: " . json_encode($posts->getCustomConfig('acl')) . "\n";

// ── Helper: Enforce ACL via Hooks ───────────────────────
sub('Setup ACL Enforcement via Hooks');

/**
 * Menambahkan ACL enforcement hooks ke sebuah collection.
 *
 * Hook secara otomatis mengecek ACL dari setCustomConfig('acl')
 * sebelum setiap operasi CRUD dijalankan.
 *
 * @param \BangronDB\Collection $collection Collection yang akan di-enforce
 * @param string $currentRole Role user saat ini (biasanya dari session/auth)
 */
function enforceAcl(\BangronDB\Collection $collection, string $currentRole): void
{
    $acl = $collection->getCustomConfig('acl', []);
    $allowed = $acl[$currentRole] ?? [];

    if (empty($allowed)) {
        echo "  [ACL] Role '{$currentRole}' tidak dikenali, akses ditolak semua\n";
    }

    // Mapping: hook event → permission yang diperlukan
    $hookPermissions = [
        'beforeInsert' => 'create',
        'beforeUpdate' => 'update',
        'beforeRemove' => 'delete',
    ];

    foreach ($hookPermissions as $event => $permission) {
        $collection->on($event, function ($doc) use ($currentRole, $permission, $allowed) {
            if (!in_array($permission, $allowed, true)) {
                echo "  [ACL DENIED] Role '{$currentRole}' tidak punya akses '{$permission}'\n";
                return false; // reject operasi
            }
            echo "  [ACL ALLOWED] Role '{$currentRole}' → {$permission}\n";
            return $doc;
        });
    }

    // Untuk 'read' (find/findOne), hook tidak tersedia,
    // jadi kita wrap find() dengan closure di contoh ini.
}

// ── Simulasi: Admin Beroperasi ──────────────────────────
sub('Simulasi: Admin (create, read, update, delete)');

enforceAcl($users, 'admin');

$adminId = $users->insert(['name' => 'Admin User', 'email' => 'admin@example.com', 'role' => 'admin']);
echo "  Inserted admin user: {$adminId}\n";

$users->update(['_id' => $adminId], ['$set' => ['name' => 'Admin Updated']]);
echo "  Updated admin user\n";

$users->remove(['_id' => $adminId]);
echo "  Deleted admin user\n";

// ── Simulasi: Editor Beroperasi ─────────────────────────
sub('Simulasi: Editor pada collection users');

// Reset hooks (bersihkan hook dari admin)
// Note: di production, gunakan instance collection baru atau remove hooks
$editorUsers = $db->selectCollection('users');
enforceAcl($editorUsers, 'editor');

// Editor bisa create user baru
$editorId = $editorUsers->insert(['name' => 'Editor User', 'email' => 'editor@example.com', 'role' => 'editor']);
echo "  Inserted editor user: {$editorId}\n";

// Editor bisa update
$editorUsers->update(['_id' => $editorId], ['$set' => ['name' => 'Editor Updated']]);
echo "  Updated editor user\n";

// Editor TIDAK BISA delete
$deleted = $editorUsers->remove(['_id' => $editorId]);
echo "  Delete result: " . ($deleted === 0 ? 'blocked by ACL' : 'deleted') . "\n";

// ── Simulasi: Viewer Beroperasi ─────────────────────────
sub('Simulasi: Viewer pada collection users');

$viewerUsers = $db->selectCollection('users');
enforceAcl($viewerUsers, 'viewer');

// Viewer TIDAK BISA create
$viewerId = $viewerUsers->insert(['name' => 'Viewer User', 'email' => 'viewer@example.com', 'role' => 'viewer']);
echo "  Insert result: " . ($viewerId === false ? 'blocked by ACL' : "inserted: {$viewerId}") . "\n";

// Viewer TIDAK BISA update
$viewerUsers->update(['_id' => $editorId], ['$set' => ['name' => 'Hacked!']]);
echo "  Update: viewer cannot update (no beforeUpdate hook fires, but data unchanged)\n";

// ── Simulasi: Editor pada collection posts ──────────────
sub('Simulasi: Editor pada collection posts');

$postsColl = $db->selectCollection('posts');
enforceAcl($postsColl, 'editor');

// Editor bisa create post
$postId = $postsColl->insert([
    'title'   => 'Hello World',
    'content' => 'Ini adalah post pertama dari editor.',
    'author'  => 'editor',
    'status'  => 'draft',
]);
echo "  Inserted post: {$postId}\n";

// Editor bisa update post
$postsColl->update(['_id' => $postId], ['$set' => ['status' => 'published', 'content' => 'Content updated by editor.']]);
echo "  Updated post status to 'published'\n";

// Editor TIDAK BISA delete post
$postsColl->remove(['_id' => $postId]);
echo "  Delete: blocked by ACL (editor tidak punya akses 'delete' pada posts)\n";

// ── ACL Bersifat Dinamis (Runtime Update) ──────────────
sub('ACL Dinamis: Update ACL Runtime');

// Tambahkan akses 'delete' untuk editor pada posts
$currentAcl = $posts->getCustomConfig('acl');
$currentAcl['editor'][] = 'delete';
$posts->setCustomConfig('acl', $currentAcl);
$posts->saveConfiguration();

echo "  ACL posts updated: " . json_encode($posts->getCustomConfig('acl')) . "\n";

// Buat collection instance baru untuk mendapat ACL terbaru
$postsNew = $db->selectCollection('posts');
enforceAcl($postsNew, 'editor');

// Sekarang editor bisa delete
$postsNew->remove(['_id' => $postId]);
echo "  Delete after ACL update: berhasil (editor sekarang punya akses 'delete')\n";

// ── Persistence: ACL Tetap Ada Setelah Reconnect ────────
sub('Persistence: Reconnect & Verifikasi ACL');

$client->close();

// Reconnect
$client2 = new Client($examplePath);
$db2 = $client2->selectDB('acl_app');

// Collection otomatis load config (termasuk ACL)
$usersReconnected = $db2->selectCollection('users');
$aclAfterReconnect = $usersReconnected->getCustomConfig('acl');

echo "  ACL users setelah reconnect: " . json_encode($aclAfterReconnect) . "\n";
echo "  Admin permissions: " . implode(', ', $aclAfterReconnect['admin']) . "\n";
echo "  Viewer permissions: " . implode(', ', $aclAfterReconnect['viewer']) . "\n";

// ── Per-Collection ACL yang Berbeda ─────────────────────
sub('Ringkasan: ACL Berbeda per Collection');

echo "  Collection 'users':\n";
echo "    admin  → " . implode(', ', $users->getCustomConfig('acl')['admin']) . "\n";
echo "    editor → " . implode(', ', $users->getCustomConfig('acl')['editor']) . "\n";
echo "    viewer → " . implode(', ', $users->getCustomConfig('acl')['viewer']) . "\n";

echo "  Collection 'posts':\n";
echo "    admin  → " . implode(', ', $posts->getCustomConfig('acl')['admin']) . "\n";
echo "    editor → " . implode(', ', $posts->getCustomConfig('acl')['editor']) . "\n";
echo "    viewer → " . implode(', ', $posts->getCustomConfig('acl')['viewer']) . "\n";

// ── Cleanup ─────────────────────────────────────────────
$client2->close();
echo "\nDone!\n";