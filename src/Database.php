<?php

declare(strict_types=1);

namespace BangronDB;

/**
 * Database object for managing SQLite database connections and operations.
 *
 * @method bool sqliteCreateFunction(string $name, callable $callback, int $numArgs = -1, int $flags = 0)
 */
class Database
{
    // Constants
    public const DSN_PATH_MEMORY = ':memory:';
    private const COLLECTION_NAME_REGEX = '/^[A-Za-z0-9_]+$/';
    private const IDENTIFIER_REGEX = '/^[A-Za-z0-9_]+$/';

    // Instance properties
    public string $path;
    public ?Client $client = null;
    /**
     * @var string|null Encryption key (protected to prevent direct external access)
     */
    protected ?string $encryptionKey = null;
    protected array $options = [];
    protected array $collections = [];

    // Connection and criteria
    /**
     * @var \PDO Database connection
     */
    public \PDO $connection;
    public ?QueryExecutor $queryExecutor = null;
    protected array $document_criterias = [];
    private ?DatabaseMetrics $metrics = null;

    // Static registry for managing database instances
    protected static array $criteria_registry = [];
    protected static array $instances = [];

    /**
     * Maximum criteria registry size before triggering cleanup.
     */
    private const MAX_CRITERIA_REGISTRY_SIZE = 1000;

    /**
     * Last cleanup timestamp.
     */
    private static int $lastCleanupTime = 0;

    /**
     * Cleanup interval in seconds (5 minutes).
     */
    private const CLEANUP_INTERVAL = 300;

    /**
     * Constructor.
     */
    public function __construct(string $path = self::DSN_PATH_MEMORY, array $options = [])
    {
        $this->path = $path;
        $this->options = $options;
        $this->encryptionKey = $options['encryption_key'] ?? null;

        $this->connection = $this->createConnection();
        $this->queryExecutor = new QueryExecutor($this->connection);

        // Configure query executor based on options
        if ($this->options['query_logging'] ?? false) {
            $this->queryExecutor->setLogging(true);
        }
        if ($this->options['performance_monitoring'] ?? false) {
            $this->queryExecutor->setPerformanceMonitoring(true);
        }

        $this->ensureMetadataTable();
        $this->setupDatabaseFunctions();
        $this->configureDatabaseSettings();
        $this->registerInstance();
    }

    /**
     * Create PDO connection.
     */
    private function createConnection(): \PDO
    {
        $dsn = "sqlite:{$this->path}";

        return new \PDO($dsn, null, null, $this->options);
    }

    /**
     * Setup custom SQLite functions.
     */
    private function setupDatabaseFunctions(): void
    {
        $conn = $this->connection;
        // PHP 8.5+ deprecated PDO::sqliteCreateFunction() in favor of Pdo\Sqlite::createFunction()
        // Use @ to suppress deprecation warnings on PHP 8.5+ while maintaining compatibility
        @\call_user_func([$conn, 'sqliteCreateFunction'], 'document_key', [$this, 'createDocumentKeyFunction'], 2);
        @\call_user_func([$conn, 'sqliteCreateFunction'], 'document_criteria', ['\\BangronDB\\Database', 'staticCallCriteria'], 2);
    }

    /**
     * Configure database settings for performance.
     */
    private function configureDatabaseSettings(): void
    {
        // Apply encryption key if set
        if ($this->encryptionKey) {
            $escapedKey = \BangronDB\Security\FieldValidator::escapePragmaKey($this->encryptionKey);
            $this->connection->exec("PRAGMA key = '{$escapedKey}'");
        }

        // Apply Config settings with whitelist validation
        $journalMode = Config::get('journal_mode', 'WAL');
        $synchronous = Config::get('synchronous', 'NORMAL');
        $pageSize = Config::get('page_size', 4096);
        $cacheSize = Config::get('cache_size', -1024);
        $autoVacuum = Config::get('auto_vacuum', 'INCREMENTAL');

        // Validate PRAGMA values against whitelists to prevent injection
        $this->execPragma('journal_mode', $journalMode, ['DELETE', 'TRUNCATE', 'PERSIST', 'MEMORY', 'WAL', 'OFF']);
        $this->execPragma('synchronous', $synchronous, ['OFF', 'NORMAL', 'FULL', 'EXTRA']);
        $this->connection->exec('PRAGMA PAGE_SIZE = ' . (int) $pageSize);
        $this->connection->exec('PRAGMA cache_size = ' . (int) $cacheSize);
        $this->execPragma('auto_vacuum', $autoVacuum, ['NONE', 'INCREMENTAL', 'FULL']);
    }

