<?php

namespace BangronDB;

use BangronDB\Traits\EncryptionTrait;
use BangronDB\Traits\HooksTrait;
use BangronDB\Traits\IdGeneratorTrait;
use BangronDB\Traits\QueryBuilderTrait;
use BangronDB\Traits\SchemaValidationTrait;
use BangronDB\Traits\SearchableFieldsTrait;
use BangronDB\Traits\SoftDeleteTrait;

/**
 * Collection object.
 */
class Collection
{
    use EncryptionTrait;
    use HooksTrait;
    use SearchableFieldsTrait;
    use IdGeneratorTrait;
    use QueryBuilderTrait;
    use SchemaValidationTrait;
    use SoftDeleteTrait;

    /**
     * ID Generation Mode Constants.
     */
    public const ID_MODE_AUTO = 'auto';          // Generate UUID v4 automatically
    public const ID_MODE_MANUAL = 'manual';      // Use provided _id only
    public const ID_MODE_PREFIX = 'prefix';      // Generate with prefix

    /**
     * Hook Event Constants.
     */
    public const HOOK_BEFORE_INSERT = 'beforeInsert';
    public const HOOK_AFTER_INSERT = 'afterInsert';
    public const HOOK_BEFORE_UPDATE = 'beforeUpdate';
    public const HOOK_AFTER_UPDATE = 'afterUpdate';
    public const HOOK_BEFORE_REMOVE = 'beforeRemove';
    public const HOOK_AFTER_REMOVE = 'afterRemove';

    public Database $database;

    public string $name;

    /**
     * Custom configuration values.
     */
    protected array $customConfig = [];

    /**
     * Constructor.
     *
     * @param object $database
     */
    public function __construct(string $name, Database $database)
    {
        $this->name = $name;
        $this->database = $database;

        // Auto-load configuration from database
        $this->loadConfiguration();
    }

    /**
     * Drop collection.
     */
    public function drop()
    {
        $this->database->dropCollection($this->name);
    }

    public function forceDelete($criteria): int
    {
        $currentSoftDelete = $this->softDeletesEnabled;
        $this->softDeletesEnabled = false;
        $result = $this->remove($criteria);
        $this->softDeletesEnabled = $currentSoftDelete;

        return $result;
    }

    /**
     * Insert document.
     *
     * @return mixed last_insert_id for single document or
     *               count count of inserted documents for arrays
     */
    public function insert(array $document = [])
    {
        if (isset($document[0])) {
            $this->database->connection->beginTransaction();

            try {
                foreach ($document as $doc) {
                    if (!\is_array($doc)) {
                        continue;
                    }

                    $res = $this->_insert($doc);

                    if (!$res) {
                        // Failure - roll back and return
                        $this->database->connection->rollBack();

                        return $res;
                    }
                }

                $this->database->connection->commit();
                $this->notifyChange();

                return \count($document);
            } catch (\Throwable $e) {
                if ($this->database->connection && $this->database->connection->inTransaction()) {
                    $this->database->connection->rollBack();
                }
                throw $e;
            }
        } else {
            $res = $this->_insert($document);
            if ($res) {
                $this->notifyChange();
            }

            return $res;
        }
    }

    /**
     * Insert document.
     */
    protected function _insert(array $document): mixed
    {
        $this->validate($document);
        $this->database->createCollection($this->name);
        $doc = $this->applyBeforeInsertHooks($document);
        if ($doc === false) {
            return false;
        }

        $doc = $this->ensureDocumentId($doc);
        if ($doc === false) {
            return false;
        }

        $data = $this->prepareDocumentForStorage($doc);
        $insertId = $this->executeInsert($data);

        if ($insertId) {
            $this->applyAfterInsertHooks($doc, $insertId);

            return $insertId;
        }

        return false;
    }

    /**
     * Prepare document data for storage (encoding + searchable fields).
     */
    protected function prepareDocumentForStorage(array $document): array
    {
        $encoded = $this->encodeStored($document);
        $data = ['document' => $encoded];

        // Add searchable index columns when configured
        $indexData = $this->_computeSearchIndexValues($document);
        foreach ($indexData as $col => $val) {
            $data[$col] = $val;
        }

        return $data;
    }

    /**
     * Execute the actual SQL insert statement.
     */
    protected function executeInsert(array $data): mixed
    {
        $table = $this->name;
        $fields = [];
        $values = [];

        foreach ($data as $col => $value) {
            $fields[] = "`{$col}`";
            $values[] = (\is_null($value) ? 'NULL' : $this->database->connection->quote($value));
        }

        $fields = \implode(',', $fields);
        $values = \implode(',', $values);

        $sql = "INSERT INTO `{$table}` ({$fields}) VALUES ({$values})";

        if ($this->database->queryExecutor->executeRawUpdateInternal($sql)) {
            return $data['document'] ? json_decode($data['document'], true)['_id'] : null;
        }

        $this->logSqlError($sql);

        return false;
    }

