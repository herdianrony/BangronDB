<?php

namespace BangronDB;

use BangronDB\Exceptions\DatabaseException;

/**
 * Factory class for creating BangronDB instances.
 *
 * Provides centralized creation of Client, Database, and Collection objects
 * with configuration management and comprehensive validation.
 */
class Factory
{
    /**
     * Create a new Client instance.
     *
     * @param string|null $path    Database path (optional, uses config default if null)
     * @param array       $options Client options
     *
     * @throws DatabaseException If path is invalid or not accessible
     * @return Client
     */
    public static function createClient(?string $path = null, array $options = []): Client
    {
        $path = $path ?? Config::get('default_path');

        // Normalize path
        $path = self::normalizePath($path);

        // Validate path if not memory
        if ($path !== Database::DSN_PATH_MEMORY) {
            self::validatePath($path);
        }

        // Merge global config with provided options
        $finalOptions = array_merge(Config::all(), $options);

        return new Client($path, $finalOptions);
    }

    /**
     * Create a new Database instance.
     *
     * @param string $path    Database path
     * @param string $name    Database name
     * @param array  $options Database options
     *
     * @throws DatabaseException If path or name is invalid
     * @return Database
     */
    public static function createDatabase(string $path, string $name, array $options = []): Database
    {
        $client = self::createClient($path, $options);

        return $client->selectDB($name, $options);
    }

    /**
     * Create a new Collection instance.
     *
     * @param string $path           Database path
     * @param string $databaseName   Database name
     * @param string $collectionName Collection name
     * @param array  $options        Collection options
     *
     * @throws DatabaseException If path or names are invalid
     * @return Collection
     */
    public static function createCollection(
        string $path,
        string $databaseName,
        string $collectionName,
        array $options = []
    ): Collection {
        $database = self::createDatabase($path, $databaseName, $options);

        return $database->selectCollection($collectionName);
    }

    /**
     * Create a collection from an existing database instance.
     *
     * @param Database $database       Database instance
     * @param string   $collectionName Collection name
     *
     * @return Collection
     */
    public static function createCollectionFromDatabase(Database $database, string $collectionName): Collection
    {
        return $database->selectCollection($collectionName);
    }

    /**
     * Normalize path by removing trailing slashes and resolving relative paths.
     *
     * @param string $path Path to normalize
     *
     * @return string Normalized path
     */
    private static function normalizePath(string $path): string
    {
        if ($path === Database::DSN_PATH_MEMORY) {
            return $path;
        }

        // Remove trailing slashes
        $path = rtrim($path, '/\\');

        // Resolve relative paths if possible
        if (file_exists($path)) {
            $realPath = realpath($path);
            if ($realPath !== false) {
                return $realPath;
            }
        }

        return $path;
    }

    /**
     * Validate database path for existence and permissions.
     *
     * @param string $path Path to validate
     *
     * @throws DatabaseException If path is invalid or not accessible
     */
    private static function validatePath(string $path): void
    {
        $directory = dirname($path);

        // Check if directory exists
        if (!is_dir($directory)) {
            throw DatabaseException::invalidPath(
                $path,
                'Directory does not exist',
                ['directory' => $directory]
            );
        }

        // Check if directory is readable
        if (!is_readable($directory)) {
            throw DatabaseException::permissionDenied($path, 'read', ['directory' => $directory]);
        }

        // Check if directory is writable
        if (!is_writable($directory)) {
            throw DatabaseException::permissionDenied($path, 'write', ['directory' => $directory]);
        }

        // If database file exists, check if it's readable and writable
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

