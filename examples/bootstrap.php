<?php

/**
 * Bootstrap file untuk semua contoh.
 * Menyediakan autoloading dan setup common.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use BangronDB\Client;
use BangronDB\Config;

// Use local data directory for examples
$exampleDataPath = __DIR__ . '/data';
if (!is_dir($exampleDataPath)) {
    mkdir($exampleDataPath, 0755, true);
}
Config::set('default_path', $exampleDataPath);

// Helper function untuk membuat client dengan database isolated
function createIsolatedClient(string $name = 'test', array $options = []): Client
{
    // Use global exampleDataPath for consistency
    global $exampleDataPath;
    $path = $exampleDataPath . '/' . $name . '_' . uniqid();

    // Create directory if it doesn't exist (needed for nested database files)
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }

    return new Client($path, $options);
}

// Helper function untuk cleanup database
function cleanupDatabase(Client $client, string $dbName): void
{
    $db = $client->selectDB($dbName);
    foreach ($db->getCollectionNames() as $collection) {
        $db->selectCollection($collection)->drop();
    }
}
