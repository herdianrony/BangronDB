<?php

namespace App\Controllers;

use App\Services\SystemService;

class RoleController extends BaseController
{
    private SystemService $system;

    public function __construct()
    {
        parent::__construct();
        $this->system = new SystemService();
    }

    public function index(): void
    {
        $this->requireLogin();
        render('roles/index', [
            'title' => 'Roles',
            'pageTitle' => 'Roles',
            'pageSubtitle' => 'Role management',
            'navActive' => 'roles',
            'user' => $this->authService->currentUser(),
            'roles' => $this->system->systemDb()->roles->find()->toArray(),
        ]);
    }

    public function create(): void
    {
        $this->requireLogin();
        render('roles/create', [
            'title' => 'Create Role',
            'pageTitle' => 'Create Role',
            'pageSubtitle' => 'Add role',
            'navActive' => 'roles',
            'user' => $this->authService->currentUser(),
        ]);
    }

    public function store(): void
    {
        $this->requireLogin();
        verify_csrf();
        $name = trim((string) request_post('name'));
        if ($name === '') {
            flash('error', 'Role name required.');
            redirect('/roles/create');
        }
        if ($this->system->systemDb()->roles->findOne(['_id' => $name])) {
            flash('error', 'Role already exists.');
            redirect('/roles/create');
        }
        $this->system->systemDb()->roles->insert([
            '_id' => $name,
            'name' => $name,
            'description' => trim((string) request_post('description')),
            'created_at' => date('c'),
        ]);
        flash('success', 'Role created.');
        redirect('/roles');
    }

    public function show(string $id): void
    {
        $this->requireLogin();
        $role = $this->system->systemDb()->roles->findOne(['_id' => $id]);
        if (!$role) {
            \Flight::halt(404, 'Role not found');
        }
        render('roles/show', [
            'title' => 'Role Detail',
            'pageTitle' => 'Role Detail',
            'pageSubtitle' => $role['name'] ?? $id,
            'navActive' => 'roles',
            'user' => $this->authService->currentUser(),
            'role' => $role,
        ]);
    }

    public function edit(string $id): void
    {
        $this->requireLogin();
        $role = $this->system->systemDb()->roles->findOne(['_id' => $id]);
        if (!$role) {
            \Flight::halt(404, 'Role not found');
        }
        render('roles/edit', [
            'title' => 'Edit Role',
            'pageTitle' => 'Edit Role',
            'pageSubtitle' => $role['name'] ?? $id,
            'navActive' => 'roles',
            'user' => $this->authService->currentUser(),
            'role' => $role,
        ]);
    }

    public function update(string $id): void
    {
        $this->requireLogin();
        verify_csrf();
        $role = $this->system->systemDb()->roles->findOne(['_id' => $id]);
        if (!$role) {
            \Flight::halt(404, 'Role not found');
        }
        $this->system->systemDb()->roles->update(['_id' => $id], [
            'name' => trim((string) request_post('name', $role['name'] ?? $id)),
            'description' => trim((string) request_post('description', $role['description'] ?? '')),
        ]);
        flash('success', 'Role updated.');
        redirect('/roles');
    }

    public function delete(string $id): void
    {
        $this->requireLogin();
        verify_csrf();
        $this->system->systemDb()->roles->remove(['_id' => $id]);
        $this->system->systemDb()->role_permissions->remove(['role_id' => $id]);
        flash('success', 'Role deleted.');
        redirect('/roles');
    }

    public function permissions(string $id): void
    {
        $this->requireLogin();
        $role = $this->system->systemDb()->roles->findOne(['_id' => $id]);
        if (!$role) {
            \Flight::halt(404, 'Role not found');
        }
        $allPermissions = $this->system->systemDb()->permissions->find()->toArray();
        $rows = $this->system->systemDb()->role_permissions->find(['role_id' => $id])->toArray();
        $rolePermissions = array_map(static fn ($r) => $r['permission_key'] ?? '', $rows);
        render('roles/permissions', [
            'title' => 'Role Permissions',
            'pageTitle' => 'Role Permissions',
            'pageSubtitle' => $role['name'] ?? $id,
            'navActive' => 'roles',
            'user' => $this->authService->currentUser(),
            'role' => $role,
            'allPermissions' => $allPermissions,
            'rolePermissions' => $rolePermissions,
        ]);
    }

    public function updatePermissions(string $id): void
    {
        $this->requireLogin();
        verify_csrf();
        $selected = request_post('permissions', []);
        if (!is_array($selected)) {
            $selected = [];
        }
        $collection = $this->system->systemDb()->role_permissions;
        $collection->remove(['role_id' => $id]);
        foreach ($selected as $key) {
            $collection->insert([
                '_id' => uuid(),
                'role_id' => $id,
                'permission_key' => (string) $key,
            ]);
        }
        flash('success', 'Role permissions updated.');
        redirect('/roles/' . urlencode($id) . '/permissions');
    }

    public function hierarchy(): void
    {
        $this->requireLogin();
        render('roles/hierarchy', [
            'title' => 'Role Hierarchy',
            'pageTitle' => 'Role Hierarchy',
            'pageSubtitle' => 'Hierarchy placeholder',
            'navActive' => 'roles',
            'user' => $this->authService->currentUser(),
        ]);
    }

    public function updateHierarchy(): void
    {
        $this->requireLogin();
        verify_csrf();
        flash('success', 'Role hierarchy updated.');
        redirect('/roles/hierarchy');
    }

    public function exportMatrix(): void
    {
        $this->requireLogin();
        $roles = $this->system->systemDb()->roles->find()->toArray();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="role_matrix_' . date('Ymd_His') . '.csv"');
        echo "role,description\n";
        foreach ($roles as $role) {
            echo sprintf("\"%s\",\"%s\"\n", $role['name'] ?? '', str_replace('"', '""', (string) ($role['description'] ?? '')));
        }
    }

    public function import(): void
    {
        $this->requireLogin();
        render('roles/import', [
            'title' => 'Import Roles',
            'pageTitle' => 'Import Roles',
            'pageSubtitle' => 'Upload CSV',
            'navActive' => 'roles',
            'user' => $this->authService->currentUser(),
        ]);
    }

    public function importFile(): void
    {
        $this->requireLogin();
        verify_csrf();
        flash('success', 'Import endpoint ready.');
        redirect('/roles');
    }

    public function createFromTemplate(): void
    {
        $this->requireLogin();
        verify_csrf();
        flash('success', 'Template creation endpoint ready.');
        redirect('/roles');
    }

    public function bulkUpdate(): void
    {
        $this->requireLogin();
        verify_csrf();
        flash('success', 'Bulk update endpoint ready.');
        redirect('/roles');
    }
}

