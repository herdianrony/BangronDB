<?php
declare(strict_types=1);

require __DIR__ . '/../BangronDB/vendor/autoload.php';
require __DIR__ . '/SecureClientFactory.php';

/**
 * Migrasi blind index SHA-256 lama -> HMAC-SHA256 berkunci
 * Jalankan SEKALI setelah upgrade ke BangronDB 1.0.0
 * 
 * php migrate_blind_index.php
 */

$client = SecureClientFactory::create();

$toMigrate = [
    'app' => [
        'users' => ['email', 'phone'],
        // 'members' => ['email'],
    ],
];

foreach ($toMigrate as $dbName => $collections) {
    if (!$client->dbExists($dbName)) {
        echo "Skip DB $dbName - tidak ada\n";
        continue;
    }
    foreach ($collections as $collectionName => $fields) {
        // v1.2.0: encryption key + version sudah di-set via SecureClientFactory / Client options
        if (!$client->collectionExists($dbName, $collectionName)) {
            echo "Skip $dbName.$collectionName - tidak ada\n";
            continue;
        }
        $col = $client->selectCollection($dbName, $collectionName);
        SecureClientFactory::applySearchableFields($col, $collectionName);

        foreach ($fields as $field) {
            echo "Rehashing $dbName.$collectionName.$field ... ";
            $count = $col->rehashSearchableField($field);
            echo "$count rows updated\n";
        }
    }
}

echo "Migration selesai.\n";
$client->close();
