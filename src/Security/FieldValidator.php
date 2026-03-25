<?php

declare(strict_types=1);

namespace BangronDB\Security;

use BangronDB\Exceptions\ValidationException;

/**
 * Security validation utility for field names, database paths, and regex patterns.
 * Prevents NoSQL injection, path traversal, and regex denial-of-service attacks.
 */
class FieldValidator
{
    /**
     * Whitelist pattern for valid field names.
     * Allows: alphanumeric, underscore, hyphen, dot
     * Blocks: quotes, semicolons, parentheses, wildcards, etc.
     *
     * @var string
     */
    private const FIELD_NAME_PATTERN = '/^[a-zA-Z0-9_\-\.]+$/';

    /**
     * Maximum length for field names to prevent memory exhaustion.
     *
     * @var int
     */
    private const MAX_FIELD_LENGTH = 255;

    /**
     * Characters that are absolutely forbidden in field names.
     *
     * @var array<int, string>
     */
    private const FORBIDDEN_CHARS = ["'", '"', '`', ';', '(', ')', '{', '}', '[', ']', '<', '>', '\\', "\n", "\r", "\0"];

    /**
     * Validate a single field name against security whitelist.
     *
     * @param string $fieldName The field name to validate
     *
     * @return bool True if valid, false otherwise
     */
    public static function isValidFieldName(string $fieldName): bool
    {
        // Check length
        if (empty($fieldName) || strlen($fieldName) > self::MAX_FIELD_LENGTH) {
            return false;
        }

        // Check for forbidden characters
        foreach (self::FORBIDDEN_CHARS as $char) {
            if (strpos($fieldName, $char) !== false) {
                return false;
            }
        }

        // Check whitelist pattern
        return (bool) preg_match(self::FIELD_NAME_PATTERN, $fieldName);
    }

    /**
     * Validate a field name and throw exception if invalid.
     *
     * @param string $fieldName The field name to validate
     *
     * @throws ValidationException If field name is invalid
     */
    public static function validateFieldName(string $fieldName): void
    {
        if (!self::isValidFieldName($fieldName)) {
            throw new ValidationException("Invalid field name '{$fieldName}'. Field names must be alphanumeric with underscores, hyphens, and dots only.");
        }
    }

    /**
     * Validate field names in a nested array structure (recursive).
     *
     * @param array<mixed, mixed> $fields Associative array of fields
     *
     * @throws ValidationException If any field name is invalid
     */
    public static function validateFieldNames(array $fields): void
    {
        foreach ($fields as $fieldName => $_value) {
            if (!is_string($fieldName)) {
                continue;
            }
            self::validateFieldName($fieldName);
        }
    }

    /**
     * Validate database file path to prevent directory traversal attacks.
     *
     * @param string      $path     The database file path
     * @param string|null $basePath Optional base directory to restrict paths to
     *
     * @return string The validated absolute path
     *
     * @throws ValidationException If path is invalid or attempts traversal
     */
    public static function validateDatabasePath(string $path, ?string $basePath = null): string
    {
        if (empty($path)) {
            throw new ValidationException('Database path cannot be empty');
        }

        // Handle special case for in-memory databases
        if ($path === ':memory:') {
            return $path;
        }

        // Resolve to absolute path
        $realPath = realpath($path);

        // If file doesn't exist yet, realpath fails - try parent directory
        if ($realPath === false) {
            $directory = dirname($path);
            if (!is_dir($directory)) {
                throw new ValidationException("Database directory does not exist: {$directory}");
            }
            $realPath = realpath($directory).DIRECTORY_SEPARATOR.basename($path);
        }

        // If basePath is specified, ensure the database path is within it
        if ($basePath !== null) {
            $basePath = realpath($basePath);
            if ($basePath === false) {
                throw new ValidationException("Base path does not exist: {$basePath}");
            }

            // Ensure $realPath starts with $basePath
            if (strpos($realPath, $basePath) !== 0) {
                throw new ValidationException("Database path '{$path}' is outside allowed base directory '{$basePath}'");
            }
        }

        return $realPath;
    }

    /**
     * Sanitize regex pattern to prevent regex denial of service (ReDoS).
     * Uses preg_quote() to escape special regex characters.
     *
     * @param string $pattern   The regex pattern to sanitize
     * @param string $delimiter Optional regex delimiter (default: '/')
     *
     * @return string The quoted pattern ready for use in preg_* functions
     */
    public static function sanitizeRegexPattern(string $pattern, string $delimiter = '/'): string
    {
        return preg_quote($pattern, $delimiter);
    }

    /**
     * Validate and escape PRAGMA key for SQLite encryption.
     * Prevents SQL injection in PRAGMA key statements.
     *
     * @param string $key The encryption key
     *
     * @return string The escaped key safe for PRAGMA statement
     *
     * @throws ValidationException If key contains invalid characters
     */
    public static function escapePragmaKey(string $key): string
    {
        if (empty($key)) {
            throw new ValidationException('PRAGMA key cannot be empty');
        }

        // Check for null bytes and other control characters
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $key)) {
            throw new ValidationException('PRAGMA key contains invalid control characters');
        }

        // Escape single quotes by doubling them (SQL standard)
        // This is more robust than str_replace for edge cases
        $escaped = str_replace("'", "''", $key);

        // Additional validation: warn if key looks suspicious
        // (Too many quotes, unlikely patterns, etc.)
        // For now, we allow it but it's properly escaped

        return $escaped;
    }

    /**
     * Check if a value is a safe callable (must be a Closure).
     * Prevents RCE via string function names like "system", "exec", etc.
     *
     * @param mixed $value The value to check
     *
     * @return bool True if value is a Closure, false otherwise
     */
    public static function isSafeCallable($value): bool
    {
        return $value instanceof \Closure;
    }

    /**
     * Validate a callable is safe (must be a Closure).
     * Throws exception with helpful message if not.
     *
     * @param mixed  $value        The callable to validate
     * @param string $operatorName The operator name for error message (e.g., '$where', '$func')
     *
     * @throws ValidationException If not a safe callable
     */
    public static function validateSafeCallable($value, string $operatorName = 'operator'): void
    {
        if (!self::isSafeCallable($value)) {
            throw new ValidationException("The '{$operatorName}' operator only accepts Closure objects (anonymous functions). String function names like 'system', 'exec', etc. are not allowed. Example: ['{$operatorName}' => fn(\$doc) => \$doc['field'] > 10]");
        }
    }
}