    /**
     * Log SQL error for debugging.
     */
    protected function logSqlError(string $sql): void
    {
        trigger_error('SQL Error: ' . \implode(', ', $this->database->connection->errorInfo()) . ":\n" . $sql);
    }

    /**
     * Save document.
     */
    public function save(array $document, bool $create = false): mixed
    {
        // Use upsert for existing documents, insert for new ones
        if (isset($document['_id'])) {
            return $this->upsertDocument($document);
        }

        return $this->insert($document);
    }

    /**
     * Perform an upsert operation (update if exists, insert if not).
     */
    protected function upsertDocument(array $document): mixed
    {
        $document = $this->ensureDocumentId($document);
        if ($document === false) {
            return false;
        }

        $this->validate($document);
        $this->database->createCollection($this->name);

        $data = $this->prepareDocumentForStorage($document);
        $idVal = $document['_id'];
        $quotedId = $this->quoteIdValue($idVal);

        // Check if document exists
        try {
            $stmt = $this->database->queryExecutor->executeQuery("SELECT id FROM `{$this->name}` WHERE json_extract(document, '$._id') = ? LIMIT 1", [$idVal]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (QueryExecutionException $e) {
            $existing = null;
        }

        if ($existing) {
            // Update existing
            $setParts = [];
            $params = [];
            foreach ($data as $col => $val) {
                $setParts[] = "`{$col}` = ?";
                $params[] = $val;
            }
            $params[] = $existing['id'];

            $sql = "UPDATE `{$this->name}` SET " . implode(', ', $setParts) . ' WHERE id = ?';
            try {
                $this->database->queryExecutor->executeUpdate($sql, $params);

                return $idVal;
            } catch (QueryExecutionException $e) {
                $this->logSqlError($sql);

                return false;
            }
        } else {
            // Insert new
            $fields = [];
            $placeholders = [];
            $params = [];

            foreach ($data as $col => $val) {
                $fields[] = "`{$col}`";
                $placeholders[] = '?';
                $params[] = $val;
            }

            $sql = "INSERT INTO `{$this->name}` (" . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
            try {
                $this->database->queryExecutor->executeUpdate($sql, $params);

                return $idVal;
            } catch (QueryExecutionException $e) {
                $this->logSqlError($sql);

                return false;
            }
        }

        return false;
    }

    /**
     * Quote ID value appropriately for SQL.
     */
    protected function quoteIdValue($idVal)
    {
        if (is_int($idVal) || is_float($idVal) || (is_string($idVal) && is_numeric($idVal))) {
            return $idVal;
        }

        return $this->database->queryExecutor->quote((string) $idVal);
    }

    /**
     * Update documents.
     */
    public function update($criteria, array $data, bool $merge = true): int
    {
        $this->database->createCollection($this->name);
        // Apply before update hooks to modify criteria/data
        $this->applyUpdateHooks($criteria, $data);

        // Build query to find documents matching criteria
        $documentsToUpdate = $this->findDocumentsToUpdate($criteria);

        $updated = 0;

        foreach ($documentsToUpdate as $doc) {
            $updated += $this->updateDocument($doc, $data, $merge);
        }

        if ($updated > 0) {
            $this->notifyChange();
        }

        return $updated;
    }

    /**
     * Find documents matching criteria for update.
     */
    protected function findDocumentsToUpdate($criteria): array
    {
        if (is_array($criteria) && $this->_canTranslateToJsonWhere($criteria)) {
            $where = $this->_buildJsonWhere($criteria);
            $sql = 'SELECT id, document FROM ' . $this->name . ' WHERE ' . $where;
            $params = [];
        } else {
            $sql = 'SELECT id, document FROM ' . $this->name . ' WHERE document_criteria(?, document)';
            $params = [$this->database->registerCriteriaFunction($criteria)];
        }

        try {
            $stmt = $this->database->queryExecutor->executeQuery($sql, $params);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (QueryExecutionException $e) {
            return [];
        }
    }

    /**
     * Update a single document.
     */
    protected function updateDocument(array $doc, array $data, bool $merge): int
    {
        $_doc = $this->decodeStored($doc['document']);

        // Handle null case for $_doc
        if ($_doc === null) {
            $_doc = [];
        }

        $document = $this->mergeDocumentData($_doc, $data, $merge);

        if ($merge && isset($data['$set'])) {
            // We can't easily validate partially updated document without fetching it first
            // For simplicity, we skip full validation on $set, or we could implement partial validation
        } elseif (!$merge) {
            $this->validate($document);
        }

        $encoded = $this->encodeStored($document);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Skip this document update if encoding fails
            return 0;
        }

        // Execute update with searchable columns
        $this->executeDocumentUpdate($doc['id'], $document, $encoded);

        // Trigger after update hooks
        $this->triggerAfterUpdateHooks($_doc, $document);

        return 1;
    }

    /**
     * Merge document data based on merge flag.
     */
    protected function mergeDocumentData(array $originalDoc, array $newData, bool $merge): array
    {
        if ($merge) {
            $document = $originalDoc;
            if (isset($newData['$set']) || isset($newData['$unset'])) {
                if (isset($newData['$set']) && is_array($newData['$set'])) {
                    foreach ($newData['$set'] as $k => $v) {
                        $document[$k] = $v;
                    }
                }
                if (isset($newData['$unset']) && is_array($newData['$unset'])) {
                    foreach ($newData['$unset'] as $k => $v) {
                        unset($document[$k]);
                    }
                }
            } else {
                $document = \array_merge($originalDoc, $newData);
            }

            return $document;
        } else {
            $document = $newData;
            // Preserve the _id field if it exists in the original document
            if (isset($originalDoc['_id'])) {
                $document['_id'] = $originalDoc['_id'];
            }

            return $document;
        }
    }

    /**
     * Execute the actual document update in database.
     */
    protected function executeDocumentUpdate(int $docId, array $document, string $encoded): void
    {
        // Include searchable columns when present
        $indexData = $this->_computeSearchIndexValues($document);
        $setParts = [];
        $params = [];

        $setParts[] = 'document = ?';
        $params[] = $encoded;

        foreach ($indexData as $col => $val) {
            $setParts[] = "`{$col}` = ?";
            $params[] = $val;
        }

        $params[] = $docId;

        $sql = 'UPDATE ' . $this->name . ' SET ' . implode(',', $setParts) . ' WHERE id = ?';

        try {
            $this->database->queryExecutor->executeUpdate($sql, $params);
        } catch (QueryExecutionException $e) {
            $this->logSqlError($sql);
        }
    }

    /**
     * Remove documents.
     *
     * @return mixed
     */
    public function remove($criteria): int
    {
        $this->database->createCollection($this->name);
        if ($this->softDeletesEnabled) {
            return $this->update($criteria, ['$set' => [$this->getDeletedAtField() => time()]]);
        }

        // Run hook: beforeRemove
        $criteria = $this->applyHooks(self::HOOK_BEFORE_REMOVE, $criteria);
        if ($criteria === false) {
            return 0;
        }

        // Find documents matching removal criteria
        $documentsToRemove = $this->findDocumentsToRemove($criteria);

        $deleted = 0;

        foreach ($documentsToRemove as $row) {
            if ($this->shouldRemoveDocument($row)) {
                $this->removeDocument($row['id'], $row['document']);
                ++$deleted;
            }
        }

        if ($deleted > 0) {
            $this->notifyChange();
        }

        return $deleted;
    }

    /**
     * Find documents matching criteria for removal.
     */
    protected function findDocumentsToRemove($criteria): array
    {
        if (is_array($criteria) && $this->_canTranslateToJsonWhere($criteria)) {
            $where = $this->_buildJsonWhere($criteria);
            $sql = 'SELECT id, document FROM ' . $this->name . ' WHERE ' . $where;
            $params = [];
        } else {
            $sql = 'SELECT id, document FROM ' . $this->name . ' WHERE document_criteria(?, document)';
            $params = [$this->database->registerCriteriaFunction($criteria)];
        }

        try {
            $stmt = $this->database->queryExecutor->executeQuery($sql, $params);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (QueryExecutionException $e) {
            return [];
        }
    }

    /**
     * Remove a single document from the database.
     */
    protected function removeDocument(int $docId, string $document): void
    {
        $doc = $this->decodeStored($document) ?: [];

        // Perform deletion by id
        $delSql = 'DELETE FROM ' . $this->name . ' WHERE id = ?';

        try {
            $this->database->queryExecutor->executeUpdate($delSql, [$docId]);
        } catch (QueryExecutionException $e) {
            $this->logSqlError($delSql);
        }

        // Trigger after remove hooks
        $this->triggerAfterRemoveHooks($doc);
    }

    /**
     * Count documents in collections.
     */
    public function count($criteria = null): int
    {
        return $this->find($criteria)->count();
    }

    /**
     * Find documents.
     *
     * @return object Cursor
     */
    public function find($criteria = null, $projection = null): Cursor
    {
        return new Cursor($this, $criteria, $projection);
    }

    /**
     * Find one document.
     */
    public function findOne($criteria = null, $projection = null): ?array
    {
        $items = $this->find($criteria, $projection)->limit(1)->toArray();

        return isset($items[0]) ? $items[0] : null;
    }

    /**
     * Populate references in given documents.
     * $foreign may be "collection" or "db.collection".
     * Returns populated documents (array). If single document passed, returns single document.
     */
    public function populate(array $documents, string $localField, string $foreign, string $foreignField = '_id', ?string $as = null): mixed
    {
        $single = false;
        if (array_keys($documents) !== range(0, count($documents) - 1)) {
            // associative or single document
            $single = true;
            $documents = [$documents];
        }

        // collect keys to fetch
        $keys = [];
        foreach ($documents as $d) {
            if (isset($d[$localField])) {
                if (is_array($d[$localField])) {
                    foreach ($d[$localField] as $v) {
                        $keys[] = $v;
                    }
                } else {
                    $keys[] = $d[$localField];
                }
            }
        }
        $keys = array_values(array_unique($keys));

        if (empty($keys)) {
            return $single ? $documents[0] : $documents;
        }

        // resolve client and target collection
        $client = $this->database->client ?? null;
        if (!$client) {
            throw new \RuntimeException('Client not available for populate');
        }

        $dbName = null;
        $collName = $foreign;
        if (strpos($foreign, '.') !== false) {
            list($dbName, $collName) = explode('.', $foreign, 2);
        }

        $targetDb = $dbName ? $client->selectDB($dbName) : $this->database;
        $targetColl = $targetDb->selectCollection($collName);

        $foreignDocs = $targetColl->find([$foreignField => ['$in' => $keys]])->toArray();

        $map = [];
        foreach ($foreignDocs as $fd) {
            $map[$fd[$foreignField]] = $fd;
        }

        $out = [];
        foreach ($documents as $d) {
            $copy = $d;
            $value = $d[$localField] ?? null;
            if ($value === null) {
                $copy[$as ?? $collName] = null;
            } elseif (is_array($value)) {
                $arr = [];
                foreach ($value as $v) {
                    if (isset($map[$v])) {
                        $arr[] = $map[$v];
                    }
                }
                $copy[$as ?? $collName] = $arr;
            } else {
                $copy[$as ?? $collName] = $map[$value] ?? null;
            }
            $out[] = $copy;
        }

        return $single ? $out[0] : $out;
    }

    /**
     * Rename Collection.
     *
     * @param string $newname [description]
     */
    public function renameCollection($newname): bool
    {
        if (!in_array($newname, $this->database->getCollectionNames())) {
            try {
                // Use internal method for DDL statements (no deprecation warning)
                $this->database->queryExecutor->executeRawUpdateInternal('ALTER TABLE ' . $this->database->queryExecutor->quoteTable($this->name) . ' RENAME TO ' . $this->database->queryExecutor->quoteTable($newname));
                $this->name = $newname;

                return true;
            } catch (QueryExecutionException $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Create a JSON index for a field on this collection.
     */
    public function createIndex(string $field, ?string $indexName = null): void
    {
        $this->database->createJsonIndex($this->name, $field, $indexName);
    }

    /**
     * Notify that the collection has changed.
     */
    public function notifyChange(): void
    {
        try {
            // Check if metadata already exists and get current version
            $stmt = $this->database->queryExecutor->executeQuery(
                "SELECT document FROM _meta WHERE json_extract(document, '$._id') = ?",
                [$this->name]
            );
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            $currentVersion = 0;
            if ($existing) {
                $doc = json_decode($existing['document'], true);
                $currentVersion = $doc['version'] ?? 0;
            }
            $newVersion = $currentVersion + 1;

            $document = json_encode([
                '_id' => $this->name,
                'version' => $newVersion,
                'last_updated' => date('c'),
            ]);

            if ($existing) {
                // Update existing - need to get id first
                $stmt = $this->database->queryExecutor->executeQuery(
                    "SELECT id FROM _meta WHERE json_extract(document, '$._id') = ?",
                    [$this->name]
                );
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    $this->database->queryExecutor->executeUpdate(
                        'UPDATE _meta SET document = ? WHERE id = ?',
                        [$document, $row['id']]
                    );
                }
            } else {
                // Insert new
                $this->database->queryExecutor->executeUpdate(
                    'INSERT INTO _meta (document) VALUES (?)',
                    [$document]
                );
            }
        } catch (QueryExecutionException $e) {
            // Silently fail if metadata table isn't ready or other DB issues
        }
    }

    /**
     * Get the current version/timestamp of the collection.
     */
    public function getLastModified(): array
    {
        try {
            $stmt = $this->database->queryExecutor->executeQuery("
                SELECT document FROM _meta WHERE json_extract(document, '\$._id') = ?
            ", [$this->name]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$result) {
                return ['version' => 0, 'last_updated' => null];
            }

            $document = json_decode($result['document'], true);

            return [
                'version' => $document['version'] ?? 0,
                'last_updated' => $document['last_updated'] ?? null,
            ];
        } catch (QueryExecutionException $e) {
            return ['version' => 0, 'last_updated' => null];
        }
    }

    /**
     * Load collection configuration from database.
     */
    protected function loadConfiguration(): void
    {
        $config = $this->database->loadCollectionConfig($this->name);

        if (!empty($config)) {
            // Apply loaded configuration
            if (isset($config['id_mode'])) {
                $this->setIdModeFromString($config['id_mode']);
            }

            // Note: encryption_key should be provided at runtime from external sources (.env, vault, etc.)
            // The config only stores encryption_enabled status
            // Use $collection->setEncryptionKey('your-key') to enable encryption

            if (isset($config['searchable_fields']) && is_array($config['searchable_fields'])) {
                foreach ($config['searchable_fields'] as $field => $hashed) {
                    $this->setSearchableFields([$field], $hashed);
                }
            }

            if (isset($config['schema']) && is_array($config['schema'])) {
                $this->setSchema($config['schema']);
            }

            if (isset($config['soft_deletes_enabled'])) {
                $this->useSoftDeletes($config['soft_deletes_enabled']);
            }

            if (isset($config['deleted_at_field'])) {
                $this->deletedAtField = $config['deleted_at_field'];
            }

            // Load custom configuration
            if (isset($config['custom_config']) && is_array($config['custom_config'])) {
                $this->customConfig = $config['custom_config'];
            }
        }
    }

    /**
     * Save current collection configuration to database.
     */
    public function saveConfiguration(): void
    {
        $config = [
            'id_mode' => $this->getIdModeString(),
            'encryption_enabled' => $this->encryptionKey !== null,
            'searchable_fields' => $this->getSearchableFieldsForConfig(),
            'schema' => $this->getSchema(),
            'soft_deletes_enabled' => $this->softDeletesEnabled(),
            'deleted_at_field' => $this->getDeletedAtField(),
            'custom_config' => $this->customConfig,
        ];

        $this->database->saveCollectionConfig($this->name, $config);
    }

    /**
     * Set a custom configuration value.
     *
     * @param string $key   Configuration key
     * @param mixed  $value Configuration value
     */
    public function setCustomConfig(string $key, $value): self
    {
        $this->customConfig[$key] = $value;

        return $this;
    }

    /**
     * Get a custom configuration value.
     *
     * @param string $key     Configuration key
     * @param mixed  $default Default value if key not found
     *
     * @return mixed Configuration value
     */
    public function getCustomConfig(string $key, $default = null)
    {
        return $this->customConfig[$key] ?? $default;
    }

    /**
     * Get all custom configuration values.
     *
     * @return array Custom configuration values
     */
    public function getAllCustomConfig(): array
    {
        return $this->customConfig;
    }

    /**
     * Set multiple custom configuration values at once.
     *
     * @param array $config Array of key-value pairs
     */
    public function setCustomConfigArray(array $config): self
    {
        $this->customConfig = array_merge($this->customConfig, $config);

        return $this;
    }

    /**
     * Set ID mode from string representation.
     */
    private function setIdModeFromString(string $mode): void
    {
        switch ($mode) {
            case 'auto':
                $this->setIdModeAuto();
                break;
            case 'manual':
                $this->setIdModeManual();
                break;
            default:
                // Handle prefix mode - assume the mode string is the prefix
                $this->setIdModePrefix($mode);
                break;
        }
    }

    /**
     * Get ID mode as string representation.
     */
    private function getIdModeString(): string
    {
        return $this->idMode === 'prefix' ? ($this->idPrefix ?? 'auto') : $this->idMode;
    }

    /**
     * Get searchable fields configuration for saving.
     */
    private function getSearchableFieldsForConfig(): array
    {
        $config = [];
        foreach ($this->searchableFields as $field => $settings) {
            $config[$field] = $settings['hash'];
        }

        return $config;
    }
}
