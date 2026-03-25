<?php

namespace App\Services;

class CollectionService
{
    private SystemService $system;
    private DatabaseService $databaseService;

    public function __construct()
    {
        $this->system = new SystemService();
        $this->databaseService = new DatabaseService();
    }

    public function create(string $dbName, string $collection): void
    {
        $this->databaseService->assertNotSystem($dbName);
        $this->validateCollectionName($collection);
        $db = $this->system->tenantClient()->selectDB($dbName);
        $db->createCollection($collection);
    }

    public function rename(string $dbName, string $collection, string $newName): void
    {
        $this->databaseService->assertNotSystem($dbName);
        $this->validateCollectionName($collection);
        $this->validateCollectionName($newName);
        $db = $this->system->tenantClient()->selectDB($dbName);
        $db->selectCollection($collection)->renameCollection($newName);
    }

    public function drop(string $dbName, string $collection): void
    {
        $this->databaseService->assertNotSystem($dbName);
        $this->validateCollectionName($collection);
        $db = $this->system->tenantClient()->selectDB($dbName);
        $db->dropCollection($collection);
    }

    public function getConfig(string $dbName, string $collection): array
    {
        $this->databaseService->assertNotSystem($dbName);
        $db = $this->system->tenantClient()->selectDB($dbName);

        return $db->loadCollectionConfig($collection);
    }

    public function saveConfig(string $dbName, string $collection, array $config, array $indexes = []): void
    {
        $this->databaseService->assertNotSystem($dbName);
        $db = $this->system->tenantClient()->selectDB($dbName);
        $coll = $db->selectCollection($collection);

        if (!empty($config['id_mode'])) {
            if ($config['id_mode'] === 'manual') {
                $coll->setIdModeManual();
            } elseif ($config['id_mode'] === 'auto') {
                $coll->setIdModeAuto();
            } elseif ($config['id_mode'] === 'prefix') {
                $coll->setIdModePrefix($config['id_prefix'] ?? 'ID');
            }
        }

        if (!empty($config['encryption_enabled']) && !empty($_ENV['DB_ENCRYPTION_KEY'])) {
            $coll->setEncryptionKey($_ENV['DB_ENCRYPTION_KEY']);
        }

        if (isset($config['searchable_fields']) && is_array($config['searchable_fields'])) {
            foreach ($config['searchable_fields'] as $field => $hashed) {
                if (is_int($field)) {
                    if (is_string($hashed) && $hashed !== '') {
                        $coll->setSearchableFields([$hashed], false);
                    }
                    continue;
                }
                if (is_string($field) && $field !== '') {
                    $coll->setSearchableFields([$field], (bool) $hashed);
                }
            }
        }

        if (isset($config['schema']) && is_array($config['schema'])) {
            $coll->setSchema($config['schema']);
        }

        if (array_key_exists('soft_deletes_enabled', $config)) {
            $coll->useSoftDeletes((bool) $config['soft_deletes_enabled']);
        }

        if (!empty($config['deleted_at_field'])) {
            $coll->setDeletedAtField($config['deleted_at_field']);
        }

        if (isset($config['custom_config']) && is_array($config['custom_config'])) {
            $coll->setCustomConfigArray($config['custom_config']);
        }

        foreach ($indexes as $field) {
            if (is_string($field) && $field !== '') {
                $coll->createIndex($field);
            }
        }

        $coll->saveConfiguration();
    }

    private function validateCollectionName(string $name): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new \RuntimeException('Invalid collection name');
        }
    }
}
