# Performance Tuning & Security Guidelines

Panduan komprehensif untuk mengoptimalkan performa dan mengamankan aplikasi BangronDB di production.

## Performance Optimization

### SQLite Configuration Tuning

#### Optimal PRAGMA Settings

```php
<?php
// config/database.php - Production settings

function configureDatabase(Database $db): void {
    $pdo = $db->connection;

    // Performance optimizations
    $pdo->exec('PRAGMA journal_mode = WAL');        // Write-Ahead Logging
    $pdo->exec('PRAGMA synchronous = NORMAL');      // Balanced safety/performance
    $pdo->exec('PRAGMA cache_size = -2000');        // 2MB cache (negative = KB)
    $pdo->exec('PRAGMA temp_store = MEMORY');       // Temp tables in memory
    $pdo->exec('PRAGMA mmap_size = 268435456');     // 256MB memory map
    $pdo->exec('PRAGMA page_size = 4096');          // 4KB pages
    $pdo->exec('PRAGMA wal_autocheckpoint = 1000'); // Auto-checkpoint WAL

    // Query optimizations
    $pdo->exec('PRAGMA optimize');                   // Run optimization
    $pdo->exec('PRAGMA analysis_limit = 1000');     // Limit analysis time
}

// Auto-apply on database creation
class OptimizedDatabase extends Database {
    public function __construct(string $path, array $options = []) {
        parent::__construct($path, $options);
        configureDatabase($this);
    }
}
```

#### Connection Pooling

```php
<?php
class DatabasePool {
    private static $pools = [];
    private $available = [];
    private $busy = [];
    private $maxConnections;

    public function __construct(string $path, int $maxConnections = 20) {
        $this->maxConnections = $maxConnections;
        $this->path = $path;
    }

    public static function getInstance(string $path): self {
        if (!isset(self::$pools[$path])) {
            self::$pools[$path] = new self($path);
        }
        return self::$pools[$path];
    }

    public function getConnection(): Database {
        if (!empty($this->available)) {
            $db = array_pop($this->available);
            $this->busy[spl_object_id($db)] = $db;
            return $db;
        }

        if (count($this->busy) >= $this->maxConnections) {
            throw new RuntimeException('Max connections reached');
        }

        $db = new Database($this->path);
        $this->busy[spl_object_id($db)] = $db;
        return $db;
    }

    public function releaseConnection(Database $db): void {
        $id = spl_object_id($db);
        if (isset($this->busy[$id])) {
            unset($this->busy[$id]);
            $this->available[] = $db;
        }
    }

    public function getStats(): array {
        return [
            'available' => count($this->available),
            'busy' => count($this->busy),
            'max' => $this->maxConnections
        ];
    }
}

// Usage
$pool = DatabasePool::getInstance('/var/data/bangrondb');
$db = $pool->getConnection();
try {
    // Use database
    $users = $db->selectCollection('users');
    // ... operations
} finally {
    $pool->releaseConnection($db);
}
```

### Query Optimization

#### Efficient Query Patterns

```php
<?php
class OptimizedQueries {
    private $collection;

    public function __construct(Collection $collection) {
        $this->collection = $collection;
    }

    // ✅ GOOD: Use searchable fields for encrypted data
    public function findUserByEmail(string $email): ?array {
        $this->collection->setSearchableFields(['email'], true);
        $this->collection->createIndex('si_email');
        return $this->collection->findOne(['email' => $email]);
    }

    // ✅ GOOD: Batch operations
    public function bulkInsert(array $documents): int {
        $this->collection->database->connection->beginTransaction();
        try {
            $count = 0;
            foreach (array_chunk($documents, 100) as $chunk) {
                $count += $this->collection->insert($chunk);
            }
            $this->collection->database->connection->commit();
            return $count;
        } catch (Exception $e) {
            $this->collection->database->connection->rollBack();
            throw $e;
        }
    }

    // ✅ GOOD: Pagination with proper indexing
    public function getUsersPaginated(int $page = 1, int $limit = 20): array {
        $skip = ($page - 1) * $limit;

        // Ensure index exists
        $this->collection->createIndex('created_at');

        return $this->collection->find(['status' => 'active'])
            ->sort(['created_at' => -1])
            ->limit($limit)
            ->skip($skip)
            ->toArray();
    }

    // ❌ BAD: Full table scan
    public function inefficientQuery(): array {
        return $this->collection->find([
            '$where' => function($doc) {
                return strlen($doc['description']) > 100; // No index possible
            }
        ])->toArray();
    }
}
```

