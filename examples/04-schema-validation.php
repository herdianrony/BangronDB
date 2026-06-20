<?php

/**
 * Contoh 04: Schema Validation
 *
 * Validasi data dengan type checking, required fields,
 * enum, regex, range (min/max), dan array constraints.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sep('Contoh 04: Schema Validation');

$client = createIsolatedClient('example04');
$db = $client->createDB('validated_app');
$users = $db->createCollection('users');

// ── Define Schema ─────────────────────────────────────────
sub('Setup Schema');

$users->setSchema([
    'username'  => ['type' => 'string', 'required' => true, 'min' => 3, 'max' => 50],
    'email'     => ['type' => 'string', 'required' => true, 'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
    'age'       => ['type' => 'int',    'min' => 13, 'max' => 120],
    'role'      => ['type' => 'string', 'required' => true, 'enum' => ['admin', 'user', 'moderator', 'guest']],
    'phone'     => ['type' => 'string', 'regex' => '/^\+?[0-9]{10,15}$/'],
    'tags'      => ['type' => 'array',  'max' => 10],
    'is_active' => ['type' => 'bool'],
]);
$users->saveConfiguration();
echo "Schema configured and saved\n";
echo "Catatan: validasi enum sekarang strict; nilai 0, false, dan '0' dianggap berbeda.\n";

// ── Valid Insert ───────────────────────────────────────────
sub('Valid Insert');

try {
    $id = $users->insert([
        'username'  => 'johndoe',
        'email'     => 'john@example.com',
        'age'       => 30,
        'role'      => 'admin',
        'phone'     => '+6281234567890',
        'tags'      => ['vip', 'premium'],
        'is_active' => true,
    ]);
    echo "OK: User created ({$id})\n";
} catch (Exception $e) {
    echo "ERROR: {$e->getMessage()}\n";
}

// ── Invalid Scenarios ──────────────────────────────────────
sub('Validation Errors - Berbagai Skenario');

$errors = [
    'Missing required'     => ['username' => 'jane'],  // no email, role
    'Invalid email'        => ['username' => 'bob', 'email' => 'not-an-email', 'role' => 'user'],
    'Invalid enum'         => ['username' => 'alice', 'email' => 'a@b.com', 'role' => 'superadmin'],
    'Age out of range'     => ['username' => 'kid', 'email' => 'k@c.com', 'role' => 'user', 'age' => 5],
    'Age too high'         => ['username' => 'old', 'email' => 'o@c.com', 'role' => 'user', 'age' => 200],
    'Username too short'   => ['username' => 'ab', 'email' => 'x@y.com', 'role' => 'user'],
    'Invalid phone'        => ['username' => 'phone', 'email' => 'p@c.com', 'role' => 'user', 'phone' => 'abc'],
    'Too many tags'        => ['username' => 'tags', 'email' => 't@c.com', 'role' => 'user', 'tags' => range(1, 12)],
];

foreach ($errors as $scenario => $data) {
    try {
        $users->insert($data);
        echo "UNEXPECTED: {$scenario} should have failed!\n";
    } catch (Exception $e) {
        $msg = str_replace("\n", ' ', substr($e->getMessage(), 0, 80));
        echo "EXPECTED: {$scenario} → {$msg}\n";
    }
}

// ── Partial Update dengan $set (tidak divalidasi) ─────────
sub('Update: $set tidak trigger validasi');

$users->update(['username' => 'johndoe'], ['$set' => ['age' => 999]]);
$user = $users->findOne(['username' => 'johndoe']);
echo "Age after \$set bypass: {$user['age']} (partial update tidak divalidasi)\n";

// ── Replace Update (divalidasi) ────────────────────────────
sub('Update: Replace divalidasi');

try {
    $users->update(['username' => 'johndoe'], ['username' => 'johnnew', 'email' => 'bad', 'role' => 'user'], false);
    echo "UNEXPECTED: should have failed\n";
} catch (Exception $e) {
    echo "OK: Replace update ditolak → " . substr($e->getMessage(), 0, 60) . "\n";
}

// ── Validate tanpa insert ──────────────────────────────────
sub('Standalone Validation');

try {
    $users->validate(['username' => 'test', 'email' => 'invalid']);
} catch (Exception $e) {
    echo "validate() throws: " . substr($e->getMessage(), 0, 60) . "\n";
}

@$client->close();
echo "\nDone!\n";
