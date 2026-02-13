<?php

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
                'encryption_enabled' => $this->db->encryptionKey !== null,
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
                $stmt = $this->db->connection->query("SELECT COUNT(*) as count, SUM(LENGTH(document)) as size FROM `{$collectionName}`");
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
            $metrics['fragmentation_ratio'] = (int) $freelistCount > 0 ? round((int) $freelistCount / (int) $pageStats, 4) : 0;
        } catch (\Exception $e) {
            $metrics['page_stats_error'] = $e->getMessage();
        }

        $metrics['indexes'] = $this->getIndexMetrics();

        try {
            $cacheSize = $this->db->connection->query('PRAGMA cache_size')->fetch(\PDO::FETCH_COLUMN);
            $metrics['cache_size_pages'] = (int) $cacheSize;
        } catch (\Exception $e) {
        }

        return $metrics;
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
                $stmt = $this->db->connection->query("SELECT SUM(LENGTH(document)) as size FROM `{$name}`");
                $size = $stmt ? (int) $stmt->fetch(\PDO::FETCH_COLUMN) : 0;
            } catch (\Exception $e) {
                // Skip if table doesn't have document column (like system tables)
            }

            $indexes = [];
            try {
                $idxStmt = $this->db->connection->query("
                    SELECT name FROM sqlite_master
                    WHERE type='index' AND tbl_name='{$name}' AND name NOT LIKE 'sqlite_%'
                ");
                $indexes = $idxStmt ? $idxStmt->fetchAll(\PDO::FETCH_COLUMN) : [];
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
