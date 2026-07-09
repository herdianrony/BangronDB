<?php

declare(strict_types=1);

namespace BangronDB\Traits;

/**
 * Trait for handling searchable fields in collections.
 * Allows indexing specific fields for fast querying on encrypted documents.
 */
trait SearchableFieldsTrait
{
    /**
     * Searchable fields configuration. Map of fieldName => ['hash' => bool]
     * When set, the collection will maintain `si_{field}` TEXT columns
     * containing the plain or hashed value to enable searching on encrypted docs.
     *
     * @var array<string,array{hash:bool}>
     */
    protected array $searchableFields = [];

    /**
     * Searchable Field Prefix.
     *
     * @var string
     */
    private static string $searchablePrefix = 'si_';

    /**
     * Compute the deterministic search-index hash for a (normalized) value.
     *
     * SECURITY: a plain SHA-256 of a low-entropy value such as an email address
     * is brute-forceable / rainbow-tableable if the .bangron file leaks, and
     * lets the same value be correlated across databases. When an encryption key
     * is available we therefore use a KEYED HMAC-SHA256 ("blind index"): a
     * dedicated search key is derived from the encryption key via PBKDF2 with a
     * distinct salt so it is domain-separated from the data-encryption key.
     * Without that key, an attacker with only the file cannot brute-force the
     * index.
     *
     * Backward compatibility: collections with NO encryption key keep the
     * legacy unkeyed SHA-256 (those values were never secret anyway). Existing
     * encrypted data hashed with the old scheme can be migrated with
     * rehashSearchableField().
     */
    protected function hashSearchableValue(string $normalized): string
    {
        $key = $this->encryptionKey ?? ($this->database->getEncryptionKey() ?? null);
        if (empty($key)) {
            // No secret available — legacy plain hash (non-encrypted collection).
            return hash('sha256', $normalized);
        }

        $searchKey = $this->getSearchIndexKey($key);

        return hash_hmac('sha256', $normalized, $searchKey);
    }

    /**
     * Derive (and cache) the dedicated HMAC key for blind-index hashing.
     * Uses PBKDF2 over the encryption key with a search-specific salt so it is
     * independent of the data-encryption key derivation.
     *
     * @var array<string,string>
     */
    private static array $searchKeyCache = [];

    private function getSearchIndexKey(string $key): string
    {
        $baseSalt = isset($this->database) ? $this->database->getEncryptionSalt() : 'bangrondb_encryption_salt';
        $salt = 'searchindex:' . $baseSalt;
        $cacheKey = hash('sha256', $key . "\0" . $salt);

        if (isset(self::$searchKeyCache[$cacheKey])) {
            return self::$searchKeyCache[$cacheKey];
        }

        $derived = hash_pbkdf2('sha256', $key, $salt, 100000, 32, true);

        if (count(self::$searchKeyCache) >= 16) {
            array_shift(self::$searchKeyCache);
        }
        self::$searchKeyCache[$cacheKey] = $derived;

        return $derived;
    }

    /**
     * Get searchable fields configuration.
     */
    public function getSearchableFields(): array
    {
        return $this->searchableFields;
    }

    /**
     * Configure searchable fields. Each field will be stored into a dedicated
     * `si_{field}` TEXT column. If $hash is true the stored value will be
     * a hex SHA-256 of the string (useful for privacy-preserving search).
     */
    public function setSearchableFields(array $fields, bool $hash = false): self
    {
        $this->searchableFields = [];
        foreach ($fields as $key => $value) {
            if (is_int($key)) {
                // Flat array: ['field1', 'field2']
                $fieldName = (string) $value;
                \BangronDB\Security\FieldValidator::validateFieldName($fieldName);
                $this->searchableFields[$fieldName] = ['hash' => $hash];
            } else {
                // Associative array: ['field1' => ['hash' => true]]
                $fieldName = (string) $key;
                \BangronDB\Security\FieldValidator::validateFieldName($fieldName);
                $this->searchableFields[$fieldName] = [
                    'hash' => (bool) ($value['hash'] ?? $value),
                ];
            }
        }

        $this->ensureSearchableColumnsExist();

        return $this;
    }

    /**
     * Remove a searchable field configuration. If $dropColumn is true the
     * method will attempt to remove the physical `si_{field}` column from
     * the SQLite table by rebuilding the table without that column.
     */
    public function removeSearchableField(string $field, bool $dropColumn = false): self
    {
        \BangronDB\Security\FieldValidator::validateFieldName($field);

        if (isset($this->searchableFields[$field])) {
            unset($this->searchableFields[$field]);
        }

        if ($dropColumn) {
            $this->dropSearchableColumn($field);
        }

        return $this;
    }

    /**
     * Build the physical column name for a searchable field.
     */
    protected function buildSearchableColumnName(string $field): string
    {
        \BangronDB\Security\FieldValidator::validateFieldName($field);

        return self::$searchablePrefix . $field;
    }

