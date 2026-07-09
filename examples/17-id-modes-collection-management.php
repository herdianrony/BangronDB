<?php

/**
 * Contoh 12: ID Generation Modes & Collection Management
 *
 * UUID auto, manual ID, prefix-based ID, rename collection,
 * drop collection, dan collection management.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sep('Contoh 12: ID Generation Modes & Collection Management');

$client = createIsolatedClient('example12');
$db = $client->createDB('id_demo');

// ── Auto UUID (Default) ───────────────────────────────────
sub('Mode 1: Auto UUID (Default)');

$users = $db->createCollection('users');
// setIdModeAuto() is default, no need to call
$userId = $users->insert(['name' => 'Auto User']);
echo "UUID: {$userId}\n";

// ── Manual ID ─────────────────────────────────────────────
sub('Mode 2: Manual ID');

$categories = $db->createCollection('categories');
$categories->setIdModeManual();

$cat1 = $categories->insert(['_id' => 'electronics', 'label' => 'Electronics']);
$cat2 = $categories->insert(['_id' => 'books',       'label' => 'Books']);
echo "Manual ID: {$cat1}, {$cat2}\n";

// ── Prefix ID ─────────────────────────────────────────────
sub('Mode 3: Prefix-based Auto Increment');

$orders = $db->createCollection('orders');
$orders->setIdModePrefix('ORD');

$ord1 = $orders->insert(['total' => 150.00, 'status' => 'pending']);
$ord2 = $orders->insert(['total' => 250.00, 'status' => 'completed']);
$ord3 = $orders->insert(['total' => 99.99,  'status' => 'pending']);
echo "Order 1: {$ord1}\n";
echo "Order 2: {$ord2}\n";
echo "Order 3: {$ord3}\n";

// ── Collection Management ─────────────────────────────────
sub('Collection Management');

// List collections
$collNames = $db->getCollectionNames();
echo "Collections: " . implode(', ', $collNames) . "\n";

// Rename collection
$success = $orders->renameCollection('sales');
echo "Rename orders → sales: " . ($success ? 'success' : 'failed') . "\n";

// Verify rename
$collNames = $db->getCollectionNames();
echo "Collections after rename: " . implode(', ', $collNames) . "\n";

// Drop collection
try {
    $db->dropCollection('categories');
    echo "Dropped 'categories'\n";
} catch (Throwable $e) {
    echo "Drop collection skipped: {$e->getMessage()}\n";
}

$collNames = $db->getCollectionNames();
echo "Collections after drop: " . implode(', ', $collNames) . "\n";

@$client->close();
echo "\nDone!\n";
