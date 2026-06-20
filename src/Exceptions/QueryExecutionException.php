<?php

declare(strict_types=1);

namespace BangronDB\Exceptions;

/**
 * Exception thrown when query execution fails.
 *
 * Provides additional context including the SQL query and parameters
 * that caused the failure for debugging purposes.
 */
class QueryExecutionException extends \RuntimeException
{
    /**
     * Keys that should be redacted from debug output.
     */
    private const SENSITIVE_KEYS = ['encryption_key', 'password', 'secret', 'token', 'api_key', 'credential'];

    private string $sql;
    private array $params;

    public function __construct(string $message, string $sql, array $params = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->sql = $sql;
        $this->params = $params;
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Return redacted parameters for safe debugging.
     */
    public function getRedactedParams(): array
    {
        $filtered = [];
        foreach ($this->params as $key => $value) {
            $keyLower = is_string($key) ? strtolower($key) : $key;
            $isSensitive = false;

            if (is_string($key)) {
                foreach (self::SENSITIVE_KEYS as $sensitive) {
                    if (strpos($keyLower, $sensitive) !== false) {
                        $isSensitive = true;
                        break;
                    }
                }
            }

            $filtered[$key] = $isSensitive ? '[REDACTED]' : $value;
        }

        return $filtered;
    }

    /**
     * Prevent accidental leakage of SQL and raw parameters in debug output.
     */
    public function __debugInfo(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'has_sql' => $this->sql !== '',
            'params' => $this->getRedactedParams(),
            'previous' => $this->getPrevious()?->getMessage(),
        ];
    }
}
