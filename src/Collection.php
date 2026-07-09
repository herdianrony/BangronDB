<?php

declare(strict_types=1);

namespace BangronDB;

use BangronDB\Enums\HookEvent;
use BangronDB\Enums\IdMode;
use BangronDB\Exceptions\QueryExecutionException;
use BangronDB\Traits\ChangeTrackingTrait;
use BangronDB\Traits\ConfigurationPersistenceTrait;
use BangronDB\Traits\EncryptionTrait;
use BangronDB\Traits\HooksTrait;
use BangronDB\Traits\IdGeneratorTrait;
use BangronDB\Traits\QueryBuilderTrait;
use BangronDB\Traits\SchemaValidationTrait;
use BangronDB\Traits\SearchableFieldsTrait;
use BangronDB\Traits\SoftDeleteTrait;
use BangronDB\Traits\TtlTrait;

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
    use ChangeTrackingTrait;
    use ConfigurationPersistenceTrait;
    use TtlTrait;

    /**
     * ID Generation Mode Constants.
     */
    // Backward-compatible enum references
    public const ID_MODE_AUTO = 'auto';          // Generate UUID v4 automatically
    public const ID_MODE_MANUAL = 'manual';      // Use provided _id only
    public const ID_MODE_PREFIX = 'prefix';      // Generate with prefix

    /**
     * Hook Event Constants.
     */
    // Backward-compatible enum references
    public const HOOK_BEFORE_INSERT = 'beforeInsert';
    public const HOOK_AFTER_INSERT = 'afterInsert';
    public const HOOK_BEFORE_UPDATE = 'beforeUpdate';
    public const HOOK_AFTER_UPDATE = 'afterUpdate';
    public const HOOK_BEFORE_REMOVE = 'beforeRemove';
    public const HOOK_AFTER_REMOVE = 'afterRemove';

    /**
     * Encryption constants (PHP 8.1 compatible — cannot be declared in traits).
     */
    private const MAX_DERIVED_KEY_CACHE_SIZE = 16;
    private const LEGACY_PBKDF2_SALT = 'bangrondb_encryption_salt';
    private const MAX_DOCUMENT_DEPTH = 64;
    private const MIN_KEY_LENGTH = 32;
    private const ENCRYPTION_VERSION = 2;

    public readonly Database $database;

    public string $name; // NOT readonly because renameCollection modifies it

    /**
     * Whether the collection table has been verified to exist.
     * Caches the result to avoid repeated CREATE TABLE IF NOT EXISTS calls.
     */
    private bool $collectionVerified = false;

    /**
     * Constructor.
     *
     * @param string   $name     Collection name
     * @param Database $database Database instance
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
    public function drop(): void
    {
        $this->database->dropCollection($this->name);
    }

    public function forceDelete(mixed $criteria): int
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
        // Reject empty document (not a batch, just [])
        if ($document === []) {
            throw new \InvalidArgumentException(
                'insert() requires a non-empty document. ' .
                'Pass an associative array like ["name" => "John"] to insert a single document.'
            );
        }

        if (isset($document[0])) {
            $this->database->connection->beginTransaction();

            try {
                foreach ($document as $doc) {
                    if (!\is_array($doc)) {
                        throw new \InvalidArgumentException('Batch insert requires all items to be arrays');
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
     * Ensure collection table exists (cached to avoid repeated CREATE TABLE).
     */
    protected function ensureCollectionExists(): void
    {
        if (!$this->collectionVerified) {
            $this->database->ensureCollectionTable($this->name);
            $this->collectionVerified = true;
        }
    }

    /**
     * Mark collection as needing re-verification (e.g., after rename or drop).
     */
    public function invalidateCollectionCache(): void
    {
        $this->collectionVerified = false;
    }

    /**
     * Insert document.
     */
    protected function _insert(array $document): mixed
    {
        $this->validate($document);
        $this->validateUnique($document);
        $this->ensureCollectionExists();

        // Apply TTL: set default expiration if configured and not already set
        $document = $this->applyTtlOnInsert($document);

        $doc = $this->applyBeforeInsertHooks($document);
        if ($doc === false) {
            return false;
        }

        $doc = $this->ensureDocumentId($doc);
        if ($doc === false) {
            return false;
        }

        $data = $this->prepareDocumentForStorage($doc);
        $insertId = $this->executeInsert($data, $doc['_id'] ?? null);

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
     * Execute the actual SQL insert statement using prepared statements.
     */
    protected function executeInsert(array $data, ?string $insertId = null): mixed
    {
        $table = $this->database->quoteIdentifier($this->name);
        $fields = [];
        $placeholders = [];
        $params = [];

        foreach ($data as $col => $value) {
            $fields[] = '`' . str_replace('`', '``', $col) . '`';
            $placeholders[] = '?';
            $params[] = $value;
        }

        $fieldsStr = \implode(',', $fields);
        $placeholdersStr = \implode(',', $placeholders);

        $sql = "INSERT INTO {$table} ({$fieldsStr}) VALUES ({$placeholdersStr})";

        try {
            $this->database->queryExecutor->executeUpdate($sql, $params);
            return $insertId ?? ($data['document'] ? json_decode($data['document'], true)['_id'] : null);
        } catch (QueryExecutionException $e) {
            $this->logSqlError($sql);
            return false;
        }
    }

    /**
     * Log SQL error for debugging.
     */
    protected function logSqlError(string $sql): void
    {
        // Log error without exposing full SQL details to prevent information leakage
        $errorInfo = $this->database->connection->errorInfo();
        error_log('BangronDB SQL Error: ' . ($errorInfo[2] ?? 'Unknown error') . ' | Query type: ' . strtoupper(explode(' ', trim($sql))[0]));
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

        $idVal = $document['_id'];

        if (!$this->documentExists((string) $idVal)) {
            return $this->insert($document);
        }

        $updated = $this->update(['_id' => $idVal], $document, false);

        return $updated > 0 ? $idVal : false;
    }

    /**
     * Check whether a document exists by its _id.
     */
    protected function documentExists(string $id): bool
    {
        $this->ensureCollectionExists();
        $table = $this->database->quoteIdentifier($this->name);

        try {
            $stmt = $this->database->queryExecutor->executeQuery(
                "SELECT 1 FROM {$table} WHERE json_extract(document, '$._id') = ? LIMIT 1",
                [$id]
            );

            return $stmt->fetch(\PDO::FETCH_ASSOC) !== false;
        } catch (QueryExecutionException $e) {
            return false;
        }
    }

    /**
     * Update documents.
     */
    public function update(mixed $criteria, array $data, bool $merge = true): int
    {
        $this->ensureCollectionExists();
        $this->applyUpdateHooks($criteria, $data);
        $updated = $this->bulkUpdate($criteria, $data, $merge);
        if ($updated > 0) {
            $this->notifyChange();
        }

        return $updated;
    }

    /**
     * Perform a bulk update using SQL UPDATE WHERE for criteria that can be translated to JSON WHERE.
     * Falls back to per-document update when hooks are registered or criteria cannot be translated.
     */
    protected function bulkUpdate($criteria, array $data, bool $merge): int
    {
        if (!empty($this->hooks[self::HOOK_AFTER_UPDATE]) || !$this->_canTranslateToJsonWhere($criteria)) {
            return $this->perDocumentUpdate($criteria, $data, $merge);
        }

        $table = $this->database->quoteIdentifier($this->name);
        $params = [];
        $where = $this->_buildJsonWhere($criteria, $params);

        // Fetch IDs of matching documents
        try {
            $stmt = $this->database->queryExecutor->executeQuery(
                "SELECT id, document FROM {$table} WHERE " . $where,
                $params
            );
            $documents = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (QueryExecutionException $e) {
            return 0;
        }

        $updated = 0;
        foreach ($documents as $doc) {
            $_doc = $this->decodeStored($doc['document']) ?? [];
            $document = $this->mergeDocumentData($_doc, $data, $merge);
            if (!$merge) {
                $this->validate($document);
            }
            $this->validateUnique($document, $_doc['_id'] ?? null);
            $encoded = $this->encodeStored($document);
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }
            $indexData = $this->_computeSearchIndexValues($document);
            $setParts = ['document = ?'];
            $setParams = [$encoded];
            foreach ($indexData as $col => $val) {
                $setParts[] = '`' . str_replace('`', '``', $col) . '` = ?';
                $setParams[] = $val;
            }
            $setParams[] = $doc['id'];
            $sql = "UPDATE {$table} SET " . implode(',', $setParts) . ' WHERE id = ?';
            try {
                $this->database->queryExecutor->executeUpdate($sql, $setParams);
                ++$updated;
            } catch (QueryExecutionException $e) {
                $this->logSqlError($sql);
            }
        }

        return $updated;
    }

    /**
     * Perform per-document update (fallback for complex criteria or when hooks are registered).
     */
    protected function perDocumentUpdate($criteria, array $data, bool $merge): int
    {
        $documentsToUpdate = $this->findDocumentsMatchingCriteria($criteria);
        $updated = 0;
        foreach ($documentsToUpdate as $doc) {
            $updated += $this->updateDocument($doc, $data, $merge);
        }
        return $updated;
    }

    /**
     * Find documents matching criteria for update/remove operations.
     */
    protected function findDocumentsMatchingCriteria($criteria): array
    {
        $table = $this->database->quoteIdentifier($this->name);

        if (is_array($criteria) && $this->_canTranslateToJsonWhere($criteria)) {
            $params = [];
            $where = $this->_buildJsonWhere($criteria, $params);
            $sql = "SELECT id, document FROM {$table} WHERE " . $where;
        } else {
            $sql = "SELECT id, document FROM {$table} WHERE document_criteria(?, document)";
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

        // Enforce unique constraints, ignoring the document being updated itself.
        $this->validateUnique($document, $_doc['_id'] ?? ($doc['_id'] ?? null));

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

            // Handle atomic operators: $set, $unset, $inc
            if (isset($newData['$set']) || isset($newData['$unset']) || isset($newData['$inc'])) {
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
                if (isset($newData['$inc']) && is_array($newData['$inc'])) {
                    foreach ($newData['$inc'] as $k => $v) {
                        if (!is_numeric($v)) {
                            throw new \InvalidArgumentException(
                                "\$inc value for field '{$k}' must be numeric. Got: " . gettype($v)
                            );
                        }
                        $current = $document[$k] ?? 0;
                        if (!is_numeric($current)) {
                            throw new \InvalidArgumentException(
                                "Cannot \$inc field '{$k}': current value is not numeric ("
                                . gettype($current) . '). Use $set to replace non-numeric values first.'
                            );
                        }
                        $document[$k] = $current + $v;
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
        $table = $this->database->quoteIdentifier($this->name);
        $setParts = [];
        $params = [];

        $setParts[] = 'document = ?';
        $params[] = $encoded;

        foreach ($indexData as $col => $val) {
            $setParts[] = '`' . str_replace('`', '``', $col) . '` = ?';
            $params[] = $val;
        }

        $params[] = $docId;

        $sql = "UPDATE {$table} SET " . implode(',', $setParts) . ' WHERE id = ?';

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
    public function remove(mixed $criteria): int
    {
        $this->ensureCollectionExists();
        if ($this->softDeletesEnabled) {
            return $this->update($criteria, ['$set' => [$this->getDeletedAtField() => time()]]);
        }

        $criteria = $this->applyHooks(self::HOOK_BEFORE_REMOVE, $criteria);
        if ($criteria === false) {
            return 0;
        }

        $hasHooks = !empty($this->hooks[self::HOOK_AFTER_REMOVE]);
        $table = $this->database->quoteIdentifier($this->name);

        if (!$hasHooks && \is_array($criteria) && $this->_canTranslateToJsonWhere($criteria)) {
            // Optimized bulk delete path
            $params = [];
            $where = $this->_buildJsonWhere($criteria, $params);
            $sql = "DELETE FROM {$table} WHERE " . $where;
            try {
                $deleted = $this->database->queryExecutor->executeUpdate($sql, $params);
                if ($deleted > 0) {
                    $this->notifyChange();
                }

                return $deleted;
            } catch (QueryExecutionException $e) {
                $this->logSqlError($sql);
                return 0;
            }
        }

        // Fallback to per-document deletion
        $documentsToRemove = $this->findDocumentsMatchingCriteria($criteria);
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
     * Remove a single document from the database.
     */
    protected function removeDocument(int $docId, string $document): void
    {
        $doc = $this->decodeStored($document) ?: [];

        $table = $this->database->quoteIdentifier($this->name);
        $delSql = "DELETE FROM {$table} WHERE id = ?";

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
    public function count(mixed $criteria = null): int
    {
        $this->ensureCollectionExists();

        // Fast path for no criteria without soft deletes
        if ($criteria === null && !$this->softDeletesEnabled) {
            $table = $this->database->quoteIdentifier($this->name);
            try {
                $stmt = $this->database->queryExecutor->executeQuery("SELECT COUNT(*) as c FROM {$table}");
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                return $row ? (int) $row['c'] : 0;
            } catch (QueryExecutionException $e) {
                return 0;
            }
        }

        return $this->find($criteria)->count();
    }

    /**
     * Find documents.
     *
     * @return object Cursor
     */
    public function find(mixed $criteria = null, ?array $projection = null): Cursor
    {
        return new Cursor($this, $criteria, $projection);
    }

    /**
     * Find one document.
     */
    public function findOne(mixed $criteria = null, ?array $projection = null): ?array
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
     * @param string $newname The new name for the collection
     */
    public function renameCollection(string $newname): bool
    {
        $oldName = $this->name;

        if ($newname === $oldName || in_array($newname, $this->database->getCollectionNames(), true)) {
            return false;
        }

        try {
            $this->database->connection->beginTransaction();

            // Use internal method for DDL statements (no deprecation warning)
            $quotedOld = $this->database->quoteIdentifier($oldName);
            $quotedNew = $this->database->quoteIdentifier($newname);
            $this->database->queryExecutor->executeRawUpdateInternal("ALTER TABLE {$quotedOld} RENAME TO {$quotedNew}");
            $this->database->renameCollectionReferences($oldName, $newname);

            $this->database->connection->commit();
            $this->name = $newname;
            $this->database->renameCollectionInCache($this, $oldName, $newname);

            return true;
        } catch (\Throwable $e) {
            if ($this->database->connection->inTransaction()) {
                $this->database->connection->rollBack();
            }

            return false;
        }
    }

    /**
     * Create a JSON index for a field on this collection.
     */
    /**
     * Prevent sensitive data from being exposed via var_dump/print_r.
     */
    public function __debugInfo(): array
    {
        return [
            'name' => $this->name,
            'database' => $this->database->path,
            'encryption' => $this->getDebugEncryptionInfo(),
            'idMode' => $this->idMode,
            'softDeletesEnabled' => $this->softDeletesEnabled,
            'ttlEnabled' => $this->ttlEnabled,
            'ttlField' => $this->ttlEnabled ? $this->ttlField : null,
            'schema' => $this->schema,
            'searchableFields' => array_keys($this->searchableFields),
            'hooks' => array_map('count', $this->hooks),
        ];
    }

    public function createIndex(string $field, ?string $indexName = null): void
    {
        $this->database->createJsonIndex($this->name, $field, $indexName);
    }

    /* ================= SECURITY AUDIT (Prioritas 4) ================= */

    /**
     * Perform a security audit on this collection.
     *
     * Analyzes encryption configuration, schema validation, searchable fields,
     * and provides actionable security recommendations.
     *
     * @return array{encryption: array{is_encrypted: bool, key_version: mixed, db_encrypted: bool, is_secure: bool, issues: array<int, string>, recommendations: array<int, string>}, configuration: array{schema: bool, searchable_fields: int, soft_deletes: bool, is_secure: bool, checks: array<int, string>, recommendations: array<int, string>}, recommendations: array<int, string>, overall_score: int, score_label: string, audited_at: int}
     *
     * @example
     *   $audit = $collection->securityAudit();
     *   echo "Security score: {$audit['overall_score']}/100 ({$audit['score_label']})";
     *   foreach ($audit['recommendations'] as $rec) {
     *       echo "- {$rec}";
     *   }
     */
    public function securityAudit(): array
    {
        return \BangronDB\Security\SecurityAuditor::auditCollection($this);
    }

    /* ================= BULK OPERATIONS (Prioritas 3) ================= */

    /**
     * Insert multiple documents at once.
     *
     * Unlike insert() with an array (which also supports batch), insertMany()
     * provides an explicit MongoDB-compatible API and returns detailed results
     * including inserted IDs and any errors encountered.
     *
     * @param  array<int, array<string, mixed>> $documents  Array of documents to insert
     * @return array{inserted_count: int, inserted_ids: array<int, string>}
     *
     * @throws \InvalidArgumentException If documents is empty or contains non-array items
     */
    public function insertMany(array $documents): array
    {
        if (empty($documents)) {
            throw new \InvalidArgumentException(
                'insertMany() requires a non-empty array of documents. ' .
                'Each item must be an associative array representing a document.'
            );
        }

        $insertedIds = [];
        $this->database->connection->beginTransaction();

        try {
            foreach ($documents as $index => $doc) {
                if (!is_array($doc)) {
                    throw new \InvalidArgumentException(
                        "insertMany(): Item at index {$index} is not an array. " .
                        'All items must be associative arrays representing documents.'
                    );
                }

                $result = $this->_insert($doc);

                if (!$result) {
                    $this->database->connection->rollBack();
                    return [
                        'inserted_count' => count($insertedIds),
                        'inserted_ids' => $insertedIds,
                    ];
                }

                $insertedIds[] = (string) $result;
            }

            $this->database->connection->commit();
            $this->notifyChange();

            return [
                'inserted_count' => count($insertedIds),
                'inserted_ids' => $insertedIds,
            ];
        } catch (\Throwable $e) {
            if ($this->database->connection->inTransaction()) {
                $this->database->connection->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Update multiple documents matching criteria.
     *
     * This is an explicit MongoDB-compatible alias for update() with a
     * return type that includes the matched count for better DX.
     *
     * @param  mixed               $criteria  Query criteria
     * @param  array<string, mixed> $data      Update data (supports $set, $unset, $inc operators)
     * @param  array<string, mixed> $options   Options: ['merge' => bool (default true)]
     * @return array{matched_count: int, modified_count: int}
     */
    public function updateMany(mixed $criteria, array $data, array $options = []): array
    {
        $merge = $options['merge'] ?? true;
        $this->ensureCollectionExists();

        // Single-pass approach: fetch matched docs once, then update inline
        // This avoids the double query that would happen if we called
        // count() + update() separately.
        $matchedDocs = $this->findDocumentsMatchingCriteria($criteria);
        $matched = count($matchedDocs);

        if ($matched === 0) {
            return ['matched_count' => 0, 'modified_count' => 0];
        }

        // Apply before-update hooks
        $this->applyUpdateHooks($criteria, $data);

        $modified = 0;
        foreach ($matchedDocs as $doc) {
            $_doc = $this->decodeStored($doc['document']) ?? [];
            $document = $this->mergeDocumentData($_doc, $data, $merge);

            if (!$merge) {
                $this->validate($document);
            }
            $this->validateUnique($document, $_doc['_id'] ?? null);

            $encoded = $this->encodeStored($document);
            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }

            $this->executeDocumentUpdate((int) $doc['id'], $document, $encoded);
            $this->triggerAfterUpdateHooks($_doc, $document);
            ++$modified;
        }

        if ($modified > 0) {
            $this->notifyChange();
        }

        return [
            'matched_count' => $matched,
            'modified_count' => $modified,
        ];
    }

    /**
     * Delete multiple documents matching criteria.
     *
     * This is an explicit MongoDB-compatible alias for remove() with a
     * return type that includes the deleted count for better DX.
     *
     * @param  mixed $criteria Query criteria
     * @return array{deleted_count: int}
     */
    public function deleteMany(mixed $criteria): array
    {
        $deleted = $this->remove($criteria);

        return [
            'deleted_count' => $deleted,
        ];
    }

    /* ================= AGGREGATION PIPELINE (Prioritas 3) ================= */

    /**
     * Execute an aggregation pipeline on the collection.
     *
     * Supports the following pipeline operators:
     * - $match:  Filter documents (same syntax as find criteria)
     * - $group:  Group documents and compute aggregates ($sum, $avg, $min, $max, $count, $first, $last, $push, $addToSet)
     * - $sort:   Sort documents (same syntax as Cursor::sort)
     * - $limit:  Limit the number of results
     * - $skip:   Skip N documents
     * - $project: Reshape documents (include/exclude fields, compute expressions)
     * - $count:  Count documents passing through the pipeline
     * - $unset:  Remove specific fields from documents
     *
     * @param  array<int, array<string, mixed>> $pipeline Array of pipeline stages
     * @return array<int, array<string, mixed>>
     *
     * @throws \InvalidArgumentException If pipeline is empty or contains invalid stages
     *
     * @example
     *   $results = $collection->aggregate([
     *       ['$match' => ['status' => 'active']],
     *       ['$group' => ['_id' => '$category', 'total' => ['$sum' => '$price'], 'count' => ['$count' => null]]],
     *       ['$sort' => ['total' => -1]],
     *       ['$limit' => 10],
     *   ]);
     */
    public function aggregate(array $pipeline): array
    {
        if (empty($pipeline)) {
            throw new \InvalidArgumentException(
                'aggregate() requires a non-empty pipeline array. ' .
                'Provide at least one pipeline stage, e.g.: [["$match" => ["status" => "active"]]]'
            );
        }

        // Stage 1: Start with all documents (filtered by soft delete if enabled)
        $documents = $this->getAllDocuments();

        // Stage 2: Process each pipeline stage sequentially
        foreach ($pipeline as $index => $stage) {
            if (!is_array($stage) || count($stage) !== 1) {
                $stageStr = json_encode($stage);
                throw new \InvalidArgumentException(
                    "Pipeline stage at index {$index} must be an associative array with exactly one key. " .
                    "Got: {$stageStr}. Each stage should be like ['\$match' => [...]]"
                );
            }

            $operator = array_key_first($stage);
            $arguments = reset($stage);

            $documents = $this->executePipelineStage($documents, $operator, $arguments, $index);
        }

        return $documents;
    }

    /**
     * Execute a single pipeline stage.
     *
     * @param  array<int, array<string, mixed>> $documents Current document set
     * @param  string                              $operator  Pipeline operator name
     * @param  mixed                               $arguments Stage arguments
     * @param  int                                 $index     Stage index for error messages
     * @return array<int, array<string, mixed>>
     */
    private function executePipelineStage(array $documents, string $operator, mixed $arguments, int $index): array
    {
        return match ($operator) {
            '$match'  => $this->stageMatch($documents, $arguments, $index),
            '$group'  => $this->stageGroup($documents, $arguments, $index),
            '$sort'   => $this->stageSort($documents, $arguments, $index),
            '$limit'  => $this->stageLimit($documents, $arguments, $index),
            '$skip'   => $this->stageSkip($documents, $arguments, $index),
            '$project' => $this->stageProject($documents, $arguments, $index),
            '$count'  => $this->stageCount($documents, $arguments, $index),
            '$unset'  => $this->stageUnset($documents, $arguments, $index),
            default   => throw new \InvalidArgumentException(
                "Unknown pipeline operator '{$operator}' at stage {$index}. " .
                'Supported operators: $match, $group, $sort, $limit, $skip, $project, $count, $unset'
            ),
        };
    }

    /**
     * $match stage: Filter documents using query criteria.
     */
    /** @param array<int, array<string, mixed>> $documents @return array<int, array<string, mixed>> */
    private function stageMatch(array $documents, mixed $criteria, int $index): array
    {
        if (!is_array($criteria)) {
            throw new \InvalidArgumentException(
                "\$match stage at index {$index} requires an array of criteria. " .
                'Use the same syntax as find(), e.g.: ["status" => "active"]'
            );
        }

        return array_values(array_filter(
            $documents,
            fn(array $doc) => UtilArrayQuery::match($criteria, $doc)
        ));
    }

    /**
     * $group stage: Group documents and compute aggregate values.
     *
     * Accumulator operators: $sum, $avg, $min, $max, $count, $first, $last, $push, $addToSet
     * Field references: use '$fieldName' to reference a document field
     *
     * @example
     *   ['$group' => [
     *       '_id' => '$category',
     *       'total' => ['$sum' => '$price'],
     *       'count' => ['$count' => null],
     *   ]]
     */
    /** @param array<int, array<string, mixed>> $documents @return array<int, array<string, mixed>> */
    private function stageGroup(array $documents, mixed $args, int $index): array
    {
        if (!is_array($args) || !array_key_exists('_id', $args)) {
            throw new \InvalidArgumentException(
                "\$group stage at index {$index} requires an '_id' field for grouping. " .
                "Use null to group all documents together: ['_id' => null, 'total' => ['\$sum' => '\$price']]"
            );
        }

        $idExpression = $args['_id'];
        unset($args['_id']);
        $accumulators = $args;

        if (empty($accumulators)) {
            throw new \InvalidArgumentException(
                "\$group stage at index {$index} requires at least one accumulator field besides '_id'. " .
                "Example: ['total' => ['\$sum' => '\$amount']]"
            );
        }

        // Group documents by _id expression
        $groups = [];
        $groupOrder = [];

        foreach ($documents as $doc) {
            $groupId = $this->resolveGroupKey($idExpression, $doc);
            $groupIdKey = is_array($groupId) ? json_encode($groupId, JSON_UNESCAPED_UNICODE) : (string) $groupId;

            if (!isset($groups[$groupIdKey])) {
                $groups[$groupIdKey] = ['_id' => $groupId];
                $groupOrder[] = $groupIdKey;
                foreach ($accumulators as $field => $accExpr) {
                    $groups[$groupIdKey][$field] = $this->getAccumulatorInitialValue($accExpr);
                }
            }

            foreach ($accumulators as $field => $accExpr) {
                $groups[$groupIdKey][$field] = $this->accumulateValue(
                    $groups[$groupIdKey][$field],
                    $accExpr,
                    $doc,
                    $field
                );
            }
        }

        // Return in original grouping order, finalizing $avg accumulators
        return array_map(function ($key) use ($groups, $accumulators) {
            $group = $groups[$key];
            foreach ($accumulators as $field => $accExpr) {
                $operator = array_key_first($accExpr);
                if ($operator === '$avg' && is_array($group[$field])) {
                    if ($group[$field]['count'] > 0) {
                        $group[$field] = round($group[$field]['sum'] / $group[$field]['count'], 10);
                    } else {
                        $group[$field] = null;
                    }
                }
            }
            return $group;
        }, $groupOrder);
    }

    /**
     * Resolve the group key for a document based on _id expression.
     */
    /** @param array<string, mixed> $doc */
    private function resolveGroupKey(mixed $expression, array $doc): mixed
    {
        if ($expression === null) {
            return null;
        }

        if (is_string($expression) && str_starts_with($expression, '$')) {
            return UtilArrayQuery::get($doc, substr($expression, 1));
        }

        return $expression;
    }

    /**
     * Get the initial value for an accumulator.
     */
    /** @param array<string, mixed> $accExpr */
    private function getAccumulatorInitialValue(array $accExpr): mixed
    {
        $operator = array_key_first($accExpr);

        return match ($operator) {
            '$sum' => 0,
            '$avg' => ['sum' => 0, 'count' => 0],
            '$min', '$max' => null,
            '$count' => 0,
            '$first', '$last' => null,
            '$push' => [],
            '$addToSet' => [],
            default => throw new \InvalidArgumentException(
                "Unknown accumulator operator '{$operator}'. " .
                'Supported: $sum, $avg, $min, $max, $count, $first, $last, $push, $addToSet'
            ),
        };
    }

    /**
     * Accumulate a value into the current accumulator state.
     */
    /** @param array<string, mixed> $accExpr @param array<string, mixed> $doc */
    private function accumulateValue(mixed $current, array $accExpr, array $doc, string $field): mixed
    {
        $operator = array_key_first($accExpr);
        $expression = reset($accExpr);

        // Resolve the value from the document if it's a field reference
        $value = $this->resolveAccumulatorValue($expression, $doc);

        return match ($operator) {
            '$sum' => is_numeric($current) && is_numeric($value)
                ? $current + $value : $current,
            '$avg' => is_numeric($value) && is_array($current)
                ? ['sum' => ($current['sum'] ?? 0) + $value, 'count' => ($current['count'] ?? 0) + 1]
                : $current,
            '$min' => ($current === null || $value < $current) ? $value : $current,
            '$max' => ($current === null || $value > $current) ? $value : $current,
            '$count' => $current + 1,
            '$first' => $current === null ? $value : $current,
            '$last' => $value,
            '$push' => [...($current ?? []), $value],
            '$addToSet' => $this->addToSet($current, $value),
            default => $current,
        };
    }

    /**
     * Add a value to a set (unique values only).
     */
    /** @return array<int, mixed> */
    private function addToSet(mixed $current, mixed $value): array
    {
        $set = $current ?? [];
        $valueKey = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
        $set[$valueKey] = $value;

        return array_values($set);
    }

    /**
     * Resolve the value for an accumulator expression.
     */
    /** @param array<string, mixed> $doc */
    private function resolveAccumulatorValue(mixed $expression, array $doc): mixed
    {
        if ($expression === null) {
            return null;
        }

        if (is_string($expression) && str_starts_with($expression, '$')) {
            return UtilArrayQuery::get($doc, substr($expression, 1));
        }

        return $expression;
    }

    /**
     * $sort stage: Sort documents by specified fields.
     */
    /** @param array<int, array<string, mixed>> $documents @return array<int, array<string, mixed>> */
    private function stageSort(array $documents, mixed $sortFields, int $index): array
    {
        if (!is_array($sortFields) || empty($sortFields)) {
            throw new \InvalidArgumentException(
                "\$sort stage at index {$index} requires a non-empty sort specification. " .
                "Example: ['created_at' => -1, 'name' => 1] (1 = ASC, -1 = DESC)"
            );
        }

        usort($documents, function (array $a, array $b) use ($sortFields): int {
            foreach ($sortFields as $field => $direction) {
                $valA = UtilArrayQuery::get($a, (string) $field);
                $valB = UtilArrayQuery::get($b, (string) $field);

                if ($valA === $valB) {
                    continue;
                }

                // Handle null values (nulls sort last)
                if ($valA === null) {
                    return 1;
                }
                if ($valB === null) {
                    return -1;
                }

                // Compare based on type
                $cmp = $this->compareValues($valA, $valB);

                return ($direction === -1) ? -$cmp : $cmp;
            }

            return 0;
        });

        return $documents;
    }

    /**
     * Compare two values for sorting.
     */
    private function compareValues(mixed $a, mixed $b): int
    {
        if (is_numeric($a) && is_numeric($b)) {
            return $a <=> $b;
        }

        if (is_string($a) && is_string($b)) {
            return strcmp($a, $b);
        }

        // Mixed types: compare string representations
        return strcmp((string) $a, (string) $b);
    }

    /**
     * $limit stage: Limit the number of documents.
     */
    /** @param array<int, array<string, mixed>> $documents @return array<int, array<string, mixed>> */
    private function stageLimit(array $documents, mixed $limit, int $index): array
    {
        if (!is_int($limit) || $limit < 0) {
            throw new \InvalidArgumentException(
                "\$limit stage at index {$index} requires a non-negative integer. Got: " .
                (is_int($limit) ? $limit : gettype($limit))
            );
        }

        return array_slice($documents, 0, $limit);
    }

    /**
     * $skip stage: Skip N documents.
     */
    /** @param array<int, array<string, mixed>> $documents @return array<int, array<string, mixed>> */
    private function stageSkip(array $documents, mixed $skip, int $index): array
    {
        if (!is_int($skip) || $skip < 0) {
            throw new \InvalidArgumentException(
                "\$skip stage at index {$index} requires a non-negative integer. Got: " .
                (is_int($skip) ? $skip : gettype($skip))
            );
        }

        return array_slice($documents, $skip);
    }

    /**
     * $project stage: Reshape documents by including/excluding fields.
     *
     * Use 1 or true to include, 0 or false to exclude.
     * Field references: use '$fieldName' to include a field from the source document.
     *
     * @example
     *   ['$project' => ['name' => 1, 'email' => 1, 'password' => 0]]
     *   ['$project' => ['userName' => '$name', 'userEmail' => '$email']]
     */
    /** @param array<int, array<string, mixed>> $documents @return array<int, array<string, mixed>> */
    private function stageProject(array $documents, mixed $projection, int $index): array
    {
        if (!is_array($projection) || empty($projection)) {
            throw new \InvalidArgumentException(
                "\$project stage at index {$index} requires a non-empty projection. " .
                "Example: ['name' => 1, 'email' => 1] or ['password' => 0]"
            );
        }

        $isInclusive = $this->isInclusiveProjectionSpec($projection);

        return array_map(function (array $doc) use ($projection, $isInclusive): array {
            return $isInclusive
                ? $this->applyInclusiveProjectStage($doc, $projection)
                : $this->applyExclusiveProjectStage($doc, $projection);
        }, $documents);
    }

    /**
     * Check if a projection spec is inclusive (has at least one truthy value).
     */
    /** @param array<string, mixed> $projection */
    private function isInclusiveProjectionSpec(array $projection): bool
    {
        foreach ($projection as $value) {
            if ($value === 1 || $value === true) {
                return true;
            }
            // Field references like '$fieldName' indicate inclusive projection with rename
            if (is_string($value) && str_starts_with($value, '$')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply inclusive projection (only include specified fields).
     */
    /** @param array<string, mixed> $doc @param array<string, mixed> $projection @return array<string, mixed> */
    private function applyInclusiveProjectStage(array $doc, array $projection): array
    {
        $result = [];

        foreach ($projection as $field => $spec) {
            if ($spec === 0 || $spec === false) {
                continue;
            }

            if (is_string($spec) && str_starts_with($spec, '$')) {
                // Field reference: map source field to new field name
                $sourceField = substr($spec, 1);
                $value = UtilArrayQuery::get($doc, $sourceField);
                $result[$field] = $value;
            } else {
                // Direct field inclusion
                if (array_key_exists($field, $doc)) {
                    $result[$field] = $doc[$field];
                }
            }
        }

        // Always include _id unless explicitly excluded
        if (isset($doc['_id']) && !isset($result['_id']) && ($projection['_id'] ?? 1) !== 0) {
            $result['_id'] = $doc['_id'];
        }

        return $result;
    }

    /**
     * Apply exclusive projection (remove specified fields).
     */
    /** @param array<string, mixed> $doc @param array<string, mixed> $projection @return array<string, mixed> */
    private function applyExclusiveProjectStage(array $doc, array $projection): array
    {
        foreach ($projection as $field => $spec) {
            if ($spec === 0 || $spec === false) {
                unset($doc[$field]);
            }
        }

        return $doc;
    }

    /**
     * $count stage: Count documents and return a single document with the count.
     */
    /** @param array<int, array<string, mixed>> $documents @return array<int, array<string, mixed>> */
    private function stageCount(array $documents, mixed $fieldName, int $index): array
    {
        if (!is_string($fieldName)) {
            throw new \InvalidArgumentException(
                "\$count stage at index {$index} requires a string field name for the count. " .
                "Example: ['\$count' => 'totalDocuments']"
            );
        }

        return [[$fieldName => count($documents)]];
    }

    /**
     * $unset stage: Remove specific fields from all documents.
     */
    /** @param array<int, array<string, mixed>> $documents @return array<int, array<string, mixed>> */
    private function stageUnset(array $documents, mixed $fields, int $index): array
    {
        $fieldsToRemove = is_array($fields) ? $fields : [$fields];

        if (empty($fieldsToRemove)) {
            throw new \InvalidArgumentException(
                "\$unset stage at index {$index} requires at least one field name. " .
                "Example: ['\$unset' => ['password', 'secret']]"
            );
        }

        return array_map(function (array $doc) use ($fieldsToRemove): array {
            foreach ($fieldsToRemove as $field) {
                unset($doc[$field]);
            }

            return $doc;
        }, $documents);
    }

    /** @return array<int, array<string, mixed>> */
    private function getAllDocuments(): array
    {
        $this->ensureCollectionExists();
        $table = $this->database->quoteIdentifier($this->name);

        $sql = "SELECT document FROM {$table}";
        $params = [];

        // Filter soft-deleted documents when soft delete is enabled
        if ($this->softDeletesEnabled) {
            $field = $this->getDeletedAtField();
            $escapedField = str_replace("'", "''", $field);
            $sql .= " WHERE json_extract(document, '$." . $escapedField . "') IS NULL";
        }

        try {
            $stmt = $this->database->queryExecutor->executeQuery($sql, $params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $documents = [];
            foreach ($rows as $row) {
                $doc = $this->decodeStored($row['document']);
                if ($doc !== null) {
                    $documents[] = $doc;
                }
            }

            return $documents;
        } catch (QueryExecutionException $e) {
            return [];
        }
    }

    /* ================= EXPLAIN QUERY (Prioritas 3) ================= */

    /**
     * Explain how a query is executed, returning execution plan details.
     *
     * Returns information about index usage, full scan status,
     * documents scanned, and execution time.
     *
     * @param  mixed $criteria Query criteria (same as find())
     * @return array{query_plan: array, performance: array}
     *
     * @example
     *   $explanation = $collection->explain(['status' => 'active']);
     *   echo $explanation['query_plan']['uses_index'] ? 'Uses index' : 'Full scan';
     *   echo "Scanned: {$explanation['performance']['documents_scanned']} documents";
     */
    /** @return array{query_plan: array<string, mixed>, performance: array<string, mixed>, suggestions: array<int, string>} */
    public function explain(mixed $criteria = null): array
    {
        $this->ensureCollectionExists();
        $startTime = microtime(true);

        $table = $this->database->quoteIdentifier($this->name);

        // Determine query strategy
        $usesSqlWhere = false;
        $usesCriteriaFunction = false;
        $whereClause = '';

        if (is_array($criteria) && !empty($criteria)) {
            if ($this->_canTranslateToJsonWhere($criteria)) {
                $usesSqlWhere = true;
                $params = [];
                $whereClause = $this->_buildJsonWhere($criteria, $params);
            } else {
                $usesCriteriaFunction = true;
            }
        } elseif (is_string($criteria) && !empty($criteria)) {
            $usesCriteriaFunction = true;
        }

        // Check index availability for the query fields
        $indexes = $this->getCollectionIndexes();
        $fieldsInCriteria = $this->extractFieldsFromCriteria($criteria);
        $usedIndexes = $this->findMatchingIndexes($fieldsInCriteria, $indexes);

        // Run EXPLAIN QUERY PLAN
        $explainResult = $this->runExplainQueryPlan($table, $whereClause, $criteria);

        // Count total and matching documents in a single conceptual pass
        // (SQLite has no single-query way to get both, but we avoid redundant work)
        $totalDocuments = $this->count();

        $matchedCount = ($criteria === null || (is_array($criteria) && empty($criteria)))
            ? $totalDocuments
            : $this->find($criteria)->count();

        $executionTime = (microtime(true) - $startTime) * 1000;

        // Estimate scanned documents from EXPLAIN output
        $estimatedScanned = $totalDocuments;
        foreach ($explainResult as $row) {
            $detail = $row['detail'] ?? '';
            if (str_contains($detail, 'USING INDEX') || str_contains($detail, 'SEARCH USING')) {
                $estimatedScanned = $matchedCount > 0 ? $matchedCount : 1;
                break;
            }
        }

        return [
            'query_plan' => [
                'strategy' => $usesSqlWhere ? 'sql_json_where' : ($usesCriteriaFunction ? 'criteria_function' : 'full_scan'),
                'uses_index' => !empty($usedIndexes),
                'indexes_available' => $indexes,
                'indexes_used' => $usedIndexes,
                'is_full_scan' => empty($usedIndexes) && !empty($criteria),
                'explain_output' => $explainResult,
            ],
            'performance' => [
                'execution_time_ms' => round($executionTime, 3),
                'total_documents' => $totalDocuments,
                'documents_scanned' => $estimatedScanned,
                'documents_matched' => $matchedCount,
                'scan_ratio' => $totalDocuments > 0
                    ? round($estimatedScanned / $totalDocuments * 100, 1)
                    : 0,
                'criteria_summary' => $this->summarizeCriteria($criteria),
            ],
            'suggestions' => $this->generateQuerySuggestions($fieldsInCriteria, $usedIndexes, $totalDocuments, $matchedCount),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function getCollectionIndexes(): array
    {
        try {
            $stmt = $this->database->queryExecutor->executeQuery(
                "SELECT name, sql FROM sqlite_master WHERE type='index' AND tbl_name = ? AND name NOT LIKE 'sqlite_%'",
                [$this->name]
            );
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (QueryExecutionException $e) {
            return [];
        }
    }

    /** @return array<int, string> */
    private function extractFieldsFromCriteria(mixed $criteria): array
    {
        if (!is_array($criteria)) {
            return [];
        }

        $fields = [];
        foreach ($criteria as $key => $value) {
            if (str_starts_with((string) $key, '$')) {
                // Logical operators like $and, $or - recurse
                if (is_array($value)) {
                    foreach ($value as $subCriteria) {
                        $fields = array_merge($fields, $this->extractFieldsFromCriteria($subCriteria));
                    }
                }
            } else {
                $fields[] = $key;
            }
        }

        return array_unique($fields);
    }

    /** @param array<int, string> $fields @param array<int, array<string, string>> $indexes @return array<int, string> */
    private function findMatchingIndexes(array $fields, array $indexes): array
    {
        $matching = [];

        foreach ($indexes as $index) {
            $sql = $index['sql'] ?? '';
            foreach ($fields as $field) {
                // Check if the index covers this field
                if (str_contains($sql, $field) || str_contains(strtolower($sql), strtolower($field))) {
                    $matching[] = $index['name'];
                    break;
                }
            }
        }

        return array_unique($matching);
    }

    /** @return array<int, array<string, string>> */
    private function runExplainQueryPlan(string $table, string $whereClause, mixed $criteria): array
    {
        try {
            $sql = "EXPLAIN QUERY PLAN SELECT document FROM {$table}";
            $params = [];

            if (!empty($whereClause)) {
                $sql .= " WHERE {$whereClause}";
            } elseif (is_string($criteria) && $criteria !== '') {
                $sql .= " WHERE document_criteria(?, document)";
                $params[] = $criteria;
            }

            $stmt = $this->database->queryExecutor->executeQuery($sql, $params);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (QueryExecutionException $e) {
            return [['error' => $e->getMessage()]];
        }
    }

    /**
     * Generate human-readable criteria summary.
     */
    private function summarizeCriteria(mixed $criteria): string
    {
        if ($criteria === null || (is_array($criteria) && empty($criteria))) {
            return 'all documents (no filter)';
        }

        if (is_string($criteria)) {
            return "string ID lookup: {$criteria}";
        }

        if (is_array($criteria)) {
            $fieldCount = count(array_filter(array_keys($criteria), fn($k) => !str_starts_with($k, '$')));
            $ops = array_filter(array_keys($criteria), fn($k) => str_starts_with($k, '$'));

            return sprintf(
                '%d field(s) queried: [%s]%s',
                $fieldCount,
                implode(', ', array_slice(array_filter(array_keys($criteria), fn($k) => !str_starts_with($k, '$')), 0, 5)),
                !empty($ops) ? ' (operators: ' . implode(', ', $ops) . ')' : ''
            );
        }

        return 'unknown criteria type';
    }

    /** @param array<int, string> $fields @param array<int, string> $usedIndexes @return array<int, string> */
    private function generateQuerySuggestions(array $fields, array $usedIndexes, int $totalDocs, int $matchedDocs): array
    {
        $suggestions = [];

        if (!empty($fields) && empty($usedIndexes) && $totalDocs > 1000) {
            foreach ($fields as $field) {
                $suggestions[] = "Consider creating an index on '{$field}' for better query performance: \$collection->createIndex('{$field}')";
            }
        }

        if ($totalDocs > 0 && $matchedDocs > 0) {
            $matchRatio = $matchedDocs / $totalDocs;
            if ($matchRatio > 0.5) {
                $suggestions[] = sprintf(
                    'Query matches %.1f%% of all documents (%d/%d). Consider adding more specific filters.',
                    $matchRatio * 100,
                    $matchedDocs,
                    $totalDocs
                );
            }
        }

        if ($totalDocs > 10000 && empty($usedIndexes)) {
            $suggestions[] = 'Collection has over 10,000 documents without indexes. Query performance may degrade as data grows.';
        }

        return $suggestions;
    }

    /* ================= CURSOR STREAMING (Prioritas 3) ================= */

    /**
     * Stream documents matching criteria using a PHP generator.
     *
     * Unlike toArray() which loads all documents into memory at once,
     * stream() yields documents one at a time, significantly reducing
     * memory usage for large result sets.
     *
     * @param  mixed  $criteria   Query criteria (same as find())
     * @param  array  $options    Options: ['sort' => array, 'limit' => int, 'skip' => int, 'projection' => array]
     * @return \Generator<int, array>
     *
     * @example
     *   foreach ($collection->stream(['status' => 'active'], ['sort' => ['created_at' => -1]]) as $doc) {
     *       processDocument($doc);
     *   }
     */
    /** @param array<string, mixed> $options @return \Generator<int, array<string, mixed>> */
    public function stream(mixed $criteria = null, array $options = []): \Generator
    {
        $cursor = $this->find($criteria, $options['projection'] ?? null);

        if (isset($options['sort'])) {
            $cursor->sort($options['sort']);
        }
        if (isset($options['skip'])) {
            $cursor->skip((int) $options['skip']);
        }
        if (isset($options['limit'])) {
            $cursor->limit((int) $options['limit']);
        }

        foreach ($cursor->getIterator() as $document) {
            yield $document;
        }
    }
}
