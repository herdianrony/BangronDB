<?php

/**
 * Contoh 11: Multiple Databases & Cross-DB Operations
 *
 * Multiple databases dalam satu client, data isolation,
 * cross-database populate, dan attach/detach.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sep('Contoh 11: Multiple Databases & Cross-DB Operations');

$client = createIsolatedClient('example11');

// ── Create Multiple Databases ─────────────────────────────
sub('Multiple Databases');

$appDb       = $client->createDB('app');
$logsDb      = $client->createDB('logs');
$analyticsDb = $client->createDB('analytics');

$appUsers = $appDb->createCollection('users');
$logActions = $logsDb->createCollection('actions');
$visits = $analyticsDb->createCollection('visits');

// App DB: users
$appUsers->insert(['name' => 'Alice', 'role' => 'admin']);
$appUsers->insert(['name' => 'Bob',   'role' => 'user']);

// Logs DB: audit trail
$logActions->insert(['action' => 'login',  'user' => 'Alice', 'timestamp' => date('c')]);
$logActions->insert(['action' => 'upload', 'user' => 'Bob',   'timestamp' => date('c')]);

// Analytics DB: metrics
$visits->insert(['page' => '/home',   'count' => 150]);
$visits->insert(['page' => '/about',  'count' => 45]);

echo "3 databases created with data\n";

// ── List Databases ────────────────────────────────────────
sub('List Databases');

$databases = $client->listDBs();
echo "Databases: " . implode(', ', $databases) . "\n";

// ── Data Isolation ────────────────────────────────────────
sub('Data Isolation');

echo "App users: " . $appUsers->count() . "\n";
echo "Log entries: " . $logActions->count() . "\n";
echo "Analytics records: " . $visits->count() . "\n";

// ── Cross-Database Populate ───────────────────────────────
sub('Cross-Database Populate');

// Profiles di database terpisah
$profilesDb = $client->createDB('profiles');
$profiles = $profilesDb->createCollection('profiles');
$profiles->insert([
    'user_id' => $appUsers->findOne(['name' => 'Alice'])['_id'],
    'bio'     => 'System Administrator',
]);
$profiles->insert([
    'user_id' => $appUsers->findOne(['name' => 'Bob'])['_id'],
    'bio'     => 'Content Creator',
]);

$profilesList = $profiles->find()->toArray();
$withUser = $profiles->populate($profilesList, 'user_id', 'app.users', '_id', 'user');

foreach ($withUser as $p) {
    echo "Profile: {$p['bio']} → {$p['user']['name']}\n";
}

// ── Cross-Database Notes ─────────────────────────────────
sub('Cross-Database Notes');

echo "Cross-database populate tersedia melalui notation database.collection\n";
echo "Contoh: 'app.users' dipakai di populate() pada demo di atas\n";

@$client->close();
echo "\nDone!\n";
