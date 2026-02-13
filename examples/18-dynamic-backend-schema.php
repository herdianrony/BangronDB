<?php

/**
 * Contoh 18: Dynamic Backend Schema dengan _relationships.
 *
 * Demonstrasi penggunaan _relationships untuk backend dinamis:
 * - Order hasMany OrderItems (reverse relationship)
 * - Dynamic field definition based on relationships
 * - Schema dengan reverse relations
 */

require_once __DIR__.'/bootstrap.php';

echo "=== Contoh 18: Dynamic Backend Schema ===\n\n";

$client = createIsolatedClient('dynamic_backend_demo');

// ============================================
// 1. Define Schema dengan Reverse Relationships
// ============================================
echo "1. Define Schema with Relationships\n";
echo "------------------------------------\n";

$orders = $client->selectDB('ecommerce')->orders;
$orderItems = $client->selectDB('ecommerce')->order_items;
$products = $client->selectDB('ecommerce')->products;

// Schema untuk OrderItems (forward FK)
$orderItems->setSchema([
    'order_id' => ['type' => 'string', 'required' => true],
    'product_id' => ['type' => 'string', 'required' => true],
    'quantity' => ['type' => 'integer', 'min' => 1],
    'unit_price' => ['type' => 'number', 'min' => 0],
    // Reverse relationship: item belongs to order
    '_relationships' => [
        'order' => [
            'type' => 'belongs_to',
            'collection' => 'orders',
            'foreign_key' => 'order_id',
        ],
        'product' => [
            'type' => 'belongs_to',
            'collection' => 'products',
            'foreign_key' => 'product_id',
        ],
    ],
]);
$orderItems->createIndex('order_id');
$orderItems->createIndex('product_id');
$orderItems->saveConfiguration();

// Schema untuk Order (reverse relationship - hasMany)
$orders->setSchema([
    'order_number' => ['type' => 'string', 'required' => true],
    'customer_name' => ['type' => 'string', 'required' => true],
    'customer_email' => ['type' => 'string', 'format' => 'email'],
    'total' => ['type' => 'number', 'min' => 0],
    'status' => ['type' => 'string', 'enum' => ['pending', 'paid', 'shipped', 'completed']],
    // Reverse relationship: order hasMany items
    '_relationships' => [
        'items' => [
            'type' => 'has_many',
            'collection' => 'order_items',
            'foreign_key' => 'order_id',
            'description' => 'Order contains many items',
        ],
    ],
]);
$orders->createIndex('order_number');
$orders->saveConfiguration();

echo "Schema defined:\n";
echo "  - orders: hasMany items (order_items)\n";
echo "  - order_items: belongs_to order + product\n";

// ============================================
// 2. Dynamic Field: Order dengan Items
// ============================================
echo "\n2. Insert Order with Items\n";
echo "--------------------------\n";

// Insert product first
$product1Id = $products->insert([
    'name' => 'Laptop',
    'price' => 999.99,
]);
$product2Id = $products->insert([
    'name' => 'Mouse',
    'price' => 29.99,
]);
echo "Created products: Laptop, Mouse\n";

// Insert order
$orderId = $orders->insert([
    'order_number' => 'ORD-001',
    'customer_name' => 'John Doe',
    'customer_email' => 'john@example.com',
    'total' => 0,
    'status' => 'pending',
]);
echo "Created order: ORD-001\n";

// Insert items (forward relationship)
$item1Id = $orderItems->insert([
    'order_id' => $orderId,
    'product_id' => $product1Id,
    'quantity' => 1,
    'unit_price' => 999.99,
]);
$item2Id = $orderItems->insert([
    'order_id' => $orderId,
    'product_id' => $product2Id,
    'quantity' => 2,
    'unit_price' => 29.99,
]);
echo "Created items: Laptop x1, Mouse x2\n";

// Update order total
$orders->update(['_id' => $orderId], [
    'total' => 999.99 + (2 * 29.99),
]);

// ============================================
// 3. Dynamic Backend: Query dengan Reverse Relationship
// ============================================
echo "\n3. Query Order with Items (Reverse Relationship)\n";
echo "-----------------------------------------------\n";

// Get order
$order = $orders->findOne(['_id' => $orderId]);

// Query items untuk order ini (manual, karena hasMany belum auto-populate)
$items = $orderItems->find(['order_id' => $orderId])->toArray();

