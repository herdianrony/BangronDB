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
            'schema' => $this->schema,
            'searchableFields' => array_keys($this->searchableFields),
            'hooks' => array_map('count', $this->hooks),
        ];
    }

    public function createIndex(string $field, ?string $indexName = null): void
    {
        $this->database->createJsonIndex($this->name, $field, $indexName);
    }
}
