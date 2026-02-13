# Panduan Lanjutan BangronDB

Panduan untuk fitur-fitur advanced dan optimisasi performa di BangronDB.

## Arsitektur Database

### Struktur Internal

BangronDB menggunakan SQLite sebagai penyimpan (dengan ekstensi `.bangron`) dengan struktur sebagai berikut:

```
database.bangron
├── sqlite_master (system table)
├── users (collection table)
│   ├── id (INTEGER PRIMARY KEY)
│   ├── document (TEXT) - JSON data
│   └── si_email (TEXT) - searchable field
├── posts (collection table)
│   ├── id (INTEGER PRIMARY KEY)
│   └── document (TEXT)
└── indexes
    ├── idx_users_email
    └── idx_posts_created_at
```

### Optimisasi SQLite

BangronDB mengaktifkan beberapa optimisasi SQLite secara default:

- **WAL Mode**: Write-Ahead Logging untuk concurrency yang lebih baik
- **Normal Synchronous**: Balance antara kecepatan dan keamanan
- **4KB Page Size**: Optimal untuk kebanyakan use cases

## Query Optimization

### Indexing Strategy

#### JSON Field Indexing

```php
// Index untuk field yang sering di-query
$users->createIndex('email');           // Index untuk lookup
$users->createIndex('created_at');      // Index untuk sorting temporal
$orders->createIndex('user_id');        // Index untuk foreign key
$products->createIndex('category');     // Index untuk filtering
```

#### Composite Indexing

Untuk query kompleks, pertimbangkan index tambahan:

```php
// Jika sering query: WHERE status='active' AND created_at > '2024-01-01'
$users->createIndex('status');
$users->createIndex('created_at');
```

### Searchable Fields Optimization

```php
// Untuk data terenkripsi yang perlu dicari
$users->setSearchableFields(['email', 'phone'], true); // Hash untuk privasi

// Struktur tabel akan menambah kolom:
// - si_email TEXT NULL
// - si_phone TEXT NULL

// Query akan menggunakan kolom fisik ini, bukan json_extract
$user = $users->findOne(['email' => 'john@example.com']);
```

### Advanced Searchable Fields Configuration

#### Selective Hashing

```php
// Hash hanya field sensitif
$users->setSearchableFields([
    'email' => ['hash' => true],      // Di-hash untuk privasi
    'username' => ['hash' => false],  // Tidak di-hash (public)
    'phone' => ['hash' => true]       // Di-hash
]);

// Query dengan hashing otomatis
$user = $users->findOne(['email' => 'john@example.com']);
// Email di-hash sebelum query: hash('sha256', 'john@example.com')

$user2 = $users->findOne(['username' => 'john_doe']);
// Username tidak di-hash
```

#### Case-Insensitive Search

```php
// Untuk search case-insensitive
$users->setSearchableFields(['name'], false);

// Data disimpan dalam lowercase
$user = $users->insert(['name' => 'John Doe']);
// Kolom si_name akan berisi: 'john doe'

// Query case-insensitive
$results = $users->find(['name' => 'JOHN DOE']); // Akan menemukan
```

#### Multiple Field Search

```php
$products->setSearchableFields(['name', 'category', 'tags'], false);

// Index untuk search kombinasi
$product = $products->insert([
    'name' => 'Laptop Gaming',
    'category' => 'electronics',
    'tags' => ['gaming', 'high-performance', 'portable']
]);

// Query pada multiple searchable fields
$results = $products->find([
    '$or' => [
        ['name' => 'gaming'],
        ['category' => 'electronics'],
        ['tags' => 'portable']
    ]
]);
```

#### Search dengan Operators

```php
$users->setSearchableFields(['age', 'score'], false);

// Range queries pada searchable fields
$adults = $users->find(['age' => ['$gte' => 18]]);
$highScores = $users->find(['score' => ['$gt' => 85, '$lte' => 100]]);
```

#### Migration ke Searchable Fields

```php
function migrateToSearchableFields($collection, $fields) {
    // Tambahkan searchable fields
    $collection->setSearchableFields($fields, false);

    // Populate existing data
    $documents = $collection->find()->toArray();

    foreach ($documents as $doc) {
        // Re-save untuk populate searchable columns
        $collection->update(['_id' => $doc['_id']], $doc);
    }

    // Buat index untuk performa
    foreach ($fields as $field) {
        $collection->createIndex('si_' . $field);
    }
}

// Usage
migrateToSearchableFields($db->users, ['email', 'username']);
```

#### Performance Comparison

```php
// Tanpa searchable fields (lambat)
$start = microtime(true);
$user = $collection->findOne(['email' => 'john@example.com']);
$time1 = microtime(true) - $start;

// Dengan searchable fields (cepat)
$collection->setSearchableFields(['email']);
$collection->createIndex('si_email');

$start = microtime(true);
$user = $collection->findOne(['email' => 'john@example.com']);
$time2 = microtime(true) - $start;

echo "Without searchable: {$time1}s\n";
echo "With searchable: {$time2}s\n";
echo "Improvement: " . round(($time1 - $time2) / $time1 * 100) . "%\n";
```

### Query Patterns

#### Efficient Queries

```php
// ✅ Good: Menggunakan index
$users = $collection->find(['status' => 'active'])
    ->sort(['created_at' => -1])
    ->limit(50);

// ✅ Good: Equality matching
$user = $collection->findOne(['email' => 'john@example.com']);

// ✅ Good: Range queries pada indexed fields
$recent = $collection->find([
    'created_at' => ['$gte' => '2024-01-01']
])->toArray();
```

#### Inefficient Queries

```php
// ❌ Bad: Regex tanpa index
$users = $collection->find([
    'bio' => ['$regex' => 'developer']
])->toArray();

// ❌ Bad: Query pada field tanpa index dengan sorting
$posts = $collection->find(['status' => 'draft'])
    ->sort(['title' => 1])  // Title tidak di-index
    ->toArray();

// ✅ Better: Gunakan searchable fields
$posts->setSearchableFields(['title']);
$posts->createIndex('si_title'); // Index pada searchable field
```

