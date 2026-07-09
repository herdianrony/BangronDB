<?php

declare(strict_types=1);

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
            // $metadata is reserved for future expansion; current behavior is version bump + timestamp refresh.
            $this->database->touchCollectionMetadata($collectionName);

            // Clear cache for this collection to force refresh
            if ($this->cacheEnabled) {
                unset($this->metadataCache[$collectionName]);
            }
        } catch (QueryExecutionException | \RuntimeException | \InvalidArgumentException $e) {
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
            $metadata = $this->database->getCollectionMetadata($collectionName);

            if ($this->cacheEnabled) {
                $this->metadataCache[$collectionName] = $metadata;
            }

            return $metadata;
        } catch (QueryExecutionException | \RuntimeException | \InvalidArgumentException $e) {
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
            return $this->database->getAllCollectionMetadata();
        } catch (QueryExecutionException | \RuntimeException $e) {
            return [];
        }
    }

    /**
     * Validate collection configuration.
     */
    private function validateCollectionConfig(array $config): void
    {
        $validKeys = [
            'id_mode', 'searchable_fields', 'schema',
            'soft_deletes_enabled', 'deleted_at_field', 'created_at', 'updated_at',
            'custom_config', 'encryption_enabled', 'encryption_key_version',
        ];

        foreach (array_keys($config) as $key) {
            if (!in_array($key, $validKeys, true)) {
                throw new \InvalidArgumentException("Invalid configuration key: {$key}");
            }
        }

        // Validate id_mode
        if (isset($config['id_mode'])) {
            if (!is_string($config['id_mode']) || $config['id_mode'] === '') {
                throw new \InvalidArgumentException('id_mode must be a non-empty string');
            }

            $validIdModes = ['auto', 'manual', 'prefix'];
            if (!in_array($config['id_mode'], $validIdModes, true)
                && !preg_match('/^prefix:.+$/', $config['id_mode'])) {
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
            'exists' => in_array($collectionName, $this->database->getCollectionNames(), true),
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
        $lastUpdatedTime = $this->normalizeMetadataTimestamp($metadata['last_updated'] ?? null);

        if ($lastUpdatedTime === null) {
            return true; // If no last_updated, consider it modified
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
            $lastUpdatedTime = $this->normalizeMetadataTimestamp($metadata['last_updated'] ?? null);
            if ($lastUpdatedTime === null || $lastUpdatedTime > $timestamp) {
                $modified[$collectionName] = $metadata;
            }
        }

        return $modified;
    }

    /**
     * Normalize metadata timestamps to a Unix timestamp.
     */
    private function normalizeMetadataTimestamp($lastUpdated): ?int
    {
        if ($lastUpdated === null || $lastUpdated === '') {
            return null;
        }

        if (is_int($lastUpdated)) {
            return $lastUpdated;
        }

        if (is_string($lastUpdated)) {
            $timestamp = strtotime($lastUpdated);

            return $timestamp === false ? null : $timestamp;
        }

        return null;
    }
}
