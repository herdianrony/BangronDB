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
     * Maximum regex pattern length for trusted regex configuration.
     */
    private const MAX_REGEX_LENGTH = 500;

    /**
     * High-risk regex constructs to reject from persisted config.
     */
    private const DANGEROUS_REGEX_PATTERNS = [
        '/([+*?]|\{[^}]*\})\s*([+*?]|\{)/',
        '/\((?:[^()\\]|\\.)*([+*?]|\{[^}]*\})(?:[^()\\]|\\.)*\)\s*(?:[+*?]|\{)/',
        '/[\\\\][1-9][0-9]*/',
        '/\(\?(?:R|[0-9]|&)/',
        '/\(\?<(?=[=!])/',
    ];

    /**
     * Whitelist pattern for valid field names.
     * Allows: alphanumeric, underscore, hyphen, dot.
     */
    private const FIELD_NAME_PATTERN = '/^[a-zA-Z0-9_\-\.]+$/';

    /**
     * Maximum length for field names to prevent memory exhaustion.
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
     */
    public static function isValidFieldName(string $fieldName): bool
    {
        if (empty($fieldName) || strlen($fieldName) > self::MAX_FIELD_LENGTH) {
            return false;
        }

        foreach (self::FORBIDDEN_CHARS as $char) {
            if (strpos($fieldName, $char) !== false) {
                return false;
            }
        }

        return (bool) preg_match(self::FIELD_NAME_PATTERN, $fieldName);
    }

    /**
     * Validate a field name and throw exception if invalid.
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
     * Validate a database directory path to prevent directory traversal attacks.
     *
     * @return string Validated absolute directory path
     *
     * @throws ValidationException If path is invalid or attempts traversal
     */
    public static function validateDatabaseDirectoryPath(string $path, ?string $basePath = null): string
    {
        if (empty($path)) {
            throw new ValidationException('Database path cannot be empty');
        }

        if ($path === ':memory:') {
            return $path;
        }

        self::assertNoTraversalSegments($path);

        if (!is_dir($path)) {
            throw new ValidationException("Database directory does not exist: {$path}");
        }

        $realPath = realpath($path);
        if ($realPath === false) {
            throw new ValidationException("Failed to resolve database directory: {$path}");
        }

        self::assertPathWithinBase($realPath, $basePath, $path);

        return $realPath;
    }

    /**
     * Validate database file path to prevent directory traversal attacks.
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

        if ($path === ':memory:') {
            return $path;
        }

        self::assertNoTraversalSegments($path);

        $realPath = realpath($path);
        if ($realPath === false) {
            $directory = dirname($path);
            if (!is_dir($directory)) {
                throw new ValidationException("Database directory does not exist: {$directory}");
            }
            $resolvedDirectory = realpath($directory);
            if ($resolvedDirectory === false) {
                throw new ValidationException("Failed to resolve database directory: {$directory}");
            }
            $realPath = $resolvedDirectory . DIRECTORY_SEPARATOR . basename($path);
        }

        self::assertPathWithinBase($realPath, $basePath, $path);

        return $realPath;
    }

    /**
     * Sanitize regex pattern to prevent regex denial of service (ReDoS).
     * Uses preg_quote() to escape special regex characters.
     */
    public static function sanitizeRegexPattern(string $pattern, string $delimiter = '/'): string
    {
        return preg_quote($pattern, $delimiter);
    }

    /**
     * Sanitize a persisted schema regex pattern while preserving safe regexes.
     * Dangerous or invalid patterns are downgraded to literal matching.
     */
    public static function sanitizeSchemaRegexPattern(string $pattern): string
    {
        if (strlen($pattern) > self::MAX_REGEX_LENGTH) {
            return '/' . self::sanitizeRegexPattern($pattern) . '/u';
        }

        if ($pattern === '') {
            return $pattern;
        }

        if ($pattern[0] !== '/') {
            return '/' . self::sanitizeRegexPattern($pattern) . '/u';
        }

        if (str_contains($pattern, '\\g') || str_contains($pattern, '\\k<')) {
            return '/' . self::sanitizeRegexPattern($pattern) . '/u';
        }

        foreach (self::DANGEROUS_REGEX_PATTERNS as $dangerPattern) {
            if (preg_match($dangerPattern, $pattern)) {
                return '/' . self::sanitizeRegexPattern($pattern) . '/u';
            }
        }

        set_error_handler(static fn () => true, E_WARNING);
        try {
            $compiled = preg_match($pattern, '');
        } finally {
            restore_error_handler();
        }

        if ($compiled === false) {
            return '/' . self::sanitizeRegexPattern($pattern) . '/u';
        }

        return $pattern;
    }

    /**
     * Validate and escape PRAGMA key for SQLite encryption.
     * Prevents SQL injection in PRAGMA key statements.
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

        if (preg_match('/[\x00-\x1F]/', $key)) {
            throw new ValidationException('PRAGMA key contains invalid control characters');
        }

        return str_replace("'", "''", $key);
    }

    /**
     * Check if a value is a safe callable (must be a Closure).
     * Prevents RCE via string function names like "system", "exec", etc.
     */
    public static function isSafeCallable($value): bool
    {
        return $value instanceof \Closure;
    }

    /**
     * Validate a callable is safe (must be a Closure).
     *
     * @throws ValidationException If not a safe callable
     */
    public static function validateSafeCallable($value, string $operatorName = 'operator'): void
    {
        if (!self::isSafeCallable($value)) {
            throw new ValidationException("The '{$operatorName}' operator only accepts Closure objects (anonymous functions). String function names like 'system', 'exec', etc. are not allowed. Example: ['{$operatorName}' => fn(\$doc) => \$doc['field'] > 10]");
        }
    }

    /**
     * Reject lexical parent-directory traversal segments early.
     *
     * @throws ValidationException
     */
    private static function assertNoTraversalSegments(string $path): void
    {
        if (preg_match('#(^|[\\/])\.\.([\\/]|$)#', $path)) {
            throw new ValidationException("Database path '{$path}' contains disallowed parent-directory traversal segments");
        }
    }

    /**
     * Assert that a resolved path stays within an optional base directory.
     *
     * @throws ValidationException
     */
    private static function assertPathWithinBase(string $realPath, ?string $basePath, string $originalPath): void
    {
        if ($basePath === null || $basePath === ':memory:') {
            return;
        }

        $resolvedBasePath = realpath($basePath);
        if ($resolvedBasePath === false) {
            throw new ValidationException("Base path does not exist: {$basePath}");
        }

        if (strpos($realPath, $resolvedBasePath) !== 0) {
            throw new ValidationException("Database path '{$originalPath}' is outside allowed base directory '{$resolvedBasePath}'");
        }
    }
}
