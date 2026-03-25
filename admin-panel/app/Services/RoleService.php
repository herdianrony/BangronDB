<?php

namespace App\Services;

use Ramsey\Uuid\Uuid;

class RoleService
{
    private SystemService $system;
    private AuditService $auditService;

    public function __construct()
    {
        $this->system = new SystemService();
        $this->auditService = new AuditService();
    }

    public function getAllRoles(): array
    {
        $db = $this->system->systemDb();
        $roles = $db->roles;

        $result = [];
        foreach ($roles->find([]) as $role) {
            $role['user_count'] = $this->getUserCountByRole($role['_id']);
            $result[] = $role;
        }

        return $result;
    }

    public function getRoleById(string $id): ?array
    {
        $db = $this->system->systemDb();
        $roles = $db->roles;
        $role = $roles->findOne(['_id' => $id]);

        if ($role) {
            $role['permissions'] = $this->getRolePermissions($id);
        }

        return $role ?: null;
    }

    public function getRoleByName(string $name): ?array
    {
        $db = $this->system->systemDb();
        $roles = $db->roles;
        $role = $roles->findOne(['name' => $name]);

        if ($role) {
            $role['permissions'] = $this->getRolePermissions($role['_id']);
        }

        return $role ?: null;
    }

    public function createRole(array $data): ?array
    {
        $db = $this->system->systemDb();
        $roles = $db->roles;

        // Check if role name already exists
        if ($this->getRoleByName($data['name'])) {
            throw new \Exception('Role name already exists.');
        }

        $roleId = Uuid::uuid4()->toString();

        $role = [
            '_id' => $roleId,
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'type' => $data['type'] ?? 'custom',
            'is_protected' => $data['is_protected'] ?? false,
            'is_system_default' => $data['is_system_default'] ?? false,
            'permissions' => $data['permissions'] ?? [],
            'parent_role_id' => $data['parent_role_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'created_by' => $_SESSION['user_id'] ?? null,
        ];

        if ($roles->insert($role)) {
            return $role;
        }

        return null;
    }

    public function updateRole(string $id, array $data): bool
    {
        $db = $this->system->systemDb();
        $roles = $db->roles;

        $role = $roles->findOne(['_id' => $id]);
        if (!$role) {
            return false;
        }

        // Check if role name already exists for other roles
        if (isset($data['name']) && $data['name'] !== $role['name']) {
            if ($this->getRoleByName($data['name'])) {
                throw new \Exception('Role name already exists.');
            }
        }

        // Protected roles cannot be modified
        if ($role['is_protected']) {
            throw new \Exception('Cannot modify protected role.');
        }

        $updateData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'type' => $data['type'] ?? 'custom',
            'permissions' => $data['permissions'] ?? [],
            'parent_role_id' => $data['parent_role_id'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        return $roles->update(['_id' => $id], ['$set' => $updateData]);
    }

    public function deleteRole(string $id): bool
    {
        $db = $this->system->systemDb();
        $roles = $db->roles;
        $users = $db->users;

        $role = $roles->findOne(['_id' => $id]);
        if (!$role) {
            return false;
        }

        // Protected roles cannot be deleted
        if ($role['is_protected']) {
            throw new \Exception('Cannot delete protected role.');
        }

        // Check if role is assigned to any users
        $userCount = $users->count(['role_id' => $id]);
        if ($userCount > 0) {
            throw new \Exception('Cannot delete role that is assigned to users.');
        }

        // Check if role has child roles
        $childRoles = $roles->find(['parent_role_id' => $id]);
        if ($childRoles->count() > 0) {
            throw new \Exception('Cannot delete role that has child roles.');
        }

        return $roles->delete(['_id' => $id]);
    }

    public function getRolePermissions(string $roleId): array
    {
        $db = $this->system->systemDb();
        $rolePermissions = $db->role_permissions;

        $permissions = [];
        foreach ($rolePermissions->find(['role_id' => $roleId]) as $permission) {
            $permissions[] = $permission;
        }

        return $permissions;
    }

    public function updateRolePermissions(string $roleId, array $permissions): bool
    {
        $db = $this->system->systemDb();
        $rolePermissions = $db->role_permissions;

        // Delete existing permissions
        $rolePermissions->delete(['role_id' => $roleId]);

        // Insert new permissions
        foreach ($permissions as $permission) {
            $rolePermissions->insert([
                'role_id' => $roleId,
                'permission_key' => $permission['key'],
                'granted' => $permission['granted'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return true;
    }

    public function getUserCountByRole(string $roleId): int
    {
        $db = $this->system->systemDb();
        $users = $db->users;

        return $users->count(['role_id' => $roleId]);
    }

    public function getRoleHierarchy(): array
    {
        $db = $this->system->systemDb();
        $roles = $db->roles;

        $allRoles = [];
        foreach ($roles->find([]) as $role) {
            $allRoles[] = $role;
        }

        $hierarchy = [];
        foreach ($allRoles as $role) {
            if (!$role['parent_role_id']) {
                $hierarchy[] = $this->buildRoleTree($role, $allRoles);
            }
        }

        return $hierarchy;
    }

    private function buildRoleTree(array $role, array $allRoles): array
    {
        $tree = $role;
        $tree['children'] = [];

        foreach ($allRoles as $childRole) {
            if ($childRole['parent_role_id'] === $role['_id']) {
                $tree['children'][] = $this->buildRoleTree($childRole, $allRoles);
            }
        }

        return $tree;
    }

    public function getInheritedPermissions(string $roleId): array
    {
        $permissions = [];
        $visitedRoles = [];

        $this->collectInheritedPermissions($roleId, $permissions, $visitedRoles);

        return $permissions;
    }

    private function collectInheritedPermissions(string $roleId, array &$permissions, array &$visitedRoles): void
    {
        if (in_array($roleId, $visitedRoles)) {
            return;
        }

        $visitedRoles[] = $roleId;

        $role = $this->getRoleById($roleId);
        if (!$role) {
            return;
        }

        // Add role permissions
        foreach ($role['permissions'] as $permission) {
            $permissions[] = $permission;
        }

        // Get parent role permissions
        if ($role['parent_role_id']) {
            $this->collectInheritedPermissions($role['parent_role_id'], $permissions, $visitedRoles);
        }
    }

    public function validateRoleData(array $data, ?string $excludeId = null): array
    {
        $errors = [];

        // Validate role name
        if (empty($data['name'])) {
            $errors[] = 'Role name is required.';
        } elseif (strlen($data['name']) < 2) {
            $errors[] = 'Role name must be at least 2 characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9_\-\s]+$/', $data['name'])) {
            $errors[] = 'Role name can only contain letters, numbers, underscores, hyphens, and spaces.';
        } else {
            // Check if role name already exists
            $existingRole = $this->getRoleByName($data['name']);
            if ($existingRole && (!$excludeId || $existingRole['_id'] !== $excludeId)) {
                $errors[] = 'Role name already exists.';
            }
        }

        // Validate description
        if (!empty($data['description']) && strlen($data['description']) > 500) {
            $errors[] = 'Description must be less than 500 characters.';
        }

        // Validate permissions
        if (!empty($data['permissions'])) {
            foreach ($data['permissions'] as $permission) {
                if (empty($permission['key'])) {
                    $errors[] = 'Permission key is required.';
                }
                if (!isset($permission['granted'])) {
                    $errors[] = 'Permission status is required.';
                }
            }
        }

        // Validate parent role
        if (!empty($data['parent_role_id'])) {
            $parentRole = $this->getRoleById($data['parent_role_id']);
            if (!$parentRole) {
                $errors[] = 'Invalid parent role selected.';
            }
        }

        // Prevent circular references
        if (!empty($data['parent_role_id'])) {
            $this->checkCircularReference($data['parent_role_id'], $excludeId, $errors);
        }

        return $errors;
    }

    private function checkCircularReference(string $roleId, string $excludeId, array &$errors): void
    {
        $visitedRoles = [];

        if ($this->hasCircularReference($roleId, $excludeId, $visitedRoles)) {
            $errors[] = 'Circular reference detected in role hierarchy.';
        }
    }

    private function hasCircularReference(string $roleId, string $excludeId, array &$visitedRoles): bool
    {
        if (in_array($roleId, $visitedRoles)) {
            return true;
        }

        $visitedRoles[] = $roleId;

        $role = $this->getRoleById($roleId);
        if (!$role || $role['parent_role_id'] === $excludeId) {
            return false;
        }

        if ($role['parent_role_id']) {
            return $this->hasCircularReference($role['parent_role_id'], $excludeId, $visitedRoles);
        }

        return false;
    }

    public function getRoleTemplates(): array
    {
        return [
            [
                'name' => 'Super Admin',
                'description' => 'Full access to all system features',
                'type' => 'system',
                'is_protected' => true,
                'is_system_default' => true,
                'permissions' => [
                    ['key' => 'view_all', 'granted' => true],
                    ['key' => 'create_all', 'granted' => true],
                    ['key' => 'edit_all', 'granted' => true],
                    ['key' => 'delete_all', 'granted' => true],
                    ['key' => 'manage_users', 'granted' => true],
                    ['key' => 'manage_roles', 'granted' => true],
                    ['key' => 'manage_system', 'granted' => true],
                ],
            ],
            [
                'name' => 'Administrator',
                'description' => 'Full access to databases and user management',
                'type' => 'admin',
                'is_protected' => true,
                'is_system_default' => true,
                'permissions' => [
                    ['key' => 'view_all', 'granted' => true],
                    ['key' => 'create_all', 'granted' => true],
                    ['key' => 'edit_all', 'granted' => true],
                    ['key' => 'delete_all', 'granted' => true],
                    ['key' => 'manage_users', 'granted' => true],
                    ['key' => 'manage_roles', 'granted' => false],
                    ['key' => 'manage_system', 'granted' => false],
                ],
            ],
            [
                'name' => 'Editor',
                'description' => 'Create, edit, and delete documents',
                'type' => 'editor',
                'is_protected' => true,
                'is_system_default' => true,
                'permissions' => [
                    ['key' => 'view_all', 'granted' => true],
                    ['key' => 'create_all', 'granted' => true],
                    ['key' => 'edit_all', 'granted' => true],
                    ['key' => 'delete_all', 'granted' => true],
                    ['key' => 'manage_users', 'granted' => false],
                    ['key' => 'manage_roles', 'granted' => false],
                    ['key' => 'manage_system', 'granted' => false],
                ],
            ],
            [
                'name' => 'Viewer',
                'description' => 'Read-only access to data',
                'type' => 'viewer',
                'is_protected' => true,
                'is_system_default' => true,
                'permissions' => [
                    ['key' => 'view_all', 'granted' => true],
                    ['key' => 'create_all', 'granted' => false],
                    ['key' => 'edit_all', 'granted' => false],
                    ['key' => 'delete_all', 'granted' => false],
                    ['key' => 'manage_users', 'granted' => false],
                    ['key' => 'manage_roles', 'granted' => false],
                    ['key' => 'manage_system', 'granted' => false],
                ],
            ],
            [
                'name' => 'API User',
                'description' => 'Access for API integrations',
                'type' => 'api',
                'is_protected' => true,
                'is_system_default' => true,
                'permissions' => [
                    ['key' => 'view_all', 'granted' => true],
                    ['key' => 'create_all', 'granted' => true],
                    ['key' => 'edit_all', 'granted' => true],
                    ['key' => 'delete_all', 'granted' => false],
                    ['key' => 'manage_users', 'granted' => false],
                    ['key' => 'manage_roles', 'granted' => false],
                    ['key' => 'manage_system', 'granted' => false],
                ],
            ],
        ];
    }

    public function createRoleFromTemplate(array $template, array $customPermissions = []): ?array
    {
        $data = array_merge($template, [
            'permissions' => array_merge($template['permissions'], $customPermissions),
        ]);

        return $this->createRole($data);
    }

    public function exportRoleMatrix(): array
    {
        $roles = $this->getAllRoles();
        $allPermissions = $this->getAllPermissions();

        $matrix = [];
        foreach ($roles as $role) {
            $roleMatrix = [
                'role_name' => $role['name'],
                'role_type' => $role['type'],
                'user_count' => $role['user_count'],
                'permissions' => [],
            ];

            foreach ($allPermissions as $permission) {
                $granted = false;
                foreach ($role['permissions'] as $rolePermission) {
                    if ($rolePermission['key'] === $permission['key']) {
                        $granted = $rolePermission['granted'];
                        break;
                    }
                }

                $roleMatrix['permissions'][$permission['key']] = $granted;
            }

            $matrix[] = $roleMatrix;
        }

        return $matrix;
    }

    public function getAllPermissions(): array
    {
        return [
            ['key' => 'view_all', 'category' => 'General', 'description' => 'View all databases and collections'],
            ['key' => 'create_all', 'category' => 'General', 'description' => 'Create databases and collections'],
            ['key' => 'edit_all', 'category' => 'General', 'description' => 'Edit databases and collections'],
            ['key' => 'delete_all', 'category' => 'General', 'description' => 'Delete databases and collections'],
            ['key' => 'manage_users', 'category' => 'User Management', 'description' => 'Manage user accounts'],
            ['key' => 'manage_roles', 'category' => 'User Management', 'description' => 'Manage roles and permissions'],
            ['key' => 'manage_system', 'category' => 'System', 'description' => 'Manage system settings'],
            ['key' => 'view_logs', 'category' => 'System', 'description' => 'View system logs'],
            ['key' => 'export_data', 'category' => 'Data', 'description' => 'Export data from collections'],
            ['key' => 'import_data', 'category' => 'Data', 'description' => 'Import data to collections'],
            ['key' => 'manage_schema', 'category' => 'Schema', 'description' => 'Manage collection schemas'],
            ['key' => 'manage_indexes', 'category' => 'Schema', 'description' => 'Manage collection indexes'],
            ['key' => 'manage_encryption', 'category' => 'Security', 'description' => 'Manage encryption settings'],
            ['key' => 'manage_backup', 'category' => 'System', 'description' => 'Manage database backups'],
            ['key' => 'manage_monitoring', 'category' => 'System', 'description' => 'Manage system monitoring'],
        ];
    }

    public function getRoleStats(): array
    {
        $db = $this->system->systemDb();
        $roles = $db->roles;

        $totalRoles = count($roles->find([]));
        $systemRoles = count($roles->find(['is_system_default' => true]));
        $customRoles = count($roles->find(['is_system_default' => false]));
        $protectedRoles = count($roles->find(['is_protected' => true]));

        return [
            'total' => $totalRoles,
            'system' => $systemRoles,
            'custom' => $customRoles,
            'protected' => $protectedRoles,
        ];
    }
}
