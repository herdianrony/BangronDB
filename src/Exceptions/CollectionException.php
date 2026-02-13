<?php

namespace BangronDB\Exceptions;

/**
 * Exception thrown for collection-related errors.
 *
 * Used for collection operations like creation, deletion, and access errors.
 */
class CollectionException extends BangronDBException
{
    /**
     * Create exception for collection not found.
     *
     * @param string $collectionName Collection name
     * @param string $databaseName   Database name
     * @param array  $context        Additional context
     */
    public static function notFound(
        string $collectionName,
        string $databaseName = '',
        array $context = []
    ): self {
        $location = $databaseName ? " in database '{$databaseName}'" : '';
        $message = "Collection '{$collectionName}'{$location} not found";
        $context = array_merge($context, [
            'collection' => $collectionName,
            'database' => $databaseName,
        ]);

        return new self($message, 'COLLECTION_NOT_FOUND', $context);
    }

    /**
     * Create exception for collection already exists.
     *
     * @param string $collectionName Collection name
     * @param string $databaseName   Database name
     * @param array  $context        Additional context
     */
    public static function alreadyExists(
        string $collectionName,
        string $databaseName = '',
        array $context = []
    ): self {
        $location = $databaseName ? " in database '{$databaseName}'" : '';
        $message = "Collection '{$collectionName}'{$location} already exists";
        $context = array_merge($context, [
            'collection' => $collectionName,
            'database' => $databaseName,
        ]);

        return new self($message, 'COLLECTION_ALREADY_EXISTS', $context);
    }

    /**
     * Create exception for document not found.
     *
     * @param mixed  $documentId     Document ID
     * @param string $collectionName Collection name
     * @param array  $context        Additional context
     */
    public static function documentNotFound(
        $documentId,
        string $collectionName = '',
        array $context = []
    ): self {
        $location = $collectionName ? " in collection '{$collectionName}'" : '';
        $message = "Document with ID '{$documentId}'{$location} not found";
        $context = array_merge($context, [
            'document_id' => $documentId,
            'collection' => $collectionName,
        ]);

        return new self($message, 'DOCUMENT_NOT_FOUND', $context);
    }

    /**
     * Create exception for operation failure.
     *
     * @param string $operation      Operation name (insert, update, delete)
     * @param string $collectionName Collection name
     * @param string $reason         Failure reason
     * @param array  $context        Additional context
     */
    public static function operationFailed(
        string $operation,
        string $collectionName,
        string $reason = '',
        array $context = []
    ): self {
        $message = "Failed to {$operation} in collection '{$collectionName}'";
        if ($reason) {
            $message .= ": {$reason}";
        }
        $context = array_merge($context, [
            'operation' => $operation,
            'collection' => $collectionName,
            'reason' => $reason,
        ]);

        return new self($message, 'OPERATION_FAILED', $context);
    }
}
