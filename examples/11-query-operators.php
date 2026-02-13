<?php

/**
 * Contoh 11: Query Operators.
 *
 * Demonstrasi lengkap query operators MongoDB-like.
 */

require_once __DIR__.'/bootstrap.php';

echo "=== Contoh 11: Query Operators ===\n\n";

// Buat client dengan database isolated
$client = createIsolatedClient('query_demo');
$db = $client->selectDB('app');
$products = $db->products;

echo "1. Insert sample data\n";
echo "----------------------\n";

$products->insert([
    ['name' => 'Laptop', 'price' => 1000, 'category' => 'electronics', 'stock' => 50, 'tags' => ['sale', 'new']],
    ['name' => 'Phone', 'price' => 500, 'category' => 'electronics', 'stock' => 100, 'tags' => ['sale']],
    ['name' => 'Book', 'price' => 20, 'category' => 'books', 'stock' => 200, 'tags' => ['educational']],
    ['name' => 'Tablet', 'price' => 300, 'category' => 'electronics', 'stock' => 0, 'tags' => ['new']],
    ['name' => 'Headphones', 'price' => 100, 'category' => 'electronics', 'stock' => 75, 'tags' => ['accessories']],
]);

echo "Inserted 5 products\n\n";

echo "2. Comparison Operators\n";
echo "----------------------\n";

// $gt - Greater than
echo "\$gt (greater than):\n";
$results = $products->find(['price' => ['$gt' => 500]])->toArray();
print_r($results);

// $gte - Greater than or equal
echo "\$gte (greater than or equal):\n";
$results = $products->find(['price' => ['$gte' => 500]])->toArray();
print_r($results);

// $lt - Less than
echo "\$lt (less than):\n";
$results = $products->find(['price' => ['$lt' => 100]])->toArray();
print_r($results);

// $lte - Less than or equal
echo "\$lte (less than or equal):\n";
$results = $products->find(['price' => ['$lte' => 100]])->toArray();
print_r($results);

// $ne - Not equal
echo "\$ne (not equal):\n";
$results = $products->find(['category' => ['$ne' => 'books']])->toArray();
print_r($results);

echo "\n3. Logical Operators\n";
echo "--------------------\n";

// $and
echo "\$and:\n";
$results = $products->find([
    '$and' => [
        ['category' => 'electronics'],
        ['price' => ['$lte' => 500]],
    ],
])->toArray();
print_r($results);

// $or
echo "\$or:\n";
$results = $products->find([
    '$or' => [
        ['category' => 'books'],
        ['stock' => ['$lte' => 50]],
    ],
])->toArray();
print_r($results);

echo "\n4. Array Operators\n";
echo "-------------------\n";

// $in
echo "\$in:\n";
$results = $products->find([
    'category' => ['$in' => ['electronics', 'books']],
])->toArray();
print_r($results);

// $nin
echo "\$nin:\n";
$results = $products->find([
    'category' => ['$nin' => ['electronics']],
])->toArray();
print_r($results);

// $has
echo "\$has:\n";
$results = $products->find(['tags' => ['$has' => 'sale']])->toArray();
print_r($results);

// $all
echo "\$all:\n";
$results = $products->find(['tags' => ['$all' => ['sale', 'new']]])->toArray();
print_r($results);

// $size
echo "\$size:\n";
$results = $products->find(['tags' => ['$size' => 2]])->toArray();
print_r($results);

echo "\n5. String Operators\n";
echo "--------------------\n";

// $regex
echo "\$regex:\n";
$results = $products->find([
    'name' => ['$regex' => 'Lap', '$options' => 'i'],
])->toArray();
print_r($results);

// $regex ends with
echo "\$regex (ends with):\n";
$results = $products->find([
    'name' => ['$regex' => '.*book$', '$options' => 'i'],
])->toArray();
print_r($results);

echo "\n6. Existence Operators\n";
echo "------------------------\n";

// $exists
echo "\$exists:\n";
$results = $products->find(['stock' => ['$exists' => true]])->toArray();
print_r($results);

// $exists false
echo "\$exists (false):\n";
$results = $products->find(['discount' => ['$exists' => false]])->toArray();
print_r($results);

echo "\n7. Combined Query\n";
echo "------------------\n";

$results = $products->find([
    'category' => 'electronics',
    'price' => ['$gte' => 100, '$lte' => 1000],
    'stock' => ['$gt' => 0],
    'tags' => ['$has' => 'sale'],
])->toArray();
print_r($results);

echo "\n=== Cleanup ===\n";
@$db->drop();
$client->close();
echo "Database dibersihkan.\n";