## Schema Validation

BangronDB menyediakan validasi schema ringan untuk memastikan integritas data sebelum disimpan.

### Mendefinisikan Schema

```php
$users->setSchema([
    'username' => [
        'type' => 'string',
        'required' => true,
        'min' => 4,
        'max' => 20,
        'regex' => '/^[a-zA-Z0-9_]+$/'
    ],
    'age' => [
        'type' => 'int',
        'min' => 13
    ],
    'role' => [
        'type' => 'string',
        'enum' => ['admin', 'editor', 'user'],
        'required' => true
    ],
    'tags' => [
        'type' => 'array',
        'max' => 5
    ]
]);
```

### Mekanisme Validasi

Validasi dilakukan otomatis pada saat:

1. `insert()`: Seluruh dokumen divalidasi.
2. `update(..., $merge = false)`: Dokumen pengganti divalidasi.
3. `save()`: Dokumen divalidasi jika operasi menyebabkan insert atau full replace.

> [!NOTE]
> Untuk performa, update partial menggunakan `$set` tidak divalidasi secara default karena memerlukan pembacaan dokumen lama (fetch) untuk validasi struktur utuh.

### Penanganan Error

Validasi yang gagal akan melempar `\Exception` dengan pesan deskriptif:

```php
try {
    $db->users->insert(['username' => 'abc']); // Terlalu pendek
} catch (\Exception $e) {
    echo $e->getMessage(); // "Field 'username' length must be at least 4."
}
```

### Validasi Schema Lanjutan

#### Validasi Bersarang (Nested Objects)

```php
$orders->setSchema([
    'customer' => [
        'type' => 'object',
        'required' => true,
        'properties' => [
            'name' => ['type' => 'string', 'required' => true, 'min' => 2],
            'email' => ['type' => 'string', 'required' => true, 'regex' => '/^[^@]+@[^@]+\.[^@]+$/'],
            'age' => ['type' => 'int', 'min' => 18, 'max' => 120]
        ]
    ],
    'items' => [
        'type' => 'array',
        'required' => true,
        'min' => 1,
        'max' => 100,
        'items' => [
            'type' => 'object',
            'properties' => [
                'product_id' => ['type' => 'string', 'required' => true],
                'quantity' => ['type' => 'int', 'required' => true, 'min' => 1],
                'price' => ['type' => 'float', 'required' => true, 'min' => 0]
            ]
        ]
    ],
    'total' => ['type' => 'float', 'required' => true, 'min' => 0]
]);

// Valid
$orders->insert([
    'customer' => [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30
    ],
    'items' => [
        ['product_id' => 'prod-001', 'quantity' => 2, 'price' => 29.99]
    ],
    'total' => 59.98
]);
```

#### Custom Validation Functions

```php
$users->setSchema([
    'username' => [
        'type' => 'string',
        'required' => true,
        'custom' => function($value, $field, $doc) {
            // Username unik (simulasi)
            static $existing = ['admin', 'root', 'system'];
            if (in_array(strtolower($value), $existing)) {
                return "Username '$value' is reserved";
            }

            // Cek apakah username sudah ada di database
            $existing = $doc['collection']->findOne(['username' => $value]);
            if ($existing && $existing['_id'] !== $doc['_id']) {
                return "Username '$value' already exists";
            }

            return true; // Valid
        }
    ],
    'password' => [
        'type' => 'string',
        'required' => true,
        'custom' => function($value) {
            if (strlen($value) < 8) {
                return 'Password must be at least 8 characters';
            }

            if (!preg_match('/[A-Z]/', $value)) {
                return 'Password must contain at least one uppercase letter';
            }

            if (!preg_match('/[a-z]/', $value)) {
                return 'Password must contain at least one lowercase letter';
            }

            if (!preg_match('/[0-9]/', $value)) {
                return 'Password must contain at least one number';
            }

            return true;
        }
    ],
    'birth_date' => [
        'type' => 'string',
        'custom' => function($value) {
            $date = DateTime::createFromFormat('Y-m-d', $value);
            if (!$date) {
                return 'Invalid date format (use YYYY-MM-DD)';
            }

            $now = new DateTime();
            $age = $now->diff($date)->y;

            if ($age < 13) {
                return 'Must be at least 13 years old';
            }

            if ($age > 150) {
                return 'Invalid birth date';
            }

            return true;
        }
    ]
]);
```

#### Conditional Validation

```php
$users->setSchema([
    'account_type' => [
        'type' => 'string',
        'enum' => ['personal', 'business'],
        'required' => true
    ],
    'company_name' => [
        'type' => 'string',
        'when' => function($doc) {
            return $doc['account_type'] === 'business';
        },
        'required' => true,
        'min' => 2
    ],
    'tax_id' => [
        'type' => 'string',
        'when' => function($doc) {
            return $doc['account_type'] === 'business';
        },
        'regex' => '/^[0-9]{10,15}$/'
    ],
    'parental_consent' => [
        'type' => 'bool',
        'when' => function($doc) {
            return ($doc['age'] ?? 0) < 18;
        },
        'required' => true
    ]
]);
```

#### Schema Versioning

```php
class SchemaManager {
    private $schemas = [];

    public function __construct() {
        $this->schemas = [
            'users_v1' => [
                'username' => ['type' => 'string', 'required' => true],
                'email' => ['type' => 'string', 'required' => true]
            ],
            'users_v2' => [
                'username' => ['type' => 'string', 'required' => true],
                'email' => ['type' => 'string', 'required' => true],
                'phone' => ['type' => 'string'],
                'profile' => [
                    'type' => 'object',
                    'properties' => [
                        'first_name' => ['type' => 'string'],
                        'last_name' => ['type' => 'string']
                    ]
                ]
            ]
        ];
    }

    public function applySchema($collection, $version) {
        if (isset($this->schemas[$version])) {
            $collection->setSchema($this->schemas[$version]);
            $collection->update(['schema_version' => null], [
                '$set' => ['schema_version' => $version]
            ], false); // Update semua dokumen
        }
    }
}

// Usage
$schemaMgr = new SchemaManager();
$schemaMgr->applySchema($db->users, 'users_v2');
```