#### Index Strategy

```php
<?php
class IndexManager {
    private $collection;

    public function __construct(Collection $collection) {
        $this->collection = $collection;
    }

    public function setupIndexes(): void {
        // Primary lookup indexes
        $this->collection->createIndex('email');
        $this->collection->createIndex('_id'); // Usually auto-indexed

        // Composite indexes for complex queries
        $this->collection->createIndex('status');
        $this->collection->createIndex('created_at');

        // Partial indexes for specific cases
        $this->collection->createIndex('priority', 'idx_high_priority');
    }

    public function optimizeForQueries(): void {
        // Analyze query patterns and create appropriate indexes
        $frequentQueries = $this->analyzeQueryPatterns();

        foreach ($frequentQueries as $query) {
            if ($this->shouldIndex($query)) {
                $this->createOptimalIndex($query);
            }
        }
    }

    private function analyzeQueryPatterns(): array {
        // Implementation would analyze query logs
        // Return most frequent query patterns
        return [
            ['email' => ['$exists' => true]],
            ['status' => 'active', 'created_at' => ['$gte' => '2024-01-01']],
            ['category' => ['$in' => ['news', 'tech']]]
        ];
    }
}
```

### Caching Strategies

#### Multi-Level Caching

```php
<?php
class CacheManager {
    private $memoryCache = [];
    private $fileCache;
    private $redis;

    public function __construct(Collection $collection) {
        $this->collection = $collection;
        $this->fileCache = new FileCache('/tmp/bangrondb_cache');
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }

    public function get(string $key, callable $fetcher, int $ttl = 3600): mixed {
        // Level 1: Memory cache
        if (isset($this->memoryCache[$key])) {
            return $this->memoryCache[$key];
        }

        // Level 2: Redis cache
        $data = $this->redis->get($key);
        if ($data !== false) {
            $this->memoryCache[$key] = $data;
            return $data;
        }

        // Level 3: File cache
        $data = $this->fileCache->get($key);
        if ($data !== null) {
            $this->redis->setEx($key, $ttl, $data);
            $this->memoryCache[$key] = $data;
            return $data;
        }

        // Fetch from database
        $data = $fetcher();

        // Store in all cache levels
        $this->memoryCache[$key] = $data;
        $this->redis->setEx($key, $ttl, $data);
        $this->fileCache->set($key, $data, $ttl);

        return $data;
    }

    public function invalidate(string $pattern): void {
        // Clear memory cache
        foreach ($this->memoryCache as $key => $value) {
            if (fnmatch($pattern, $key)) {
                unset($this->memoryCache[$key]);
            }
        }

        // Clear Redis cache
        $keys = $this->redis->keys($pattern);
        if (!empty($keys)) {
            $this->redis->del($keys);
        }

        // Clear file cache
        $this->fileCache->clear($pattern);
    }

    public function invalidateCollection(Collection $collection): void {
        $version = $collection->getLastModified()['version'];
        $pattern = "collection:{$collection->name}:*";

        // Only invalidate if version changed
        if ($this->getCollectionVersion($collection->name) !== $version) {
            $this->invalidate($pattern);
            $this->setCollectionVersion($collection->name, $version);
        }
    }
}
```

#### Query Result Caching

```php
<?php
class CachedCollection {
    private $collection;
    private $cache;

    public function __construct(Collection $collection, CacheManager $cache) {
        $this->collection = $collection;
        $this->cache = $cache;
    }

    public function findOneCached($criteria, int $ttl = 300): ?array {
        $key = 'findone:' . md5(serialize($criteria));

        return $this->cache->get($key, function() use ($criteria) {
            return $this->collection->findOne($criteria);
        }, $ttl);
    }

    public function findCached($criteria = null, $projection = null, int $ttl = 300): array {
        $key = 'find:' . md5(serialize([$criteria, $projection]));

        return $this->cache->get($key, function() use ($criteria, $projection) {
            $this->cache->invalidateCollection($this->collection);
            return $this->collection->find($criteria, $projection)->toArray();
        }, $ttl);
    }
}
```

