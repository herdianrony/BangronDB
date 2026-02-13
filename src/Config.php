<?php

namespace BangronDB;

/**
 * Configuration manager for BangronDB.
 *
 * Manages global configuration options for the database library.
 * Provides a simple static interface for configuration management.
 */
class Config
{
    /**
     * Default configuration values.
     */
    private static array $defaults = [
        'default_path' => ':memory:',
        'encryption_key' => null,
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
        'page_size' => 4096,
        'cache_size' => -1024, // KB
        'auto_vacuum' => 'INCREMENTAL',
    ];

    /**
     * Current configuration.
     */
    private static array $config = [];

    /**
     * Valid configuration keys.
     */
    private static array $validKeys = [
        'default_path',
        'encryption_key',
        'journal_mode',
        'synchronous',
        'page_size',
        'cache_size',
        'auto_vacuum',
    ];

    /**
     * Valid values for enum-type configuration.
     */
    private static array $validEnumValues = [
        'journal_mode' => ['DELETE', 'TRUNCATE', 'PERSIST', 'MEMORY', 'WAL', 'OFF'],
        'synchronous' => ['OFF', 'NORMAL', 'FULL', 'EXTRA'],
        'auto_vacuum' => ['NONE', 'FULL', 'INCREMENTAL'],
    ];

    /**
     * Set a configuration value.
     *
     * @param string $key   Configuration key
     * @param mixed  $value Configuration value
     *
     * @throws \InvalidArgumentException If key or value is not valid
     */
    public static function set(string $key, $value): void
    {
        self::validateKey($key);
        self::validateValue($key, $value);
        self::ensureInitialized();
        self::$config[$key] = $value;
    }

    /**
     * Get a configuration value.
     *
     * @param string $key     Configuration key
     * @param mixed  $default Default value if key not found
     *
     * @return mixed Configuration value
     */
    public static function get(string $key, $default = null)
    {
        self::ensureInitialized();

        return self::$config[$key] ?? $default;
    }

    /**
     * Get all configuration values.
     *
     * @return array All configuration values
     */
    public static function all(): array
    {
        self::ensureInitialized();

        return self::$config;
    }

    /**
     * Reset configuration to defaults.
     */
    public static function reset(): void
    {
        self::$config = self::$defaults;
    }

    /**
     * Check if a configuration key exists.
     *
     * @param string $key Configuration key
     *
     * @return bool True if key exists
     */
    public static function has(string $key): bool
    {
        self::ensureInitialized();

        return array_key_exists($key, self::$config);
    }

    /**
     * Ensure configuration is initialized.
     */
    private static function ensureInitialized(): void
    {
        if (empty(self::$config)) {
            self::$config = self::$defaults;
        }
    }

    /**
     * Validate configuration key.
     *
     * @param string $key Configuration key
     *
     * @throws \InvalidArgumentException If key is not valid
     */
    private static function validateKey(string $key): void
    {
        if (!in_array($key, self::$validKeys, true)) {
            throw new \InvalidArgumentException(
                "Invalid configuration key '{$key}'. Valid keys are: " .
                implode(', ', self::$validKeys)
            );
        }
    }

    /**
     * Validate configuration value.
     *
     * @param string $key   Configuration key
     * @param mixed  $value Configuration value
     *
     * @throws \InvalidArgumentException If value is not valid for the given key
     */
    private static function validateValue(string $key, $value): void
    {
        switch ($key) {
            case 'default_path':
                self::validateDefaultPath($value);
                break;

            case 'encryption_key':
                self::validateEncryptionKey($value);
                break;

            case 'journal_mode':
            case 'synchronous':
            case 'auto_vacuum':
                self::validateEnumValue($key, $value);
                break;

            case 'page_size':
                self::validatePageSize($value);
                break;

            case 'cache_size':
                self::validateCacheSize($value);
                break;
        }
    }

    /**
     * Validate default_path value.
     *
     * @throws \InvalidArgumentException If value is invalid
     */
    private static function validateDefaultPath($value): void
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException(
                "Config 'default_path' must be a non-empty string"
            );
        }
    }

    /**
     * Validate encryption_key value.
     *
     * @throws \InvalidArgumentException If value is invalid
     */
    private static function validateEncryptionKey($value): void
    {
        if ($value !== null && !is_string($value)) {
            throw new \InvalidArgumentException(
                "Config 'encryption_key' must be a string or null"
            );
        }
    }

    /**
     * Validate enum-type configuration values.
     *
     * @throws \InvalidArgumentException If value is not in allowed enum values
     */
    private static function validateEnumValue(string $key, $value): void
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException(
                "Config '{$key}' must be a string"
            );
        }

        $upperValue = strtoupper($value);
        $validValues = self::$validEnumValues[$key];

        if (!in_array($upperValue, $validValues, true)) {
            throw new \InvalidArgumentException(
                "Config '{$key}' must be one of: " . implode(', ', $validValues) .
                ". Got: {$value}"
            );
        }
    }

    /**
     * Validate page_size value.
     *
     * @throws \InvalidArgumentException If value is invalid
     */
    private static function validatePageSize($value): void
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException(
                "Config 'page_size' must be an integer"
            );
        }

        // SQLite page_size must be power of 2 between 512 and 65536
        $validSizes = [512, 1024, 2048, 4096, 8192, 16384, 32768, 65536];

        if (!in_array($value, $validSizes, true)) {
            throw new \InvalidArgumentException(
                "Config 'page_size' must be a power of 2 between 512 and 65536. " .
                "Valid values: " . implode(', ', $validSizes)
            );
        }
    }

    /**
     * Validate cache_size value.
     *
     * @throws \InvalidArgumentException If value is invalid
     */
    private static function validateCacheSize($value): void
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException(
                "Config 'cache_size' must be an integer"
            );
        }

        // cache_size can be positive (pages) or negative (KB)
        // Reasonable range: -1GB to 1GB equivalent
        if ($value === 0 || abs($value) > 1048576) {
            throw new \InvalidArgumentException(
                "Config 'cache_size' must be non-zero and within reasonable bounds " .
                "(-1048576 to 1048576, where negative values are in KB)"
            );
        }
    }
}

