<?php

/**
 * Contoh 06: Hooks (Event System)
 *
 * Event hooks untuk intercept operasi: beforeInsert, afterInsert,
 * beforeUpdate, afterUpdate, beforeRemove, afterRemove.
 * Hook chaining, veto operation, dan auto-timestamps.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sep('Contoh 06: Hooks (Event System)');

$client = createIsolatedClient('example06');
$db = $client->createDB('hooked_app');
$users = $db->createCollection('users');

// ── beforeInsert: Auto timestamps & defaults ──────────────
sub('beforeInsert - Auto Timestamps & Defaults');

$users->on('beforeInsert', function ($doc) {
    $doc['created_at'] = date('c');
    $doc['updated_at'] = date('c');
    $doc['status'] = $doc['status'] ?? 'pending';
    return $doc;
});

$users->on('afterInsert', function ($doc, $id) {
    echo "  [afterInsert] Created: {$id}\n";
});

$id = $users->insert(['name' => 'User 1', 'email' => 'u1@test.com']);
$user = $users->findOne(['_id' => $id]);
echo "Has auto created_at: " . isset($user['created_at']) . "\n";
echo "Has default status: {$user['status']}\n";

// ── beforeUpdate: Auto updated_at ─────────────────────────
sub('beforeUpdate - Auto Updated At');

$users->on('beforeUpdate', function ($criteria, $data) {
    if (!isset($data['$set'])) {
        $data['$set'] = [];
    }
    $data['$set']['updated_at'] = date('c');
    return [$criteria, $data];
});

$users->update(['_id' => $id], ['$set' => ['status' => 'active']]);
$updated = $users->findOne(['_id' => $id]);
echo "updated_at changed: " . ($updated['updated_at'] !== $updated['created_at'] ? 'yes' : 'no') . "\n";

// ── beforeRemove: Veto delete (protect admin) ─────────────
sub('beforeRemove - Veto Delete');

$users->on('beforeRemove', function ($doc) {
    if (($doc['role'] ?? '') === 'admin') {
        echo "  [VETO] Cannot delete admin: {$doc['name']}\n";
        return false; // Cancel deletion
    }
    return true;
});

$adminId = $users->insert(['name' => 'Admin', 'email' => 'admin@test.com', 'role' => 'admin']);
$userId2 = $users->insert(['name' => 'Normal', 'email' => 'normal@test.com', 'role' => 'user']);

echo "Attempt delete admin:\n";
$users->remove(['_id' => $adminId]); // Vetoed

echo "Attempt delete normal user:\n";
$users->remove(['_id' => $userId2]); // OK

echo "Remaining users: " . $users->count() . "\n";

// ── Hook Chaining (multiple hooks per event) ──────────────
sub('Hook Chaining - Multiple Hooks');

$logs = [];

$users->on('beforeInsert', function ($doc) use (&$logs) {
    $logs[] = 'Hook A: Normalize name';
    $doc['name'] = trim(ucwords(strtolower($doc['name'])));
    return $doc;
});

$users->on('beforeInsert', function ($doc) use (&$logs) {
    $logs[] = 'Hook B: Add metadata';
    $doc['_source'] = 'web_form';
    return $doc;
});

$id2 = $users->insert(['name' => '  test USER  ']);
$result = $users->findOne(['_id' => $id2]);
echo "Name normalized: '{$result['name']}'\n";
echo "Metadata added: {$result['_source']}\n";
echo "Hooks executed: " . implode(', ', $logs) . "\n";

// ── Remove Hook ───────────────────────────────────────────
sub('Remove Hook');

$users->off('beforeInsert');
echo "All beforeInsert hooks removed\n";

// ── All Hook Events ───────────────────────────────────────
sub('All Hook Events Reference');

$events = [
    'beforeInsert',
    'afterInsert',
    'beforeUpdate',
    'afterUpdate',
    'beforeRemove',
    'afterRemove',
];
echo "Available events: " . implode(', ', $events) . "\n";

echo "\nHook Return Values:\n";
echo "  - Return array → modify data\n";
echo "  - Return false → cancel operation (veto)\n";
echo "  - Return true/null → continue without change\n";

@$client->close();
echo "\nDone!\n";
