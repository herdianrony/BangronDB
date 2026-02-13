<?php

/**
 * Contoh 16: Computer Store & Service Center.
 *
 * Demonstrasi BangronDB untuk toko komputer dengan:
 * - Inventory management (produk, stok, harga)
 * - Sales/penjualan dengan transaction
 * - Service/repair tracking
 * - Customer management
 * - Multi-database architecture
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;
use BangronDB\Database;

echo "=== Computer Store & Service Center ===\n\n";

// ============================================
// Setup Multiple Databases
// ============================================

echo "1. Setup Databases\n";
echo "------------------\n";

// Master database for products and catalog
$masterPath = __DIR__ . '/data/computer_store_master';
if (!is_dir($masterPath)) {
    mkdir($masterPath, 0755, true);
}
$masterClient = new Client($masterPath);
$masterDb = $masterClient->selectDB('store');

// Transaction database for sales and orders
$transactionPath = __DIR__ . '/data/computer_store_transaction';
if (!is_dir($transactionPath)) {
    mkdir($transactionPath, 0755, true);
}
$transactionClient = new Client($transactionPath);
$transactionDb = $transactionClient->selectDB('transaction');

echo "- Master DB: Products, Categories, Customers\n";
echo "- Transaction DB: Sales, Service Orders\n\n";

// ============================================
// Setup Master Database Collections
// ============================================

echo "2. Setup Master Collections\n";
echo "----------------------------\n";

$categories = $masterDb->categories;
$categories->setSchema([
    'name' => ['type' => 'string', 'required' => true],
    'code' => ['type' => 'string', 'required' => true],
    'description' => ['type' => 'string'],
]);
$categories->saveConfiguration();

$products = $masterDb->products;
$products->setSchema([
    'sku' => ['type' => 'string', 'required' => true],
    'name' => ['type' => 'string', 'required' => true],
    'category_id' => ['type' => 'string', 'required' => true],
    'price' => ['type' => 'number', 'required' => true],
    'cost' => ['type' => 'number', 'required' => true],
    'stock' => ['type' => 'int', 'min' => 0],
    'min_stock' => ['type' => 'int'],
    'brand' => ['type' => 'string'],
    'specs' => ['type' => 'object'],
]);
$products->saveConfiguration();

$customers = $masterDb->customers;
$customers->setEncryptionKey('customer-encryption-key-32-chars-at-least!!');
$customers->setSearchableFields(['phone'], true);
$customers->setSchema([
    'name' => ['type' => 'string', 'required' => true],
    'phone' => ['type' => 'string', 'required' => true],
    'email' => ['type' => 'string'],
    'address' => ['type' => 'string'],
]);
$customers->saveConfiguration();

echo "- Categories: Product categories\n";
echo "- Products: Inventory with schema validation\n";
echo "- Customers: Encrypted customer data\n\n";

// ============================================
// Setup Transaction Collections
// ============================================

echo "3. Setup Transaction Collections\n";
echo "------------------------------\n";

$sales = $transactionDb->sales;
$sales->setSchema([
    'invoice_number' => ['type' => 'string', 'required' => true],
    'customer_id' => ['type' => 'string'],
    'items' => ['type' => 'array', 'required' => true],
    'subtotal' => ['type' => 'number', 'required' => true],
    'tax' => ['type' => 'number'],
    'discount' => ['type' => 'number'],
    'total' => ['type' => 'number', 'required' => true],
    'payment_method' => ['enum' => ['cash', 'card', 'transfer']],
    'status' => ['enum' => ['pending', 'completed', 'cancelled', 'refunded']],
]);
$sales->saveConfiguration();

$services = $transactionDb->services;
$services->setEncryptionKey('service-encryption-key-32-chars-at-least!!');
$services->setSchema([
    'ticket_number' => ['type' => 'string', 'required' => true],
    'customer_id' => ['type' => 'string', 'required' => true],
    'device_type' => ['type' => 'string', 'required' => true],
    'brand' => ['type' => 'string'],
    'model' => ['type' => 'string'],
    'serial_number' => ['type' => 'string'],
    'problem' => ['type' => 'string', 'required' => true],
    'diagnosis' => ['type' => 'string'],
    'solution' => ['type' => 'string'],
    'estimated_cost' => ['type' => 'number'],
    'actual_cost' => ['type' => 'number'],
    'status' => ['enum' => ['received', 'diagnosing', 'repairing', 'waiting_parts', 'ready', 'delivered', 'cancelled']],
    'priority' => ['enum' => ['low', 'normal', 'high', 'urgent']],
]);
$services->saveConfiguration();

$service_parts = $transactionDb->service_parts;
$service_parts->setSchema([
    'service_id' => ['type' => 'string', 'required' => true],
    'product_id' => ['type' => 'string'],
    'part_name' => ['type' => 'string', 'required' => true],
    'quantity' => ['type' => 'int', 'required' => true],
    'unit_price' => ['type' => 'number', 'required' => true],
]);
$service_parts->saveConfiguration();

echo "- Sales: Sales transactions\n";
echo "- Services: Repair/service tickets (encrypted)\n";
echo "- Service Parts: Parts used in repairs\n\n";

// ============================================
// Insert Categories
// ============================================

echo "4. Insert Categories\n";
echo "--------------------\n";

$laptopCat = $categories->insert([
    'name' => 'Laptop',
    'code' => 'LAPTOP',
    'description' => 'Laptop computers',
]);
echo "- Laptop category added\n";

$desktopCat = $categories->insert([
    'name' => 'Desktop PC',
    'code' => 'DESKTOP',
    'description' => 'Desktop computers',
]);
echo "- Desktop category added\n";

$accessoryCat = $categories->insert([
    'name' => 'Accessories',
    'code' => 'ACCESSORY',
    'description' => 'Computer accessories',
]);
echo "- Accessories category added\n";

$serviceCat = $categories->insert([
    'name' => 'Service',
    'code' => 'SERVICE',
    'description' => 'Repair services',
]);
echo "- Service category added\n\n";

// ============================================
// Insert Products
// ============================================

echo "5. Insert Products\n";
echo "------------------\n";

$product1 = $products->insert([
    'sku' => 'LAP-ASUS-001',
    'name' => 'ASUS VivoBook 15',
    'category_id' => $laptopCat,
    'price' => 8500000,
    'cost' => 7000000,
    'stock' => 10,
    'min_stock' => 3,
    'brand' => 'ASUS',
    'specs' => [
        'processor' => 'Intel Core i5-12450H',
        'ram' => '8GB DDR4',
        'storage' => '512GB SSD',
        'display' => '15.6" FHD',
    ],
]);
echo "- ASUS VivoBook 15 added (Stock: 10)\n";

$product2 = $products->insert([
    'sku' => 'LAP-DELL-001',
    'name' => 'Dell Inspiron 14',
    'category_id' => $laptopCat,
    'price' => 9200000,
    'cost' => 7500000,
    'stock' => 5,
    'min_stock' => 2,
    'brand' => 'Dell',
    'specs' => [
        'processor' => 'Intel Core i7-1255U',
        'ram' => '16GB DDR4',
        'storage' => '512GB SSD',
        'display' => '14" FHD',
    ],
]);
echo "- Dell Inspiron 14 added (Stock: 5)\n";

$product3 = $products->insert([
    'sku' => 'ACC-MOUSE-001',
    'name' => 'Logitech Wireless Mouse',
    'category_id' => $accessoryCat,
    'price' => 150000,
    'cost' => 80000,
    'stock' => 50,
    'min_stock' => 10,
    'brand' => 'Logitech',
]);
echo "- Logitech Mouse added (Stock: 50)\n";

$product4 = $products->insert([
    'sku' => 'ACC-HDD-001',
    'name' => 'Seagate HDD 1TB',
    'category_id' => $accessoryCat,
    'price' => 450000,
    'cost' => 350000,
    'stock' => 20,
    'min_stock' => 5,
    'brand' => 'Seagate',
]);
echo "- Seagate HDD 1TB added (Stock: 20)\n\n";

// ============================================
// Insert Customers
// ============================================

echo "6. Insert Customers\n";
echo "--------------------\n";

$customer1 = $customers->insert([
    'name' => 'PT Maju Bersama',
    'phone' => '+62812345678',
    'email' => 'admin@majubersama.com',
    'address' => 'Jl. Sudirman No. 123, Jakarta',
]);
echo "- Customer added: PT Maju Bersama\n";

$customer2 = $customers->insert([
    'name' => 'Budi Santoso',
    'phone' => '+62856789012',
    'email' => 'budi@email.com',
    'address' => 'Jl. Thamrin No. 45, Jakarta',
]);
echo "- Customer added: Budi Santoso\n\n";

// ============================================
// Create Sale Transaction
// ============================================

echo "7. Create Sale Transaction\n";
echo "--------------------------\n";

$invoiceNumber = 'INV-' . date('Ymd') . '-001';

$saleId = $sales->insert([
    'invoice_number' => $invoiceNumber,
    'customer_id' => $customer1,
    'items' => [
        [
            'product_id' => $product1,
            'sku' => 'LAP-ASUS-001',
            'name' => 'ASUS VivoBook 15',
            'quantity' => 2,
            'unit_price' => 8500000,
            'subtotal' => 17000000,
        ],
        [
            'product_id' => $product3,
            'sku' => 'ACC-MOUSE-001',
            'name' => 'Logitech Wireless Mouse',
            'quantity' => 5,
            'unit_price' => 150000,
            'subtotal' => 750000,
        ],
    ],
    'subtotal' => 17750000,
    'tax' => 1775000,
    'discount' => 500000,
    'total' => 19000000,
    'payment_method' => 'transfer',
    'status' => 'completed',
]);
echo "- Sale created: $invoiceNumber\n";
echo "  Total: Rp 19,000,000\n";

// Update stock
$products->update(['_id' => $product1], ['$set' => ['stock' => 8]]);
$products->update(['_id' => $product3], ['$set' => ['stock' => 45]]);
echo "- Stock updated\n\n";

// ============================================
// Create Service Ticket
// ============================================

echo "8. Create Service Ticket\n";
echo "------------------------\n";

$ticketNumber = 'SRV-' . date('Ymd') . '-001';

$serviceId = $services->insert([
    'ticket_number' => $ticketNumber,
    'customer_id' => $customer2,
    'device_type' => 'Laptop',
    'brand' => 'HP',
    'model' => 'HP EliteBook 840',
    'serial_number' => 'SN-12345678',
    'problem' => 'Laptop tidak bisa nyala, kemungkinan masalah motherboard',
    'status' => 'received',
    'priority' => 'normal',
]);
echo "- Service ticket created: $ticketNumber\n";
echo "  Customer: Budi Santoso\n";
echo "  Device: HP EliteBook 840\n";
echo "  Problem: Laptop tidak bisa nyala\n\n";

// ============================================
// Update Service Status
// ============================================

echo "9. Update Service Status\n";
echo "------------------------\n";

$services->update(['_id' => $serviceId], [
    '$set' => [
        'diagnosis' => 'Motherboard rusak, perlu penggantian',
        'estimated_cost' => 2500000,
        'status' => 'waiting_parts',
    ],
]);
echo "- Status updated to: waiting_parts\n";
echo "- Estimated cost: Rp 2,500,000\n\n";

// ============================================
// Add Service Parts
// ============================================

echo "10. Add Service Parts\n";
echo "---------------------\n";

$service_parts->insert([
    'service_id' => $serviceId,
    'product_id' => $product4,
    'part_name' => 'HDD 1TB Replacement',
    'quantity' => 1,
    'unit_price' => 450000,
]);
echo "- Parts added to service\n\n";

// ============================================
// Queries and Reports
// ============================================

echo "11. Queries and Reports\n";
echo "-----------------------\n";

// Low stock alert
echo "a. Low Stock Alert:\n";
$lowStock = $products->find(['stock' => ['$lte' => 5]]);
foreach ($lowStock as $p) {
    echo "  - {$p['name']}: {$p['stock']} units (min: {$p['min_stock']})\n";
}

// Sales summary
echo "\nb. Sales Summary:\n";
$sale = $sales->findOne(['_id' => $saleId]);
echo "  Invoice: {$sale['invoice_number']}\n";
echo '  Total: Rp ' . number_format($sale['total'], 0, ',', '.') . "\n";
echo "  Status: {$sale['status']}\n";

// Service status
echo "\nc. Service Status:\n";
$activeServices = $services->find(['status' => ['$nin' => ['delivered', 'cancelled']]]);
foreach ($activeServices as $s) {
    echo "  - {$s['ticket_number']}: {$s['status']} (Priority: {$s['priority']})\n";
}

// Customer lookup (with encryption)
echo "\nd. Customer Lookup:\n";
$customer = $customers->findOne(['_id' => $customer2]);
echo "  Name: {$customer['name']}\n";
echo "  Phone: {$customer['phone']}\n";

// Product by category
echo "\ne. Laptop Products:\n";
$laptops = $products->find(['category_id' => $laptopCat]);
foreach ($laptops as $p) {
    echo "  - {$p['name']}: Rp " . number_format($p['price'], 0, ',', '.') . " (Stock: {$p['stock']})\n";
}

echo "\n=== Summary ===\n";
echo "Master DB:\n";
echo "  - Categories: {$categories->count()}\n";
echo "  - Products: {$products->count()}\n";
echo "  - Customers: {$customers->count()}\n\n";

echo "Transaction DB:\n";
echo "  - Sales: {$sales->count()}\n";
echo "  - Services: {$services->count()}\n";
echo "  - Service Parts: {$service_parts->count()}\n\n";

echo "=== Cleanup ===\n";
@Database::closeAll();
@$transactionDb->drop();
@$masterDb->drop();
@$transactionClient->close();
@$masterClient->close();
echo "All databases cleaned.\n";
