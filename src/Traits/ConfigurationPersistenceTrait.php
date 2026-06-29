<?php

declare(strict_types=1);

namespace BangronDB\Traits;

trait ConfigurationPersistenceTrait
{
    protected array $customConfig = [];
    private const SENSITIVE_CONFIG_KEYS = [
        'encryption_key','encryptionkey','password','passwd','secret','token','api_key','apikey','private_key','credential',
    ];

    protected function loadConfiguration(): void
    {
        $config = $this->database->loadCollectionConfig($this->name);
        if (!empty($config)) {
            if (isset($config['id_mode'])) { $this->setIdModeFromString($config['id_mode']); }
            if (isset($config['searchable_fields']) && is_array($config['searchable_fields'])) {
                $searchableFields = [];
                foreach ($config['searchable_fields'] as $field => $hashed) {
                    $searchableFields[$field] = ['hash' => (bool) $hashed];
                }
                $this->setSearchableFields($searchableFields);
            }
            if (isset($config['schema']) && is_array($config['schema'])) { $this->setSchema($config['schema']); }
            if (isset($config['soft_deletes_enabled'])) { $this->useSoftDeletes($config['soft_deletes_enabled']); }
            if (isset($config['deleted_at_field']) && is_string($config['deleted_at_field'])) { $this->setDeletedAtField($config['deleted_at_field']); }
            if (isset($config['custom_config']) && is_array($config['custom_config'])) {
                $this->customConfig = $this->filterSensitiveConfig($config['custom_config']);
            }
        }
    }

    public function saveConfiguration(): void
    {
        $config = [
            'id_mode' => $this->getIdModeString(),
            'encryption_enabled' => $this->encryptionKey !== null,
            'encryption_key_version' => $this->encryptionKeyVersion ?? null,
            'searchable_fields' => $this->getSearchableFieldsForConfig(),
            'schema' => $this->getSchema(),
            'soft_deletes_enabled' => $this->softDeletesEnabled(),
            'deleted_at_field' => $this->getDeletedAtField(),
            'custom_config' => $this->filterSensitiveConfig($this->customConfig),
        ];
        $this->database->saveCollectionConfig($this->name, $config);
    }

    private function filterSensitiveConfig(array $config): array
    {
        foreach (self::SENSITIVE_CONFIG_KEYS as $sensitive) {
            foreach (array_keys($config) as $key) {
                if (strtolower((string)$key) === $sensitive) {
                    unset($config[$key]);
                }
            }
        }
        return $config;
    }

    private function isSensitiveConfigKey(string $key): bool
    {
        return in_array(strtolower($key), self::SENSITIVE_CONFIG_KEYS, true);
    }

    public function setCustomConfig(string $key, $value): self
    {
        if ($this->isSensitiveConfigKey($key)) {
            throw new \InvalidArgumentException("Custom config key '{$key}' is forbidden - sensitive credentials must not be persisted. Provide encryption keys at runtime via setEncryptionKey() / \$_ENV.");
        }
        $this->customConfig[$key] = $value;
        return $this;
    }

    public function getCustomConfig(string $key, $default = null)
    {
        return $this->customConfig[$key] ?? $default;
    }

    public function getAllCustomConfig(): array
    {
        return $this->customConfig;
    }

    public function setCustomConfigArray(array $config): self
    {
        foreach (array_keys($config) as $key) {
            if (is_string($key) && $this->isSensitiveConfigKey($key)) {
                throw new \InvalidArgumentException("Custom config key '{$key}' is forbidden - sensitive credentials must not be persisted.");
            }
        }
        $this->customConfig = array_merge($this->customConfig, $this->filterSensitiveConfig($config));
        return $this;
    }

    private function setIdModeFromString(string $mode): void
    {
        switch ($mode) {
            case 'auto': $this->setIdModeAuto(); break;
            case 'manual': $this->setIdModeManual(); break;
            default:
                if (str_starts_with($mode, 'prefix:')) { $this->setIdModePrefix(substr($mode, strlen('prefix:'))); break; }
                $this->setIdModePrefix($mode); break;
        }
    }

    private function getIdModeString(): string
    {
        if ($this->idMode !== 'prefix') { return $this->idMode; }
        return $this->idPrefix !== null && $this->idPrefix !== '' ? 'prefix:' . $this->idPrefix : 'prefix';
    }

    private function getSearchableFieldsForConfig(): array
    {
        $config = [];
        foreach ($this->searchableFields as $field => $settings) {
            $config[$field] = $settings['hash'];
        }
        return $config;
    }
}