    /**
     * Drop a searchable column from the database table.
     */
    private function dropSearchableColumn(string $field): void
    {
        $col = $this->buildSearchableColumnName($field);
        $table = $this->database->quoteIdentifier($this->name);
        // Check if column exists
        $stmt = $this->database->connection->query("PRAGMA table_info({$table})");
        $cols = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        $existing = [];
        foreach ($cols as $c) {
            $existing[$c['name']] = $c;
        }

        if (isset($existing[$col])) {
            // SQLite has no DROP COLUMN; perform a safe table rebuild
            $colsToKeep = [];
            foreach ($cols as $c) {
                if ($c['name'] === $col) {
                    continue;
                }
                $colsToKeep[] = $c['name'];
            }

            $colsList = implode(', ', array_map(function ($n) {
                return "`{$n}`";
            }, $colsToKeep));

            $tmp = $this->name . '_tmp_' . bin2hex(random_bytes(8));
            $quotedTmp = $this->database->quoteIdentifier($tmp);
            // Create temp table with only the kept columns
            $createCols = [];
            foreach ($cols as $c) {
                if ($c['name'] === $col) {
                    continue;
                }
                $def = "`{$c['name']}` {$c['type']}";
                if ($c['notnull']) {
                    $def .= ' NOT NULL';
                }
                if ($c['pk']) {
                    $def .= ' PRIMARY KEY';
                    if ($c['name'] === 'id' && \strtoupper($c['type']) === 'INTEGER') {
                        $def .= ' AUTOINCREMENT';
                    }
                }
                $createCols[] = $def;
            }

            $this->database->connection->beginTransaction();
            try {
                $this->database->connection->exec("CREATE TABLE {$quotedTmp} (" . implode(',', $createCols) . ')');
                $this->database->connection->exec("INSERT INTO {$quotedTmp} ({$colsList}) SELECT {$colsList} FROM {$table}");
                $this->database->connection->exec("DROP TABLE {$table}");
                $this->database->connection->exec("ALTER TABLE {$quotedTmp} RENAME TO {$table}");
                $this->database->connection->commit();
            } catch (\Throwable $e) {
                if ($this->database->connection->inTransaction()) {
                    $this->database->connection->rollBack();
                }
                throw $e;
            }
        }
    }

    /**
     * Ensure searchable columns exist in the database table.
     */
    protected function ensureSearchableColumnsExist(): void
    {
        if (empty($this->searchableFields)) {
            return;
        }

        // Ensure table exists without instantiating/reloading the collection object
        $this->database->ensureCollectionTable($this->name);

        $table = $this->database->quoteIdentifier($this->name);

        $stmt = $this->database->connection->query("PRAGMA table_info({$table})");
        $cols = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        $existing = [];
        foreach ($cols as $c) {
            $existing[$c['name']] = true;
        }

        foreach ($this->searchableFields as $field => $cfg) {
            $col = $this->buildSearchableColumnName($field);
            if (!isset($existing[$col])) {
                $quotedColumn = '`' . str_replace('`', '``', $col) . '`';
                $this->database->connection->exec("ALTER TABLE {$table} ADD COLUMN {$quotedColumn} TEXT NULL");
            }
        }
    }

    /**
     * Compute the map of searchable column => value for a given document.
     */
    protected function _computeSearchIndexValues(array $doc): array
    {
        $out = [];
        if (empty($this->searchableFields)) {
            return $out;
        }

        foreach ($this->searchableFields as $field => $cfg) {
            // support dot notation for nested fields
            $parts = explode('.', $field);
            $ref = $doc;
            foreach ($parts as $p) {
                if (!is_array($ref) || !array_key_exists($p, $ref)) {
                    $ref = null;
                    break;
                }
                $ref = $ref[$p];
            }

            if ($ref === null) {
                $val = null;
            } elseif (is_array($ref)) {
                // join arrays into comma separated string
                $val = implode(',', array_map('strval', $ref));
            } else {
                $val = strtolower((string) $ref);
            }

            if ($val !== null) {
                if ($cfg['hash']) {
                    $val = $this->hashSearchableValue($val);
                }
            }

            $out[$this->buildSearchableColumnName($field)] = $val;
        }

        return $out;
    }

    /**
     * Get the searchable prefix constant.
     */
    protected function getSearchablePrefix(): string
    {
        return self::$searchablePrefix;
    }

    /**
     * Recompute the search-index column for one hashed field across all rows.
     *
     * Use this to migrate data that was previously indexed with the legacy
     * unkeyed SHA-256 to the keyed HMAC blind index (after an encryption key was
     * configured). It re-reads each document, recomputes the index value with
     * the current scheme, and updates the si_{field} column.
     *
     * @return int Number of rows updated.
     */
    public function rehashSearchableField(string $field): int
    {
        \BangronDB\Security\FieldValidator::validateFieldName($field);
        if (!isset($this->searchableFields[$field]) || !$this->searchableFields[$field]['hash']) {
            return 0;
        }

        $table = $this->database->quoteIdentifier($this->name);
        $col = '`' . str_replace('`', '``', $this->buildSearchableColumnName($field)) . '`';
        $pdo = $this->database->connection;

        $rows = $pdo->query("SELECT id, document FROM {$table}")->fetchAll(\PDO::FETCH_ASSOC);
        $update = $pdo->prepare("UPDATE {$table} SET {$col} = ? WHERE id = ?");

        $count = 0;
        foreach ($rows as $row) {
            $doc = $this->decodeStored((string) $row['document']);
            if (!is_array($doc)) {
                continue;
            }
            $values = $this->_computeSearchIndexValues($doc);
            $newVal = $values[$this->buildSearchableColumnName($field)] ?? null;
            $update->execute([$newVal, $row['id']]);
            $count++;
        }

        return $count;
    }
}
