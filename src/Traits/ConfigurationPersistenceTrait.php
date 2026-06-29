<?php

declare(strict_types=1);

namespace BangronDB\Traits;

trait ConfigurationPersistenceTrait
{
    /** @var array<string, mixed> user-defined non-sensitive custom config values */
    protected array $customConfig = [];

    /**
     * Sensitive config keys that must never be persisted.
     *
     * Declared as a static method (not a constant) because PHP 8.1 does not
     * allow constants in traits — that feature was added in PHP 8.2. The
     * project targets PHP ^8.1, so a static method is the compatible,
     * immutable equivalent.
     *
     * @return list<string>
     */
    private static function sensitiveConfigKeys(): array
    {
        return [
            'encryption_key', 'encryptionkey', 'password', 'passwd',
            'secret', 'token', 'api_key', 'apikey', 'private_key', 'credential',
        ];
    }

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

    /**
     * Strip sensitive keys (encryption_key, password, token, etc.) from a
     * config array. Used as a defence-in-depth filter on save and load.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function filterSensitiveConfig(array $config): array
    {
        foreach (self::sensitiveConfigKeys() as $sensitive) {
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
        return in_array(strtolower($key), self::sensitiveConfigKeys(), true);
    }

    /**
     * Set a non-sensitive custom config value.
     *
     * @param mixed $value Any serialisable value (string, int, bool, array, null).
     */
    public function setCustomConfig(string $key, mixed $value): self
    {
        if ($this->isSensitiveConfigKey($key)) {
            throw new \InvalidArgumentException("Custom config key '{$key}' is forbidden - sensitive credentials must not be persisted. Provide encryption keys at runtime via setEncryptionKey() / \$_ENV.");
        }
        $this->customConfig[$key] = $value;
        return $this;
    }

    /**
     * Get a custom config value, returning $default when the key is absent.
     *
     * @param mixed $default Fallback value returned when the key is not set.
     * @return mixed The stored value, or $default when absent.
     */
    public function getCustomConfig(string $key, mixed $default = null): mixed
    {
        return $this->customConfig[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAllCustomConfig(): array
    {
        return $this->customConfig;
    }

    /**
     * @param array<string, mixed> $config
     */
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

    /**
     * @return array<string, bool>
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
