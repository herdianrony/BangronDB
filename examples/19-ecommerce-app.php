<?php

/**
 * Contoh 14: Real-World Application - E-Commerce
 *
 * Skenario lengkap: user management, product catalog,
 * shopping cart, order processing, dengan semua fitur
 * BangronDB digabungkan.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sep('Contoh 14: E-Commerce Application');

$client = createIsolatedClient('example14');
$db = $client->createDB('ecommerce');

// ── Setup Collections ─────────────────────────────────────
sub('Setup: Schema, Hooks, Encryption');

// Products
$products = $db->createCollection('products');
$products->setIdModePrefix('PRD');
$products->setSchema([
    'name'  => ['type' => 'string', 'required' => true, 'min' => 2],
    'price' => ['type' => 'int', 'required' => true, 'min' => 1],
    'stock' => ['type' => 'int', 'min' => 0],
    'category' => ['type' => 'string'],
]);
$products->setSearchableFields(['name'], false);
$products->createIndex('category');
$products->saveConfiguration();

// Users
$users = $db->createCollection('users');
$users->setIdModePrefix('USR');
$users->setSchema([
    'name'  => ['type' => 'string', 'required' => true],
    'email' => ['type' => 'string', 'required' => true],
    'role'  => ['type' => 'string', 'enum' => ['customer', 'seller', 'admin']],
]);
$users->setSearchableFields(['email'], true);
$users->useSoftDeletes(true);
$users->on('beforeInsert', function ($doc) {
    $doc['created_at'] = date('c');
    return $doc;
});
$users->saveConfiguration();

// Orders
$orders = $db->createCollection('orders');
$orders->setIdModePrefix('ORD');
$orders->setEncryptionKey($_ENV['DB_ENCRYPTION_KEY'] ?? 'ecommerce-encryption-key-32char!!!', $_ENV['DB_ENCRYPTION_KEY_VERSION'] ?? 'v2-2026'); // v1.2.0
$orders->on('beforeInsert', function ($doc) {
    $doc['created_at'] = date('c');
    $doc['status'] = $doc['status'] ?? 'pending';
    return $doc;
});
$orders->saveConfiguration();

echo "Collections configured with schemas, hooks, encryption\n";

// ── Add Products ──────────────────────────────────────────
sub('Add Products');

$products->insert([
    ['name' => 'Laptop Pro',    'price' => 15000000, 'stock' => 10, 'category' => 'electronics'],
    ['name' => 'Wireless Mouse', 'price' => 250000,   'stock' => 50, 'category' => 'accessories'],
    ['name' => 'USB-C Hub',     'price' => 450000,   'stock' => 30, 'category' => 'accessories'],
    ['name' => 'Mechanical KB',  'price' => 1200000,  'stock' => 20, 'category' => 'accessories'],
    ['name' => 'Monitor 27"',   'price' => 5000000,  'stock' => 5,  'category' => 'electronics'],
]);
echo "5 products added\n";

// ── Register Users ────────────────────────────────────────
sub('Register Users');

$customer1 = $users->insert(['name' => 'Budi', 'email' => 'budi@mail.com', 'role' => 'customer']);
$customer2 = $users->insert(['name' => 'Sari', 'email' => 'sari@mail.com', 'role' => 'customer']);
$seller    = $users->insert(['name' => 'Toko Jaya', 'email' => 'toko@mail.com', 'role' => 'seller']);
echo "3 users registered\n";

// ── Search Products ───────────────────────────────────────
sub('Search & Filter Products');

$accessories = $products->find(['category' => 'accessories'])->sort(['price' => 1])->toArray();
echo "Accessories (sorted by price):\n";
foreach ($accessories as $p) {
    echo "  - {$p['name']}: Rp " . number_format($p['price']) . "\n";
}

$affordable = $products->find(['price' => ['$lte' => 500000]])->toArray();
echo "Under Rp 500k: " . count($affordable) . " products\n";

// ── Place Orders ──────────────────────────────────────────
sub('Place Orders');

// Transaction untuk order
$db->connection->beginTransaction();
try {
    $order1 = $orders->insert([
        'user_id'  => $customer1,
        'items'    => [
            ['product' => 'Laptop Pro', 'qty' => 1, 'price' => 15000000],
            ['product' => 'Wireless Mouse', 'qty' => 2, 'price' => 250000],
        ],
        'total'    => 15500000,
        'status'   => 'pending',
        'payment'  => 'credit_card_4111',
    ]);

    // Reduce stock
    $products->update(['name' => 'Laptop Pro'], ['$set' => ['stock' => 9]]);
    $products->update(['name' => 'Wireless Mouse'], ['$set' => ['stock' => 48]]);

    $db->connection->commit();
    echo "Order placed: {$order1}\n";
} catch (Exception $e) {
    $db->connection->rollBack();
    echo "Order failed: {$e->getMessage()}\n";
}

// ── Process Order ─────────────────────────────────────────
sub('Process Order (Update Status)');

$orders->update(['_id' => $order1], ['$set' => ['status' => 'shipped']]);
$order = $orders->findOne(['_id' => $order1]);
echo "Order status: {$order['status']}\n";
echo "Payment (encrypted): " . substr($order['payment'] ?? '', 0, 10) . "...\n";

// ── View Orders with User Info ────────────────────────────
sub('Orders with User (Populate)');

$ordersList = $orders->find()->toArray();
$withUser = $orders->populate($ordersList, 'user_id', 'users', '_id', 'customer');

foreach ($withUser as $o) {
    $name = $o['customer']['name'] ?? 'Unknown';
    echo "Order {$o['_id']}: {$name} - Rp " . number_format($o['total']) . " [{$o['status']}]\n";
}

// ── Soft Delete User & Restore ────────────────────────────
sub('Soft Delete & Restore');

$users->remove(['_id' => $customer2]);
echo "Deleted Sari. Active users: " . $users->count() . "\n";

$users->restore(['_id' => $customer2]);
echo "Restored Sari. Active users: " . $users->count() . "\n";

// ── Health Check ──────────────────────────────────────────
sub('Health Check');

$report = $db->getHealthReport();
echo "DB Status: {$report['status']}\n";
echo "Total products: " . $products->count() . "\n";
echo "Total users: " . $users->count() . "\n";
echo "Total orders: " . $orders->count() . "\n";

@$client->close();
echo "\nDone!\n";
