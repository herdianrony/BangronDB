<?php

declare(strict_types=1);

namespace BangronDB;

use BangronDB\Exceptions\CollectionException;
use BangronDB\Security\FieldValidator;

/**
 * Database object for managing SQLite database connections and operations.
 *
 * @method bool sqliteCreateFunction(string $name, callable $callback, int $numArgs = -1, int $flags = 0)
 */
class Database
{
    public const DSN_PATH_MEMORY = ':memory:';
    private const COLLECTION_NAME_REGEX = '/^[A-Za-z0-9_]+$/';
    private const IDENTIFIER_REGEX = '/^[A-Za-z0-9_]+$/';

    public string $path;
    public ?Client $client = null;
    protected ?string $encryptionKey = null;
    protected array $options = [];
    protected array $collections = [];
    private ?string $encryptionSalt = null;
    protected ?string $encryptionKeyVersion = null;

    /** @var \PDO Database connection */
    public \PDO $connection;
    public ?QueryExecutor $queryExecutor = null;
    protected array $document_criterias = [];
    private ?DatabaseMetrics $metrics = null;

    protected static array $criteria_registry = [];
    protected static array $instances = [];

    private const MAX_CRITERIA_REGISTRY_SIZE = 1000;
    private static int $lastCleanupTime = 0;
    private const CLEANUP_INTERVAL = 300;

