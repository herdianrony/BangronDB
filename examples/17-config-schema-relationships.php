<?php

/**
 * Contoh 17: Schema dengan Relasi Data (Cross-Database).
 *
 * Demonstrasi bagaimana menyimpan relasi di schema collection,
 * bukan di custom_config.
 *
 * ALASAN:
 * - Schema = validasi struktur data (apa yang MASUK)
 * - Relationships = metadata relasi (bagaimana menavigasi data KELUAR)
 * - Keduanya tentang metadata collection, jadi lebih konsisten
 *   jika disimpan bersama di schema
 */

require_once __DIR__.'/bootstrap.php';

use BangronDB\Client;

echo "=== Contoh 17: Schema dengan Relasi Data ===\n\n";

// Setup client
$client = createIsolatedClient('schema_relationships_demo');

// ============================================
// 1. Multi-Database Architecture
// ============================================
echo "1. Multi-Database Architecture\n";
echo "------------------------------\n";

// Master database - Contains reference data
$masterDb = $client->selectDB('master');
$users = $masterDb->users;
$categories = $masterDb->categories;

// Transaction database - Contains transactional data
$transactionDb = $client->selectDB('transactions');
$orders = $transactionDb->orders;
$orderItems = $transactionDb->order_items;

// Inventory database - Contains product data
$inventoryDb = $client->selectDB('inventory');
$products = $inventoryDb->products;

// ============================================
// 2. Define Schema with Relationships
// ============================================
echo "\n2. Define Schema with Relationships\n";
echo "------------------------------------\n";

// Products schema dengan relationships di dalam schema
$products->setSchema([
    'name' => ['type' => 'string', 'required' => true],
    'sku' => ['type' => 'string', 'required' => true],
    'price' => ['type' => 'number', 'min' => 0],
    'category_id' => ['type' => 'string'],  // FK ke master.categories
    'created_by' => ['type' => 'string'],   // FK ke master.users
    'stock' => ['type' => 'integer', 'min' => 0],
    // Relationships disimpan di schema['_relationships']
    '_relationships' => [
        'category' => [
            'type' => 'belongs_to',
            'collection' => 'categories',
            'database' => 'master',
            'foreign_key' => 'category_id',
        ],
        'created_by' => [
            'type' => 'belongs_to',
            'collection' => 'users',
            'database' => 'master',
            'foreign_key' => 'created_by',
        ],
    ],
]);
$products->createIndex('category_id');
$products->createIndex('created_by');
$products->saveConfiguration();

// Orders schema dengan relationships
$orders->setSchema([
    'order_number' => ['type' => 'string', 'required' => true],
    'customer_id' => ['type' => 'string'],  // FK ke master.users
    'order_date' => ['type' => 'string'],
    'status' => ['type' => 'string', 'enum' => ['pending', 'processing', 'completed', 'cancelled']],
    'total' => ['type' => 'number', 'min' => 0],
    '_relationships' => [
        'customer' => [
            'type' => 'belongs_to',
            'collection' => 'users',
            'database' => 'master',
            'foreign_key' => 'customer_id',
        ],
    ],
]);
$orders->createIndex('customer_id');
$orders->createIndex('order_number');
$orders->saveConfiguration();

// Order Items schema dengan relationships
$orderItems->setSchema([
    'order_id' => ['type' => 'string'],   // FK ke transactions.orders
    'product_id' => ['type' => 'string'],  // FK ke inventory.products
    'quantity' => ['type' => 'integer', 'min' => 1],
    'unit_price' => ['type' => 'number', 'min' => 0],
    '_relationships' => [
        'order' => [
            'type' => 'belongs_to',
            'collection' => 'orders',
            'database' => 'transactions',
            'foreign_key' => 'order_id',
        ],
        'product' => [
            'type' => 'belongs_to',
            'collection' => 'products',
            'database' => 'inventory',
            'foreign_key' => 'product_id',
        ],
    ],
]);
$orderItems->createIndex('order_id');
$orderItems->createIndex('product_id');
$orderItems->saveConfiguration();

echo "Schemas saved with relationships:\n";
echo "- products: category, created_by (master database)\n";
echo "- orders: customer (master database)\n";
echo "- order_items: order (transactions), product (inventory)\n";

// ============================================
// 3. Insert Reference Data
// ============================================
echo "\n3. Insert Reference Data in Master DB\n";
echo "------------------------------------\n";

$adminId = $users->insert([
    'name' => 'Admin User',
    'email' => 'admin@company.com',
    'role' => 'admin',
]);
echo "Created user: Admin User\n";

$customerId = $users->insert([
    'name' => 'John Customer',
    'email' => 'john@customer.com',
    'role' => 'customer',
]);
echo "Created user: John Customer\n";

$laptopCatId = $categories->insert([
    'name' => 'Laptops',
    'code' => 'LAPTOP',
    'description' => 'Portable computers',
]);
echo "Created category: Laptops\n";

