<?php

namespace App\Controllers;

use App\Services\SystemService;

class UserController extends BaseController
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
        $users = $this->system->systemDb()->users->find()->sort(['created_at' => -1])->toArray();
        $roles = $this->system->systemDb()->roles->find()->toArray();
        render('users/index', [
            'title' => 'Users',
            'pageTitle' => 'Users',
            'pageSubtitle' => 'User management',
            'navActive' => 'users',
            'user' => $this->authService->currentUser(),
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    public function create(): void
    {
        $this->requireLogin();
        render('users/create', [
            'title' => 'Create User',
            'pageTitle' => 'Create User',
            'pageSubtitle' => 'Add new user',
            'navActive' => 'users',
            'user' => $this->authService->currentUser(),
            'roles' => $this->system->systemDb()->roles->find()->toArray(),
        ]);
    }

    public function store(): void
    {
        $this->requireLogin();
        verify_csrf();
        $email = trim((string) request_post('email'));
        $password = (string) request_post('password');
        if ($email === '' || $password === '') {
            flash('error', 'Email and password are required.');
            redirect('/users/create');
        }
        if ($this->system->systemDb()->users->findOne(['email' => $email])) {
            flash('error', 'Email already exists.');
            redirect('/users/create');
        }
        $name = trim((string) request_post('first_name') . ' ' . (string) request_post('last_name'));
        $this->system->systemDb()->users->insert([
            '_id' => uuid(),
            'name' => trim($name) ?: $email,
            'first_name' => trim((string) request_post('first_name')),
            'last_name' => trim((string) request_post('last_name')),
            'email' => $email,
            'username' => trim((string) request_post('username')),
            'phone' => trim((string) request_post('phone')),
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role_id' => trim((string) request_post('role_id', 'viewer')),
            'status' => trim((string) request_post('status', 'active')),
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ]);
        flash('success', 'User created.');
        redirect('/users');
    }

    public function show(string $id): void
    {
        $this->requireLogin();
        $user = $this->system->systemDb()->users->findOne(['_id' => $id]);
        if (!$user) {
            \Flight::halt(404, 'User not found');
        }
        render('users/show', [
            'title' => 'User Detail',
            'pageTitle' => 'User Detail',
            'pageSubtitle' => $user['email'] ?? $id,
            'navActive' => 'users',
            'user' => $this->authService->currentUser(),
            'target' => $user,
        ]);
    }

    public function edit(string $id): void
    {
        $this->requireLogin();
        $user = $this->system->systemDb()->users->findOne(['_id' => $id]);
        if (!$user) {
            \Flight::halt(404, 'User not found');
        }
        render('users/edit', [
            'title' => 'Edit User',
            'pageTitle' => 'Edit User',
            'pageSubtitle' => $user['email'] ?? $id,
            'navActive' => 'users',
            'user' => $this->authService->currentUser(),
            'target' => $user,
            'roles' => $this->system->systemDb()->roles->find()->toArray(),
        ]);
    }

    public function update(string $id): void
    {
        $this->requireLogin();
        verify_csrf();
        $existing = $this->system->systemDb()->users->findOne(['_id' => $id]);
        if (!$existing) {
            \Flight::halt(404, 'User not found');
        }
        $payload = [
            'first_name' => trim((string) request_post('first_name')),
            'last_name' => trim((string) request_post('last_name')),
            'name' => trim((string) request_post('first_name') . ' ' . (string) request_post('last_name')),
            'email' => trim((string) request_post('email', $existing['email'] ?? '')),
            'username' => trim((string) request_post('username')),
            'phone' => trim((string) request_post('phone')),
            'role_id' => trim((string) request_post('role_id', $existing['role_id'] ?? 'viewer')),
            'status' => trim((string) request_post('status', $existing['status'] ?? 'active')),
            'updated_at' => date('c'),
        ];
        $password = (string) request_post('password', '');
        if ($password !== '') {
            $payload['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        }
        $this->system->systemDb()->users->update(['_id' => $id], $payload);
        flash('success', 'User updated.');
        redirect('/users');
    }

    public function delete(string $id): void
    {
        $this->requireLogin();
        verify_csrf();
        $this->system->systemDb()->users->remove(['_id' => $id]);
        flash('success', 'User deleted.');
        redirect('/users');
    }

    public function permissions(string $id): void
    {
        $this->requireLogin();
        $target = $this->system->systemDb()->users->findOne(['_id' => $id]);
        if (!$target) {
            \Flight::halt(404, 'User not found');
        }
        $permissions = $this->system->systemDb()->permissions->find()->toArray();
        $rows = $this->system->systemDb()->user_permissions->find(['user_id' => $id])->toArray();
        $userPermissions = array_map(static fn ($r) => $r['permission_key'] ?? '', $rows);

        render('users/permissions', [
            'title' => 'User Permissions',
            'pageTitle' => 'User Permissions',
            'pageSubtitle' => $target['email'] ?? $id,
            'navActive' => 'users',
            'user' => $this->authService->currentUser(),
            'target' => $target,
            'permissions' => $permissions,
            'userPermissions' => $userPermissions,
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
        $userPermissions = $this->system->systemDb()->user_permissions;
        $userPermissions->remove(['user_id' => $id]);
        foreach ($selected as $permissionKey) {
            $userPermissions->insert([
                '_id' => uuid(),
                'user_id' => $id,
                'permission_key' => (string) $permissionKey,
                'created_at' => date('c'),
            ]);
        }
        flash('success', 'Permissions updated.');
        redirect('/users/' . urlencode($id) . '/permissions');
    }

    public function bulkDelete(): void
    {
        $this->requireLogin();
        verify_csrf();
        $ids = request_post('ids', []);
        if (!is_array($ids)) {
            $ids = [];
        }
        foreach ($ids as $id) {
            $this->system->systemDb()->users->remove(['_id' => (string) $id]);
        }
        flash('success', 'Bulk delete finished.');
        redirect('/users');
    }

    public function export(): void
    {
        $this->requireLogin();
        $users = $this->system->systemDb()->users->find()->toArray();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="users_' . date('Ymd_His') . '.csv"');
        echo "id,name,email,role,status,created_at\n";
        foreach ($users as $u) {
            echo sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $u['_id'] ?? '',
                str_replace('"', '""', (string) ($u['name'] ?? '')),
                $u['email'] ?? '',
                $u['role_id'] ?? '',
                $u['status'] ?? '',
                $u['created_at'] ?? ''
            );
        }
    }

    public function resetPassword(string $id): void
    {
        $this->requireLogin();
        verify_csrf();
        $newPassword = (string) request_post('password', 'admin12345');
        $this->system->systemDb()->users->update(['_id' => $id], [
            'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
            'updated_at' => date('c'),
        ]);
        flash('success', 'Password reset.');
        redirect('/users/' . urlencode($id));
    }

    public function toggleStatus(string $id): void
    {
        $this->requireLogin();
        verify_csrf();
        $u = $this->system->systemDb()->users->findOne(['_id' => $id]);
        if (!$u) {
            \Flight::halt(404, 'User not found');
        }
        $next = (($u['status'] ?? 'active') === 'active') ? 'suspended' : 'active';
        $this->system->systemDb()->users->update(['_id' => $id], ['status' => $next, 'updated_at' => date('c')]);
        flash('success', 'Status updated to ' . $next . '.');
        redirect('/users');
    }
}

