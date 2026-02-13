<?php

namespace BangronDB;

/**
 * QueryExecutor handles SQL query execution with enhanced features
 * including prepared statements, error handling, logging, and performance monitoring.
 */
class QueryExecutor
{
    /**
     * Database connection instance.
     */
    private \PDO $connection;

    /**
     * Query logging enabled flag.
     */
    private bool $loggingEnabled = false;

    /**
     * Query log storage.
     */
    private array $queryLog = [];

    /**
     * Performance monitoring flag.
     */
    private bool $performanceMonitoring = false;

    /**
     * Query execution statistics.
     */
    private array $queryStats = [];

    /**
     * Constructor.
     */
    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Enable or disable query logging.
     */
    public function setLogging(bool $enabled): self
    {
        $this->loggingEnabled = $enabled;

        return $this;
    }

    /**
     * Enable or disable performance monitoring.
     */
    public function setPerformanceMonitoring(bool $enabled): self
    {
        $this->performanceMonitoring = $enabled;

        return $this;
    }

    /**
     * Get query log.
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    /**
     * Get query execution statistics.
     */
    public function getQueryStats(): array
    {
        return $this->queryStats;
    }

    /**
     * Clear query log and statistics.
     */
    public function clearLogs(): void
    {
        $this->queryLog = [];
        $this->queryStats = [];
    }

    /**
     * Execute a SELECT query and return PDOStatement.
     *
     * @throws QueryExecutionException
     */
    public function executeQuery(string $sql, array $params = []): \PDOStatement
    {
        $startTime = $this->performanceMonitoring ? microtime(true) : 0;

        try {
            $stmt = $this->connection->prepare($sql);

            if (!$stmt) {
                throw new QueryExecutionException('Failed to prepare statement: '.implode(', ', $this->connection->errorInfo()), $sql, $params);
            }

            $this->bindParameters($stmt, $params);

            if (!$stmt->execute()) {
                throw new QueryExecutionException('Failed to execute query: '.implode(', ', $stmt->errorInfo()), $sql, $params);
            }

            if ($this->loggingEnabled || $this->performanceMonitoring) {
                $this->logQuery($sql, $params, $startTime);
            }

            return $stmt;
        } catch (\PDOException $e) {
            $this->logError($sql, $params, $e);
            throw new QueryExecutionException('Query execution failed: '.$e->getMessage(), $sql, $params, $e);
        }
    }

    /**
     * Execute a non-SELECT query (INSERT, UPDATE, DELETE) and return affected rows.
     *
     * @throws QueryExecutionException
     */
    public function executeUpdate(string $sql, array $params = []): int
    {
        $startTime = $this->performanceMonitoring ? microtime(true) : 0;

        try {
            $stmt = $this->connection->prepare($sql);

            if (!$stmt) {
                throw new QueryExecutionException('Failed to prepare statement: '.implode(', ', $this->connection->errorInfo()), $sql, $params);
            }

            $this->bindParameters($stmt, $params);
            $result = $stmt->execute();

            if (!$result) {
                throw new QueryExecutionException('Failed to execute update: '.implode(', ', $stmt->errorInfo()), $sql, $params);
            }

            $affectedRows = $stmt->rowCount();

            if ($this->loggingEnabled || $this->performanceMonitoring) {
                $this->logQuery($sql, $params, $startTime, $affectedRows);
            }

            return $affectedRows;
        } catch (\PDOException $e) {
            $this->logError($sql, $params, $e);
            throw new QueryExecutionException('Update execution failed: '.$e->getMessage(), $sql, $params, $e);
        }
    }

    /**
     * Execute a raw query without parameters (use with caution).
     *
     * @deprecated This method is deprecated and will be removed in a future version.
     *             Use executeQuery() with prepared statements instead to prevent SQL injection.
     *
     * @throws QueryExecutionException
     */
    public function executeRaw(string $sql): \PDOStatement|false
    {
        trigger_error('executeRaw() is deprecated. Use executeQuery() with prepared statements instead.', E_USER_DEPRECATED);

        $startTime = $this->performanceMonitoring ? microtime(true) : 0;

        try {
            $result = $this->connection->query($sql);

            if ($result === false) {
                throw new QueryExecutionException('Failed to execute raw query: '.implode(', ', $this->connection->errorInfo()), $sql);
            }

            if ($this->loggingEnabled || $this->performanceMonitoring) {
                $this->logQuery($sql, [], $startTime);
            }

            return $result;
        } catch (\PDOException $e) {
            $this->logError($sql, [], $e);
            throw new QueryExecutionException('Raw query execution failed: '.$e->getMessage(), $sql, [], $e);
        }
    }

