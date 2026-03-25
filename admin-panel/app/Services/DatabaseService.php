<?php

namespace App\Services;

use BangronDB\Exceptions\ValidationException;

class DatabaseService
{
    private SystemService $system;
    private RegistryService $registry;

    public function __construct()
    {
        $this->system = new SystemService();
        $this->registry = new RegistryService();
    }

    public function syncRegistry(): void
    {
        $names = $this->registry->scanTenants(tenant_path());
        $this->registry->sync($names);
    }

    public function listDatabasesForUser(array $user): array
    {
        $this->syncRegistry();
        $registry = $this->system->systemDb()->database_registry;
        $all = $registry->find()->toArray();

        if (($user['role_id'] ?? '') === 'super_admin') {
            return $all;
        }

        $perms = $this->system->systemDb()->database_permissions;
        $userPerms = $perms->find(['user_id' => $user['_id']])->toArray();
        $allowed = array_column($userPerms, 'database_name');

        return array_values(array_filter($all, function ($db) use ($allowed) {
            return in_array($db['_id'], $allowed, true);
        }));
    }

    public function create(string $name, string $label, string $ownerId): array
    {
        $this->assertNotSystem($name);
        $this->validateDatabaseName($name);

        $registry = $this->system->systemDb()->database_registry;
        if ($registry->findOne(['_id' => $name])) {
            throw new \RuntimeException('Database already registered');
        }

        $client = $this->system->tenantClient();
        $client->selectDB($name);

        $entry = [
            '_id' => $name,
            'label' => $label ?: $name,
            'path' => tenant_path($name . '.bangron'),
            'owner_user_id' => $ownerId,
            'created_at' => date('c'),
            'status' => 'active',
            'source' => 'manual',
        ];
        $registry->insert($entry);

        $permissions = $this->system->systemDb()->database_permissions;
        $permissions->insert([
            'user_id' => $ownerId,
            'database_name' => $name,
            'role' => 'owner',
            'created_at' => date('c'),
        ]);

        return $entry;
    }

    public function getDatabase(string $name): ?array
    {
        $registry = $this->system->systemDb()->database_registry;
        $db = $registry->findOne(['_id' => $name]);

        return $db ?: null;
    }

    public function ensureRegistered(string $name): ?array
    {
        $existing = $this->getDatabase($name);
        if ($existing) {
            return $existing;
        }

        if ($this->isSystemDb($name)) {
            return null;
        }

        $path = tenant_path($name . '.bangron');
        if (!file_exists($path)) {
            return null;
        }

        $registry = $this->system->systemDb()->database_registry;
        $entry = [
            '_id' => $name,
            'label' => $name,
            'path' => $path,
            'owner_user_id' => null,
            'created_at' => date('c'),
            'status' => 'active',
            'source' => 'auto',
        ];
        $registry->insert($entry);

        return $entry;
    }

    public function userCanAccess(array $user, string $dbName, string $minRole = 'viewer'): bool
    {
        if ($this->isSystemDb($dbName)) {
            return ($user['role_id'] ?? '') === 'super_admin';
        }

        if (($user['role_id'] ?? '') === 'super_admin') {
            return true;
        }

        $dbRole = $this->getUserDbRole($user, $dbName);
        if (!$dbRole) {
            return false;
        }

        return $this->compareDbRole($dbRole, $minRole) >= 0;
    }

    public function getUserDbRole(array $user, string $dbName): ?string
    {
        $permissions = $this->system->systemDb()->database_permissions;
        $row = $permissions->findOne([
            'user_id' => $user['_id'],
            'database_name' => $dbName,
        ]);

        return $row['role'] ?? null;
    }

    public function setUserDbRole(string $userId, string $dbName, string $role): void
    {
        $permissions = $this->system->systemDb()->database_permissions;
        $existing = $permissions->findOne([
            'user_id' => $userId,
            'database_name' => $dbName,
        ]);
        if ($existing) {
            $permissions->update(['_id' => $existing['_id']], ['role' => $role]);
            return;
        }
        $permissions->insert([
            'user_id' => $userId,
            'database_name' => $dbName,
            'role' => $role,
            'created_at' => date('c'),
        ]);
    }

