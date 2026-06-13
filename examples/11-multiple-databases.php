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

$client = new Client(__DIR__ . '/data');

// ── Create Multiple Databases ─────────────────────────────
sub('Multiple Databases');

$appDb       = $client->selectDB('app');
$logsDb      = $client->selectDB('logs');
$analyticsDb = $client->selectDB('analytics');

// App DB: users
$appDb->users->insert(['name' => 'Alice', 'role' => 'admin']);
$appDb->users->insert(['name' => 'Bob',   'role' => 'user']);

// Logs DB: audit trail
$logsDb->actions->insert(['action' => 'login',  'user' => 'Alice', 'timestamp' => date('c')]);
$logsDb->actions->insert(['action' => 'upload', 'user' => 'Bob',   'timestamp' => date('c')]);

// Analytics DB: metrics
$analyticsDb->visits->insert(['page' => '/home',   'count' => 150]);
$analyticsDb->visits->insert(['page' => '/about',  'count' => 45]);

echo "3 databases created with data\n";

// ── List Databases ────────────────────────────────────────
sub('List Databases');

$databases = $client->listDBs();
echo "Databases: " . implode(', ', $databases) . "\n";

// ── Data Isolation ────────────────────────────────────────
sub('Data Isolation');

echo "App users: " . $appDb->users->count() . "\n";
echo "Log entries: " . $logsDb->actions->count() . "\n";
echo "Analytics records: " . $analyticsDb->visits->count() . "\n";

// ── Cross-Database Populate ───────────────────────────────
sub('Cross-Database Populate');

// Profiles di database terpisah
$profilesDb = $client->selectDB('profiles');
$profilesDb->profiles->insert([
    'user_id' => $appDb->users->findOne(['name' => 'Alice'])['_id'],
    'bio'     => 'System Administrator',
]);
$profilesDb->profiles->insert([
    'user_id' => $appDb->users->findOne(['name' => 'Bob'])['_id'],
    'bio'     => 'Content Creator',
]);

$profilesList = $profilesDb->profiles->find()->toArray();
$withUser = $profilesDb->profiles->populate($profilesList, 'user_id', 'app.users', '_id', 'user');

foreach ($withUser as $p) {
    echo "Profile: {$p['bio']} → {$p['user']['name']}\n";
}

// ── Attach/Detach Database ────────────────────────────────
sub('Attach/Detach Database');

// Attach memungkinkan query cross-database via SQL
echo "attach() and detach() available for advanced cross-database SQL queries\n";

@$client->close();
echo "\nDone!\n";
