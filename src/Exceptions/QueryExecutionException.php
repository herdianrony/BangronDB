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
    public string $sql;
    public array $params;

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
}
