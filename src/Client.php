<?php

namespace BangronDB;

use BangronDB\Exceptions\ValidationException;

/**
 * Client object for managing BangronDB databases.
 *
 * This class provides access to multiple databases within a single directory
 * or in-memory storage. Databases can be accessed either explicitly via
 * selectDB() or using the magic property syntax.
 *
 * Example usage:
 * ```php
 * $client = new Client('/path/to/databases');
 *
 * // Explicit access (recommended for IDE autocomplete)
 * $db = $client->selectDB('mydb');
 *
 * // Magic property access (convenient but less IDE-friendly)
 * $db = $client->mydb;
 * ```
 */
class Client
{
    /**
     * @var array<string,\BangronDB\Database>
     */
    protected array $databases = [];

    /**
     * @var string Database path
     */
    public string $path;

    /**
     * @var array Client options
     */
    protected array $options = [];

    /**
     * Path validation regex for database names.
     */
    private const DATABASE_NAME_REGEX = '/^[a-z0-9_-]+$/i';

    /**
     * Constructor.
     *
     * @param string $path    Pathname to database file or :memory:
     * @param array  $options Client options
     */
    public function __construct(string $path, array $options = [])
    {
        $this->path = $this->normalizePath($path);
        $this->options = $options;
    }

    /**
     * Normalize path by trimming slashes.
     */
    private function normalizePath(string $path): string
    {
        return \rtrim($path, '/\\');
    }

    /**
     * List Databases.
     *
     * @return array List of database names
     */
    public function listDBs(): array
    {
        if ($this->path === Database::DSN_PATH_MEMORY) {
            return $this->getMemoryDatabaseNames();
        }

        return $this->getDiskDatabaseNames();
    }

    /**
     * Get database names from memory.
     */
    private function getMemoryDatabaseNames(): array
    {
        return array_keys($this->databases);
    }

    /**
     * Get database names from disk.
     */
    private function getDiskDatabaseNames(): array
    {
        $databases = [];

        try {
            foreach (new \DirectoryIterator($this->path) as $fileInfo) {
                if ($this->isDatabaseFile($fileInfo)) {
                    $filename = $fileInfo->getFilename();
                    if (str_ends_with($filename, '.bangron')) {
                        $databases[] = substr($filename, 0, -8);
                    }
                }
            }
        } catch (\Exception $e) {
            // Handle directory access errors gracefully
            return [];
        }

        return $databases;
    }

    /**
     * Check if file is BangronDB database.
     */
    private function isDatabaseFile(\SplFileInfo $fileInfo): bool
    {
        $ext = $fileInfo->getExtension();

        return $ext === 'bangron';
    }

    /**
     * Select Collection.
     *
     * @param string $database   Database name
     * @param string $collection Collection name
     */
    public function selectCollection(string $database, string $collection): Collection
    {
        return $this->selectDB($database)->selectCollection($collection);
    }

    /**
     * Select database.
     *
     * @param string $name Database name
     *
     * @throws ValidationException If database name is invalid
     */
    public function selectDB(string $name, array $options = []): Database
    {
        $this->validateDatabaseName($name);

        if (!isset($this->databases[$name])) {
            $this->databases[$name] = $this->createDatabaseInstance($name, $options);
        }

        return $this->databases[$name];
    }

    /**
     * Validate database name.
     *
     * @throws ValidationException If database name is invalid
     */
    private function validateDatabaseName(string $name): void
    {
        if ($name !== Database::DSN_PATH_MEMORY && !preg_match(self::DATABASE_NAME_REGEX, $name)) {
            throw ValidationException::invalidNameFormat(
                $name,
                self::DATABASE_NAME_REGEX,
                'database'
            );
        }
    }

    /**
     * Create database instance.
     */
    private function createDatabaseInstance(string $name, array $options = []): Database
    {
        $dbPath = $this->buildDatabasePath($name);
        // Merge client global options with specific db options
        $finalOptions = array_merge($this->options, $options);
        $database = new Database($dbPath, $finalOptions);

        // Attach back-reference to client
        $database->client = $this;

        return $database;
    }

    /**
     * Build database file path.
     */
    private function buildDatabasePath(string $name): string
    {
        if ($this->path === Database::DSN_PATH_MEMORY) {
            return $this->path;
        }

        return sprintf('%s/%s.bangron', $this->path, $name);
    }



    /**
     * Magic getter for database access.
     *
     * Provides convenient property-style access to databases.
     * Note: For better IDE support and autocomplete, consider using
     * selectDB() instead.
     *
     * @param string $database Database name
     *
     * @return Database
     *
     * @example
     * ```php
     * $client = new Client('/path/to/db');
     * $db = $client->mydb; // Equivalent to $client->selectDB('mydb')
     * ```
     */
    public function __get(string $database): Database
    {
        return $this->selectDB($database);
    }

    /**
     * Close all database connections held by this client.
     */
    public function close(): void
    {
        foreach ($this->databases as $db) {
            if (is_object($db) && method_exists($db, 'close')) {
                $db->close();
            }
        }

        $this->databases = [];
    }

    /**
     * Destructor to ensure all connections are closed.
     */
    public function __destruct()
    {
        $this->close();
    }
}