## Security Guidelines

### Encryption Best Practices

#### Key Management

```php
<?php
class KeyManager {
    private static $keys = [];

    public static function generateKey(): string {
        return bin2hex(random_bytes(32)); // 256-bit key
    }

    public static function rotateKeys(Collection $collection, string $newKey): void {
        $oldKey = $collection->encryptionKey;

        // Re-encrypt all documents with new key
        $documents = $collection->find()->toArray();

        foreach ($documents as $doc) {
            $id = $doc['_id'];
            unset($doc['_id']);

            // Temporarily disable encryption to read old data
            $collection->setEncryptionKey(null);
            // Re-enable with new key
            $collection->setEncryptionKey($newKey);
            // Update will encrypt with new key
            $collection->update(['_id' => $id], $doc);
        }

        // Securely destroy old key
        self::destroyKey($oldKey);
    }

    public static function deriveKey(string $password, string $salt): string {
        return hash_pbkdf2('sha256', $password, $salt, 10000, 32, true);
    }

    private static function destroyKey(string &$key): void {
        // Overwrite in memory
        $key = str_repeat("\0", strlen($key));
        unset($key);
    }
}
```

#### Environment-based Encryption

```php
<?php
class EnvironmentEncryption {
    private static $environments = [
        'development' => 'dev-key-12345678901234567890123456789012',
        'staging' => 'staging-key-123456789012345678901234567890',
        'production' => null // Loaded from secure source
    ];

    public static function getEncryptionKey(string $env = null): string {
        $env = $env ?? getenv('APP_ENV') ?? 'development';

        if ($env === 'production') {
            return self::loadProductionKey();
        }

        return self::$environments[$env] ?? self::generateFallbackKey();
    }

    private static function loadProductionKey(): string {
        // Load from secure key vault, HSM, or encrypted file
        $keyPath = '/etc/bangrondb/keys/master.key.enc';

        if (!file_exists($keyPath)) {
            throw new RuntimeException('Production encryption key not found');
        }

        // Decrypt key file with separate key encryption key (KEK)
        $kek = getenv('BANGRONDB_KEK');
        $encryptedKey = file_get_contents($keyPath);

        return self::decryptKey($encryptedKey, $kek);
    }

    private static function generateFallbackKey(): string {
        // Emergency key generation (log security alert)
        error_log('WARNING: Using fallback encryption key');
        return bin2hex(random_bytes(32));
    }
}
```

### Input Validation & Sanitization