    public function __construct(string $path = self::DSN_PATH_MEMORY, array $options = [])
    {
        $basePath = isset($options['base_path']) && is_string($options['base_path'])
            ? $options['base_path']
            : null;

        $this->path = $path === self::DSN_PATH_MEMORY
            ? $path
            : FieldValidator::validateDatabasePath($path, $basePath);
        $this->options = $options;
        $this->encryptionKey = $options['encryption_key'] ?? null;
        $version = $options['encryption_key_version'] ?? null;
        $this->encryptionKeyVersion = $version === null ? null : (string) $version;

        $this->connection = $this->createConnection();
        $this->queryExecutor = new QueryExecutor($this->connection);

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

    private function createConnection(): \PDO
    {
        $dsn = "sqlite:{$this->path}";

        return new \PDO($dsn, null, null, $this->options);
    }

    private function setupDatabaseFunctions(): void
    {
        $conn = $this->connection;
        @\call_user_func([$conn, 'sqliteCreateFunction'], 'document_key', [$this, 'createDocumentKeyFunction'], 2);
        @\call_user_func([$conn, 'sqliteCreateFunction'], 'document_criteria', ['\\BangronDB\\Database', 'staticCallCriteria'], 2);
    }

    private function configureDatabaseSettings(): void
    {
        if ($this->encryptionKey) {
            $escapedKey = FieldValidator::escapePragmaKey($this->encryptionKey);
            $this->connection->exec("PRAGMA key = '{$escapedKey}'");
        }

        $this->connection->exec('PRAGMA busy_timeout = 5000');

        $journalMode = Config::get('journal_mode', 'WAL');
        $synchronous = Config::get('synchronous', 'NORMAL');
        $pageSize = Config::get('page_size', 4096);
        $cacheSize = Config::get('cache_size', -1024);
        $autoVacuum = Config::get('auto_vacuum', 'INCREMENTAL');

        $this->execPragma('journal_mode', $journalMode, ['DELETE', 'TRUNCATE', 'PERSIST', 'MEMORY', 'WAL', 'OFF']);
        $this->connection->exec('PRAGMA PAGE_SIZE = ' . (int) $pageSize);
        $this->connection->exec('PRAGMA cache_size = ' . (int) $cacheSize);
        $this->execPragma('auto_vacuum', $autoVacuum, ['NONE', 'INCREMENTAL', 'FULL']);
        $this->execPragma('synchronous', $synchronous, ['OFF', 'NORMAL', 'FULL', 'EXTRA']);

        if (strtoupper($journalMode) === 'WAL') {
            $walAutocheckpoint = Config::get('wal_autocheckpoint', 1000);
            $this->connection->exec('PRAGMA wal_autocheckpoint = ' . (int) $walAutocheckpoint);
        }
    }

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

    private function ensureMetadataTable(): void
    {
        $this->connection->exec('
            CREATE TABLE IF NOT EXISTS _meta (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                document TEXT
            )
        ');

        $this->connection->exec('
            CREATE TABLE IF NOT EXISTS _config (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                document TEXT
            )
        ');

        $this->connection->exec('
            CREATE TABLE IF NOT EXISTS _crypto (
                key TEXT PRIMARY KEY,
                value TEXT
            )
        ');
    }

    private function registerInstance(): void
    {
        if (class_exists('WeakReference')) {
            self::$instances[] = \WeakReference::create($this);
        } else {
            self::$instances[] = $this;
        }
    }

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

    public function getEncryptionKey(): ?string
    {
        return $this->encryptionKey;
    }

    public function __debugInfo(): array
    {
        return [
            'path' => $this->path,
            'encryptionEnabled' => $this->encryptionKey !== null,
            'encryptionKeyLength' => $this->encryptionKey !== null ? strlen($this->encryptionKey) : 0,
            'keyVersion' => $this->encryptionKeyVersion,
            'collections' => array_keys($this->collections),
            'options' => array_diff_key($this->options, ['encryption_key' => '']),
        ];
    }

    public function setEncryptionKey(?string $key, ?string $keyVersion = null): self
    {
        $this->encryptionKey = $key;
        // Consistent string casting – mirrors EncryptionTrait/Collection behaviour
        $this->encryptionKeyVersion = $keyVersion === null ? null : (string) $keyVersion;
        Collection::clearDerivedKeyCache();

        return $this;
    }

    public function getEncryptionKeyVersion(): ?string
    {
        return $this->encryptionKeyVersion;
    }

    public function setEncryptionKeyVersion(?string $version): self
    {
        // Consistent string casting – mirrors EncryptionTrait/Collection behaviour
        $this->encryptionKeyVersion = $version === null ? null : (string) $version;

        return $this;
    }

    public function isEncryptionEnabled(): bool
    {
        return $this->encryptionKey !== null;
    }

    public function getEncryptionKeyStatus(): array
    {
        return [
            'enabled' => $this->encryptionKey !== null,
            'key_length' => $this->encryptionKey !== null ? strlen($this->encryptionKey) : 0,
            'key_version' => $this->encryptionKeyVersion,
        ];
    }

    public function getEncryptionSalt(): string
    {
        if ($this->encryptionSalt !== null) {
            return $this->encryptionSalt;
        }

        if ($this->path === self::DSN_PATH_MEMORY) {
            $this->encryptionSalt = base64_encode(random_bytes(16));

            return $this->encryptionSalt;
        }

        $salt = $this->loadCryptoValue('kdf_salt');
        if ($salt === null) {
            $salt = base64_encode(random_bytes(16));
            $this->saveCryptoValue('kdf_salt', $salt);
        }

        $this->encryptionSalt = $salt;

        return $this->encryptionSalt;
    }

    private function loadCryptoValue(string $key): ?string
    {
        $stmt = $this->connection->prepare('SELECT value FROM _crypto WHERE key = ? LIMIT 1');
        if (!$stmt || !$stmt->execute([$key])) {
            return null;
        }

        $value = $stmt->fetchColumn();

        return is_string($value) ? $value : null;
    }

    private function saveCryptoValue(string $key, string $value): void
    {
        $stmt = $this->connection->prepare('INSERT OR REPLACE INTO _crypto (key, value) VALUES (?, ?)');
        if (!$stmt || !$stmt->execute([$key, $value])) {
            throw new \RuntimeException('Failed to persist encryption salt metadata');
        }
    }

    public static function closeAll(): void
    {
        foreach (self::$instances as $key => $ref) {
            self::closeInstance($ref, $key);
        }
        self::$instances = [];
    }

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

    private function cleanupCriteriaRegistry(): void
    {
        foreach (array_keys($this->document_criterias) as $id) {
            if (isset(self::$criteria_registry[$id])) {
                unset(self::$criteria_registry[$id]);
            }
        }
    }

    public function __destruct()
    {
        $this->close();
        $this->cleanupStaleCriteriaReferences();
    }

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

    private function registerCallableCriteria(string $id, callable $criteria): string
    {
        $this->document_criterias[$id] = $criteria;
        $this->registerWeakReference($id);

        return $id;
    }

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

    private function registerWeakReference(string $id): void
    {
        if (class_exists('WeakReference')) {
            self::$criteria_registry[$id] = \WeakReference::create($this);
        } else {
            self::$criteria_registry[$id] = $this;
        }

        $this->maybeCleanupCriteriaRegistry();
    }

    private function maybeCleanupCriteriaRegistry(): void
    {
        $registrySize = count(self::$criteria_registry);
        $currentTime = time();
        $shouldCleanup = $registrySize > self::MAX_CRITERIA_REGISTRY_SIZE
            || ($currentTime - self::$lastCleanupTime) > self::CLEANUP_INTERVAL;

        if ($shouldCleanup) {
            $this->cleanupStaleCriteriaReferences();
            self::$lastCleanupTime = $currentTime;
        }
    }

    private function cleanupStaleCriteriaReferences(): void
    {
        foreach (self::$criteria_registry as $id => $ref) {
            if ($this->isStaleReference($ref)) {
                unset(self::$criteria_registry[$id]);
            }
        }
    }

    private function isStaleReference($ref): bool
    {
        return $ref instanceof \WeakReference && $ref->get() === null;
    }

    public function callCriteriaFunction(string $id, $document): bool
    {
        return isset($this->document_criterias[$id])
            ? $this->document_criterias[$id]($document)
            : false;
    }

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

    private static function resolveDatabaseReference($ref): ?Database
    {
        if (is_object($ref) && $ref instanceof \WeakReference) {
            return $ref->get();
        }

        return $ref;
    }

    public function vacuum(): void
    {
        $this->connection->query('VACUUM');
    }

    public function drop(): void
    {
        if ($this->path !== static::DSN_PATH_MEMORY) {
            if (!str_ends_with($this->path, '.bangron')) {
                throw new \RuntimeException('Refusing to drop a database file without .bangron extension');
            }

            $this->close();
            unlink($this->path);
        }
    }

    public function ensureCollectionTable(string $name): void
    {
        $this->validateCollectionName($name);
        $this->executeCreateCollection($name);
    }

    public function createCollection(string $name): Collection
    {
        $this->ensureCollectionTable($name);

        if (!isset($this->collections[$name])) {
            $this->collections[$name] = new Collection($name, $this);
        }

        return $this->collections[$name];
    }

    public function collectionExists(string $name): bool
    {
        $this->validateCollectionName($name);

        if (isset($this->collections[$name])) {
            return true;
        }

        return in_array($name, $this->getCollectionNames(), true);
    }

    private function validateCollectionName(string $name): void
    {
        if (!preg_match(self::COLLECTION_NAME_REGEX, $name)) {
            throw new \InvalidArgumentException('Invalid collection name: ' . $name);
        }
    }

    private function executeCreateCollection(string $name): void
    {
        $quoted = $this->quoteIdentifier($name);
        $sql = "CREATE TABLE IF NOT EXISTS {$quoted} ( id INTEGER PRIMARY KEY AUTOINCREMENT, document TEXT )";
        $this->connection->exec($sql);
    }

    public function dropCollection(string $name): void
    {
        $this->validateCollectionName($name);
        $this->executeDropCollection($name);
        $this->removeCollectionFromCache($name);
    }

    private function executeDropCollection(string $name): void
    {
        $quoted = $this->quoteIdentifier($name);
        $sql = "DROP TABLE IF EXISTS {$quoted}";
        $this->connection->exec($sql);
    }

    private function removeCollectionFromCache(string $name): void
    {
        unset($this->collections[$name]);
    }

    public function renameCollectionInCache(Collection $collection, string $oldName, string $newName): void
    {
        unset($this->collections[$oldName]);
        $this->collections[$newName] = $collection;
    }

    public function renameCollectionReferences(string $oldName, string $newName): void
    {
        $this->validateCollectionName($oldName);
        $this->validateCollectionName($newName);

        $this->queryExecutor->executeUpdate(
            "UPDATE _meta SET document = json_set(document, '$._id', ?) WHERE json_extract(document, '$._id') = ?",
            [$newName, $oldName]
        );

        $this->queryExecutor->executeUpdate(
            "UPDATE _config SET document = json_set(document, '$._id', ?) WHERE json_extract(document, '$._id') = ?",
            [$newName, $oldName]
        );
    }

    public function getCollectionNames(): array
    {
        $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name NOT IN ('sqlite_sequence', '_meta', '_config', '_crypto')";
        $stmt = $this->connection->query($sql);
        $tables = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        return array_column($tables, 'name');
    }

    public function listCollections(): array
    {
        foreach ($this->getCollectionNames() as $name) {
            $this->ensureCollectionLoaded($name);
        }

        return $this->collections;
    }

    private function ensureCollectionLoaded(string $name): void
    {
        if (!isset($this->collections[$name])) {
            if (!in_array($name, $this->getCollectionNames(), true)) {
                $databaseName = $this->path === self::DSN_PATH_MEMORY
                    ? self::DSN_PATH_MEMORY
                    : basename($this->path, '.bangron');
                throw CollectionException::notFound($name, $databaseName);
            }
            $this->collections[$name] = new Collection($name, $this);
        }
    }

    public function renameCollection(string $oldName, string $newName): bool
    {
        $this->validateCollectionName($oldName);
        $this->validateCollectionName($newName);

        if (!$this->collectionExists($oldName)) {
            return false;
        }

        if ($oldName === $newName || $this->collectionExists($newName)) {
            return false;
        }

        $collection = $this->collections[$oldName] ?? new Collection($oldName, $this);

        return $collection->renameCollection($newName);
    }

    public function selectCollection(string $name): Collection
    {
        $this->ensureCollectionLoaded($name);

        return $this->collections[$name];
    }

    public function __get(string $collection): Collection
    {
        return $this->selectCollection($collection);
    }

    public function createJsonIndex(string $collection, string $field, ?string $indexName = null): void
    {
        $this->validateCollectionName($collection);
        FieldValidator::validateFieldName($field);

        $indexName = $indexName ?? $this->generateIndexName($collection, $field);

        if (!preg_match(self::IDENTIFIER_REGEX, $indexName)) {
            throw new \InvalidArgumentException('Invalid index name: ' . $indexName);
        }

        $quotedCollection = $this->quoteIdentifier($collection);
        $path = '$.' . str_replace("'", "''", $field);
        $sql = 'CREATE INDEX IF NOT EXISTS `' . str_replace('`', '``', $indexName) . '` ON ' . $quotedCollection .
            " (json_extract(document, '" . $path . "'))";

        $this->connection->exec($sql);
    }

    private function generateIndexName(string $collection, string $field): string
    {
        $sanitizedField = preg_replace('/[^a-zA-Z0-9_]/', '_', $field);

        return sprintf('idx_%s_%s', $collection, $sanitizedField);
    }

    public function quoteIdentifier(string $name): string
    {
        if (!preg_match(self::IDENTIFIER_REGEX, $name)) {
            throw new \InvalidArgumentException('Invalid identifier: ' . $name);
        }

        return '`' . str_replace('`', '``', $name) . '`';
    }

    public function dropIndex(string $indexName): void
    {
        if (!preg_match(self::IDENTIFIER_REGEX, $indexName)) {
            throw new \InvalidArgumentException('Invalid index name: ' . $indexName);
        }
        $sql = 'DROP INDEX IF EXISTS `' . str_replace('`', '``', $indexName) . '`';
        $this->connection->exec($sql);
    }

    private function getMetrics(): DatabaseMetrics
    {
        if ($this->metrics === null) {
            $this->metrics = new DatabaseMetrics($this);
        }
        return $this->metrics;
    }

    public function getHealthMetrics(): array
    {
        return $this->getMetrics()->getHealthMetrics();
    }

    public function checkIntegrity(): array
    {
        return $this->getMetrics()->checkIntegrity();
    }

    public function getDataMetrics(): array
    {
        return $this->getMetrics()->getDataMetrics();
    }

    public function getPerformanceMetrics(): array
    {
        return $this->getMetrics()->getPerformanceMetrics();
    }

    public function getIndexMetrics(): array
    {
        return $this->getMetrics()->getIndexMetrics();
    }

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

    public function getCollectionMetrics(): array
    {
        return $this->getMetrics()->getCollectionMetrics();
    }

    public function touchCollectionMetadata(string $collectionName): array
    {
        $this->validateCollectionName($collectionName);

        $metadata = $this->getCollectionMetadata($collectionName);
        $next = [
            'version' => $metadata['version'] + 1,
            'last_updated' => date('c'),
        ];

        $document = $this->encodeMetadataDocument($collectionName, $next);

        try {
            $stmt = $this->queryExecutor->executeQuery(
                "SELECT id FROM _meta WHERE json_extract(document, '$._id') = ?",
                [$collectionName]
            );
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (QueryExecutionException $e) {
            $existing = null;
        }

        if ($existing) {
            $this->queryExecutor->executeUpdate(
                'UPDATE _meta SET document = ? WHERE id = ?',
                [$document, $existing['id']]
            );
        } else {
            $this->queryExecutor->executeUpdate(
                'INSERT INTO _meta (document) VALUES (?)',
                [$document]
            );
        }

        return $next;
    }

    public function getCollectionMetadata(string $collectionName): array
    {
        $this->validateCollectionName($collectionName);

        try {
            $stmt = $this->queryExecutor->executeQuery(
                "SELECT document FROM _meta WHERE json_extract(document, '$._id') = ?",
                [$collectionName]
            );
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (QueryExecutionException $e) {
            $row = null;
        }

        if (!$row) {
            return $this->getEmptyMetadata();
        }

        return $this->decodeMetadataRow($row['document']);
    }

    public function getAllCollectionMetadata(): array
    {
        try {
            $stmt = $this->queryExecutor->executeQuery('SELECT document FROM _meta');
            $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        } catch (QueryExecutionException $e) {
            return [];
        }

        $metadata = [];
        foreach ($rows as $row) {
            $document = json_decode($row['document'], true);
            if (!is_array($document) || !isset($document['_id'])) {
                continue;
            }

            $metadata[$document['_id']] = $this->decodeMetadataArray($document);
        }

        return $metadata;
    }

    private function encodeMetadataDocument(string $collectionName, array $metadata): string
    {
        $document = json_encode([
            '_id' => $collectionName,
            'version' => (int) ($metadata['version'] ?? 0),
            'last_updated' => $metadata['last_updated'] ?? null,
        ]);

        if ($document === false) {
            throw new \RuntimeException('Failed to encode metadata document as JSON');
        }

        return $document;
    }

    private function decodeMetadataRow(string $document): array
    {
        $decoded = json_decode($document, true);
        if (!is_array($decoded)) {
            return $this->getEmptyMetadata();
        }

        return $this->decodeMetadataArray($decoded);
    }

    private function decodeMetadataArray(array $document): array
    {
        return [
            'version' => (int) ($document['version'] ?? 0),
            'last_updated' => isset($document['last_updated']) && is_string($document['last_updated'])
                ? $document['last_updated']
                : null,
        ];
    }

    private function getEmptyMetadata(): array
    {
        return ['version' => 0, 'last_updated' => null];
    }

    public function saveCollectionConfig(string $collectionName, array $config): void
    {
        $this->validateCollectionName($collectionName);

        $document = [
            '_id' => $collectionName,
            'id_mode' => $config['id_mode'] ?? 'auto',
            'encryption_enabled' => $config['encryption_enabled'] ?? false,
            'encryption_key_version' => $config['encryption_key_version'] ?? null,
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

        try {
            $stmt = $this->queryExecutor->executeQuery("SELECT id FROM _config WHERE json_extract(document, '$._id') = ?", [$collectionName]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (QueryExecutionException $e) {
            $existing = null;
        }

        if ($existing) {
            $this->queryExecutor->executeUpdate('UPDATE _config SET document = ? WHERE id = ?', [$encoded, $existing['id']]);
        } else {
            $this->queryExecutor->executeUpdate('INSERT INTO _config (document) VALUES (?)', [$encoded]);
        }
    }

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
            return [];
        }

        $document = json_decode($row['document'], true);
        if ($document === null) {
            return [];
        }

        unset($document['_id']);

        return $document;
    }

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

    public function deleteCollectionConfig(string $collectionName): void
    {
        $this->validateCollectionName($collectionName);

        $this->queryExecutor->executeUpdate("DELETE FROM _config WHERE json_extract(document, '$._id') = ?", [$collectionName]);
    }

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

        if ($metrics['integrity']['status'] !== 'healthy') {
            $report['status'] = 'critical';
            $report['issues'][] = 'Database integrity check failed';
        }

        if (($metrics['performance']['fragmentation_ratio'] ?? 0) > 0.1) {
            $report['warnings'][] = 'High database fragmentation detected';
            $report['recommendations'][] = 'Consider running VACUUM to optimize database';
        }

        foreach ($metrics['collections'] as $name => $collection) {
            if ($collection['documents'] > 10000) {
                $report['warnings'][] = "Collection '{$name}' has many documents ({$collection['documents']})";
                $report['recommendations'][] = "Consider indexing frequently queried fields in '{$name}'";
            }
        }

        if (!$metrics['database']['encryption_enabled']) {
            $report['warnings'][] = 'Database encryption is not enabled';
            $report['recommendations'][] = 'Consider enabling encryption for sensitive data';
        }

        if (!empty($report['issues'])) {
            $report['status'] = 'critical';
        } elseif (!empty($report['warnings'])) {
            $report['status'] = 'warning';
        }

        return $report;
    }
}