    public function listCollections(string $dbName): array
    {
        if ($this->isSystemDb($dbName)) {
            $db = $this->system->systemDb();
        } else {
            $client = $this->system->tenantClient();
            $db = $client->selectDB($dbName);
        }

        return $db->getCollectionNames();
    }

    public function isSystemDb(string $name): bool
    {
        return $name === config('system.db_name', 'admin');
    }

    public function assertNotSystem(string $name): void
    {
        if ($this->isSystemDb($name)) {
            throw new \RuntimeException('System database cannot be modified');
        }
    }

    private function validateDatabaseName(string $name): void
    {
        try {
            $client = $this->system->tenantClient();
            $client->selectDB($name);
        } catch (ValidationException $e) {
            throw new \RuntimeException('Invalid database name');
        }
    }

    private function compareDbRole(string $role, string $required): int
    {
        $map = ['viewer' => 1, 'admin' => 2, 'owner' => 3];

        return ($map[$role] ?? 0) <=> ($map[$required] ?? 0);
    }

    public function updateMetadata(string $name, array $changes): void
    {
        $registry = $this->system->systemDb()->database_registry;
        $existing = $registry->findOne(['_id' => $name]);
        if (!$existing) {
            throw new \RuntimeException('Database not found in registry');
        }

        $allowed = ['label', 'status', 'owner_user_id', 'source', 'path'];
        $safeChanges = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $changes)) {
                $safeChanges[$key] = $changes[$key];
            }
        }

        if (!$safeChanges) {
            return;
        }

        $registry->update(['_id' => $name], $safeChanges);
    }

    public function getHealthReport(string $dbName): array
    {
        $db = $this->system->tenantClient()->selectDB($dbName);
        $report = $db->getHealthReport();

        return [
            'status' => $report['status'] ?? 'warning',
            'issues' => $report['issues'] ?? [],
            'warnings' => $report['warnings'] ?? [],
            'recommendations' => $report['recommendations'] ?? [],
        ];
    }

    public function getTotalSize(): int
    {
        $total = 0;
        $files = glob(tenant_path('*.bangron')) ?: [];
        foreach ($files as $file) {
            $size = @filesize($file);
            if ($size !== false) {
                $total += $size;
            }
        }

        return $total;
    }

    public function getPageCount(): int
    {
        $db = $this->sampleTenantDatabase();
        if (!$db) {
            return 0;
        }
        $stmt = $db->connection->query('PRAGMA page_count');
        $value = $stmt ? (int) $stmt->fetchColumn() : 0;

        return $value;
    }

    public function getPageSize(): int
    {
        $db = $this->sampleTenantDatabase();
        if (!$db) {
            return 0;
        }
        $stmt = $db->connection->query('PRAGMA page_size');
        $value = $stmt ? (int) $stmt->fetchColumn() : 0;

        return $value;
    }

    public function getFragmentation(): float
    {
        $db = $this->sampleTenantDatabase();
        if (!$db) {
            return 0;
        }
        $metrics = $db->getPerformanceMetrics();

        return (float) ($metrics['fragmentation_ratio'] ?? 0);
    }

    public function getIndexCount(): int
    {
        $db = $this->sampleTenantDatabase();
        if (!$db) {
            return 0;
        }
        $stmt = $db->connection->query("SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND name NOT LIKE 'sqlite_%'");

        return $stmt ? (int) $stmt->fetchColumn() : 0;
    }

    public function getConnectionCount(): int
    {
        return 1;
    }

    public function getQueryCount(): int
    {
        return 0;
    }

    public function getSlowQueries(): int
    {
        return 0;
    }

    public function getCacheHitRate(): float
    {
        return 95.0;
    }

    public function getBackupStatus(): array
    {
        $registry = $this->system->systemDb()->selectCollection('backup_registry');
        $latest = $registry->find()->sort(['created_at' => -1])->limit(1)->toArray();

        return [
            'enabled' => true,
            'last_backup' => $latest[0]['created_at'] ?? null,
            'count' => $registry->count(),
        ];
    }

    private function sampleTenantDatabase()
    {
        $names = $this->registry->scanTenants(tenant_path());
        if (empty($names)) {
            return null;
        }

        return $this->system->tenantClient()->selectDB($names[0]);
    }
}