```php
<?php
class SecurityValidator {
    public static function validateDocument(array $document, array $rules): array {
        $errors = [];
        $sanitized = [];

        foreach ($rules as $field => $rule) {
            $value = $document[$field] ?? null;

            // Required check
            if (($rule['required'] ?? false) && $value === null) {
                $errors[] = "Field '{$field}' is required";
                continue;
            }

            if ($value === null) {
                continue;
            }

            // Type validation
            $validated = self::validateType($field, $value, $rule, $errors);
            if ($validated !== null) {
                $sanitized[$field] = $validated;
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Validation failed: ' . implode(', ', $errors));
        }

        return $sanitized;
    }

    private static function validateType(string $field, $value, array $rule, array &$errors) {
        $type = $rule['type'] ?? 'string';

        switch ($type) {
            case 'string':
                if (!is_string($value)) {
                    $errors[] = "Field '{$field}' must be string";
                    return null;
                }

                $value = self::sanitizeString($value);

                // Length checks
                $min = $rule['min'] ?? null;
                $max = $rule['max'] ?? null;

                if ($min !== null && strlen($value) < $min) {
                    $errors[] = "Field '{$field}' too short (min {$min})";
                    return null;
                }

                if ($max !== null && strlen($value) > $max) {
                    $errors[] = "Field '{$field}' too long (max {$max})";
                    return null;
                }

                // Regex validation
                if (isset($rule['regex']) && !preg_match($rule['regex'], $value)) {
                    $errors[] = "Field '{$field}' format invalid";
                    return null;
                }

                // Enum validation
                if (isset($rule['enum']) && !in_array($value, $rule['enum'])) {
                    $errors[] = "Field '{$field}' must be one of: " . implode(', ', $rule['enum']);
                    return null;
                }

                return $value;

            case 'int':
            case 'integer':
                if (!is_numeric($value)) {
                    $errors[] = "Field '{$field}' must be numeric";
                    return null;
                }

                $value = (int) $value;

                $min = $rule['min'] ?? null;
                $max = $rule['max'] ?? null;

                if ($min !== null && $value < $min) {
                    $errors[] = "Field '{$field}' too small (min {$min})";
                    return null;
                }

                if ($max !== null && $value > $max) {
                    $errors[] = "Field '{$field}' too large (max {$max})";
                    return null;
                }

                return $value;

            case 'array':
                if (!is_array($value)) {
                    $errors[] = "Field '{$field}' must be array";
                    return null;
                }

                $max = $rule['max'] ?? null;
                if ($max !== null && count($value) > $max) {
                    $errors[] = "Field '{$field}' too many items (max {$max})";
                    return null;
                }

                return array_map('self::sanitizeValue', $value);

            default:
                return self::sanitizeValue($value);
        }
    }

    private static function sanitizeString(string $value): string {
        // Remove null bytes, strip tags, trim whitespace
        $value = str_replace("\0", '', $value);
        $value = strip_tags($value);
        $value = trim($value);

        // Prevent SQL injection (additional layer)
        $value = str_replace(['"', "'", ';'], '', $value);

        return $value;
    }

    private static function sanitizeValue($value) {
        if (is_string($value)) {
            return self::sanitizeString($value);
        }

        if (is_array($value)) {
            return array_map('self::sanitizeValue', $value);
        }

        return $value;
    }
}
```

### SQL Injection Prevention

```php
<?php
class SecureQueryBuilder {
    private $collection;

    public function __construct(Collection $collection) {
        $this->collection = $collection;
    }

    public function secureFind(array $criteria, array $options = []): array {
        // Validate all criteria values
        $validatedCriteria = $this->validateCriteria($criteria);

        // Use parameterized queries internally
        return $this->collection->find($validatedCriteria, $options)->toArray();
    }

    private function validateCriteria(array $criteria): array {
        $validated = [];

        foreach ($criteria as $key => $value) {
            $validated[$key] = $this->validateCriteriaValue($value);
        }

        return $validated;
    }

    private function validateCriteriaValue($value) {
        if (is_string($value)) {
            // Prevent SQL injection in custom queries
            if (preg_match('/[\'";\\]/', $value)) {
                throw new SecurityException('Potentially dangerous characters in query');
            }
            return $value;
        }

        if (is_array($value)) {
            return array_map([$this, 'validateCriteriaValue'], $value);
        }

        if (is_numeric($value) || is_bool($value) || $value === null) {
            return $value;
        }

        throw new SecurityException('Unsupported criteria value type');
    }
}
```

### Access Control

```php
<?php
class AccessControl {
    private $user;
    private $permissions;

    public function __construct(array $user) {
        $this->user = $user;
        $this->permissions = $user['permissions'] ?? [];
    }

    public function canAccess(Collection $collection, string $operation, array $document = null): bool {
        $collectionName = $collection->name;

        // Check collection-level permissions
        if (!$this->hasPermission("collection.{$collectionName}.{$operation}")) {
            return false;
        }

        // Check document-level permissions if document provided
        if ($document && !$this->canAccessDocument($collectionName, $operation, $document)) {
            return false;
        }

        return true;
    }

    public function filterQuery(Collection $collection, array $criteria): array {
        // Add ownership filter for non-admin users
        if (!$this->isAdmin()) {
            $userId = $this->user['_id'];

            if ($collection->name === 'users') {
                // Users can only see themselves
                $criteria['_id'] = $userId;
            } else {
                // Other collections filter by owner
                $criteria['owner_id'] = $userId;
            }
        }

        return $criteria;
    }

    public function canAccessDocument(string $collection, string $operation, array $document): bool {
        // Document-level access control
        if ($this->isAdmin()) {
            return true;
        }

        $ownerId = $document['owner_id'] ?? $document['user_id'] ?? null;

        if (!$ownerId) {
            return false;
        }

        return $ownerId === $this->user['_id'];
    }

    private function hasPermission(string $permission): bool {
        return in_array($permission, $this->permissions) ||
               in_array('*', $this->permissions) ||
               $this->isAdmin();
    }

    private function isAdmin(): bool {
        return ($this->user['role'] ?? 'user') === 'admin';
    }
}

// Usage in application
class SecureCollection {
    private $collection;
    private $accessControl;

    public function __construct(Collection $collection, AccessControl $accessControl) {
        $this->collection = $collection;
        $this->accessControl = $accessControl;
    }

    public function secureFind(array $criteria = null, array $options = []): array {
        if (!$this->accessControl->canAccess($this->collection, 'read')) {
            throw new AccessDeniedException('Read access denied');
        }

        $filteredCriteria = $this->accessControl->filterQuery($this->collection, $criteria ?? []);

        return $this->collection->find($filteredCriteria, $options)->toArray();
    }

    public function secureInsert(array $document): string {
        if (!$this->accessControl->canAccess($this->collection, 'create')) {
            throw new AccessDeniedException('Create access denied');
        }

        // Add ownership
        $document['owner_id'] = $this->accessControl->getUserId();
        $document['created_at'] = date('c');

        return $this->collection->insert($document);
    }
}
```

