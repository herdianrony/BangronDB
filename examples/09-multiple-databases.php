<?php

/**
 * Contoh 09: Multiple Databases.
 *
 * Demonstrasi pengelolaan multiple databases dalam satu client.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

echo "=== Contoh 09: Multiple Databases ===\n\n";

// Buat client dengan path database lokal
$path = __DIR__ . '/data/multi_db_demo';
// Create directory if it doesn't exist
if (!is_dir($path)) {
    mkdir($path, 0755, true);
}
$client = new Client($path);

echo "1. Create multiple databases\n";
echo "-----------------------------\n";

$appDb = $client->selectDB('app');
$logsDb = $client->selectDB('logs');
$analyticsDb = $client->selectDB('analytics');

echo "Databases created:\n";
echo "- app\n";
echo "- logs\n";
echo "- analytics\n\n";

echo "2. List databases\n";
echo "-----------------\n";

$databases = $client->listDBs();
print_r($databases);

echo "\n3. Use different databases\n";
echo "---------------------------\n";

// Insert ke app database
$appUsers = $appDb->users;
$appUsers->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);
echo "User added to app database\n";

// Insert ke logs database
$logsDb->actions->insert([
    'action' => 'login',
    'user_id' => 'user123',
    'timestamp' => date('c'),
]);
echo "Log entry added to logs database\n";

// Insert ke analytics database
$analyticsDb->visits->insert([
    'page' => '/home',
    'visits' => 100,
    'date' => date('Y-m-d'),
]);
echo "Analytics data added to analytics database\n";

echo "\n4. Data isolation\n";
echo "------------------\n";

echo 'Users in app database: ' . $appDb->users->count() . "\n";
echo 'Entries in logs database: ' . $logsDb->actions->count() . "\n";
echo 'Records in analytics database: ' . $analyticsDb->visits->count() . "\n";

echo "\n=== Cleanup ===\n";
$client->close();
echo "Client closed.\n";
