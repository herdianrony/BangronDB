<?php

namespace App\Services;

class BackupService
{
    private SystemService $system;

    public function __construct()
    {
        $this->system = new SystemService();
    }

    public function ensureBackupCollection(): void
    {
        $db = $this->system->systemDb();
        $db->createCollection('backup_registry');
    }

    public function createBackup(string $dbName, string $createdBy, string $type = 'manual'): array
    {
        $this->ensureBackupCollection();
        $source = tenant_path($dbName . '.bangron');
        if (!file_exists($source)) {
            throw new \RuntimeException('Database file not found');
        }

        $backupDir = storage_path('backups/' . $dbName);
        ensure_dir($backupDir);
        $fileName = date('Ymd_His') . '.snapshot';
        $target = $backupDir . '/' . $fileName;

        if (!copy($source, $target)) {
            throw new \RuntimeException('Failed to create backup file');
        }

        $record = [
            '_id' => uuid(),
            'database_name' => $dbName,
            'file_path' => $target,
            'size' => filesize($target) ?: 0,
            'created_by' => $createdBy,
            'created_at' => date('c'),
            'type' => $type,
        ];

        $this->system->systemDb()->selectCollection('backup_registry')->insert($record);

        return $record;
    }

    public function listBackups(string $dbName): array
    {
        $this->ensureBackupCollection();
        return $this->system->systemDb()
            ->selectCollection('backup_registry')
            ->find(['database_name' => $dbName])
            ->sort(['created_at' => -1])
            ->toArray();
    }

    public function listAllBackups(): array
    {
        $this->ensureBackupCollection();
        return $this->system->systemDb()
            ->selectCollection('backup_registry')
            ->find()
            ->sort(['created_at' => -1])
            ->toArray();
    }

    public function pruneBackups(string $dbName, int $keepLatest = 10): int
    {
        $this->ensureBackupCollection();
        $keepLatest = max(1, $keepLatest);
        $records = $this->listBackups($dbName);
        if (count($records) <= $keepLatest) {
            return 0;
        }

        $deleted = 0;
        $toDelete = array_slice($records, $keepLatest);
        $registry = $this->system->systemDb()->selectCollection('backup_registry');
        foreach ($toDelete as $row) {
            if (!empty($row['file_path']) && file_exists($row['file_path'])) {
                @unlink($row['file_path']);
            }
            $registry->remove(['_id' => $row['_id']]);
            ++$deleted;
        }

        return $deleted;
    }

    public function restoreBackup(string $backupId, string $restoredBy): array
    {
        $this->ensureBackupCollection();
        $registry = $this->system->systemDb()->selectCollection('backup_registry');
        $backup = $registry->findOne(['_id' => $backupId]);
        if (!$backup) {
            throw new \RuntimeException('Backup not found');
        }
        if (!file_exists($backup['file_path'])) {
            throw new \RuntimeException('Snapshot file is missing');
        }

        $dbName = $backup['database_name'];
        $dest = tenant_path($dbName . '.bangron');
        $safeSnapshot = $this->createBackup($dbName, $restoredBy, 'pre_restore');

        if (!copy($backup['file_path'], $dest)) {
            throw new \RuntimeException('Restore failed');
        }

        return [
            'database_name' => $dbName,
            'restored_from' => $backupId,
            'safety_snapshot' => $safeSnapshot['_id'],
        ];
    }
}
