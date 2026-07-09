<?php

declare(strict_types=1);

namespace BangronDB;

/**
 * Helper class for Database health metrics and integrity checks.
 */
class DatabaseMetrics
{
    protected Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Get database health and metrics information.
     */
    public function getHealthMetrics(): array
    {
        return [
            'database' => [
                'path' => $this->db->path,
                'type' => $this->db->path === Database::DSN_PATH_MEMORY ? 'memory' : 'file',
                'encryption_enabled' => $this->db->isEncryptionEnabled(),
            ],
            'integrity' => $this->checkIntegrity(),
            'metrics' => $this->getDataMetrics(),
            'performance' => $this->getPerformanceMetrics(),
            'collections' => $this->getCollectionMetrics(),
        ];
    }

    /**
     * Check database integrity using SQLite's PRAGMA integrity_check.
     */
    public function checkIntegrity(): array
    {
        try {
            $stmt = $this->db->connection->query('PRAGMA integrity_check');
            $result = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            return [
                'status' => $result[0] === 'ok' ? 'healthy' : 'corrupted',
                'details' => $result,
                'checked_at' => time(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'checked_at' => time(),
            ];
        }
    }

    /**
     * Get comprehensive data metrics for the database.
     */
    public function getDataMetrics(): array
    {
        $collections = $this->db->getCollectionNames();
        $totalDocuments = 0;
        $totalSize = 0;
        $collectionStats = [];

        foreach ($collections as $collectionName) {
            $collection = $this->db->selectCollection($collectionName);
            $count = $collection->count();

            // Estimate size (rough calculation)
            $size = 0;
            try {
                $quoted = $this->db->quoteIdentifier($collectionName);
                $stmt = $this->db->connection->query("SELECT COUNT(*) as count, SUM(LENGTH(document)) as size FROM {$quoted}");
                $stats = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
                $size = (int) ($stats['size'] ?? 0);
            } catch (\Exception $e) {
                // Skip if table doesn't have document column (like system tables)
                $size = 0;
            }

            $collectionStats[$collectionName] = [
                'documents' => $count,
                'size_bytes' => $size,
                'avg_document_size' => $count > 0 ? round($size / $count, 2) : 0,
            ];

            $totalDocuments += $count;
            $totalSize += $size;
        }

        return [
            'total_collections' => count($collections),
            'total_documents' => $totalDocuments,
            'total_size_bytes' => $totalSize,
            'avg_document_size' => $totalDocuments > 0 ? round($totalSize / $totalDocuments, 2) : 0,
            'collections' => $collectionStats,
            'last_updated' => time(),
        ];
    }

    /**
     * Get performance metrics for the database.
     */
    public function getPerformanceMetrics(): array
    {
        $metrics = [];

        if ($this->db->path !== Database::DSN_PATH_MEMORY && file_exists($this->db->path)) {
            $metrics['file_size_bytes'] = filesize($this->db->path);
        }

        try {
            $pageStats = $this->db->connection->query('PRAGMA page_count')->fetch(\PDO::FETCH_COLUMN);
            $pageSize = $this->db->connection->query('PRAGMA page_size')->fetch(\PDO::FETCH_COLUMN);
            $freelistCount = $this->db->connection->query('PRAGMA freelist_count')->fetch(\PDO::FETCH_COLUMN);

            $metrics['page_count'] = (int) $pageStats;
            $metrics['page_size'] = (int) $pageSize;
            $metrics['total_pages_bytes'] = (int) $pageStats * (int) $pageSize;
            $metrics['freelist_count'] = (int) $freelistCount;
            $metrics['fragmentation_ratio'] = (int) $pageStats > 0 ? round((int) $freelistCount / (int) $pageStats, 4) : 0;

            // Prioritas 5: Detailed fragmentation analysis
            $metrics['fragmentation'] = $this->analyzeFragmentation(
                (int) $pageStats,
                (int) $pageSize,
                (int) $freelistCount,
                $metrics['file_size_bytes'] ?? null
            );
        } catch (\Exception $e) {
            $metrics['page_stats_error'] = $e->getMessage();
        }

        $metrics['indexes'] = $this->getIndexMetrics();

        try {
            $cacheSize = $this->db->connection->query('PRAGMA cache_size')->fetch(\PDO::FETCH_COLUMN);
            $metrics['cache_size_pages'] = (int) $cacheSize;
        } catch (\Exception $e) {
        }

        // Prioritas 5: Query time analysis from QueryExecutor stats
        if ($this->db->queryExecutor !== null) {
            $metrics['query_stats'] = $this->db->queryExecutor->getQueryStats();
        }

        return $metrics;
    }

    /**
     * Analyze database fragmentation and provide VACUUM recommendations.
     *
     * Part of Prioritas 5 from the BangronDB roadmap.
     *
     * @return array{level: string, ratio: float, wasted_bytes: int, vacuum_recommended: bool, vacuum_savings_estimate_bytes: int, auto_vacuum_mode: string}
     */
    private function analyzeFragmentation(int $pageCount, int $pageSize, int $freelistCount, ?int $fileSize): array
    {
        $ratio = $pageCount > 0 ? round($freelistCount / $pageCount, 4) : 0;
        $wastedBytes = $freelistCount * $pageSize;

        // Determine fragmentation level
        $level = 'none';
        if ($ratio > 0.2) {
            $level = 'high';
        } elseif ($ratio > 0.1) {
            $level = 'moderate';
        } elseif ($ratio > 0.02) {
            $level = 'low';
        }

        // Estimate VACUUM savings (freelist pages + page overhead)
        $vacuumSavings = $wastedBytes;
        if ($fileSize !== null && $fileSize > 0) {
            // Account for additional btree overhead that VACUUM can reclaim
            $vacuumSavings = (int) ($wastedBytes * 1.2);
        }

        // Check auto_vacuum mode
        $autoVacuum = 'NONE';
        try {
            $autoVacuumResult = $this->db->connection->query('PRAGMA auto_vacuum')->fetch(\PDO::FETCH_COLUMN);
            if ($autoVacuumResult !== false) {
                $autoVacuumMap = [0 => 'NONE', 1 => 'FULL', 2 => 'INCREMENTAL'];
                $autoVacuum = $autoVacuumMap[(int) $autoVacuumResult] ?? 'UNKNOWN';
            }
        } catch (\Exception $e) {
        }

        return [
            'level' => $level,
            'ratio' => $ratio,
            'wasted_bytes' => $wastedBytes,
            'wasted_pages' => $freelistCount,
            'vacuum_recommended' => $ratio > 0.05,
            'vacuum_savings_estimate_bytes' => $vacuumSavings,
            'auto_vacuum_mode' => $autoVacuum,
            'incremental_vacuum_hint' => $autoVacuum === 'INCREMENTAL'
                ? 'Run PRAGMA incremental_vacuum to reclaim freelist pages without full VACUUM lock.'
                : null,
        ];
    }

    /**
     * Run VACUUM on the database to reclaim wasted space.
     *
     * WARNING: VACUUM requires an exclusive lock. Do not run during peak traffic.
     * For INCREMENTAL auto_vacuum mode, consider incrementalVacuum() instead.
     *
     * @return array{success: bool, bytes_before: int|null, bytes_after: int|null, bytes_reclaimed: int|null}
     */
    public function vacuum(): array
    {
        $sizeBefore = null;
        $sizeAfter = null;

        if ($this->db->path !== Database::DSN_PATH_MEMORY && file_exists($this->db->path)) {
            $sizeBefore = filesize($this->db->path);
        }

        try {
            $this->db->connection->exec('VACUUM');
        } catch (\Exception $e) {
            return [
                'success' => false,
                'bytes_before' => $sizeBefore,
                'bytes_after' => null,
                'bytes_reclaimed' => null,
                'error' => $e->getMessage(),
            ];
        }

        if ($this->db->path !== Database::DSN_PATH_MEMORY && file_exists($this->db->path)) {
            $sizeAfter = filesize($this->db->path);
        }

        return [
            'success' => true,
            'bytes_before' => $sizeBefore,
            'bytes_after' => $sizeAfter,
            'bytes_reclaimed' => ($sizeBefore !== null && $sizeAfter !== null) ? $sizeBefore - $sizeAfter : null,
        ];
    }

    /**
     * Run incremental VACUUM to reclaim freelist pages.
     *
     * Safer than full VACUUM as it doesn't require an exclusive lock
     * and can be run during normal operation. Only works when
     * auto_vacuum is set to INCREMENTAL.
     *
     * @return array{success: bool, pages_reclaimed: int}
     */
    public function incrementalVacuum(): array
    {
        $freelistBefore = 0;
        $freelistAfter = 0;

        try {
            $freelistBefore = (int) $this->db->connection->query('PRAGMA freelist_count')->fetch(\PDO::FETCH_COLUMN);
            $this->db->connection->exec('PRAGMA incremental_vacuum');
            $freelistAfter = (int) $this->db->connection->query('PRAGMA freelist_count')->fetch(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'pages_reclaimed' => 0,
                'error' => $e->getMessage(),
            ];
        }

        return [
            'success' => true,
            'pages_reclaimed' => max(0, $freelistBefore - $freelistAfter),
            'freelist_before' => $freelistBefore,
            'freelist_after' => $freelistAfter,
        ];
    }

    /**
     * Get index metrics for the database.
     */
    public function getIndexMetrics(): array
    {
        $indexes = [];

        try {
            $stmt = $this->db->connection->query("
                SELECT name, tbl_name, sql
                FROM sqlite_master
                WHERE type='index' AND name NOT LIKE 'sqlite_%'
            ");
            $indexList = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($indexList as $index) {
                $indexName = $index['name'];
                $tableName = $index['tbl_name'];

                $indexes[$indexName] = [
                    'table' => $tableName,
                    'type' => strpos($indexName, 'idx_') === 0 ? 'json_index' : 'custom_index',
                    'definition' => $index['sql'],
                ];
            }
        } catch (\Exception $e) {
            $indexes['error'] = $e->getMessage();
        }

        return $indexes;
    }

    /**
     * Get detailed metrics for each collection.
     */
    public function getCollectionMetrics(): array
    {
        $collections = [];

        foreach ($this->db->getCollectionNames() as $name) {
            $collection = $this->db->selectCollection($name);
            $count = $collection->count();

            $size = 0;
            try {
                $quotedName = $this->db->quoteIdentifier($name);
                $stmt = $this->db->connection->query("SELECT SUM(LENGTH(document)) as size FROM {$quotedName}");
                $size = $stmt ? (int) $stmt->fetch(\PDO::FETCH_COLUMN) : 0;
            } catch (\Exception $e) {
                // Skip if table doesn't have document column (like system tables)
            }

            $indexes = [];
            try {
                $idxStmt = $this->db->queryExecutor->executeQuery(
                    "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name = ? AND name NOT LIKE 'sqlite_%'",
                    [$name]
                );
                $indexes = $idxStmt->fetchAll(\PDO::FETCH_COLUMN);
            } catch (\Exception $e) {
            }

            $collections[$name] = [
                'documents' => $count,
                'size_bytes' => $size,
                'indexes' => $indexes,
                'index_count' => count($indexes),
                'hooks' => $this->getHookCounts($collection),
                'encryption_enabled' => $collection->isEncrypted(),
                'id_mode' => $collection->getIdMode(),
                'searchable_fields' => array_keys($collection->getSearchableFields()),
            ];
        }

        return $collections;
    }

    private function getHookCounts($collection): array
    {
        $hooks = $collection->getHooks();
        $events = ['beforeInsert', 'afterInsert', 'beforeUpdate', 'afterUpdate', 'beforeRemove', 'afterRemove'];
        $counts = [];
        foreach ($events as $event) {
            $counts[$event] = isset($hooks[$event]) ? count($hooks[$event]) : 0;
        }

        return $counts;
    }
}