````

## Soft Deletes

Fitur Soft Deletes memungkinkan Anda "menghapus" dokumen tanpa benar-benar menghapusnya dari database. Dokumen hanya ditandai dengan timestamp penghapusan.

### Aktivasi

```php
$users->useSoftDeletes(true);
````

Secara default, BangronDB akan menambahkan field `_deleted_at` (dalam format ISO 8601) saat dokumen dihapus.

### Operasi Query

Saat Soft Deletes aktif:

- `find()` dan `findOne()` secara otomatis **mengabaikan** dokumen yang terhapus.
- `count()` hanya menghitung dokumen yang aktif.

#### Mengambil Data Terhapus

```php
// Termasuk yang sudah dihapus
$allDocs = $users->find()->withTrashed()->toArray();

// Hanya yang sudah dihapus
$trashed = $users->find()->onlyTrashed()->toArray();
```

### Pemulihan dan Penghapusan Permanen

```php
// Memulihkan dokumen (menghapus field _deleted_at)
$users->restore(['_id' => $id]);

// Menghapus permanen (fisik)
$users->forceDelete(['_id' => $id]);
```

### Advanced Soft Delete Patterns

#### Cascading Soft Deletes

```php
// Hook untuk cascade soft delete
$users->on('afterRemove', function($user) use ($db) {
    // Soft delete posts dari user ini
    $db->posts->remove(['author_id' => $user['_id']]);

    // Soft delete comments dari user ini
    $db->comments->remove(['user_id' => $user['_id']]);

    // Log activity
    $db->activities->insert([
        'type' => 'user_deleted',
        'user_id' => $user['_id'],
        'timestamp' => date('c'),
        'data' => $user
    ]);
});

// Hook untuk prevent delete jika ada dependencies
$users->on('beforeRemove', function($user) use ($db) {
    $activePosts = $db->posts->find([
        'author_id' => $user['_id'],
        'status' => 'published'
    ])->count();

    if ($activePosts > 0) {
        throw new Exception("Cannot delete user with {$activePosts} active posts");
    }

    return [$user['_id']];
});
```

#### Soft Delete dengan Archiving

```php
// Hook untuk archive sebelum soft delete
$users->on('beforeRemove', function($user) use ($db) {
    // Archive ke collection terpisah
    $db->user_archive->insert(array_merge($user, [
        'archived_at' => date('c'),
        'archive_reason' => 'user_deletion'
    ]));

    return [$user['_id']];
});
```

#### Temporary Deactivation (Alternative to Soft Delete)

```php
// Alih-alih soft delete, gunakan status deactivation
$users->on('beforeRemove', function($user) {
    // Update status instead of delete
    $user['collection']->update(
        ['_id' => $user['_id']],
        ['$set' => [
            'status' => 'deactivated',
            'deactivated_at' => date('c'),
            'deactivated_by' => 'system'
        ]]
    );

    // Prevent actual deletion
    return false; // Hook returns false to prevent deletion
});

// Override query methods untuk exclude deactivated
class DeactivatableCollection extends Collection {
    public function find($criteria = [], $options = []) {
        $criteria = array_merge($criteria, [
            'status' => ['$ne' => 'deactivated']
        ]);
        return parent::find($criteria, $options);
    }

    public function count($criteria = []) {
        $criteria = array_merge($criteria, [
            'status' => ['$ne' => 'deactivated']
        ]);
        return parent::count($criteria);
    }
}
```

#### Bulk Soft Delete Operations

```php
function bulkSoftDelete($collection, $criteria) {
    $documents = $collection->find($criteria)->toArray();

    $deletedIds = [];
    foreach ($documents as $doc) {
        try {
            $result = $collection->remove(['_id' => $doc['_id']]);
            if ($result) {
                $deletedIds[] = $doc['_id'];
            }
        } catch (Exception $e) {
            // Log error tapi lanjutkan
            error_log("Failed to soft delete {$doc['_id']}: " . $e->getMessage());
        }
    }

    return $deletedIds;
}

// Usage
$deletedUsers = bulkSoftDelete($db->users, ['last_login' => ['$lt' => '2023-01-01']]);
echo "Soft deleted " . count($deletedUsers) . " inactive users\n";
```

#### Soft Delete dengan Time-based Recovery

```php
// Automatic permanent delete setelah periode tertentu
function cleanupExpiredSoftDeletes($collection, $days = 30) {
    $cutoff = date('c', strtotime("-{$days} days"));

    $expired = $collection->find()->onlyTrashed()->toArray();
    $permanentlyDeleted = 0;

    foreach ($expired as $doc) {
        if (($doc['_deleted_at'] ?? '') < $cutoff) {
            $collection->forceDelete(['_id' => $doc['_id']]);
            $permanentlyDeleted++;
        }
    }

    return $permanentlyDeleted;
}

// Scheduled cleanup (jalankan weekly/monthly)
$deleted = cleanupExpiredSoftDeletes($db->users, 90); // 90 hari
echo "Permanently deleted {$deleted} expired soft-deleted users\n";
```

### Customization

Anda dapat menyesuaikan nama field yang digunakan untuk timestamp penghapusan (segera hadir di konfigurasi tingkat lanjut).

## ID Generation Strategies

BangronDB menyediakan fleksibilitas dalam generasi ID dokumen dengan berbagai strategi untuk memenuhi kebutuhan aplikasi yang berbeda.

### Mode ID yang Tersedia

#### Auto Mode (Default)

Generate UUID v4 secara otomatis untuk setiap dokumen baru:

```php
$users = $db->users;
// Mode auto aktif secara default

$user = $users->insert(['name' => 'John', 'email' => 'john@example.com']);
echo $user['_id']; // Output: '550e8400-e29b-41d4-a716-446655440000'
```

#### Manual Mode

Gunakan ID kustom yang disediakan pengguna:

```php
$users = $db->users;
$users->setIdModeManual();