    /**
     * Execute a raw update query without parameters (use with caution).
     *
     * @deprecated This method is deprecated and will be removed in a future version.
     *             Use executeUpdate() with prepared statements instead to prevent SQL injection.
     *
     * @throws QueryExecutionException
     */
    public function executeRawUpdate(string $sql): int
    {
        trigger_error('executeRawUpdate() is deprecated. Use executeUpdate() with prepared statements instead.', E_USER_DEPRECATED);

        $startTime = $this->performanceMonitoring ? microtime(true) : 0;

        try {
            $affectedRows = $this->connection->exec($sql);

            if ($affectedRows === false) {
                throw new QueryExecutionException('Failed to execute raw update: '.implode(', ', $this->connection->errorInfo()), $sql);
            }

            if ($this->loggingEnabled || $this->performanceMonitoring) {
                $this->logQuery($sql, [], $startTime, $affectedRows);
            }

            return $affectedRows;
        } catch (\PDOException $e) {
            $this->logError($sql, [], $e);
            throw new QueryExecutionException('Raw update execution failed: '.$e->getMessage(), $sql, [], $e);
        }
    }

    /**
     * Execute a raw update query without parameters (internal use only).
     *
     * This method is for internal library use only and does not trigger deprecation warnings.
     * Used for DDL statements like ALTER TABLE RENAME that cannot use prepared statements.
     *
     * @throws QueryExecutionException
     */
    public function executeRawUpdateInternal(string $sql): int
    {
        $startTime = $this->performanceMonitoring ? microtime(true) : 0;

        try {
            $affectedRows = $this->connection->exec($sql);

            if ($affectedRows === false) {
                throw new QueryExecutionException('Failed to execute raw update: '.implode(', ', $this->connection->errorInfo()), $sql);
            }

            if ($this->loggingEnabled || $this->performanceMonitoring) {
                $this->logQuery($sql, [], $startTime, $affectedRows);
            }

            return $affectedRows;
        } catch (\PDOException $e) {
            $this->logError($sql, [], $e);
            throw new QueryExecutionException('Raw update execution failed: '.$e->getMessage(), $sql, [], $e);
        }
    }