### Audit Logging

```php
<?php
class AuditLogger {
    private $auditCollection;

    public function __construct(Collection $auditCollection) {
        $this->auditCollection = $auditCollection;
    }

    public function logOperation(string $operation, array $context): void {
        $logEntry = [
            'timestamp' => date('c'),
            'operation' => $operation,
            'user_id' => $context['user_id'] ?? null,
            'collection' => $context['collection'] ?? null,
            'document_id' => $context['document_id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'data' => $context['data'] ?? null,
            'result' => $context['result'] ?? null,
            'error' => $context['error'] ?? null
        ];

        $this->auditCollection->insert($logEntry);
    }

    public function logQuery(Collection $collection, array $criteria, array $result, string $userId = null): void {
        $this->logOperation('query', [
            'user_id' => $userId,
            'collection' => $collection->name,
            'data' => ['criteria' => $criteria, 'result_count' => count($result)],
            'result' => 'success'
        ]);
    }

    public function logModification(Collection $collection, string $operation, array $document, string $userId = null): void {
        $this->logOperation($operation, [
            'user_id' => $userId,
            'collection' => $collection->name,
            'document_id' => $document['_id'] ?? null,
            'data' => $document,
            'result' => 'success'
        ]);
    }

    public function logError(Collection $collection, string $operation, Exception $e, string $userId = null): void {
        $this->logOperation($operation, [
            'user_id' => $userId,
            'collection' => $collection->name,
            'error' => [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ],
            'result' => 'error'
        ]);
    }
}

// Integration with collection operations
class AuditedCollection {
    private $collection;
    private $auditLogger;
    private $userId;

    public function __construct(Collection $collection, AuditLogger $auditLogger, string $userId = null) {
        $this->collection = $collection;
        $this->auditLogger = $auditLogger;
        $this->userId = $userId;
    }

    public function auditedInsert(array $document): string {
        try {
            $id = $this->collection->insert($document);
            $document['_id'] = $id;
            $this->auditLogger->logModification($this->collection, 'insert', $document, $this->userId);
            return $id;
        } catch (Exception $e) {
            $this->auditLogger->logError($this->collection, 'insert', $e, $this->userId);
            throw $e;
        }
    }

    public function auditedUpdate($criteria, array $data, bool $merge = true): int {
        try {
            $result = $this->collection->update($criteria, $data, $merge);
            $this->auditLogger->logOperation('update', [
                'user_id' => $this->userId,
                'collection' => $this->collection->name,
                'data' => ['criteria' => $criteria, 'update' => $data, 'count' => $result],
                'result' => 'success'
            ]);
            return $result;
        } catch (Exception $e) {
            $this->auditLogger->logError($this->collection, 'update', $e, $this->userId);
            throw $e;
        }
    }
}
```

Dengan mengikuti panduan ini, BangronDB dapat memberikan performa tinggi dan keamanan enterprise-grade untuk aplikasi production. Pastikan untuk selalu melakukan testing menyeluruh dan monitoring kontinyu pada environment production.