$user = $users->insert([
    '_id' => 'custom-user-123',
    'name' => 'John',
    'email' => 'john@example.com'
]);
echo $user['_id']; // Output: 'custom-user-123'
```

#### Prefix Mode

Generate ID dengan prefix dan counter yang bertambah otomatis:

```php
$users = $db->users;
$users->setIdModePrefix('USR');

// Insert pertama
$user1 = $users->insert(['name' => 'John']);
echo $user1['_id']; // Output: 'USR-000001'

// Insert kedua
$user2 = $users->insert(['name' => 'Jane']);
echo $user2['_id']; // Output: 'USR-000002'
```

### Advanced ID Configuration

#### Menggunakan Prefix dan Suffix Global

```php
$users = $db->users;
$users->setIdModeAuto();
$users->setPrefix('app-v1-');
$users->setSuffix('-user');

$user = $users->insert(['name' => 'John']);
echo $user['_id']; // Output: 'app-v1-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx-user'
```

#### Counter Management

Untuk mode prefix, counter diinisialisasi dari ID tertinggi yang ada:

```php
// Jika database sudah memiliki USR-000005
$users->setIdModePrefix('USR');
// Counter otomatis di-set ke 5

$user = $users->insert(['name' => 'New User']);
echo $user['_id']; // Output: 'USR-000006'
```

### Migrasi dan Kompatibilitas

Mode ID dapat diubah kapan saja, namun pertimbangkan dampaknya:

```php
// Migrasi dari manual ke auto
$users->setIdModeAuto();
// Insert baru akan dapat UUID, existing documents tetap

// Migrasi dari auto ke manual
$users->setIdModeManual();
// Insert tanpa _id akan gagal

// Migrasi dari prefix ke auto
$users->setIdModeAuto();
// Counter di-reset, ID baru jadi UUID
```

### Tips Penggunaan

- **Auto mode**: Ideal untuk aplikasi baru tanpa persyaratan ID khusus
- **Manual mode**: Cocok untuk import data atau integrasi dengan sistem existing
- **Prefix mode**: Untuk ID yang readable dan sequential per jenis data
- **Prefix/Suffix global**: Untuk namespace di aplikasi multi-tenant

---

## Advanced Encryption

### Per-Collection Encryption

```php
// Collection dengan enkripsi berbeda
$sensitive = $db->sensitive_data;
$sensitive->setEncryptionKey('collection-specific-key');

// Override database-level encryption
$public = $db->public_data;
$public->setEncryptionKey(null); // Non-encrypted
```

### Encrypted Document Structure

Dokumen terenkripsi disimpan sebagai:

```json
{
  "_id": "507f1f77bcf86cd799439011",
  "encrypted_data": "base64-encoded-ciphertext",
  "iv": "base64-encoded-initialization-vector"
}
```

### Key Management

```php
// Rotasi key dengan migrasi data
function rotateEncryptionKey($collection, $oldKey, $newKey) {
    $documents = $collection->find()->toArray();

    // Temporarily disable encryption to read old data
    $collection->setEncryptionKey(null);

    foreach ($documents as $doc) {
        // Re-encrypt with new key
        $collection->setEncryptionKey($newKey);
        $collection->update(['_id' => $doc['_id']], $doc);
    }

    $collection->setEncryptionKey($newKey);
}
```

### Advanced Encryption Scenarios

#### Multiple Encryption Keys

```php
class MultiKeyEncryptionManager {
    private $keys = [];
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->keys = [
            'user_data' => 'user-specific-key-123',
            'financial' => 'financial-records-key-456',
            'medical' => 'hipaa-compliant-key-789',
            'logs' => null // Non-encrypted
        ];
    }

    public function setupCollections() {
        // User data - encrypted
        $this->db->users->setEncryptionKey($this->keys['user_data']);

        // Financial records - encrypted
        $this->db->transactions->setEncryptionKey($this->keys['financial']);
        $this->db->invoices->setEncryptionKey($this->keys['financial']);

        // Medical records - encrypted
        $this->db->patient_records->setEncryptionKey($this->keys['medical']);
        $this->db->prescriptions->setEncryptionKey($this->keys['medical']);

        // Audit logs - non-encrypted
        $this->db->audit_logs->setEncryptionKey($this->keys['logs']);
    }

    public function rotateKey($collection, $oldKey, $newKey) {
        // Temporarily disable encryption to read old data
        $collection->setEncryptionKey(null);
        $documents = $collection->find()->toArray();

        // Re-encrypt with new key
        $collection->setEncryptionKey($newKey);

        foreach ($documents as $doc) {
            $collection->update(['_id' => $doc['_id']], $doc);
        }

        $collection->setEncryptionKey($newKey);
    }
}

// Usage
$encryptionMgr = new MultiKeyEncryptionManager($db);
$encryptionMgr->setupCollections();
```

#### Environment-Based Encryption

```php
class EnvironmentEncryption {
    public static function setupEncryption($db, $environment) {
        $keys = [
            'development' => 'dev-key-insecure-123',
            'staging' => 'staging-key-medium-456',
            'production' => 'prod-key-high-security-789'
        ];

        $key = $keys[$environment] ?? $keys['development'];

        // Apply to sensitive collections
        $db->users->setEncryptionKey($key);
        $db->financial->setEncryptionKey($key);
        $db->medical->setEncryptionKey($key);

        // Log encryption setup (but not the key!)
        error_log("Encryption initialized for environment: {$environment}");
    }
}

// Usage
EnvironmentEncryption::setupEncryption($db, getenv('APP_ENV') ?: 'development');
```

#### Encryption dengan Data Partitioning

```php
// Separate databases untuk different sensitivity levels
class DataPartitionManager {
    private $client;

    public function __construct($client) {
        $this->client = $client;
    }

    public function getPublicDB() {
        return $this->client->selectDB('public_data');
        // Non-encrypted by default
    }

