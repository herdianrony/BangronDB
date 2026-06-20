<?php

declare(strict_types=1);

namespace BangronDB;

use BangronDB\Exceptions\DatabaseException;
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
 * // Create database explicitly
 * $client->createDB('mydb');
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
     * Explicitly create a collection and return its instance.
     *
     * This is a convenience wrapper around Database::createCollection() plus
     * Database::selectCollection().
     */
    public function createCollection(string $database, string $collection): Collection
    {
        $db = $this->dbExists($database)
            ? $this->selectDB($database)
            : $this->createDB($database);

        return $db->createCollection($collection);
    }

    /**
     * Check whether a collection exists in a given database.
     */
    public function collectionExists(string $database, string $collection): bool
    {
        if (!$this->dbExists($database)) {
            return false;
        }

        return $this->selectDB($database)->collectionExists($collection);
    }

    /**
     * Rename a collection in a given database.
     */
    public function renameCollection(string $database, string $oldName, string $newName): bool
    {
        if (!$this->dbExists($database)) {
            return false;
        }

        return $this->selectDB($database)->renameCollection($oldName, $newName);
    }

    /**
     * Drop a collection in a given database.
     */
    public function dropCollection(string $database, string $collection): bool
    {
        if (!$this->dbExists($database)) {
            return false;
        }

        $db = $this->selectDB($database);
        if (!$db->collectionExists($collection)) {
            return false;
        }

        $db->dropCollection($collection);

        return true;
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
     * Explicitly create a database and return its instance.
     *
     * For backward compatibility, this method is idempotent and will return
     * the existing database instance if the database already exists or was
     * previously selected.
     *
     * @throws ValidationException If database name is invalid
     */
    public function createDB(string $name, array $options = []): Database
    {
        $this->validateDatabaseName($name);

        if (!isset($this->databases[$name])) {
            $this->databases[$name] = $this->createDatabaseInstance($name, $options);
        }

        return $this->databases[$name];
    }

    /**
     * Check whether a database exists.
     *
     * @throws ValidationException If database name is invalid
     */
    public function dbExists(string $name): bool
    {
        $this->validateDatabaseName($name);

        if (isset($this->databases[$name])) {
            return true;
        }

        if ($this->path === Database::DSN_PATH_MEMORY) {
            return false;
        }

        return file_exists($this->buildDatabasePath($name));
    }

    /**
     * Drop a database.
     *
     * @throws ValidationException If database name is invalid
     */
    public function dropDB(string $name): bool
    {
        $this->validateDatabaseName($name);

        if ($this->path === Database::DSN_PATH_MEMORY) {
            return $this->dropMemoryDatabase($name);
        }

        return $this->dropDiskDatabase($name);
    }

    /**
     * Rename a database.
     *
     * Note: if the database was already selected, any previously held Database
     * object becomes stale after rename. Re-select the database using the new
     * name to continue working with it.
     *
     * @throws ValidationException If database name is invalid
     */
    public function renameDB(string $oldName, string $newName): bool
    {
        $this->validateDatabaseName($oldName);
        $this->validateDatabaseName($newName);

        if ($oldName === $newName || $this->dbExists($newName)) {
            return false;
        }

        if ($this->path === Database::DSN_PATH_MEMORY) {
            return $this->renameMemoryDatabase($oldName, $newName);
        }

        return $this->renameDiskDatabase($oldName, $newName);
    }

    /**
     * Select database.
     *
     * @param string $name Database name
     *
     * @throws ValidationException If database name is invalid
     * @throws DatabaseException   If database does not exist
     */
    public function selectDB(string $name, array $options = []): Database
    {
        $this->validateDatabaseName($name);

        if (!isset($this->databases[$name])) {
            if (!$this->dbExists($name)) {
                throw DatabaseException::notFound($name, $this->buildDatabasePath($name));
            }

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
     * Drop an in-memory database.
     */
    private function dropMemoryDatabase(string $name): bool
    {
        if (!isset($this->databases[$name])) {
            return false;
        }

        $this->closeDatabaseHandle($name);

        return true;
    }

    /**
     * Drop a disk-backed database.
     */
    private function dropDiskDatabase(string $name): bool
    {
        $path = $this->buildDatabasePath($name);
        if (!$this->dbExists($name)) {
            return false;
        }

        $this->closeDatabaseHandle($name);

        $deleted = file_exists($path) ? @unlink($path) : true;
        $this->deleteSidecarFiles($path);

        return $deleted;
    }

    /**
     * Rename an in-memory database.
     */
    private function renameMemoryDatabase(string $oldName, string $newName): bool
    {
        if (!isset($this->databases[$oldName])) {
            return false;
        }

        $this->databases[$newName] = $this->databases[$oldName];
        unset($this->databases[$oldName]);

        return true;
    }

    /**
     * Rename a disk-backed database.
     */
    private function renameDiskDatabase(string $oldName, string $newName): bool
    {
        $oldPath = $this->buildDatabasePath($oldName);
        $newPath = $this->buildDatabasePath($newName);

        if (!file_exists($oldPath)) {
            return false;
        }

        $this->closeDatabaseHandle($oldName);

        if (!@rename($oldPath, $newPath)) {
            return false;
        }

        $this->renameSidecarFiles($oldPath, $newPath);

        return true;
    }

    /**
     * Close a cached database handle and remove it from cache.
     */
    private function closeDatabaseHandle(string $name): void
    {
        if (!isset($this->databases[$name])) {
            return;
        }

        $this->databases[$name]->close();
        unset($this->databases[$name]);
    }

    /**
     * Delete SQLite sidecar files if they exist.
     */
    private function deleteSidecarFiles(string $path): void
    {
        foreach ([$path . '-wal', $path . '-shm'] as $sidecar) {
            if (file_exists($sidecar)) {
                @unlink($sidecar);
            }
        }
    }

    /**
     * Rename SQLite sidecar files if they exist.
     */
    private function renameSidecarFiles(string $oldPath, string $newPath): void
    {
        foreach (['-wal', '-shm'] as $suffix) {
            $oldSidecar = $oldPath . $suffix;
            $newSidecar = $newPath . $suffix;
            if (file_exists($oldSidecar)) {
                @rename($oldSidecar, $newSidecar);
            }
        }
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
