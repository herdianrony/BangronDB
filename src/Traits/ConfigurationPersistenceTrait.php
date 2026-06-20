<?php

declare(strict_types=1);

namespace BangronDB\Traits;

/**
 * Trait for persisting and loading collection configuration to/from the database.
 *
 * Handles serialization and deserialization of collection settings such as
 * ID mode, encryption status, searchable fields, schema, and soft delete options.
 */
trait ConfigurationPersistenceTrait
{
    /**
     * Custom configuration values.
     */
    protected array $customConfig = [];

    /**
     * Load collection configuration from database.
     *
     * Restores previously saved settings including ID mode, searchable fields,
     * schema validation, soft delete configuration, and custom config values.
     */
    protected function loadConfiguration(): void
    {
        $config = $this->database->loadCollectionConfig($this->name);

        if (!empty($config)) {
            // Apply loaded configuration
            if (isset($config['id_mode'])) {
                $this->setIdModeFromString($config['id_mode']);
            }

            // Note: encryption_key should be provided at runtime from external sources (.env, vault, etc.)
            // The config only stores encryption_enabled status
            // Use $collection->setEncryptionKey('your-key') to enable encryption

            if (isset($config['searchable_fields']) && is_array($config['searchable_fields'])) {
                $searchableFields = [];
                foreach ($config['searchable_fields'] as $field => $hashed) {
                    $searchableFields[$field] = ['hash' => (bool) $hashed];
                }
                $this->setSearchableFields($searchableFields);
            }

            if (isset($config['schema']) && is_array($config['schema'])) {
                $this->setSchema($config['schema']);
            }

            if (isset($config['soft_deletes_enabled'])) {
                $this->useSoftDeletes($config['soft_deletes_enabled']);
            }

            if (isset($config['deleted_at_field']) && is_string($config['deleted_at_field'])) {
                $this->setDeletedAtField($config['deleted_at_field']);
            }

            // Load custom configuration
            if (isset($config['custom_config']) && is_array($config['custom_config'])) {
                $this->customConfig = $config['custom_config'];
            }
        }
    }

    /**
     * Save current collection configuration to database.
     *
     * Persists all current collection settings so they can be restored
     * when the collection is loaded again in the future.
     */
    public function saveConfiguration(): void
    {
        $config = [
            'id_mode' => $this->getIdModeString(),
            'encryption_enabled' => $this->encryptionKey !== null,
            'searchable_fields' => $this->getSearchableFieldsForConfig(),
            'schema' => $this->getSchema(),
            'soft_deletes_enabled' => $this->softDeletesEnabled(),
            'deleted_at_field' => $this->getDeletedAtField(),
            'custom_config' => $this->customConfig,
        ];

        $this->database->saveCollectionConfig($this->name, $config);
    }

    /**
     * Set a custom configuration value.
     *
     * @param string $key   Configuration key
     * @param mixed  $value Configuration value
     */
    public function setCustomConfig(string $key, $value): self
    {
        $this->customConfig[$key] = $value;

        return $this;
    }

    /**
     * Get a custom configuration value.
     *
     * @param string $key     Configuration key
     * @param mixed  $default Default value if key not found
     *
     * @return mixed Configuration value
     */
    public function getCustomConfig(string $key, $default = null)
    {
        return $this->customConfig[$key] ?? $default;
    }

    /**
     * Get all custom configuration values.
     *
     * @return array Custom configuration values
     */
    public function getAllCustomConfig(): array
    {
        return $this->customConfig;
    }

    /**
     * Set multiple custom configuration values at once.
     *
     * @param array $config Array of key-value pairs
     */
    public function setCustomConfigArray(array $config): self
    {
        $this->customConfig = array_merge($this->customConfig, $config);

        return $this;
    }

    /**
     * Set ID mode from string representation.
     */
    private function setIdModeFromString(string $mode): void
    {
        switch ($mode) {
            case 'auto':
                $this->setIdModeAuto();
                break;
            case 'manual':
                $this->setIdModeManual();
                break;
            default:
                // Handle prefix mode - assume the mode string is the prefix
                $this->setIdModePrefix($mode);
                break;
        }
    }

    /**
     * Get ID mode as string representation.
     */
    private function getIdModeString(): string
    {
        return $this->idMode === 'prefix' ? ($this->idPrefix ?? 'auto') : $this->idMode;
    }

    /**
     * Get searchable fields configuration for saving.
     */
    private function getSearchableFieldsForConfig(): array
    {
        $config = [];
        foreach ($this->searchableFields as $field => $settings) {
            $config[$field] = $settings['hash'];
        }

        return $config;
    }
}