    public function getPrivateDB() {
        $db = $this->client->selectDB('private_data');
        $db->setEncryptionKey('private-data-key');
        return $db;
    }

    public function getSensitiveDB() {
        $db = $this->client->selectDB('sensitive_data');
        $db->setEncryptionKey('sensitive-data-key');
        return $db;
    }

    public function migrateDataBetweenPartitions($fromCollection, $toCollection, $criteria = []) {
        $documents = $fromCollection->find($criteria)->toArray();

        foreach ($documents as $doc) {
            // Remove internal fields
            unset($doc['_id']);

            try {
                $toCollection->insert($doc);
            } catch (Exception $e) {
                error_log("Failed to migrate document: " . $e->getMessage());
            }
        }
    }
}

// Usage
$partitionMgr = new DataPartitionManager($client);

$publicDB = $partitionMgr->getPublicDB();
$privateDB = $partitionMgr->getPrivateDB();

// Move public profile data to unencrypted partition
$partitionMgr->migrateDataBetweenPartitions(
    $privateDB->user_profiles,
    $publicDB->user_profiles,
    ['visibility' => 'public']
);
```

#### Encryption Key Backup dan Recovery

```php
class EncryptionKeyManager {
    private $keys = [];
    private $backupPath;

    public function __construct($backupPath = '/secure/key-backups') {
        $this->backupPath = $backupPath;
        $this->loadKeys();
    }

    public function backupKeys() {
        $backupFile = $this->backupPath . '/keys_' . date('Y-m-d_H-i-s') . '.enc';

        // Encrypt the keys themselves
        $keyData = json_encode($this->keys);
        $encrypted = $this->encryptWithMasterKey($keyData);

        file_put_contents($backupFile, $encrypted);
        chmod($backupFile, 0600); // Secure permissions

        return $backupFile;
    }

    public function restoreKeys($backupFile) {
        if (!file_exists($backupFile)) {
            throw new Exception('Backup file not found');
        }

        $encrypted = file_get_contents($backupFile);
        $keyData = $this->decryptWithMasterKey($encrypted);

        $this->keys = json_decode($keyData, true);

        return count($this->keys);
    }

    public function rotateAllKeys($db) {
        $newKeys = [];

        foreach ($this->keys as $purpose => $oldKey) {
            if ($oldKey) {
                $newKey = $this->generateKey();
                $newKeys[$purpose] = $newKey;

                // Rotate collection keys
                $collections = $this->getCollectionsForKey($purpose);
                foreach ($collections as $collection) {
                    $this->rotateCollectionKey($db->$collection, $oldKey, $newKey);
                }
            }
        }

        $this->keys = $newKeys;
        $this->backupKeys();

        return count($newKeys);
    }

    private function generateKey() {
        return bin2hex(random_bytes(32));
    }

    private function encryptWithMasterKey($data) {
        // Use a master key for encrypting the key backups
        $masterKey = getenv('MASTER_ENCRYPTION_KEY');
        // Implementation would use proper encryption
        return $data; // Placeholder
    }

    private function decryptWithMasterKey($data) {
        // Implementation would decrypt with master key
        return $data; // Placeholder
    }

    private function rotateCollectionKey($collection, $oldKey, $newKey) {
        $collection->setEncryptionKey(null);
        $documents = $collection->find()->toArray();

        $collection->setEncryptionKey($newKey);

        foreach ($documents as $doc) {
            $collection->update(['_id' => $doc['_id']], $doc);
        }
    }

    private function getCollectionsForKey($purpose) {
        $mapping = [
            'user_data' => ['users', 'profiles'],
            'financial' => ['transactions', 'invoices'],
            'medical' => ['patient_records', 'prescriptions']
        ];

        return $mapping[$purpose] ?? [];
    }
}

// Usage
$keyManager = new EncryptionKeyManager();

// Backup keys
$backupFile = $keyManager->backupKeys();
echo "Keys backed up to: {$backupFile}\n";

// Emergency key rotation
$rotated = $keyManager->rotateAllKeys($db);
echo "Rotated {$rotated} encryption keys\n";
```

#### Encryption Error Handling

```php
$collection->setEncryptionKey('my-encryption-key');

try {
    $user = $collection->insert(['name' => 'John', 'email' => 'john@example.com']);
    echo "User inserted successfully\n";

} catch (Exception $e) {
    if (strpos($e->getMessage(), 'encryption') !== false) {
        error_log("Encryption error: " . $e->getMessage());

        // Fallback to non-encrypted storage
        $collection->setEncryptionKey(null);
        $user = $collection->insert(['name' => 'John', 'email' => 'john@example.com']);
        echo "User inserted without encryption (fallback)\n";

    } else {
        throw $e; // Re-throw non-encryption errors
    }
}
```

#### Encryption Performance Considerations

```php
// Test encryption performance
function benchmarkEncryption($collection, $testData, $iterations = 1000) {
    $times = ['encrypted' => [], 'unencrypted' => []];

    // Test unencrypted
    $collection->setEncryptionKey(null);
    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);
        $collection->insert($testData);
        $times['unencrypted'][] = microtime(true) - $start;
    }

    // Test encrypted
    $collection->setEncryptionKey('test-encryption-key');
    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);
        $collection->insert($testData);
        $times['encrypted'][] = microtime(true) - $start;
    }

    $avgUnencrypted = array_sum($times['unencrypted']) / count($times['unencrypted']);
    $avgEncrypted = array_sum($times['encrypted']) / count($times['encrypted']);

    echo "Unencrypted: " . round($avgUnencrypted * 1000, 2) . "ms per insert\n";
    echo "Encrypted: " . round($avgEncrypted * 1000, 2) . "ms per insert\n";
    echo "Overhead: " . round((($avgEncrypted - $avgUnencrypted) / $avgUnencrypted) * 100, 1) . "%\n";
}