    /**
     * Execute multiple queries in a transaction.
     *
     * @param array<array{sql: string, params?: array}> $queries
     *
     * @throws QueryExecutionException
     */
    public function executeTransaction(array $queries): array
    {
        $results = [];
        $this->connection->beginTransaction();

        try {
            foreach ($queries as $query) {
                $sql = $query['sql'];
                $params = $query['params'] ?? [];

                if (stripos(trim($sql), 'SELECT') === 0) {
                    $stmt = $this->executeQuery($sql, $params);
                    $results[] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                } else {
                    $results[] = $this->executeUpdate($sql, $params);
                }
            }

            $this->connection->commit();

            return $results;
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * Get last insert ID.
     */
    public function getLastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Quote a string for safe SQL usage.
     */
    public function quote(string $string): string
    {
        return $this->connection->quote($string);
    }

    /**
     * Bind parameters to a prepared statement.
     */
    private function bindParameters(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $paramName = is_int($key) ? $key + 1 : $key;
            $paramType = $this->getParamType($value);
            $stmt->bindValue($paramName, $value, $paramType);
        }
    }

    /**
     * Get PDO parameter type for a value.
     */
    private function getParamType($value): int
    {
        if (is_int($value)) {
            return \PDO::PARAM_INT;
        } elseif (is_bool($value)) {
            return \PDO::PARAM_BOOL;
        } elseif (is_null($value)) {
            return \PDO::PARAM_NULL;
        } else {
            return \PDO::PARAM_STR;
        }
    }

    /**
     * Sensitive keys to filter from logs.
     */
    private const SENSITIVE_KEYS = ['encryption_key', 'password', 'secret', 'token', 'api_key', 'credential'];

    /**
     * Filter sensitive data from parameters before logging.
     */
    private function filterSensitiveParams(array $params): array
    {
        $filtered = [];
        foreach ($params as $key => $value) {
            $keyLower = is_string($key) ? strtolower($key) : $key;
            $isSensitive = false;

            if (is_string($key)) {
                foreach (self::SENSITIVE_KEYS as $sensitive) {
                    if (strpos($keyLower, $sensitive) !== false) {
                        $isSensitive = true;
                        break;
                    }
                }
            }

            $filtered[$key] = $isSensitive ? '[REDACTED]' : $value;
        }

        return $filtered;
    }

    /**
     * Log a successful query execution.
     */
    private function logQuery(string $sql, array $params, float $startTime, ?int $affectedRows = null): void
    {
        $logEntry = [
            'timestamp' => microtime(true),
            'sql' => $sql,
            'params' => $this->filterSensitiveParams($params),
            'execution_time' => $this->performanceMonitoring ? (microtime(true) - $startTime) * 1000 : null,
            'affected_rows' => $affectedRows,
            'type' => $this->getQueryType($sql),
        ];

        $this->queryLog[] = $logEntry;

        if ($this->performanceMonitoring) {
            $type = $logEntry['type'];
            if (!isset($this->queryStats[$type])) {
                $this->queryStats[$type] = [
                    'count' => 0,
                    'total_time' => 0,
                    'avg_time' => 0,
                    'min_time' => PHP_FLOAT_MAX,
                    'max_time' => 0,
                ];
            }

            ++$this->queryStats[$type]['count'];
            $this->queryStats[$type]['total_time'] += $logEntry['execution_time'];
            $this->queryStats[$type]['avg_time'] = $this->queryStats[$type]['total_time'] / $this->queryStats[$type]['count'];
            $this->queryStats[$type]['min_time'] = min($this->queryStats[$type]['min_time'], $logEntry['execution_time']);
            $this->queryStats[$type]['max_time'] = max($this->queryStats[$type]['max_time'], $logEntry['execution_time']);
        }
    }

    /**
     * Log a query error.
     */
    private function logError(string $sql, array $params, \Exception $e): void
    {
        if ($this->loggingEnabled) {
            $this->queryLog[] = [
                'timestamp' => microtime(true),
                'sql' => $sql,
                'params' => $this->filterSensitiveParams($params),
                'error' => $e->getMessage(),
                'type' => 'error',
            ];
        }
    }

    /**
     * Get query type from SQL string.
     */
    private function getQueryType(string $sql): string
    {
        $sql = trim(strtoupper($sql));

        if (strpos($sql, 'SELECT') === 0) {
            return 'SELECT';
        } elseif (strpos($sql, 'INSERT') === 0) {
            return 'INSERT';
        } elseif (strpos($sql, 'UPDATE') === 0) {
            return 'UPDATE';
        } elseif (strpos($sql, 'DELETE') === 0) {
            return 'DELETE';
        } elseif (strpos($sql, 'CREATE') === 0) {
            return 'CREATE';
        } elseif (strpos($sql, 'DROP') === 0) {
            return 'DROP';
        } elseif (strpos($sql, 'ALTER') === 0) {
            return 'ALTER';
        } else {
            return 'OTHER';
        }
    }

    /**
     * Sanitize SQL identifier (table/column name).
     */
    public function sanitizeIdentifier(string $identifier): string
    {
        // Remove any potentially dangerous characters
        $identifier = preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);

        // Ensure it's not empty and doesn't start with a number
        if (empty($identifier) || is_numeric($identifier[0])) {
            throw new \InvalidArgumentException('Invalid identifier: '.$identifier);
        }

        return $identifier;
    }

    /**
     * Create a safe table name by quoting it.
     */
    public function quoteTable(string $tableName): string
    {
        $sanitized = $this->sanitizeIdentifier($tableName);

        return "`{$sanitized}`";
    }

    /**
     * Check if a table exists.
     */
    public function tableExists(string $tableName): bool
    {
        try {
            $stmt = $this->executeQuery(
                "SELECT name FROM sqlite_master WHERE type='table' AND name = ?",
                [$tableName]
            );

            return $stmt->fetch() !== false;
        } catch (QueryExecutionException $e) {
            return false;
        }
    }
}

/**
 * Exception thrown when query execution fails.
 */
class QueryExecutionException extends \RuntimeException
{
    public string $sql;
    public array $params;

    public function __construct(string $message, string $sql, array $params = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->sql = $sql;
        $this->params = $params;
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function getParams(): array
    {
        return $this->params;
    }
}