// Populate product info
$itemsWithProduct = $orderItems->populate($items, 'product_id', 'ecommerce.products', '_id', 'product');

// Build dynamic order response
$orderWithItems = [
    '_id' => $order['_id'],
    'order_number' => $order['order_number'],
    'customer_name' => $order['customer_name'],
    'total' => $order['total'],
    'status' => $order['status'],
    'items' => array_map(function ($item) {
        return [
            'product_name' => $item['product']['name'] ?? 'Unknown',
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'subtotal' => $item['quantity'] * $item['unit_price'],
        ];
    }, $itemsWithProduct),
];

echo "Order dengan items:\n";
print_r($orderWithItems);

// ============================================
// 4. Schema-Based Dynamic Form Generation
// ============================================
echo "\n4. Dynamic Form from Schema\n";
echo "---------------------------\n";

function generateFormFromSchema($schema, $prefix = '')
{
    $fields = [];
    foreach ($schema as $field => $config) {
        if ($field === '_relationships') {
            continue;
        }

        if (is_array($config)) {
            $type = $config['type'] ?? 'string';
            $required = $config['required'] ?? false;
            $enum = $config['enum'] ?? null;

            $fieldInfo = [
                'name' => $prefix.$field,
                'type' => $type,
                'required' => $required,
            ];
            if ($enum) {
                $fieldInfo['enum'] = $enum;
            }
            $fields[] = $fieldInfo;
        }
    }

    return $fields;
}

$orderSchema = $orders->database->loadCollectionConfig('orders')['schema'];
$formFields = generateFormFromSchema($orderSchema);

echo "Dynamic form fields from schema:\n";
foreach ($formFields as $field) {
    $required = $field['required'] ? ' (required)' : '';
    $enum = $field['enum'] ? ' [enum: '.implode(', ', $field['enum']).']' : '';
    echo "  - {$field['name']}: {$field['type']}{$required}{$enum}\n";
}

// ============================================
// 5. Dynamic Relationship Navigation
// ============================================
echo "\n5. Dynamic Relationship Navigation\n";
echo "----------------------------------\n";

function getRelationships($config)
{
    return $config['_relationships'] ?? [];
}

$orderRels = getRelationships($orderSchema);
$itemsRels = getRelationships($orderItems->database->loadCollectionConfig('order_items')['schema']);

echo "Order relationships:\n";
foreach ($orderRels as $name => $rel) {
    echo "  - $name: {$rel['type']} → {$rel['collection']}\n";
}

echo "\nOrderItems relationships:\n";
foreach ($itemsRels as $name => $rel) {
    echo "  - $name: {$rel['type']} → {$rel['collection']} ({$rel['foreign_key']})\n";
}

// ============================================
// 6. Query All Orders dengan Items
// ============================================
echo "\n6. Query All Orders with Items\n";
echo "------------------------------\n";

$allOrders = $orders->find()->toArray();

foreach ($allOrders as $o) {
    $itemsForOrder = $orderItems->find(['order_id' => $o['_id']])->toArray();
    $itemsPopulated = $orderItems->populate($itemsForOrder, 'product_id', 'ecommerce.products', '_id', 'product');

    echo "Order {$o['order_number']} - {$o['customer_name']}\n";
    echo "  Total: \${$o['total']} ({$o['status']})\n";
    echo "  Items:\n";
    foreach ($itemsPopulated as $item) {
        $subtotal = $item['quantity'] * $item['unit_price'];
        echo "    - {$item['product']['name']} x{$item['quantity']} = \${$subtotal}\n";
    }
}

// ============================================
// Summary
// ============================================
echo "\n=== Summary ===\n";
echo "_relationships untuk Dynamic Backend:\n\n";
echo "1. hasMany Relationship:\n";
echo "   orders → order_items (via order_id)\n\n";
echo "2. belongsTo Relationship:\n";
echo "   order_items → orders (via order_id)\n";
echo "   order_items → products (via product_id)\n\n";
echo "3. Dynamic Form Generation:\n";
echo "   - Generate form fields dari schema\n";
echo "   - Relationship metadata untuk navigasi\n\n";
echo "4. Query Pattern:\n";
echo "   - Forward: FK di child → find by FK\n";
echo "   - Reverse: Parent hasMany → query children by parent_id\n";

// Cleanup
@$client->selectDB('ecommerce')->drop();
$client->close();
echo "\nCleanup done.\n";