// Usage
$testData = ['name' => 'Test User', 'email' => 'test@example.com', 'data' => str_repeat('x', 1000)];
benchmarkEncryption($db->test_collection, $testData, 100);
```

## Advanced Hooks

### Conditional Hooks

```php
// Hook dengan kondisi
$collection->on('beforeUpdate', function($criteria, $data) {
    // Hanya log jika mengubah sensitive fields
    if (isset($data['$set']['password']) || isset($data['$set']['email'])) {
        auditLog('sensitive_update', $criteria);
    }

    return [$criteria, $data];
});
```

### Async Hooks (Pseudo-async dengan logging)

```php
// Hook untuk operasi async
$collection->on('afterInsert', function($doc, $id) {
    // Log untuk processing nanti
    $logEntry = [
        'type' => 'user_registered',
        'user_id' => $id,
        'timestamp' => date('c'),
        'data' => $doc
    ];

    file_put_contents('/tmp/user_registration_queue.log',
        json_encode($logEntry) . "\n", FILE_APPEND);

    // Background worker akan memproses file ini
});
```

### Cascade Operations

```php
// Hook untuk cascade delete
$users->on('afterRemove', function($user) {
    global $db;

    // Hapus related data
    $db->posts->remove(['author_id' => $user['_id']]);
    $db->comments->remove(['user_id' => $user['_id']]);
    $db->sessions->remove(['user_id' => $user['_id']]);
});
```

### Advanced Hook Patterns

#### Audit Trail dengan Hooks

```php
class AuditTrail {
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->setupAuditHooks();
    }

    private function setupAuditHooks() {
        $collections = ['users', 'posts', 'comments'];

        foreach ($collections as $collectionName) {
            $collection = $this->db->$collectionName;

            $collection->on('beforeInsert', function($doc) use ($collectionName) {
                $this->log('insert', $collectionName, null, $doc);
                return [$doc];
            });

            $collection->on('beforeUpdate', function($criteria, $update) use ($collectionName) {
                $oldDoc = $collectionName === 'collection' ? null : $this->db->$collectionName->findOne($criteria);
                $this->log('update', $collectionName, $oldDoc, $update, $criteria);
                return [$criteria, $update];
            });

            $collection->on('afterRemove', function($doc) use ($collectionName) {
                $this->log('delete', $collectionName, $doc, null);
            });
        }
    }

    private function log($action, $collection, $oldDoc = null, $newDoc = null, $criteria = null) {
        $this->db->audit_log->insert([
            'action' => $action,
            'collection' => $collection,
            'old_data' => $oldDoc,
            'new_data' => $newDoc,
            'criteria' => $criteria,
            'user_id' => $_SESSION['user_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'timestamp' => date('c')
        ]);
    }
}

