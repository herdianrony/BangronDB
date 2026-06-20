<?php

/**
 * Contoh 02: Query Operators Lengkap
 *
 * Semua operator query MongoDB-like: comparison, logical,
 * array, string, existence, custom function, fuzzy search.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sep('Contoh 02: Query Operators Lengkap');

$client = createIsolatedClient('example02');
$db = $client->createDB('shop');
$products = $db->createCollection('products');

// ── Setup data ────────────────────────────────────────────
$products->insert([
    ['name' => 'Laptop',      'price' => 1200, 'category' => 'electronics', 'stock' => 50,  'tags' => ['sale','new'],        'brand' => 'Dell'],
    ['name' => 'Smartphone',  'price' => 800,  'category' => 'electronics', 'stock' => 0,   'tags' => ['sale'],              'brand' => 'Samsung'],
    ['name' => 'Tablet',      'price' => 400,  'category' => 'electronics', 'stock' => 30,  'tags' => ['new'],               'brand' => 'Apple'],
    ['name' => 'Headphones',  'price' => 150,  'category' => 'electronics', 'stock' => 100, 'tags' => ['sale','accessories'],'brand' => 'Sony'],
    ['name' => 'PHP Book',    'price' => 45,   'category' => 'books',       'stock' => 200, 'tags' => ['educational'],       'brand' => 'OReilly'],
    ['name' => 'Novel',       'price' => 15,   'category' => 'books',       'stock' => 300, 'tags' => ['fiction'],           'brand' => 'Penguin'],
    ['name' => 'Keyboard',    'price' => 80,   'category' => 'electronics', 'stock' => 75,  'tags' => ['accessories'],       'brand' => 'Logitech'],
    ['name' => 'Mouse',       'price' => 35,   'category' => 'electronics', 'stock' => 150, 'tags' => ['accessories','sale'],'brand' => 'Logitech'],
]);
echo "Inserted 8 products\n";

// ── Comparison Operators ───────────────────────────────────
sub('Comparison: $gt, $gte, $lt, $lte, $ne');

$expensive = $products->find(['price' => ['$gt' => 500]])->toArray();
echo "\$gt 500: " . implode(', ', array_column($expensive, 'name')) . "\n";

$affordable = $products->find(['price' => ['$lte' => 50]])->toArray();
echo "\$lte 50: " . implode(', ', array_column($affordable, 'name')) . "\n";

$notBooks = $products->find(['category' => ['$ne' => 'books']])->toArray();
echo "\$ne 'books': " . count($notBooks) . " products\n";

// ── Logical Operators ──────────────────────────────────────
sub('Logical: $and, $or');

$cheapElectronics = $products->find([
    '$and' => [
        ['category' => 'electronics'],
        ['price' => ['$lte' => 150]],
    ],
])->toArray();
echo "\$and electronics + cheap: " . implode(', ', array_column($cheapElectronics, 'name')) . "\n";

$booksOrOutOfStock = $products->find([
    '$or' => [
        ['category' => 'books'],
        ['stock' => 0],
    ],
])->toArray();
echo "\$or books OR out-of-stock: " . implode(', ', array_column($booksOrOutOfStock, 'name')) . "\n";

// ── Array Operators ────────────────────────────────────────
sub('Array: $in, $nin, $has, $all, $size');

$elecOrBooks = $products->find(['category' => ['$in' => ['electronics', 'books']]])->toArray();
echo "\$in [electronics, books]: " . count($elecOrBooks) . " products\n";

$notElec = $products->find(['category' => ['$nin' => ['electronics']]])->toArray();
echo "\$nin [electronics]: " . implode(', ', array_column($notElec, 'name')) . "\n";

$onSale = $products->find(['tags' => ['$has' => 'sale']])->toArray();
echo "\$has 'sale': " . implode(', ', array_column($onSale, 'name')) . "\n";

$saleAndNew = $products->find(['tags' => ['$all' => ['sale', 'new']]])->toArray();
echo "\$all ['sale','new']: " . implode(', ', array_column($saleAndNew, 'name')) . "\n";

$twoTags = $products->find(['tags' => ['$size' => 2]])->toArray();
echo "\$size 2 tags: " . implode(', ', array_column($twoTags, 'name')) . "\n";
echo "Catatan: item pada \$in/\$nin harus berupa nilai scalar; nested array akan ditolak eksplisit.\n";

// ── String Operators ───────────────────────────────────────
sub('String: $regex, $not');

$startsWithS = $products->find(['name' => ['$regex' => '^S']])->toArray();
echo "\$regex '^S': " . implode(', ', array_column($startsWithS, 'name')) . "\n";

$notDell = $products->find(['brand' => ['$not' => '/Dell/i']])->toArray();
echo "\$not Dell: " . count($notDell) . " products\n";

// ── Existence Operator ─────────────────────────────────────
sub('Existence: $exists');

$withBrand = $products->find(['brand' => ['$exists' => true]])->toArray();
echo "\$exists brand: " . count($withBrand) . " products\n";

// ── Custom Function (Closure only - secure) ────────────────
sub('Custom Function: $where, $func (Closure only)');

$longName = $products->find([
    'name' => ['$func' => fn($val) => strlen($val) > 6],
])->toArray();
echo "\$func strlen>6: " . implode(', ', array_column($longName, 'name')) . "\n";

$premium = $products->find([
    '$where' => fn($doc) => ($doc['price'] ?? 0) > 500 && ($doc['stock'] ?? 0) > 0,
])->toArray();
echo "\$where price>500 & stock>0: " . implode(', ', array_column($premium, 'name')) . "\n";

// ── Fuzzy Search ───────────────────────────────────────────
sub('Fuzzy Search: $fuzzy');

$fuzzy = $products->find([
    'name' => ['$fuzzy' => ['$search' => 'laptob', '$minScore' => 0.7]],
])->toArray();
echo "\$fuzzy 'laptob': " . implode(', ', array_column($fuzzy, 'name')) . "\n";

// ── Dot Notation (nested fields) ───────────────────────────
sub('Dot Notation');

$products->insert([
    'name' => 'Gaming PC',
    'price' => 2000,
    'specs' => ['cpu' => 'i9', 'ram' => 32],
]);

$withI9 = $products->find(['specs.cpu' => 'i9'])->toArray();
echo "specs.cpu=i9: " . implode(', ', array_column($withI9, 'name')) . "\n";

// ── Combined Query ─────────────────────────────────────────
sub('Combined Query');

$result = $products->find([
    'category' => 'electronics',
    'price' => ['$gte' => 100, '$lte' => 1500],
    'stock' => ['$gt' => 0],
    'tags' => ['$has' => 'sale'],
])->toArray();
echo "Electronics $100-$1500, in stock, on sale: " . implode(', ', array_column($result, 'name')) . "\n";

$client->close();
echo "\nDone!\n";
