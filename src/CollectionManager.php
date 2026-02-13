<?php

namespace BangronDB;

/**
 * CollectionManager handles collection metadata and configuration management.
 *
 * This class provides a higher-level interface for managing collection configurations,
 * metadata tracking, and caching for improved performance.
 */
class CollectionManager
{
    private Database $database;
    private array $configCache = [];
    private array $metadataCache = [];
    private bool $cacheEnabled = true;

    /**
     * Constructor.
     */
    public function __construct(Database $database)
    {
        $this->database = $database;
        $this->initializeCaches();
    }

    /**
     * Initialize caches with existing data.
     */
    private function initializeCaches(): void
    {
        if ($this->cacheEnabled) {
            $this->configCache = $this->database->getAllCollectionConfigs();
            $this->metadataCache = $this->loadAllMetadata();
        }
    }

    /**
     * Enable or disable caching.
     */
    public function setCacheEnabled(bool $enabled): void
    {
        $this->cacheEnabled = $enabled;
        if ($enabled && empty($this->configCache)) {
            $this->initializeCaches();
        } elseif (!$enabled) {
            $this->configCache = [];
            $this->metadataCache = [];
        }
    }

    /**
     * Check if caching is enabled.
     */
    public function isCacheEnabled(): bool
    {
        return $this->cacheEnabled;
    }

    /**
     * Save collection configuration.
     */
    public function saveCollectionConfig(string $collectionName, array $config): void
    {
        $this->validateCollectionConfig($config);

        // Ensure timestamps are set
        $existingConfig = $this->database->loadCollectionConfig($collectionName);
        if (empty($existingConfig)) {
            $config['created_at'] = $config['created_at'] ?? time();
        } else {
            $config['created_at'] = $existingConfig['created_at'] ?? time();
        }
        $config['updated_at'] = time();

        $this->database->saveCollectionConfig($collectionName, $config);

        if ($this->cacheEnabled) {
            $this->configCache[$collectionName] = $config;
        }
    }

    /**
     * Load collection configuration.
     */
    public function loadCollectionConfig(string $collectionName): array
    {
        if ($this->cacheEnabled && isset($this->configCache[$collectionName])) {
            return $this->configCache[$collectionName];
        }

        $config = $this->database->loadCollectionConfig($collectionName);

        if ($this->cacheEnabled) {
            $this->configCache[$collectionName] = $config;
        }

        return $config;
    }

    /**
     * Get all collection configurations.
     */
    public function getAllCollectionConfigs(): array
    {
        if ($this->cacheEnabled && !empty($this->configCache)) {
            return $this->configCache;
        }

        $configs = $this->database->getAllCollectionConfigs();

        if ($this->cacheEnabled) {
            $this->configCache = $configs;
        }

        return $configs;
    }

    /**
     * Delete collection configuration.
     */
    public function deleteCollectionConfig(string $collectionName): void
    {
        $this->database->deleteCollectionConfig($collectionName);

        if ($this->cacheEnabled) {
            unset($this->configCache[$collectionName]);
            unset($this->metadataCache[$collectionName]);
        }
    }

    /**
     * Update collection metadata.
     */
    public function updateMetadata(string $collectionName, array $metadata = []): void
    {
        try {
            // First, check if metadata exists directly from database to avoid cache issues
            $stmt = $this->database->queryExecutor->executeQuery(
                "SELECT id, document FROM _meta WHERE json_extract(document, '$._id') = ?",
                [$collectionName]
            );
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            $currentVersion = 0;
            if ($existing) {
                $doc = json_decode($existing['document'], true);
                $currentVersion = $doc['version'] ?? 0;
            }
            $newVersion = $currentVersion + 1;

            $document = json_encode([
                '_id' => $collectionName,
                'version' => $newVersion,
                'last_updated' => date('c'),
            ]);

            if ($existing) {
                $this->database->queryExecutor->executeUpdate(
                    'UPDATE _meta SET document = ? WHERE id = ?',
                    [$document, $existing['id']]
                );
            } else {
                $this->database->queryExecutor->executeUpdate(
                    'INSERT INTO _meta (document) VALUES (?)',
                    [$document]
                );
            }

            // Clear cache for this collection to force refresh
            if ($this->cacheEnabled) {
                unset($this->metadataCache[$collectionName]);
            }
        } catch (QueryExecutionException $e) {
            // Silently fail if metadata table isn't ready or other DB issues
            error_log("Failed to update metadata for collection {$collectionName}: ".$e->getMessage());
        }
    }