    /**
     * Execute a PRAGMA with whitelist value validation.
     *
     * @param string   $name       PRAGMA name (already safe - hardcoded)
     * @param string   $value      Value to validate against whitelist
     * @param string[] $allowed    Allowed values
     *
     * @throws \InvalidArgumentException If value is not in whitelist
     */
    private function execPragma(string $name, string $value, array $allowed): void
    {
        $upper = strtoupper($value);
        if (!in_array($upper, $allowed, true)) {
            throw new \InvalidArgumentException(
                "Invalid PRAGMA value for {$name}: '{$value}'. Allowed: " . implode(', ', $allowed)
            );
        }
        $this->connection->exec("PRAGMA {$name} = {$upper}");
    }

    /**
     * Ensure the metadata table for change tracking exists.
     */
    private function ensureMetadataTable(): void
    {
        $this->connection->exec('
            CREATE TABLE IF NOT EXISTS _meta (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                document TEXT
            )
        ');

        // Create collections configuration table (document-oriented)
        $this->connection->exec('
            CREATE TABLE IF NOT EXISTS _config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                document TEXT
            )
        ');
    }

    /**
     * Register database instance for cleanup.
     */
    private function registerInstance(): void
    {
        if (class_exists('WeakReference')) {
            self::$instances[] = \WeakReference::create($this);
        } else {
            self::$instances[] = $this;
        }
    }

    /**
     * Document key function for SQLite.
     */
    public function createDocumentKeyFunction(string $key, $document): string
    {
        if ($document === null) {
            return '';
        }

        $document = json_decode($document, true);
        if ($document === null || !is_array($document)) {
            return '';
        }

        $value = UtilArrayQuery::get($document, $key, '');

        return is_array($value) || is_object($value) ? json_encode($value) : (string) $value;
    }

    /**
     * Get the database-level encryption key (for internal library use only).
     * Used by EncryptionTrait in Collection to access the database-level key.
     *
     * @internal This method is for internal library use only. Do not expose in public API.
     */
    public function getEncryptionKey(): ?string
    {
        return $this->encryptionKey;
    }

    /**
     * Set the database-level encryption key.
     *
     * @param string|null $key Encryption key (minimum 32 characters)
     */
    public function setEncryptionKey(?string $key): self
    {
        $this->encryptionKey = $key;

        // Clear derived key cache since the key changed
        EncryptionTrait::clearDerivedKeyCache();

        return $this;
    }

    /**
     * Check if database-level encryption is enabled.
     */
    public function isEncryptionEnabled(): bool
    {
        return $this->encryptionKey !== null;
    }

    /**
     * Get the database-level encryption key status (does not expose the key itself).
     */
    public function getEncryptionKeyStatus(): array
    {
        return [
            'enabled' => $this->encryptionKey !== null,
            'key_length' => $this->encryptionKey !== null ? strlen($this->encryptionKey) : 0,
        ];
    }

    /**
     * Close all known Database instances (best-effort).
     */
    public static function closeAll(): void
    {
        foreach (self::$instances as $key => $ref) {
            self::closeInstance($ref, $key);
        }
        self::$instances = [];
    }

    /**
     * Close a single database instance.
     */
    private static function closeInstance($ref, int $key): void
    {
        if (is_object($ref) && $ref instanceof \WeakReference) {
            $db = $ref->get();
            if ($db) {
                $db->close();
            }
        } elseif (is_object($ref)) {
            $ref->close();
        }

        unset(self::$instances[$key]);
    }

    /**
     * Close the database connection.
     */
    public function close(): void
    {
        $this->cleanupCriteriaRegistry();
        $this->document_criterias = [];
        unset($this->connection);
        $this->metrics = null;
        if (isset($this->queryExecutor)) {
            $this->queryExecutor = null;
        }
    }

    /**
     * Clean up criteria registry.
     */
    private function cleanupCriteriaRegistry(): void
    {
        foreach (array_keys($this->document_criterias) as $id) {
            if (isset(self::$criteria_registry[$id])) {
                unset(self::$criteria_registry[$id]);
            }
        }
    }

    /**
     * Destructor to ensure connection is closed.
     */
    public function __destruct()
    {
        $this->close();
        // Trigger cleanup on destruction
        $this->cleanupStaleCriteriaReferences();
    }

    /**
     * Register Criteria function.
     * Uses random_bytes for unpredictable IDs instead of uniqid.
     */
    public function registerCriteriaFunction($criteria): ?string
    {
        $id = 'criteria_' . bin2hex(random_bytes(8));

        if (is_callable($criteria)) {
            return $this->registerCallableCriteria($id, $criteria);
        }

        if (is_array($criteria)) {
            return $this->registerArrayCriteria($id, $criteria);
        }

        return null;
    }

    /**
     * Register callable criteria function.
     */
    private function registerCallableCriteria(string $id, callable $criteria): string
    {
        $this->document_criterias[$id] = $criteria;
        $this->registerWeakReference($id);

        return $id;
    }

    /**
     * Register array-based criteria function.
     */
    private function registerArrayCriteria(string $id, array $criteria): string
    {
        $fn = function ($document) use ($criteria) {
            if (!is_array($document)) {
                return false;
            }

            return UtilArrayQuery::match($criteria, $document);
        };

        $this->document_criterias[$id] = $fn;
        $this->registerWeakReference($id);

        return $id;
    }

    /**
     * Register weak reference for criteria.
     */
    private function registerWeakReference(string $id): void
    {
        if (class_exists('WeakReference')) {
            self::$criteria_registry[$id] = \WeakReference::create($this);
        } else {
            self::$criteria_registry[$id] = $this;
        }

        // Trigger periodic cleanup if registry is large
        $this->maybeCleanupCriteriaRegistry();
    }

    /**
     * Perform periodic cleanup of stale weak references.
     *
     * Removes entries where the weakly-referenced object has been garbage collected.
     */
    private function maybeCleanupCriteriaRegistry(): void
    {
        $registrySize = count(self::$criteria_registry);
        $currentTime = time();

        // Trigger cleanup if:
        // 1. Registry exceeds max size, OR
        // 2. Cleanup interval has elapsed
        $shouldCleanup = $registrySize > self::MAX_CRITERIA_REGISTRY_SIZE
            || ($currentTime - self::$lastCleanupTime) > self::CLEANUP_INTERVAL;

        if ($shouldCleanup) {
            $this->cleanupStaleCriteriaReferences();
            self::$lastCleanupTime = $currentTime;
        }
    }

    /**
     * Clean up stale weak references from criteria registry.
     *
     * Removes dead weak references where the Database instance
     * has been garbage collected.
     */
    private function cleanupStaleCriteriaReferences(): void
    {
        foreach (self::$criteria_registry as $id => $ref) {
            if ($this->isStaleReference($ref)) {
                unset(self::$criteria_registry[$id]);
            }
        }
    }

    /**
     * Check if a reference is stale (dead weak reference).
     *
     * @param mixed $ref The reference to check
     *
     * @return bool True if reference is stale
     */
    private function isStaleReference($ref): bool
    {
        if ($ref instanceof \WeakReference) {
            return $ref->get() === null;
        }

        return false;
    }

    /**
     * Execute registered criteria function.
     */
    public function callCriteriaFunction(string $id, $document): bool
    {
        return isset($this->document_criterias[$id])
            ? $this->document_criterias[$id]($document)
            : false;
    }

    /**
     * Static entrypoint called by SQLite extension.
     */
    public static function staticCallCriteria(string $id, $document): bool
    {
        if (!isset(self::$criteria_registry[$id])) {
            return false;
        }

        $db = self::resolveDatabaseReference(self::$criteria_registry[$id]);
        if ($db === null) {
            unset(self::$criteria_registry[$id]);

            return false;
        }

        if ($document === null) {
            return false;
        }

        $document = json_decode($document, true);

        return $db->callCriteriaFunction($id, $document);
    }

    /**
     * Resolve database reference from registry.
     */
    private static function resolveDatabaseReference($ref): ?Database
    {
        if (is_object($ref) && $ref instanceof \WeakReference) {
            return $ref->get();
        }

        return $ref;
    }

    /**
     * Vacuum database to reclaim space.
     */
    public function vacuum(): void
    {
        $this->connection->query('VACUUM');
    }

    /**
     * Drop database file (for non-memory databases).
     */
    public function drop(): void
    {
        if ($this->path !== static::DSN_PATH_MEMORY) {
            $this->close();
            unlink($this->path);
        }
    }

    /**
     * Create a collection.
     */
    public function createCollection(string $name): void
    {
        $this->validateCollectionName($name);
        $this->executeCreateCollection($name);
    }

    /**
     * Validate collection name.
     */
    private function validateCollectionName(string $name): void
    {
        if (!preg_match(self::COLLECTION_NAME_REGEX, $name)) {
            throw new \InvalidArgumentException('Invalid collection name: ' . $name);
        }
    }

    /**
     * Execute collection creation.
     */
    private function executeCreateCollection(string $name): void
    {
        $quoted = $this->quoteIdentifier($name);
        $sql = "CREATE TABLE IF NOT EXISTS {$quoted} ( id INTEGER PRIMARY KEY AUTOINCREMENT, document TEXT )";
        $this->connection->exec($sql);
    }

    /**
     * Drop a collection.
     */
    public function dropCollection(string $name): void
    {
        $this->validateCollectionName($name);
        $this->executeDropCollection($name);
        $this->removeCollectionFromCache($name);
    }

    /**
     * Execute collection drop.
     */
    private function executeDropCollection(string $name): void
    {
        $quoted = $this->quoteIdentifier($name);
        $sql = "DROP TABLE IF EXISTS {$quoted}";
        $this->connection->exec($sql);
    }

    /**
     * Remove collection from cache.
     */
    private function removeCollectionFromCache(string $name): void
    {
        unset($this->collections[$name]);
    }

    /**
     * Get all collection names in the database.
     */
    public function getCollectionNames(): array
    {
        $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT IN ('sqlite_sequence', '_meta', '_config')";
        $stmt = $this->connection->query($sql);
        $tables = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        return array_column($tables, 'name');
    }

    /**
     * Get all collections in the database.
     */
    public function listCollections(): array
    {
        foreach ($this->getCollectionNames() as $name) {
            $this->ensureCollectionLoaded($name);
        }

        return $this->collections;
    }

    /**
     * Ensure collection is loaded into cache.
     */
    private function ensureCollectionLoaded(string $name): void
    {
        if (!isset($this->collections[$name])) {
            if (!in_array($name, $this->getCollectionNames())) {
                $this->createCollection($name);
            }
            $this->collections[$name] = new Collection($name, $this);
        }
    }

    /**
     * Select collection.
     */
    public function selectCollection(string $name): Collection
    {
        $this->ensureCollectionLoaded($name);

        return $this->collections[$name];
    }

    /**
     * Magic getter for collection access.
     */
    public function __get(string $collection): Collection
    {
        return $this->selectCollection($collection);
    }

    /**
     * Create an index for a JSON field using json_extract(document, '$.field').
     *
     * Security: Collection name and field path are validated and properly quoted.
     */
    public function createJsonIndex(string $collection, string $field, ?string $indexName = null): void
    {
        $this->validateCollectionName($collection);

        // Validate field name to prevent injection
        \BangronDB\Security\FieldValidator::validateFieldName($field);

        $indexName = $indexName ?? $this->generateIndexName($collection, $field);

        // Validate index name
        if (!preg_match(self::IDENTIFIER_REGEX, $indexName)) {
            throw new \InvalidArgumentException('Invalid index name: ' . $indexName);
        }

        $quotedCollection = $this->quoteIdentifier($collection);
        $path = '$.' . str_replace("'", "\\'", $field);
        $sql = 'CREATE INDEX IF NOT EXISTS `' . str_replace('`', '``', $indexName) . '` ON ' . $quotedCollection .
            " (json_extract(document, '" . $path . "'))";

        $this->connection->exec($sql);
    }

    /**
     * Generate index name.
     */
    private function generateIndexName(string $collection, string $field): string
    {
        $sanitizedField = preg_replace('/[^a-zA-Z0-9_]/', '_', $field);

        return sprintf('idx_%s_%s', $collection, $sanitizedField);
    }

    /**
     * Quote an identifier (table/index/column name) in a safe manner.
     */
    public function quoteIdentifier(string $name): string
    {
        if (!preg_match(self::IDENTIFIER_REGEX, $name)) {
            throw new \InvalidArgumentException('Invalid identifier: ' . $name);
        }

        return '`' . str_replace('`', '``', $name) . '`';
    }

    /**
     * Drop an index by name.
     */
    public function dropIndex(string $indexName): void
    {
        // Validate index name to prevent SQL injection
        if (!preg_match(self::IDENTIFIER_REGEX, $indexName)) {
            throw new \InvalidArgumentException('Invalid index name: ' . $indexName);
        }
        $sql = 'DROP INDEX IF EXISTS `' . str_replace('`', '``', $indexName) . '`';
        $this->connection->exec($sql);
    }

    /**
     * Get or create cached DatabaseMetrics instance.
     */
    private function getMetrics(): DatabaseMetrics
    {
        if ($this->metrics === null) {
            $this->metrics = new DatabaseMetrics($this);
        }
        return $this->metrics;
    }

    /**
     * Get database health and metrics information.
     */
    public function getHealthMetrics(): array
    {
        return $this->getMetrics()->getHealthMetrics();
    }

    /**
     * Check database integrity using SQLite's PRAGMA integrity_check.
     */
    public function checkIntegrity(): array
    {
        return $this->getMetrics()->checkIntegrity();
    }

    /**
     * Get comprehensive data metrics for the database.
     */
    public function getDataMetrics(): array
    {
        return $this->getMetrics()->getDataMetrics();
    }

    /**
     * Get performance metrics for the database.
     */
    public function getPerformanceMetrics(): array
    {
        return $this->getMetrics()->getPerformanceMetrics();
    }

    /**
     * Get index metrics for the database.
     */
    public function getIndexMetrics(): array
    {
        return $this->getMetrics()->getIndexMetrics();
    }

    /**
     * Check if a table has a specific column.
     */
    public function tableHasColumn(string $tableName, string $columnName): bool
    {
        try {
            $quotedTable = $this->quoteIdentifier($tableName);
            $stmt = $this->connection->query("PRAGMA table_info({$quotedTable})");
            $columns = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            foreach ($columns as $column) {
                if ($column['name'] === $columnName) {
                    return true;
                }
            }
        } catch (\Exception $e) {
        }

        return false;
    }

    /**
     * Get detailed metrics for each collection.
     */
    public function getCollectionMetrics(): array
    {
        return $this->getMetrics()->getCollectionMetrics();
    }

    /**
     * Save collection configuration to database.
     */
    public function saveCollectionConfig(string $collectionName, array $config): void
    {
        $this->validateCollectionName($collectionName);

        $document = [
            '_id' => $collectionName,
            'id_mode' => $config['id_mode'] ?? 'auto',
            'encryption_enabled' => $config['encryption_enabled'] ?? false,
            'searchable_fields' => $config['searchable_fields'] ?? [],
            'schema' => $config['schema'] ?? [],
            'soft_deletes_enabled' => $config['soft_deletes_enabled'] ?? false,
            'deleted_at_field' => $config['deleted_at_field'] ?? '_deleted_at',
            'custom_config' => $config['custom_config'] ?? [],
            'created_at' => $config['created_at'] ?? time(),
            'updated_at' => time(),
        ];

        $encoded = json_encode($document);
        if ($encoded === false) {
            throw new \RuntimeException('Failed to encode collection config as JSON');
        }

        // Check if config already exists
        try {
            $stmt = $this->queryExecutor->executeQuery("SELECT id FROM _config WHERE json_extract(document, '$._id') = ?", [$collectionName]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (QueryExecutionException $e) {
            $existing = null;
        }

        if ($existing) {
            // Update existing
            $this->queryExecutor->executeUpdate('UPDATE _config SET document = ? WHERE id = ?', [$encoded, $existing['id']]);
        } else {
            // Insert new
            $this->queryExecutor->executeUpdate('INSERT INTO _config (document) VALUES (?)', [$encoded]);
        }
    }

    /**
     * Load collection configuration from database.
     */
    public function loadCollectionConfig(string $collectionName): array
    {
        $this->validateCollectionName($collectionName);

        try {
            $stmt = $this->queryExecutor->executeQuery("SELECT document FROM _config WHERE json_extract(document, '$._id') = ?", [$collectionName]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (QueryExecutionException $e) {
            $row = null;
        }

        if (!$row) {
            return []; // Return empty config if not found
        }

        $document = json_decode($row['document'], true);
        if ($document === null) {
            return []; // Invalid JSON
        }

        // Remove _id from returned config as it's internal
        unset($document['_id']);

        return $document;
    }

    /**
     * Get all collection configurations.
     */
    public function getAllCollectionConfigs(): array
    {
        try {
            $stmt = $this->queryExecutor->executeQuery('SELECT document FROM _config ORDER BY json_extract(document, "$._id")');
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (QueryExecutionException $e) {
            $rows = [];
        }

        $configs = [];
        foreach ($rows as $row) {
            $document = json_decode($row['document'], true);
            if ($document !== null && isset($document['_id'])) {
                $collectionName = $document['_id'];
                unset($document['_id']);
                $configs[$collectionName] = $document;
            }
        }

        return $configs;
    }

    /**
     * Delete collection configuration.
     */
    public function deleteCollectionConfig(string $collectionName): void
    {
        $this->validateCollectionName($collectionName);

        $this->queryExecutor->executeUpdate("DELETE FROM _config WHERE json_extract(document, '$._id') = ?", [$collectionName]);
    }

    /**
     * Generate a health report summary.
     *
     * @return array Health report
     */
    public function getHealthReport(): array
    {
        $metrics = $this->getHealthMetrics();

        $report = [
            'status' => 'healthy',
            'issues' => [],
            'warnings' => [],
            'recommendations' => [],
            'timestamp' => time(),
        ];

        // Check integrity
        if ($metrics['integrity']['status'] !== 'healthy') {
            $report['status'] = 'critical';
            $report['issues'][] = 'Database integrity check failed';
        }

        // Check fragmentation
        if (($metrics['performance']['fragmentation_ratio'] ?? 0) > 0.1) {
            $report['warnings'][] = 'High database fragmentation detected';
            $report['recommendations'][] = 'Consider running VACUUM to optimize database';
        }

        // Check large collections
        foreach ($metrics['collections'] as $name => $collection) {
            if ($collection['documents'] > 10000) {
                $report['warnings'][] = "Collection '{$name}' has many documents ({$collection['documents']})";
                $report['recommendations'][] = "Consider indexing frequently queried fields in '{$name}'";
            }
        }

        // Check for unencrypted sensitive data
        if (!$metrics['database']['encryption_enabled']) {
            $report['warnings'][] = 'Database encryption is not enabled';
            $report['recommendations'][] = 'Consider enabling encryption for sensitive data';
        }

        // Overall status determination
        if (!empty($report['issues'])) {
            $report['status'] = 'critical';
        } elseif (!empty($report['warnings'])) {
            $report['status'] = 'warning';
        }

        return $report;
    }
}
