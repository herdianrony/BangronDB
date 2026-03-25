<?php

namespace App\Services;

class SetupService
{
    private SystemService $system;

    public function __construct()
    {
        $this->system = new SystemService();
    }

    public function isInstalled(): bool
    {
        return file_exists(config('installed.lock'));
    }

    public function ensureBaselineSchema(): void
    {
        if (!$this->isInstalled()) {
            return;
        }
        $db = $this->system->systemDb();
        $this->createCollections($db);
        $this->seedRolesAndPermissions($db);
    }

    public function install(string $name, string $email, string $password): void
    {
        ensure_dir(storage_path());
        ensure_dir(admin_storage_path());
        ensure_dir(tenant_path());

        $db = $this->system->systemDb();
        $this->createCollections($db);
        $this->seedRolesAndPermissions($db);
        $this->createAdminUser($db, $name, $email, $password);

        file_put_contents(config('installed.lock'), 'installed=' . date('c'));
    }

    private function createCollections($db): void
    {
        $collections = [
            'users',
            'roles',
            'permissions',
            'role_permissions',
            'user_permissions',
            'database_registry',
            'database_permissions',
            'audit_logs',
            'backup_registry',
            'backup_policies',
            'notifications',
            'query_history',
            'security_policies',
            'api_tokens',
            'terminal_logs',
            'settings',
        ];

        foreach ($collections as $name) {
            $db->createCollection($name);
        }
    }

    private function seedRolesAndPermissions($db): void
    {
        $roles = [
            'super_admin' => 'Full access',
            'admin' => 'Manage databases and collections',
            'operator' => 'Operate collections and documents',
            'viewer' => 'Read-only access',
        ];

        $permissions = [
            'database.create' => 'Create databases',
            'database.delete' => 'Delete databases',
            'database.backup' => 'Create database backups',
            'database.restore' => 'Restore database from backup',
            'collection.manage' => 'Manage collections',
            'document.read' => 'Read documents',
            'document.write' => 'Write documents',
            'audit.read' => 'Read audit logs',
            'query.run' => 'Run query playground',
            'security.manage' => 'Manage security policy and keys',
        ];

        $rolesCollection = $db->roles;
        $permissionsCollection = $db->permissions;
        $rolePermissions = $db->role_permissions;

        foreach ($roles as $id => $desc) {
            if (!$rolesCollection->findOne(['_id' => $id])) {
                $rolesCollection->insert([
                    '_id' => $id,
                    'name' => $id,
                    'description' => $desc,
                    'created_at' => date('c'),
                ]);
            }
        }

        foreach ($permissions as $key => $desc) {
            if (!$permissionsCollection->findOne(['_id' => $key])) {
                $permissionsCollection->insert([
                    '_id' => $key,
                    'key' => $key,
                    'description' => $desc,
                ]);
            }
        }

        $roleMap = [
            'super_admin' => array_keys($permissions),
            'admin' => [
                'database.create',
                'database.delete',
                'database.backup',
                'database.restore',
                'collection.manage',
                'document.read',
                'document.write',
                'audit.read',
                'query.run',
                'security.manage',
            ],
            'operator' => [
                'collection.manage',
                'document.read',
                'document.write',
                'database.backup',
                'audit.read',
                'query.run',
            ],
            'viewer' => [
                'document.read',
                'audit.read',
            ],
        ];

        foreach ($roleMap as $roleId => $permKeys) {
            foreach ($permKeys as $perm) {
                $exists = $rolePermissions->findOne([
                    'role_id' => $roleId,
                    'permission_key' => $perm,
                ]);
                if (!$exists) {
                    $rolePermissions->insert([
                        'role_id' => $roleId,
                        'permission_key' => $perm,
                    ]);
                }
            }
        }
    }

    private function createAdminUser($db, string $name, string $email, string $password): void
    {
        $users = $db->users;
        if ($users->findOne(['email' => $email])) {
            return;
        }

        $users->insert([
            '_id' => uuid(),
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role_id' => 'super_admin',
            'status' => 'active',
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ]);
    }
}