// Usage
$audit = new AuditTrail($db);
```

#### Data Transformation Hooks

```php
// Hook untuk transformasi data otomatis
$users->on('beforeInsert', function($user) {
    // Normalize email
    $user['email'] = strtolower(trim($user['email']));

    // Generate display name
    if (empty($user['display_name'])) {
        $user['display_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
    }

    // Hash password
    if (isset($user['password'])) {
        $user['password_hash'] = password_hash($user['password'], PASSWORD_DEFAULT);
        unset($user['password']); // Remove plain password
    }

    // Set timestamps
    $user['created_at'] = date('c');
    $user['updated_at'] = date('c');

    return [$user];
});

$users->on('beforeUpdate', function($criteria, $update) {
    // Always update timestamp
    if (!isset($update['$set'])) {
        $update['$set'] = [];
    }
    $update['$set']['updated_at'] = date('c');

    // Log sensitive field changes
    if (isset($update['$set']['email']) || isset($update['$set']['password'])) {
        error_log("Sensitive field update for user: " . json_encode($criteria));
    }

    return [$criteria, $update];
});
```

#### Conditional Hooks dengan Context

```php
// Hook yang hanya aktif dalam kondisi tertentu
class ConditionalHookManager {
    private $hooks = [];

    public function addConditionalHook($collection, $event, $condition, $callback) {
        $this->hooks[] = [
            'collection' => $collection,
            'event' => $event,
            'condition' => $condition,
            'callback' => $callback
        ];
    }

    public function applyHooks() {
        foreach ($this->hooks as $hook) {
            $collection = $hook['collection'];

            $collection->on($hook['event'], function(...$args) use ($hook) {
                // Check condition
                if (call_user_func($hook['condition'], ...$args)) {
                    return call_user_func($hook['callback'], ...$args);
                }

                // Return original args if condition not met
                return $args;
            });
        }
    }
}

// Usage
$hookManager = new ConditionalHookManager();

// Hook hanya untuk admin users
$hookManager->addConditionalHook(
    $db->users,
    'beforeUpdate',
    function($criteria, $update) {
        $user = $this->findOne($criteria);
        return ($user['role'] ?? '') === 'admin';
    },
    function($criteria, $update) {
        // Admin-only validation
        if (isset($update['$set']['role']) && $update['$set']['role'] === 'super_admin') {
            throw new Exception('Cannot promote to super admin');
        }
        return [$criteria, $update];
    }
);

$hookManager->applyHooks();
```

#### Error Handling dalam Hooks

```php
$users->on('beforeInsert', function($user) {
    try {
        // Validate email format
        if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Check uniqueness
        $existing = $this->findOne(['email' => $user['email']]);
        if ($existing) {
            throw new Exception('Email already exists');
        }

        return [$user];

    } catch (Exception $e) {
        // Log error
        error_log("User insert validation failed: " . $e->getMessage());

        // Re-throw to prevent insert
        throw $e;
    }
});

$users->on('afterInsert', function($user, $id) {
    try {
        // Send welcome email (pseudo-async)
        $this->queueEmail('welcome', $user['email'], [
            'name' => $user['name'],
            'user_id' => $id
        ]);

        // Create user profile
        $this->database->user_profiles->insert([
            'user_id' => $id,
            'created_at' => date('c')
        ]);

    } catch (Exception $e) {
        // Log error but don't fail the insert
        error_log("Post-insert processing failed for user {$id}: " . $e->getMessage());
    }
});
```

#### Hook Chaining dan Prioritization

```php
class HookManager {
    private $hooks = [];

    public function addHook($collection, $event, $priority, $callback) {
        $this->hooks[$collection->getName()][$event][] = [
            'priority' => $priority,
            'callback' => $callback
        ];
    }

    public function applyHooks() {
        foreach ($this->hooks as $collectionName => $events) {
            foreach ($events as $event => $hookList) {
                // Sort by priority (higher number = higher priority)
                usort($hookList, function($a, $b) {
                    return $b['priority'] <=> $a['priority'];
                });

                // Apply hooks in priority order
                foreach ($hookList as $hook) {
                    $this->db->$collectionName->on($event, $hook['callback']);
                }
            }
        }
    }
}

// Usage
$hookManager = new HookManager();

// High priority validation (runs first)
$hookManager->addHook($db->users, 'beforeInsert', 100, function($user) {
    if (empty($user['email'])) {
        throw new Exception('Email is required');
    }
    return [$user];
});

// Medium priority transformation
$hookManager->addHook($db->users, 'beforeInsert', 50, function($user) {
    $user['email'] = strtolower($user['email']);
    return [$user];
});

// Low priority logging (runs last)
$hookManager->addHook($db->users, 'beforeInsert', 10, function($user) {
    error_log("Inserting user: " . $user['email']);
    return [$user];
});

$hookManager->applyHooks();
```

## Complex Queries

### Advanced UtilArrayQuery

#### Custom Function Queries

```php
// Query dengan logic kompleks
$complexResults = $collection->find([
    '$where' => function($doc) {
        // Custom business logic
        $score = calculateUserScore($doc);
        return $score > 80 && $doc['status'] === 'active';
    }
])->toArray();
```

#### Fuzzy Search

```php
// Advanced fuzzy search
$results = $collection->find([
    'description' => ['$text' => [
        'search' => 'artificial intelligence',
        'minScore' => 0.7,    // Minimum similarity 70%
        'distance' => 3       // Max edit distance
    ]]
])->toArray();
```

#### Array Queries

```php
// Query pada nested arrays
$posts = $collection->find([
    'comments' => [
        '$func' => function($comments) {
            // Posts dengan komentar dari admin
            foreach ($comments as $comment) {
                if (($comment['user']['role'] ?? '') === 'admin') {
                    return true;
                }
            }
            return false;
        }
    ]
])->toArray();

// Query dengan array size
$largeGroups = $collection->find([
    'members' => ['$size' => ['$gte' => 10]]
])->toArray();
```

### Aggregation-like Operations

```php
// Pseudo-aggregation dengan PHP
function aggregateUsers($collection) {
    $users = $collection->find()->toArray();

    $stats = [
        'total' => count($users),
        'active' => 0,
        'by_role' => [],
        'avg_age' => 0
    ];

    $totalAge = 0;
    foreach ($users as $user) {
        if ($user['status'] === 'active') {
            $stats['active']++;
        }

        $role = $user['role'] ?? 'user';
        $stats['by_role'][$role] = ($stats['by_role'][$role] ?? 0) + 1;

        $totalAge += $user['age'] ?? 0;
    }

    $stats['avg_age'] = $stats['total'] > 0 ? $totalAge / $stats['total'] : 0;

    return $stats;
}
```

## Performance Monitoring

### Health Metrics

```php
$metrics = $db->getHealthMetrics();
print_r($metrics);

/*
Array (
    'database' => Array (
        'path' => '/path/to/db.sqlite',
        'type' => 'file',
        'encryption_enabled' => false
    ),
    'integrity' => Array (
        'status' => 'healthy',
        'checked_at' => '2024-01-01T12:00:00+00:00'
    ),
    'metrics' => Array (
        'total_collections' => 5,
        'total_documents' => 1250,
        'total_size_bytes' => 5242880
    ),
    'performance' => Array (
        'file_size_bytes' => 8388608,
        'fragmentation_ratio' => 0.05
    )
)
*/
```

### Health Report

```php
$report = $db->getHealthReport();
echo "Status: " . $report['status'] . "\n";

if (!empty($report['issues'])) {
    echo "Issues:\n";
    foreach ($report['issues'] as $issue) {
        echo "- $issue\n";
    }
}

if (!empty($report['warnings'])) {
    echo "Warnings:\n";
    foreach ($report['warnings'] as $warning) {
        echo "- $warning\n";
    }
}

if (!empty($report['recommendations'])) {
    echo "Recommendations:\n";
    foreach ($report['recommendations'] as $rec) {
        echo "- $rec\n";
    }
}
```

### Query Performance Profiling

```php
// Measure query performance
function profileQuery($cursor, $label) {
    $start = microtime(true);
    $result = $cursor->toArray();
    $end = microtime(true);

    $duration = round(($end - $start) * 1000, 2); // ms
    $count = count($result);

    echo "$label: {$count} results in {$duration}ms\n";

    return $result;
}

// Usage
$results = profileQuery(
    $collection->find(['status' => 'active'])->limit(100),
    'Active users query'
);
```

## Database Maintenance

### Vacuum Operation

```php
// Reclaim space setelah banyak delete
$db->vacuum();

// Cek fragmentation sebelum/dan sesudah
$before = $db->getPerformanceMetrics()['fragmentation_ratio'];
$db->vacuum();
$after = $db->getPerformanceMetrics()['fragmentation_ratio'];

echo "Fragmentation: {$before} -> {$after}\n";
```

### Backup Strategy

```php
function backupDatabase($db, $backupPath) {
    $sourcePath = $db->path;

    // SQLite backup dengan copy file (WAL mode safe)
    if (copy($sourcePath, $backupPath)) {
        echo "Backup created: $backupPath\n";
        return true;
    }

    return false;
}

// Usage
backupDatabase($db, '/backups/db_' . date('Y-m-d_H-i-s') . '.sqlite');
```

### Integrity Checking

```php
function checkAndRepairDatabase($db) {
    $integrity = $db->checkIntegrity();

    if ($integrity['status'] !== 'healthy') {
        echo "Database corrupted!\n";
        print_r($integrity['details']);

        // Attempt repair (limited options in SQLite)
        $db->vacuum();

        // Re-check
        $recheck = $db->checkIntegrity();
        if ($recheck['status'] === 'healthy') {
            echo "Database repaired successfully\n";
        } else {
            echo "Database repair failed, restore from backup\n";
        }
    } else {
        echo "Database integrity OK\n";
    }
}
```

## Advanced Population

### Complex Population Scenarios

```php
// Multiple level population
$posts = $db->posts->find()
    ->with('author_id', $db->users, ['as' => 'author'])
    ->with('comments.user_id', $db->users, ['as' => 'commenter'])
    ->with('comments.replies.user_id', $db->users, ['as' => 'reply_author'])
    ->toArray();

// Population dengan kondisi
function populateActiveComments($posts, $commentsCollection, $usersCollection) {
    // Populate comments yang hanya active
    $populated = [];
    foreach ($posts as $post) {
        $activeComments = $commentsCollection->find([
            'post_id' => $post['_id'],
            'status' => 'active'
        ])->toArray();

        // Populate user untuk setiap comment
        $activeComments = $commentsCollection->populate(
            $activeComments,
            'user_id',
            $usersCollection,
            '_id',
            'user'
        );

        $post['active_comments'] = $activeComments;
        $populated[] = $post;
    }

    return $populated;
}
```

### Population dengan Custom Logic

```php
// Population dengan transformasi data
$posts->on('afterFind', function($posts) use ($users) {
    // Custom population logic
    $userIds = array_unique(array_column($posts, 'author_id'));

    if (!empty($userIds)) {
        $authors = $users->find(['_id' => ['$in' => $userIds]])->toArray();
        $authorMap = array_column($authors, null, '_id');

        foreach ($posts as &$post) {
            if (isset($authorMap[$post['author_id']])) {
                $author = $authorMap[$post['author_id']];
                // Custom transformation
                $post['author'] = [
                    'id' => $author['_id'],
                    'name' => $author['name'],
                    'avatar' => $author['avatar'] ?? null,
                    'is_verified' => $author['verified'] ?? false
                ];
            }
        }
    }

    return $posts;
});
```

## Connection Pooling dan Management

### Multiple Database Connections

```php
class DatabaseManager {
    private static $connections = [];

    public static function getConnection($name, $path = null) {
        if (!isset(self::$connections[$name])) {
            $client = new Client($path ?? __DIR__ . '/data');
            self::$connections[$name] = $client->selectDB($name);
        }

        return self::$connections[$name];
    }

    public static function closeAll() {
        foreach (self::$connections as $db) {
            if ($db instanceof Database) {
                $db->close();
            }
        }
        self::$connections = [];
    }
}

// Usage
$userDb = DatabaseManager::getConnection('users');
$productDb = DatabaseManager::getConnection('products');
$logDb = DatabaseManager::getConnection('logs', '/var/log/db');
```

### Connection Health Checks

```php
function isDatabaseHealthy($db) {
    try {
        // Simple query to test connection
        $stmt = $db->connection->query('SELECT 1');
        $result = $stmt ? $stmt->fetch() : false;

        return $result !== false;
    } catch (Exception $e) {
        return false;
    }
}

function getConnectionStatus($db) {
    $healthy = isDatabaseHealthy($db);

    return [
        'healthy' => $healthy,
        'path' => $db->path,
        'last_check' => date('c'),
        'metrics' => $healthy ? $db->getHealthMetrics() : null
    ];
}
```

## Migration dan Schema Evolution

### Schema Versioning

```php
class SchemaManager {
    private $db;
    private $migrations = [];

    public function __construct($db) {
        $this->db = $db;
        $this->loadMigrations();
    }

    public function migrate() {
        $currentVersion = $this->getCurrentVersion();

        foreach ($this->migrations as $version => $migration) {
            if ($version > $currentVersion) {
                echo "Running migration {$version}...\n";
                $migration($this->db);
                $this->setCurrentVersion($version);
            }
        }
    }

    private function getCurrentVersion() {
        $meta = $this->db->meta->findOne(['key' => 'schema_version']);
        return $meta['value'] ?? 0;
    }

    private function setCurrentVersion($version) {
        $this->db->meta->update(
            ['key' => 'schema_version'],
            ['key' => 'schema_version', 'value' => $version],
            false // replace
        );
    }

    private function loadMigrations() {
        $this->migrations = [
            1 => function($db) {
                // Create initial collections
                $db->users->createCollection('users');
                $db->posts->createCollection('posts');
                $db->users->createIndex('email');
            },
            2 => function($db) {
                // Add encryption to sensitive collections
                $db->users->setEncryptionKey('user-data-key');
            },
            3 => function($db) {
                // Add searchable fields
                $db->users->setSearchableFields(['name', 'email']);
            }
        ];
    }
}
```

## Error Handling dan Recovery

### Advanced Error Handling

```php
class DatabaseException extends Exception {
    public function __construct($message, $code = 0, $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

function executeWithRetry($operation, $maxRetries = 3) {
    $attempt = 0;
    $lastException = null;

    while ($attempt < $maxRetries) {
        try {
            return $operation();
        } catch (Exception $e) {
            $lastException = $e;
            $attempt++;

            // Exponential backoff
            $delay = pow(2, $attempt) * 100000; // microseconds
            usleep($delay);

            // Log retry attempt
            error_log("Database operation failed (attempt {$attempt}): " . $e->getMessage());
        }
    }

    throw new DatabaseException(
        "Operation failed after {$maxRetries} attempts: " . $lastException->getMessage(),
        0,
        $lastException
    );
}

// Usage
$result = executeWithRetry(function() use ($collection, $data) {
    return $collection->insert($data);
});
```

Dengan mengikuti panduan ini, Anda dapat memanfaatkan fitur-fitur advanced BangronDB untuk membangun aplikasi yang robust, scalable, dan maintainable.