$accessoryCatId = $categories->insert([
    'name' => 'Accessories',
    'code' => 'ACCESS',
    'description' => 'Computer accessories',
]);
echo "Created category: Accessories\n";

// ============================================
// 4. Insert Products
// ============================================
echo "\n4. Insert Products in Inventory DB\n";
echo "----------------------------------\n";

$product1Id = $products->insert([
    'name' => 'MacBook Pro 14"',
    'sku' => 'MBP14-001',
    'price' => 1999.99,
    'category_id' => $laptopCatId,
    'created_by' => $adminId,
    'stock' => 10,
]);
echo "Created: MacBook Pro 14\" (Stock: 10)\n";

$product2Id = $products->insert([
    'name' => 'USB-C Hub',
    'sku' => 'USBHUB-001',
    'price' => 49.99,
    'category_id' => $accessoryCatId,
    'created_by' => $adminId,
    'stock' => 100,
]);
echo "Created: USB-C Hub (Stock: 100)\n";

// ============================================
// 5. Create Order
// ============================================
echo "\n5. Create Order with Cross-Database References\n";
echo "----------------------------------------------\n";

$orderId = $orders->insert([
    'order_number' => 'ORD-2026-001',
    'customer_id' => $customerId,
    'order_date' => date('Y-m-d'),
    'status' => 'pending',
    'total' => 0,
]);
echo "Created order: ORD-2026-001\n";

$orderItems->insert([
    'order_id' => $orderId,
    'product_id' => $product1Id,
    'quantity' => 1,
    'unit_price' => 1999.99,
]);
echo "Added: MacBook Pro 14\" x1\n";

$orderItems->insert([
    'order_id' => $orderId,
    'product_id' => $product2Id,
    'quantity' => 2,
    'unit_price' => 49.99,
]);
echo "Added: USB-C Hub x2\n";

$orders->update(['_id' => $orderId], [
    'total' => 1999.99 + (2 * 49.99),
]);

// ============================================
// 6. Query and Populate
// ============================================
echo "\n6. Query and Populate Relationships\n";
echo "------------------------------------\n";

// Get order with customer
$order = $orders->findOne(['_id' => $orderId]);
$orderWithCustomer = $orders->populate($order, 'customer_id', 'master.users', '_id', 'customer');

echo "Order ORD-2026-001:\n";
echo '  Customer: '.($orderWithCustomer['customer']['name'] ?? 'N/A')."\n";
echo '  Status: '.$orderWithCustomer['status']."\n";
echo '  Total: $'.number_format($orderWithCustomer['total'], 2)."\n";

// Get items with products
$items = $orderItems->find(['order_id' => $orderId])->toArray();
$itemsWithProducts = $orderItems->populate($items, 'product_id', 'inventory.products', '_id', 'product');

echo "\nOrder Items:\n";
foreach ($itemsWithProducts as $item) {
    echo '  - '.($item['product']['name'] ?? 'Unknown');
    echo ' x'.$item['quantity'];
    echo ' = $'.number_format($item['quantity'] * $item['unit_price'], 2)."\n";
}

// Get products with category and creator
$productsList = $products->find()->toArray();
$withCategories = $products->populate($productsList, 'category_id', 'master.categories', '_id', 'category');
$withCreator = $products->populate($withCategories, 'created_by', 'master.users', '_id', 'creator');

echo "\nProducts:\n";
foreach ($withCreator as $p) {
    echo '  - '.$p['name'].' ['.($p['category']['name'] ?? 'N/A')."]\n";
    echo '    Created by: '.($p['creator']['name'] ?? 'N/A')."\n";
    echo '    Price: $'.number_format($p['price'], 2)."\n";
}

// ============================================
// 7. Query Schema with Relationships
// ============================================
echo "\n7. Schema with Relationships from _config\n";
echo "--------------------------------------\n";

$productsConfig = $products->database->loadCollectionConfig('products');
echo "Products schema (_config):\n";
print_r($productsConfig['schema'] ?? []);

// ============================================
// Summary
// ============================================
echo "\n=== Summary ===\n";
echo "ALASAN Relationships di schema (bukan custom_config):\n";
echo "  1. Schema = validasi data (apa yang MASUK)\n";
echo "  2. Relationships = metadata relasi (bagaimana menavigasi KELUAR)\n";
echo "  3. Keduanya tentang metadata collection -> konsisten jika disimpan bersama\n";
echo "  4. Tidak perlu custom_config terpisah\n\n";
echo "Cross-database references:\n";
echo "  - order_items.product_id -> inventory.products\n";
echo "  - order_items.order_id -> transactions.orders\n";
echo "  - products.category_id -> master.categories\n";
echo "  - products.created_by -> master.users\n";

// Cleanup
@$masterDb->drop();
@$transactionDb->drop();
@$inventoryDb->drop();
$client->close();
echo "\nCleanup done.\n";
