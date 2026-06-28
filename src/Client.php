<?php

declare(strict_types=1);

namespace BangronDB;

use BangronDB\Exceptions\DatabaseException;
use BangronDB\Exceptions\ValidationException;
use BangronDB\Security\FieldValidator;

/**
 * Client object for managing BangronDB databases.
 */
class Client
{
    /**
     * @var array<string,\BangronDB\Database>
     */
    protected array $databases = [];

    public string $path;
    protected array $options = [];

    private const DATABASE_NAME_REGEX = '/^[a-z0-9_-]+$/i';

    public function __construct(string $path, array $options = [])
    {
        $normalizedPath = $this->normalizePath($path);
        $basePath = isset($options['base_path']) && is_string($options['base_path'])
            ? $options['base_path']
            : null;

        $this->path = $normalizedPath === Database::DSN_PATH_MEMORY
            ? $normalizedPath
            : FieldValidator::validateDatabaseDirectoryPath($normalizedPath, $basePath);
        $this->options = $options;
    }

    private function normalizePath(string $path): string
    {
        return \rtrim($path, '/\\');
    }

    public function listDBs(): array
    {
        if ($this->path === Database::DSN_PATH_MEMORY) {
            return $this->getMemoryDatabaseNames();
        }

        return $this->getDiskDatabaseNames();
    }

    private function getMemoryDatabaseNames(): array
    {
        return array_keys($this->databases);
    }

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
            return [];
        }

        return $databases;
    }

    private function isDatabaseFile(\SplFileInfo $fileInfo): bool
    {
        return $fileInfo->getExtension() === 'bangron';
    }

    /**
     * Explicitly create a collection and return its instance.
     */
    public function createCollection(string $database, string $collection): Collection
    {
        $db = $this->dbExists($database)
            ? $this->selectDB($database)
            : $this->createDB($database);

        return $db->createCollection($collection);
    }

    public function collectionExists(string $database, string $collection): bool
    {
        if (!$this->dbExists($database)) {
            return false;
        }

        return $this->selectDB($database)->collectionExists($collection);
    }

    /**
     * List the names of all collections in a database.
     *
     * Returns an empty array if the database does not exist (mirrors
     * collectionExists() behaviour — no exception for a missing DB).
     *
     * @return array<int, string> Collection names.
     */
    public function listCollections(string $database): array
    {
        if (!$this->dbExists($database)) {
            return [];
        }

        return $this->selectDB($database)->getCollectionNames();
    }

    /**
     * Alias of listCollections() for convenience / discoverability.
     *
     * @return array<int, string> Collection names.
     */
    public function listCollection(string $database): array
    {
        return $this->listCollections($database);
    }

    public function renameCollection(string $database, string $oldName, string $newName): bool
    {
        if (!$this->dbExists($database)) {
            return false;
        }

        return $this->selectDB($database)->renameCollection($oldName, $newName);
    }

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

    public function selectCollection(string $database, string $collection): Collection
    {
        return $this->selectDB($database)->selectCollection($collection);
    }

    public function createDB(string $name, array $options = []): Database
    {
        $this->validateDatabaseName($name);

        if (!isset($this->databases[$name])) {
            $this->databases[$name] = $this->createDatabaseInstance($name, $options);
        }

        return $this->databases[$name];
    }

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

    public function dropDB(string $name): bool
    {
        $this->validateDatabaseName($name);

        if ($this->path === Database::DSN_PATH_MEMORY) {
            return $this->dropMemoryDatabase($name);
        }

        return $this->dropDiskDatabase($name);
    }

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
     * @throws DatabaseException
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

    private function validateDatabaseName(string $name): void
    {
        if ($name !== Database::DSN_PATH_MEMORY && !preg_match(self::DATABASE_NAME_REGEX, $name)) {
            throw ValidationException::invalidNameFormat($name, self::DATABASE_NAME_REGEX, 'database');
        }
    }

    private function createDatabaseInstance(string $name, array $options = []): Database
    {
        $dbPath = $this->buildDatabasePath($name);
        $validatedDbPath = $dbPath === Database::DSN_PATH_MEMORY
            ? $dbPath
            : FieldValidator::validateDatabasePath($dbPath, $this->path);
        $finalOptions = array_merge($this->options, $options);
        $database = new Database($validatedDbPath, $finalOptions);
        $database->client = $this;

        return $database;
    }

    private function buildDatabasePath(string $name): string
    {
        if ($this->path === Database::DSN_PATH_MEMORY) {
            return $this->path;
        }

        return sprintf('%s/%s.bangron', $this->path, $name);
    }

    private function dropMemoryDatabase(string $name): bool
    {
        if (!isset($this->databases[$name])) {
            return false;
        }

        $this->closeDatabaseHandle($name);

        return true;
    }

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

    private function renameMemoryDatabase(string $oldName, string $newName): bool
    {
        if (!isset($this->databases[$oldName])) {
            return false;
        }

        $this->databases[$newName] = $this->databases[$oldName];
        unset($this->databases[$oldName]);

        return true;
    }

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

    private function closeDatabaseHandle(string $name): void
    {
        if (!isset($this->databases[$name])) {
            return;
        }

        $this->databases[$name]->close();
        unset($this->databases[$name]);
    }

    private function deleteSidecarFiles(string $path): void
    {
        foreach ([$path . '-wal', $path . '-shm'] as $sidecar) {
            if (file_exists($sidecar)) {
                @unlink($sidecar);
            }
        }
    }

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

    public function __get(string $database): Database
    {
        return $this->selectDB($database);
    }

    public function close(): void
    {
        foreach ($this->databases as $db) {
            if (is_object($db) && method_exists($db, 'close')) {
                $db->close();
            }
        }

        $this->databases = [];
    }

    public function __destruct()
    {
        $this->close();
    }
}
