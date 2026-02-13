<?php

namespace BangronDB\Exceptions;

/**
 * Exception thrown for database-level errors.
 *
 * Used for database operations, connection issues, and integrity problems.
 */
class DatabaseException extends BangronDBException
{
    /**
     * Create exception for database not found.
     *
     * @param string $databaseName Database name
     * @param string $path         Database path
     * @param array  $context      Additional context
     */
    public static function notFound(
        string $databaseName,
        string $path = '',
        array $context = []
    ): self {
        $location = $path ? " at path '{$path}'" : '';
        $message = "Database '{$databaseName}'{$location} not found";
        $context = array_merge($context, [
            'database' => $databaseName,
            'path' => $path,
        ]);

        return new self($message, 'DATABASE_NOT_FOUND', $context);
    }

    /**
     * Create exception for connection failure.
     *
     * @param string          $path     Database path
     * @param string          $reason   Failure reason
     * @param \Throwable|null $previous Previous exception
     * @param array           $context  Additional context
     */
    public static function connectionFailed(
        string $path,
        string $reason = '',
        ?\Throwable $previous = null,
        array $context = []
    ): self {
        $message = "Failed to connect to database at '{$path}'";
        if ($reason) {
            $message .= ": {$reason}";
        }
        $context = array_merge($context, [
            'path' => $path,
            'reason' => $reason,
        ]);

        return new self($message, 'CONNECTION_FAILED', $context, 0, $previous);
    }

    /**
     * Create exception for integrity check failure.
     *
     * @param string $databaseName Database name
     * @param array  $issues       Integrity issues found
     * @param array  $context      Additional context
     */
    public static function integrityCheckFailed(
        string $databaseName,
        array $issues = [],
        array $context = []
    ): self {
        $message = "Database '{$databaseName}' failed integrity check";
        $context = array_merge($context, [
            'database' => $databaseName,
            'issues' => $issues,
        ]);

        return new self($message, 'INTEGRITY_CHECK_FAILED', $context);
    }

    /**
     * Create exception for permission denied.
     *
     * @param string $path      Database path
     * @param string $operation Operation attempted (read, write, create)
     * @param array  $context   Additional context
     */
    public static function permissionDenied(
        string $path,
        string $operation = 'access',
        array $context = []
    ): self {
        $message = "Permission denied to {$operation} database at '{$path}'";
        $context = array_merge($context, [
            'path' => $path,
            'operation' => $operation,
        ]);

        return new self($message, 'PERMISSION_DENIED', $context);
    }

    /**
     * Create exception for invalid database path.
     *
     * @param string $path    Database path
     * @param string $reason  Reason why path is invalid
     * @param array  $context Additional context
     */
    public static function invalidPath(
        string $path,
        string $reason = '',
        array $context = []
    ): self {
        $message = "Invalid database path '{$path}'";
        if ($reason) {
            $message .= ": {$reason}";
        }
        $context = array_merge($context, [
            'path' => $path,
            'reason' => $reason,
        ]);

        return new self($message, 'INVALID_PATH', $context);
    }
}
