<?php

declare(strict_types=1);

namespace BangronDB;

use BangronDB\Exceptions\DatabaseException;
use BangronDB\Security\FieldValidator;

/**
 * Factory class for creating BangronDB instances.
 */
class Factory
{
    /**
     * @throws DatabaseException
     */
    public static function createClient(?string $path = null, array $options = []): Client
    {
        $path = $path ?? Config::get('default_path');
        $path = self::normalizePath($path);

        if ($path !== Database::DSN_PATH_MEMORY) {
            $basePath = isset($options['base_path']) && is_string($options['base_path'])
                ? $options['base_path']
                : null;
            $path = FieldValidator::validateDatabaseDirectoryPath($path, $basePath);
        }

        $finalOptions = array_merge(Config::all(), $options);

        return new Client($path, $finalOptions);
    }

    /**
     * @throws DatabaseException
     */
    public static function createDatabase(string $path, string $name, array $options = []): Database
    {
        $client = self::createClient($path, $options);

        return $client->createDB($name, $options);
    }

    /**
     * @throws DatabaseException
     */
    public static function createCollection(
        string $path,
        string $databaseName,
        string $collectionName,
        array $options = []
    ): Collection {
        $database = self::createDatabase($path, $databaseName, $options);

        return $database->createCollection($collectionName);
    }

    public static function createCollectionFromDatabase(Database $database, string $collectionName): Collection
    {
        return $database->createCollection($collectionName);
    }

    private static function normalizePath(string $path): string
    {
        if ($path === Database::DSN_PATH_MEMORY) {
            return $path;
        }

        $path = rtrim($path, '/\\');

        if (file_exists($path)) {
            $realPath = realpath($path);
            if ($realPath !== false) {
                return $realPath;
            }
        }

        return $path;
    }

    /**
     * Legacy validation helper retained for backward compatibility.
     *
     * @throws DatabaseException
     */
    private static function validatePath(string $path): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            throw DatabaseException::invalidPath(
                $path,
                'Directory does not exist',
                ['directory' => $directory]
            );
        }

        if (!is_readable($directory)) {
            throw DatabaseException::permissionDenied($path, 'read', ['directory' => $directory]);
        }

        if (!is_writable($directory)) {
            throw DatabaseException::permissionDenied($path, 'write', ['directory' => $directory]);
        }

        if (file_exists($path)) {
            if (!is_readable($path)) {
                throw DatabaseException::permissionDenied($path, 'read');
            }

            if (!is_writable($path)) {
                throw DatabaseException::permissionDenied($path, 'write');
            }
        }
    }
}