    /**
     * Get collection metadata.
     */
    public function getMetadata(string $collectionName): array
    {
        if ($this->cacheEnabled && isset($this->metadataCache[$collectionName])) {
            return $this->metadataCache[$collectionName];
        }

        try {
            $stmt = $this->database->queryExecutor->executeQuery(
                "SELECT document FROM _meta WHERE json_extract(document, '$._id') = ?",
                [$collectionName]
            );
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$result) {
                $metadata = ['version' => 0, 'last_updated' => null];
            } else {
                $document = json_decode($result['document'], true);
                $metadata = [
                    'version' => $document['version'] ?? 0,
                    'last_updated' => $document['last_updated'] ?? null,
                ];
            }

            if ($this->cacheEnabled) {
                $this->metadataCache[$collectionName] = $metadata;
            }

            return $metadata;
        } catch (QueryExecutionException $e) {
            return ['version' => 0, 'last_updated' => null];
        }
    }

    /**
     * Get all collection metadata.
     */
    public function getAllMetadata(): array
    {
        // Always load fresh metadata to ensure accuracy
        $metadata = $this->loadAllMetadata();

        if ($this->cacheEnabled) {
            $this->metadataCache = $metadata;
        }

        return $metadata;
    }

    /**
     * Load all metadata from database.
     */
    private function loadAllMetadata(): array
    {
        try {
            $stmt = $this->database->queryExecutor->executeQuery('SELECT document FROM _meta');
            $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

            $metadata = [];
            foreach ($rows as $row) {
                $document = json_decode($row['document'], true);
                if ($document && isset($document['_id'])) {
                    $metadata[$document['_id']] = [
                        'version' => $document['version'] ?? 0,
                        'last_updated' => $document['last_updated'] ?? null,
                    ];
                }
            }

            return $metadata;
        } catch (QueryExecutionException $e) {
            return [];
        }
    }

    /**
     * Validate collection configuration.
     */
    private function validateCollectionConfig(array $config): void
    {
        $validKeys = [
            'id_mode', 'encryption_key', 'searchable_fields', 'schema',
            'soft_deletes_enabled', 'deleted_at_field', 'created_at', 'updated_at',
        ];

        foreach (array_keys($config) as $key) {
            if (!in_array($key, $validKeys)) {
                throw new \InvalidArgumentException("Invalid configuration key: {$key}");
            }
        }

        // Validate id_mode
        if (isset($config['id_mode'])) {
            $validIdModes = ['auto', 'manual', 'prefix'];
            if (!in_array($config['id_mode'], $validIdModes)
                && !preg_match('/^prefix:/', $config['id_mode'])) {
                throw new \InvalidArgumentException("Invalid id_mode: {$config['id_mode']}");
            }
        }

        // Validate searchable_fields
        if (isset($config['searchable_fields']) && !is_array($config['searchable_fields'])) {
            throw new \InvalidArgumentException('searchable_fields must be an array');
        }

        // Validate schema
        if (isset($config['schema']) && !is_array($config['schema'])) {
            throw new \InvalidArgumentException('schema must be an array');
        }

        // Validate boolean fields
        if (isset($config['soft_deletes_enabled']) && !is_bool($config['soft_deletes_enabled'])) {
            throw new \InvalidArgumentException('soft_deletes_enabled must be a boolean');
        }
    }

    /**
     * Clear all caches.
     */
    public function clearCaches(): void
    {
        $this->configCache = [];
        $this->metadataCache = [];
    }

    /**
     * Get collection statistics including config and metadata.
     */
    public function getCollectionStats(string $collectionName): array
    {
        return [
            'config' => $this->loadCollectionConfig($collectionName),
            'metadata' => $this->getMetadata($collectionName),
            'exists' => in_array($collectionName, $this->database->getCollectionNames()),
        ];
    }

    /**
     * Get statistics for all collections.
     */
    public function getAllCollectionStats(): array
    {
        $collectionNames = $this->database->getCollectionNames();
        $stats = [];

        foreach ($collectionNames as $name) {
            $stats[$name] = $this->getCollectionStats($name);
        }

        return $stats;
    }

    /**
     * Check if a collection has been modified since a given timestamp.
     */
    public function isModifiedSince(string $collectionName, int $timestamp): bool
    {
        $metadata = $this->getMetadata($collectionName);

        if (!$metadata['last_updated']) {
            return true; // If no last_updated, consider it modified
        }

        // SQLite CURRENT_TIMESTAMP returns format like '2026-01-22 12:51:41'
        if (is_string($metadata['last_updated'])) {
            $lastUpdatedTime = strtotime($metadata['last_updated']);
        } else {
            $lastUpdatedTime = (int) $metadata['last_updated'];
        }

        return $lastUpdatedTime > $timestamp;
    }

    /**
     * Get collections modified since a given timestamp.
     */
    public function getModifiedSince(int $timestamp): array
    {
        $allMetadata = $this->getAllMetadata();
        $modified = [];

        foreach ($allMetadata as $collectionName => $metadata) {
            if (!$metadata['last_updated']) {
                $modified[$collectionName] = $metadata; // No last_updated means modified
                continue;
            }

            // SQLite CURRENT_TIMESTAMP returns format like '2026-01-22 12:51:41'
            if (is_string($metadata['last_updated'])) {
                $lastUpdatedTime = strtotime($metadata['last_updated']);
            } else {
                $lastUpdatedTime = (int) $metadata['last_updated'];
            }

            if ($lastUpdatedTime > $timestamp) {
                $modified[$collectionName] = $metadata;
            }
        }

        return $modified;
    }
}
