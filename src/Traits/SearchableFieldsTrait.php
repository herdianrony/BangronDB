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
                    $val = hash('sha256', $val);
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
}
