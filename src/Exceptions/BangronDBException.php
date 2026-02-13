<?php

namespace BangronDB\Exceptions;

use Exception;

/**
 * Base exception class for all BangronDB exceptions.
 *
 * Provides enhanced error handling with error codes and context information
 * for better debugging and error tracking.
 */
class BangronDBException extends Exception
{
    /**
     * @var array Additional context information about the error
     */
    protected array $context = [];

    /**
     * @var string Machine-readable error code
     */
    protected string $errorCode = '';

    /**
     * Constructor.
     *
     * @param string          $message  Error message
     * @param string          $errorCode Machine-readable error code
     * @param array           $context  Additional context information
     * @param int             $code     Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = '',
        string $errorCode = '',
        array $context = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
        $this->context = $context;
    }

    /**
     * Get additional context information.
     *
     * @return array Context information
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get machine-readable error code.
     *
     * @return string Error code
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get full error information including context.
     *
     * @return array Complete error information
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'context' => $this->context,
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }

    /**
     * Convert exception to JSON string.
     *
     * @return string JSON representation
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}
