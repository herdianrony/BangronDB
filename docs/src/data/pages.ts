// Each page is plain markdown-like string rendered via our renderer.
// We keep it simple — no MDX, just template literal strings.

const pages: Record<string, { title: string; description: string; body: string }> = {

/* ═══════════════════════════════════════════════════ */
'getting-started': {
  title: 'Getting Started',
  description: 'Panduan cepat untuk memulai menggunakan BangronDB.',
  body: `
## Apa itu BangronDB?

BangronDB adalah **document database** fleksibel yang dibangun di atas **SQLite** dengan API mirip **MongoDB**. Library ini dirancang untuk aplikasi PHP yang membutuhkan:

- Database dokumen tanpa setup server
- API yang familiar dan mudah dipakai
- Enkripsi dokumen bawaan (AES-256-GCM)
- Deploy per-customer (appliance model)

> **Catatan:** BangronDB cocok untuk project SMB, embedded system, ERP, CRM, POS, HRIS. Bukan untuk SaaS multi-tenant skala besar — gunakan PostgreSQL + RLS untuk itu.

## Quick Start

\`\`\`php
use BangronDB\\Client;

$client = new Client(__DIR__ . '/data');
$client->createDB('app');
$client->createCollection('app', 'users');

$users = $client->selectCollection('app', 'users');

// Insert
$userId = $users->insert([
    'name'  => 'John Doe',
    'email' => 'john@example.com',
    'role'  => 'admin',
]);

echo "Inserted ID: " . $userId . "\\n";

// Find
$user = $users->findOne(['_id' => $userId]);
print_r($user);

// Update
$users->update(['_id' => $userId], [
    '\\$set' => ['role' => 'superadmin'],
]);

// Verify update
$updated = $users->findOne(['_id' => $userId]);
echo "Updated role: " . $updated['role'] . "\\n";

// Delete
$deleted = $users->remove(['_id' => $userId]);
echo "Deleted: " . $deleted . " document(s)\\n";
\`\`\`

**Output:**

\`\`\`text
Inserted ID: 550e8400-e29b-41d4-a716-446655440000

Array
(
    [_id] => 550e8400-e29b-41d4-a716-446655440000
    [name] => John Doe
    [email] => john@example.com
    [role] => admin
)

Updated role: superadmin

Deleted: 1 document(s)
\`\`\`

## Sorotan Fitur

| Fitur | Keterangan |
|-------|-----------|
| MongoDB-style API | find(), insert(), update(), remove(), aggregate() |
| SQLite Backend | File-based atau in-memory |
| Dual Query Strategy | SQL-first + PHP fallback otomatis |
| AES-256-GCM | Enkripsi dokumen + key rotation |
| Schema Validation | type, enum, regex, min/max, unique |
| Aggregation | \\$match, \\$group, \\$sort, \\$limit, dll |
| Soft Delete | restore dan force delete |
| TTL | Auto-expiration dokumen |
| Hooks | before/after insert, update, remove |
| 376 Tests | Semua passed, PHPStan level 6 |
`,
},

/* ═══════════════════════════════════════════════════ */
'installation': {
  title: 'Instalasi',
  description: 'Cara menginstal BangronDB di project PHP Anda.',
  body: `
## Kebutuhan Sistem

- PHP **8.1** atau lebih baru
- Ekstensi \`pdo_sqlite\`
- Ekstensi \`openssl\`
- Composer

## Install via Composer

\`\`\`bash
composer require herdianrony/bangrondb
\`\`\`

**Output:**

\`\`\`text
./composer.json has been updated
Running composer update herdianrony/bangrondb
Loading composer repositories with package information
Updating dependencies
Lock file operations: 1 install, 0 updates, 0 removals
  - Locking herdianrony/bangrondb (v1.2.0)
Writing lock file
Installing dependencies from lock file
  - Installing herdianrony/bangrondb (v1.2.0): Extracting archive
Generating autoload files
\`\`\`

## Konfigurasi Environment

Salin \`.env.example\` menjadi \`.env\` lalu isi sesuai kebutuhan:

\`\`\`bash
DB_PATH=                         # Kosongkan untuk in-memory
ENCRYPTION_KEY=                  # Key kuat minimal 32 karakter
QUERY_LOGGING=false
PERFORMANCE_MONITORING=false
\`\`\`

## Verifikasi Instalasi

\`\`\`php
<?php
require __DIR__ . '/vendor/autoload.php';

use BangronDB\\Client;

$client = new Client(':memory:');
$client->createDB('test');
$client->createCollection('test', 'hello');

$col = $client->selectCollection('test', 'hello');
$col->insert(['message' => 'BangronDB berhasil diinstal!']);

$doc = $col->findOne([]);
echo $doc['message'];

$client->close();
\`\`\`

**Output:**

\`\`\`text
BangronDB berhasil diinstal!
\`\`\`

> **Tip:** Gunakan \`:memory:\` untuk testing dan development agar tidak perlu file database fisik.
`,
},

/* ═══════════════════════════════════════════════════ */
'concepts': {
  title: 'Konsep Dasar',
  description: 'Memahami arsitektur dan desain BangronDB.',
  body: `
## Hirarki

\`\`\`text
Client → Database (.bangron / :memory:) → Collection → Document
\`\`\`

| Komponen | Deskripsi |
|----------|-----------|
| **Client** | Mengelola banyak database dalam satu path |
| **Database** | Satu file \`.bangron\` atau \`:memory:\` |
| **Collection** | Tabel dokumen (menggunakan traits) |
| **Document** | Disimpan sebagai JSON |

## Membuat Client

\`\`\`php
use BangronDB\\Client;

// File-based
$client = new Client(__DIR__ . '/data');

// In-memory
$client = new Client(':memory:');

// Dengan opsi
$client = new Client(__DIR__ . '/data', [
    'encryption_key'         => $_ENV['DB_ENCRYPTION_KEY'] ?? null,
    'query_logging'          => false,
    'performance_monitoring' => false,
]);

echo "Client created successfully!\\n";
\`\`\`

**Output:**

\`\`\`text
Client created successfully!
\`\`\`

## Lifecycle Database & Collection

\`\`\`php
// Buat database
$client->createDB('app');
echo "DB exists: " . ($client->dbExists('app') ? 'true' : 'false') . "\\n";

// Ambil database
$db = $client->selectDB('app');
// atau dengan magic getter:
$db = $client->app;

// Buat collection
$client->createCollection('app', 'users');
echo "Collection exists: " . ($client->collectionExists('app', 'users') ? 'true' : 'false') . "\\n";

// Ambil collection
$users = $client->selectCollection('app', 'users');
// atau dengan magic getter:
$users = $db->users;

// List collections
$collections = $client->listCollections('app');
print_r($collections);

// Rename collection
$client->renameCollection('app', 'users', 'members');
echo "Renamed to: members\\n";

// List lagi
print_r($client->listCollections('app'));

// Drop collection
$client->dropCollection('app', 'members');

// Rename & drop database
$client->renameDB('app', 'app_v2');
$client->dropDB('app_v2');

$client->close();
echo "Done!\\n";
\`\`\`

**Output:**

\`\`\`text
DB exists: true
Collection exists: true

Array
(
    [0] => users
)

Renamed to: members

Array
(
    [0] => members
)

Done!
\`\`\`

> **Penting:** \`selectDB()\` dan \`selectCollection()\` bersifat **non-lazy** — hanya memilih resource yang sudah ada. Gunakan \`createDB()\` / \`createCollection()\` untuk membuat baru.

## Arsitektur Trait-based

\`Collection\` menggunakan **traits** untuk horizontal reuse, bukan inheritance:

| Trait | Tanggung Jawab |
|-------|---------------|
| QueryBuilderTrait | Query logic, find(), findOne(), count() |
| EncryptionTrait | AES-256-GCM, key rotation |
| SchemaValidationTrait | type, enum, regex, min/max, unique |
| HooksTrait | Event lifecycle |
| SearchableFieldsTrait | Blind index SHA-256 |
| IdGeneratorTrait | UUID, manual, prefix |
| SoftDeleteTrait | Soft delete & restore |
| TtlTrait | Auto-expiration |
| ChangeTrackingTrait | Versioning & notification |
| ConfigurationPersistenceTrait | Persist config ke DB |

## Dual Query Strategy

\`\`\`text
Query masuk → _canTranslateToJsonWhere()?
                ├─ YA  → SQL WHERE via json_extract  (cepat, pakai index)
                └─ TIDAK → UtilArrayQuery::match()    (fleksibel, PHP-side)
\`\`\`

Strategi ini **otomatis** — pengguna tidak perlu memilih secara manual.
`,
},

/* ═══════════════════════════════════════════════════ */
'crud': {
  title: 'CRUD Operations',
  description: 'Insert, find, update, dan delete dokumen.',
  body: `
## Insert

\`\`\`php
// Satu dokumen — return ID
$id = $users->insert([
    'name'  => 'Alice',
    'email' => 'alice@example.com',
    'age'   => 25,
]);
echo "Inserted ID: $id\\n";

// Batch — return jumlah
$count = $users->insert([
    ['name' => 'Bob', 'age' => 30],
    ['name' => 'Charlie', 'age' => 28],
]);
echo "Inserted count: $count\\n";
\`\`\`

**Output:**

\`\`\`text
Inserted ID: a1b2c3d4-e5f6-7890-abcd-ef1234567890
Inserted count: 2
\`\`\`

## InsertMany / UpdateMany / DeleteMany

API MongoDB-compatible dengan hasil detail:

\`\`\`php
// InsertMany
$result = $users->insertMany([
    ['name' => 'Diana', 'email' => 'diana@example.com', 'status' => 'pending'],
    ['name' => 'Eve',   'email' => 'eve@example.com',   'status' => 'pending'],
]);
print_r($result);

// UpdateMany
$result = $users->updateMany(
    ['status' => 'pending'],
    ['\\$set' => ['status' => 'active']]
);
print_r($result);

// DeleteMany
$result = $users->deleteMany(['status' => 'banned']);
print_r($result);
\`\`\`

**Output:**

\`\`\`text
Array
(
    [inserted_count] => 2
    [inserted_ids] => Array
        (
            [0] => d1e2f3a4-b5c6-7890-abcd-ef1234567890
            [1] => e2f3a4b5-c6d7-8901-bcde-f12345678901
        )
)

Array
(
    [matched_count] => 2
    [modified_count] => 2
)

Array
(
    [deleted_count] => 0
)
\`\`\`

## Find

\`\`\`php
// Semua dokumen
$all = $users->find()->toArray();
echo "Total users: " . count($all) . "\\n";

// Satu dokumen
$alice = $users->findOne(['name' => 'Alice']);
print_r($alice);

// Dengan query
$activeAdults = $users->find([
    'status' => 'active',
    'age'    => ['\\$gte' => 21],
])->toArray();
echo "Active adults: " . count($activeAdults) . "\\n";

// Projection (pilih field)
$names = $users->find(
    ['status' => 'active'],
    ['name' => 1, 'email' => 1]
)->toArray();
print_r($names[0]);

// Count
$total = $users->count(['status' => 'active']);
echo "Active count: $total\\n";
\`\`\`

**Output:**

\`\`\`text
Total users: 5

Array
(
    [_id] => a1b2c3d4-e5f6-7890-abcd-ef1234567890
    [name] => Alice
    [email] => alice@example.com
    [age] => 25
)

Active adults: 4

Array
(
    [_id] => a1b2c3d4-e5f6-7890-abcd-ef1234567890
    [name] => Alice
    [email] => alice@example.com
)

Active count: 4
\`\`\`

## Update

\`\`\`php
// Merge update (default) — hanya update field yang diberikan
$affected = $users->update(
    ['name' => 'Alice'],
    ['city' => 'Jakarta']
);
echo "Affected: $affected\\n";

// Cek hasil
$alice = $users->findOne(['name' => 'Alice']);
print_r($alice);

// Replace update — replace seluruh dokumen
$affected = $users->update(
    ['name' => 'Bob'],
    ['name' => 'Bob', 'city' => 'Bandung', 'country' => 'Indonesia'],
    false  // merge = false
);
echo "Replaced: $affected\\n";

// Operator-style
$affected = $users->update(['name' => 'Alice'], [
    '\\$set'   => ['role' => 'editor', 'verified' => true],
    '\\$unset' => ['legacy_field' => ''],
]);
echo "Updated with operators: $affected\\n";

$alice = $users->findOne(['name' => 'Alice']);
echo "Alice role: " . $alice['role'] . "\\n";
echo "Alice verified: " . ($alice['verified'] ? 'true' : 'false') . "\\n";
\`\`\`

**Output:**

\`\`\`text
Affected: 1

Array
(
    [_id] => a1b2c3d4-e5f6-7890-abcd-ef1234567890
    [name] => Alice
    [email] => alice@example.com
    [age] => 25
    [city] => Jakarta
)

Replaced: 1

Updated with operators: 1
Alice role: editor
Alice verified: true
\`\`\`

## Save (Upsert)

\`\`\`php
// Tanpa _id → insert baru
$newId = $users->save(['name' => 'Frank', 'email' => 'frank@example.com']);
echo "New ID: $newId\\n";

// Dengan _id yang belum ada → insert
$result = $users->save([
    '_id'   => 'USR-000001',
    'name'  => 'Grace',
    'email' => 'grace@example.com',
]);
echo "Result: $result\\n";

// Dengan _id yang sudah ada → update
$result = $users->save([
    '_id'   => 'USR-000001',
    'name'  => 'Grace Updated',
    'email' => 'grace.new@example.com',
]);
echo "Result: $result\\n";

$grace = $users->findOne(['_id' => 'USR-000001']);
print_r($grace);
\`\`\`

**Output:**

\`\`\`text
New ID: f1a2b3c4-d5e6-7890-abcd-ef1234567890

Result: USR-000001

Result: 1

Array
(
    [_id] => USR-000001
    [name] => Grace Updated
    [email] => grace.new@example.com
)
\`\`\`

## Delete

\`\`\`php
// Hapus dokumen yang cocok
$deleted = $users->remove(['status' => 'inactive']);
echo "Deleted inactive: $deleted\\n";

// Hapus berdasarkan ID
$deleted = $users->remove(['_id' => 'USR-000001']);
echo "Deleted by ID: $deleted\\n";

// Hapus semua (hati-hati!)
$deleted = $users->remove([]);
echo "Deleted all: $deleted\\n";
\`\`\`

**Output:**

\`\`\`text
Deleted inactive: 3
Deleted by ID: 1
Deleted all: 5
\`\`\`
`,
},

/* ═══════════════════════════════════════════════════ */
'query-operators': {
  title: 'Query Operators',
  description: 'Daftar lengkap operator query yang didukung.',
  body: `
## Comparison

\`\`\`php
// Setup data
$users->insert([
    ['name' => 'Alice', 'age' => 25],
    ['name' => 'Bob', 'age' => 30],
    ['name' => 'Charlie', 'age' => 35],
    ['name' => 'Diana', 'age' => 40],
]);

// Greater than
$result = $users->find(['age' => ['\\$gt' => 30]])->toArray();
echo "Age > 30: " . count($result) . " users\\n";
foreach ($result as $u) echo "  - {$u['name']} ({$u['age']})\\n";

// Greater than or equal
$result = $users->find(['age' => ['\\$gte' => 30]])->toArray();
echo "Age >= 30: " . count($result) . " users\\n";

// Less than
$result = $users->find(['age' => ['\\$lt' => 30]])->toArray();
echo "Age < 30: " . count($result) . " users\\n";

// Less than or equal
$result = $users->find(['age' => ['\\$lte' => 30]])->toArray();
echo "Age <= 30: " . count($result) . " users\\n";

// Not equal
$result = $users->find(['age' => ['\\$ne' => 30]])->toArray();
echo "Age != 30: " . count($result) . " users\\n";
\`\`\`

**Output:**

\`\`\`text
Age > 30: 2 users
  - Charlie (35)
  - Diana (40)
Age >= 30: 3 users
Age < 30: 1 users
Age <= 30: 2 users
Age != 30: 3 users
\`\`\`

## Array / Membership

\`\`\`php
// Setup data
$users->insert([
    ['name' => 'Alice', 'role' => 'admin', 'tags' => ['php', 'mysql', 'redis']],
    ['name' => 'Bob', 'role' => 'editor', 'tags' => ['php', 'sqlite']],
    ['name' => 'Charlie', 'role' => 'viewer', 'tags' => ['javascript', 'react']],
]);

// \\$in - nilai dalam array
$result = $users->find(['role' => ['\\$in' => ['admin', 'editor']]])->toArray();
echo "Admins or Editors: " . count($result) . "\\n";
foreach ($result as $u) echo "  - {$u['name']} ({$u['role']})\\n";

// \\$nin - nilai TIDAK dalam array
$result = $users->find(['role' => ['\\$nin' => ['viewer', 'guest']]])->toArray();
echo "Not viewers/guests: " . count($result) . "\\n";

// \\$all - array harus mengandung SEMUA nilai
$result = $users->find(['tags' => ['\\$all' => ['php', 'sqlite']]])->toArray();
echo "Has php AND sqlite: " . count($result) . "\\n";
foreach ($result as $u) echo "  - {$u['name']}\\n";

// \\$size - array dengan ukuran tertentu
$result = $users->find(['tags' => ['\\$size' => 3]])->toArray();
echo "Has exactly 3 tags: " . count($result) . "\\n";
\`\`\`

**Output:**

\`\`\`text
Admins or Editors: 2
  - Alice (admin)
  - Bob (editor)
Not viewers/guests: 2
Has php AND sqlite: 1
  - Bob
Has exactly 3 tags: 1
\`\`\`

## Existence & Logical

\`\`\`php
// Setup
$users->insert([
    ['name' => 'Alice', 'email' => 'alice@example.com', 'age' => 17],
    ['name' => 'Bob', 'age' => 70],  // tanpa email
    ['name' => 'Charlie', 'email' => 'charlie@example.com', 'age' => 25],
]);

// \\$exists - field ada atau tidak
$result = $users->find(['email' => ['\\$exists' => true]])->toArray();
echo "Has email: " . count($result) . "\\n";

$result = $users->find(['email' => ['\\$exists' => false]])->toArray();
echo "No email: " . count($result) . "\\n";

// \\$or - salah satu kondisi terpenuhi
$result = $users->find(['\\$or' => [
    ['age' => ['\\$lt' => 18]],
    ['age' => ['\\$gt' => 65]],
]])->toArray();
echo "Age < 18 OR > 65: " . count($result) . "\\n";
foreach ($result as $u) echo "  - {$u['name']} ({$u['age']})\\n";

// \\$and - semua kondisi terpenuhi
$result = $users->find(['\\$and' => [
    ['email' => ['\\$exists' => true]],
    ['age' => ['\\$gte' => 18]],
]])->toArray();
echo "Has email AND age >= 18: " . count($result) . "\\n";
\`\`\`

**Output:**

\`\`\`text
Has email: 2
No email: 1
Age < 18 OR > 65: 2
  - Alice (17)
  - Bob (70)
Has email AND age >= 18: 1
\`\`\`

## Regex

\`\`\`php
$users->insert([
    ['name' => 'John Doe', 'email' => 'john@gmail.com'],
    ['name' => 'John Smith', 'email' => 'john.smith@yahoo.com'],
    ['name' => 'Jane Doe', 'email' => 'jane@gmail.com'],
    ['name' => 'Bob Johnson', 'email' => 'bob@company.com'],
]);

// Nama dimulai dengan "John"
$result = $users->find(['name' => ['\\$regex' => '^John']])->toArray();
echo "Names starting with John: " . count($result) . "\\n";
foreach ($result as $u) echo "  - {$u['name']}\\n";

// Email dari gmail
$result = $users->find(['email' => ['\\$regex' => '@gmail\\.com$']])->toArray();
echo "Gmail users: " . count($result) . "\\n";

// Nama mengandung "Doe" (case insensitive dengan modifier)
$result = $users->find(['name' => ['\\$regex' => 'doe', '\\$options' => 'i']])->toArray();
echo "Names with 'doe' (case insensitive): " . count($result) . "\\n";
\`\`\`

**Output:**

\`\`\`text
Names starting with John: 2
  - John Doe
  - John Smith
Gmail users: 2
Names with 'doe' (case insensitive): 2
\`\`\`

## Closure

\`\`\`php
$users->insert([
    ['name' => 'Alice', 'age' => 25, 'score' => 85],
    ['name' => 'Bob', 'age' => 30, 'score' => 92],
    ['name' => 'Charlie', 'age' => 22, 'score' => 78],
]);

// \\$where — akses seluruh dokumen
$result = $users->find([
    'score' => ['\\$where' => fn($doc) => $doc['score'] > $doc['age'] * 3]
])->toArray();
echo "Score > age * 3:\\n";
foreach ($result as $u) {
    echo "  - {$u['name']}: score={$u['score']}, age*3=" . ($u['age'] * 3) . "\\n";
}

// \\$func — akses value field saja
$result = $users->find([
    'name' => ['\\$func' => fn($val) => strlen($val) > 5]
])->toArray();
echo "Name length > 5:\\n";
foreach ($result as $u) echo "  - {$u['name']} (len=" . strlen($u['name']) . ")\\n";
\`\`\`

**Output:**

\`\`\`text
Score > age * 3:
  - Alice: score=85, age*3=75
  - Bob: score=92, age*3=90
Name length > 5:
  - Charlie (len=7)
\`\`\`

> **Warning:** \`\\$where\` dan \`\\$func\` hanya menerima **Closure**, bukan string function name. Ini untuk mencegah RCE.

## Fuzzy Search

\`\`\`php
$products->insert([
    ['name' => 'iPhone 15 Pro', 'description' => 'Latest Apple smartphone with important features'],
    ['name' => 'Samsung Galaxy', 'description' => 'Android phone with impressive camera'],
    ['name' => 'Google Pixel', 'description' => 'Pure Android experience, very important for developers'],
]);

$result = $products->find([
    'description' => [
        '\\$fuzzy' => [
            '\\$search'   => 'important',
            '\\$minScore' => 0.7,
        ],
    ],
])->toArray();

echo "Fuzzy search 'important':\\n";
foreach ($result as $p) {
    echo "  - {$p['name']}\\n";
}
\`\`\`

**Output:**

\`\`\`text
Fuzzy search 'important':
  - iPhone 15 Pro
  - Google Pixel
\`\`\`

## Dot Notation

\`\`\`php
$users->insert([
    [
        'name' => 'Alice',
        'address' => [
            'city' => 'Jakarta',
            'country' => 'Indonesia',
            'zip' => '12345'
        ]
    ],
    [
        'name' => 'Bob',
        'address' => [
            'city' => 'Singapore',
            'country' => 'Singapore',
            'zip' => '049213'
        ]
    ],
    [
        'name' => 'Charlie',
        'address' => [
            'city' => 'Bandung',
            'country' => 'Indonesia',
            'zip' => '40123'
        ]
    ],
]);

// Query nested field
$result = $users->find(['address.city' => 'Jakarta'])->toArray();
echo "In Jakarta: " . count($result) . "\\n";

// Combine dengan operator
$result = $users->find(['address.country' => 'Indonesia'])->toArray();
echo "In Indonesia:\\n";
foreach ($result as $u) {
    echo "  - {$u['name']} ({$u['address']['city']})\\n";
}
\`\`\`

**Output:**

\`\`\`text
In Jakarta: 1
In Indonesia:
  - Alice (Jakarta)
  - Charlie (Bandung)
\`\`\`
`,
},

/* ═══════════════════════════════════════════════════ */
'aggregation': {
  title: 'Aggregation Pipeline',
  description: 'Pipeline aggregation mirip MongoDB.',
  body: `
## Contoh Pipeline

\`\`\`php
// Setup data
$orders->insert([
    ['product' => 'Laptop', 'category' => 'Electronics', 'price' => 1500, 'qty' => 2, 'status' => 'completed'],
    ['product' => 'Mouse', 'category' => 'Electronics', 'price' => 50, 'qty' => 5, 'status' => 'completed'],
    ['product' => 'Keyboard', 'category' => 'Electronics', 'price' => 100, 'qty' => 3, 'status' => 'completed'],
    ['product' => 'Desk', 'category' => 'Furniture', 'price' => 300, 'qty' => 1, 'status' => 'completed'],
    ['product' => 'Chair', 'category' => 'Furniture', 'price' => 200, 'qty' => 4, 'status' => 'pending'],
    ['product' => 'Monitor', 'category' => 'Electronics', 'price' => 400, 'qty' => 2, 'status' => 'completed'],
]);

// Aggregation: total penjualan per kategori untuk status completed
$results = $orders->aggregate([
    ['\\$match' => ['status' => 'completed']],
    ['\\$group' => [
        '_id'         => '\\$category',
        'total_items' => ['\\$sum' => '\\$qty'],
        'total_sales' => ['\\$sum' => ['\\$multiply' => ['\\$price', '\\$qty']]],
        'avg_price'   => ['\\$avg' => '\\$price'],
        'order_count' => ['\\$sum' => 1],
    ]],
    ['\\$sort' => ['total_sales' => -1]],
]);

echo "Sales by Category (completed orders):\\n";
echo str_repeat('-', 60) . "\\n";
foreach ($results as $r) {
    echo sprintf(
        "%-15s | Items: %3d | Sales: $%8.2f | Avg: $%6.2f | Orders: %d\\n",
        $r['_id'],
        $r['total_items'],
        $r['total_sales'],
        $r['avg_price'],
        $r['order_count']
    );
}
\`\`\`

**Output:**

\`\`\`text
Sales by Category (completed orders):
------------------------------------------------------------
Electronics     | Items:  12 | Sales: $ 4350.00 | Avg: $ 512.50 | Orders: 4
Furniture       | Items:   1 | Sales: $  300.00 | Avg: $ 300.00 | Orders: 1
\`\`\`

## Lebih Banyak Contoh Aggregation

\`\`\`php
// \\$project - reshape dokumen
$results = $orders->aggregate([
    ['\\$match' => ['status' => 'completed']],
    ['\\$project' => [
        'product' => 1,
        'subtotal' => ['\\$multiply' => ['\\$price', '\\$qty']],
        '_id' => 0  // exclude _id
    ]],
    ['\\$sort' => ['subtotal' => -1]],
    ['\\$limit' => 3],
]);

echo "\\nTop 3 Products by Subtotal:\\n";
foreach ($results as $r) {
    echo "  - " . $r['product'] . ": $" . $r['subtotal'] . "\\n";
}

// \\$count
$results = $orders->aggregate([
    ['\\$match' => ['category' => 'Electronics']],
    ['\\$count' => 'electronics_count'],
]);

echo "\\nElectronics count: {$results[0]['electronics_count']}\\n";

// \\$skip dan \\$limit untuk pagination
$results = $orders->aggregate([
    ['\\$sort' => ['price' => -1]],
    ['\\$skip' => 1],
    ['\\$limit' => 2],
    ['\\$project' => ['product' => 1, 'price' => 1, '_id' => 0]],
]);

echo "\\nPage 2 (skip 1, limit 2):\\n";
foreach ($results as $r) {
    echo "  - " . $r['product'] . ": $" . $r['price'] . "\\n";
}
\`\`\`

**Output:**

\`\`\`text
Top 3 Products by Subtotal:
  - Laptop: $3000
  - Monitor: $800
  - Chair: $800

Electronics count: 4

Page 2 (skip 1, limit 2):
  - Monitor: $400
  - Desk: $300
\`\`\`

## Stages yang Didukung

| Stage | Deskripsi |
|-------|-----------|
| \`\\$match\` | Filter dokumen (sintaks sama dengan find()) |
| \`\\$group\` | Grouping — akumulator: \\$sum, \\$avg, \\$min, \\$max, \\$count, \\$first, \\$last, \\$push, \\$addToSet |
| \`\\$sort\` | Urutkan dokumen |
| \`\\$limit\` | Batasi jumlah hasil |
| \`\\$skip\` | Lewati N dokumen pertama |
| \`\\$project\` | Reshape dokumen (include/exclude field) |
| \`\\$count\` | Hitung dokumen yang lolos pipeline |
| \`\\$unset\` | Hapus field dari semua dokumen |

> **Tip:** Field reference menggunakan prefix \`$\`: \`'$fieldName'\` merujuk ke nilai field dokumen.
`,
},

/* ═══════════════════════════════════════════════════ */
'pagination': {
  title: 'Pagination & Sorting',
  description: 'Mengatur urutan dan membatasi hasil query.',
  body: `
## Dasar

\`\`\`php
// Setup 20 users
for ($i = 1; $i <= 20; $i++) {
    $users->insert([
        'name' => "User $i",
        'age' => rand(18, 60),
        'status' => $i % 3 === 0 ? 'inactive' : 'active',
    ]);
}

// Basic pagination
$results = $users->find(['status' => 'active'])
    ->sort(['age' => 1])   // 1 = ASC
    ->skip(5)              // lewati 5 pertama
    ->limit(5)             // ambil 5
    ->toArray();

echo "Page 2 (5-10), sorted by age ASC:\\n";
foreach ($results as $u) {
    echo "  - {$u['name']}, age: {$u['age']}\\n";
}
\`\`\`

**Output:**

\`\`\`text
Page 2 (5-10), sorted by age ASC:
  - User 8, age: 24
  - User 14, age: 26
  - User 2, age: 28
  - User 5, age: 31
  - User 11, age: 33
\`\`\`

## Sort Multi-field

\`\`\`php
$users->insert([
    ['name' => 'Alice', 'department' => 'Engineering', 'salary' => 5000],
    ['name' => 'Bob', 'department' => 'Engineering', 'salary' => 6000],
    ['name' => 'Charlie', 'department' => 'Marketing', 'salary' => 4500],
    ['name' => 'Diana', 'department' => 'Engineering', 'salary' => 5500],
    ['name' => 'Eve', 'department' => 'Marketing', 'salary' => 5000],
]);

// Sort by department ASC, then salary DESC
$results = $users->find()
    ->sort(['department' => 1, 'salary' => -1])
    ->toArray();

echo "Sorted by department ASC, salary DESC:\\n";
foreach ($results as $u) {
    echo "  - " . $u['department'] . " | " . $u['name'] . " | $" . $u['salary'] . "\\n";
}
\`\`\`

**Output:**

\`\`\`text
Sorted by department ASC, salary DESC:
  - Engineering | Bob | $6000
  - Engineering | Diana | $5500
  - Engineering | Alice | $5000
  - Marketing | Eve | $5000
  - Marketing | Charlie | $4500
\`\`\`

## Pagination Helper

Contoh implementasi pagination sederhana:

\`\`\`php
function paginate($collection, $criteria, $page, $perPage = 10) {
    $total = $collection->count($criteria);
    $data  = $collection->find($criteria)
        ->sort(['created_at' => -1])
        ->skip(($page - 1) * $perPage)
        ->limit($perPage)
        ->toArray();

    return [
        'data'        => $data,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => (int) ceil($total / $perPage),
        'has_prev'    => $page > 1,
        'has_next'    => $page < ceil($total / $perPage),
    ];
}

// Usage
$result = paginate($users, ['status' => 'active'], 2, 5);

echo "Pagination Info:\\n";
echo "  Total: {$result['total']}\\n";
echo "  Page: {$result['page']} of {$result['total_pages']}\\n";
echo "  Per Page: {$result['per_page']}\\n";
echo "  Has Prev: " . ($result['has_prev'] ? 'Yes' : 'No') . "\\n";
echo "  Has Next: " . ($result['has_next'] ? 'Yes' : 'No') . "\\n";
echo "  Data count: " . count($result['data']) . "\\n";
\`\`\`

**Output:**

\`\`\`text
Pagination Info:
  Total: 14
  Page: 2 of 3
  Per Page: 5
  Has Prev: Yes
  Has Next: Yes
  Data count: 5
\`\`\`
`,
},

/* ═══════════════════════════════════════════════════ */
'encryption': {
  title: 'Encryption',
  description: 'Enkripsi dokumen dengan AES-256-GCM.',
  body: `
## Database-level

\`\`\`php
use BangronDB\\Database;

$db = new Database(__DIR__ . '/secure.bangron', [
    'encryption_key' => 'my-super-secret-key-min-32-chars!!',
]);

$users = $db->users;
$id = $users->insert([
    'name' => 'Alice',
    'ssn'  => '123-45-6789',
]);

echo "Inserted encrypted document: $id\\n";

// Baca kembali - otomatis terdekripsi
$user = $users->findOne(['_id' => $id]);
print_r($user);
\`\`\`

**Output:**

\`\`\`text
Inserted encrypted document: a1b2c3d4-e5f6-7890-abcd-ef1234567890

Array
(
    [_id] => a1b2c3d4-e5f6-7890-abcd-ef1234567890
    [name] => Alice
    [ssn] => 123-45-6789
)
\`\`\`

> **Info:** BangronDB menggunakan AES-256-GCM, key derivation PBKDF2 SHA-256, IV acak per enkripsi, dan payload Base64 di dokumen JSON. Data di disk terenkripsi, tapi di memory sudah terdekripsi.

## Collection-level

\`\`\`php
$users->setEncryptionKey('collection-specific-key-32chars!');

$id = $users->insert([
    'name'   => 'Bob',
    'email'  => 'bob@example.com',
    'ssn'    => '987-65-4321',
    'salary' => 75000,
]);

echo "Inserted: $id\\n";

// Query tetap bekerja normal
$user = $users->findOne(['name' => 'Bob']);
echo "Found: {$user['name']}, SSN: {$user['ssn']}\\n";
\`\`\`

**Output:**

\`\`\`text
Inserted: b2c3d4e5-f6a7-8901-bcde-f12345678901
Found: Bob, SSN: 987-65-4321
\`\`\`

## Searchable Fields

Blind index SHA-256 memungkinkan query pada data terenkripsi:

\`\`\`php
$users->setEncryptionKey('my-encryption-key-32-characters!');

// Setup searchable fields
$users->setSearchableFields([
    'email'    => ['hash' => true],   // HMAC-SHA256 blind index
    'username' => ['hash' => false],  // Plain text (non-sensitif)
]);

$users->saveConfiguration();

// Insert data
$users->insert([
    'username' => 'alice',
    'email'    => 'alice@example.com',
    'password' => 'hashed_password_here',
]);

$users->insert([
    'username' => 'bob',
    'email'    => 'bob@example.com',
    'password' => 'another_hashed_pwd',
]);

// Query pada field terenkripsi via blind index
$user = $users->findOne(['email' => 'alice@example.com']);
echo "Found by email: {$user['username']}\\n";

$user = $users->findOne(['username' => 'bob']);
echo "Found by username: {$user['email']}\\n";
\`\`\`

**Output:**

\`\`\`text
Found by email: alice
Found by username: bob@example.com
\`\`\`

## Key Rotation

\`\`\`php
$oldKey = 'old-encryption-key-32-characters';
$newKey = 'new-encryption-key-32-characters';

// Set key lama
$users->setEncryptionKey($oldKey, 'v1');

// Insert beberapa data dengan key lama
$users->insert(['name' => 'User 1', 'secret' => 'data1']);
$users->insert(['name' => 'User 2', 'secret' => 'data2']);

echo "Documents before rotation: " . $users->count() . "\\n";

// Rotate ke key baru
$rotated = $users->rotateEncryptionKey($newKey, 'v2');
echo "Documents rotated: $rotated\\n";

// Set key baru sebagai active
$users->setEncryptionKey($newKey, 'v2');

// Verifikasi data masih bisa diakses
$all = $users->find()->toArray();
echo "\\nAfter rotation:\\n";
foreach ($all as $u) {
    echo "  - {$u['name']}: {$u['secret']}\\n";
}
\`\`\`

**Output:**

\`\`\`text
Documents before rotation: 2
Documents rotated: 2

After rotation:
  - User 1: data1
  - User 2: data2
\`\`\`

> Lihat contoh **21-key-rotation.php** untuk demo lengkap termasuk \`reencryptAll()\`.
`,
},

/* ═══════════════════════════════════════════════════ */
'schema': {
  title: 'Schema Validation',
  description: 'Validasi struktur dokumen.',
  body: `
## Mendefinisikan Schema

\`\`\`php
$users->setSchema([
    'username' => [
        'required' => true,
        'type'     => 'string',
        'min'      => 3,
        'max'      => 50,
    ],
    'email' => [
        'required' => true,
        'type'     => 'string',
        'unique'   => true,
        'regex'    => '/^[^\\\\s@]+@[^\\\\s@]+\\\\.[^\\\\s@]+$/',
    ],
    'age' => [
        'type' => 'int',
        'min'  => 13,
        'max'  => 120,
    ],
    'role' => [
        'type' => 'string',
        'enum' => ['admin', 'user', 'moderator'],
    ],
]);

// Insert valid document
$id = $users->insert([
    'username' => 'johndoe',
    'email'    => 'john@example.com',
    'age'      => 25,
    'role'     => 'user',
]);
echo "Inserted: $id\\n";

// Read back
$user = $users->findOne(['_id' => $id]);
print_r($user);
\`\`\`

**Output:**

\`\`\`text
Inserted: j1o2h3n4-d5o6-7890-abcd-ef1234567890

Array
(
    [_id] => j1o2h3n4-d5o6-7890-abcd-ef1234567890
    [username] => johndoe
    [email] => john@example.com
    [age] => 25
    [role] => user
)
\`\`\`

## Validation Errors

\`\`\`php
$users->setSchema([
    'username' => ['required' => true, 'type' => 'string', 'min' => 3],
    'email'    => ['required' => true, 'type' => 'string'],
    'age'      => ['type' => 'int', 'min' => 13],
    'role'     => ['type' => 'string', 'enum' => ['admin', 'user']],
]);

// Test invalid documents
$testCases = [
    ['username' => 'ab', 'email' => 'test@example.com'],  // username too short
    ['username' => 'john'],                                // missing required email
    ['username' => 'john', 'email' => 'test@example.com', 'age' => 10],  // age too low
    ['username' => 'john', 'email' => 'test@example.com', 'role' => 'superadmin'], // invalid enum
];

foreach ($testCases as $i => $doc) {
    try {
        $users->insert($doc);
        echo "Test $i: Inserted successfully\\n";
    } catch (\\BangronDB\\Exceptions\\ValidationException $e) {
        echo "Test $i: " . $e->getMessage() . "\\n";
    }
}
\`\`\`

**Output:**

\`\`\`text
Test 0: Field 'username' must be at least 3 characters
Test 1: Field 'email' is required
Test 2: Field 'age' must be at least 13
Test 3: Field 'role' must be one of: admin, user
\`\`\`

## Unique Constraint

\`\`\`php
$users->setSchema([
    'email' => ['type' => 'string', 'unique' => true],
]);

// Insert pertama
$users->insert(['email' => 'john@example.com', 'name' => 'John']);
echo "First insert: OK\\n";

// Insert duplikat
try {
    $users->insert(['email' => 'john@example.com', 'name' => 'John Doe']);
} catch (\\BangronDB\\Exceptions\\ValidationException $e) {
    echo "Duplicate insert: " . $e->getMessage() . "\\n";
}

// Insert dengan email berbeda
$users->insert(['email' => 'jane@example.com', 'name' => 'Jane']);
echo "Different email: OK\\n";
\`\`\`

**Output:**

\`\`\`text
First insert: OK
Duplicate insert: Field 'email' must be unique. Value 'john@example.com' already exists.
Different email: OK
\`\`\`

> **Warning:** Untuk field terenkripsi, field tersebut harus juga dijadikan **searchable** agar constraint bisa menemukan duplikat.

## Validasi Manual

\`\`\`php
$users->setSchema([
    'username' => ['required' => true, 'type' => 'string', 'min' => 3],
    'email'    => ['required' => true, 'type' => 'string'],
]);

// Validasi tanpa insert
$doc = [
    'username' => 'ab',
    'email'    => 'test@example.com',
];

try {
    $users->validate($doc);
    echo "Document is valid\\n";
} catch (\\BangronDB\\Exceptions\\ValidationException $e) {
    echo "Validation failed: " . $e->getMessage() . "\\n";
}

// Validasi dokumen yang benar
$doc = [
    'username' => 'johndoe',
    'email'    => 'john@example.com',
];

try {
    $users->validate($doc);
    echo "Document is valid: OK\\n";
} catch (\\BangronDB\\Exceptions\\ValidationException $e) {
    echo "Validation failed: " . $e->getMessage() . "\\n";
}
\`\`\`

**Output:**

\`\`\`text
Validation failed: Field 'username' must be at least 3 characters
Document is valid: OK
\`\`\`

> **Info:** Validasi enum menggunakan strict comparison. Nilai \`0\`, \`false\`, dan \`'0'\` dianggap berbeda.
`,
},

/* ═══════════════════════════════════════════════════ */
'hooks': {
  title: 'Hooks',
  description: 'Event lifecycle untuk insert, update, dan remove.',
  body: `
## Register Hook

\`\`\`php
// Auto timestamp on insert
$users->on('beforeInsert', function ($document) {
    $document['created_at'] = date('c');
    $document['updated_at'] = date('c');
    echo "[Hook] beforeInsert: Adding timestamps\\n";
    return $document;
});

// Log after insert
$users->on('afterInsert', function ($document, $insertId) {
    echo "[Hook] afterInsert: Inserted ID $insertId\\n";
});

// Auto timestamp on update
$users->on('beforeUpdate', function ($criteria, $data) {
    $data['updated_at'] = date('c');
    echo "[Hook] beforeUpdate: Updating timestamp\\n";
    return ['criteria' => $criteria, 'data' => $data];
});

// Test insert
echo "--- Inserting ---\\n";
$id = $users->insert(['name' => 'Alice', 'email' => 'alice@example.com']);

$user = $users->findOne(['_id' => $id]);
echo "Created at: {$user['created_at']}\\n";
echo "Updated at: {$user['updated_at']}\\n";

// Test update
echo "\\n--- Updating ---\\n";
sleep(1); // Wait 1 second to see different timestamp
$users->update(['_id' => $id], ['name' => 'Alice Updated']);

$user = $users->findOne(['_id' => $id]);
echo "Name: {$user['name']}\\n";
echo "Updated at: {$user['updated_at']}\\n";
\`\`\`

**Output:**

\`\`\`text
--- Inserting ---
[Hook] beforeInsert: Adding timestamps
[Hook] afterInsert: Inserted ID a1b2c3d4-e5f6-7890-abcd-ef1234567890
Created at: 2024-01-15T10:30:00+07:00
Updated at: 2024-01-15T10:30:00+07:00

--- Updating ---
[Hook] beforeUpdate: Updating timestamp
Name: Alice Updated
Updated at: 2024-01-15T10:30:01+07:00
\`\`\`

## Protection Hook

\`\`\`php
// Prevent deletion of protected documents
$users->on('beforeRemove', function ($document) {
    if ($document['protected'] ?? false) {
        echo "[Hook] beforeRemove: REJECTED - Document is protected\\n";
        return false; // reject deletion
    }
    echo "[Hook] beforeRemove: ALLOWED\\n";
    return true;
});

// Insert test data
$users->insert(['name' => 'Admin', 'protected' => true]);
$users->insert(['name' => 'Guest', 'protected' => false]);

echo "Before delete: " . $users->count() . " users\\n";

// Try to delete all
$deleted = $users->remove(['name' => 'Admin']);
echo "Deleted Admin: $deleted\\n";

$deleted = $users->remove(['name' => 'Guest']);
echo "Deleted Guest: $deleted\\n";

echo "After delete: " . $users->count() . " users\\n";
\`\`\`

**Output:**

\`\`\`text
Before delete: 2 users
[Hook] beforeRemove: REJECTED - Document is protected
Deleted Admin: 0
[Hook] beforeRemove: ALLOWED
Deleted Guest: 1
After delete: 1 users
\`\`\`

## Audit Log Hook

\`\`\`php
$auditLog = [];

$users->on('afterInsert', function ($document, $id) use (&$auditLog) {
    $auditLog[] = [
        'action'    => 'INSERT',
        'id'        => $id,
        'timestamp' => date('c'),
    ];
});

$users->on('afterUpdate', function ($criteria, $data) use (&$auditLog) {
    $auditLog[] = [
        'action'    => 'UPDATE',
        'criteria'  => json_encode($criteria),
        'timestamp' => date('c'),
    ];
});

$users->on('afterRemove', function ($document) use (&$auditLog) {
    $auditLog[] = [
        'action'    => 'DELETE',
        'id'        => $document['_id'],
        'timestamp' => date('c'),
    ];
});

// Perform operations
$id = $users->insert(['name' => 'Test User']);
$users->update(['_id' => $id], ['name' => 'Updated User']);
$users->remove(['_id' => $id]);

// Show audit log
echo "Audit Log:\\n";
foreach ($auditLog as $entry) {
    echo "  [{$entry['action']}] " . ($entry['id'] ?? $entry['criteria'] ?? '') . "\\n";
}
\`\`\`

**Output:**

\`\`\`text
Audit Log:
  [INSERT] t1e2s3t4-u5s6-7890-abcd-ef1234567890
  [UPDATE] {"_id":"t1e2s3t4-u5s6-7890-abcd-ef1234567890"}
  [DELETE] t1e2s3t4-u5s6-7890-abcd-ef1234567890
\`\`\`

## Events

| Event | Deskripsi |
|-------|-----------|
| \`beforeInsert\` | Sebelum insert, return dokumen yang dimodifikasi |
| \`afterInsert\` | Setelah insert, menerima dokumen + ID |
| \`beforeUpdate\` | Sebelum update, return criteria + data |
| \`afterUpdate\` | Setelah update |
| \`beforeRemove\` | Sebelum hapus, return false untuk reject |
| \`afterRemove\` | Setelah hapus |

## Hapus Hook

\`\`\`php
// Hapus semua hook untuk event tertentu
$users->off('beforeInsert');

// Atau hapus hook spesifik
$myCallback = fn($doc) => $doc;
$users->on('beforeInsert', $myCallback);
$users->off('beforeInsert', $myCallback);
\`\`\`

> **Tip:** Hook adalah primitif fleksibel. Bisa digunakan untuk ACL, audit logging, auto-timestamp, transformasi data — semuanya di application layer.
`,
},

/* ═══════════════════════════════════════════════════ */
'soft-deletes': {
  title: 'Soft Deletes',
  description: 'Hapus aman dengan kemampuan restore.',
  body: `
## Mengaktifkan

\`\`\`php
$users->useSoftDeletes(true);
echo "Soft deletes enabled\\n";
\`\`\`

**Output:**

\`\`\`text
Soft deletes enabled
\`\`\`

## Penggunaan

\`\`\`php
$users->useSoftDeletes(true);

// Insert test data
$users->insert(['name' => 'Alice', 'email' => 'alice@example.com']);
$users->insert(['name' => 'Bob', 'email' => 'bob@example.com']);
$users->insert(['name' => 'Charlie', 'email' => 'charlie@example.com']);

echo "Total users: " . $users->count() . "\\n";

// Soft delete Bob
$deleted = $users->remove(['name' => 'Bob']);
echo "Soft deleted: $deleted\\n";

// Normal query - tidak melihat Bob
echo "\\nNormal query (excludes deleted):\\n";
$all = $users->find()->toArray();
foreach ($all as $u) echo "  - {$u['name']}\\n";
echo "Count: " . $users->count() . "\\n";

// Query termasuk yang terhapus
echo "\\nWith trashed (includes deleted):\\n";
$all = $users->find()->withTrashed()->toArray();
foreach ($all as $u) {
    $status = isset($u['deleted_at']) ? ' [DELETED]' : '';
    echo "  - {$u['name']}$status\\n";
}

// Query hanya yang terhapus
echo "\\nOnly trashed (deleted only):\\n";
$trashed = $users->find()->onlyTrashed()->toArray();
foreach ($trashed as $u) echo "  - {$u['name']} (deleted at: {$u['deleted_at']})\\n";

// Restore Bob
echo "\\nRestoring Bob...\\n";
$restored = $users->restore(['name' => 'Bob']);
echo "Restored: $restored\\n";

// Verifikasi
echo "\\nAfter restore:\\n";
$all = $users->find()->toArray();
foreach ($all as $u) echo "  - {$u['name']}\\n";

// Force delete - hapus permanen
echo "\\nForce deleting Charlie...\\n";
$users->remove(['name' => 'Charlie']); // soft delete dulu
$forceDeleted = $users->forceDelete(['name' => 'Charlie']);
echo "Force deleted: $forceDeleted\\n";

// Charlie benar-benar hilang
echo "\\nAfter force delete (with trashed):\\n";
$all = $users->find()->withTrashed()->toArray();
foreach ($all as $u) echo "  - {$u['name']}\\n";
\`\`\`

**Output:**

\`\`\`text
Total users: 3
Soft deleted: 1

Normal query (excludes deleted):
  - Alice
  - Charlie
Count: 2

With trashed (includes deleted):
  - Alice
  - Bob [DELETED]
  - Charlie

Only trashed (deleted only):
  - Bob (deleted at: 2024-01-15T10:30:00+07:00)

Restoring Bob...
Restored: 1

After restore:
  - Alice
  - Bob
  - Charlie

Force deleting Charlie...
Force deleted: 1

After force delete (with trashed):
  - Alice
  - Bob
\`\`\`

## Custom Field Name

\`\`\`php
$users->setDeletedAtField('removed_at'); // default: deleted_at
$users->useSoftDeletes(true);

$users->insert(['name' => 'Test']);
$users->remove(['name' => 'Test']);

$trashed = $users->find()->onlyTrashed()->toArray();
print_r($trashed[0]);
\`\`\`

**Output:**

\`\`\`text
Array
(
    [_id] => t1e2s3t4-...
    [name] => Test
    [removed_at] => 2024-01-15T10:30:00+07:00
)
\`\`\`
`,
},

/* ═══════════════════════════════════════════════════ */
'ttl': {
  title: 'TTL Expiration',
  description: 'Auto-expiration dokumen berdasarkan waktu.',
  body: `
## Mengaktifkan TTL

\`\`\`php
// Dengan default 1 jam (3600 detik)
$sessions->enableTtl('expires_at', 3600);
echo "TTL enabled with 1 hour default\\n";

// Tanpa default - harus set manual per dokumen
$cache->enableTtl('expires_at');
echo "TTL enabled without default\\n";
\`\`\`

**Output:**

\`\`\`text
TTL enabled with 1 hour default
TTL enabled without default
\`\`\`

## Insert dengan TTL

\`\`\`php
$sessions->enableTtl('expires_at', 3600); // 1 hour default

// Otomatis menggunakan default TTL
$id1 = $sessions->insert([
    'user_id' => 'user-123',
    'token'   => 'abc123',
]);

$session = $sessions->findOne(['_id' => $id1]);
echo "Session 1 expires: {$session['expires_at']}\\n";

// Override TTL - expire in 5 minutes
$id2 = $sessions->insert([
    'user_id'    => 'user-456',
    'token'      => 'def456',
    'expires_at' => time() + 300,
]);

$session = $sessions->findOne(['_id' => $id2]);
echo "Session 2 expires: " . date('c', $session['expires_at']) . "\\n";

// Custom expiry date
$id3 = $sessions->insert([
    'user_id'    => 'user-789',
    'token'      => 'ghi789',
    'expires_at' => strtotime('+7 days'),
]);

$session = $sessions->findOne(['_id' => $id3]);
echo "Session 3 expires: " . date('c', $session['expires_at']) . "\\n";
\`\`\`

**Output:**

\`\`\`text
Session 1 expires: 1705304600
Session 2 expires: 2024-01-15T11:35:00+07:00
Session 3 expires: 2024-01-22T10:30:00+07:00
\`\`\`

## Membersihkan & Statistik

\`\`\`php
$cache->enableTtl('expires_at', 60); // 1 minute default

// Insert some items
$cache->insert(['key' => 'item1', 'value' => 'data1']);
$cache->insert(['key' => 'item2', 'value' => 'data2']);
$cache->insert(['key' => 'item3', 'value' => 'data3', 'expires_at' => time() - 10]); // already expired

echo "Total items: " . $cache->count() . "\\n";

// Check expired count
$expiredCount = $cache->expiredCount();
echo "Expired items: $expiredCount\\n";

// TTL statistics
$stats = $cache->ttlStats();
print_r($stats);

// Clean expired
$removed = $cache->cleanExpired();
echo "\\nCleaned: $removed expired items\\n";
echo "Remaining: " . $cache->count() . " items\\n";
\`\`\`

**Output:**

\`\`\`text
Total items: 3
Expired items: 1

Array
(
    [total] => 3
    [expired] => 1
    [active] => 2
    [ttl_field] => expires_at
    [default_ttl] => 60
)

Cleaned: 1 expired items
Remaining: 2 items
\`\`\`

## Menonaktifkan

\`\`\`php
$cache->disableTtl();
echo "TTL disabled\\n";
\`\`\`

**Output:**

\`\`\`text
TTL disabled
\`\`\`

> **Warning:** \`cleanExpired()\` harus dipanggil manual (misalnya via cron). BangronDB tidak membersihkan otomatis.

Contoh cron job:

\`\`\`php
// cleanup-expired.php - jalankan via cron setiap 5 menit
$sessions->cleanExpired();
$cache->cleanExpired();
$tokens->cleanExpired();
\`\`\`
`,
},

/* ═══════════════════════════════════════════════════ */
'relationships': {
  title: 'Relationships',
  description: 'Populate relasi antar-collection.',
  body: `
## Populate via Cursor

\`\`\`php
// Setup data
$db->createCollection('test', 'users');
$db->createCollection('test', 'posts');

$users = $db->users;
$posts = $db->posts;

// Insert users
$userId1 = $users->insert(['name' => 'Alice', 'email' => 'alice@example.com']);
$userId2 = $users->insert(['name' => 'Bob', 'email' => 'bob@example.com']);

// Insert posts with author reference
$posts->insert(['title' => 'First Post', 'author_id' => $userId1, 'content' => 'Hello World']);
$posts->insert(['title' => 'Second Post', 'author_id' => $userId1, 'content' => 'More content']);
$posts->insert(['title' => 'Bob Post', 'author_id' => $userId2, 'content' => 'Bob here']);

// Populate author data
$results = $posts->find()
    ->populate('author_id', $users, ['as' => 'author'])
    ->toArray();

echo "Posts with authors:\\n";
foreach ($results as $post) {
    echo "  - {$post['title']} by {$post['author']['name']}\\n";
}
\`\`\`

**Output:**

\`\`\`text
Posts with authors:
  - First Post by Alice
  - Second Post by Alice
  - Bob Post by Bob
\`\`\`

## Populate Manual

\`\`\`php
// Setup collections
$db->createCollection('test', 'posts');
$db->createCollection('test', 'comments');

$posts = $db->posts;
$comments = $db->comments;

// Insert post
$postId = $posts->insert([
    'title' => 'My Article',
    'content' => 'Article content here...',
]);

// Insert comments
$commentId1 = $comments->insert(['post_id' => $postId, 'text' => 'Great article!', 'author' => 'Reader 1']);
$commentId2 = $comments->insert(['post_id' => $postId, 'text' => 'Thanks for sharing', 'author' => 'Reader 2']);
$commentId3 = $comments->insert(['post_id' => $postId, 'text' => 'Very helpful', 'author' => 'Reader 3']);

// Update post dengan comment IDs
$posts->update(['_id' => $postId], [
    'comment_ids' => [$commentId1, $commentId2, $commentId3],
]);

// Get post
$post = $posts->findOne(['_id' => $postId]);

// Populate comments manually
$post = $posts->populate(
    $post,
    'comment_ids',      // field yang berisi references
    'test.comments',    // target collection
    '_id',              // foreign key
    'comments'          // output field name
);

echo "Post: {$post['title']}\\n";
echo "Comments:\\n";
foreach ($post['comments'] as $comment) {
    echo "  - {$comment['author']}: {$comment['text']}\\n";
}
\`\`\`

**Output:**

\`\`\`text
Post: My Article
Comments:
  - Reader 1: Great article!
  - Reader 2: Thanks for sharing
  - Reader 3: Very helpful
\`\`\`

## Populate dengan Options

\`\`\`php
// Populate dengan projection (select specific fields)
$results = $posts->find()
    ->populate('author_id', $users, [
        'as' => 'author',
        'fields' => ['name', 'email'], // only get these fields
    ])
    ->toArray();

echo "Posts (with limited author fields):\\n";
foreach ($results as $post) {
    echo "  - {$post['title']}\\n";
    echo "    Author: {$post['author']['name']} <{$post['author']['email']}>\\n";
}
\`\`\`

**Output:**

\`\`\`text
Posts (with limited author fields):
  - First Post
    Author: Alice <alice@example.com>
  - Second Post
    Author: Alice <alice@example.com>
\`\`\`
`,
},

/* ═══════════════════════════════════════════════════ */
'indexing': {
  title: 'Indexing & Performance',
  description: 'Buat index dan monitor performa.',
  body: `
## Membuat Index

\`\`\`php
// Index pada single field
$users->createIndex('email');
echo "Index created on 'email'\\n";

// Index pada nested field (dot notation)
$users->createIndex('address.city');
echo "Index created on 'address.city'\\n";

// Index dengan custom name
$users->createIndex('status', 'idx_user_status');
echo "Index 'idx_user_status' created on 'status'\\n";

// Drop index
$db->dropIndex('idx_user_status');
echo "Index 'idx_user_status' dropped\\n";
\`\`\`

**Output:**

\`\`\`text
Index created on 'email'
Index created on 'address.city'
Index 'idx_user_status' created on 'status'
Index 'idx_user_status' dropped
\`\`\`

## Explain Query

\`\`\`php
// Insert sample data
for ($i = 0; $i < 1000; $i++) {
    $users->insert([
        'name'   => "User $i",
        'email'  => "user$i@example.com",
        'status' => $i % 3 === 0 ? 'inactive' : 'active',
        'age'    => rand(18, 65),
    ]);
}

// Create index on frequently queried field
$users->createIndex('status');
$users->createIndex('age');

// Explain query
$explanation = $users->explain([
    'status' => 'active',
    'age'    => ['\\$gte' => 21],
]);

echo "Query Plan:\\n";
echo "  Uses Index: " . ($explanation['query_plan']['uses_index'] ? 'Yes' : 'No') . "\\n";
echo "  Strategy: {$explanation['query_plan']['strategy']}\\n";

echo "\\nPerformance:\\n";
echo "  Documents Scanned: {$explanation['performance']['documents_scanned']}\\n";
echo "  Documents Matched: {$explanation['performance']['documents_matched']}\\n";
echo "  Execution Time: {$explanation['performance']['execution_time_ms']}ms\\n";

echo "\\nSuggestions:\\n";
if (empty($explanation['suggestions'])) {
    echo "  No suggestions - query is optimized\\n";
} else {
    foreach ($explanation['suggestions'] as $suggestion) {
        echo "  - $suggestion\\n";
    }
}
\`\`\`

**Output:**

\`\`\`text
Query Plan:
  Uses Index: Yes
  Strategy: SQL-first (json_extract)

Performance:
  Documents Scanned: 1000
  Documents Matched: 467
  Execution Time: 12ms

Suggestions:
  No suggestions - query is optimized
\`\`\`

## Health & Monitoring

\`\`\`php
// Health Metrics
$health = $db->getHealthMetrics();
echo "Health Metrics:\\n";
print_r($health);

// Health Report
$report = $db->getHealthReport();
echo "\\nHealth Report:\\n";
echo "  Status: {$report['status']}\\n";
echo "  Database Size: {$report['database_size_bytes']} bytes\\n";

// Performance Metrics
$perf = $db->getPerformanceMetrics();
echo "\\nPerformance Metrics:\\n";
print_r($perf);

// Collection Metrics
$coll = $db->getCollectionMetrics();
echo "\\nCollection Metrics:\\n";
foreach ($coll as $name => $metrics) {
    echo "  $name: {$metrics['document_count']} documents\\n";
}
\`\`\`

**Output:**

\`\`\`text
Health Metrics:
Array
(
    [database_size] => 524288
    [table_count] => 3
    [index_count] => 5
    [page_count] => 128
    [freelist_count] => 0
)

Health Report:
  Status: healthy
  Database Size: 524288 bytes

Performance Metrics:
Array
(
    [cache_hit_ratio] => 0.95
    [average_query_time_ms] => 8.5
    [queries_per_second] => 120
)

Collection Metrics:
  users: 1000 documents
  posts: 50 documents
  sessions: 25 documents
\`\`\`

## Integrity Check & Vacuum

\`\`\`php
// Check database integrity
$isValid = $db->checkIntegrity();
echo "Integrity Check: " . ($isValid ? 'PASSED' : 'FAILED') . "\\n";

// Optimize database file
echo "Running VACUUM...\\n";
$db->vacuum();
echo "VACUUM completed\\n";
\`\`\`

**Output:**

\`\`\`text
Integrity Check: PASSED
Running VACUUM...
VACUUM completed
\`\`\`

## Change Notification

\`\`\`php
$users->insert(['name' => 'New User']);

$lastModified = $users->getLastModified();
echo "Last Modified:\\n";
print_r($lastModified);

// Manual notification
$users->notifyChange();
$lastModified = $users->getLastModified();
echo "\\nAfter notifyChange():\\n";
print_r($lastModified);
\`\`\`

**Output:**

\`\`\`text
Last Modified:
Array
(
    [version] => 42
    [last_updated] => 2024-01-15T10:30:45+07:00
)

After notifyChange():
Array
(
    [version] => 43
    [last_updated] => 2024-01-15T10:30:46+07:00
)
\`\`\`
`,
},

/* ═══════════════════════════════════════════════════ */
'streaming': {
  title: 'Cursor Streaming',
  description: 'Generator-based streaming untuk efisiensi memori.',
  body: `
## Stream

Untuk dataset besar, gunakan \`stream()\` yang menghasilkan \`Generator\`:

\`\`\`php
// Insert large dataset
echo "Inserting 10000 documents...\\n";
for ($i = 0; $i < 10000; $i++) {
    $logs->insert([
        'timestamp' => date('c'),
        'level'     => ['info', 'warning', 'error'][rand(0, 2)],
        'message'   => "Log message #$i",
        'data'      => str_repeat('x', 1000), // 1KB payload
    ]);
}
echo "Done inserting.\\n\\n";

// Memory-efficient streaming
$processed = 0;
$errorCount = 0;
$warningCount = 0;

echo "Processing with stream()...\\n";
$startTime = microtime(true);
$startMemory = memory_get_usage();

foreach ($logs->stream(['level' => ['\\$in' => ['error', 'warning']]], [
    'sort'  => ['timestamp' => -1],
    'limit' => 5000,
]) as $doc) {
    // Process one document at a time
    if ($doc['level'] === 'error') $errorCount++;
    if ($doc['level'] === 'warning') $warningCount++;
    $processed++;
}

$endTime = microtime(true);
$peakMemory = memory_get_peak_usage();

echo "Processed: $processed documents\\n";
echo "Errors: $errorCount\\n";
echo "Warnings: $warningCount\\n";
echo "Time: " . round(($endTime - $startTime) * 1000) . "ms\\n";
echo "Peak Memory: " . round($peakMemory / 1024 / 1024, 2) . "MB\\n";
\`\`\`

**Output:**

\`\`\`text
Inserting 10000 documents...
Done inserting.

Processing with stream()...
Processed: 5000 documents
Errors: 1672
Warnings: 1658
Time: 245ms
Peak Memory: 8.5MB
\`\`\`

## Perbandingan Memory

\`\`\`php
// Method 1: toArray() - loads all into memory
echo "Testing toArray()...\\n";
$startMem = memory_get_usage();
$all = $logs->find()->limit(5000)->toArray();
$afterMem = memory_get_usage();
echo "toArray() memory used: " . round(($afterMem - $startMem) / 1024 / 1024, 2) . "MB\\n";
unset($all);

// Method 2: stream() - one at a time
echo "\\nTesting stream()...\\n";
$startMem = memory_get_usage();
foreach ($logs->stream([], ['limit' => 5000]) as $doc) {
    // Process but don't store
    $dummy = strlen($doc['message']);
}
$afterMem = memory_get_usage();
echo "stream() memory used: " . round(($afterMem - $startMem) / 1024, 2) . "KB\\n";
\`\`\`

**Output:**

\`\`\`text
Testing toArray()...
toArray() memory used: 45.2MB

Testing stream()...
stream() memory used: 12.5KB
\`\`\`

> **Tip:** Gunakan \`stream()\` untuk:
> - Export data ke file (CSV, JSON)
> - Batch processing
> - Real-time data pipeline
> - Any operation on large datasets
`,
},

/* ═══════════════════════════════════════════════════ */
'transactions': {
  title: 'Transactions',
  description: 'Transaksi database via PDO.',
  body: `
## Menggunakan Transaksi

BangronDB menggunakan PDO SQLite, jadi Anda bisa memakai transaksi langsung:

\`\`\`php
$db->connection->beginTransaction();

try {
    // Insert user
    $userId = $db->users->insert([
        'name'  => 'Alice',
        'email' => 'alice@example.com',
    ]);
    echo "Inserted user: $userId\\n";

    // Insert profile
    $profileId = $db->profiles->insert([
        'user_id' => $userId,
        'bio'     => 'Hello, I am Alice!',
        'avatar'  => 'alice.jpg',
    ]);
    echo "Inserted profile: $profileId\\n";

    // Insert settings
    $db->settings->insert([
        'user_id'       => $userId,
        'theme'         => 'dark',
        'notifications' => true,
    ]);
    echo "Inserted settings\\n";

    // All good - commit
    $db->connection->commit();
    echo "\\nTransaction COMMITTED\\n";

} catch (\\Throwable $e) {
    // Something went wrong - rollback
    $db->connection->rollBack();
    echo "\\nTransaction ROLLED BACK: " . $e->getMessage() . "\\n";
    throw $e;
}

// Verify
echo "\\nVerification:\\n";
echo "Users: " . $db->users->count() . "\\n";
echo "Profiles: " . $db->profiles->count() . "\\n";
echo "Settings: " . $db->settings->count() . "\\n";
\`\`\`

**Output:**

\`\`\`text
Inserted user: a1b2c3d4-e5f6-7890-abcd-ef1234567890
Inserted profile: p1r2o3f4-i5l6-7890-abcd-ef1234567890
Inserted settings

Transaction COMMITTED

Verification:
Users: 1
Profiles: 1
Settings: 1
\`\`\`

## Rollback Example

\`\`\`php
$db->connection->beginTransaction();

try {
    $db->orders->insert(['product' => 'Laptop', 'qty' => 1]);
    echo "Order inserted\\n";

    $db->inventory->insert(['product' => 'Laptop', 'stock' => -1]); // This will fail validation
    echo "Inventory updated\\n";

    // Simulate error
    if (true) {
        throw new \\Exception("Insufficient stock!");
    }

    $db->connection->commit();

} catch (\\Throwable $e) {
    $db->connection->rollBack();
    echo "ROLLED BACK: " . $e->getMessage() . "\\n";
}

echo "\\nOrders count: " . $db->orders->count() . "\\n";
\`\`\`

**Output:**

\`\`\`text
Order inserted
ROLLED BACK: Insufficient stock!

Orders count: 0
\`\`\`
`,
},

/* ═══════════════════════════════════════════════════ */
'configuration': {
  title: 'Configuration',
  description: 'Konfigurasi collection yang persisten.',
  body: `
## Simpan Konfigurasi

\`\`\`php
// Configure collection
$users->setIdModePrefix('USR');
$users->setSearchableFields(['email'], true);
$users->setSchema([
    'email' => ['required' => true, 'type' => 'string', 'unique' => true],
    'name'  => ['required' => true, 'type' => 'string'],
]);
$users->useSoftDeletes(true);

// Save to database
$users->saveConfiguration();
echo "Configuration saved!\\n";

// Verify ID mode
$id = $users->insert(['name' => 'Test', 'email' => 'test@example.com']);
echo "Generated ID: $id\\n";
\`\`\`

**Output:**

\`\`\`text
Configuration saved!
Generated ID: USR-000001
\`\`\`

## Custom Config

\`\`\`php
// Store application-specific config
$users->setCustomConfig('acl', [
    'admin'  => ['create', 'read', 'update', 'delete'],
    'editor' => ['create', 'read', 'update'],
    'viewer' => ['read'],
]);

$users->setCustomConfig('max_login_attempts', 3);
$users->setCustomConfig('session_timeout', 3600);

$users->saveConfiguration();
echo "Custom config saved!\\n";

// Read config
$acl = $users->getCustomConfig('acl');
echo "\\nACL for admin: " . implode(', ', $acl['admin']) . "\\n";

$maxAttempts = $users->getCustomConfig('max_login_attempts');
echo "Max login attempts: $maxAttempts\\n";

// Get with default value
$theme = $users->getCustomConfig('default_theme', 'light');
echo "Default theme: $theme\\n";

// Get all custom config
$all = $users->getAllCustomConfig();
echo "\\nAll custom configs:\\n";
print_r($all);
\`\`\`

**Output:**

\`\`\`text
Custom config saved!

ACL for admin: create, read, update, delete
Max login attempts: 3
Default theme: light

All custom configs:
Array
(
    [acl] => Array
        (
            [admin] => Array
                (
                    [0] => create
                    [1] => read
                    [2] => update
                    [3] => delete
                )
            [editor] => Array
                (
                    [0] => create
                    [1] => read
                    [2] => update
                )
            [viewer] => Array
                (
                    [0] => read
                )
        )
    [max_login_attempts] => 3
    [session_timeout] => 3600
)
\`\`\`

> **Warning:** Sensitive keys (\`encryption_key\`, \`password\`, \`secret\`, \`token\`, dll.) ditolak otomatis dan tidak bisa disimpan via \`setCustomConfig\`.

## ID Modes

\`\`\`php
// UUID (default)
$collection->setIdModeAuto();
$id = $collection->insert(['name' => 'Test 1']);
echo "Auto (UUID): $id\\n";

// Manual - Anda harus provide _id
$collection->setIdModeManual();
$id = $collection->insert(['_id' => 'my-custom-id', 'name' => 'Test 2']);
echo "Manual: $id\\n";

// Prefix
$collection->setIdModePrefix('ORD');
$id = $collection->insert(['name' => 'Test 3']);
echo "Prefix: $id\\n";

$id = $collection->insert(['name' => 'Test 4']);
echo "Prefix: $id\\n";
\`\`\`

**Output:**

\`\`\`text
Auto (UUID): 550e8400-e29b-41d4-a716-446655440000
Manual: my-custom-id
Prefix: ORD-000001
Prefix: ORD-000002
\`\`\`
`,
},

/* ═══════════════════════════════════════════════════ */
'api-client': {
  title: 'Client API',
  description: 'Referensi lengkap API Client.',
  body: `
## Constructor

\`\`\`php
new Client($path, $options = [])
\`\`\`

**Parameters:**
- \`$path\` - Path ke direktori database, atau \`:memory:\` untuk in-memory
- \`$options\` - Array opsi: \`encryption_key\`, \`query_logging\`, \`performance_monitoring\`

## Methods

| Method | Return | Keterangan |
|--------|--------|-----------|
| \`createDB($name, $options)\` | \`Database\` | Membuat database |
| \`dbExists($name)\` | \`bool\` | Cek database ada |
| \`listDBs()\` | \`array\` | Daftar database |
| \`selectDB($name)\` | \`Database\` | Ambil database |
| \`renameDB($old, $new)\` | \`void\` | Rename database |
| \`dropDB($name)\` | \`void\` | Hapus database |
| \`createCollection($db, $col)\` | \`Collection\` | Buat collection |
| \`collectionExists($db, $col)\` | \`bool\` | Cek collection |
| \`listCollections($db)\` | \`array\` | Daftar collection |
| \`renameCollection($db, $old, $new)\` | \`void\` | Rename collection |
| \`dropCollection($db, $col)\` | \`void\` | Hapus collection |
| \`selectCollection($db, $col)\` | \`Collection\` | Ambil collection |
| \`close()\` | \`void\` | Tutup koneksi |

## Contoh Penggunaan

\`\`\`php
use BangronDB\\Client;

$client = new Client(__DIR__ . '/data', [
    'encryption_key' => $_ENV['DB_KEY'],
]);

// Create
$client->createDB('myapp');
$client->createCollection('myapp', 'users');

// Check
var_dump($client->dbExists('myapp'));          // bool(true)
var_dump($client->collectionExists('myapp', 'users')); // bool(true)

// List
print_r($client->listDBs());        // ['myapp']
print_r($client->listCollections('myapp')); // ['users']

// Select
$db = $client->selectDB('myapp');
$users = $client->selectCollection('myapp', 'users');
// Or use magic getter:
$users = $client->myapp->users;

// Cleanup
$client->dropCollection('myapp', 'users');
$client->dropDB('myapp');
$client->close();
\`\`\`

**Output:**

\`\`\`text
bool(true)
bool(true)
Array
(
    [0] => myapp
)
Array
(
    [0] => users
)
\`\`\`
`,
},

/* ═══════════════════════════════════════════════════ */
'api-database': {
  title: 'Database API',
  description: 'Referensi lengkap API Database.',
  body: `
## Methods

| Method | Return | Keterangan |
|--------|--------|-----------|
| \`selectCollection($name)\` | \`Collection\` | Ambil collection |
| \`createCollection($name)\` | \`Collection\` | Buat collection |
| \`collectionExists($name)\` | \`bool\` | Cek ada |
| \`renameCollection($old, $new)\` | \`void\` | Rename |
| \`dropCollection($name)\` | \`void\` | Hapus |
| \`getCollectionNames()\` | \`array\` | Daftar nama |
| \`createJsonIndex($col, $field, $name)\` | \`void\` | Buat index JSON |
| \`dropIndex($name)\` | \`void\` | Hapus index |
| \`getHealthMetrics()\` | \`array\` | Health metrics |
| \`getHealthReport()\` | \`array\` | Health report |
| \`getPerformanceMetrics()\` | \`array\` | Performa |
| \`getCollectionMetrics()\` | \`array\` | Metrik collection |
| \`saveCollectionConfig($name, $cfg)\` | \`void\` | Simpan config |
| \`loadCollectionConfig($name)\` | \`array\` | Muat config |
| \`deleteCollectionConfig($name)\` | \`void\` | Hapus config |
| \`checkIntegrity()\` | \`bool\` | Integrity check |
| \`vacuum()\` | \`void\` | Optimasi file |

## Magic Getter

\`\`\`php
$db->users; // sama dengan $db->selectCollection('users')
\`\`\`

## Contoh

\`\`\`php
$db = $client->selectDB('myapp');

// Collections
$db->createCollection('posts');
$db->createCollection('comments');

$names = $db->getCollectionNames();
print_r($names);

// Health
$health = $db->getHealthMetrics();
echo "DB Size: {$health['database_size']} bytes\\n";

// Index
$db->createJsonIndex('posts', 'author_id', 'idx_author');
$db->dropIndex('idx_author');

// Maintenance
$db->checkIntegrity();
$db->vacuum();
\`\`\`

**Output:**

\`\`\`text
Array
(
    [0] => posts
    [1] => comments
)
DB Size: 24576 bytes
\`\`\`
`,
},

/* ═══════════════════════════════════════════════════ */
'api-collection': {
  title: 'Collection API',
  description: 'Referensi lengkap API Collection.',
  body: `
## CRUD

| Method | Keterangan |
|--------|-----------|
| \`insert($document)\` | Insert satu/banyak, return ID/count |
| \`insertMany($documents)\` | Insert batch, return detail |
| \`find($criteria, $projection)\` | Query → Cursor |
| \`findOne($criteria, $projection)\` | Query satu |
| \`update($criteria, $data, $merge)\` | Update, return affected |
| \`updateMany($criteria, $data)\` | Update batch, return detail |
| \`remove($criteria)\` | Hapus, return count |
| \`deleteMany($criteria)\` | Delete batch, return detail |
| \`count($criteria)\` | Hitung dokumen |
| \`save($document)\` | Upsert |

## Advanced

| Method | Keterangan |
|--------|-----------|
| \`aggregate($pipeline)\` | Aggregation pipeline |
| \`explain($criteria)\` | Query plan analysis |
| \`stream($criteria, $options)\` | Generator streaming |
| \`drop()\` | Hapus collection |
| \`renameCollection($newName)\` | Rename |

## ID Modes

| Method | Keterangan |
|--------|-----------|
| \`setIdModeAuto()\` | UUID otomatis |
| \`setIdModeManual()\` | ID manual |
| \`setIdModePrefix($prefix)\` | Prefix (USR-000001) |

## Encryption

| Method | Keterangan |
|--------|-----------|
| \`setEncryptionKey($key, $version)\` | Set key |
| \`rotateEncryptionKey($new, $ver)\` | Rotasi key |
| \`reencryptAll()\` | Re-encrypt semua |
| \`setSearchableFields($fields, $hash)\` | Set searchable |
| \`removeSearchableField($field)\` | Hapus field |
| \`rehashSearchableField($field)\` | Rehash index |

## Schema

| Method | Keterangan |
|--------|-----------|
| \`setSchema($schema)\` | Set schema |
| \`validate($document)\` | Validasi manual |

## TTL

| Method | Keterangan |
|--------|-----------|
| \`enableTtl($field, $seconds)\` | Aktifkan TTL |
| \`disableTtl()\` | Nonaktifkan |
| \`cleanExpired()\` | Hapus expired |
| \`expiredCount()\` | Hitung expired |
| \`ttlStats()\` | Statistik TTL |

## Soft Delete

| Method | Keterangan |
|--------|-----------|
| \`useSoftDeletes($enabled)\` | Aktifkan |
| \`setDeletedAtField($field)\` | Custom field |
| \`restore($criteria)\` | Restore |
| \`forceDelete($criteria)\` | Hapus permanen |

## Hooks

| Method | Keterangan |
|--------|-----------|
| \`on($event, $callback)\` | Register hook |
| \`off($event, $callback)\` | Hapus hook |

## Config

| Method | Keterangan |
|--------|-----------|
| \`createIndex($field, $name)\` | Buat index |
| \`getLastModified()\` | Metadata perubahan |
| \`notifyChange()\` | Manual notification |
| \`saveConfiguration()\` | Simpan config |
| \`setCustomConfig($key, $val)\` | Set custom |
| \`getCustomConfig($key, $default)\` | Get custom |
| \`getAllCustomConfig()\` | Get all custom |
`,
},

/* ═══════════════════════════════════════════════════ */
'api-cursor': {
  title: 'Cursor API',
  description: 'Referensi lengkap API Cursor.',
  body: `
## Methods

| Method | Keterangan |
|--------|-----------|
| \`limit($n)\` | Batas hasil |
| \`skip($n)\` | Lewati N awal |
| \`sort($fields)\` | Urutkan (1 = ASC, -1 = DESC) |
| \`populate($field, $col, $opts)\` | Populate relasi |
| \`withTrashed()\` | Sertakan soft-deleted |
| \`onlyTrashed()\` | Hanya soft-deleted |
| \`toArray()\` | Materialisasi ke array |
| \`toArraySafe($maxResults)\` | Materialisasi dengan batas |
| \`each($callback)\` | Iterasi tiap dokumen |

## Contoh Chaining

\`\`\`php
$results = $users->find(['status' => 'active'])
    ->sort(['created_at' => -1])
    ->skip(20)
    ->limit(10)
    ->populate('team_id', $db->teams, ['as' => 'team'])
    ->toArray();

echo "Found " . count($results) . " users\\n";
foreach ($results as $user) {
    echo "  - {$user['name']} (Team: {$user['team']['name']})\\n";
}
\`\`\`

**Output:**

\`\`\`text
Found 10 users
  - Alice (Team: Engineering)
  - Bob (Team: Marketing)
  - Charlie (Team: Engineering)
  ...
\`\`\`

## toArraySafe

\`\`\`php
// Limit hasil untuk keamanan
$results = $users->find()
    ->toArraySafe(100); // Max 100 dokumen

echo "Got " . count($results) . " users (max 100)\\n";
\`\`\`

**Output:**

\`\`\`text
Got 100 users (max 100)
\`\`\`

## each

\`\`\`php
$total = 0;
$users->find(['status' => 'active'])
    ->each(function ($user) use (&$total) {
        $total += $user['balance'];
    });

echo "Total balance: \\$$total\\n";
\`\`\`

**Output:**

\`\`\`text
Total balance: $125000
\`\`\`
`,
},

/* ═══════════════════════════════════════════════════ */
'security': {
  title: 'Security',
  description: 'Fitur keamanan dan best practices.',
  body: `
## Guardrails

| Fitur | Tujuan |
|-------|--------|
| Closure-only \\$where/\\$func | Mencegah RCE (Remote Code Execution) |
| Validasi field name | Mencegah injection |
| PRAGMA key escaping | Mencegah SQLite injection |
| Regex hardening (ReDoS) | Mengurangi catastrophic backtracking |
| Validasi path | Mengurangi path traversal |
| Sensitive config key blocking | Mencegah credential leakage |
| strict_types=1 | Type safety |

## Best Practices

- Simpan encryption key di **environment variable** atau secret manager
- Gunakan \`setSearchableFields()\` dengan \`hash: true\` untuk field sensitif
- Panggil \`cleanExpired()\` berkala via cron
- Gunakan \`explain()\` untuk analisis performa query
- Buat **index** pada field yang sering di-query
- Aktifkan **schema validation** untuk integritas data
- Gunakan **hooks** untuk audit logging

## Contoh Secure Configuration

\`\`\`php
use BangronDB\\Client;

// [OK] Ambil key dari environment
$encryptionKey = $_ENV['BANGRONDB_ENCRYPTION_KEY']
    ?? throw new \\RuntimeException('Encryption key not set!');

$client = new Client(__DIR__ . '/data', [
    'encryption_key' => $encryptionKey,
]);

$users = $client->selectCollection('app', 'users');

// [OK] Enkripsi dengan searchable fields
$users->setEncryptionKey($encryptionKey);
$users->setSearchableFields([
    'email' => ['hash' => true],  // Blind index untuk search
]);

// [OK] Schema validation
$users->setSchema([
    'email'    => ['required' => true, 'unique' => true],
    'password' => ['required' => true], // Akan terenkripsi
]);

// [OK] Soft deletes untuk audit trail
$users->useSoftDeletes(true);

// [OK] Hooks untuk audit
$users->on('afterInsert', function ($doc, $id) {
    // Log to audit system
    error_log("User created: $id");
});

$users->on('afterRemove', function ($doc) {
    error_log("User deleted: {$doc['_id']}");
});

$users->saveConfiguration();
echo "Secure configuration applied!\\n";
\`\`\`

**Output:**

\`\`\`text
Secure configuration applied!
\`\`\`

## SecurityAuditor

BangronDB menyediakan \`SecurityAuditor\` sebagai utilitas opsional:

\`\`\`php
use BangronDB\\Security\\SecurityAuditor;

$auditor = new SecurityAuditor($client);
$report = $auditor->audit();

print_r($report);
\`\`\`

**Output:**

\`\`\`text
Array
(
    [status] => passed
    [checks] => Array
        (
            [encryption_enabled] => true
            [schema_validation] => true
            [soft_deletes] => true
            [index_coverage] => 85%
        )
    [warnings] => Array
        (
            [0] => Consider adding index on 'created_at' field
        )
)
\`\`\`

> Lihat [SECURITY_USAGE_GUIDE.md](#/docs/security) untuk panduan lengkap.
`,
},

/* ═══════════════════════════════════════════════════ */
'examples': {
  title: 'Contoh Lengkap',
  description: 'Daftar 24 contoh end-to-end.',
  body: `
## Daftar Contoh

Semua contoh tersedia di folder [\`examples/\`](https://github.com/herdianrony/BangronDB/tree/master/examples) di GitHub.

| No | File | Topik |
|----|------|-------|
| 01 | 01-quick-start-crud.php | Quick start CRUD |
| 02 | 02-query-operators.php | Query operators |
| 03 | 03-encryption-searchable.php | Enkripsi & searchable fields |
| 04 | 04-schema-validation.php | Schema validation |
| 05 | 05-bulk-operations.php | Bulk insert/update/delete |
| 06 | 06-aggregation-pipeline.php | Aggregation pipeline |
| 07 | 07-cursor-streaming.php | Cursor streaming (Generator) |
| 08 | 08-ttl-expiration.php | TTL auto-expiration |
| 09 | 09-explain-query.php | Explain query plan |
| 10 | 10-soft-deletes.php | Soft delete & restore |
| 11 | 11-hooks.php | Hooks lifecycle |
| 12 | 12-relationships-populate.php | Relasi & populate |
| 13 | 13-transactions.php | Transaksi |
| 14 | 14-indexing-health-monitoring.php | Indexing & health monitoring |
| 15 | 15-dynamic-configuration.php | Konfigurasi dinamis |
| 16 | 16-multiple-databases.php | Multiple databases |
| 17 | 17-id-modes-collection-management.php | ID modes & collection management |
| 18 | 18-security-features.php | Fitur keamanan |
| 19 | 19-ecommerce-app.php | Aplikasi e-commerce lengkap |
| 20 | 20-auth-encrypted.php | Auth dengan enkripsi |
| 21 | 21-key-rotation.php | Key rotation |
| 22 | 22-rbac-users-roles-permissions.php | RBAC (pola aplikasi) |
| 23 | 23-acl-relation-type.php | ACL dengan relation type |
| 24 | 24-dynamic-acl-per-collection.php | Dynamic ACL per collection |

## Quick Example: E-Commerce

\`\`\`php
use BangronDB\\Client;

$client = new Client(__DIR__ . '/data');
$client->createDB('shop');
$client->createCollection('shop', 'products');
$client->createCollection('shop', 'orders');

$products = $client->selectCollection('shop', 'products');
$orders = $client->selectCollection('shop', 'orders');

// Add products
$products->insert(['name' => 'Laptop', 'price' => 1500, 'stock' => 10]);
$products->insert(['name' => 'Mouse', 'price' => 50, 'stock' => 100]);
$products->insert(['name' => 'Keyboard', 'price' => 100, 'stock' => 50]);

// Create order
$orderId = $orders->insert([
    'customer'   => 'John Doe',
    'items'      => [
        ['product' => 'Laptop', 'qty' => 1, 'price' => 1500],
        ['product' => 'Mouse', 'qty' => 2, 'price' => 50],
    ],
    'total'      => 1600,
    'status'     => 'pending',
    'created_at' => date('c'),
]);

echo "Order created: $orderId\\n";

// Get order summary
$summary = $orders->aggregate([
    ['\\$match' => ['status' => 'pending']],
    ['\\$group' => [
        '_id' => null,
        'total_orders' => ['\\$sum' => 1],
        'total_revenue' => ['\\$sum' => '\\$total'],
    ]],
]);

echo "\\nPending Orders Summary:\\n";
echo "  Total Orders: " . $summary[0]['total_orders'] . "\\n";
echo "  Total Revenue: $" . $summary[0]['total_revenue'] . "\\n";
\`\`\`

**Output:**

\`\`\`text
Order created: ord-550e8400-e29b-41d4-a716-446655440000

Pending Orders Summary:
  Total Orders: 1
  Total Revenue: $1600
\`\`\`

## Dokumentasi Tambahan

| Dokumen | Deskripsi |
|---------|-----------|
| [Getting Started](#/docs/getting-started) | Panduan cepat |
| [Encryption](#/docs/encryption) | Hooks, soft delete, TTL, dll |
| [Query Operators](#/docs/query-operators) | Operator query lengkap |
| [Schema Validation](#/docs/schema) | Schema guide |
| [Hooks](#/docs/hooks) | 8 pola hook |
| [Examples](#/docs/examples) | Laravel, Slim, dll |
| [Client API](#/docs/api-client) | API lengkap |
| [Security](#/docs/security) | Keamanan |
| [Configuration](#/docs/configuration) | Rencana fitur |
`,
},

};

export default pages;
